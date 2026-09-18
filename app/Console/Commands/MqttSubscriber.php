<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\Models\AcLog;
use Illuminate\Support\Carbon;
use PhpMqtt\Client\Exceptions\MqttClientException;

#[Signature('mqtt:subscribe')]
#[Description('Subscribe to EMQX MQTT topic to receive AC monitoring logs from ESP32/Raspberry Pi')]
class MqttSubscriber extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $server   = env('MQTT_HOST', '127.0.0.1');
        $port     = (int) env('MQTT_PORT', 1883);
        $clientId = env('MQTT_CLIENT_ID', 'laravel_mqtt_client') . '_' . rand(1000, 9999);
        $username = env('MQTT_USERNAME');
        $password = env('MQTT_PASSWORD');
        $topic    = 'pindad/ac/logs';

        if ($username === 'null') $username = null;
        if ($password === 'null') $password = null;

        $this->info("Connecting to MQTT Broker at {$server}:{$port}...");

        while (true) {
            try {
                $mqtt = new MqttClient($server, $port, $clientId);

                $connectionSettings = (new ConnectionSettings)
                    ->setUsername($username)
                    ->setPassword($password)
                    ->setKeepAliveInterval(60)
                    ->setLastWillTopic('pindad/ac/status')
                    ->setLastWillMessage('offline')
                    ->setLastWillQualityOfService(1);

                $mqtt->connect($connectionSettings, true);
                $this->info("Connected successfully! Subscribing to multi-device telemetry topics...");

                $processMessage = function (string $topic, string $message) {
                    $this->info("Received message on [{$topic}]: {$message}");

                    try {
                        $data = json_decode($message, true);

                        if (!$data) {
                            $this->error("Invalid JSON format received.");
                            return;
                        }

                        // Validate required keys
                        if (!isset($data['device_id']) || !isset($data['active_ac']) || !isset($data['current_ampere'])) {
                            $this->error("Missing required keys in payload.");
                            return;
                        }

                        $deviceId = $data['device_id'];
                        $recordedAt = isset($data['recorded_at']) 
                            ? Carbon::parse($data['recorded_at']) 
                            : now();

                        $activeAc = $data['active_ac'] ?? '';
                        $uNum = 1;
                        $uState = 'ON';

                        if (preg_match('/(?:AC|IN)[_\s]?(\d+)(?:[_\s]+([A-Za-z]+))?/i', $activeAc, $matches)) {
                            $uNum = (int)$matches[1];
                            $uState = isset($matches[2]) ? strtoupper($matches[2]) : (str_contains(strtoupper($activeAc), 'ON') ? 'ON' : 'OFF');
                        } elseif (isset($data['relay']) || isset($data['ac_number'])) {
                            $uNum = (int)($data['relay'] ?? $data['ac_number']);
                            $uState = strtoupper($data['command'] ?? $data['state'] ?? 'ON');
                        } elseif (str_contains(strtoupper($activeAc), 'OFF')) {
                            $uState = 'OFF';
                        }

                        $normalizedActiveAc = "AC_{$uNum}_{$uState}";

                        // Insert into AcLog
                        $log = AcLog::create([
                            'device_id'      => $deviceId,
                            'active_ac'      => $normalizedActiveAc,
                            'ac_number'      => $uNum,
                            'state'          => $uState,
                            'current_ampere' => (float) ($data['current_ampere'] ?? 0.0),
                            'recorded_at'    => $recordedAt,
                        ]);

                        // Sync with Device current_values if present
                        $dev = \App\Models\Device::where('device_id', $deviceId)->first();
                        if ($dev) {
                            $vals = $dev->current_values ?? [];
                            $numAc = max(1, (int)($dev->num_ac ?? 2));
                            
                            $vals["V" . ($uNum - 1)] = ($uState === 'ON') ? 1 : 0;
                            $curPin = "V" . ($numAc + $uNum - 1);
                            $vals[$curPin] = ($uState === 'OFF') ? 0.0 : (float)($data['current_ampere'] ?? 0.0);
                            
                            $dev->status = 'online';
                            $dev->current_values = $vals;
                            $dev->save();
                        }

                        // Real-time Anomaly Detection & Emergency Telegram Alert
                        try {
                            app(\App\Services\AnomalyDetectorService::class)->evaluateTelemetry(
                                $deviceId,
                                $uNum,
                                $uState,
                                (float) ($data['current_ampere'] ?? 0.0),
                                $recordedAt
                            );
                        } catch (\Exception $ex) {
                            $this->error("Anomaly detector error: " . $ex->getMessage());
                        }

                        $this->info("Saved log ID {$log->id} to database (Device: {$log->device_id}, Unit: AC {$uNum} {$uState}, Current: {$log->current_ampere} A)");

                    } catch (\Exception $e) {
                        $this->error("Error processing message: " . $e->getMessage());
                    }
                };

                $mqtt->subscribe('pindad/ac/logs', $processMessage, 0);
                $mqtt->subscribe('pindad/ac/telemetry', $processMessage, 0);
                $mqtt->subscribe('pindad/devices/+/logs', $processMessage, 0);
                $mqtt->subscribe('pindad/devices/+/telemetry', $processMessage, 0);

                // Start loop to stay connected and process incoming messages
                $mqtt->loop(true);

            } catch (MqttClientException $e) {
                $this->error("MQTT client error: " . $e->getMessage() . ". Reconnecting in 5 seconds...");
                sleep(5);
            } catch (\Exception $e) {
                $this->error("General error: " . $e->getMessage() . ". Reconnecting in 5 seconds...");
                sleep(5);
            }
        }
    }
}

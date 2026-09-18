<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\Schedule;
use App\Models\Device;
use App\Services\MqttService;
use Illuminate\Support\Carbon;

#[Signature('ac:schedule-worker')]
#[Description('Automatic AC schedule evaluator that sends MQTT commands according to active schedule rules per device')]
class RunAcScheduler extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MqttService $mqttService)
    {
        $this->info("Starting AC Scheduler Automation Worker (Per-Device Multi-Tenancy)...");
        $this->info("Checking active schedule rules every 5 seconds...");

        $lastStates = []; // Key: device_id . '_' . relay -> bool

        while (true) {
            try {
                $now = Carbon::now('Asia/Jakarta');
                $nowTime = $now->format('H:i');

                // Get all active schedules
                $activeSchedules = Schedule::where('is_active', true)->get();

                // Distinct devices to evaluate
                $devices = Device::all();
                $deviceIds = $devices->pluck('device_id')->filter()->unique()->values()->toArray();
                if (!in_array('RPI3B_PINDAD_ROOM_1', $deviceIds)) {
                    $deviceIds[] = 'RPI3B_PINDAD_ROOM_1';
                }

                foreach ($deviceIds as $devId) {
                    $dev = $devices->firstWhere('device_id', $devId);
                    $numAc = max(1, (int)($dev->num_ac ?? 2));

                    $devSchedules = $activeSchedules->filter(function($s) use ($devId) {
                        if ($devId === 'RPI3B_PINDAD_ROOM_1') {
                            return empty($s->device_id) || $s->device_id === 'RPI3B_PINDAD_ROOM_1';
                        }
                        return $s->device_id === $devId;
                    });

                    // Track desired states for all 1..N units
                    $desiredStates = [];
                    for ($i = 1; $i <= $numAc; $i++) {
                        $desiredStates[$i] = false;
                    }

                    foreach ($devSchedules as $schedule) {
                        $start = Carbon::parse($schedule->start_time)->format('H:i');
                        $end = Carbon::parse($schedule->end_time)->format('H:i');

                        $isInsideWindow = false;
                        if ($start <= $end) {
                            $isInsideWindow = ($nowTime >= $start && $nowTime < $end);
                        } else {
                            $isInsideWindow = ($nowTime >= $start || $nowTime < $end);
                        }

                        $targetAc = $schedule->target_ac ?? 'all';
                        if ($isInsideWindow) {
                            if ($targetAc === 'all') {
                                for ($i = 1; $i <= $numAc; $i++) {
                                    $desiredStates[$i] = true;
                                }
                            } elseif (is_numeric($targetAc)) {
                                $tNum = (int)$targetAc;
                                if (isset($desiredStates[$tNum])) {
                                    $desiredStates[$tNum] = true;
                                }
                            }
                        }
                    }

                    // Evaluate and publish state transitions for each relay 1..N
                    for ($relay = 1; $relay <= $numAc; $relay++) {
                        $stateKey = "{$devId}_{$relay}";
                        $isDesiredOn = $desiredStates[$relay];

                        if (!isset($lastStates[$stateKey]) || $lastStates[$stateKey] !== $isDesiredOn) {
                            $lastStates[$stateKey] = $isDesiredOn;
                            $cmd = $isDesiredOn ? 'ON' : 'OFF';
                            $payload = json_encode([
                                'device_id' => $devId,
                                'relay' => $relay,
                                'ac_number' => $relay,
                                'command' => $cmd,
                                'state' => $cmd,
                                'source' => 'schedule',
                                'timestamp' => Carbon::now('Asia/Jakarta')->toIso8601String()
                            ]);

                            if ($devId === 'RPI3B_PINDAD_ROOM_1') {
                                $mqttService->publish('pindad/ac/schedule', $payload);
                            }
                            $mqttService->publish("pindad/devices/{$devId}/schedule", $payload);
                            $mqttService->publish("pindad/devices/{$devId}/control", $payload);
                            $this->info("[{$nowTime} WIB] [{$devId}] Sinyal Transisi Terkirim -> AC {$relay}: {$cmd}");
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->error("Scheduler evaluation error: " . $e->getMessage());
            }

            sleep(5);
        }
    }
}

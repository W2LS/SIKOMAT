<?php

namespace App\Services;

use App\Models\Device;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AnomalyDetectorService
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Evaluate incoming telemetry data for potential AC failures.
     */
    public function evaluateTelemetry(string $deviceId, int $acNumber, string $state, float $currentAmpere, string $recordedAt): array
    {
        $device = Device::where('device_id', $deviceId)->first();
        $roomName = $device->name ?? $deviceId;
        $location = $device->location ?? 'PT PINDAD (PERSERO)';
        
        // Resolve custom AC unit name from template if configured
        $unitName = "AC {$acNumber}";
        if ($device && $device->template_id) {
            $tmpl = \App\Models\Template::find($device->template_id);
            if ($tmpl && isset($tmpl->datastreams)) {
                $stream = collect($tmpl->datastreams)->firstWhere('pin', 'V' . ($acNumber - 1));
                if ($stream && !empty($stream['name'])) {
                    $unitName = $stream['name'];
                }
            }
        }

        $cacheKeyFailure = "ac_failure_{$deviceId}_{$acNumber}";
        $cacheKeyCooldown = "ac_alert_cooldown_{$deviceId}_{$acNumber}";
        $isFailing = Cache::get($cacheKeyFailure, false);
        $isCooldown = Cache::has($cacheKeyCooldown);

        $cooldownMinutes = (int)SystemSetting::get('telegram_cooldown_minutes', 15);

        // CASE 1: ANOMALI GAGAL HIDUP (Saklar ON tapi Arus 0 Ampere)
        if ($state === 'ON' && $currentAmpere < 0.15) {
            Cache::forever($cacheKeyFailure, [
                'device_id' => $deviceId,
                'room_name' => $roomName,
                'location' => $location,
                'ac_number' => $acNumber,
                'unit_name' => $unitName,
                'current_ampere' => $currentAmpere,
                'detected_at' => $recordedAt,
                'status' => 'GAGAL_HIDUP',
            ]);

            if (!$isCooldown) {
                // Set cooldown timer
                Cache::put($cacheKeyCooldown, true, now()->addMinutes($cooldownMinutes));

                // Send Telegram Emergency Alert
                $res = $this->telegramService->sendFailureAlert(
                    $roomName,
                    $location,
                    $deviceId,
                    $acNumber,
                    $unitName,
                    $currentAmpere,
                    $recordedAt,
                    'Kompresor Mati / Beban Nol (0 A)'
                );

                Log::warning("[AnomalyDetector] Sent Emergency Alert to Telegram for {$deviceId} Unit {$acNumber}");
                return [
                    'anomaly_detected' => true,
                    'type' => 'GAGAL_HIDUP',
                    'telegram_sent' => $res['success'] ?? false,
                ];
            }

            return [
                'anomaly_detected' => true,
                'type' => 'GAGAL_HIDUP',
                'telegram_sent' => false,
                'reason' => 'in_cooldown',
            ];
        }

        // CASE 2: PEMULIHAN SISTEM (Arus kembali normal saat ON)
        if ($state === 'ON' && $currentAmpere >= 0.15 && $isFailing) {
            Cache::forget($cacheKeyFailure);
            Cache::forget($cacheKeyCooldown);

            // Send Telegram Recovery Notification
            $res = $this->telegramService->sendRecoveryAlert(
                $roomName,
                $location,
                $deviceId,
                $acNumber,
                $unitName,
                $currentAmpere,
                $recordedAt
            );

            Log::info("[AnomalyDetector] Sent Recovery Alert to Telegram for {$deviceId} Unit {$acNumber}");
            return [
                'recovered' => true,
                'telegram_sent' => $res['success'] ?? false,
            ];
        }

        // CASE 3: NORMAL STANDBY (Saklar OFF dan Arus 0)
        if ($state === 'OFF') {
            Cache::forget($cacheKeyFailure);
        }

        return [
            'anomaly_detected' => false,
        ];
    }

    /**
     * Get all active failure anomalies across all devices.
     */
    public function getActiveAnomalies(): array
    {
        $devices = Device::all();
        $anomalies = [];

        foreach ($devices as $dev) {
            $numAc = max(1, (int)($dev->num_ac ?? 2));
            for ($i = 1; $i <= $numAc; $i++) {
                $cacheKey = "ac_failure_{$dev->device_id}_{$i}";
                $failData = Cache::get($cacheKey);
                if ($failData) {
                    $anomalies[] = $failData;
                }
            }
        }

        return $anomalies;
    }
}

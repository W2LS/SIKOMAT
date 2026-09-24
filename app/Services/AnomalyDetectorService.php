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

        $minThreshold = (float)SystemSetting::get('anomaly_min_current', 0.005);
        $cooldownMinutes = (int)SystemSetting::get('telegram_cooldown_minutes', 5);

        // KETENTUAN MURNI: Kalau ada arus = AC NYALA, kalau tidak ada arus = AC MATI
        $isCurrentFlowing = ($currentAmpere >= $minThreshold);
        $currentStatus = $isCurrentFlowing ? 'NYALA' : 'MATI';

        $cacheKeyStatus = "ac_state_status_{$deviceId}_{$acNumber}";
        $cacheKeyCooldown = "ac_notif_cooldown_{$deviceId}_{$acNumber}";
        $cacheKeyFailure = "ac_failure_{$deviceId}_{$acNumber}";

        $lastStatus = Cache::get($cacheKeyStatus, null);
        $inCooldown = Cache::has($cacheKeyCooldown);

        // Update cache failure untuk dashboard visual warning jika saklar ON tapi AC MATI
        if ($state === 'ON' && !$isCurrentFlowing) {
            Cache::forever($cacheKeyFailure, [
                'device_id' => $deviceId,
                'room_name' => $roomName,
                'location' => $location,
                'ac_number' => $acNumber,
                'unit_name' => $unitName,
                'current_ampere' => $currentAmpere,
                'detected_at' => (string)$recordedAt,
                'status' => 'AC_MATI_TANPA_ARUS',
            ]);
        } else {
            Cache::forget($cacheKeyFailure);
        }

        // Inisialisasi awal tanpa spam notifikasi saat node pertama kali terhubung
        if ($lastStatus === null) {
            Cache::forever($cacheKeyStatus, $currentStatus);
            return [
                'status' => $currentStatus,
                'notif_sent' => false,
                'reason' => 'initial_state'
            ];
        }

        // HANYA kirim notifikasi jika TERJADI PERUBAHAN STATUS (Mati -> Nyala ATAU Nyala -> Mati)
        if ($currentStatus !== $lastStatus) {
            Cache::forever($cacheKeyStatus, $currentStatus);

            if (!$inCooldown) {
                Cache::put($cacheKeyCooldown, true, now()->addMinutes($cooldownMinutes));

                if ($currentStatus === 'NYALA') {
                    $res = $this->telegramService->sendAcOnNotification(
                        $roomName,
                        $location,
                        $deviceId,
                        $acNumber,
                        $unitName,
                        $currentAmpere,
                        (string)$recordedAt
                    );
                    Log::info("[Notification] Sent AC NYALA notification to Telegram for {$deviceId} Unit {$acNumber}");
                } else {
                    $res = $this->telegramService->sendAcOffNotification(
                        $roomName,
                        $location,
                        $deviceId,
                        $acNumber,
                        $unitName,
                        $currentAmpere,
                        (string)$recordedAt
                    );
                    Log::info("[Notification] Sent AC MATI notification to Telegram for {$deviceId} Unit {$acNumber}");
                }

                return [
                    'status' => $currentStatus,
                    'notif_sent' => $res['success'] ?? false,
                    'transition' => "{$lastStatus}_TO_{$currentStatus}",
                ];
            }

            return [
                'status' => $currentStatus,
                'notif_sent' => false,
                'reason' => 'in_cooldown',
            ];
        }

        return [
            'status' => $currentStatus,
            'notif_sent' => false,
            'reason' => 'no_state_change',
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

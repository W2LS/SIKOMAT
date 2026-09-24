<?php

namespace App\Http\Controllers;

use App\Models\AcHourlySummary;
use App\Models\AcLog;
use App\Models\Device;
use App\Models\Schedule;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AnomalyDetectorService;
use App\Services\DataRetentionService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TelemetryLogController extends Controller
{
    /**
     * Ingestion endpoint untuk menerima telemetri dari node Raspberry Pi.
     */
    public function receiveTelemetry(Request $request)
    {
        $data = $request->all();
        if (empty($data)) {
            $raw = $request->getContent();
            $data = json_decode($raw, true) ?? [];
        }

        $deviceId = $data['device_id'] ?? $request->input('device_id');
        if (!$deviceId) {
            return response()->json(['error' => 'Missing device_id'], 400);
        }

        $activeAc = $data['active_ac'] ?? $request->input('active_ac', '');
        $uNum = 1;
        $uState = 'ON';

        if (isset($data['state']) && in_array(strtoupper($data['state']), ['ON', 'OFF'])) {
            $uState = strtoupper($data['state']);
            if (isset($data['ac_number']) || isset($data['relay'])) {
                $uNum = (int)($data['ac_number'] ?? $data['relay']);
            } elseif (!empty($activeAc) && preg_match('/(?:AC|IN)[_\s]?(\d+)/i', $activeAc, $matches)) {
                $uNum = (int)$matches[1];
            }
        } elseif (!empty($activeAc) && preg_match('/(?:AC|IN)[_\s]?(\d+)(?:[_\s]+([A-Za-z]+))?/i', $activeAc, $matches)) {
            $uNum = (int)$matches[1];
            $uState = isset($matches[2]) ? strtoupper($matches[2]) : (str_contains(strtoupper($activeAc), 'OFF') ? 'OFF' : 'ON');
        } elseif (isset($data['relay']) || isset($data['ac_number'])) {
            $uNum = (int)($data['relay'] ?? $data['ac_number']);
            $uState = strtoupper($data['command'] ?? $data['state'] ?? 'ON');
        } elseif (!empty($activeAc) && str_contains(strtoupper($activeAc), 'OFF')) {
            $uState = 'OFF';
        }

        $normalizedActiveAc = "AC_{$uNum}_{$uState}";
        $currentAmpere = (float)($data['current_ampere'] ?? $request->input('current_ampere', 0.0));
        $isBuffer = ($request->header('X-Pindad-Buffer') === '1');
        $recordedAt = isset($data['recorded_at']) ? Carbon::parse($data['recorded_at']) : now();

        // Jika request live tetapi jam RTC Raspberry Pi belum tersinkron (> 120s), gunakan waktu server sekarang
        if (!$isBuffer && abs(now()->diffInSeconds($recordedAt)) > 120) {
            $recordedAt = now();
        }

        $isBacklog = $isBuffer;

        // 1. Insert into AcLog (Preserve complete historic logging)
        $log = AcLog::create([
            'device_id'      => $deviceId,
            'active_ac'      => $normalizedActiveAc,
            'ac_number'      => $uNum,
            'state'          => $uState,
            'current_ampere' => $currentAmpere,
            'recorded_at'    => $recordedAt,
        ]);

        $dev = Device::where('device_id', $deviceId)->first();

        // 2. Sync with Device current_values (Pastikan device langsung online jika live)
        if ($dev) {
            $dev->status = 'online';
            if (!$isBacklog) {
                $vals = $dev->current_values ?? [];
                $numAc = max(1, (int)($dev->num_ac ?? 2));
                $vals["V" . ($uNum - 1)] = ($uState === 'ON') ? 1 : 0;
                $curPin = "V" . ($numAc + $uNum - 1);
                $vals[$curPin] = ($uState === 'OFF') ? 0.0 : $currentAmpere;

                // Recalculate combined wattage
                $totalCur = 0.0;
                for ($k = 1; $k <= $numAc; $k++) {
                    $kRelayOn = ($vals["V" . ($k - 1)] ?? 0) == 1;
                    $kCur = (float)($vals["V" . ($numAc + $k - 1)] ?? 0.0);
                    if (!$kRelayOn) $kCur = 0.0;
                    $totalCur += $kCur;
                }
                $vals["V" . ($numAc * 2)] = round($totalCur * 220);
                $dev->current_values = $vals;
            }
            $dev->save();
        }

        // 2.5 Real-time WebSocket Broadcast (Live only)
        if (!$isBacklog) {
            try {
                app(\App\Services\WebSocketBroadcastService::class)->broadcast('telemetry_updated', [
                    'device_id'      => $deviceId,
                    'ac_number'      => $uNum,
                    'state'          => $uState,
                    'current_ampere' => $currentAmpere,
                    'watt'           => round($currentAmpere * 220),
                    'active_ac'      => $normalizedActiveAc,
                    'recorded_at'    => $recordedAt->toIso8601String(),
                ]);
            } catch (\Throwable $e) {}

            // 3. Real-time Anomaly Detection & Emergency Telegram Alert
            try {
                app(AnomalyDetectorService::class)->evaluateTelemetry(
                    $deviceId,
                    $uNum,
                    $uState,
                    $currentAmpere,
                    $recordedAt
                );
            } catch (\Exception $e) {}
        }

        // 4. Fetch Active Schedules for Hardware RTC & Web Dual-Sync on Node
        $activeSchedules = Schedule::where('is_active', true)
            ->where(function($q) use ($deviceId) {
                $q->where('device_id', $deviceId);
                if ($deviceId === 'RPI3B_PINDAD_ROOM_1') {
                    $q->orWhereNull('device_id');
                }
            })
            ->get()
            ->map(function($s) {
                return [
                    'id' => (string)($s->_id ?? $s->id),
                    'label' => $s->label,
                    'start_time' => Carbon::parse($s->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($s->end_time)->format('H:i'),
                    'target_ac' => $s->target_ac ?? 'all',
                    'is_active' => (bool)$s->is_active,
                ];
            })
            ->values()
            ->toArray();

        $telegramConfig = [
            'enabled' => (bool)SystemSetting::get('telegram_alert_enabled', true),
            'bot_token' => SystemSetting::get('telegram_bot_token', ''),
            'chat_id' => SystemSetting::get('telegram_chat_id', ''),
            'cooldown_minutes' => (int)SystemSetting::get('telegram_cooldown_minutes', 15),
        ];

        return response()->json([
            'success'     => true,
            'device_id'   => $deviceId,
            'unit'        => $uNum,
            'state'       => $uState,
            'ampere'      => $currentAmpere,
            'watt'        => round($currentAmpere * 220),
            'status'      => 'online',
            'command'     => null,
            'server_time' => now()->toIso8601String(),
            'schedules'   => $activeSchedules,
            'telegram'    => $telegramConfig,
        ]);
    }

    /**
     * Ekspor log telemetri ke format CSV.
     */
    public function exportCsv(Request $request)
    {
        $userRole = session('user_role', 'admin');
        $userNip = session('user_nip', 'PINDAD-IOT-2026');
        $isSuperAdmin = ($userRole === 'admin' || $userNip === 'PINDAD-IOT-2026');
        $assignedDeviceIds = session('assigned_devices', []);

        $deviceId = $request->query('device_id', 'all');
        $query = AcLog::query();
        
        if ($isSuperAdmin) {
            if ($deviceId && $deviceId !== 'all') {
                $query->where('device_id', $deviceId);
                $fileName = "telemetri_pindad_{$deviceId}_" . date('Ymd_His') . ".csv";
            } else {
                $fileName = "telemetri_pindad_fleet_" . date('Ymd_His') . ".csv";
            }
        } else {
            // Operator Scoped to assigned devices
            if ($deviceId && $deviceId !== 'all') {
                if (in_array($deviceId, $assignedDeviceIds)) {
                    $query->where('device_id', $deviceId);
                } else {
                    $query->whereNull('_id');
                }
                $fileName = "telemetri_pindad_{$deviceId}_" . date('Ymd_His') . ".csv";
            } else {
                if (!empty($assignedDeviceIds)) {
                    $query->whereIn('device_id', $assignedDeviceIds);
                } else {
                    $query->whereNull('_id');
                }
                $fileName = "telemetri_pindad_operator_" . date('Ymd_His') . ".csv";
            }
        }

        $logs = ($isSuperAdmin || !empty($assignedDeviceIds) || ($deviceId && $deviceId !== 'all')) ? $query->latest('recorded_at')->take(500)->get() : collect();

        $headers = [
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename={$fileName}",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['ID', 'Device ID', 'Target AC', 'Arus (Ampere)', 'Estimasi Daya (Watt)', 'Waktu Pencatatan (WIB)'];

        $callback = function () use ($logs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($logs as $log) {
                $amp = (float) $log->current_ampere;
                $watt = round($amp * 220, 2);
                $time = Carbon::parse($log->recorded_at)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');

                fputcsv($file, [
                    $log->_id ?? $log->id,
                    $log->device_id ?? '-',
                    $log->active_ac,
                    $amp,
                    $watt,
                    $time,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Bersihkan log telemetri mentah seketika (Khusus Super Admin).
     */
    public function clearLogs(Request $request)
    {
        $userRole = session('user_role', 'admin');
        $userNip = session('user_nip', 'PINDAD-IOT-2026');

        // RBAC Matrix: Pruning / Hapus Log hanya diizinkan untuk Super Admin (Divisi TI)
        if ($userRole !== 'admin' && $userNip !== 'PINDAD-IOT-2026') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak: Hanya Super Admin (Divisi TI) yang berhak menghapus log telemetri.'
                ], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak: Hanya Super Admin (Divisi TI) yang berhak menghapus log.');
        }

        $deviceId = $request->input('device_id', 'all');

        if ($deviceId === 'all' || empty($deviceId)) {
            AcLog::truncate();
            $msg = "Seluruh log telemetri armada berhasil dibersihkan!";
        } else {
            AcLog::where('device_id', $deviceId)->delete();
            $msg = "Seluruh log telemetri untuk perangkat {$deviceId} berhasil dibersihkan!";
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Jalankan auto-pruning dan agregasi per jam untuk log mentah lama (Khusus Super Admin).
     */
    public function pruneLogs(Request $request, DataRetentionService $retentionService)
    {
        $userRole = session('user_role', 'admin');
        $userNip = session('user_nip', 'PINDAD-IOT-2026');

        // RBAC Matrix: Pruning / Hapus Log hanya diizinkan untuk Super Admin (Divisi TI)
        if ($userRole !== 'admin' && $userNip !== 'PINDAD-IOT-2026') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak: Hanya Super Admin (Divisi TI) yang berhak melakukan pruning log telemetri.'
                ], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak: Hanya Super Admin (Divisi TI) yang berhak melakukan pruning log.');
        }

        $days = (int)$request->input('days', 3);
        $deviceId = $request->input('device_id');

        $result = $retentionService->aggregateAndPrune($days, $deviceId);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->back()->with('success', $result['message'] ?? 'Proses retensi data selesai dijalankan.');
    }

    /**
     * Ambil statistik penyimpanan log telemetri di database.
     */
    public function getLogStats(DataRetentionService $retentionService)
    {
        $stats = $retentionService->getStorageStats();
        return response()->json([
            'success' => true,
            'stats'   => $stats,
        ]);
    }

    /**
     * Live Polling Endpoint untuk AJAX Dashboard (/api/logs & /api/live-status).
     */
    public function getLiveLogs(Request $request)
    {
        $deviceId = $request->query('device_id') ?? Device::first()?->device_id ?? 'RPI3B_PINDAD_ROOM_1';
        $device = Device::where('device_id', $deviceId)->first();

        $numAc = max(1, (int)($device?->num_ac ?? 2));
        $curVals = $device?->current_values ?? [];

        $lastLog = AcLog::where('device_id', $deviceId)->latest('recorded_at')->first();
        $isLive = false;
        if ($lastLog && Carbon::parse($lastLog->recorded_at)->diffInMinutes(now()) <= 3) {
            $isLive = true;
        } elseif ($device && $device->status === 'online') {
            $isLive = true;
        }

        $units = [];
        $totalCurrent = 0.0;
        $totalWatt = 0;

        for ($i = 1; $i <= $numAc; $i++) {
            $pinKey = 'V' . ($i - 1);
            $curPinKey = 'V' . ($numAc + $i - 1);

            // Live switch state strictly adheres to device current_values in MongoDB
            $isOn = false;
            if ($device && isset($curVals[$pinKey])) {
                $isOn = ((int)$curVals[$pinKey] === 1);
            }

            // Check latest specific unit log for live current / wattage
            $uLog = AcLog::where('device_id', $deviceId)
                ->where(function($q) use ($i) {
                    $q->where('active_ac', 'like', "AC_{$i}%")
                      ->orWhere('ac_number', $i);
                })
                ->latest('recorded_at')
                ->first();

            if (!$device || !isset($curVals[$pinKey])) {
                if ($uLog && !empty($uLog->state)) {
                    $isOn = (strtoupper($uLog->state) === 'ON');
                }
            }

            $ampere = 0.0;
            if ($isOn) {
                if ($uLog && (float)$uLog->current_ampere > 0.0) {
                    $ampere = (float)$uLog->current_ampere;
                } elseif (isset($curVals[$curPinKey]) && (float)$curVals[$curPinKey] > 0.0) {
                    $ampere = (float)$curVals[$curPinKey];
                }
            }
            $watt = round($ampere * 220);

            if ($isOn) {
                $totalCurrent += $ampere;
                $totalWatt += $watt;
            }

            // Get active shift text
            $sched = Schedule::where('device_id', $deviceId)
                ->where('is_active', true)
                ->whereIn('target_ac', [
                    (int)$i,
                    (string)$i,
                    "AC_{$i}",
                    "AC {$i}",
                    'all',
                    'ALL',
                    'all_on',
                    'ALL_ON',
                ])
                ->first();

            $shiftText = 'Belum Ada Jadwal';
            if ($sched) {
                $st = Carbon::parse($sched->start_time)->format('H:i');
                $et = Carbon::parse($sched->end_time)->format('H:i');
                $shiftText = "{$sched->label} ({$st} - {$et})";
            }

            $units[$i] = [
                'current' => round($ampere, 4),
                'watt'    => $watt,
                'status'  => $isOn ? 'ON' : 'OFF',
                'shift'   => $shiftText,
            ];
        }

        // Fetch recent 10 chart points
        $chartLogs = AcLog::where('device_id', $deviceId)
            ->latest('recorded_at')
            ->take(20)
            ->get()
            ->reverse();

        $labels = [];
        $dataAc1 = [];
        $dataAc2 = [];

        foreach ($chartLogs as $cl) {
            $timeLabel = Carbon::parse($cl->recorded_at)->setTimezone('Asia/Jakarta')->format('H:i:s');
            if (!in_array($timeLabel, $labels)) {
                $labels[] = $timeLabel;
            }
            $uNum = (int)($cl->ac_number ?? 1);
            if ($uNum === 1) {
                $dataAc1[] = (float)$cl->current_ampere;
            } elseif ($uNum === 2) {
                $dataAc2[] = (float)$cl->current_ampere;
            }
        }

        return response()->json([
            'status'    => 'success',
            'device_id' => $deviceId,
            'is_live'   => $isLive,
            'units'     => $units,
            'summary'   => [
                'total_current' => round($totalCurrent, 4),
                'total_watt'    => $totalWatt,
            ],
            'charts'    => [
                'labels' => array_slice($labels, -10),
                'ac1'    => array_slice($dataAc1, -10),
                'ac2'    => array_slice($dataAc2, -10),
            ]
        ]);
    }

    /**
     * Aliases for route compatibility
     */
    public function exportLogs(Request $request) { return $this->exportCsv($request); }
    public function pruneLogsManual(Request $request, DataRetentionService $retentionService) { return $this->pruneLogs($request, $retentionService); }
    public function getStats(DataRetentionService $retentionService) { return $this->getLogStats($retentionService); }
    public function getTelemetry(Request $request) { return $this->getLiveLogs($request); }
    public function ingestTelemetry(Request $request) { return $this->receiveTelemetry($request); }
    public function getLiveStatus(Request $request) { return $this->getLiveLogs($request); }
}

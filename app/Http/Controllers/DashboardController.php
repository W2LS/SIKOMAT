<?php

namespace App\Http\Controllers;

use App\Models\AcLog;
use App\Models\Device;
use App\Models\DeviceRequest;
use App\Models\Schedule;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\TwoFactorSetting;
use App\Models\User;
use App\Services\AnomalyDetectorService;
use App\Services\MqttService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected MqttService $mqttService;

    public function __construct(MqttService $mqttService)
    {
        $this->mqttService = $mqttService;
    }

    protected function getDeviceRelayCapacity(?Device $dev, ?Template $tmpl = null): int
    {
        $template = $tmpl ?? ($dev?->template ?? ($dev?->template_id ? Template::find($dev->template_id) : null));
        
        if ($template && !empty($template->datastreams)) {
            $relayCount = 0;
            foreach ($template->datastreams as $ds) {
                $pinName = strtolower($ds['name'] ?? '');
                $isTurboOrTotal = str_contains($pinName, 'total') || str_contains($pinName, 'turbo') || str_contains($pinName, 'priority') || str_contains($pinName, 'arus') || str_contains($pinName, 'ampere');
                if (($ds['type'] ?? '') === 'Integer' && ($ds['max'] ?? 1) == 1 && !$isTurboOrTotal) {
                    $relayCount++;
                }
            }
            if ($relayCount > 0) {
                return $relayCount;
            }
        }

        return max(1, (int)($dev?->num_ac ?? 2));
    }

    /**
     * Display the dashboard page with Multi-Device Fleet & Developer Zone support (Role-Based Scoped).
     */
    public function index(Request $request)
    {
        $userNip = session('user_nip', 'PINDAD-IOT-2026');
        $userRole = session('user_role', 'admin');
        $assignedDeviceIds = session('assigned_devices', []);

        // 1. Fetch Devices & Templates based on Role
        if ($userRole === 'admin' || $userNip === 'PINDAD-IOT-2026') {
            // Super Admin: Claim legacy devices and schedules if unassigned
            $legacyDevices = Device::whereNull('user_nip')->get();
            foreach ($legacyDevices as $ld) {
                $ld->user_nip = $userNip;
                $ld->save();
            }
            $legacySchedules = Schedule::whereNull('user_nip')->get();
            foreach ($legacySchedules as $ls) {
                $ls->user_nip = $userNip;
                $ls->save();
            }
            $devices = Device::all();
        } else {
            // Operator: Strictly load assigned devices
            if (empty($assignedDeviceIds)) {
                $dbUser = User::where('username', $userNip)->orWhere('nip', $userNip)->first();
                $assignedDeviceIds = $dbUser?->assigned_device_ids ?? [];
                session(['assigned_devices' => $assignedDeviceIds]);
            }
            $devices = Device::whereIn('device_id', $assignedDeviceIds)->get();
        }

        $allFleetDevices = Device::all(); // Full device list for Admin Management UI
        $allOperators = User::where('role', 'operator')->get();
        $allUsers = User::all();
        $templates = Template::all();

        // Bi-directional synchronization between device_id and filter_device so Home and Module 3 never desync
        $selectedDeviceId = $request->query('device_id') ?? $request->query('filter_device') ?? ($devices->first()?->device_id ?? null);
        $filterDevice = $request->query('filter_device') ?? $request->query('device_id') ?? $selectedDeviceId;
        $currentDevice = $selectedDeviceId ? $devices->firstWhere('device_id', $selectedDeviceId) : null;

        // 2. Build Dynamic Unit Data for the selected device (1, 2, 4, or N AC units)
        $unitData = [];
        $numAc = 0;
        $tmpl = null;
        $tmplStreams = [];

        if ($currentDevice) {
            $tmpl = $currentDevice->template ?? ($currentDevice->template_id ? Template::find($currentDevice->template_id) : null);
            $numAc = $this->getDeviceRelayCapacity($currentDevice, $tmpl);
            $tmplStreams = $tmpl->datastreams ?? [];

            for ($i = 1; $i <= $numAc; $i++) {
                $pinKey = 'V' . ($i - 1);
                $vState = (int)($currentDevice->current_values[$pinKey] ?? 0);
                
                $log = AcLog::where('device_id', $currentDevice->device_id)
                    ->where(function($q) use ($i) {
                        $q->where('active_ac', 'like', "AC_{$i}%")
                          ->orWhere('active_ac', 'like', "AC {$i}%")
                          ->orWhere('active_ac', 'like', "IN{$i}%")
                          ->orWhere('ac_number', $i);
                    })->orderBy('recorded_at', 'desc')->orderBy('_id', 'desc')->orderBy('id', 'desc')->first();
                
                $isOn = false;
                if (isset($currentDevice->current_values[$pinKey])) {
                    $isOn = ((int)$currentDevice->current_values[$pinKey] === 1);
                } elseif ($log) {
                    if (!empty($log->state)) {
                        $isOn = (strtoupper($log->state) === 'ON');
                    } elseif (str_contains(strtoupper($log->active_ac), 'OFF')) {
                        $isOn = false;
                    } elseif (str_contains(strtoupper($log->active_ac), 'ON')) {
                        $isOn = true;
                    } else {
                        $isOn = ($vState === 1);
                    }
                } else {
                    $isOn = ($vState === 1);
                }

                $curPin = 'V' . ($numAc + $i - 1);
                $ampere = 0.0;
                if ($isOn) {
                    if ($log && (float)$log->current_ampere > 0.0) {
                        $ampere = (float)$log->current_ampere;
                    } elseif (isset($currentDevice->current_values[$curPin]) && (float)$currentDevice->current_values[$curPin] > 0.0) {
                        $ampere = (float)$currentDevice->current_values[$curPin];
                    } else {
                        $ampere = 0.0; // Zero current when no physical load is measured
                    }
                }
                $watt = round($ampere * 220);
                $shift = $this->getActiveShiftText($i, $selectedDeviceId, $userNip);
                
                $gpioPin = match($i) {
                    1 => 17,
                    2 => 27,
                    3 => 22,
                    4 => 23,
                    5 => 24,
                    6 => 25,
                    7 => 5,
                    8 => 6,
                    default => 17 + $i,
                };
                
                $unitName = "AC {$i}";
                if ($selectedDeviceId === 'RPI3B_PINDAD_ROOM_1') {
                    $unitName = ($i === 1 ? 'Panasonic 1' : ($i === 2 ? 'Panasonic 2' : "Panasonic {$i}"));
                } else {
                    $streamName = collect($tmplStreams)->firstWhere('pin', $pinKey)['name'] ?? null;
                    if ($streamName) {
                        $unitName = $streamName;
                    }
                }

                $unitData[$i] = [
                    'number' => $i,
                    'name' => $unitName,
                    'gpio' => $gpioPin,
                    'gpio_pin' => $gpioPin,
                    'is_on' => $isOn,
                    'ampere' => $ampere,
                    'watt' => $watt,
                    'shift' => $shift,
                    'last_updated' => $log ? Carbon::parse($log->recorded_at)->diffForHumans() : 'Belum ada data',
                ];
            }
        }

        // Backward compatibility for dual AC unit views
        $latestAc1 = $unitData[1] ?? ['is_on' => false, 'ampere' => 0.0, 'watt' => 0, 'shift' => 'Belum Ada Jadwal'];
        $latestAc2 = $unitData[2] ?? ['is_on' => false, 'ampere' => 0.0, 'watt' => 0, 'shift' => 'Belum Ada Jadwal'];

        // 3. Telemetry Logs Query Scoped by Selected Device
        $logsQuery = AcLog::query();
        if ($filterDevice) {
            $logsQuery->where('device_id', $filterDevice);
        } elseif ($devices->isNotEmpty()) {
            $logsQuery->whereIn('device_id', $devices->pluck('device_id')->toArray());
        }

        // Apply Date Range Filter if provided
        if ($request->filled('start_date')) {
            $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
            $logsQuery->where('recorded_at', '>=', $startDate);
        }
        if ($request->filled('end_date')) {
            $endDate = Carbon::parse($request->input('end_date'))->endOfDay();
            $logsQuery->where('recorded_at', '<=', $endDate);
        }

        $recentLogsAll = (clone $logsQuery)->orderBy('recorded_at', 'desc')->orderBy('_id', 'desc')->orderBy('id', 'desc')->limit(10)->get();
        $recentLogsAc1 = (clone $logsQuery)->where(function($q) {
            $q->where('active_ac', 'like', 'AC_1%')->orWhere('active_ac', 'like', 'AC 1%')->orWhere('ac_number', 1);
        })->orderBy('recorded_at', 'desc')->orderBy('_id', 'desc')->orderBy('id', 'desc')->limit(10)->get();
        $recentLogsAc2 = (clone $logsQuery)->where(function($q) {
            $q->where('active_ac', 'like', 'AC_2%')->orWhere('active_ac', 'like', 'AC 2%')->orWhere('ac_number', 2);
        })->orderBy('recorded_at', 'desc')->orderBy('_id', 'desc')->orderBy('id', 'desc')->limit(10)->get();

        // Multi-Unit Dynamic Logs
        $recentLogsByUnit = [];
        $unitLogNames = [];
        $logNumAc = $numAc ?: 2;
        for ($u = 1; $u <= $logNumAc; $u++) {
            $recentLogsByUnit[$u] = (clone $logsQuery)->where(function($q) use ($u) {
                $q->where('active_ac', 'like', "AC_{$u}%")
                  ->orWhere('active_ac', 'like', "AC {$u}%")
                  ->orWhere('ac_number', $u);
            })->orderBy('recorded_at', 'desc')->orderBy('_id', 'desc')->orderBy('id', 'desc')->limit(10)->get();
            
            $unitLogNames[$u] = $unitData[$u]['name'] ?? "AC {$u}";
        }

        // 4. Schedules Scoped by selected device
        $schedQuery = Schedule::query();
        if ($selectedDeviceId) {
            $schedQuery->where(function($q) use ($selectedDeviceId) {
                $q->where('device_id', $selectedDeviceId);
                if ($selectedDeviceId === 'RPI3B_PINDAD_ROOM_1') {
                    $q->orWhereNull('device_id');
                }
            });
        }
        $schedules = $schedQuery->get();

        $shiftAc1 = $this->getActiveShiftText(1, $selectedDeviceId, $userNip);
        $shiftAc2 = $this->getActiveShiftText(2, $selectedDeviceId, $userNip);

        // 5. Multi-Device Fleet Stats
        $fleetStats = [];
        $totalFleetWatt = 0;
        $totalFleetCurrent = 0;
        $onlineCount = 0;

        foreach ($devices as $dev) {
            $devTmpl = $dev->template ?? ($dev->template_id ? Template::find($dev->template_id) : null);
            $devRelayCount = $this->getDeviceRelayCapacity($dev, $devTmpl);

            $lastLog = AcLog::where('device_id', $dev->device_id)->orderBy('_id', 'desc')->first();
            $isDevOnline = false;
            $logTime = $lastLog ? ($lastLog->created_at ?: $lastLog->recorded_at) : null;
            if ($logTime && Carbon::parse($logTime)->diffInMinutes(now()) <= 3) {
                $isDevOnline = true;
            } elseif ($dev->status === 'online') {
                $isDevOnline = true;
            }

            if ($isDevOnline) $onlineCount++;

            $devTotalWatt = 0;
            $devTotalCurrent = 0;
            $devActiveRelays = 0;

            for ($k = 1; $k <= $devRelayCount; $k++) {
                $pState = (int)($dev->current_values['V' . ($k - 1)] ?? 0);
                $pAmp = (float)($dev->current_values['V' . ($devRelayCount + $k - 1)] ?? 0);
                if ($pState === 1) {
                    $devActiveRelays++;
                    $devTotalCurrent += $pAmp;
                    $devTotalWatt += round($pAmp * 220);
                }
            }

            $totalFleetWatt += $devTotalWatt;
            $totalFleetCurrent += $devTotalCurrent;

            $fleetStats[$dev->device_id] = [
                'device' => $dev,
                'is_online' => $isDevOnline,
                'active_relays' => $devActiveRelays,
                'total_relays' => $devRelayCount,
                'total_watt' => $devTotalWatt,
                'total_current' => round($devTotalCurrent, 2),
                'last_seen' => $lastLog ? Carbon::parse($lastLog->recorded_at)->diffForHumans() : 'Belum ada data',
            ];
        }

        // Authenticated User Details
        $user = auth()->user() ?? User::where('username', $userNip)->orWhere('nip', $userNip)->first() ?? (object)[
            'name' => session('user_name', 'Dicky Akbar Syah Putra'),
            'email' => session('user_nip', 'PINDAD-IOT-2026') . '@pindad.com',
            'role' => $userRole,
        ];

        // Telegram Bot Alert Settings
        $telegramSettings = [
            'bot_token' => SystemSetting::get('telegram_bot_token', ''),
            'chat_id' => SystemSetting::get('telegram_chat_id', ''),
            'alert_enabled' => (bool)SystemSetting::get('telegram_alert_enabled', true),
            'cooldown_minutes' => (int)SystemSetting::get('telegram_cooldown_minutes', 15),
        ];
        $activeAnomalies = app(AnomalyDetectorService::class)->getActiveAnomalies();

        // Detected Server Host IP for LAN network access & IoT client setup
        $serverLanHost = $this->detectLanHost($request);

        // Device Requests (Tickets) for Admin & Operator
        if ($userRole === 'admin' || $userNip === 'PINDAD-IOT-2026') {
            $deviceRequests = DeviceRequest::orderBy('created_at', 'desc')->get();
            $pendingRequestCount = DeviceRequest::pending()->count();
            $myDeviceRequests = collect();
        } else {
            $deviceRequests = collect();
            $pendingRequestCount = 0;
            $myDeviceRequests = DeviceRequest::where('operator_username', $userNip)->orderBy('created_at', 'desc')->get();
        }

        $twoFactorSetting = TwoFactorSetting::where('user_nip', $userNip)->first();

        return view('dashboard', compact(
            'twoFactorSetting',
            'unitData', 'numAc',
            'latestAc1', 'latestAc2', 'recentLogsAll', 'recentLogsAc1', 'recentLogsAc2', 
            'recentLogsByUnit', 'unitLogNames', 'logNumAc',
            'schedules', 'shiftAc1', 'shiftAc2', 'devices', 'allFleetDevices', 'allOperators', 'allUsers', 
            'templates', 'selectedDeviceId', 'currentDevice', 'fleetStats', 'totalFleetWatt', 'totalFleetCurrent', 
            'onlineCount', 'filterDevice', 'user', 'telegramSettings', 'activeAnomalies', 'serverLanHost', 'deviceRequests', 'pendingRequestCount', 'myDeviceRequests'
        ));
    }

    /**
     * Helper to detect active LAN Host IP for Raspberry Pi setup and cross-PC LAN access.
     */
    protected function detectLanHost(Request $request): string
    {
        $host = $request->getHost();
        if ($host && $host !== '127.0.0.1' && $host !== 'localhost' && !str_starts_with($host, '172.')) {
            return $host;
        }

        // Detect real LAN IP on Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @exec('ipconfig', $out);
            if (!empty($out)) {
                foreach ($out as $line) {
                    if (preg_match('/(?:IPv4 Address|Alamat IPv4)[ .]*: ([\d.]+)/i', $line, $m)) {
                        $ip = trim($m[1]);
                        if ($ip !== '127.0.0.1' && !str_starts_with($ip, '169.254') && !str_starts_with($ip, '172.')) {
                            return $ip;
                        }
                    }
                }
            }
        }

        return $host ?: '127.0.0.1';
    }

    /**
     * Format shift description string for a given AC unit.
     */
    protected function getActiveShiftText(int $unitNumber, ?string $deviceId = null, ?string $userNip = null): string
    {
        if (!$deviceId) {
            return 'Belum Ada Jadwal';
        }

        $schedules = Schedule::where('device_id', $deviceId)
            ->where('is_active', true)
            ->whereIn('target_ac', [
                (int)$unitNumber,
                (string)$unitNumber,
                "AC_{$unitNumber}",
                "AC {$unitNumber}",
                'all',
                'ALL',
                'all_on',
                'ALL_ON',
            ])
            ->get();

        if ($schedules->isEmpty()) {
            return 'Belum Ada Jadwal';
        }

        $first = $schedules->first();
        $startTime = Carbon::parse($first->start_time)->format('H:i');
        $endTime = Carbon::parse($first->end_time)->format('H:i');
        return "{$first->label} ({$startTime} - {$endTime})";
    }
}

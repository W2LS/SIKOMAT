<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\User;

use App\Models\Device;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileSettingsController extends Controller
{
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
        ]);

        $user = auth()->user() ?? User::first();
        if ($user) {
            $user->name = $request->input('name');
            $user->email = $request->input('email');
            $user->save();
        }

        return redirect()->back()->with('success', 'Profil operator PT PINDAD berhasil diperbarui.');
    }

    /**
     * Update Password Operator.
     */

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $user = auth()->user() ?? User::first();
        if ($user && Hash::check($request->input('current_password'), $user->password)) {
            $user->password = Hash::make($request->input('new_password'));
            $user->save();
            return redirect()->back()->with('success', 'Kata sandi akun berhasil diubah.');
        }

        return redirect()->back()->with('error', 'Kata sandi saat ini tidak cocok!');
    }

    /**
     * Export telemetry logs to CSV with device filter (Scoped per User Account).
     */

    public function saveTelegramSettings(Request $request)
    {
        $userRole = session('user_role', 'admin');
        $userNip = session('user_nip', 'PINDAD-IOT-2026');

        // RBAC Matrix: Konfigurasi Bot TG hanya untuk Super Admin (Divisi TI)
        if ($userRole !== 'admin' && $userNip !== 'PINDAD-IOT-2026') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak: Konfigurasi Bot Telegram hanya untuk Super Admin (Divisi TI).'
                ], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak: Konfigurasi Bot Telegram hanya untuk Super Admin.');
        }

        $request->validate([
            'telegram_bot_token' => 'nullable|string',
            'telegram_chat_id' => 'nullable|string',
            'telegram_cooldown_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        SystemSetting::set('telegram_bot_token', $request->input('telegram_bot_token', ''));
        SystemSetting::set('telegram_chat_id', $request->input('telegram_chat_id', ''));
        SystemSetting::set('telegram_alert_enabled', $request->has('telegram_alert_enabled') ? true : false);
        SystemSetting::set('telegram_cooldown_minutes', (int)$request->input('telegram_cooldown_minutes', 15));

        return redirect()->back()->with('success', 'Konfigurasi Notifikasi Bot Telegram berhasil disimpan!');
    }

    /**
     * Send instant test message to Telegram (Khusus Super Admin).
     */
    public function testTelegramNotification(Request $request, TelegramService $telegramService)
    {
        $userRole = session('user_role', 'admin');
        $userNip = session('user_nip', 'PINDAD-IOT-2026');

        // RBAC Matrix: Konfigurasi Bot TG hanya untuk Super Admin (Divisi TI)
        if ($userRole !== 'admin' && $userNip !== 'PINDAD-IOT-2026') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Konfigurasi Bot Telegram hanya untuk Super Admin (Divisi TI).'
            ], 403);
        }

        $botToken = $request->input('telegram_bot_token');
        $chatId = $request->input('telegram_chat_id');

        $res = $telegramService->sendTestMessage($botToken, $chatId);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($res);
        }

        if ($res['success']) {
            return redirect()->back()->with('success', $res['message']);
        }

        return redirect()->back()->with('error', $res['message']);
    }

    /**
     * Ingest Real-Time Telemetry from Raspberry Pi Nodes (Dual-Sync HTTP REST + MQTT).
     */
    public function downloadScript(Request $request, string $type)
    {
        // Auto-detect Server LAN IP for Raspberry Pi connection
        $detectedHost = $this->detectLanHost($request);
        $brokerHost = $request->query('broker_host') ?: $detectedHost;
        $brokerPort = (int)$request->query('broker_port', 1883);

        // 1. Download tailored standalone Python script for a specific device (No JSON required!)
        if ($type === 'device' || $request->has('device_id')) {
            $deviceId = $request->query('device_id', $type);
            $dev = Device::where('device_id', $deviceId)->first();
            $roomName = $dev->name ?? $deviceId;
            $location = $dev->location ?? 'PT PINDAD (PERSERO)';
            $numAc = $dev->num_ac ?? 2;

            $baseCode = file_get_contents(base_path('scripts/pindad_universal_node.py'));
            
            // Standard Industrial Raspberry Pi pinout & ADS1115 ADC mapping for 1 to 8 AC units
            $tmpl = $dev?->template ?? ($dev?->template_id ? Template::find($dev->template_id) : null);
            $tmplStreams = $tmpl->datastreams ?? [];

            $standardPins = [
                1 => ['gpio' => 17, 'adc' => 0],
                2 => ['gpio' => 27, 'adc' => 1],
                3 => ['gpio' => 22, 'adc' => 2],
                4 => ['gpio' => 23, 'adc' => 3],
                5 => ['gpio' => 24, 'adc' => 0],
                6 => ['gpio' => 25, 'adc' => 1],
                7 => ['gpio' => 5,  'adc' => 2],
                8 => ['gpio' => 6,  'adc' => 3],
            ];

            $relays = [];
            $maxChannels = max(1, min(8, (int)$numAc));
            for ($i = 1; $i <= $maxChannels; $i++) {
                $pinInfo = $standardPins[$i] ?? ['gpio' => 17 + $i, 'adc' => ($i - 1) % 4];
                $streamName = collect($tmplStreams)->firstWhere('pin', 'V' . ($i - 1))['name'] ?? null;
                $uName = $streamName ?: "AC {$i}";
                if ($deviceId === 'RPI3B_PINDAD_ROOM_1') {
                    $uName = ($i === 1 ? 'Panasonic 1 (Lampu Bawah)' : ($i === 2 ? 'Panasonic 2 (Lampu Atas)' : "Panasonic {$i}"));
                }
                $relays[] = [
                    'ac_number' => $i,
                    'gpio_pin' => $pinInfo['gpio'],
                    'name' => $uName,
                    'adc_channel' => $pinInfo['adc'],
                ];
            }

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

            $customConfig = [
                'device_id' => $deviceId,
                'room_name' => $roomName,
                'location' => $location,
                'mqtt_broker_host' => $brokerHost,
                'mqtt_broker_port' => $brokerPort,
                'dashboard_http_url' => "http://" . $this->detectLanHost($request) . ":" . ($request->getPort() ?: 8000),
                'sophos_auth' => ['enabled' => true, 'user' => 'pin-00020', 'pass' => '5uiFS4eE', 'url' => 'https://sophostrn.pindad.com:8090/login.xml'],
                'telegram' => [
                    'enabled' => (bool)SystemSetting::get('telegram_alert_enabled', true),
                    'bot_token' => SystemSetting::get('telegram_bot_token', ''),
                    'chat_id' => SystemSetting::get('telegram_chat_id', ''),
                    'cooldown_minutes' => (int)SystemSetting::get('telegram_cooldown_minutes', 15),
                ],
                'relays' => $relays,
                'schedules' => $activeSchedules,
                'turbo_cooling_seconds' => 0,
                'telemetry_interval_seconds' => 15,
            ];

            $jsonConfigStr = json_encode($customConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $pyConfigStr = str_replace([': true', ': false', ': null'], [': True', ': False', ': None'], $jsonConfigStr);
            $indentedPyConfig = preg_replace('/^/m', '    ', $pyConfigStr);
            $replacement = "    default_config = " . ltrim($indentedPyConfig);
            
            $pattern = '/    default_config\s*=\s*\{.*?\n    \}/s';
            $tailoredCode = preg_replace($pattern, $replacement, $baseCode);

            $fileName = "pindad_node_" . strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $deviceId)) . ".py";

            return response($tailoredCode, 200, [
                'Content-Type' => 'text/x-python',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]);
        }

        if ($type === 'universal_node' || $type === 'node') {
            $path = base_path('scripts/pindad_universal_node.py');
            return response()->download($path, 'pindad_universal_node.py', [
                'Content-Type' => 'text/x-python',
            ]);
        }

        if ($type === 'config' || $type === 'node_config') {
            $path = base_path('scripts/node_config.json');
            return response()->download($path, 'node_config.json', [
                'Content-Type' => 'application/json',
            ]);
        }

        if ($type === 'setup' || $type === 'sh') {
            $path = base_path('scripts/setup_raspberry_pi.sh');
            return response()->download($path, 'setup_raspberry_pi.sh', [
                'Content-Type' => 'text/x-sh',
            ]);
        }

        if ($type === 'wizard') {
            $path = base_path('scripts/pindad_setup_wizard.py');
            return response()->download($path, 'pindad_setup_wizard.py', [
                'Content-Type' => 'text/x-python',
            ]);
        }

        return response()->json(['error' => 'Invalid script type requested'], 404);
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
     * Save Telegram Alert Settings.
     */

    public function manualPdf()
    {
        $user = auth()->user() ?? (object)[
            'name' => 'Dicky Akbar Syah Putra',
            'email' => 'dicky.akbar@pindad.com'
        ];
        $devices = Device::all();
        $templates = Template::all();

        return view('panduan-pdf', compact('user', 'devices', 'templates'));
    }
}

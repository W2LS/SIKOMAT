<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Template;
use App\Models\User;
use App\Services\MqttService;
use App\Services\WebSocketBroadcastService;
use Illuminate\Http\Request;

class AcControlController extends Controller
{
    protected MqttService $mqttService;

    public function __construct(MqttService $mqttService)
    {
        $this->mqttService = $mqttService;
    }

    /**
     * Check if current session user has access to control the target device.
     */
    protected function authorizeDeviceAccess(?string $deviceId): bool
    {
        $userRole = session('user_role', 'admin');
        if ($userRole === 'admin' || session('user_nip') === 'PINDAD-IOT-2026') {
            return true;
        }

        if (empty($deviceId)) {
            return false;
        }

        $assigned = session('assigned_devices', []);
        if (empty($assigned)) {
            $userNip = session('user_nip');
            $user = User::where('username', $userNip)->orWhere('nip', $userNip)->first();
            $assigned = $user?->assigned_device_ids ?? [];
            session(['assigned_devices' => $assigned]);
        }

        return in_array($deviceId, $assigned, true);
    }

    /**
     * Handle manual AC control toggle command via MQTT.
     */
    public function toggleAc(Request $request)
    {
        $cmdInput = $request->input('command') ?: $request->input('state');
        if ($cmdInput) {
            $request->merge(['command' => $cmdInput]);
        }

        $request->validate([
            'ac_number' => 'required|integer|min:1|max:8',
            'command' => 'required|string|in:ON,OFF,TOGGLE,on,off,toggle',
            'device_id' => 'nullable|string',
        ]);

        $acNumber = (int)$request->input('ac_number');
        $command = strtoupper($request->input('command'));
        $deviceId = $request->input('device_id') ?: 'RPI3B_SERVER_TELEPON';

        if (!$this->authorizeDeviceAccess($deviceId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Anda tidak memiliki hak kontrol untuk perangkat di ruangan ini.'
            ], 403);
        }

        $device = Device::where('device_id', $deviceId)->first();
        $targetState = ($command === 'ON' ? 1 : 0);

        if ($device) {
            $curVals = $device->current_values ?? [];
            $pinKey = 'V' . ($acNumber - 1);
            $curVals[$pinKey] = $targetState;
            $device->current_values = $curVals;
            $device->pending_command = null;
            $device->save();
        }

        // Publish to device-specific MQTT topic and legacy topic
        $topic = "pindad/ac/control/{$deviceId}";
        $payload = json_encode([
            'device_id' => $deviceId,
            'ac' => $acNumber,
            'ac_number' => $acNumber,
            'relay' => $acNumber,
            'command' => $command,
            'state' => $command,
            'timestamp' => now()->toISOString(),
        ]);

        $this->mqttService->publish($topic, $payload);
        $this->mqttService->publish("pindad/devices/{$deviceId}/control", $payload);
        // Clean single-channel MQTT

        // Real-Time WebSocket Broadcast to connected clients
        try {
            app(WebSocketBroadcastService::class)->broadcastDeviceTelemetry($deviceId, [
                'event' => 'ac_control',
                'ac_number' => $acNumber,
                'state' => $command,
                'pin' => 'V' . ($acNumber - 1),
                'value' => $targetState,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Non-blocking WebSocket broadcast error
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'state' => $command,
            'command' => $command,
            'message' => "Perintah {$command} untuk AC {$acNumber} pada unit {$deviceId} berhasil dikirim.",
            'data' => [
                'device_id' => $deviceId,
                'ac_number' => $acNumber,
                'command' => $command,
                'state' => $command,
            ]
        ]);
    }

    /**
     * Handle control of any arbitrary virtual datastream pin (V0 - V127).
     */
    public function toggleStream(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string',
            'pin' => 'required|string',
            'value' => 'required',
        ]);

        $deviceId = $request->input('device_id');
        $pin = strtoupper($request->input('pin'));
        $val = $request->input('value');

        if (!$this->authorizeDeviceAccess($deviceId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Anda tidak memiliki hak akses pada perangkat ini.'
            ], 403);
        }

        $device = Device::where('device_id', $deviceId)->first();
        if ($device) {
            $curVals = $device->current_values ?? [];
            $curVals[$pin] = is_numeric($val) ? (float)$val : $val;
            $device->current_values = $curVals;
            $device->save();
        }

        $topic = "pindad/ac/control/{$deviceId}";
        $payload = json_encode([
            'device_id' => $deviceId,
            'pin' => $pin,
            'value' => $val,
            'command' => ($val == 1 ? 'ON' : ($val == 0 ? 'OFF' : 'SET')),
            'timestamp' => now()->toISOString(),
        ]);

        $this->mqttService->publish($topic, $payload);
        $this->mqttService->publish("pindad/devices/{$deviceId}/control", $payload);
        // Clean single-channel MQTT

        // Real-Time WebSocket Broadcast
        try {
            app(WebSocketBroadcastService::class)->broadcastDeviceTelemetry($deviceId, [
                'event' => 'stream_control',
                'pin' => $pin,
                'value' => $val,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Non-blocking
        }

        return response()->json([
            'status' => 'success',
            'message' => "Datastream {$pin} pada {$deviceId} berhasil diset ke {$val}.",
            'data' => [
                'device_id' => $deviceId,
                'pin' => $pin,
                'value' => $val
            ]
        ]);
    }

    /**
     * Master Fleet Control: Nyalakan / Matikan Semua Device.
     */
    public function masterControl(Request $request)
    {
        $request->validate([
            'command' => 'required|string|in:ALL_ON,ALL_OFF,all_on,all_off',
        ]);

        $userRole = session('user_role', 'admin');
        $userNip = session('user_nip', 'PINDAD-IOT-2026');

        // RBAC Matrix: Master Switch (All) hanya diizinkan untuk Super Admin (Divisi TI)
        if ($userRole !== 'admin' && $userNip !== 'PINDAD-IOT-2026') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Master Switch All hanya diizinkan untuk Super Admin (Divisi TI).'
            ], 403);
        }

        $command = strtoupper($request->input('command'));
        $devices = Device::all();
        $targetState = ($command === 'ALL_ON' ? 1 : 0);
        $stateText = ($command === 'ALL_ON' ? 'ON' : 'OFF');

        foreach ($devices as $dev) {
            $numAc = max(1, (int)($dev->num_ac ?? 2));
            $curVals = $dev->current_values ?? [];

            for ($i = 0; $i < $numAc; $i++) {
                $curVals['V' . $i] = $targetState;
            }
            $dev->current_values = $curVals;
            $dev->save();

            $topic = "pindad/ac/control/{$dev->device_id}";
            $payload = json_encode([
                'device_id' => $dev->device_id,
                'ac' => 'ALL',
                'command' => $stateText,
                'timestamp' => now()->toISOString(),
            ]);
            $this->mqttService->publish($topic, $payload);
        }

        $this->mqttService->publish('pindad/ac/control', json_encode([
            'device_id' => 'ALL',
            'command' => $stateText,
            'timestamp' => now()->toISOString(),
        ]));

        $msg = "Master Switch: Seluruh unit AC di armada berhasil di-{$stateText}!";

        if ($request->ajax() || $request->wantsJson() || $request->isJson()) {
            return response()->json([
                'success' => true,
                'status'  => 'success',
                'message' => $msg,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }
}

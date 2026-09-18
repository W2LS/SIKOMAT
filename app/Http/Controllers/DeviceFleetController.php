<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Template;
use App\Models\Schedule;
use App\Models\AcLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceFleetController extends Controller
{
    public function storeDevice(Request $request)
    {
        if (session('user_role') !== 'admin' && session('user_nip') !== 'PINDAD-IOT-2026') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Hanya Super Administrator yang berhak mendaftarkan perangkat IoT.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak: Hanya Super Administrator yang berhak mendaftarkan perangkat IoT.');
        }

        $userNip = session('user_nip', 'PINDAD-IOT-2026');

        $request->validate([
            'name' => 'required|string|max:100',
            'location' => 'required|string|max:100',
            'device_id' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $clean = strtoupper(preg_replace('/[^A-Z0-9_]/', '_', $value));
                    if (Device::where('device_id', $value)->orWhere('device_id', $clean)->exists()) {
                        $fail("ID Perangkat '{$value}' sudah terdaftar dalam sistem.");
                    }
                },
            ],
            'template_id' => 'nullable|string',
            'type' => 'nullable|string',
            'ip_address' => 'nullable|string|max:50',
            'hardware_type' => 'nullable|string|max:50',
            'num_ac' => 'nullable|integer|min:0|max:8',
            'description' => 'nullable|string',
        ]);

        $rawId = $request->input('device_id') ?: ('RPI3B_' . Str::slug($request->input('name'), '_'));
        $cleanId = strtoupper(preg_replace('/[^A-Z0-9_]/', '_', $rawId));
        $cleanId = trim(preg_replace('/_+/', '_', $cleanId), '_');

        // Check if IP address is already registered to prevent network collision
        $ip = trim($request->input('ip_address', ''));
        if (!empty($ip) && $ip !== '192.168.196.x') {
            $conflictDev = Device::where('ip_address', $ip)->first();
            if ($conflictDev) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', "Gagal menambahkan node! Alamat IP {$ip} sudah digunakan oleh perangkat '{$conflictDev->name}' ({$conflictDev->device_id}). Silakan gunakan alamat IP yang berbeda!");
            }
        }

        $template = Template::find($request->input('template_id'));

        $initialValues = [];
        if ($template && !empty($template->datastreams)) {
            foreach ($template->datastreams as $ds) {
                $pin = $ds['pin'];
                $def = $ds['default_value'] ?? 0;
                $initialValues[$pin] = is_numeric($def) ? (float)$def : $def;
            }
        }
        if (empty($initialValues)) {
            $initialValues = ['V0' => 0, 'V1' => 0, 'V2' => 0.0, 'V3' => 0.0, 'V4' => 0];
        }

        $numAc = $template ? $this->getDeviceRelayCapacity(null, $template) : max(1, (int)$request->input('num_ac', 2));

        // Pastikan kardus baru benar-benar bersih dari sisa data masa lalu
        Schedule::where('device_id', $cleanId)->delete();
        AcLog::where('device_id', $cleanId)->delete();

        Device::create([
            'user_nip' => $userNip,
            'device_id' => $cleanId,
            'template_id' => $request->input('template_id'),
            'name' => $request->input('name'),
            'type' => $request->input('type') ?? ($template ? ($template->name === 'Smart Industrial Lighting' ? 'smart_lighting' : 'ac_monitoring') : 'general_iot'),
            'icon' => $template->icon ?? '⚡',
            'location' => $request->input('location'),
            'ip_address' => $request->input('ip_address', '192.168.196.x'),
            'hardware_type' => $template->hardware_type ?? $request->input('hardware_type', 'Raspberry Pi 3B+'),
            'status' => 'standby',
            'auth_token' => Str::random(32),
            'num_ac' => $numAc,
            'description' => $request->input('description', ''),
            'current_values' => $initialValues,
        ]);

        return redirect()->route('dashboard', ['device_id' => $cleanId])->with('success', "Node perangkat {$request->input('name')} ({$cleanId}) berhasil didaftarkan!");
    }

    /**
     * Update Device.
     */

    public function updateDevice(Request $request, string $id)
    {
        if (session('user_role') !== 'admin' && session('user_nip') !== 'PINDAD-IOT-2026') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak: Hanya Super Administrator yang berhak mengubah data perangkat.');
        }
        $request->validate([
            'name' => 'required|string|max:100',
            'location' => 'required|string|max:100',
            'template_id' => 'nullable|string',
            'ip_address' => 'nullable|string|max:50',
            'hardware_type' => 'nullable|string|max:50',
            'num_ac' => 'nullable|integer|min:0|max:8',
            'description' => 'nullable|string',
        ]);

        $device = Device::findOrFail($id);
        $template = Template::find($request->input('template_id'));

        $updateData = $request->only('name', 'location', 'template_id', 'hardware_type', 'description');
        if ($template) {
            $updateData['icon'] = $template->icon ?? $device->icon;
            $updateData['hardware_type'] = $template->hardware_type ?? $device->hardware_type;
            $updateData['num_ac'] = $this->getDeviceRelayCapacity(null, $template);
        } elseif ($request->has('num_ac')) {
            $updateData['num_ac'] = max(1, (int)$request->input('num_ac'));
        }

        $device->update($updateData);

        return redirect()->route('dashboard', ['device_id' => $device->device_id])->with('success', "Informasi node {$device->name} berhasil diperbarui!");
    }

    /**
     * Delete Device.
     */

    public function deleteDevice(string $id)
    {
        if (session('user_role') !== 'admin' && session('user_nip') !== 'PINDAD-IOT-2026') {
            return redirect()->back()->with('error', 'Akses ditolak: Hanya Super Administrator yang berhak menghapus perangkat.');
        }
        $device = Device::findOrFail($id);
        $name = $device->name;
        $deviceId = $device->device_id;

        // Bersihkan seluruh isi kardus (jadwal dan log terkait) dari lemari
        Schedule::where('device_id', $deviceId)->delete();
        AcLog::where('device_id', $deviceId)->delete();

        $device->delete();

        return redirect()->route('dashboard')->with('success', "Perangkat {$name} berhasil dihapus dari sistem.");
    }

    /**
     * Helper to compute relay capacity from template datastreams or fallback to num_ac.
     */
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
}

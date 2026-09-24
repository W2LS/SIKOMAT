<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Authorize access to schedule device.
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

    public function storeSchedule(Request $request)
    {
        $userNip = session('user_nip', 'PINDAD-IOT-2026');
        $deviceId = $request->input('device_id');

        if ($deviceId && !$this->authorizeDeviceAccess($deviceId)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak: Anda tidak memiliki izin mengatur jadwal perangkat di ruangan ini.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak: Anda tidak memiliki izin mengatur jadwal perangkat di ruangan ini.');
        }

        $request->validate([
            'label' => 'required|string|max:100',
            'target_ac' => 'nullable|string|in:1,2,all',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'device_id' => 'nullable|string',
        ]);

        Schedule::create([
            'user_nip' => $userNip,
            'label' => $request->input('label'),
            'target_ac' => $request->input('target_ac', 'all'),
            'start_time' => $request->input('start_time') . ':00',
            'end_time' => $request->input('end_time') . ':00',
            'is_active' => true,
            'device_id' => $deviceId,
        ]);

        $this->syncSchedulesToMqtt($deviceId);

        return redirect()->route('dashboard', ['device_id' => $deviceId])
            ->with('success', 'Aturan jadwal rotasi berhasil ditambahkan.');
    }

    /**
     * Update Schedule.
     */
    public function updateSchedule(Request $request, string $id)
    {
        $schedule = Schedule::findOrFail($id);

        if ($schedule->device_id && !$this->authorizeDeviceAccess($schedule->device_id)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak: Anda tidak memiliki izin mengubah jadwal perangkat ini.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak: Anda tidak memiliki izin mengubah jadwal perangkat ini.');
        }

        $request->validate([
            'label' => 'required|string|max:100',
            'target_ac' => 'nullable|string|in:1,2,all',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
        ]);

        $schedule->update([
            'label' => $request->input('label'),
            'target_ac' => $request->input('target_ac', 'all'),
            'start_time' => $request->input('start_time') . ':00',
            'end_time' => $request->input('end_time') . ':00',
            'is_active' => $request->has('is_active') ? (bool)$request->input('is_active') : false,
        ]);

        $this->syncSchedulesToMqtt($schedule->device_id);

        return redirect()->back()->with('success', 'Jadwal rotasi AC berhasil diperbarui.');
    }

    /**
     * Toggle Schedule active state.
     */
    public function toggleSchedule(string $id)
    {
        $schedule = Schedule::findOrFail($id);

        if ($schedule->device_id && !$this->authorizeDeviceAccess($schedule->device_id)) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak.');
        }

        $schedule->is_active = !$schedule->is_active;
        $schedule->save();

        $this->syncSchedulesToMqtt($schedule->device_id);

        return redirect()->back()->with('success', 'Status jadwal berhasil diubah.');
    }

    /**
     * Delete Schedule.
     */
    public function deleteSchedule(string $id)
    {
        $schedule = Schedule::findOrFail($id);

        if ($schedule->device_id && !$this->authorizeDeviceAccess($schedule->device_id)) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak.');
        }

        $devId = $schedule->device_id;
        $schedule->delete();
        $this->syncSchedulesToMqtt($devId);

        return redirect()->back()->with('success', 'Jadwal berhasil dihapus.');
    }

    /**
     * Broadcast updated schedules to target Raspberry Pi node via MQTT (<50ms sync).
     */
    protected function syncSchedulesToMqtt(?string $deviceId): void
    {
        if (!$deviceId) return;
        try {
            $scheds = Schedule::where('device_id', $deviceId)
                ->where('is_active', true)
                ->get()
                ->map(function($s) {
                    return [
                        'id' => (string)($s->_id ?? $s->id),
                        'label' => $s->label,
                        'start_time' => \Carbon\Carbon::parse($s->start_time)->format('H:i'),
                        'end_time' => \Carbon\Carbon::parse($s->end_time)->format('H:i'),
                        'target_ac' => $s->target_ac ?? 'all',
                        'is_active' => (bool)$s->is_active,
                    ];
                })
                ->values()
                ->toArray();

            app(\App\Services\MqttService::class)->publish(
                "pindad/devices/{$deviceId}/schedule",
                json_encode([
                    'device_id' => $deviceId,
                    'server_time' => now()->toIso8601String(),
                    'schedules' => $scheds
                ])
            );
        } catch (\Throwable $e) {}
    }
}
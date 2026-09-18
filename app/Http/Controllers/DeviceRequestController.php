<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeviceRequestController extends Controller
{
    /**
     * Operator submits a new device/room access request ticket.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_name' => 'required|string|max:100',
            'location' => 'required|string|max:100',
            'request_type' => 'required|in:new_device,access_existing',
            'notes' => 'nullable|string|max:500',
        ], [
            'room_name.required' => 'Nama ruangan / perangkat yang diajukan wajib diisi.',
            'location.required' => 'Lokasi / gedung penempatan wajib diisi.',
            'request_type.required' => 'Pilih jenis pengajuan (Pengadaan Baru / Izin Akses).',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withInput()->withErrors($validator);
        }

        $userNip = session('user_nip');
        $user = User::where('username', $userNip)->orWhere('nip', $userNip)->first();

        $req = DeviceRequest::create([
            'operator_id' => (string)($user?->_id ?? $user?->id ?? session('user_id', '')),
            'operator_username' => $userNip ?: 'operator',
            'operator_name' => $user?->name ?? session('user_name', 'Operator Ruangan'),
            'operator_division' => $user?->division ?? session('user_division', 'Divisi Fasilitas'),
            'room_name' => trim($request->input('room_name')),
            'location' => trim($request->input('location')),
            'request_type' => $request->input('request_type', 'new_device'),
            'notes' => trim($request->input('notes', '')),
            'status' => 'pending',
            'admin_notes' => null,
            'assigned_device_id' => null,
        ]);

        $typeLabel = ($req->request_type === 'new_device') ? 'Pengadaan Node Baru' : 'Izin Akses Ruangan';
        $msg = "Pengajuan {$typeLabel} '{$req->room_name}' berhasil dikirim! Menunggu peninjauan & survei lapangan oleh Super Administrator (PINDAD-IOT-2026).";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $msg, 'ticket' => $req]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Super Admin reviews and approves a device request.
     */
    public function approve(Request $request, string $id)
    {
        if (session('user_role') !== 'admin' && session('user_nip') !== 'PINDAD-IOT-2026') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Hanya Super Administrator yang berhak menyetujui pengajuan.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak.');
        }

        $ticket = DeviceRequest::findOrFail($id);

        $assignedDeviceId = $request->input('assigned_device_id');
        $adminNotes = trim($request->input('admin_notes', 'Disetujui setelah peninjauan & pengecekan di lapangan.'));

        // If a device ID is specified to link with this operator
        if (!empty($assignedDeviceId)) {
            $operator = User::where('username', $ticket->operator_username)
                ->orWhere('nip', $ticket->operator_username)
                ->first();

            if ($operator) {
                $assigned = $operator->assigned_device_ids ?? [];
                if (!in_array($assignedDeviceId, $assigned, true)) {
                    $assigned[] = $assignedDeviceId;
                    $operator->assigned_device_ids = array_values(array_unique($assigned));
                    $operator->save();
                }
            }
        }

        $ticket->status = 'approved';
        $ticket->admin_notes = $adminNotes;
        $ticket->assigned_device_id = $assignedDeviceId ?: null;
        $ticket->reviewed_by = session('user_nip', 'PINDAD-IOT-2026');
        $ticket->reviewed_at = now();
        $ticket->save();

        $successMsg = "Pengajuan dari operator '{$ticket->operator_name}' untuk '{$ticket->room_name}' BERHASIL DISETUJUI!";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $successMsg]);
        }

        return redirect()->back()->with('success', $successMsg);
    }

    /**
     * Super Admin reviews and rejects a device request with reason.
     */
    public function reject(Request $request, string $id)
    {
        if (session('user_role') !== 'admin' && session('user_nip') !== 'PINDAD-IOT-2026') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Hanya Super Administrator yang berhak menolak pengajuan.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak.');
        }

        $ticket = DeviceRequest::findOrFail($id);

        $reason = trim($request->input('admin_notes', 'Pengajuan belum memenuhi persyaratan survei lapangan.'));

        $ticket->status = 'rejected';
        $ticket->admin_notes = $reason;
        $ticket->reviewed_by = session('user_nip', 'PINDAD-IOT-2026');
        $ticket->reviewed_at = now();
        $ticket->save();

        $successMsg = "Pengajuan untuk '{$ticket->room_name}' telah ditolak dengan catatan peninjauan.";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $successMsg]);
        }

        return redirect()->back()->with('info', $successMsg);
    }

    /**
     * Delete an archived ticket.
     */
    public function delete(Request $request, string $id)
    {
        if (session('user_role') !== 'admin' && session('user_nip') !== 'PINDAD-IOT-2026') {
            return redirect()->back()->with('error', 'Akses ditolak.');
        }

        $ticket = DeviceRequest::findOrFail($id);
        $ticket->delete();

        return redirect()->back()->with('success', 'Arsip tiket pengajuan berhasil dihapus.');
    }
}

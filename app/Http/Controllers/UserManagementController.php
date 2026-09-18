<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\TwoFactorSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserManagementController extends Controller
{
    /**
     * Create new Operator User with assigned room/device access.
     */
    public function storeUser(Request $request)
    {
        // Enforce admin permission
        if (session('user_role') !== 'admin' && session('user_nip') !== 'PINDAD-IOT-2026') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Hanya Super Administrator yang berhak menambah user operator.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak: Hanya Super Administrator yang berhak menambah user operator.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'username' => 'required|string|min:3|max:50',
            'password' => 'required|string|min:6',
            'division' => 'nullable|string|max:100',
            'assigned_device_ids' => 'nullable|array',
            'assigned_device_ids.*' => 'string',
        ], [
            'name.required' => 'Nama lengkap operator wajib diisi.',
            'username.required' => 'Username / NIP operator wajib diisi.',
            'username.min' => 'Username minimal 3 karakter.',
            'password.required' => 'Kata sandi awal operator wajib diisi.',
            'password.min' => 'Kata sandi minimal 6 karakter.',
        ]);

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
            }
            return redirect()->back()->withInput()->withErrors($validator);
        }

        $username = trim($request->input('username'));

        // Check if username already exists in DB
        $existing = User::where('username', $username)
            ->orWhere('nip', $username)
            ->orWhere('email', $username)
            ->first();

        if ($existing) {
            $msg = "Username atau NIP '{$username}' sudah terdaftar dalam sistem!";
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return redirect()->back()->withInput()->with('error', $msg);
        }

        $assignedDevices = $request->input('assigned_device_ids', []);
        if (!is_array($assignedDevices)) {
            $assignedDevices = [];
        }

        $user = User::create([
            'name' => trim($request->input('name')),
            'username' => $username,
            'nip' => $username,
            'email' => $username . '@pindad.local',
            'password' => Hash::make($request->input('password')),
            'role' => 'operator',
            'division' => trim($request->input('division', 'Divisi Sistem Informasi & Fasilitas')),
            'assigned_device_ids' => array_values(array_filter($assignedDevices)),
            'status' => 'active',
            'created_by' => session('user_nip', 'PINDAD-IOT-2026'),
        ]);

        $deviceCount = count($user->assigned_device_ids);
        $successMsg = "User operator '{$user->name}' ({$user->username}) berhasil dibuat dengan akses ke {$deviceCount} perangkat/ruangan!";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $successMsg,
                'user' => $user
            ]);
        }

        return redirect()->back()->with('success', $successMsg);
    }

    /**
     * Update Operator User details and room/device access.
     */
    public function updateUser(Request $request, string $id)
    {
        if (session('user_role') !== 'admin' && session('user_nip') !== 'PINDAD-IOT-2026') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Hanya Super Administrator yang berhak mengubah data user operator.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak: Hanya Super Administrator yang berhak mengubah user operator.');
        }

        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'division' => 'nullable|string|max:100',
            'assigned_device_ids' => 'nullable|array',
            'assigned_device_ids.*' => 'string',
            'status' => 'nullable|in:active,inactive',
        ], [
            'name.required' => 'Nama lengkap operator wajib diisi.',
        ]);

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
            }
            return redirect()->back()->withInput()->withErrors($validator);
        }

        $assignedDevices = $request->input('assigned_device_ids', []);
        if (!is_array($assignedDevices)) {
            $assignedDevices = [];
        }

        $user->name = trim($request->input('name'));
        $user->division = trim($request->input('division', $user->division ?: 'Divisi Sistem Informasi & Fasilitas'));
        $user->assigned_device_ids = array_values(array_filter($assignedDevices));
        if ($request->has('status')) {
            $user->status = $request->input('status');
        }
        $user->save();

        $deviceCount = count($user->assigned_device_ids);
        $successMsg = "Hak akses & data operator '{$user->name}' berhasil diperbarui ({$deviceCount} perangkat diizinkan).";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $successMsg,
                'user' => $user
            ]);
        }

        return redirect()->back()->with('success', $successMsg);
    }

    /**
     * Reset / Update Password for an Operator User by Admin.
     */
    public function resetPassword(Request $request, string $id)
    {
        if (session('user_role') !== 'admin' && session('user_nip') !== 'PINDAD-IOT-2026') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Hanya Super Administrator yang berhak mereset password operator.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak.');
        }

        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6',
        ], [
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi minimal 6 karakter.',
        ]);

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
            }
            return redirect()->back()->withInput()->withErrors($validator);
        }

        $user->password = Hash::make($request->input('password'));
        $user->save();

        $successMsg = "Kata sandi untuk operator '{$user->name}' ({$user->username}) berhasil diubah!";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $successMsg]);
        }

        return redirect()->back()->with('success', $successMsg);
    }

    /**
     * Reset 2FA configuration for an Operator User by Admin.
     */
    public function reset2fa(Request $request, string $id)
    {
        if (session('user_role') !== 'admin' && session('user_nip') !== 'PINDAD-IOT-2026') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Hanya Super Administrator yang berhak mereset 2FA operator.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak.');
        }

        $user = User::findOrFail($id);

        $identifiers = array_filter([$user->username, $user->nip, $user->email]);
        TwoFactorSetting::whereIn('user_nip', $identifiers)->delete();

        $successMsg = "Kunci 2FA untuk operator '{$user->name}' ({$user->username}) berhasil di-reset! Operator akan diminta melakukan setup 2FA baru saat login berikutnya.";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $successMsg]);
        }

        return redirect()->back()->with('success', $successMsg);
    }

    /**
     * Delete an Operator User.
     */
    public function deleteUser(Request $request, string $id)
    {
        if (session('user_role') !== 'admin' && session('user_nip') !== 'PINDAD-IOT-2026') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Hanya Super Administrator yang berhak menghapus user.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak.');
        }

        $user = User::findOrFail($id);

        // Protect Super Administrator
        if ($user->isAdmin() || $user->username === 'PINDAD-IOT-2026' || $user->nip === 'PINDAD-IOT-2026') {
            $msg = 'Akun Super Administrator utama tidak dapat dihapus demi keamanan sistem!';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 403);
            }
            return redirect()->back()->with('error', $msg);
        }

        $userName = $user->name;
        
        // Also cleanup their 2FA settings
        $identifiers = array_filter([$user->username, $user->nip, $user->email]);
        TwoFactorSetting::whereIn('user_nip', $identifiers)->delete();
        
        $user->delete();

        $successMsg = "Akun operator '{$userName}' berhasil dihapus dari sistem.";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => $successMsg]);
        }

        return redirect()->back()->with('success', $successMsg);
    }

    /**
     * Toggle operator active/inactive status.
     */
    public function toggleStatus(Request $request, string $id)
    {
        if (session('user_role') !== 'admin' && session('user_nip') !== 'PINDAD-IOT-2026') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $user = User::findOrFail($id);
        $newStatus = ($user->status === 'inactive') ? 'active' : 'inactive';
        $user->status = $newStatus;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => "Status operator '{$user->name}' diubah menjadi: " . ($newStatus === 'active' ? 'Aktif' : 'Nonaktif'),
            'new_status' => $newStatus
        ]);
    }
}

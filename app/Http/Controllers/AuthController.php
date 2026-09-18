<?php

namespace App\Http\Controllers;

use App\Models\TwoFactorSetting;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /**
     * Data fallback akun operator resmi SIKOMAT jika DB belum terisi.
     */
    protected array $validAccounts = [
        'PINDAD-IOT-2026' => [
            'password' => 'pindad123',
            'name' => 'Dicky Akbar Syah Putra',
            'division' => 'Divisi Sistem Informasi & Fasilitas',
            'role' => 'admin'
        ],
        'operator' => [
            'password' => 'pindad123',
            'name' => 'Dicky Akbar Syah Putra',
            'division' => 'Divisi Sistem Informasi & Fasilitas',
            'role' => 'operator'
        ],
        'admin' => [
            'password' => 'admin123',
            'name' => 'Administrator Server',
            'division' => 'Divisi Sistem Informasi',
            'role' => 'admin'
        ],
    ];

    /**
     * Tampilkan halaman login.
     */
    public function showLoginForm()
    {
        if (session('logged_in')) {
            return redirect()->route('dashboard');
        }
        if (session('2fa_setup_pending')) {
            return redirect()->route('login.2fa.setup');
        }
        if (session('2fa_pending')) {
            return redirect()->route('login.2fa');
        }
        return view('auth.login');
    }

    /**
     * Memproses percobaan login operator (Langkah 1).
     */
    public function login(Request $request)
    {
        $request->validate([
            'nip' => 'required|string',
            'password' => 'required|string',
        ], [
            'nip.required' => 'NIP / Username wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $nipInput = trim($request->input('nip'));
        $passwordInput = trim($request->input('password'));

        // 1. Cek User di database MongoDB
        $user = User::where('username', $nipInput)
            ->orWhere('nip', $nipInput)
            ->orWhere('email', $nipInput)
            ->first();

        $passwordValid = false;

        if ($user) {
            // Cek status akun
            if ($user->status === 'inactive') {
                return back()->withInput()->withErrors([
                    'login_error' => 'Akun operator ini dinonaktifkan oleh Administrator. Silakan hubungi admin.'
                ]);
            }

            // Cek hash password atau fallback plain-text
            if (Hash::check($passwordInput, $user->password)) {
                $passwordValid = true;
            } elseif ($user->password === $passwordInput) {
                // Auto-upgrade legacy plain-text password to bcrypt hash
                $user->password = Hash::make($passwordInput);
                $user->save();
                $passwordValid = true;
            }
        } elseif (array_key_exists($nipInput, $this->validAccounts) && $this->validAccounts[$nipInput]['password'] === $passwordInput) {
            // Fallback: Akun bawaan sistem, auto-sync ke collection User
            $fallback = $this->validAccounts[$nipInput];
            $user = User::create([
                'name' => $fallback['name'],
                'username' => $nipInput,
                'nip' => $nipInput,
                'email' => strtolower($nipInput) . '@pindad.com',
                'password' => Hash::make($passwordInput),
                'role' => $fallback['role'],
                'division' => $fallback['division'],
                'assigned_device_ids' => [],
                'status' => 'active',
                'created_by' => 'system',
            ]);
            $passwordValid = true;
        }

        if ($user && $passwordValid) {
            $userNip = $user->username ?? $user->nip ?? $nipInput;
            $userRole = ($user->isAdmin() || $userNip === 'PINDAD-IOT-2026') ? 'admin' : ($user->role ?? 'operator');

            // Cek apakah 2FA aktif untuk akun ini
            $identifiers = array_filter([$user->username, $user->nip, $user->email, $userNip]);
            $twoFactor = TwoFactorSetting::whereIn('user_nip', $identifiers)->first();

            // Skenario 1: User sudah mengaktifkan 2FA -> Arahkan ke Verifikasi OTP 2FA
            if ($twoFactor && $twoFactor->is_enabled && $twoFactor->confirmed_at) {
                session([
                    '2fa_pending' => true,
                    '2fa_user_nip' => $userNip,
                    '2fa_remember' => $request->boolean('remember', false),
                    '2fa_started_at' => now()->timestamp,
                ]);

                return redirect()->route('login.2fa');
            }

            // Skenario 2: User adalah Operator dan BELUM konfigurasi 2FA -> WAJIB ONBOARDING 2FA
            if ($userRole === 'operator') {
                session([
                    '2fa_setup_pending' => true,
                    '2fa_user_nip' => $userNip,
                    '2fa_remember' => $request->boolean('remember', false),
                    '2fa_started_at' => now()->timestamp,
                ]);

                return redirect()->route('login.2fa.setup');
            }

            // Skenario 3: Super Admin tanpa 2FA -> Login langsung ke dashboard
            session([
                'logged_in' => true,
                'user_id' => (string)($user->_id ?? $user->id),
                'user_nip' => $userNip,
                'user_name' => $user->name,
                'user_division' => $user->division ?? 'Divisi Sistem Informasi & Fasilitas',
                'user_role' => $userRole,
                'assigned_devices' => $user->assigned_device_ids ?? [],
                'login_time' => now()->format('d M Y, H:i:s WIB'),
                '2fa_verified' => false
            ]);

            return redirect()->route('dashboard')->with('success', 'Selamat datang kembali, ' . $user->name . '!');
        }

        return back()->withInput()->withErrors([
            'login_error' => 'NIP/Username atau Password tidak sesuai. Silakan periksa kembali.'
        ]);
    }

    /**
     * Tampilkan halaman input kode 2FA (Langkah 2 untuk user yang sudah aktif 2FA).
     */
    public function show2faForm()
    {
        if (!session('2fa_pending') || !session('2fa_user_nip')) {
            return redirect()->route('login');
        }

        $nip = session('2fa_user_nip');
        $user = User::where('username', $nip)->orWhere('nip', $nip)->first();
        $userName = $user ? $user->name : ($this->validAccounts[$nip]['name'] ?? 'Operator SIKOMAT');

        return view('auth.2fa', [
            'nip' => $nip,
            'userName' => $userName,
            'user' => $user
        ]);
    }

    /**
     * Tampilkan halaman Onboarding / Setup Wajib 2FA (untuk Operator baru / setelah Reset 2FA).
     */
    public function show2faSetupForm(TotpService $totpService)
    {
        if (!session('2fa_setup_pending') || !session('2fa_user_nip')) {
            return redirect()->route('login');
        }

        $nip = session('2fa_user_nip');
        $user = User::where('username', $nip)->orWhere('nip', $nip)->first();
        $userName = $user ? $user->name : ($this->validAccounts[$nip]['name'] ?? 'Operator SIKOMAT');

        $secret = session('2fa_onboarding_secret');
        $recoveryCodes = session('2fa_onboarding_recovery_codes');

        if (!$secret || empty($recoveryCodes)) {
            $secret = $totpService->generateSecret(16);
            $recoveryCodes = $totpService->generateRecoveryCodes(8);
            session([
                '2fa_onboarding_secret' => $secret,
                '2fa_onboarding_recovery_codes' => $recoveryCodes,
            ]);
        }

        $otpAuthUri = $totpService->getOtpAuthUri($nip, $secret, 'SIKOMAT PT PINDAD');
        $qrSvg = $totpService->generateQrSvg($otpAuthUri, 180);

        return view('auth.2fa-setup', [
            'nip' => $nip,
            'userName' => $userName,
            'user' => $user,
            'secret' => $secret,
            'qrSvg' => $qrSvg,
            'otpAuthUri' => $otpAuthUri,
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    /**
     * Konfirmasi aktivasi setup 2FA pertama kali dengan 6-digit OTP.
     */
    public function confirm2faSetup(Request $request, TotpService $totpService)
    {
        if (!session('2fa_setup_pending') || !session('2fa_user_nip')) {
            return redirect()->route('login');
        }

        $nip = session('2fa_user_nip');
        $secret = session('2fa_onboarding_secret');
        $plainRecoveryCodes = session('2fa_onboarding_recovery_codes');

        if (!$secret || empty($plainRecoveryCodes)) {
            return redirect()->route('login.2fa.setup')->withErrors(['setup_error' => 'Sesi setup telah kedaluwarsa. Silakan muat ulang halaman.']);
        }

        $request->validate([
            'code' => 'required|string|size:6',
        ], [
            'code.required' => 'Masukkan 6 digit kode dari aplikasi Authenticator untuk konfirmasi.',
            'code.size' => 'Kode harus terdiri dari 6 angka.',
        ]);

        $code = trim($request->input('code'));

        if (!$totpService->verifyTotp($secret, $code, 1)) {
            return back()->withInput()->withErrors([
                'setup_error' => 'Kode verifikasi 6 digit tidak sesuai atau jam pada ponsel Anda belum sinkron. Silakan coba lagi.'
            ]);
        }

        // Simpan konfigurasi 2FA ke MongoDB
        $hashedCodes = $totpService->hashRecoveryCodes($plainRecoveryCodes);
        TwoFactorSetting::updateOrCreate(
            ['user_nip' => $nip],
            [
                'is_enabled' => true,
                'secret_key' => $secret,
                'recovery_codes' => $hashedCodes,
                'confirmed_at' => now(),
            ]
        );

        // Login penuh
        $user = User::where('username', $nip)->orWhere('nip', $nip)->first();
        $userRole = ($user && $user->isAdmin()) ? 'admin' : 'operator';

        session([
            'logged_in' => true,
            'user_id' => (string)($user->_id ?? $user->id ?? ''),
            'user_nip' => $nip,
            'user_name' => $user ? $user->name : 'Operator SIKOMAT',
            'user_division' => $user->division ?? 'Divisi Sistem Informasi & Fasilitas',
            'user_role' => $userRole,
            'assigned_devices' => $user->assigned_device_ids ?? [],
            'login_time' => now()->format('d M Y, H:i:s WIB'),
            '2fa_verified' => true
        ]);

        session()->forget([
            '2fa_setup_pending', '2fa_user_nip', '2fa_onboarding_secret', '2fa_onboarding_recovery_codes', '2fa_remember', '2fa_started_at'
        ]);

        return redirect()->route('dashboard')->with('success', 'Two-Factor Authentication berhasil dikonfigurasi! Selamat datang di Dashboard SIKOMAT.');
    }

    /**
     * Memproses verifikasi kode 2FA (Langkah 2).
     */
    public function verify2fa(Request $request, TotpService $totpService)
    {
        if (!session('2fa_pending') || !session('2fa_user_nip')) {
            return redirect()->route('login')->withErrors(['login_error' => 'Sesi autentikasi telah kedaluwarsa. Silakan login kembali.']);
        }

        $nip = session('2fa_user_nip');
        $rateLimitKey = '2fa_attempts:' . $nip;

        // Proteksi brute force: max 5 percobaan per 5 menit
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return back()->withErrors([
                'totp_error' => "Terlalu banyak percobaan salah. Silakan tunggu {$seconds} detik sebelum mencoba lagi."
            ]);
        }

        $request->validate([
            'code' => 'required|string',
        ], [
            'code.required' => 'Masukkan 6 digit kode OTP atau 10 karakter kode pemulihan.',
        ]);

        $rawCode = trim($request->input('code'));
        $code = str_replace(' ', '', $rawCode);

        // Ambil konfigurasi 2FA user
        $twoFactor = TwoFactorSetting::where('user_nip', $nip)->first();

        if (!$twoFactor || !$twoFactor->is_enabled || !$twoFactor->secret_key) {
            $this->cleanup2faSession();
            return redirect()->route('login')->withErrors([
                'login_error' => 'Konfigurasi 2FA tidak ditemukan. Silakan hubungi Administrator.'
            ]);
        }

        $secret = $twoFactor->secret_key;
        $isValid = false;
        $isRecoveryUsed = false;

        // 1. Cek apakah input adalah kode OTP 6 Digit
        if (strlen($code) === 6 && ctype_digit($code)) {
            $isValid = $totpService->verifyTotp($secret, $code, 1);
        }

        // 2. Jika bukan OTP biasa atau OTP gagal, cek apakah input adalah Recovery Code
        if (!$isValid && strlen($code) >= 8) {
            $recoveryCodes = $twoFactor->recovery_codes ?? [];
            foreach ($recoveryCodes as $index => $hashedCode) {
                if (Hash::check($code, $hashedCode) || $code === $hashedCode) {
                    $isValid = true;
                    $isRecoveryUsed = true;
                    // Hapus recovery code yang sudah dipakai
                    unset($recoveryCodes[$index]);
                    $twoFactor->recovery_codes = array_values($recoveryCodes);
                    $twoFactor->save();
                    break;
                }
            }
        }

        if (!$isValid) {
            RateLimiter::hit($rateLimitKey, 300);
            $remaining = RateLimiter::retriesLeft($rateLimitKey, 5);
            return back()->withErrors([
                'totp_error' => "Kode verifikasi salah atau kedaluwarsa. Sisa percobaan: {$remaining} kali."
            ]);
        }

        RateLimiter::clear($rateLimitKey);

        // Ambil data user
        $user = User::where('username', $nip)->orWhere('nip', $nip)->first();
        $userRole = ($user && $user->isAdmin()) ? 'admin' : ($user->role ?? 'operator');

        // Set Sesi Login Penuh
        session([
            'logged_in' => true,
            'user_id' => (string)($user->_id ?? $user->id ?? ''),
            'user_nip' => $nip,
            'user_name' => $user ? $user->name : ($this->validAccounts[$nip]['name'] ?? 'Operator SIKOMAT'),
            'user_division' => $user ? ($user->division ?? 'Divisi Sistem Informasi & Fasilitas') : ($this->validAccounts[$nip]['division'] ?? 'Divisi Sistem Informasi & Fasilitas'),
            'user_role' => $userRole,
            'assigned_devices' => $user->assigned_device_ids ?? [],
            'login_time' => now()->format('d M Y, H:i:s WIB'),
            '2fa_verified' => true
        ]);

        $this->cleanup2faSession();

        $successMsg = 'Verifikasi 2FA berhasil. Selamat bertugas!';
        if ($isRecoveryUsed) {
            $successMsg .= ' (Perhatian: Anda menggunakan kode pemulihan. Kode tersebut telah hangus).';
        }

        return redirect()->route('dashboard')->with('success', $successMsg);
    }

    /**
     * Batalkan proses 2FA dan bersihkan sesi menggantung.
     */
    public function cancel2fa()
    {
        $this->cleanup2faSession();
        return redirect()->route('login')->with('info', 'Autentikasi 2FA dibatalkan.');
    }

    /**
     * Endpoint API Profil: Generate setup data 2FA (Secret & SVG QR Code).
     */
    public function setup2fa(TotpService $totpService)
    {
        $nip = session('user_nip');
        if (!$nip) {
            return response()->json(['status' => 'error', 'message' => 'Sesi tidak valid.'], 401);
        }

        $secret = $totpService->generateSecret(16);
        $otpAuthUri = $totpService->getOtpAuthUri($nip, $secret, 'SIKOMAT PT PINDAD');
        $qrSvg = $totpService->generateQrSvg($otpAuthUri, 180);
        $plainRecoveryCodes = $totpService->generateRecoveryCodes(8);

        session([
            '2fa_setup_secret' => $secret,
            '2fa_setup_recovery_codes' => $plainRecoveryCodes,
        ]);

        return response()->json([
            'status' => 'success',
            'secret' => $secret,
            'qr_svg' => $qrSvg,
            'otp_uri' => $otpAuthUri,
            'recovery_codes' => $plainRecoveryCodes,
        ]);
    }

    /**
     * Endpoint Profil: Konfirmasi aktivasi 2FA dengan kode OTP pertama.
     */
    public function enable2fa(Request $request, TotpService $totpService)
    {
        $nip = session('user_nip');
        $secret = session('2fa_setup_secret');
        $plainRecoveryCodes = session('2fa_setup_recovery_codes');

        if (!$nip || !$secret || empty($plainRecoveryCodes)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi konfigurasi 2FA telah kedaluwarsa. Silakan buka ulang modal aktivasi.'
            ], 422);
        }

        $request->validate([
            'code' => 'required|string|size:6',
        ], [
            'code.required' => 'Masukkan 6 digit kode dari aplikasi Authenticator untuk konfirmasi.',
            'code.size' => 'Kode harus terdiri dari 6 angka.',
        ]);

        $code = trim($request->input('code'));

        if (!$totpService->verifyTotp($secret, $code, 1)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kode verifikasi salah atau jam perangkat belum sinkron. Silakan coba lagi.'
            ], 422);
        }

        $hashedCodes = $totpService->hashRecoveryCodes($plainRecoveryCodes);

        TwoFactorSetting::updateOrCreate(
            ['user_nip' => $nip],
            [
                'is_enabled' => true,
                'secret_key' => $secret,
                'recovery_codes' => $hashedCodes,
                'confirmed_at' => now(),
            ]
        );

        session()->forget(['2fa_setup_secret', '2fa_setup_recovery_codes']);

        return response()->json([
            'status' => 'success',
            'message' => 'Two-Factor Authentication (2FA) berhasil diaktifkan untuk akun Anda!',
            'recovery_codes' => $plainRecoveryCodes
        ]);
    }

    /**
     * Endpoint Profil: Nonaktifkan 2FA dengan verifikasi kata sandi operator.
     */
    public function disable2fa(Request $request)
    {
        $nip = session('user_nip');
        if (!$nip) {
            return response()->json(['status' => 'error', 'message' => 'Sesi tidak valid.'], 401);
        }

        $request->validate([
            'password' => 'required|string',
        ], [
            'password.required' => 'Masukkan kata sandi akun Anda untuk menonaktifkan 2FA.',
        ]);

        $passwordInput = trim($request->input('password'));

        $user = User::where('username', $nip)->orWhere('nip', $nip)->first();
        $isMatch = false;

        if ($user) {
            $isMatch = Hash::check($passwordInput, $user->password) || ($user->password === $passwordInput);
        } else {
            $validPassword = $this->validAccounts[$nip]['password'] ?? null;
            $isMatch = ($passwordInput === $validPassword);
        }

        if (!$isMatch) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kata sandi yang Anda masukkan salah.'
            ], 422);
        }

        TwoFactorSetting::where('user_nip', $nip)->update([
            'is_enabled' => false,
            'secret_key' => null,
            'recovery_codes' => null,
            'confirmed_at' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Two-Factor Authentication (2FA) berhasil dinonaktifkan.'
        ]);
    }

    /**
     * Helper: Bersihkan sesi pre-auth 2FA.
     */
    protected function cleanup2faSession(): void
    {
        session()->forget([
            '2fa_pending', '2fa_setup_pending', '2fa_user_nip', '2fa_remember', '2fa_started_at',
            '2fa_onboarding_secret', '2fa_onboarding_recovery_codes'
        ]);
    }

    /**
     * Mengakhiri sesi login operator.
     */
    public function logout(Request $request)
    {
        $this->cleanup2faSession();
        session()->forget([
            'logged_in', 'user_id', 'user_nip', 'user_name', 'user_division', 'user_role', 'assigned_devices', 'login_time', '2fa_verified'
        ]);
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar dari sistem.');
    }
}

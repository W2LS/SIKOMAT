<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AcControlController;
use App\Http\Controllers\DeviceFleetController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\DeveloperZoneController;
use App\Http\Controllers\TelemetryLogController;
use App\Http\Controllers\ProfileSettingsController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\DeviceRequestController;

// ==========================================
// 1. ROUTE AUTENTIKASI & 2FA OPERATOR
// ==========================================
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/login/2fa', [AuthController::class, 'show2faForm'])->name('login.2fa');
Route::post('/login/2fa', [AuthController::class, 'verify2fa'])->name('login.2fa.verify');
Route::get('/login/2fa-setup', [AuthController::class, 'show2faSetupForm'])->name('login.2fa.setup');
Route::post('/login/2fa-setup', [AuthController::class, 'confirm2faSetup'])->name('login.2fa.setup.confirm');
Route::post('/login/2fa/cancel', [AuthController::class, 'cancel2fa'])->name('login.2fa.cancel');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ==========================================
// 2. ROUTE TERPROTEKSI OPERATOR DASHBOARD
// ==========================================
Route::middleware('auth.session')->group(function () {
    
    // A. Dashboard Utama & Real-Time Analytics
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // B. Kontrol Relay & Virtual Datastreams (AcControlController)
    Route::post('/ac/control', [AcControlController::class, 'toggleAc'])->name('ac.control');
    Route::post('/devices/control-stream', [AcControlController::class, 'toggleStream'])->name('devices.controlStream');
    Route::post('/devices/master-control', [AcControlController::class, 'masterControl'])->name('devices.masterControl');

    // C. Manajemen Perangkat IoT & Fleet (DeviceFleetController)
    Route::post('/devices', [DeviceFleetController::class, 'storeDevice'])->name('devices.store');
    Route::put('/devices/{id}', [DeviceFleetController::class, 'updateDevice'])->name('devices.update');
    Route::delete('/devices/{id}', [DeviceFleetController::class, 'deleteDevice'])->name('devices.destroy');
    Route::delete('/devices/{id}/delete', [DeviceFleetController::class, 'deleteDevice'])->name('devices.delete');

    // D. Penjadwalan Rotasi AC (ScheduleController)
    Route::post('/schedules', [ScheduleController::class, 'storeSchedule'])->name('schedules.store');
    Route::put('/schedules/{id}', [ScheduleController::class, 'updateSchedule'])->name('schedules.update');
    Route::post('/schedules/{id}/toggle', [ScheduleController::class, 'toggleSchedule'])->name('schedules.toggle');
    Route::delete('/schedules/{id}', [ScheduleController::class, 'deleteSchedule'])->name('schedules.destroy');
    Route::delete('/schedules/{id}/delete', [ScheduleController::class, 'deleteSchedule'])->name('schedules.delete');

    // E. Developer Zone: Dynamic Templates & Datastreams (DeveloperZoneController)
    Route::post('/templates', [DeveloperZoneController::class, 'storeTemplate'])->name('templates.store');
    Route::put('/templates/{id}', [DeveloperZoneController::class, 'updateTemplate'])->name('templates.update');
    Route::delete('/templates/{id}', [DeveloperZoneController::class, 'deleteTemplate'])->name('templates.destroy');
    Route::delete('/templates/{id}/delete', [DeveloperZoneController::class, 'deleteTemplate'])->name('templates.delete');
    Route::post('/templates/{id}/datastreams', [DeveloperZoneController::class, 'addDatastream'])->name('templates.addDatastream');
    Route::delete('/templates/{id}/datastreams/{pin}', [DeveloperZoneController::class, 'removeDatastream'])->name('templates.deleteDatastream');
    Route::post('/templates/preset', [DeveloperZoneController::class, 'createPresetTemplate'])->name('templates.preset');
    Route::get('/templates/export/{id}', [DeveloperZoneController::class, 'exportTemplate'])->name('templates.export');
    Route::post('/templates/import', [DeveloperZoneController::class, 'importTemplate'])->name('templates.import');

    // F. Riwayat Telemetri & Export Data (TelemetryLogController)
    Route::get('/logs/export', [TelemetryLogController::class, 'exportCsv'])->name('logs.export');
    Route::post('/logs/clear', [TelemetryLogController::class, 'clearLogs'])->name('logs.clear');
    Route::post('/logs/prune', [TelemetryLogController::class, 'pruneLogs'])->name('logs.prune');
    Route::get('/logs/stats', [TelemetryLogController::class, 'getLogStats'])->name('logs.stats');

    // G. Pengaturan Profil, Telegram & Unduh Skrip (ProfileSettingsController)
    Route::post('/profile', [ProfileSettingsController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/password', [ProfileSettingsController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/telegram', [ProfileSettingsController::class, 'saveTelegramSettings'])->name('settings.telegram');
    Route::post('/profile/telegram/save', [ProfileSettingsController::class, 'saveTelegramSettings'])->name('profile.telegram');
    Route::post('/profile/telegram/test', [ProfileSettingsController::class, 'testTelegramNotification'])->name('settings.telegram.test');
    Route::get('/panduan-pdf', [ProfileSettingsController::class, 'manualPdf'])->name('panduan.pdf');
    Route::get('/profile/panduan-pdf', [ProfileSettingsController::class, 'manualPdf'])->name('profile.manualPdf');

    // H. Fitur Keamanan 2FA TOTP (AuthController / Profile 2FA)
    Route::get('/profile/2fa/setup', [AuthController::class, 'setup2fa'])->name('profile.2fa.setup');
    Route::post('/profile/2fa/enable', [AuthController::class, 'enable2fa'])->name('profile.2fa.enable');
    Route::post('/profile/2fa/disable', [AuthController::class, 'disable2fa'])->name('profile.2fa.disable');

    // I. Manajemen User Operator & Role-Based Access Control (UserManagementController)
    Route::post('/admin/users', [UserManagementController::class, 'storeUser'])->name('admin.users.store');
    Route::put('/admin/users/{id}', [UserManagementController::class, 'updateUser'])->name('admin.users.update');
    Route::post('/admin/users/{id}/password', [UserManagementController::class, 'resetPassword'])->name('admin.users.password');
    Route::post('/admin/users/{id}/reset-2fa', [UserManagementController::class, 'reset2fa'])->name('admin.users.reset-2fa');
    Route::delete('/admin/users/{id}', [UserManagementController::class, 'deleteUser'])->name('admin.users.delete');
    Route::post('/admin/users/{id}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('admin.users.toggleStatus');

    // J. Tiket Pengajuan Perangkat Operator (DeviceRequestController)
    Route::post('/operator/device-requests', [DeviceRequestController::class, 'store'])->name('operator.device-requests.store');
    Route::post('/admin/device-requests/{id}/approve', [DeviceRequestController::class, 'approve'])->name('admin.device-requests.approve');
    Route::post('/admin/device-requests/{id}/reject', [DeviceRequestController::class, 'reject'])->name('admin.device-requests.reject');
    Route::delete('/admin/device-requests/{id}', [DeviceRequestController::class, 'delete'])->name('admin.device-requests.delete');
});

// ==========================================
// 3. API TELEMETRI PUBLIK / IoT CLIENT
// ==========================================
Route::get('/api/logs', [TelemetryLogController::class, 'getLiveLogs'])->name('api.logs');
Route::get('/api/live-status', [TelemetryLogController::class, 'getLiveLogs'])->name('api.liveStatus');
Route::post('/api/telemetry', [TelemetryLogController::class, 'receiveTelemetry'])->name('api.telemetry');
Route::get('/telemetry', [TelemetryLogController::class, 'getLiveLogs'])->name('telemetry.get');
Route::post('/telemetry', [TelemetryLogController::class, 'receiveTelemetry'])->name('telemetry.ingest');


// ==========================================
// 4. UNDUH SKRIP IoT NODE PUBLIK (Tanpa Sesi Login)
// ==========================================
Route::get('/download-script/{type}', [ProfileSettingsController::class, 'downloadScript'])->name('scripts.download');
Route::get('/scripts/download/{type}', [ProfileSettingsController::class, 'downloadScript'])->name('profile.downloadScript');
Route::get('/api/node-script/download', function (\Illuminate\Http\Request $request) {
    return app(\App\Http\Controllers\ProfileSettingsController::class)->downloadScript($request, 'device');
})->name('api.node-script.download');

<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Send a formatted HTML text message to Telegram Bot API.
     */
    public function sendMessage(string $htmlMessage, ?string $botToken = null, ?string $chatId = null): array
    {
        $token = $botToken ?: SystemSetting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN', ''));
        $chat = $chatId ?: SystemSetting::get('telegram_chat_id', env('TELEGRAM_CHAT_ID', ''));
        $enabled = SystemSetting::get('telegram_alert_enabled', true);

        if (!$botToken && !$enabled) {
            return [
                'success' => false,
                'message' => 'Notifikasi Telegram saat ini dinonaktifkan di pengaturan sistem.',
            ];
        }

        if (empty($token) || empty($chat)) {
            return [
                'success' => false,
                'message' => 'Bot Token atau Chat ID Telegram belum diatur.',
            ];
        }

        try {
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $response = Http::withoutVerifying()->connectTimeout(2)->timeout(3.5)->post($url, [
                'chat_id' => $chat,
                'text' => $htmlMessage,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Pesan Telegram berhasil dikirim ke teknisi.',
                    'data' => $response->json(),
                ];
            }

            $errMsg = $response->json('description') ?? 'Gagal mengirim pesan ke Telegram.';
            Log::error("[TelegramService] Error: " . $errMsg);
            return [
                'success' => false,
                'message' => "Telegram API Error: {$errMsg}",
            ];
        } catch (\Exception $e) {
            Log::error("[TelegramService] Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Koneksi Telegram Gagal: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Send critical emergency failure alert for a specific device and AC unit.
     */
    public function sendFailureAlert(
        string $roomName,
        string $location,
        string $deviceId,
        int $acNumber,
        string $unitName,
        float $currentAmpere,
        string $timestamp,
        string $cause = 'Kompresor Mati / Beban Nol (0 A)'
    ): array {
        $formattedTime = date('d M Y - H:i:s \W\I\B', strtotime($timestamp ?: 'now'));
        
        $msg = "🚨 <b>[PERINGATAN KRITIS • PT PINDAD]</b>\n";
        $msg .= "⚠️ <b>GANGGUAN: AC GAGAL MENYALA / MATI!</b>\n\n";
        $msg .= "📍 <b>Ruangan:</b> <code>{$roomName}</code>\n";
        $msg .= "🏢 <b>Lokasi:</b> {$location}\n";
        $msg .= "🆔 <b>ID Perangkat:</b> <code>{$deviceId}</code>\n";
        $msg .= "❄️ <b>Unit AC:</b> <b>Unit {$acNumber} ({$unitName})</b>\n";
        $msg .= "⚡ <b>Arus Terukur:</b> <code>{$currentAmpere} A</code> (0 Watt)\n";
        $msg .= "⚙️ <b>Status Sistem:</b> DIPERINTAHKAN ON (WAKTU NYALA)\n";
        $msg .= "⏰ <b>Waktu Terdeteksi:</b> {$formattedTime}\n\n";
        $msg .= "🔍 <b>DIAGNOSA SENSOR ARUS (ACS712):</b>\n";
        $msg .= "Saklar relai berstatus <b>ON</b>, tetapi sensor arus mendeteksi <b>0 Ampere</b> (tidak ada daya listrik yang terserap oleh kompresor).\n";
        $msg .= "<i>Indikasi: {$cause}, MCB Trip, Kapasitor Rusak, atau Kabel Terputus.</i>\n\n";
        $msg .= "👨‍🔧 <b>TINDAKAN:</b> Mohon teknisi segera lakukan pengecekan fisik di lokasi <b>{$roomName}</b>!";

        return $this->sendMessage($msg);
    }

    /**
     * Send recovery notification when the AC unit recovers back to normal.
     */
    public function sendRecoveryAlert(
        string $roomName,
        string $location,
        string $deviceId,
        int $acNumber,
        string $unitName,
        float $currentAmpere,
        string $timestamp
    ): array {
        $formattedTime = date('d M Y - H:i:s \W\I\B', strtotime($timestamp ?: 'now'));
        $watt = round($currentAmpere * 220);

        $msg = "✅ <b>[PEMULIHAN SISTEM • PT PINDAD]</b>\n";
        $msg .= "🟢 <b>STATUS: AC TELAH BEROPERASI NORMAL KEMBALI</b>\n\n";
        $msg .= "📍 <b>Ruangan:</b> <code>{$roomName}</code>\n";
        $msg .= "🏢 <b>Lokasi:</b> {$location}\n";
        $msg .= "❄️ <b>Unit AC:</b> <b>Unit {$acNumber} ({$unitName})</b>\n";
        $msg .= "⚡ <b>Arus Normal:</b> <code>{$currentAmpere} A</code> (≈ {$watt} Watt)\n";
        $msg .= "⏰ <b>Waktu Pemulihan:</b> {$formattedTime}\n\n";
        $msg .= "👍 Beban kompresor telah aktif dan mendinginkan ruangan dengan normal kembali.";

        return $this->sendMessage($msg);
    }

    /**
     * Send a test message to verify Telegram bot connection.
     */
    public function sendTestMessage(?string $botToken = null, ?string $chatId = null): array
    {
        $now = date('d M Y - H:i:s \W\I\B');
        $msg = "🤖 <b>[UJI COBA SISTEM NOTIFIKASI • PT PINDAD]</b>\n\n";
        $msg .= "✅ <b>Koneksi Bot Telegram Berhasil Terhubung!</b>\n";
        $msg .= "📡 <b>Platform:</b> PINDAD IoT Engine & Central AC Monitoring\n";
        $msg .= "⏰ <b>Waktu Uji Coba:</b> {$now}\n\n";
        $msg .= "Bot ini telah siap mengirimkan notifikasi darurat secara instan jika terdeteksi AC gagal hidup, kompresor mati, atau anomali arus pada seluruh armada IoT PT PINDAD. 🚀🏢";

        return $this->sendMessage($msg, $botToken, $chatId);
    }
}

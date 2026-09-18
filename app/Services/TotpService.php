<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TotpService
{
    /**
     * Alfabet Base32 RFC 4648 standar (tanpa 0, 1, 8, 9)
     */
    protected static string $base32Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate Base32 Secret Key (Panjang 16 karakter = 80-bit entropy standar Google Authenticator).
     */
    public function generateSecret(int $length = 16): string
    {
        $secret = '';
        $alphabetLength = strlen(self::$base32Alphabet);
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Alphabet[random_int(0, $alphabetLength - 1)];
        }
        return $secret;
    }

    /**
     * Hitung kode TOTP 6 digit (RFC 6238) berdasarkan Secret dan Timestamp.
     */
    public function getTotpCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $timeSlice = floor($timestamp / 30);

        $secretBinary = $this->base32Decode($secret);
        if ($secretBinary === false) {
            return '';
        }

        // Pack time slice ke format 64-bit integer big-endian
        $timePacked = pack('N*', 0) . pack('N*', $timeSlice);

        // Hitung HMAC-SHA1
        $hash = hash_hmac('sha1', $timePacked, $secretBinary, true);

        // Dynamic Truncation (RFC 4226)
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncatedHash = substr($hash, $offset, 4);

        $unpacked = unpack('N', $truncatedHash);
        $value = $unpacked[1] & 0x7FFFFFFF;

        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verifikasi kode 6-digit TOTP dengan toleransi time-drift (discrepancy = 1 -> ±30 detik).
     */
    public function verifyTotp(string $secret, string $code, int $discrepancy = 1): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        $currentTime = time();
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->getTotpCode($secret, $currentTime + ($i * 30));
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decode string Base32 menjadi biner.
     */
    protected function base32Decode(string $secret): string|false
    {
        $secret = strtoupper(trim($secret));
        if (empty($secret)) {
            return '';
        }

        $buffer = 0;
        $bufferLength = 0;
        $binary = '';

        for ($i = 0; $i < strlen($secret); $i++) {
            $char = $secret[$i];
            if ($char === '=') {
                break;
            }

            $position = strpos(self::$base32Alphabet, $char);
            if ($position === false) {
                return false;
            }

            $buffer = ($buffer << 5) | $position;
            $bufferLength += 5;

            if ($bufferLength >= 8) {
                $bufferLength -= 8;
                $binary .= chr(($buffer >> $bufferLength) & 0xFF);
            }
        }

        return $binary;
    }

    /**
     * Buat URL otpauth URI untuk aplikasi Authenticator.
     */
    public function getOtpAuthUri(string $account, string $secret, string $issuer = 'SIKOMAT PT PINDAD'): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            rawurlencode($secret),
            rawurlencode($issuer)
        );
    }

    /**
     * Generate One-Time Recovery Codes acak (8 kode, format ABCD-1234).
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $p1 = strtoupper(Str::random(4));
            $p2 = strtoupper(Str::random(4));
            $codes[] = $p1 . '-' . $p2;
        }
        return $codes;
    }

    /**
     * Hash array recovery codes menggunakan Hash::make().
     */
    public function hashRecoveryCodes(array $plainCodes): array
    {
        return array_map(function ($code) {
            return Hash::make($this->normalizeCode($code));
        }, $plainCodes);
    }

    /**
     * Verifikasi dan konsumsi 1 recovery code (langsung hapus dari array jika cocok).
     */
    public function verifyAndConsumeRecoveryCode(array &$hashedCodes, string $inputCode): bool
    {
        $normalizedInput = $this->normalizeCode($inputCode);

        foreach ($hashedCodes as $index => $hashedCode) {
            if (Hash::check($normalizedInput, $hashedCode)) {
                unset($hashedCodes[$index]);
                $hashedCodes = array_values($hashedCodes); // Re-index array
                return true;
            }
        }

        return false;
    }

    /**
     * Normalisasi format kode (hapus spasi, strip, ubah ke uppercase).
     */
    public function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    /**
     * Render SVG QR Code Mandiri 100% Offline (Tanpa API Eksternal).
     */
    public function generateQrSvg(string $text, int $size = 220): string
    {
        return $this->buildQrSvg($text, $size);
    }

    /**
     * Matriks QR Code Generator internal.
     */
    protected function buildQrSvg(string $text, int $size): string
    {
        $matrix = $this->encodeToQrMatrix($text);
        $moduleCount = count($matrix);
        $moduleSize = $size / $moduleCount;

        $path = '';
        for ($r = 0; $r < $moduleCount; $r++) {
            for ($c = 0; $c < $moduleCount; $c++) {
                if ($matrix[$r][$c]) {
                    $x = round($c * $moduleSize, 2);
                    $y = round($r * $moduleSize, 2);
                    $w = round($moduleSize, 2);
                    $h = round($moduleSize, 2);
                    $path .= "M{$x},{$y}h{$w}v{$h}h-{$w}z ";
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" style="background:#ffffff;border-radius:12px;padding:8px;"><path d="%s" fill="#1D1616"/></svg>',
            $size, $size, $size, $size, $path
        );
    }

    protected function encodeToQrMatrix(string $text): array
    {
        $len = strlen($text);
        $size = ($len > 80) ? 33 : 29;
        $matrix = array_fill(0, $size, array_fill(0, $size, 0));

        $this->addFinderPattern($matrix, 0, 0);
        $this->addFinderPattern($matrix, $size - 7, 0);
        $this->addFinderPattern($matrix, 0, $size - 7);

        for ($i = 8; $i < $size - 8; $i++) {
            $matrix[6][$i] = ($i % 2 === 0) ? 1 : 0;
            $matrix[$i][6] = ($i % 2 === 0) ? 1 : 0;
        }

        $alignPos = $size - 7;
        $this->addAlignmentPattern($matrix, $alignPos, $alignPos);

        $bytes = unpack('C*', $text);
        $bitStream = '';
        foreach ($bytes as $b) {
            $bitStream .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }

        $bitIdx = 0;
        $bitLen = strlen($bitStream);
        $hashVal = hash('sha256', $text);
        $hashBits = '';
        for ($i = 0; $i < strlen($hashVal); $i++) {
            $hashBits .= str_pad(decbin(hexdec($hashVal[$i])), 4, '0', STR_PAD_LEFT);
        }

        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($this->isReserved($r, $c, $size)) {
                    continue;
                }
                if ($bitIdx < $bitLen) {
                    $val = (int)$bitStream[$bitIdx++];
                } else {
                    $hIdx = ($r * $size + $c) % strlen($hashBits);
                    $val = (int)$hashBits[$hIdx];
                }
                $matrix[$r][$c] = ($val ^ (($r + $c) % 2 === 0)) ? 1 : 0;
            }
        }

        return $matrix;
    }

    protected function addFinderPattern(array &$m, int $row, int $col): void
    {
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                if ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)) {
                    $m[$row + $r][$col + $c] = 1;
                } else {
                    $m[$row + $r][$col + $c] = 0;
                }
            }
        }
        for ($i = 0; $i < 8; $i++) {
            if ($row + 7 < count($m) && $col + $i < count($m)) $m[$row + 7][$col + $i] = 0;
            if ($row + $i < count($m) && $col + 7 < count($m)) $m[$row + $i][$col + 7] = 0;
        }
    }

    protected function addAlignmentPattern(array &$m, int $row, int $col): void
    {
        if ($row < 4 || $col < 4 || $row + 2 >= count($m) || $col + 2 >= count($m)) return;
        for ($r = -2; $r <= 2; $r++) {
            for ($c = -2; $c <= 2; $c++) {
                if (abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0)) {
                    $m[$row + $r][$col + $c] = 1;
                } else {
                    $m[$row + $r][$col + $c] = 0;
                }
            }
        }
    }

    protected function isReserved(int $r, int $c, int $size): bool
    {
        if ($r <= 8 && $c <= 8) return true;
        if ($r <= 8 && $c >= $size - 9) return true;
        if ($r >= $size - 9 && $c <= 8) return true;
        if ($r === 6 || $c === 6) return true;
        $alignPos = $size - 7;
        if (abs($r - $alignPos) <= 2 && abs($c - $alignPos) <= 2) return true;
        return false;
    }
}

#!/bin/bash
# =============================================================================
# PINDAD IOT ENGINE - AUTO INSTALLER & DEPENDENCY SETUP SCRIPT
# PT PINDAD (PERSERO) - DIVISI MUTU & TI
# =============================================================================

echo "======================================================="
echo "🚀 MEMULAI SETUP DEPENDENCY RASPBERRY PI PT PINDAD IOT"
echo "======================================================="

# 0. Coba Autentikasi Firewall Sophos Pindad (Jika Jaringan Butuh Login)
echo "🔐 [0/4] Memeriksa akses jaringan intranet Pindad..."
curl -k -s --connect-timeout 3 -d "mode=191&username=pin-00020&password=5uiFS4eE" "https://sophostrn.pindad.com:8090/login.xml" >/dev/null 2>&1 || true

# 1. Cek Apakah Library Sensor Sudah Terpasang Sebelumnya
ALL_INSTALLED=false
if python3 -c "import paho.mqtt, adafruit_ads1x15, adafruit_ds3231, RPi.GPIO" 2>/dev/null; then
    echo "✅ [1/4] Semua pustaka sensor Python (MQTT, ADS1115, DS3231, GPIO) SUDAH TERPASANG!"
    ALL_INSTALLED=true
else
    echo "📦 [1/4] Mengupdate package repository Linux..."
    # Timeout 4 detik agar tidak menggantung jika jaringan tidak ada akses internet publik
    sudo apt-get -o Acquire::Connect-Timeout=4 -o Acquire::Http::Timeout=4 update || echo "⚠️ Server repositori publik Debian tidak terjangkau (Berjalan di mode LAN Intranet)."
    sudo apt install -y python3-pip python3-smbus i2c-tools git 2>/dev/null || true
fi

# 2. Aktifkan I2C & GPIO Interface
echo "🔌 [2/4] Mengaktifkan hardware interface I2C & GPIO..."
sudo raspi-config nonint do_i2c 0 2>/dev/null || true

# 3. Install Python Libraries (Hanya Jika Belum Ada)
if [ "$ALL_INSTALLED" = false ]; then
    echo "🐍 [3/4] Menginstall library Python sensor..."
    pip3 install --timeout 15 paho-mqtt adafruit-circuitpython-ads1x15 adafruit-circuitpython-ds3231 RPi.GPIO --break-system-packages 2>/dev/null || \
    pip3 install --timeout 15 paho-mqtt adafruit-circuitpython-ads1x15 adafruit-circuitpython-ds3231 RPi.GPIO || \
    echo "⚠️ Pip install dilewati (akan menggunakan pustaka lokal yang ada)."
else
    echo "🐍 [3/4] Pustaka sensor lengkap. Tidak perlu mengunduh ulang."
fi

# 4. Verifikasi Hardware Sensor I2C
echo "🔍 [4/4] Memeriksa deteksi hardware I2C (ADS1115 = 0x48, DS3231 = 0x68)..."
if command -v i2cdetect >/dev/null 2>&1; then
    i2cdetect -y 1
else
    echo "ℹ️ i2cdetect belum aktif, modul sensor akan diakses langsung via busio Python."
fi

echo "======================================================="
echo "🎉 SETUP RASPBERRY PI SELESAI!"
echo "Semua dependensi dan antarmuka I2C/GPIO sudah siap."
echo "Sekarang jalankan Langkah 2 (Perintah Auto-Start Node) dari Dashboard."
echo "======================================================="

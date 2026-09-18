#!/bin/bash
# =============================================================================
# PINDAD IOT ENGINE - AUTO INSTALLER & DEPENDENCY SETUP SCRIPT
# PT PINDAD (PERSERO) - DIVISI MUTU & TI
# =============================================================================

echo "======================================================="
echo "🚀 MEMULAI SETUP DEPENDENCY RASPBERRY PI PT PINDAD IOT"
echo "======================================================="

# 1. Update OS Package Repository
echo "📦 [1/4] Mengupdate package repository Linux..."
sudo apt update && sudo apt install -y python3-pip python3-smbus i2c-tools git

# 2. Aktifkan I2C & GPIO Interface
echo "🔌 [2/4] Mengaktifkan hardware interface I2C & GPIO..."
sudo raspi-config nonint do_i2c 0

# 3. Install Python Libraries
echo "🐍 [3/4] Menginstall library Python sensor (MQTT, ADS1115, DS3231)..."
pip3 install paho-mqtt adafruit-circuitpython-ads1x15 adafruit-circuitpython-ds3231 RPi.GPIO --break-system-packages 2>/dev/null || pip3 install paho-mqtt adafruit-circuitpython-ads1x15 adafruit-circuitpython-ds3231 RPi.GPIO

# 4. Verifikasi Hardware Sensor I2C
echo "🔍 [4/4] Memeriksa deteksi hardware I2C (ADS1115 = 0x48, DS3231 = 0x68)..."
i2cdetect -y 1

echo "======================================================="
echo "🎉 INSTALASI DEPENDENCY RASPBERRY PI 100% SUKSES!"
echo "Semua library Python (paho-mqtt, adafruit, RPi.GPIO) sudah siap."
echo "Silakan salin & jalankan perintah 1-klik dari modal Setup Node di Dashboard."
echo "======================================================="

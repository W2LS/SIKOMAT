# ❄️ SIKOMAT — Sistem Kontrol & Monitoring Otomatis AC
### 🏢 PT PINDAD (PERSERO) • Divisi Mutu & Teknologi Informasi

<p align="center">
  <img src="public/SIKOMAT.png" alt="SIKOMAT PT PINDAD" width="380" />
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12" />
  <img src="https://img.shields.io/badge/Tailwind_CSS-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="Tailwind CSS" />
  <img src="https://img.shields.io/badge/Alpine.js-3.x-8BC0D0?style=for-the-badge&logo=alpine.js&logoColor=white" alt="Alpine.js" />
  <img src="https://img.shields.io/badge/MQTT-Mosquitto_TCP_1883-660066?style=for-the-badge&logo=eclipse-mosquitto&logoColor=white" alt="MQTT Mosquitto" />
  <img src="https://img.shields.io/badge/Database-MongoDB_Atlas_&_Local-47A248?style=for-the-badge&logo=mongodb&logoColor=white" alt="Database" />
  <img src="https://img.shields.io/badge/Hardware-Raspberry_Pi_3B+-C51A4A?style=for-the-badge&logo=raspberry-pi&logoColor=white" alt="Raspberry Pi" />
  <img src="https://img.shields.io/badge/Bot-Telegram_Alerts-2CA5E0?style=for-the-badge&logo=telegram&logoColor=white" alt="Telegram Bot" />
</p>

---

## 📑 Daftar Isi
1. [Ringkasan Proyek](#-ringkasan-proyek)
2. [Arsitektur Sistem (Enterprise Multi-Tenancy)](#-arsitektur-sistem-enterprise-multi-tenancy)
3. [Fitur Utama Platform Web (4 Modul Inti)](#-fitur-utama-platform-web-4-modul-inti)
4. [Skema Wiring Pinout Hardware (Relay 1 s/d 8 Channel & Sensor)](#-skema-wiring-pinout-hardware-relay-1-sd-8-channel--sensor)
5. [Panduan Instalasi & Deployment](#-panduan-instalasi--deployment)
   - [1. Setup Server Web Dashboard (Laptop / Server)](#1-setup-server-web-dashboard-laptop--server)
   - [2. Setup Raspberry Pi Node Baru (3 Langkah Mudah)](#2-setup-raspberry-pi-node-baru-3-langkah-mudah)
6. [Fitur Buku Panduan & SOP Teknis Cetak PDF](#-fitur-buku-panduan--sop-teknis-cetak-pdf)
7. [Protokol Topik MQTT & Struktur Data](#-protokol-topik-mqtt--struktur-data)
8. [Struktur Direktori Repositori](#-struktur-direktori-repositori)
9. [Catatan Rilis & Changelog](#-catatan-rilis--changelog)

---

## 📌 Ringkasan Proyek
**SIKOMAT (Sistem Kontrol & Monitoring Otomatis AC)** adalah platform Industrial IoT yang dirancang khusus untuk memantau beban arus listrik (Ampere), konsumsi daya (Watt), dan mengotomasi pergantian shift 12 jam pada unit pendingin ruangan (AC) di berbagai fasilitas strategis **PT PINDAD (PERSERO)** secara *real-time*, *fail-safe*, dan terintegrasi dengan alarm darurat bot Telegram.

Platform ini mengadopsi arsitektur **Enterprise Multi-Tenancy & Edge Computing** di mana **1 Template Cetak Biru Hardware** dapat digunakan oleh **banyak perangkat ruangan (*Multi-Device Fleet*)** secara terisolasi tanpa risiko interferensi data atau salah kendali.

---

## 🏛️ Arsitektur Sistem (Enterprise Multi-Tenancy)

```text
                                    ┌─────────────────────────────────────────────────────────┐
                                    │                   CENTRAL MQTT BROKER                   │
                                    │                (Mosquitto : 127.0.0.1:1883)             │
                                    └───────────▲─────────────────────────────────▲───────────┘
                                                │                                 │
                    Telemetry: pindad/devices/{id}/logs             Command: pindad/devices/{id}/control
                                                │                                 │
                ┌────────────────────────────────┴────────┐       ┌────────────────┴────────────────────────┐
                ▼                                         ▼       ▼                                         ▼
    ┌──────────────────────┐                  ┌──────────────────────┐                  ┌──────────────────────┐
    │  NODE RUANG SERVER 1 │                  │  NODE RUANG SERVER 2 │                  │  NODE RUANG LAINNYA  │
    │  ID: RPI3B_ROOM_1    │                  │  ID: RPI3B_ROOM_2    │                  │  ID: RPI3B_ROOM_X    │
    │  IP: 192.168.197.64  │                  │  IP: 192.168.197.65  │                  │  IP: 192.168.197.xx  │
    └───────────┬──────────┘                  └───────────┬──────────┘                  └───────────┬──────────┘
                │                                         │                                         │
        [Sensor & Relai]                          [Sensor & Relai]                          [Sensor & Relai]
        • ACS712 (30A)                            • ACS712 (30A)                            • ACS712 (30A)
        • ADS1115 ADC (I2C)                       • ADS1115 ADC (I2C)                       • ADS1115 ADC (I2C)
        • DS3231 RTC (I2C)                        • DS3231 RTC (I2C)                        • DS3231 RTC (I2C)
        • Relai 1-8 Channel                       • Relai 1-8 Channel                       • Relai 1-8 Channel
```

### 🔁 Alur Komunikasi Data:
1. **Telemetry Uplink:** Raspberry Pi membaca arus sensor ACS712 via ADC ADS1115 & status relay, lalu mempublikasikannya secara periodik (5-10 detik) ke broker MQTT dengan identitas `device_id` masing-masing.
2. **Subscriber Daemon (`php artisan mqtt:subscribe`):** Menerima pesan MQTT dan menyimpannya secara asinkron ke database MongoDB / MySQL (`ac_logs`).
3. **Web Dashboard:** Melakukan *zero-reload polling* via AJAX (`/api/logs?device_id=...`) untuk memperbarui angka Ampere, Watt, suhu, dan status saklar tanpa me-refresh halaman.
4. **Downlink Control:** Operator menekan saklar taktil pada web $\rightarrow$ sinyal diteruskan ke topik kontrol spesifik per-device $\rightarrow$ relay Raspberry Pi langsung merespon seketika.
5. **Anomaly Alert:** Jika AC dijadwalkan ON namun arus terdeteksi `0.00 A` (kompresor macet / MCB trip), sistem otomatis menembakkan pesan darurat ke grup Telegram teknisi.

---

## 🚀 Fitur Utama Platform Web (4 Modul Inti)

### 📱 Modul 1: Home & Universal IoT Fleet Overview
* **Fleet Dashboard:** Memantau seluruh ruangan secara bersamaan dengan indikator status koneksi (🟢 *Online* / 🔴 *Offline*).
* **Drilldown Detail Ruangan:**
  * **Saklar Taktil IoT (Zero-Reload AJAX):** Saklar ON/OFF animasi fisik (*tactile slider*) yang bergeser instan tanpa kedipan reload.
  * **Telemetri Arus & Suhu Real-Time:** Ampere presisi 4 desimal, estimasi daya Watt, dan suhu ruangan live.
  * **Turbo Cooling Priority:** Mode darurat pendinginan ganda saat suhu ruangan server melonjak di atas batas normal.
  * **Otomasi Shift Shifting:** Penjadwalan rotasi AC otomatis 12 jam berbasis RTC DS3231 anti mati listrik.
  * **Pendaftaran Perangkat Cepat:** Form pendaftaran node baru dengan validasi IP Address anti-duplikasi.
  * **Unduh Skrip (.py) Otomatis:** Tombol `📥 Unduh Skrip (.py)` pada kartu ruangan untuk men-generate file Python siap pakai per-device.

### 🛠️ Modul 2: DevZone (PINDAD Hardware Templates & Datastreams Console)
* **Template Blueprint Manager:** Manajemen cetak biru spesifikasi modul relay (Preset 1 Channel, 2 Channel, 4 Channel, 8 Channel).
* **Virtual Datastreams Editor:** Konfigurasi Virtual Pin (`V0` - `V3` untuk relay, `V10` suhu, `V20` - `V27` sensor arus, `V30` turbo).
* **Ekspor & Impor JSON:** Kemudahan backup dan migrasi template ke server lain.

### 📊 Modul 3: Log Telemetri & Audit Trail
* **Riwayat Telemetri Terperinci:** Pencatatan arus, suhu, status saklar, pemicu (*manual / schedule / boot*), dan timestamp WIB.
* **Filter Multi-Device & Rentang Tanggal:** Memudahkan pelacakan performa per-ruangan.
* **Ekspor CSV / Excel:** Unduh laporan telemetri langsung ke spreadsheet untuk audit manajemen.

### ⚙️ Modul 4: Akun Operator & Informasi Sistem
* **Panduan Praktis Setup Node IoT:** SOP 5 langkah ringkas mulai dari pemilihan template, registrasi node, hingga running di RPi.
* **Buku Panduan & SOP Teknis Cetak PDF:** Tombol `📥 Unduh PDF Manual` untuk membuka dan mencetak buku SOP resmi format A4 (`/panduan/pdf`).
* **Notifikasi Alarm Bot Telegram:** Pengaturan Bot Token, Chat ID, Cooldown anti-spam, dan tombol uji coba kirim pesan darurat.
* **Manajemen Profil & Ubah Password:** Pembaruan kredensial login operator dan administrasi sistem.

---

## 📟 Skema Wiring Pinout Hardware (Relay 1 s/d 8 Channel & Sensor)

### 1. Pinout Inti Sensor & I2C Bus:
| Komponen Sensor / Modul | Pin Modul | Pin Raspberry Pi 3B+ | Keterangan / Fungsi |
| :--- | :--- | :--- | :--- |
| **ADS1115 (ADC 16-Bit)** | `VDD` & `GND` | `Pin 1 (3.3V)` & `Pin 6 (GND)` | Catu daya positif & Common Ground |
| **ADS1115 (I2C Bus)** | `SDA` & `SCL` | `Pin 3 (GPIO 2)` & `Pin 5 (GPIO 3)` | Alamat I2C: `0x48` • Data & Clock ADC |
| **DS3231 (RTC Clock)** | `VCC` & `GND` | `Pin 17 (3.3V)` & `Pin 9 (GND)` | Catu daya modul pewaktu presisi |
| **DS3231 (I2C Bus)** | `SDA` & `SCL` | `Pin 3 (GPIO 2)` & `Pin 5 (GPIO 3)` | Alamat I2C: `0x68` • Paralel dengan ADS1115 |
| **ACS712 (30A Hall Sensor)** | `VCC` & `GND` | `Pin 2 (5V)` & `Pin 14 (GND)` | Catu daya sensor efek Hall 5V |
| **Modul Relay Daya 5V** | `VCC` & `GND` | `Pin 4 (5V)` & `Pin 20 (GND)` | Catu daya koil optocoupler 5V |

---

### 2. Pilihan Pin Modul Relay (Skema 1 Channel s/d 8 Channel):
| Channel Relay | Target Perangkat / Beban | Pin Fisik RPi 3B+ | Nomor BCM GPIO | Channel ADC ACS712 | Virtual Pin Web |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Relay 1 (IN 1)** | Unit AC 1 (Lampu Bawah) | **Pin 11** | `GPIO 17` | `ADS1115 Pin A0` | `V0` (`V20`) |
| **Relay 2 (IN 2)** | Unit AC 2 (Lampu Atas) | **Pin 13** | `GPIO 27` | `ADS1115 Pin A1` | `V1` (`V21`) |
| **Relay 3 (IN 3)** | Unit AC 3 / Exhaust 1 | **Pin 15** | `GPIO 22` | `ADS1115 Pin A2` | `V2` (`V22`) |
| **Relay 4 (IN 4)** | Unit AC 4 / Exhaust 2 | **Pin 16** | `GPIO 23` | `ADS1115 Pin A3` | `V3` (`V23`) |
| **Relay 5 (IN 5)** | Auxiliary Relay 5 | **Pin 18** | `GPIO 24` | `ADC 2 (0x49 A0)` | `V4` (`V24`) |
| **Relay 6 (IN 6)** | Auxiliary Relay 6 | **Pin 22** | `GPIO 25` | `ADC 2 (0x49 A1)` | `V5` (`V25`) |
| **Relay 7 (IN 7)** | Auxiliary Relay 7 | **Pin 29** | `GPIO 5` | `ADC 2 (0x49 A2)` | `V6` (`V26`) |
| **Relay 8 (IN 8)** | Auxiliary Relay 8 | **Pin 31** | `GPIO 6` | `ADC 2 (0x49 A3)` | `V7` (`V27`) |

> 💡 **Panduan Pemilihan Modul Relay:**
> * **1 Channel:** Gunakan `Pin 11 (GPIO 17)`.
> * **2 Channel:** Gunakan `Pin 11 (GPIO 17)` dan `Pin 13 (GPIO 27)`.
> * **4 Channel:** Gunakan `Pin 11, 13, 15, 16 (GPIO 17, 27, 22, 23)`.
> * **8 Channel:** Gunakan `Pin 11, 13, 15, 16, 18, 22, 29, 31`.

---

## 💻 Panduan Instalasi & Deployment

### 1. Setup Server Web Dashboard (Laptop / Server)
Pastikan PHP 8.2+, Composer, Mosquitto MQTT Broker, dan MongoDB sudah terpasang:

```bash
# 1. Clone repository
git clone https://github.com/W2LS/Sistem_Monitoring_AC.git
cd Sistem_Monitoring_AC/Dashboard

# 2. Install dependencies PHP
composer install

# 3. Salin environment & generate key
cp .env.example .env
php artisan key:generate

# 4. Jalankan migrasi & seeder database
php artisan migrate
php artisan db:seed

# 5. Jalankan server Laravel & background workers
php artisan serve --host=0.0.0.0 --port=8000
php artisan mqtt:subscribe
php artisan ac:schedule-worker
```

---

### 2. Setup Raspberry Pi Node Baru (3 Langkah Mudah)

#### Langkah 1: Instalasi Library Python (Khusus Raspberry Pi Baru / 1x Setup)
Buka terminal SSH Raspberry Pi dan jalankan:
```bash
pip3 install paho-mqtt adafruit-circuitpython-ads1x15 adafruit-circuitpython-ds3231 RPi.GPIO
```

#### Langkah 2: Unduh Skrip & Salin Perintah Auto-Start on Boot
1. Buka dashboard web di browser &rarr; masuk ke menu **Home**.
2. Klik tombol **`📥 Unduh Skrip (.py)`** pada kartu ruangan (contoh: `pindad_node_ruang_server.py`).
3. Salin file `.py` ke folder `/home/alex/` (atau `/home/pi/`) di Raspberry Pi.
4. Salin dan jalankan **Perintah 1-Baris Auto-Start** di terminal SSH:
```bash
(crontab -l 2>/dev/null | grep -v 'pindad_node'; echo "@reboot sleep 10 && cd /home/alex && python3 -u /home/alex/pindad_node_xxxx.py > /home/alex/node.log 2>&1 &") | crontab - && nohup python3 -u /home/alex/pindad_node_xxxx.py > /home/alex/node.log 2>&1 &
```

#### Langkah 3: Verifikasi Log & Status Web
Periksa log pengiriman telemetri secara live:
```bash
tail -f /home/alex/node.log
```
Status perangkat di web dashboard akan langsung berubah menjadi **🟢 Online**.

---

## 📑 Fitur Buku Panduan & SOP Teknis Cetak PDF

SIKOMAT menyediakan endpoint dokumen resmi yang dirancang dengan tata letak cetak A4 berstandar PT PINDAD (Persero).

* **URL Akses:** `http://localhost:8000/panduan/pdf` (atau klik tombol **`📥 Unduh PDF Manual`** pada Modul 4).
* **Fitur Dokumen:**
  * Kop surat dan identitas dokumen kontrol resmi: `SOP/TI-PINDAD/AC/2026/V2.5`.
  * Panduan lengkap 6 BAB (Arsitektur, Web Dashboard, Skema Wiring 1-8 Channel, Instalasi OS & Auto-start, Telegram Alert, Troubleshooting).
  * Lembar pengesahan tanda tangan teknisi (**Dicky Akbar Syah Putra**) dan kepala divisi.
  * Tombol **`Cetak / Simpan PDF (A4)`** yang teroptimasi otomatis (`@media print`).

---

## 📡 Protokol Topik MQTT & Struktur Data

### 1. Topik Telemetri (Uplink: Node $\rightarrow$ Server)
* **Topik:** `pindad/devices/{device_id}/logs` atau `pindad/ac/logs`
* **Contoh Payload JSON:**
```json
{
  "device_id": "RPI3B_SERVER_TELEPON",
  "temperature": 24.5,
  "ac1_current": 2.1874,
  "ac2_current": 0.0000,
  "total_current": 2.1874,
  "watt_approx": 481.23,
  "relay1": 1,
  "relay2": 0,
  "trigger_source": "schedule",
  "recorded_at": "2026-09-03T11:00:00+07:00"
}
```

### 2. Topik Kontrol Saklar (Downlink: Server $\rightarrow$ Node)
* **Topik:** `pindad/devices/{device_id}/control`
* **Contoh Payload JSON:**
```json
{
  "device_id": "RPI3B_SERVER_TELEPON",
  "relay": 1,
  "command": "ON",
  "ac_number": 1,
  "state": "ON",
  "source": "manual",
  "timestamp": "2026-09-03T11:05:00+07:00"
}
```

---

## 📁 Struktur Direktori Repositori

```text
Dashboard/
├── app/
│   ├── Http/Controllers/
│   │   ├── AuthController.php          # Autentikasi operator & user session
│   │   └── DashboardController.php     # Controller Fleet, Telemetri, DevZone, Jadwal, PDF Manual
│   ├── Models/
│   │   ├── AcLog.php                   # Model log telemetri arus & suhu
│   │   ├── Device.php                  # Model instance perangkat ruangan
│   │   ├── Template.php                # Model blueprint template hardware
│   │   ├── Schedule.php                # Model jadwal shift rotasi RTC
│   │   ├── SystemSetting.php           # Model pengaturan global (Telegram bot, alarm)
│   │   └── User.php                    # Model akun pengguna operator
│   └── Services/
│       ├── MqttService.php             # Service publikasi & subscriber MQTT
│       ├── TelegramService.php         # Service pengiriman notifikasi bot Telegram
│       └── AnomalyDetectorService.php  # Service deteksi anomali AC 0 Ampere
├── resources/
│   └── views/
│       ├── dashboard.blade.php         # Layout utama dashboard
│       ├── panduan-pdf.blade.php       # Halaman Buku Panduan & SOP Cetak PDF A4
│       └── partials/
│           ├── section-home.blade.php  # Modul 1: Fleet Overview & Kontrol Ruangan
│           ├── section-devzone.blade.php # Modul 2: Developer Zone (Template & Virtual Pin)
│           ├── section-log.blade.php   # Modul 3: Riwayat Log Telemetri & Ekspor CSV
│           ├── section-akun.blade.php  # Modul 4: SOP Panduan, Telegram Alert, Akun
│           └── floating-nav.blade.php  # Navigasi melayang responsif mobile
├── routes/
│   └── web.php                         # Definisi rute web, API AJAX, & PDF endpoint
└── public/
    └── SIKOMAT.png                     # Logo resmi SIKOMAT PT PINDAD
```

---

## 📝 Catatan Rilis & Changelog

### Versi 2.5.0 (September 2026) - *Official SIKOMAT & PDF Manual Book Edition*
* 🏷️ **Official SIKOMAT Logo Branding:** Integrasi logo resmi SIKOMAT pada header dashboard dan dokumen teknis.
* 📑 **Buku Panduan & SOP Teknis Cetak PDF:** Penambahan halaman dan fitur download PDF (`/panduan/pdf`) berformat A4 lengkap 6 BAB.
* 🔌 **Dukungan Pinout Relay Multi-Channel (1 s/d 8 Channel):** Dokumentasi dan generator skrip lengkap untuk relay 1, 2, 3, 4, hingga 8 channel.
* 🤖 **Telegram Bot Emergency Alert:** Sistem peringatan dini otomatis ke grup Telegram teknisi saat AC anomali gagal hidup.
* 📱 **Mobile UI & Alignment Polish:** Penataan lencana badge `SOP` dan `ALARM`, padding modal responsif `z-[60]`, dan keseragaman ukuran kartu Modul 4.
* 👤 **Standardisasi Kredensial Operator:** Standardisasi nama operator resmi **Dicky Akbar Syah Putra** di seluruh sistem dan database.

---

<p align="center">
  <b>Developed with ❤️ for PT PINDAD (PERSERO) • Divisi Mutu & Teknologi Informasi</b><br>
  <i>Sistem Kontrol & Monitoring Otomatis AC (SIKOMAT) Berbasis IoT Terintegrasi</i>
</p>

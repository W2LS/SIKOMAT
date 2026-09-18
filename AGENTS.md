# 🤖 AGENTS.MD — Panduan Arsitektur & Rangkuman Komprehensif SIKOMAT AC

> **Platform:** SIKOMAT (Sistem Kontrol & Monitoring Suhu AC Otomatis)  
> **Instansi:** PT PINDAD (PERSERO) — Divisi Mutu & Teknologi Informasi  
> **Operator / Lead Developer:** Dicky Akbar Syah Putra  
> **Versi Terkini:** v2.5.0 (Official SIKOMAT & PDF Manual Book Edition)  
> **Tujuan Dokumen:** Referensi teknis, peta arsitektur, spesifikasi protokol, dan panduan kerja untuk AI Agents serta pengembang lanjutan.

---

## 📌 1. Ringkasan Eksekutif & Tujuan Proyek

**SIKOMAT** adalah platform Industrial IoT (*Internet of Things*) terpadu yang dibangun untuk mengotomasi pergantian rotasi (*shift shifting*) 12 jam pada pendingin ruangan (AC), memantau beban arus listrik (*Ampere*), mengestimasi konsumsi daya (*Watt*), mengukur suhu ruangan, serta mendeteksi anomali kegagalan kompresor/kelistrikan secara *real-time* di seluruh fasilitas strategis **PT PINDAD (PERSERO)** (seperti Ruang Server Telepon, Ruang Server TI, Ruang Panel Listrik, dan Laboratorium Mutu).

Platform ini menerapkan arsitektur **Enterprise Multi-Tenancy & Edge Computing**:
* **1 Template Cetak Biru Hardware (*Blueprint*)** dapat digunakan oleh **banyak perangkat ruangan (*Multi-Device Fleet*)** secara terisolasi.
* Dilengkapi dengan sistem pengaman darurat (*fail-safe*), isolasi anomali arus 0A, alarm bot Telegram, generator skrip otomatis (.py), buku SOP teknis cetak PDF A4, dan launcher lintas-platform (*Windows/Linux/macOS/Raspberry Pi*).

---

## 🏛️ 2. Stack Teknologi & Arsitektur Sistem

```text
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                                   WEB DASHBOARD LAYER                                   │
│            Laravel 11/12 • Blade • Tailwind CSS 3 • Alpine.js 3 • Chart.js              │
│                (SPA-like Reactive UI, Zero-Reload AJAX Polling, Dark Glassmorphic)       │
└───────────────────────────────────────────▲─────────────────────────────────────────────┘
                                            │ HTTP / JSON API & WebSockets/AJAX
                                            ▼
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                                  BACKEND SERVICES LAYER                                 │
│  • AnomalyDetectorService (Arus 0A Check)       • TelegramService (Alarm Darurat Bot)   │
│  • MqttService (php-mqtt / Mosquitto Bridge)    • DomPDF / Blade A4 Manual Engine       │
│  • SQLite / MongoDB Atlas & Local Dual-Driver   • Task Scheduler (12h Shift Rotation)   │
└───────────────────────────────────────────▲─────────────────────────────────────────────┘
                                            │ MQTT TCP 1883 / REST Ingest (/api/telemetry)
                                            ▼
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                                   COMMUNICATION LAYER                                   │
│              Central MQTT Mosquitto Broker (1883) & REST Fallback (0.0.0.0:8000)        │
└───────────────────────────────────────────▲─────────────────────────────────────────────┘
                                            │ Bidirectional Uplink & Downlink
                                            ▼
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                                    EDGE NODES LAYER                                     │
│  • Raspberry Pi 3B+ / 4B (Python 3, Paho-MQTT, Adafruit ADS1x15, CircuitPython DS3231)  │
│  • ESP32 NodeMCU Prototype (C++ Arduino, PubSubClient, ArduinoJson)                     │
│  • Hardware: Sensor ACS712 30A • ADC ADS1115 16-Bit • RTC DS3231 • Relai 1-8 Channel    │
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

### Rincian Teknologi:
* **Backend:** PHP 8.2+, Laravel 11/12 (`app/Http/Controllers/DashboardController.php`, `AuthController.php`).
* **Frontend:** Blade Templates, Tailwind CSS (Custom Dark Theme `#0b0f19`), Alpine.js (Reactivity), FontAwesome 6, Chart.js.
* **Database:** SQLite (Default zero-config: `database/database.sqlite`) & MongoDB support (`jenssegers/mongodb`).
* **Hardware Edge:** Raspberry Pi 3B+ / 4B, ESP32 Microcontroller, Sensor Arus ACS712-30A, ADC 16-Bit ADS1115 (I2C `0x48`), Modul RTC DS3231 (I2C `0x68`), Modul Relai 5V Optocoupler (Active LOW 1 s/d 8 Channel).
* **Komunikasi:** MQTT (Eclipse Mosquitto TCP port `1883`), HTTP REST API (`/api/telemetry`, `/api/logs`).

---

## 🚀 3. Struktur 4 Modul Inti Platform Web

Dashboard web SIKOMAT dirancang dengan navigasi melayang (*Glassmorphic Floating Nav*) yang membagi fungsi ke dalam 4 modul utama:

### 📱 Modul 1: Home & Universal Fleet Overview (`section-home.blade.php`)
* **Fleet Dashboard:** Menampilkan seluruh perangkat ruangan dalam bentuk kartu interaktif (*grid*) dengan status koneksi live (🟢 *Online* / 🔴 *Offline*).
* **Saklar Taktil IoT (Zero-Reload AJAX):** Saklar ON/OFF animasi fisik (*tactile slider*) per-AC/Relai yang merespons instan tanpa kedipan reload halaman.
* **Telemetri Live:** Monitoring beban arus listrik presisi 4 desimal (*Ampere*), estimasi konsumsi daya (*Watt*), dan suhu ruangan live.
* **Shift Shifting 12 Jam Otomatis:** Otomasi rotasi beban AC Utama & Cadangan berbasis RTC hardware DS3231 (anti reset saat mati lampu).
* **Turbo Cooling Priority:** Mode pendinginan ganda darurat saat suhu ruangan server melonjak melebihi ambang batas.
* **Master Control Switch:** Tombol kendali global untuk menyalakan/mematikan seluruh unit AC sekaligus.
* **Modal Setup Node IoT:** Form pendaftaran node baru, validasi IP Address unik, dan tombol unduh skrip Python per-device (*auto-generated token & config*).

### 🛠️ Modul 2: Developer Zone (`section-developer-zone.blade.php`)
* **Template Blueprint Manager:** Manajemen spesifikasi cetak biru hardware (Preset 1, 2, 4, dan 8 Channel).
* **Virtual Datastreams Console:** Konfigurasi Pin Virtual (`V0`-`V7` saklar relay, `V10` suhu, `V20`-`V27` sensor arus ACS712, `V30` turbo).
* **Selector Tipe Koneksi:** Pilihan koneksi node (`Ethernet LAN`, `Wi-Fi Jaringan Lokal`, `MQTT Broker Terpusat`).
* **Ekspor & Impor JSON:** Kemudahan migrasi dan backup konfigurasi template antar-server.

### 📊 Modul 3: Log Telemetri & Audit Trail (`section-riwayat.blade.php`)
* **Pencatatan Telemetri Lengkap:** Riwayat pembacaan sensor arus, suhu, status relai, sumber pemicu (*manual / schedule / boot / turbo*), dan waktu WIB.
* **Filter Multi-Device & Rentang Waktu:** Pelacakan performa per-ruangan dan filter tanggal terintegrasi.
* **Ekspor CSV / Excel:** Unduh laporan telemetri langsung ke format spreadsheet untuk keperluan audit dan manajemen energi.

### ⚙️ Modul 4: Akun Operator & Sistem (`section-akun.blade.php`)
* **SOP Panduan Setup Node IoT:** Panduan 5 langkah cepat instalasi dari registrasi hingga eksekusi auto-start di Raspberry Pi.
* **Buku Panduan & SOP Teknis Cetak PDF:** Tombol akses ke endpoint `/panduan/pdf` untuk mencetak buku manual resmi format A4 standar PT PINDAD.
* **Notifikasi Alarm Bot Telegram:** Konfigurasi Telegram Bot Token, Chat ID, Cooldown anti-spam, dan pengujian kirim pesan darurat live.
* **Manajemen Akun Operator:** Pembaruan nama profil, email, dan ganti kata sandi operator.

---

## 📟 4. Skema Pinout Hardware & Wiring Relai (1 s/d 8 Channel)

### 1. Pinout Sensor & Bus I2C:
| Modul / Komponen | Pin Modul | Pin Raspberry Pi 3B+ | Fungsi / Keterangan |
| :--- | :--- | :--- | :--- |
| **ADS1115 (ADC 16-Bit)** | `VDD` & `GND` | `Pin 2 / 4 (5V)` & `Pin 6 (GND)` | Catu daya 5V (mencegah latch-up cold boot & match range ACS712 5V) |
| **ADS1115 (I2C Bus)** | `SDA` & `SCL` | `Pin 3 (GPIO 2)` & `Pin 5 (GPIO 3)` | Alamat I2C: `0x48` • Data & Clock ADC |
| **DS3231 (RTC Clock)** | `VCC` & `GND` | `Pin 1 / 17 (3.3V)` & `Pin 9 (GND)` | Catu daya RTC presisi baterai CR2032 |
| **DS3231 (I2C Bus)** | `SDA` & `SCL` | `Pin 3 (GPIO 2)` & `Pin 5 (GPIO 3)` | Alamat I2C: `0x68` • Paralel pada Bus I2C |
| **ACS712 (30A Hall Sensor)** | `VCC` & `GND` | `Pin 2 (5V)` & `Pin 14 (GND)` | Catu daya 5V sensor efek Hall |
| **Modul Relai 5V** | `VCC` & `GND` | `Pin 4 (5V)` & `Pin 20 (GND)` | Catu daya koil optocoupler relai |

### 2. Pemetaan Saluran Relai (1 - 8 Channel):
| Saluran Relai | Beban / Unit AC | Pin Fisik RPi | BCM GPIO | Channel ADC ACS712 | Virtual Pin Web |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Relai 1 (IN 1)** | Unit AC 1 (Lampu Bawah) | **Pin 11** | `GPIO 17` | `ADS1115 Pin A0` | `V0` / `V20` |
| **Relai 2 (IN 2)** | Unit AC 2 (Lampu Atas) | **Pin 13** | `GPIO 27` | `ADS1115 Pin A1` | `V1` / `V21` |
| **Relai 3 (IN 3)** | Unit AC 3 / Exhaust 1 | **Pin 15** | `GPIO 22` | `ADS1115 Pin A2` | `V2` / `V22` |
| **Relai 4 (IN 4)** | Unit AC 4 / Exhaust 2 | **Pin 16** | `GPIO 23` | `ADS1115 Pin A3` | `V3` / `V23` |
| **Relai 5 (IN 5)** | Auxiliary Relai 5 | **Pin 18** | `GPIO 24` | `ADC 2 (0x49 A0)` | `V4` / `V24` |
| **Relai 6 (IN 6)** | Auxiliary Relai 6 | **Pin 22** | `GPIO 25` | `ADC 2 (0x49 A1)` | `V5` / `V25` |
| **Relai 7 (IN 7)** | Auxiliary Relai 7 | **Pin 29** | `GPIO 5` | `ADC 2 (0x49 A2)` | `V6` / `V26` |
| **Relai 8 (IN 8)** | Auxiliary Relai 8 | **Pin 31** | `GPIO 6` | `ADC 2 (0x49 A3)` | `V7` / `V27` |

---

## 📡 5. Protokol Komunikasi & Spesifikasi API

### 1. Ingest Telemetri Sensor (Node $\rightarrow$ Server Web):
* **Endpoint HTTP:** `POST /api/telemetry`
* **Topik MQTT:** `pindad/devices/{device_id}/logs` atau `pindad/ac/logs`
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
  "recorded_at": "2026-09-08T12:00:00+07:00"
}
```

### 2. Downlink Kontrol Saklar (Server Web $\rightarrow$ Node):
* **Endpoint HTTP:** `POST /ac/control` atau `POST /devices/control-stream`
* **Topik MQTT:** `pindad/devices/{device_id}/control`
* **Contoh Payload JSON:**
```json
{
  "device_id": "RPI3B_SERVER_TELEPON",
  "relay": 1,
  "command": "ON",
  "ac_number": 1,
  "state": "ON",
  "source": "manual",
  "timestamp": "2026-09-08T12:05:00+07:00"
}
```

### 3. AJAX Live Polling (Browser $\rightarrow$ Server Web):
* **Endpoint:** `GET /api/logs?device_id={device_id}`
* **Fungsi:** Mengambil status relai, data arus terkini, suhu, dan grafik riwayat tanpa me-reload halaman web.

---

## 🤖 6. Layanan Otomasi & Deteksi Anomali

1. **`AnomalyDetectorService.php`:**
   * Menganalisis telemetri masuk. Jika sebuah relai AC dalam posisi `ON` tetapi arus terdeteksi `0.00 A` secara terus menerus (indikasi kompresor macet, thermal overload trip, atau MCB putus), status anomali ditandai dan diteruskan ke bot Telegram.
2. **`TelegramService.php`:**
   * Menembakkan pesan peringatan darurat format Markdown/HTML ke grup teknisi PT PINDAD dengan menyertakan nama ruangan, nomor AC, arus terbaca, suhu saat kejadian, dan timestamp.
   * Dilengkapi fitur **Cooldown Interval** untuk mencegah *spamming* pesan.
3. **`MqttService.php`:**
   * Mengelola koneksi klien MQTT terpusat untuk mempublikasikan perintah kontrol saklar ke topik perangkat tujuan.

---

## 🖥️ 7. Script Launcher & Auto-Start Lintas Platform

### 1. Windows Launcher: [`start-lan-server.bat`](file:///c:/Users/dicky/Documents/Prototype/Project%20Maggang%201/Dashboard/start-lan-server.bat)
* Otomatis berpindah direktori (`cd /d "%~dp0"`).
* Mengecek instalasi PHP, file konfigurasi `.env`, dan database `database.sqlite`.
* Mendeteksi alamat IP jaringan lokal aktif (Wi-Fi / Ethernet `192.168.x.x` / `10.x.x.x`).
* Menampilkan URL dashboard siap klik untuk localhost, laptop lain, dan node Raspberry Pi.
* Menjalankan server `php artisan serve --host=0.0.0.0 --port=8000`.

### 2. Linux / macOS / Raspberry Pi Launcher: [`start-lan-server.sh`](file:///c:/Users/dicky/Documents/Prototype/Project%20Maggang%201/Dashboard/start-lan-server.sh)
* Skrip bash portable (`chmod +x start-lan-server.sh && ./start-lan-server.sh`).
* Mendeteksi IP via `hostname -I` / `ip addr` / `ifconfig`.
* Menjalankan server Laravel di jaringan lokal secara konsisten.

### 3. Skrip Node Edge Raspberry Pi: [`scripts/pindad_universal_node.py`](file:///c:/Users/dicky/Documents/Prototype/Project%20Maggang%201/Dashboard/scripts/pindad_universal_node.py)
* Mengendalikan GPIO Raspberry Pi, membaca ADS1115 & DS3231, mendengarkan MQTT downlink, dan mempublikasikan telemetri uplink.
* Perintah auto-start on boot via crontab:
```bash
(crontab -l 2>/dev/null | grep -v 'pindad_node'; echo "@reboot sleep 10 && cd /home/alex && python3 -u /home/alex/pindad_universal_node.py > /home/alex/node.log 2>&1 &") | crontab - && nohup python3 -u /home/alex/pindad_universal_node.py > /home/alex/node.log 2>&1 &
```

---

## 📂 8. Peta Struktur Direktori Proyek

```text
Dashboard/
├── AGENTS.md                           # Panduan arsitektur komprehensif untuk AI Agents & Developer
├── README.md                           # Dokumentasi publik proyek & panduan instalasi
├── PROJECT_ROADMAP_CHECKLIST.md        # Roadmap pengembangan & status fitur
├── start-lan-server.bat                # Launcher otomatis Windows (LAN IP auto-detect)
├── start-lan-server.sh                 # Launcher otomatis Linux / RPi / macOS
│
├── app/
│   ├── Http/Controllers/
│   │   ├── AuthController.php          # Manajemen session & autentikasi operator
│   │   └── DashboardController.php     # Controller utama (Fleet, Relai, DevZone, Logs, PDF)
│   ├── Models/
│   │   ├── AcLog.php                   # Model log arus & suhu
│   │   ├── Device.php                  # Model data node ruangan IoT
│   │   ├── Schedule.php                # Model jadwal rotasi shift RTC
│   │   ├── SystemSetting.php           # Model setting bot Telegram & sistem
│   │   ├── Template.php                # Model template blueprint pin virtual
│   │   └── User.php                    # Model akun pengguna operator
│   └── Services/
│       ├── AnomalyDetectorService.php  # Deteksi anomali kompresor/arus 0A
│       ├── MqttService.php             # Publikasi & listener broker MQTT
│       └── TelegramService.php         # Engine notifikasi darurat bot Telegram
│
├── resources/
│   └── views/
│       ├── dashboard.blade.php         # Layout utama dashboard
│       ├── panduan-pdf.blade.php       # Halaman Buku SOP & Manual Teknis Cetak PDF A4
│       ├── auth/
│       │   └── login.blade.php         # Halaman login operator
│       └── partials/
│           ├── section-home.blade.php  # Modul 1: Fleet Overview & Kontrol Ruangan
│           ├── section-developer-zone.blade.php # Modul 2: Template Blueprint & Datastreams
│           ├── section-riwayat.blade.php # Modul 3: Log Telemetri & Export CSV
│           ├── section-akun.blade.php  # Modul 4: Setup SOP, Telegram Alert & Akun
│           └── floating-nav.blade.php  # Navigasi melayang kaca responsif
│
├── routes/
│   └── web.php                         # Routing web, endpoint AJAX, API ingest, & PDF
│
├── scripts/
│   ├── pindad_universal_node.py        # Skrip Python inti untuk Raspberry Pi Edge Node
│   ├── pindad_setup_wizard.py          # Wizard konfigurasi terminal interaktif
│   ├── setup_raspberry_pi.sh           # Skrip instalasi dependensi pip & driver
│   └── node_config.json                # File konfigurasi lokal node
│
├── esp32_mqtt_prototype/
│   └── esp32_mqtt_prototype.ino        # Firmware prototype ESP32 C++ Arduino
│
└── public/
    └── SIKOMAT.png                     # Logo resmi SIKOMAT PT PINDAD
```

---

## 📋 9. Pedoman Pengembangan untuk AI Agent & Pengembang

1. **Integritas Dokumentasi & Operator:**
   * Nama operator resmi sistem adalah **Dicky Akbar Syah Putra** (PT PINDAD PERSERO • Divisi Mutu & TI). Selalu pertahankan konsistensi identitas ini pada dokumen PDF, database, dan komentar kode.
2. **Desain UI / UX:**
   * Gunakan palette gelap elegan (*Dark Glassmorphic UI*), animasi mikro yang halus, lencana status kontras tinggi, dan *zero-reload AJAX polling* agar pengalaman pengguna tetap responsif dan bebas kedipan (*flicker*).
3. **Kompatibilitas Lintas Perangkat:**
   * Pastikan endpoint REST (`/api/telemetry`) dan MQTT subscriber selalu siap menerima data dari berbagai tipe hardware (*Raspberry Pi, ESP32, atau PC bridge*).
4. **Keamanan & Validasi Input:**
   * Semua input IP Address, ID perangkat, dan token hardware harus divalidasi ketat untuk menghindari konflik duplikasi pada *multi-tenant fleet*.
5. **Pembaruan Git:**
   * Setiap kali menyelesaikan fitur atau perbaikan penting, pastikan perubahan diuji, di-*commit* dengan pesan terstruktur (*conventional commits*), dan di-*push* ke branch `main` repositori GitHub (`W2LS/Sistem_Monitoring_AC`).

---

*Dokumen ini dibuat otomatis sebagai panduan arsitektur definitif sistem SIKOMAT PT PINDAD (PERSERO).*

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buku Panduan & SOP Teknis SIKOMAT - PT PINDAD</title>
    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1D1616;
            background-color: #F8FAFC;
        }
        code, pre {
            font-family: 'JetBrains Mono', monospace;
        }
        @media print {
            body {
                background-color: #FFFFFF !important;
                color: #000000 !important;
                font-size: 11pt;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
            .avoid-break {
                page-break-inside: avoid;
            }
            .doc-container {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            @page {
                size: A4 portrait;
                margin: 15mm 15mm 15mm 15mm;
            }
        }
    </style>
</head>
<body class="min-h-screen py-6 sm:py-10">

    <!-- FLOATING TOP ACTION BAR (NON-PRINTABLE) -->
    <div class="no-print fixed top-0 left-0 right-0 z-50 bg-[#1D1616]/95 backdrop-blur-md text-white border-b border-white/10 px-4 py-3 shadow-lg">
        <div class="max-w-5xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <img src="/SIKOMAT.png" alt="SIKOMAT Logo" class="h-8 object-contain brightness-0 invert" onerror="this.src='/logo-pindad.png';">
                <div>
                    <h1 class="text-xs sm:text-sm font-black tracking-tight text-white">BUKU PANDUAN & SOP TEKNIS SIKOMAT</h1>
                    <p class="text-[10px] text-slate-300">Dokumen Standar Operasional Prosedur Sistem IoT PT PINDAD</p>
                </div>
            </div>
            <div class="flex items-center gap-2.5 w-full sm:w-auto">
                <a href="{{ route('dashboard') }}" 
                   class="flex-1 sm:flex-none px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs uppercase tracking-wider transition text-center cursor-pointer">
                    ← Kembali
                </a>
                <button onclick="window.print()" 
                        class="flex-1 sm:flex-none px-5 py-2 rounded-xl bg-[#D84040] hover:bg-[#8E1616] text-white font-black text-xs uppercase tracking-wider shadow-md transition flex items-center justify-center gap-2 cursor-pointer active:scale-95">
                    <span>📥</span>
                    <span>Cetak / Simpan PDF (A4)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- MAIN A4 DOCUMENT CONTAINER -->
    <div class="doc-container max-w-4xl mx-auto bg-white rounded-3xl sm:rounded-[36px] shadow-2xl border border-slate-200/80 p-6 sm:p-12 mt-12 sm:mt-10 space-y-10">

        <!-- ========================================================================= -->
        <!-- COVER / HEADER RESMI PT PINDAD -->
        <!-- ========================================================================= -->
        <div class="border-b-4 border-[#8E1616] pb-8 space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <span class="text-[11px] font-black uppercase tracking-widest text-[#8E1616] block">
                        PT PINDAD (PERSERO) • DIVISI MUTU & TEKNOLOGI INFORMASI
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-black text-[#1D1616] tracking-tight">
                        BUKU PANDUAN PENGOPERASIAN & MANUAL HARDWARE IOT
                    </h1>
                    <p class="text-sm font-semibold text-slate-600">
                        Sistem Kontrol & Monitoring Otomatis AC Gedung (SIKOMAT) Berbasis Raspberry Pi & Laravel
                    </p>
                </div>
                <div class="hidden sm:block text-right shrink-0">
                    <div class="w-16 h-16 rounded-2xl bg-[#8E1616]/10 border border-[#8E1616]/20 flex items-center justify-center text-3xl">
                        ❄️
                    </div>
                </div>
            </div>

            <!-- DOCUMENT METADATA GRID -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-4 rounded-2xl border border-slate-200 text-xs">
                <div>
                    <span class="text-[9.5px] font-extrabold uppercase tracking-wider text-slate-500 block">No. Dokumen</span>
                    <span class="font-mono font-bold text-slate-800 text-[11px] block mt-0.5">SOP/TI-PINDAD/AC/2026/V2.5</span>
                </div>
                <div>
                    <span class="text-[9.5px] font-extrabold uppercase tracking-wider text-slate-500 block">Klasifikasi</span>
                    <span class="font-bold text-emerald-700 text-[11px] block mt-0.5">Dokumen Teknis Internal</span>
                </div>
                <div>
                    <span class="text-[9.5px] font-extrabold uppercase tracking-wider text-slate-500 block">Versi Engine</span>
                    <span class="font-bold text-[#8E1616] text-[11px] block mt-0.5">v2.5.0 (PINDAD Enterprise Architecture)</span>
                </div>
                <div>
                    <span class="text-[9.5px] font-extrabold uppercase tracking-wider text-slate-500 block">Penyusun / Operator</span>
                    <span class="font-bold text-slate-800 text-[11px] block mt-0.5">{{ $user->name ?? 'Dicky Akbar Syah Putra' }}</span>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- DAFTAR ISI RINGKAS -->
        <!-- ========================================================================= -->
        <div class="bg-rose-50/50 p-5 sm:p-6 rounded-2xl border border-rose-100 space-y-3.5 avoid-break">
            <h2 class="text-xs font-black uppercase tracking-widest text-[#8E1616] flex items-center gap-2">
                <span>📑</span>
                <span>DAFTAR ISI PANDUAN LENGKAP</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-xs font-medium text-slate-700">
                <a href="#bab1" class="hover:text-[#8E1616] transition flex items-start gap-2 group p-1 rounded-lg hover:bg-white/60">
                    <span class="font-mono font-black text-[#8E1616] whitespace-nowrap shrink-0">BAB 1.</span>
                    <span class="group-hover:underline leading-snug">Pendahuluan & Arsitektur IoT SIKOMAT</span>
                </a>
                <a href="#bab2" class="hover:text-[#8E1616] transition flex items-start gap-2 group p-1 rounded-lg hover:bg-white/60">
                    <span class="font-mono font-black text-[#8E1616] whitespace-nowrap shrink-0">BAB 2.</span>
                    <span class="group-hover:underline leading-snug">Panduan Penggunaan Web Dashboard</span>
                </a>
                <a href="#bab3" class="hover:text-[#8E1616] transition flex items-start gap-2 group p-1 rounded-lg hover:bg-white/60">
                    <span class="font-mono font-black text-[#8E1616] whitespace-nowrap shrink-0">BAB 3.</span>
                    <span class="group-hover:underline leading-snug">Perakitan Hardware & Pinout Relay (1-8 Ch)</span>
                </a>
                <a href="#bab4" class="hover:text-[#8E1616] transition flex items-start gap-2 group p-1 rounded-lg hover:bg-white/60">
                    <span class="font-mono font-black text-[#8E1616] whitespace-nowrap shrink-0">BAB 4.</span>
                    <span class="group-hover:underline leading-snug">Instalasi OS, Dependensi & Auto-Start Skrip</span>
                </a>
                <a href="#bab5" class="hover:text-[#8E1616] transition flex items-start gap-2 group p-1 rounded-lg hover:bg-white/60">
                    <span class="font-mono font-black text-[#8E1616] whitespace-nowrap shrink-0">BAB 5.</span>
                    <span class="group-hover:underline leading-snug">Setup Bot Telegram Alarm Darurat Anomali</span>
                </a>
                <a href="#bab6" class="hover:text-[#8E1616] transition flex items-start gap-2 group p-1 rounded-lg hover:bg-white/60">
                    <span class="font-mono font-black text-[#8E1616] whitespace-nowrap shrink-0">BAB 6.</span>
                    <span class="group-hover:underline leading-snug">Pengujian, Kalibrasi & Troubleshooting</span>
                </a>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- BAB 1: PENDAHULUAN & ARSITEKTUR SISTEM -->
        <!-- ========================================================================= -->
        <div id="bab1" class="space-y-4 pt-2">
            <div class="flex items-center gap-3 border-b border-slate-200 pb-3">
                <span class="w-8 h-8 rounded-xl bg-[#8E1616] text-white flex items-center justify-center font-black text-sm">1</span>
                <h2 class="text-lg sm:text-xl font-black text-[#1D1616] tracking-tight">
                    BAB I: PENDAHULUAN & ARSITEKTUR IOT
                </h2>
            </div>
            
            <p class="text-xs sm:text-sm text-slate-700 leading-relaxed text-justify">
                <strong>SIKOMAT (Sistem Kontrol & Monitoring Otomatis AC)</strong> adalah platform Industrial IoT yang dirancang khusus untuk memonitor suhu ruangan, arus listrik (Ampere), dan mengatur pergantian (shifting) operasional unit AC secara otomatis pada ruangan-ruangan vital PT PINDAD (seperti Ruang Server Telepon, Data Center, dan Ruang Kontrol) guna mencegah <em>overheating</em> serta menjaga efisiensi energi.
            </p>

            <div class="bg-slate-900 text-white p-4 sm:p-5 rounded-2xl border border-slate-800 space-y-3 avoid-break">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase tracking-wider text-amber-400">Diagram Alur Arsitektur Komunikasi IoT</span>
                    <span class="text-[10px] font-mono bg-white/10 px-2 py-0.5 rounded text-slate-300">Data Flow</span>
                </div>
                <div class="p-3 bg-black/60 rounded-xl font-mono text-[11px] sm:text-xs text-emerald-400 leading-relaxed overflow-x-auto whitespace-pre">
[Sensors: ACS712 + RTC DS3231] ──> [Raspberry Pi Node (Python Script)]
                                            │ (MQTT TCP 1883)
                                            ▼
[Mosquitto MQTT Broker] ──> [Laravel Dashboard Backend & MongoDB]
                                            │
               ┌────────────────────────────┴────────────────────────────┐
               ▼                                                         ▼
[Web Dashboard Real-Time GUI]                                [Telegram Alert Bot]
(Status, Live Chart, Kontrol Relay)                          (Peringatan Darurat AC Gagal)</div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- BAB 2: PANDUAN LENGKAP PENGOPERASIAN WEB DASHBOARD -->
        <!-- ========================================================================= -->
        <div id="bab2" class="space-y-6 pt-6 page-break">
            <div class="flex items-center gap-3 border-b border-slate-200 pb-3">
                <span class="w-8 h-8 rounded-xl bg-[#8E1616] text-white flex items-center justify-center font-black text-sm">2</span>
                <h2 class="text-lg sm:text-xl font-black text-[#1D1616] tracking-tight">
                    BAB II: PANDUAN PENGGUNAAN WEB DASHBOARD
                </h2>
            </div>

            <!-- MODUL 1: HOME -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3 avoid-break">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-black text-[#8E1616] uppercase tracking-wider flex items-center gap-2">
                        <span>🏠</span>
                        <span>2.1 Modul 1: Home (Universal IoT Fleet Overview)</span>
                    </h3>
                    <span class="bg-rose-100 text-[#8E1616] text-[9px] font-black uppercase px-2 py-0.5 rounded">Fleet Mode</span>
                </div>
                <ul class="text-xs text-slate-700 space-y-2 list-disc pl-5 leading-relaxed">
                    <li><strong>Monitoring Status Node:</strong> Melihat status seluruh ruangan secara serentak. Indikator 🟢 <em>Online</em> menandakan Raspberry Pi aktif mengirimkan telemetri, sedangkan 🔴 <em>Offline</em> menandakan perangkat kehilangan koneksi.</li>
                    <li><strong>Drilldown Detail Ruangan:</strong> Klik salah satu kartu ruangan untuk membuka tampilan drilldown terperinci: grafik suhu & arus ampere live, saklar relay manual per-unit AC, dan pengaturan jadwal shift.</li>
                    <li><strong>Pendaftaran Perangkat Baru:</strong> Klik tombol <b>`+ Tambah Perangkat Baru`</b> di halaman Home. Masukkan Nama Ruangan (misal: <i>Server Telepon</i>), IP Raspberry Pi, kapasitas AC, dan pilih Template Modul Relay.</li>
                    <li><strong>Unduh Skrip (.py) Mandiri:</strong> Setelah perangkat didaftarkan, klik tombol <b>`📥 Unduh Skrip (.py)`</b> pada kartu perangkat. Web akan otomatis men-generate file Python lengkap yang sudah berisi konfigurasi Device ID, IP, dan topic MQTT yang sesuai.</li>
                </ul>
            </div>

            <!-- MODUL 2: DEVZONE -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3 avoid-break">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-black text-[#8E1616] uppercase tracking-wider flex items-center gap-2">
                        <span>🛠️</span>
                        <span>2.2 Modul 2: Developer Zone (Template & Virtual Pin)</span>
                    </h3>
                    <span class="bg-slate-100 text-slate-700 text-[9px] font-black uppercase px-2 py-0.5 rounded">Blueprint Engine</span>
                </div>
                <ul class="text-xs text-slate-700 space-y-2 list-disc pl-5 leading-relaxed">
                    <li><strong>Konsep Template Hardware:</strong> Template berfungsi sebagai blueprint modul relay (1 Channel, 2 Channel, 4 Channel, atau 8 Channel) yang memetakan pin virtual IoT ke saklar fisik.</li>
                    <li><strong>Alokasi Virtual Pin:</strong>
                        <ul class="list-circle pl-5 mt-1 space-y-1 text-slate-600">
                            <li><code class="font-bold text-slate-800">V0, V1, V2, V3</code> : Saklar Relay Kontrol Unit AC 1 s/d AC 4</li>
                            <li><code class="font-bold text-slate-800">V4</code> : Total Akumulasi Beban Daya Nyata (Watt)</li>
                            <li><code class="font-bold text-slate-800">V20, V21, V22</code> : Telemetri Arus Listrik AC 1 s/d AC 4 (Ampere)</li>
                            <li><code class="font-bold text-slate-800">V30</code> : Mode Turbo Cooling Priority (Emergency Dual-Cooling)</li>
                        </ul>
                    </li>
                    <li><strong>Ekspor & Impor Template:</strong> Operator dapat mengunduh file blueprint JSON template untuk dicadangkan atau dibagikan ke server SIKOMAT lainnya.</li>
                </ul>
            </div>

            <!-- MODUL 3: LOG AC -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3 avoid-break">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-black text-[#8E1616] uppercase tracking-wider flex items-center gap-2">
                        <span>📊</span>
                        <span>2.3 Modul 3: Log AC & Histori Audit Trail</span>
                    </h3>
                    <span class="bg-slate-100 text-slate-700 text-[9px] font-black uppercase px-2 py-0.5 rounded">Audit & CSV</span>
                </div>
                <p class="text-xs text-slate-700 leading-relaxed">
                    Menyimpan rekaman riwayat arus ampere, status switching relay, suhu, dan event anomali. Operator dapat memfilter log berdasarkan perangkat dan rentang tanggal, serta mengunduh data dalam format file <strong>Excel / CSV</strong> untuk keperluan laporan audit fasilitas.
                </p>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- BAB 3: PERAKITAN HARDWARE NODE RASPBERRY PI DARI NOL -->
        <!-- ========================================================================= -->
        <div id="bab3" class="space-y-6 pt-6 page-break">
            <div class="flex items-center gap-3 border-b border-slate-200 pb-3">
                <span class="w-8 h-8 rounded-xl bg-[#8E1616] text-white flex items-center justify-center font-black text-sm">3</span>
                <h2 class="text-lg sm:text-xl font-black text-[#1D1616] tracking-tight">
                    BAB III: PANDUAN PERAKITAN HARDWARE & WIRING PINOUT
                </h2>
            </div>

            <!-- DAFTAR KOMPONEN -->
            <div class="space-y-3 avoid-break">
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">3.1 Daftar Komponen Perangkat Keras</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="font-bold text-slate-900 block">1. Raspberry Pi 3 Model B+</span>
                        <span class="text-slate-500 text-[11px]">Kontroler komputasi utama + MicroSD 16GB/32GB + PSU 5V 3A</span>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="font-bold text-slate-900 block">2. Modul ADC ADS1115 (16-Bit I2C)</span>
                        <span class="text-slate-500 text-[11px]">Mengubah sinyal analog sensor arus ACS712 menjadi data digital</span>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="font-bold text-slate-900 block">3. Sensor Arus Listrik ACS712 (30A)</span>
                        <span class="text-slate-500 text-[11px]">Sensor efek Hall pengukur arus beban kompresor AC (66mV/A)</span>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="font-bold text-slate-900 block">4. Real-Time Clock Maxim DS3231 I2C</span>
                        <span class="text-slate-500 text-[11px]">Penjaga akurasi jam operasional shifting saat offline / tanpa internet</span>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="font-bold text-slate-900 block">5. Modul Relay Optocoupler 5V</span>
                        <span class="text-slate-500 text-[11px]">Saklar pemutus/penghubung daya AC 220V (Active LOW)</span>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="font-bold text-slate-900 block">6. Terminal Blok & Kabel Jumper</span>
                        <span class="text-slate-500 text-[11px]">Kabel jumper Female-to-Female dan konektor listrik standar PLN</span>
                    </div>
                </div>
            </div>

            <!-- TABEL SKEMA WIRING / PINOUT -->
            <div class="space-y-3 avoid-break">
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">3.2 Skema Pengkabelan Inti (Core Wiring Table)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border border-slate-200 rounded-xl overflow-hidden">
                        <thead class="bg-slate-900 text-white text-[11px] uppercase tracking-wider">
                            <tr>
                                <th class="p-3">Komponen Sensor / Modul</th>
                                <th class="p-3">Pin Komponen</th>
                                <th class="p-3">Pin Raspberry Pi 3B+</th>
                                <th class="p-3">Keterangan / Fungsi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-slate-700 bg-white">
                            <tr class="bg-slate-50/50">
                                <td class="p-2.5 font-bold" rowspan="5">ADS1115 (ADC 16-Bit I2C)</td>
                                <td class="p-2.5 font-mono">VDD</td>
                                <td class="p-2.5 font-mono font-bold text-rose-700">Pin 2 / Pin 4 (5V DC)</td>
                                <td class="p-2.5 text-slate-500">Daya modul ADC (Mendukung rentang input analog 0-5V ACS712)</td>
                            </tr>
                            <tr class="bg-slate-50/50">
                                <td class="p-2.5 font-mono">GND</td>
                                <td class="p-2.5 font-mono font-bold text-slate-800">Pin 6 (GND)</td>
                                <td class="p-2.5 text-slate-500">Ground bersama (Common Ground)</td>
                            </tr>
                            <tr class="bg-slate-50/50">
                                <td class="p-2.5 font-mono">ADDR</td>
                                <td class="p-2.5 font-mono font-bold text-slate-800">GND (Pin 9 / Common GND)</td>
                                <td class="p-2.5 text-slate-500"><b>Wajib ke GND:</b> Mengunci alamat I2C stabil di <code class="font-bold text-emerald-700">0x48</code> & anti-noise</td>
                            </tr>
                            <tr class="bg-slate-50/50">
                                <td class="p-2.5 font-mono">SDA</td>
                                <td class="p-2.5 font-mono font-bold text-amber-600">Pin 3 (GPIO 2 / SDA)</td>
                                <td class="p-2.5 text-slate-500">Jalur data I2C Bus (Paralel dengan DS3231)</td>
                            </tr>
                            <tr class="bg-slate-50/50">
                                <td class="p-2.5 font-mono">SCL</td>
                                <td class="p-2.5 font-mono font-bold text-amber-600">Pin 5 (GPIO 3 / SCL)</td>
                                <td class="p-2.5 text-slate-500">Jalur clock I2C Bus (Paralel dengan DS3231)</td>
                            </tr>
                            <tr>
                                <td class="p-2.5 font-bold" rowspan="4">DS3231 (RTC Clock I2C)</td>
                                <td class="p-2.5 font-mono">VCC</td>
                                <td class="p-2.5 font-mono font-bold text-rose-700">Pin 17 (3.3V DC)</td>
                                <td class="p-2.5 text-slate-500">Daya modul RTC</td>
                            </tr>
                            <tr>
                                <td class="p-2.5 font-mono">GND</td>
                                <td class="p-2.5 font-mono font-bold text-slate-800">Pin 9 (GND)</td>
                                <td class="p-2.5 text-slate-500">Ground RTC</td>
                            </tr>
                            <tr>
                                <td class="p-2.5 font-mono">SDA</td>
                                <td class="p-2.5 font-mono font-bold text-amber-600">Pin 3 (GPIO 2 / SDA)</td>
                                <td class="p-2.5 text-slate-500">I2C SDA Paralel dengan ADS1115</td>
                            </tr>
                            <tr>
                                <td class="p-2.5 font-mono">SCL</td>
                                <td class="p-2.5 font-mono font-bold text-amber-600">Pin 5 (GPIO 3 / SCL)</td>
                                <td class="p-2.5 text-slate-500">I2C SCL Paralel dengan ADS1115</td>
                            </tr>
                            <tr class="bg-slate-50/50">
                                <td class="p-2.5 font-bold" rowspan="3">Sensor ACS712 (30A)</td>
                                <td class="p-2.5 font-mono">VCC / GND</td>
                                <td class="p-2.5 font-mono font-bold text-rose-700">Pin 2 (5V) & Pin 14 (GND)</td>
                                <td class="p-2.5 text-slate-500">Daya sensor efek Hall</td>
                            </tr>
                            <tr class="bg-slate-50/50">
                                <td class="p-2.5 font-mono">OUT Sensor AC 1</td>
                                <td class="p-2.5 font-mono font-bold text-emerald-700">ADS1115 Pin A0</td>
                                <td class="p-2.5 text-slate-500">Input analog pembacaan arus AC 1</td>
                            </tr>
                            <tr class="bg-slate-50/50">
                                <td class="p-2.5 font-mono">OUT Sensor AC 2</td>
                                <td class="p-2.5 font-mono font-bold text-emerald-700">ADS1115 Pin A1</td>
                                <td class="p-2.5 text-slate-500">Input analog pembacaan arus AC 2</td>
                            </tr>
                            <tr>
                                <td class="p-2.5 font-bold" rowspan="2">Modul Relay Daya</td>
                                <td class="p-2.5 font-mono">VCC / GND</td>
                                <td class="p-2.5 font-mono font-bold text-rose-700">Pin 4 (5V DC) & Pin 20 (GND)</td>
                                <td class="p-2.5 text-slate-500">Daya koil modul relay 5V</td>
                            </tr>
                            <tr>
                                <td class="p-2.5 font-mono">COM & NO</td>
                                <td class="p-2.5 font-mono text-slate-800">Seri Jalur Fasa AC 220V</td>
                                <td class="p-2.5 text-slate-500">Saklar daya AC / Kontaktor Magnetik</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SUB-SECTION 3.3: OPSI PIN MODUL RELAY 1, 2, 3, 4, S/D 8 CHANNEL -->
            <div class="space-y-3 avoid-break">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">
                        3.3 Pilihan Pin Modul Relay (Skema 1, 2, 3, 4, hingga 8 Channel)
                    </h3>
                    <span class="text-[9.5px] font-black uppercase bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-sans">1 s/d 8 Channel</span>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Gunakan tabel pemetaan GPIO di bawah ini sesuai dengan modul relay fisik yang Anda pasang. Skrip Python otomatis dari web SIKOMAT telah memprogram nomor-nomor GPIO ini secara presisi:
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border border-slate-200 rounded-xl overflow-hidden">
                        <thead class="bg-[#1D1616] text-white text-[10.5px] uppercase tracking-wider">
                            <tr>
                                <th class="p-2.5">Channel Relay</th>
                                <th class="p-2.5">Target Perangkat / Beban</th>
                                <th class="p-2.5">Pin Fisik RPi 3B+</th>
                                <th class="p-2.5">Nomor BCM GPIO</th>
                                <th class="p-2.5">Channel ADC ACS712</th>
                                <th class="p-2.5">Virtual Pin Web</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-slate-700 bg-white">
                            <tr class="bg-rose-50/30">
                                <td class="p-2.5 font-bold text-[#8E1616]">Relay 1 (IN 1)</td>
                                <td class="p-2.5 font-medium">Unit AC 1 (Lampu Bawah)</td>
                                <td class="p-2.5 font-mono font-bold text-slate-900">Pin 11</td>
                                <td class="p-2.5 font-mono font-bold text-indigo-700">GPIO 17</td>
                                <td class="p-2.5 font-mono text-emerald-700 font-bold">ADS1115 A0</td>
                                <td class="p-2.5 font-mono font-bold text-slate-800">V0 (V20)</td>
                            </tr>
                            <tr class="bg-rose-50/30">
                                <td class="p-2.5 font-bold text-[#8E1616]">Relay 2 (IN 2)</td>
                                <td class="p-2.5 font-medium">Unit AC 2 (Lampu Atas)</td>
                                <td class="p-2.5 font-mono font-bold text-slate-900">Pin 13</td>
                                <td class="p-2.5 font-mono font-bold text-indigo-700">GPIO 27</td>
                                <td class="p-2.5 font-mono text-emerald-700 font-bold">ADS1115 A1</td>
                                <td class="p-2.5 font-mono font-bold text-slate-800">V1 (V21)</td>
                            </tr>
                            <tr>
                                <td class="p-2.5 font-bold text-slate-800">Relay 3 (IN 3)</td>
                                <td class="p-2.5 font-medium">Unit AC 3 / Fan Exhaust 1</td>
                                <td class="p-2.5 font-mono font-bold text-slate-900">Pin 15</td>
                                <td class="p-2.5 font-mono font-bold text-indigo-700">GPIO 22</td>
                                <td class="p-2.5 font-mono text-emerald-700 font-bold">ADS1115 A2</td>
                                <td class="p-2.5 font-mono font-bold text-slate-800">V2 (V22)</td>
                            </tr>
                            <tr>
                                <td class="p-2.5 font-bold text-slate-800">Relay 4 (IN 4)</td>
                                <td class="p-2.5 font-medium">Unit AC 4 / Fan Exhaust 2</td>
                                <td class="p-2.5 font-mono font-bold text-slate-900">Pin 16</td>
                                <td class="p-2.5 font-mono font-bold text-indigo-700">GPIO 23</td>
                                <td class="p-2.5 font-mono text-emerald-700 font-bold">ADS1115 A3</td>
                                <td class="p-2.5 font-mono font-bold text-slate-800">V3 (V23)</td>
                            </tr>
                            <tr class="bg-slate-50/60">
                                <td class="p-2.5 font-bold text-slate-600">Relay 5 (IN 5)</td>
                                <td class="p-2.5 font-medium text-slate-500">Auxiliary Relay 5 (Ekspansi)</td>
                                <td class="p-2.5 font-mono font-bold text-slate-700">Pin 18</td>
                                <td class="p-2.5 font-mono font-bold text-slate-600">GPIO 24</td>
                                <td class="p-2.5 font-mono text-slate-400">ADC 2 (0x49 A0)</td>
                                <td class="p-2.5 font-mono text-slate-600">V4 (V24)</td>
                            </tr>
                            <tr class="bg-slate-50/60">
                                <td class="p-2.5 font-bold text-slate-600">Relay 6 (IN 6)</td>
                                <td class="p-2.5 font-medium text-slate-500">Auxiliary Relay 6 (Ekspansi)</td>
                                <td class="p-2.5 font-mono font-bold text-slate-700">Pin 22</td>
                                <td class="p-2.5 font-mono font-bold text-slate-600">GPIO 25</td>
                                <td class="p-2.5 font-mono text-slate-400">ADC 2 (0x49 A1)</td>
                                <td class="p-2.5 font-mono text-slate-600">V5 (V25)</td>
                            </tr>
                            <tr class="bg-slate-50/60">
                                <td class="p-2.5 font-bold text-slate-600">Relay 7 (IN 7)</td>
                                <td class="p-2.5 font-medium text-slate-500">Auxiliary Relay 7 (Ekspansi)</td>
                                <td class="p-2.5 font-mono font-bold text-slate-700">Pin 29</td>
                                <td class="p-2.5 font-mono font-bold text-slate-600">GPIO 5</td>
                                <td class="p-2.5 font-mono text-slate-400">ADC 2 (0x49 A2)</td>
                                <td class="p-2.5 font-mono text-slate-600">V6 (V26)</td>
                            </tr>
                            <tr class="bg-slate-50/60">
                                <td class="p-2.5 font-bold text-slate-600">Relay 8 (IN 8)</td>
                                <td class="p-2.5 font-medium text-slate-500">Auxiliary Relay 8 (Ekspansi)</td>
                                <td class="p-2.5 font-mono font-bold text-slate-700">Pin 31</td>
                                <td class="p-2.5 font-mono font-bold text-slate-600">GPIO 6</td>
                                <td class="p-2.5 font-mono text-slate-400">ADC 2 (0x49 A3)</td>
                                <td class="p-2.5 font-mono text-slate-600">V7 (V27)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-[11px] text-slate-600 space-y-1">
                    <p>💡 <b>Panduan Pemilihan Modul Relay:</b></p>
                    <ul class="list-disc pl-5 space-y-0.5">
                        <li><strong>Jika pakai Modul Relay 1 Channel:</strong> Hubungkan ke <b>Pin 11 (GPIO 17)</b> saja.</li>
                        <li><strong>Jika pakai Modul Relay 2 Channel:</strong> Hubungkan ke <b>Pin 11 (GPIO 17)</b> dan <b>Pin 13 (GPIO 27)</b>.</li>
                        <li><strong>Jika pakai Modul Relay 4 Channel:</strong> Hubungkan ke <b>Pin 11, 13, 15, dan 16 (GPIO 17, 27, 22, 23)</b>.</li>
                        <li><strong>Jika pakai Modul Relay 8 Channel:</strong> Hubungkan ke <b>Pin 11, 13, 15, 16, 18, 22, 29, dan 31</b>.</li>
                    </ul>
                </div>
            </div>

            <!-- SUB-SECTION 3.4: FOTO PROTOTYPE & WIRING FISIK ADAPTOR -->
            <div class="space-y-3 avoid-break">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">
                        3.4 Foto Visual Prototype Alat & Skema Wiring Fisik
                    </h3>
                    <span class="text-[9.5px] font-black uppercase bg-[#8E1616]/10 text-[#8E1616] px-2 py-0.5 rounded font-sans">Hardware Prototype</span>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Berikut adalah dokumentasi foto prototype perangkat keras unit kontroler dan rangkaian adaptor listrik yang telah dirakit dan diuji di laboratorium:
                </p>

                <!-- KOTAK GAMBAR PROTOTYPE -->
                @php
                    $wiringImgSrc = '/images/WIRING_AC.png';
                    if (file_exists(public_path('images/WIRING_AC.png'))) {
                        $wiringImgSrc = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('images/WIRING_AC.png')));
                    } elseif (file_exists(public_path('images/ADAPTOR.png'))) {
                        $wiringImgSrc = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('images/ADAPTOR.png')));
                    }
                @endphp
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm space-y-3.5">
                    <div class="rounded-xl overflow-hidden bg-slate-50 border border-slate-200 p-2 sm:p-4 flex items-center justify-center">
                        <img src="{{ $wiringImgSrc }}" 
                             alt="Foto Prototype Skema Wiring Hardware SIKOMAT AC - PT PINDAD" 
                             class="max-h-[360px] w-auto max-w-full object-contain rounded-lg shadow-xs">
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-[11px] text-slate-600">
                        <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="font-bold text-[#8E1616] block uppercase text-[10px]">1. Modul Power Supply & Adaptor</span>
                            <span class="text-slate-500 text-[10.5px]">Menyuplai daya DC 5V 3A stabil untuk Raspberry Pi 3B+, koil relay, dan sensor ACS712.</span>
                        </div>
                        <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="font-bold text-[#8E1616] block uppercase text-[10px]">2. Rangkaian Sensor ACS712 & Relai</span>
                            <span class="text-slate-500 text-[10.5px]">Sensor arus dipasang seri pada kabel fasa AC 220V, dikendalikan saklar relay COM & NO.</span>
                        </div>
                        <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="font-bold text-[#8E1616] block uppercase text-[10px]">3. Komunikasi I2C Bus & Pin ADDR</span>
                            <span class="text-slate-500 text-[10.5px]">SDA (Pin 3) & SCL (Pin 5) paralel ke ADS1115 (0x48) & DS3231 (0x68). <b>Pin ADDR dikunci ke GND</b>.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- K3 LISTRIK PERINGATAN -->
            <div class="p-4 bg-amber-50 rounded-2xl border border-amber-200 text-xs text-amber-900 space-y-1 avoid-break">
                <div class="flex items-center gap-2 font-black">
                    <span>⚠️</span>
                    <span>STANDAR KESELAMATAN K3 LISTRIK TEGANGAN TINGGI (220V AC):</span>
                </div>
                <p class="leading-relaxed text-[11px] text-amber-800">
                    Pastikan MCB panel dalam kondisi <strong>OFF (Mati Total)</strong> sebelum menyambungkan kabel listrik AC ke terminal COM dan NO modul relay. Gunakan kabel berstandar SNI (minimal NYAF 1.5mm²) dan bungkus seluruh sambungan dengan isolator atau heatshrink tube.
                </p>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- BAB 4: INSTALASI OS, DEPENDENSI & AUTO-START SKRIP -->
        <!-- ========================================================================= -->
        <div id="bab4" class="space-y-6 pt-6 page-break">
            <div class="flex items-center gap-3 border-b border-slate-200 pb-3">
                <span class="w-8 h-8 rounded-xl bg-[#8E1616] text-white flex items-center justify-center font-black text-sm">4</span>
                <h2 class="text-lg sm:text-xl font-black text-[#1D1616] tracking-tight">
                    BAB IV: INSTALASI OS, DEPENDENSI & AUTO-START SKRIP
                </h2>
            </div>

            <div class="space-y-4 text-xs">
                
                <!-- LANGKAH 1: INTERFACE HARDWARE -->
                <div class="p-4 bg-white rounded-2xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-black text-slate-900 text-xs uppercase tracking-wider block">
                            4.1 Pengaturan Interface I2C (Hardware Enable)
                        </span>
                        <span class="text-[9.5px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">Terminal SSH</span>
                    </div>
                    <p class="text-slate-600 leading-relaxed">
                        Interface I2C wajib diaktifkan pada Raspberry Pi agar sistem dapat berkomunikasi dengan sensor ADC ADS1115 (arus ACS712) dan modul jam RTC DS3231:
                    </p>
                    <code class="block font-mono bg-slate-900 text-slate-100 p-2.5 rounded-xl select-all">
                        sudo raspi-config
                    </code>
                    <p class="text-slate-500 text-[11px] leading-relaxed">
                        Pilih menu: <b>3 Interface Options &rarr; I5 I2C &rarr; Pilih &lt;Yes&gt; &rarr; Finish &rarr; Reboot</b>.<br>
                        <em>Verifikasi deteksi hardware:</em> Jalankan <code>sudo i2cdetect -y 1</code>. Alamat <code>0x48</code> (ADS1115) dan <code>0x68</code> (DS3231) akan muncul pada tabel matriks I2C.
                    </p>
                </div>

                <!-- LANGKAH 2: DEPENDENSI PYTHON -->
                <div class="p-4 bg-white rounded-2xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-black text-slate-900 text-xs uppercase tracking-wider block">
                            4.2 Instalasi Library Python IoT (1x Setup pada RPi Baru)
                        </span>
                        <span class="text-[9.5px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">Python 3 Pip</span>
                    </div>
                    <p class="text-slate-600 leading-relaxed">
                        Jalankan 1 baris perintah pip berikut di terminal Raspberry Pi untuk menginstall pustaka protokol MQTT, komunikasi ADC, modul RTC, dan kontrol GPIO:
                    </p>
                    <code class="block font-mono bg-slate-900 text-slate-100 p-2.5 rounded-xl select-all overflow-x-auto leading-relaxed text-[11px]">
                        pip3 install paho-mqtt adafruit-circuitpython-ads1x15 adafruit-circuitpython-ds3231 RPi.GPIO
                    </code>
                </div>

                <!-- LANGKAH 3: PANDUAN FLEKSIBILITAS IP & SKENARIO DEPLOYMENT (DETAIL) -->
                <div class="p-4 bg-amber-50/70 rounded-2xl border border-amber-200/90 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-black text-amber-900 text-xs uppercase tracking-wider block">
                            4.3 Penyesuaian IP Server Host & Skenario Deployment (Penting)
                        </span>
                        <span class="text-[9.5px] font-bold text-amber-900 bg-amber-200/70 px-2 py-0.5 rounded">Fleksibel & Dinamis</span>
                    </div>
                    <p class="text-slate-700 leading-relaxed text-[11.5px]">
                        Pada antarmuka Web Dashboard (Modul <b>Home</b> &rarr; tombol <b>`⚡ Setup Node`</b>), terdapat kolom input <b>`IP Server Host (Komputer Dashboard)`</b>. Kolom ini dirancang agar dapat disesuaikan dengan skenario infrastruktur jaringan PT PINDAD:
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5 text-[11px]">
                        <div class="bg-white p-3 rounded-xl border border-amber-200 space-y-1">
                            <span class="font-black text-[#8E1616] block uppercase text-[10px]">Skenario 1: LAN Lokal / Uji Coba</span>
                            <p class="text-slate-600 leading-tight">
                                Jika dashboard dijalankan pada laptop/PC teknisi, masukkan IP LAN laptop (misal: <code>192.168.196.236</code>).
                            </p>
                        </div>
                        <div class="bg-white p-3 rounded-xl border border-amber-200 space-y-1">
                            <span class="font-black text-[#8E1616] block uppercase text-[10px]">Skenario 2: Server Produksi / VPS</span>
                            <p class="text-slate-600 leading-tight">
                                Saat web sudah dideploy ke server permanen atau domain intranet PINDAD, cukup masukkan domain/IP server (misal: <code>sikomat.pindad.co.id</code> atau <code>10.10.15.20</code>).
                            </p>
                        </div>
                        <div class="bg-white p-3 rounded-xl border border-amber-200 space-y-1">
                            <span class="font-black text-[#8E1616] block uppercase text-[10px]">Skenario 3: Pergantian Router / IP</span>
                            <p class="text-slate-600 leading-tight">
                                Jika IP komputer berganti, <strong>tidak perlu mengedit kode Python manual</strong>. Cukup ketik IP baru di modal web, salin perintah baru, dan jalankan di Raspberry Pi.
                            </p>
                        </div>
                    </div>
                    
                    <p class="text-amber-900 text-[10.5px] font-medium leading-tight">
                        💡 <strong>Otomatisasi Penuh:</strong> Setiap kali nilai IP/Domain pada kolom diubah, perintah <code>curl</code> pada antarmuka web langsung menyesuaikan secara *real-time*.
                    </p>
                </div>

                <!-- LANGKAH 4: PERINTAH ALL-IN-ONE -->
                <div class="p-4 bg-slate-900 text-white rounded-2xl border border-slate-800 space-y-3 shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="font-black text-amber-400 text-xs uppercase tracking-wider block">
                            4.4 Eksekusi Perintah All-In-One (Unduh, Auto-Boot & Run)
                        </span>
                        <span class="text-[9px] font-mono bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded border border-emerald-500/30">1-Klik Eksekusi</span>
                    </div>
                    <p class="text-slate-300 leading-relaxed text-[11.5px]">
                        Salin perintah 1-baris dari modal <b>`⚡ Setup Node`</b> dan jalankan langsung di terminal SSH Raspberry Pi:
                    </p>
                    <code class="block font-mono text-[10px] bg-black/70 text-emerald-400 p-3 rounded-xl select-all break-all leading-relaxed border border-white/10">
                        sudo systemctl stop pindad* *telepon* 2>/dev/null; sudo pkill -9 -f 'server_telepon' 2>/dev/null; sudo pkill -9 -f 'pindad' 2>/dev/null; sudo pkill -9 -f python 2>/dev/null; (crontab -l 2>/dev/null | grep -v -E 'pindad|telepon|python') | crontab -; (sudo crontab -l 2>/dev/null | grep -v -E 'pindad|telepon|python') | sudo crontab - 2>/dev/null; sudo rm -f /etc/systemd/system/*pindad* /etc/systemd/system/*telepon* 2>/dev/null; sudo systemctl daemon-reload 2>/dev/null; rm -f ~/pindad_*.py ~/node_config.json ~/node.log 2>/dev/null; curl -sSL "http://192.168.196.236:8000/scripts/download/device?device_id=RPI3B_SERVER_INTERNET&broker_host={{ $serverLanHost ?? "192.168.196.236" }}" -o ~/pindad_node.py && (crontab -l 2>/dev/null | grep -v 'pindad_node'; echo "@reboot sleep 10 && python3 -u ~/pindad_node.py > ~/node.log 2>&1 &") | crontab - && nohup python3 -u ~/pindad_node.py > ~/node.log 2>&1 &
                    </code>

                    <!-- DEKONSTRUKSI ANATOMI PERINTAH -->
                    <div class="bg-white/5 rounded-xl p-3 border border-white/10 space-y-1.5 text-[10.5px]">
                        <span class="font-black text-amber-300 block uppercase">Anatomi & Alur Kerja Baris Perintah:</span>
                        <ul class="space-y-1 text-slate-300 list-disc list-inside">
                            <li><strong class="text-white">sudo pkill -9 -f python3 & rm:</strong> Membersihkan seluruh proses Python lama dan file script sebelumnya agar tidak terjadi bentrokan (0 ghost process).</li>
                            <li><strong class="text-white">curl -sSL ... -o:</strong> Mengunduh skrip Python siap pakai yang sudah dipersonalisasi sesuai ID perangkat, pin GPIO, dan alamat broker MQTT.</li>
                            <li><strong class="text-white">crontab @reboot sleep 10:</strong> Mendaftarkan otomatisasi pada cron sistem agar skrip selalu otomatis menyala 10 detik setelah Raspberry Pi reboot (anti mati listrik).</li>
                            <li><strong class="text-white">pkill -f pindad_node:</strong> Menghentikan skrip versi sebelumnya secara bersih jika ada pembaruan konfigurasi.</li>
                            <li><strong class="text-white">nohup python3 -u ... &:</strong> Menjalankan proses di latar belakang (*background daemon*) tanpa terputus meskipun jendela terminal SSH ditutup.</li>
                        </ul>
                    </div>
                </div>

                <!-- LANGKAH 5: MONITORING LOG -->
                <div class="p-4 bg-white rounded-2xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-black text-slate-900 text-xs uppercase tracking-wider block">
                            4.5 Pemantauan Log Pengiriman Data Real-Time
                        </span>
                        <span class="text-[9.5px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">Live Verification</span>
                    </div>
                    <p class="text-slate-600 leading-relaxed">
                        Untuk memverifikasi apakah pembacaan arus sensor dan telemetri berhasil terkirim ke dashboard:
                    </p>
                    <div class="flex items-center justify-between bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                        <code class="font-mono font-bold text-slate-800 text-xs">tail -f ~/node.log</code>
                        <span class="text-[10px] text-slate-400">Tekan Ctrl+C untuk keluar</span>
                    </div>
                    <div class="bg-slate-900 rounded-xl p-2.5 text-[10.5px] font-mono text-emerald-400 space-y-0.5 border border-slate-800">
                        <p class="text-slate-400"># Contoh output log berhasil di terminal:</p>
                        <p>[2026-09-07 10:15:02] [INFO] Koneksi MQTT lokal terhubung ke 127.0.0.1:1883</p>
                        <p>[2026-09-07 10:15:17] [TELEMETRY] AC1=5.2100A (1146W) | AC2=0.0000A (0W) | Total=1146W</p>
                        <p>[2026-09-07 10:15:17] [HTTP-REST] Telemetri berhasil tersinkronisasi ke Dashboard (200 OK)</p>
                    </div>
                </div>

                <!-- LANGKAH 6: TROUBLESHOOTING PERINTAH CEPAT -->
                <div class="p-4 bg-white rounded-2xl border border-slate-200 space-y-2">
                    <span class="font-black text-slate-900 text-xs uppercase tracking-wider block">
                        4.6 Perintah Cepat Manajemen Proses Node
                    </span>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-[11px]">
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                            <span class="font-bold text-slate-700 block text-[10px] uppercase">Cek Status Proses</span>
                            <code class="font-mono text-[10px] text-indigo-700 block mt-1">ps aux | grep pindad_node</code>
                        </div>
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                            <span class="font-bold text-slate-700 block text-[10px] uppercase">Hentikan Skrip Manual</span>
                            <code class="font-mono text-[10px] text-rose-700 block mt-1">pkill -f pindad_node</code>
                        </div>
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                            <span class="font-bold text-slate-700 block text-[10px] uppercase">Jalankan Ulang Manual</span>
                            <code class="font-mono text-[10px] text-emerald-700 block mt-1">python3 -u pindad_node_*.py</code>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- BAB 5: PANDUAN BOT TELEGRAM ALARM DARURAT -->
        <!-- ========================================================================= -->
        <div id="bab5" class="space-y-6 pt-6 page-break">
            <div class="flex items-center gap-3 border-b border-slate-200 pb-3">
                <span class="w-8 h-8 rounded-xl bg-[#8E1616] text-white flex items-center justify-center font-black text-sm">5</span>
                <h2 class="text-lg sm:text-xl font-black text-[#1D1616] tracking-tight">
                    BAB V: PANDUAN SETUP NOTIFIKASI BOT TELEGRAM
                </h2>
            </div>

            <div class="space-y-3 text-xs leading-relaxed text-slate-700">
                <p>
                    Sistem SIKOMAT dilengkapi mesin pendeteksi anomali otomatis (*Anomaly Detector Service*). Ketika unit AC dijadwalkan ON namun arus listrik terdeteksi <strong>0.00 Ampere</strong> (kompresor macet, MCB trip, atau AC mati), sistem akan langsung mengirimkan pesan darurat ke grup Telegram teknisi.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5">
                        <span class="font-black text-[#8E1616] text-[11px] uppercase block">1. Buat Bot Telegram</span>
                        <p class="text-slate-600 text-[11px]">Buka Telegram &rarr; cari <b>@BotFather</b> &rarr; kirim <code>/newbot</code> &rarr; salin <b>HTTP API Token</b>.</p>
                    </div>
                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5">
                        <span class="font-black text-[#8E1616] text-[11px] uppercase block">2. Dapatkan Chat ID</span>
                        <p class="text-slate-600 text-[11px]">Kirim pesan ke bot Anda atau masukkan bot ke grup teknisi &rarr; cari ID via <b>@userinfobot</b>.</p>
                    </div>
                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5">
                        <span class="font-black text-[#8E1616] text-[11px] uppercase block">3. Simpan di Modul 4</span>
                        <p class="text-slate-600 text-[11px]">Buka Modul 4 &rarr; paste Token & Chat ID &rarr; klik tombol <b>🧪 Uji Coba Kirim Pesan</b>.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- BAB 6: PENGUJIAN & TROUBLESHOOTING MASALAH -->
        <!-- ========================================================================= -->
        <div id="bab6" class="space-y-6 pt-6 page-break">
            <div class="flex items-center gap-3 border-b border-slate-200 pb-3">
                <span class="w-8 h-8 rounded-xl bg-[#8E1616] text-white flex items-center justify-center font-black text-sm">6</span>
                <h2 class="text-lg sm:text-xl font-black text-[#1D1616] tracking-tight">
                    BAB VI: PENGUJIAN, KALIBRASI & TROUBLESHOOTING
                </h2>
            </div>

            <div class="space-y-3 text-xs">
                <div class="border border-slate-200 rounded-xl p-4 bg-white space-y-1.5">
                    <span class="font-bold text-[#8E1616] block">Q1: Perangkat Node I2C Tidak Terdeteksi (ADS1115 / DS3231)?</span>
                    <p class="text-slate-600 leading-relaxed text-[11.5px]">
                        Jalankan perintah <code>i2cdetect -y 1</code> di terminal. Alamat <b>0x48</b> (ADS1115) dan <b>0x68</b> (DS3231) harus muncul. Jika kosong, periksa koneksi kabel SDA/SCL dan pin 3.3V power supply.
                    </p>
                </div>

                <div class="border border-slate-200 rounded-xl p-4 bg-white space-y-1.5">
                    <span class="font-bold text-[#8E1616] block">Q2: Status di Web Dashboard Tetap "🔴 Offline"?</span>
                    <p class="text-slate-600 leading-relaxed text-[11.5px]">
                        Pastikan Raspberry Pi dan server dashboard terhubung pada jaringan lokal yang sama (Wi-Fi/LAN). Pastikan broker Mosquitto aktif di port 1883 dan IP Address di skrip Python sesuai dengan IP server dashboard.
                    </p>
                </div>

                <div class="border border-slate-200 rounded-xl p-4 bg-white space-y-1.5">
                    <span class="font-bold text-[#8E1616] block">Q3: Nilai Pembacaan Arus (Ampere) Melompat atau Tidak Akurat?</span>
                    <p class="text-slate-600 leading-relaxed text-[11.5px]">
                        Sensor ACS712 mengukur arus AC dengan kalkulasi RMS. Pastikan common ground (GND) antara RPi, sensor ACS712, dan ADC ADS1115 terhubung kuat untuk menghindari fluktuasi noise tegangan referensi.
                    </p>
                </div>
            </div>

            <!-- TANDA TANGAN PENGESAHAN DOKUMEN -->
            <div class="pt-8 border-t-2 border-slate-200 grid grid-cols-2 text-center text-xs avoid-break">
                <div class="space-y-12">
                    <span class="text-slate-500 font-bold block">Disiapkan Oleh (Teknisi IoT):</span>
                    <div>
                        <span class="font-black text-slate-900 block underline">{{ $user->name ?? 'Dicky Akbar Syah Putra' }}</span>
                        <span class="text-[10px] text-slate-500 block">Operator & Administrator Sistem SIKOMAT</span>
                    </div>
                </div>
                <div class="space-y-12">
                    <span class="text-slate-500 font-bold block">Disetujui Oleh (Kepala Divisi):</span>
                    <div>
                        <span class="font-black text-slate-900 block underline">PT PINDAD (Persero)</span>
                        <span class="text-[10px] text-slate-500 block">Divisi Mutu & Teknologi Informasi</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- FOOTER INFO -->
    <div class="max-w-4xl mx-auto text-center text-slate-400 text-[11px] py-6 no-print">
        &copy; {{ date('Y') }} PT PINDAD (Persero). Sistem Kontrol & Monitoring Otomatis AC (SIKOMAT). All rights reserved.
    </div>

</body>
</html>

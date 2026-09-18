<!DOCTYPE html>
@php
    $isSuperAdmin = (session('user_role') === 'admin' || session('user_nip') === 'PINDAD-IOT-2026');
    $isOperator = !$isSuperAdmin;
@endphp
<html lang="id" x-data="{ 
    activeTab: {{ $isOperator ? "'home'" : "localStorage.getItem('pindad_active_tab') || 'home'" }}, 
    modalFabOpen: false 
}" x-init="if ({{ $isOperator ? 'true' : 'false' }}) { activeTab = 'home'; } else { $watch('activeTab', val => localStorage.setItem('pindad_active_tab', val)); }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>PINDAD IoT Engine • Platform Monitoring & Kontrol Perangkat</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
        [x-cloak] { display: none !important; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #EEEEEE;
        }
        ::-webkit-scrollbar-thumb {
            background: #8E1616;
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #D84040;
        }
    </style>
</head>
<body class="bg-[#EEEEEE] text-[#1D1616] min-h-screen antialiased flex flex-col justify-between selection:bg-[#D84040] selection:text-white overflow-x-hidden w-full max-w-full">

    <!-- MAIN RESPONSIVE WRAPPER CONTAINER (Proporsional, Elegan & Nyaman di Mobile, Tablet/iPad, & Desktop) -->
    <div class="w-full max-w-5xl xl:max-w-6xl mx-auto px-3.5 sm:px-6 md:px-8 lg:px-12 pt-4 sm:pt-6 pb-36 sm:pb-44 space-y-6 sm:space-y-8">
        
        <!-- ================= TOP HEADER ================= -->
        <header class="flex items-center justify-between pt-2">
            <div class="flex items-center">
                <img src="{{ asset('SIKOMAT.png') }}" 
                     alt="SIKOMAT - Sistem Kontrol & Monitoring AC IoT PT PINDAD" 
                     class="h-10 sm:h-12 md:h-14 w-auto object-contain select-none">
            </div>

            <!-- Right Profile Avatar with Red Notification Badge -->
            <div @click="activeTab = 'akun'" class="relative cursor-pointer group shrink-0" title="Buka Informasi Sistem & Akun">
                <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-gradient-to-tr from-[#1D1616] via-[#8E1616] to-[#D84040] text-white flex items-center justify-center font-black text-lg sm:text-xl border-2 border-white shadow-md transition-transform group-hover:scale-105">
                    ⚙️
                </div>
                <span class="absolute -bottom-0.5 -right-0.5 w-4 h-4 sm:w-4.5 sm:h-4.5 bg-[#D84040] border-2 border-[#EEEEEE] rounded-full flex items-center justify-center">
                    <span class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-white rounded-full animate-ping"></span>
                </span>
            </div>
        </header>

        <!-- ================= ALERTS NOTIFICATION ================= -->
        @if(session('success'))
            <div class="bg-white rounded-[24px] border border-[#8E1616]/30 text-[#1D1616] shadow-[0_20px_50px_-12px_rgba(29,22,22,0.08)] px-6 py-4 flex items-center justify-between" role="alert">
                <span class="text-xs sm:text-sm font-black flex items-center space-x-2.5">
                    <span class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center text-xs font-bold">✓</span>
                    <span>{{ session('success') }}</span>
                </span>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-[#D84040] font-bold text-2xl cursor-pointer">&times;</button>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-white rounded-[24px] border border-rose-300 text-rose-800 shadow-[0_20px_50px_-12px_rgba(216,64,64,0.08)] px-6 py-4 flex items-center justify-between" role="alert">
                <span class="text-xs sm:text-sm font-black flex items-center space-x-2.5">
                    <span class="w-7 h-7 rounded-full bg-rose-100 text-rose-800 flex items-center justify-center text-xs font-bold">!</span>
                    <span>{{ session('error') }}</span>
                </span>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-[#D84040] font-bold text-2xl cursor-pointer">&times;</button>
            </div>
        @endif

        <!-- ================= HORIZONTAL SNAP SCROLL SELECTOR (4 CLEAN MODULES) ================= -->
        <div class="flex items-center space-x-2.5 sm:space-x-3.5 overflow-x-auto pb-2 pt-1 no-scrollbar -mx-1 px-1 sm:mx-0 sm:px-0">
            
            <!-- Category 1: Home (Universal IoT Fleet Overview & Drilldown) -->
            <button 
                @click="activeTab = 'home'; window.dispatchEvent(new CustomEvent('reset-home-view'))"
                type="button"
                :class="activeTab === 'home' 
                    ? 'w-48 bg-[#8E1616] text-white shadow-lg shadow-[#8E1616]/25' 
                    : 'w-14 bg-white text-[#1D1616]/60 border border-[#8E1616]/20 hover:border-[#8E1616]'"
                class="h-14 rounded-[22px] p-2 flex items-center space-x-3 shrink-0 transition-all duration-300 cursor-pointer overflow-hidden">
                <div class="w-10 h-10 rounded-full bg-[#D84040] text-white flex items-center justify-center shrink-0 shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </div>
                <div class="text-left truncate pr-2" x-show="activeTab === 'home'">
                    <span class="text-[9px] font-bold uppercase tracking-widest text-[#EEEEEE]/80 block">MODUL 1</span>
                    <span class="text-xs font-black text-white leading-none block">Home Fleet</span>
                </div>
            </button>

            @if($isSuperAdmin)
            <!-- Category 2: Developer Zone (Templates & Datastreams Console) -->
            <button 
                @click="activeTab = 'devzone'"
                type="button"
                :class="activeTab === 'devzone' 
                    ? 'w-48 bg-[#8E1616] text-white shadow-lg shadow-[#8E1616]/25' 
                    : 'w-14 bg-white text-[#1D1616]/60 border border-[#8E1616]/20 hover:border-[#8E1616]'"
                class="h-14 rounded-[22px] p-2 flex items-center space-x-3 shrink-0 transition-all duration-300 cursor-pointer overflow-hidden">
                <div class="w-10 h-10 rounded-full bg-[#D84040] text-white flex items-center justify-center shrink-0 shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                </div>
                <div class="text-left truncate pr-2" x-show="activeTab === 'devzone'">
                    <span class="text-[9px] font-bold uppercase tracking-widest text-[#EEEEEE]/80 block">MODUL 2</span>
                    <span class="text-xs font-black text-white leading-none block">DevZone</span>
                </div>
            </button>

            <!-- Category 3: Log Telemetri & Sensor Audit -->
            <button 
                @click="activeTab = 'book'"
                type="button"
                :class="activeTab === 'book' 
                    ? 'w-48 bg-[#8E1616] text-white shadow-lg shadow-[#8E1616]/25' 
                    : 'w-14 bg-white text-[#1D1616]/60 border border-[#8E1616]/20 hover:border-[#8E1616]'"
                class="h-14 rounded-[22px] p-2 flex items-center space-x-3 shrink-0 transition-all duration-300 cursor-pointer overflow-hidden">
                <div class="w-10 h-10 rounded-full bg-[#D84040] text-white flex items-center justify-center shrink-0 shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
                <div class="text-left truncate pr-2" x-show="activeTab === 'book'">
                    <span class="text-[9px] font-bold uppercase tracking-widest text-[#EEEEEE]/80 block">MODUL 3</span>
                    <span class="text-xs font-black text-white leading-none block">Log Telemetri</span>
                </div>
            </button>

            <!-- Category 4: Akun & Informasi Sistem -->
            <button 
                @click="activeTab = 'akun'"
                type="button"
                :class="activeTab === 'akun' 
                    ? 'w-48 bg-[#8E1616] text-white shadow-lg shadow-[#8E1616]/25' 
                    : 'w-14 bg-white text-[#1D1616]/60 border border-[#8E1616]/20 hover:border-[#8E1616]'"
                class="h-14 rounded-[22px] p-2 flex items-center space-x-3 shrink-0 transition-all duration-300 cursor-pointer overflow-hidden">
                <div class="w-10 h-10 rounded-full bg-[#D84040] text-white flex items-center justify-center shrink-0 shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div class="text-left truncate pr-2" x-show="activeTab === 'akun'">
                    <span class="text-[9px] font-bold uppercase tracking-widest text-[#EEEEEE]/80 block">MODUL 4</span>
                    <span class="text-xs font-black text-white leading-none block">Akun & Sistem</span>
                </div>
            </button>
            @else
            <!-- Category 2 for Operator: Log Telemetri (Scoped) -->
            <button 
                @click="activeTab = 'book'"
                type="button"
                :class="activeTab === 'book' 
                    ? 'w-48 bg-[#8E1616] text-white shadow-lg shadow-[#8E1616]/25' 
                    : 'w-14 bg-white text-[#1D1616]/60 border border-[#8E1616]/20 hover:border-[#8E1616]'"
                class="h-14 rounded-[22px] p-2 flex items-center space-x-3 shrink-0 transition-all duration-300 cursor-pointer overflow-hidden">
                <div class="w-10 h-10 rounded-full bg-[#D84040] text-white flex items-center justify-center shrink-0 shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
                <div class="text-left truncate pr-2" x-show="activeTab === 'book'">
                    <span class="text-[9px] font-bold uppercase tracking-widest text-[#EEEEEE]/80 block">MODUL 2</span>
                    <span class="text-xs font-black text-white leading-none block">Log AC</span>
                </div>
            </button>

            <!-- Category 3 for Operator: Akun & Informasi Profil -->
            <button 
                @click="activeTab = 'akun'"
                type="button"
                :class="activeTab === 'akun' 
                    ? 'w-48 bg-[#8E1616] text-white shadow-lg shadow-[#8E1616]/25' 
                    : 'w-14 bg-white text-[#1D1616]/60 border border-[#8E1616]/20 hover:border-[#8E1616]'"
                class="h-14 rounded-[22px] p-2 flex items-center space-x-3 shrink-0 transition-all duration-300 cursor-pointer overflow-hidden">
                <div class="w-10 h-10 rounded-full bg-[#D84040] text-white flex items-center justify-center shrink-0 shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div class="text-left truncate pr-2" x-show="activeTab === 'akun'">
                    <span class="text-[9px] font-bold uppercase tracking-widest text-[#EEEEEE]/80 block">MODUL 3</span>
                    <span class="text-xs font-black text-white leading-none block">Akun & Profil</span>
                </div>
            </button>
            @endif

        </div>

        <!-- ================= MAIN DYNAMIC CONTENT TABS ================= -->
        <main class="space-y-8">
            
            <!-- TAB 1: HOME (UNIVERSAL IOT FLEET OVERVIEW & DEVICE DRILLDOWN) -->
            <div x-show="activeTab === 'home'" x-cloak>
                @include('partials.section-home')
            </div>

            @if($isSuperAdmin)
            <!-- TAB 2: DEVELOPER ZONE (TEMPLATES & DATASTREAMS CONSOLE) -->
            <div x-show="activeTab === 'devzone'" x-cloak>
                @include('partials.section-developer-zone')
            </div>
            @endif

            <!-- TAB 3: LOG TELEMETRI (AUDIT SENSOR DENGAN FILTER & DOWNLOAD CSV - SCOPED FOR OPERATOR) -->
            <div x-show="activeTab === 'book'" x-cloak>
                @include('partials.section-riwayat')
            </div>

            <!-- TAB 4: AKUN & SISTEM (PENGELOLAAN AKUN OPERATOR & INFORMASI WEB) -->
            <div x-show="activeTab === 'akun'" x-cloak>
                @include('partials.section-akun')
            </div>

        </main>

    </div>

    @if($isSuperAdmin)
    <!-- ================= FLOATING CENTER ACTION BUTTON MODAL (SUPER ADMIN ONLY) ================= -->
    <div x-show="modalFabOpen" x-cloak 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
         @keydown.escape.window="modalFabOpen = false">
        
        <div @click.away="modalFabOpen = false" 
             class="bg-white rounded-[40px] p-8 max-w-md w-full shadow-2xl border border-[#8E1616]/30 space-y-6 relative transform transition-all">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-[20px] bg-[#D84040] text-white flex items-center justify-center font-black text-xl shadow-md">
                        ⚡
                    </div>
                    <div>
                        <h4 class="text-lg font-black text-[#1D1616]">Aksi Pintar & Kontrol Cepat</h4>
                        <p class="text-xs text-slate-500">Pusat Komando & Master Kontrol Armada</p>
                    </div>
                </div>
                <button @click="modalFabOpen = false" class="text-slate-400 hover:text-[#8E1616] text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <!-- Master Control Action Buttons -->
            <div class="space-y-4">
                <p class="text-xs text-slate-500 leading-relaxed">
                    Pilih aksi di bawah ini untuk mengontrol serentak seluruh unit perangkat AC di semua ruangan secara bersamaan:
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <!-- Nyalakan Semua Device -->
                    <button type="button" 
                            @click="if (confirm('Nyalakan SELURUH unit perangkat di semua ruangan?')) { executeMasterControl('ON'); modalFabOpen = false; }"
                            class="w-full py-4 px-4 rounded-2xl bg-gradient-to-br from-emerald-50 to-emerald-100/80 hover:from-emerald-100 hover:to-emerald-200/90 text-emerald-900 border-2 border-emerald-300 font-black text-xs uppercase tracking-wider flex flex-col items-center justify-center gap-2.5 cursor-pointer active:scale-95 shadow-sm hover:shadow-md transition group">
                        <div class="w-11 h-11 rounded-xl bg-emerald-500 text-white flex items-center justify-center text-xl shadow-xs group-hover:scale-110 transition-transform">
                            ⚡
                        </div>
                        <div class="text-center">
                            <span class="block font-black text-xs text-emerald-950">Nyalakan Semua Device</span>
                            <span class="text-[10.5px] text-emerald-700 font-semibold normal-case">Semua AC Langsung ON</span>
                        </div>
                    </button>

                    <!-- Matikan Semua Device -->
                    <button type="button" 
                            @click="if (confirm('Matikan SELURUH unit perangkat di semua ruangan?')) { executeMasterControl('OFF'); modalFabOpen = false; }"
                            class="w-full py-4 px-4 rounded-2xl bg-gradient-to-br from-rose-50 to-rose-100/80 hover:from-rose-100 hover:to-rose-200/90 text-rose-900 border-2 border-rose-300 font-black text-xs uppercase tracking-wider flex flex-col items-center justify-center gap-2.5 cursor-pointer active:scale-95 shadow-sm hover:shadow-md transition group">
                        <div class="w-11 h-11 rounded-xl bg-rose-600 text-white flex items-center justify-center text-xl shadow-xs group-hover:scale-110 transition-transform">
                            ⭕
                        </div>
                        <div class="text-center">
                            <span class="block font-black text-xs text-rose-950">Matikan Semua Device</span>
                            <span class="text-[10.5px] text-rose-700 font-semibold normal-case">Semua AC Langsung OFF</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- ================= FLOATING NAVIGATION BAR ================= -->
    @include('partials.floating-nav')

    <!-- ================= CHART & AJAX REAL-TIME POLLING SCRIPT ================= -->
    <script>
        let currentChartInstance = null;
        const currentDeviceId = "{{ $selectedDeviceId }}";

        function initChart() {
            const ctx = document.getElementById('currentChart');
            if (!ctx) return;

            const labels = [
                @foreach($recentLogsAc1->reverse() as $log)
                    "{{ \Illuminate\Support\Carbon::parse($log->recorded_at)->setTimezone('Asia/Jakarta')->format('H:i:s') }}",
                @endforeach
            ];

            const dataAc1 = [
                @foreach($recentLogsAc1->reverse() as $log)
                    {{ (float)$log->current_ampere }},
                @endforeach
            ];

            const dataAc2 = [
                @foreach($recentLogsAc2->reverse() as $log)
                    {{ (float)$log->current_ampere }},
                @endforeach
            ];

            currentChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Panasonic 1 (A)',
                            data: dataAc1,
                            borderColor: '#D84040',
                            backgroundColor: 'rgba(216, 64, 64, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 3,
                        },
                        {
                            label: 'Panasonic 2 (A)',
                            data: dataAc2,
                            borderColor: '#8E1616',
                            backgroundColor: 'rgba(142, 22, 22, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 3,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMax: 10,
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }

        let isFetchingTelemetry = false;

        async function executeMasterControl(command) {
            try {
                const res = await fetch('{{ route('devices.masterControl') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ command: command })
                });
                const json = await res.json();
                if (json.success) {
                    fetchRealTimeTelemetry();
                }
            } catch (e) {
                console.error('Master control error:', e);
            }
        }

        async function fetchRealTimeTelemetry() {
            if (isFetchingTelemetry) return;
            isFetchingTelemetry = true;
            try {
                const res = await fetch(`/api/logs?device_id=${currentDeviceId}`);
                if (!res.ok) return;
                const data = await res.json();

                if (data.status === 'success') {
                    // Dispatch Custom Event for Alpine.js reactive components across all active computers
                    window.dispatchEvent(new CustomEvent('telemetry-updated', { detail: data }));

                    // Update Node Online Status Pill dynamically
                    const nodeStatusPill = document.getElementById('node-online-status-pill');
                    if (nodeStatusPill) {
                        if (data.is_live) {
                            nodeStatusPill.innerHTML = `
                                <span class="text-2xl animate-pulse">🟢</span>
                                <div>
                                    <span class="text-sm font-black text-white block">Node Online (Live Telemetri)</span>
                                    <span class="text-xs text-[#EEEEEE]/70 font-semibold">Sinkronisasi Real-Time</span>
                                </div>
                            `;
                        } else {
                            nodeStatusPill.innerHTML = `
                                <span class="text-2xl">⚪</span>
                                <div>
                                    <span class="text-sm font-black text-white block">Node Standby (Menunggu Sinyal)</span>
                                    <span class="text-xs text-[#EEEEEE]/70 font-semibold">Perangkat Belum Terhubung</span>
                                </div>
                            `;
                        }
                    }

                    // Update AC Units dynamically
                    if (data.units) {
                        Object.keys(data.units).forEach(uNum => {
                            const u = data.units[uNum];
                            const valAc = document.getElementById(`val-current-ac${uNum}`);
                            if (valAc) valAc.innerText = `${u.current.toFixed(4)} A`;
                            const wattAc = document.getElementById(`val-watt-ac${uNum}`);
                            if (wattAc) wattAc.innerText = `≈ ${u.watt} Watt`;
                            const badgeAc = document.getElementById(`badge-status-ac${uNum}`);
                            if (badgeAc) {
                                badgeAc.innerText = u.status === 'ON' ? 'ONLINE' : 'STANDBY';
                                badgeAc.className = `px-3.5 py-1 rounded-full text-xs font-black uppercase tracking-wider ${u.status === 'ON' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'}`;
                            }
                            const shiftAc = document.getElementById(`shift-text-ac${uNum}`);
                            if (shiftAc && u.shift) {
                                shiftAc.innerText = u.shift;
                                shiftAc.className = `text-xs ${u.shift === 'Belum Ada Jadwal' ? 'font-semibold text-slate-400 italic' : 'font-extrabold text-[#8E1616]'} mt-1.5 block leading-snug`;
                            }
                        });
                    }

                    // Update Summary
                    const totalCur = document.getElementById('stat-total-current');
                    if (totalCur) totalCur.innerText = `${data.summary.total_current.toFixed(4)} A`;
                    const totalWatt = document.getElementById('stat-total-watt');
                    if (totalWatt) totalWatt.innerText = `≈ ${data.summary.total_watt} Watt`;

                    // Update Chart
                    if (currentChartInstance && data.charts) {
                        currentChartInstance.data.labels = data.charts.labels;
                        currentChartInstance.data.datasets[0].data = data.charts.ac1;
                        currentChartInstance.data.datasets[1].data = data.charts.ac2;
                        currentChartInstance.update('none');
                    }
                }
            } catch (err) {
                console.error("Telemetry fetch error:", err);
            } finally {
                isFetchingTelemetry = false;
            }
        }

        // =========================================================================
        // REALTIME WEBSOCKET CLIENT (Push-Based Ultra-Low Latency < 5ms)
        // =========================================================================
        let wsClient = null;
        let wsConnected = false;

        function initWebSocketClient() {
            const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            const host = window.location.hostname || '127.0.0.1';
            const wsUrl = `${protocol}//${host}:8080`;

            try {
                wsClient = new WebSocket(wsUrl);

                wsClient.onopen = () => {
                    wsConnected = true;
                    console.log('⚡ [SIKOMAT AC] Realtime WebSocket Connected:', wsUrl);
                    updateWsBadge(true);
                };

                wsClient.onmessage = (event) => {
                    try {
                        const payload = JSON.parse(event.data);
                        // Trigger immediate live refresh (< 5ms response)
                        if (payload.type === 'telemetry_updated' || payload.type === 'ac_control_event' || payload.type === 'mqtt_event') {
                            fetchRealTimeTelemetry();
                        }
                    } catch (e) {}
                };

                wsClient.onclose = () => {
                    wsConnected = false;
                    updateWsBadge(false);
                    // Reconnect after 3 seconds
                    setTimeout(initWebSocketClient, 3000);
                };

                wsClient.onerror = () => {
                    wsConnected = false;
                    updateWsBadge(false);
                    wsClient.close();
                };
            } catch (err) {
                wsConnected = false;
                updateWsBadge(false);
                setTimeout(initWebSocketClient, 3000);
            }
        }

        function updateWsBadge(isConnected) {
            const badge = document.getElementById('ws-realtime-badge');
            if (!badge) return;
            if (isConnected) {
                badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-400/40 text-emerald-300 text-[10px] font-black uppercase tracking-wider transition-all duration-300 shadow-xs';
                badge.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span><span>⚡ WebSocket Live (&lt; 5ms)</span>';
            } else {
                badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/20 border border-amber-400/40 text-amber-300 text-[10px] font-black uppercase tracking-wider transition-all duration-300 shadow-xs';
                badge.innerHTML = '<span class="w-2 h-2 rounded-full bg-amber-400"></span><span>🔄 Polling Fallback</span>';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            initChart();
            initWebSocketClient();
            // Background Fallback Heartbeat (set to 8 seconds instead of 1.5s for optimal efficiency)
            setInterval(fetchRealTimeTelemetry, 8000);
        });
    </script>
</body>
</html>

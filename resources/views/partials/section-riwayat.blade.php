@php
    $isSuperAdmin = (session('user_role') === 'admin' || session('user_nip') === 'PINDAD-IOT-2026');
    $isOperator = !$isSuperAdmin;
@endphp
<!-- ================= MODUL 3: LOG TELEMETRI & AUDIT SENSOR (DENGAN FILTER PERANGKAT & EXPORT CSV) ================= -->
<div class="space-y-6 pb-20" x-data="{ 
    selectedLogDevice: '{{ $filterDevice ?? 'all' }}',
    openRetention: false,
    logTab: 'all',
    modalConfirmClearLogs: false,
    retentionDays: 3,
    loadingPrune: false,
    stats: {
        total_raw_logs: {{ \App\Models\AcLog::count() }},
        total_hourly_summaries: {{ \App\Models\AcHourlySummary::count() }},
        oldest_log_date: '{{ \App\Models\AcLog::orderBy('recorded_at', 'asc')->first()?->recorded_at?->format('d M Y') ?? '-' }}',
        newest_log_date: '{{ \App\Models\AcLog::orderBy('recorded_at', 'desc')->first()?->recorded_at?->format('d M Y') ?? '-' }}',
        total_storage_est_mb: {{ round((\App\Models\AcLog::count() * 250 + \App\Models\AcHourlySummary::count() * 150) / 1024 / 1024, 2) }}
    },
    async runPrune() {
        if (!confirm('Jalankan Auto-Pruning & Agregasi? Data log mentah yang berumur lebih dari ' + this.retentionDays + ' hari akan diringkas ke rata-rata per jam dan log mentah lama akan dihapus untuk membebaskan ruang MongoDB.')) return;
        this.loadingPrune = true;
        try {
            const res = await fetch('{{ route('logs.prune') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ days: this.retentionDays, device_id: this.selectedLogDevice })
            });
            const data = await res.json();
            alert(data.message || 'Pruning selesai.');
            this.fetchStats();
        } catch(e) {
            alert('Gagal mengeksekusi pruning: ' + e.message);
        } finally {
            this.loadingPrune = false;
        }
    },
    async fetchStats() {
        try {
            const res = await fetch('{{ route('logs.stats') }}');
            const data = await res.json();
            if (data.success && data.stats) {
                this.stats = data.stats;
            }
        } catch(e) {}
    }
}" x-init="fetchStats()">
    
    <!-- 1. PAGE HEADER & ACTIONS (DOWNLOAD CSV & CLEAR LOGS) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[#8E1616]/20 pb-4">
        <div>
            <span class="text-[11px] font-extrabold uppercase tracking-widest text-[#8E1616] flex items-center gap-1.5">
                <span>📊</span>
                <span>PUSAT AUDIT DATA & TELEMETRI</span>
            </span>
            <h2 class="text-2xl sm:text-3xl font-black text-[#1D1616] tracking-tight mt-0.5">
                Riwayat & Log Telemetri
            </h2>
            <p class="text-xs font-semibold text-slate-500 mt-1">
                Data beban arus sensor ACS712, status relay, dan audit operasional per perangkat.
            </p>
        </div>

        <!-- ACTION BUTTONS: DOWNLOAD CSV & CLEAR LOGS -->
        <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto shrink-0">
            <!-- DOWNLOAD CSV BUTTON (FLEKSIBEL PER DEVICE) -->
            <a :href="'{{ route('logs.export') }}?device_id=' + selectedLogDevice" 
               class="inline-flex items-center justify-center space-x-2 bg-[#D84040] hover:bg-[#8E1616] text-white text-xs font-black uppercase tracking-wider py-3.5 px-5 rounded-[22px] shadow-md shadow-[#D84040]/25 transition flex-1 sm:flex-initial shrink-0 cursor-pointer active:scale-95 text-center">
                <span>📥</span>
                <span>Unduh CSV</span>
            </a>

            @if($isSuperAdmin)
            <!-- CLEAR LOGS BUTTON (SUPER ADMIN ONLY) -->
            <button @click="modalConfirmClearLogs = true"
                    type="button"
                    class="inline-flex items-center justify-center space-x-2 bg-rose-50 hover:bg-rose-100 text-rose-700 hover:text-rose-900 border border-rose-200 text-xs font-black uppercase tracking-wider py-3.5 px-5 rounded-[22px] transition flex-1 sm:flex-initial shrink-0 cursor-pointer active:scale-95 text-center shadow-2xs">
                <span>🗑️</span>
                <span>Bersihkan Log</span>
            </button>
            @endif
        </div>
    </div>

    
@if($isSuperAdmin)
    <!-- ================= ENTERPRISE DATA RETENTION & HOURLY AGGREGATION ACCORDION ================= -->
    <div class="bg-white rounded-[32px] border border-[#8E1616]/20 shadow-xs overflow-hidden transition-all duration-300">
        <button @click="openRetention = !openRetention" 
                type="button" 
                class="w-full p-4 sm:p-6 text-left flex items-center justify-between hover:bg-slate-50 transition cursor-pointer">
            <div class="flex items-center space-x-3 sm:space-x-4 min-w-0">
                <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-rose-100 text-[#8E1616] flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                    🧹
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm sm:text-base font-black text-[#1D1616] leading-tight truncate">Retensi Data & Pruning Log</h3>
                        <span class="bg-emerald-600 text-white text-[9px] font-black uppercase px-2 py-0.5 rounded-full whitespace-nowrap shrink-0 tracking-wider">Otomatis</span>
                    </div>
                    <p class="text-[11px] sm:text-xs font-semibold text-slate-500 mt-0.5 truncate sm:whitespace-normal">
                        Ringkasan per jam & pembersihan log mentah database
                    </p>
                </div>
            </div>
            <div class="w-8 h-8 rounded-full bg-[#EEEEEE] flex items-center justify-center text-slate-600 font-bold text-sm transition-transform duration-300 shrink-0 ml-2"
                 :class="openRetention ? 'rotate-90 bg-[#8E1616] text-white' : ''">
                ➔
            </div>
        </button>

        <!-- ACCORDION CONTENT: STATS & CONTROLS -->
        <div x-show="openRetention" 
             x-cloak 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="px-4 sm:px-6 pb-6 pt-4 border-t border-[#8E1616]/10 space-y-4 bg-slate-50/70">
            
            <!-- 4 METRIC STATS TILES -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white rounded-2xl p-3.5 border border-slate-200/80 shadow-2xs">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Total Log Mentah (15s)</span>
                    <span class="text-base sm:text-lg font-black text-[#1D1616] font-mono mt-0.5 block" x-text="Number(stats.total_raw_logs || 0).toLocaleString('id-ID')">
                        {{ number_format(\App\Models\AcLog::count(), 0, ',', '.') }}
                    </span>
                    <span class="text-[10px] font-semibold text-slate-400">baris telemetri</span>
                </div>

                <div class="bg-white rounded-2xl p-3.5 border border-slate-200/80 shadow-2xs">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Ringkasan Per Jam</span>
                    <span class="text-base sm:text-lg font-black text-emerald-600 font-mono mt-0.5 block" x-text="Number(stats.total_hourly_summaries || 0).toLocaleString('id-ID')">
                        {{ number_format(\App\Models\AcHourlySummary::count(), 0, ',', '.') }}
                    </span>
                    <span class="text-[10px] font-semibold text-slate-400">hourly summary buckets</span>
                </div>

                <div class="bg-white rounded-2xl p-3.5 border border-slate-200/80 shadow-2xs">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Rentang Log Mentah</span>
                    <span class="text-xs font-black text-[#1D1616] font-mono mt-1 block truncate" x-text="stats.oldest_log_date || '-'">
                        -
                    </span>
                    <span class="text-[10px] font-semibold text-slate-400">data paling awal</span>
                </div>

                <div class="bg-white rounded-2xl p-3.5 border border-slate-200/80 shadow-2xs">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Estimasi Storage</span>
                    <span class="text-base sm:text-lg font-black text-[#8E1616] font-mono mt-0.5 block">
                        <span x-text="stats.total_storage_est_mb || '0.00'">0.00</span> <span class="text-xs">MB</span>
                    </span>
                    <span class="text-[10px] font-semibold text-slate-400">MongoDB footprint</span>
                </div>
            </div>

            <!-- ACTION BAR -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-1">
                <div class="flex items-center gap-2.5 w-full sm:w-auto">
                    <label class="text-[11px] font-bold text-slate-600 shrink-0">Batas Retensi:</label>
                    <select x-model="retentionDays" 
                            class="bg-white border border-slate-200 text-xs font-bold text-[#1D1616] rounded-xl px-3 py-2 focus:ring-2 focus:ring-[#8E1616] focus:outline-none cursor-pointer">
                        <option value="3" selected>3 Hari (Rekomendasi Edge IoT - Standar PINDAD)</option>
                        <option value="7">7 Hari (1 Minggu)</option>
                        <option value="14">14 Hari (2 Minggu)</option>
                        <option value="30">30 Hari (1 Bulan - Rekomendasi)</option>
                        <option value="60">60 Hari (2 Bulan)</option>
                        <option value="90">90 Hari (3 Bulan)</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <button @click="fetchStats()"
                            type="button"
                            class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-bold text-xs uppercase tracking-wider cursor-pointer transition shadow-2xs">
                        🔄 Refresh
                    </button>

                    <button @click="runPrune()"
                            type="button"
                            :disabled="loadingPrune"
                            class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-black text-xs uppercase tracking-wider shadow-md shadow-emerald-600/20 cursor-pointer active:scale-95 transition flex items-center gap-2">
                        <span x-show="!loadingPrune">⚡</span>
                        <span x-show="loadingPrune" class="inline-block animate-spin">⏳</span>
                        <span x-text="loadingPrune ? 'Memproses...' : 'Jalankan Auto-Prune & Ringkas'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

@endif

    <!-- 2. FILTER BAR (DUAL CUSTOM DROPDOWNS ALA DEVZONE V-PIN) -->
    <div class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-6 shadow-sm border border-[#8E1616]/20 grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
        
        <!-- DROPDOWN 1: PILIH PERANGKAT -->
        <div x-data="{
            openDevDropdown: false,
            searchDev: '',
            devices: [
                { id: 'all', name: 'Seluruh Armada (All Devices)', code: 'ALL', icon: '🌐' },
                @foreach($devices as $d)
                { 
                    id: '{{ $d->device_id }}', 
                    name: '{{ addslashes($d->name) }}', 
                    code: '{{ $d->device_id }}', 
                    icon: '{{ addslashes($d->icon ?? '⚡') }}' 
                },
                @endforeach
            ],
            get currentDev() {
                return this.devices.find(d => d.id === selectedLogDevice) || this.devices[0];
            },
            get filteredDevs() {
                if (!this.searchDev) return this.devices;
                const q = this.searchDev.toLowerCase();
                return this.devices.filter(d => d.name.toLowerCase().includes(q) || d.code.toLowerCase().includes(q));
            },
            selectDevice(devId) {
                selectedLogDevice = devId;
                this.openDevDropdown = false;
                window.location.href = '/?filter_device=' + devId;
            }
        }" class="relative">
            <label class="block text-[11px] font-black uppercase text-slate-500 tracking-wider mb-1.5 flex items-center gap-1.5">
                <span>📱</span>
                <span>PILIH PERANGKAT</span>
            </label>

            <!-- Trigger Button -->
            <div @click="openDevDropdown = !openDevDropdown" 
                 @click.away="openDevDropdown = false"
                 class="w-full px-4 py-3 rounded-2xl border border-slate-200 text-xs sm:text-sm font-bold text-[#1D1616] bg-slate-50/70 hover:bg-white focus-within:ring-2 focus-within:ring-[#D84040] flex items-center justify-between cursor-pointer shadow-2xs hover:border-slate-300 transition">
                <div class="flex items-center gap-2.5 truncate">
                    <span class="text-base shrink-0" x-text="currentDev.icon"></span>
                    <span class="truncate font-black" x-text="currentDev.name"></span>
                    <span class="text-[10px] font-mono font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded shrink-0 hidden sm:inline" x-text="currentDev.code"></span>
                </div>
                <svg class="w-4 h-4 text-slate-400 transition-transform duration-200 shrink-0 ml-2" :class="openDevDropdown ? 'rotate-180 text-[#D84040]' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>

            <!-- Dropdown Panel -->
            <div x-show="openDevDropdown" 
                 x-cloak
                 x-transition:enter="transition ease-out duration-150 transform opacity-0 scale-95"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="absolute left-0 right-0 z-50 mt-1.5 bg-white rounded-2xl shadow-2xl border border-slate-200 p-2 space-y-1.5 max-h-60 overflow-y-auto">
                
                <!-- Search input -->
                <div class="px-1 pt-1 pb-1">
                    <input type="text" 
                           x-model="searchDev" 
                           @click.stop
                           placeholder="🔍 Cari perangkat / ID..." 
                           class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-[#D84040] outline-none">
                </div>

                <!-- Device List -->
                <div class="divide-y divide-slate-50">
                    <template x-for="dev in filteredDevs" :key="dev.id">
                        <div @click="selectDevice(dev.id)" 
                             :class="selectedLogDevice === dev.id ? 'bg-[#D84040] text-white font-black' : 'text-slate-700 hover:bg-slate-50 font-bold'"
                             class="px-3.5 py-2.5 rounded-xl text-xs cursor-pointer flex items-center justify-between transition">
                            <div class="flex items-center gap-2 truncate">
                                <span x-text="dev.icon"></span>
                                <span class="truncate" x-text="dev.name"></span>
                                <span class="text-[10px] font-mono opacity-60" x-text="'(' + dev.code + ')'"></span>
                            </div>
                            <span x-show="selectedLogDevice === dev.id" class="text-[10px] font-black shrink-0">✓</span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- DROPDOWN 2: FILTER UNIT AC -->
        <div x-data="{
            openUnitDropdown: false,
            units: [
                { id: 'all', label: 'Semua Unit AC', count: {{ count($recentLogsAll) }}, badge: 'ALL', color: 'bg-slate-900 text-white' },
                @for($u = 1; $u <= ($logNumAc ?? 2); $u++)
                @php
                    $uCount = count($recentLogsByUnit[$u] ?? []);
                    $uLabel = $unitLogNames[$u] ?? ("AC {$u}");
                @endphp
                { 
                    id: 'ac{{ $u }}', 
                    label: '{{ addslashes($uLabel) }}', 
                    count: {{ $uCount }}, 
                    badge: 'AC {{ $u }}', 
                    color: '{{ match($u) { 1 => 'bg-rose-50 text-[#D84040] border border-rose-200', 2 => 'bg-red-50 text-[#8E1616] border border-red-200', 3 => 'bg-amber-50 text-amber-800 border border-amber-200', 4 => 'bg-emerald-50 text-emerald-800 border border-emerald-200', 5 => 'bg-cyan-50 text-cyan-800 border border-cyan-200', default => 'bg-purple-50 text-purple-800 border border-purple-200' } }}'
                },
                @endfor
            ],
            get currentUnit() {
                return this.units.find(u => u.id === logTab) || this.units[0];
            },
            selectUnit(unitId) {
                logTab = unitId;
                this.openUnitDropdown = false;
            }
        }" class="relative">
            <label class="block text-[11px] font-black uppercase text-slate-500 tracking-wider mb-1.5 flex items-center gap-1.5">
                <span>❄️</span>
                <span>FILTER UNIT AC</span>
            </label>

            <!-- Trigger Button -->
            <div @click="openUnitDropdown = !openUnitDropdown" 
                 @click.away="openUnitDropdown = false"
                 class="w-full px-4 py-3 rounded-2xl border border-slate-200 text-xs sm:text-sm font-bold text-[#1D1616] bg-slate-50/70 hover:bg-white focus-within:ring-2 focus-within:ring-[#8E1616] flex items-center justify-between cursor-pointer shadow-2xs hover:border-slate-300 transition">
                <div class="flex items-center gap-2.5 truncate">
                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase shrink-0" :class="currentUnit.color" x-text="currentUnit.badge"></span>
                    <span class="truncate font-black" x-text="currentUnit.label"></span>
                    <span class="text-[11px] font-mono font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full shrink-0" x-text="'(' + currentUnit.count + ' log)'"></span>
                </div>
                <svg class="w-4 h-4 text-slate-400 transition-transform duration-200 shrink-0 ml-2" :class="openUnitDropdown ? 'rotate-180 text-[#8E1616]' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>

            <!-- Dropdown Panel -->
            <div x-show="openUnitDropdown" 
                 x-cloak
                 x-transition:enter="transition ease-out duration-150 transform opacity-0 scale-95"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="absolute left-0 right-0 z-50 mt-1.5 bg-white rounded-2xl shadow-2xl border border-slate-200 p-2 space-y-1.5 max-h-60 overflow-y-auto">
                
                <div class="divide-y divide-slate-50">
                    <template x-for="u in units" :key="u.id">
                        <div @click="selectUnit(u.id)" 
                             :class="logTab === u.id ? 'bg-[#8E1616] text-white font-black' : 'text-slate-700 hover:bg-slate-50 font-bold'"
                             class="px-3.5 py-2.5 rounded-xl text-xs cursor-pointer flex items-center justify-between transition">
                            <div class="flex items-center gap-2.5 truncate">
                                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase" :class="logTab === u.id ? 'bg-white/20 text-white' : u.color" x-text="u.badge"></span>
                                <span class="truncate" x-text="u.label"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-mono px-2 py-0.5 rounded-full" :class="logTab === u.id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'" x-text="u.count"></span>
                                <span x-show="logTab === u.id" class="text-[10px] font-black">✓</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

    </div>

    <!-- 3. MAIN LOG TABLE -->
    <div class="bg-white rounded-[28px] sm:rounded-[40px] p-4 sm:p-7 shadow-[0_20px_50px_-12px_rgba(29,22,22,0.08)] border border-[#8E1616]/20 space-y-4">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-[#8E1616]/15 gap-2">
            <span class="text-xs font-black uppercase tracking-wider text-[#1D1616] flex items-center gap-2">
                <span>Aliran Data Telemetri</span>
                <span class="text-[10px] text-slate-400 font-normal">({{ ($filterDevice && $filterDevice !== 'all') ? $filterDevice : 'Semua Perangkat' }})</span>
            </span>
            <span class="text-[10px] font-bold text-emerald-800 bg-emerald-100 border border-emerald-300 px-3 py-1 rounded-full inline-flex items-center gap-1.5 w-fit">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Auto-Recording Aktif</span>
            </span>
        </div>

        <!-- 1. TAB: SEMUA LOG -->
        <div x-show="logTab === 'all'" class="space-y-2 font-mono text-xs max-h-[520px] overflow-y-auto pr-1">
            @forelse($recentLogsAll as $log)
                @php
                    $acUnitNumber = 1;
                    $acState = 'OFF';
                    if (preg_match('/AC_(\d+)_([A-Z]+)/', $log->active_ac, $m)) {
                        $acUnitNumber = (int)$m[1];
                        $acState = $m[2];
                    } elseif (str_contains($log->active_ac, 'ON')) {
                        $acState = 'ON';
                    }
                    $badgeColor = match($acUnitNumber) {
                        1 => 'bg-rose-50 text-[#D84040] border-rose-200',
                        2 => 'bg-[#8E1616]/10 text-[#8E1616] border-[#8E1616]/20',
                        3 => 'bg-amber-50 text-amber-800 border-amber-200',
                        4 => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                        5 => 'bg-cyan-50 text-cyan-800 border-cyan-200',
                        default => 'bg-purple-50 text-purple-800 border-purple-200',
                    };
                    $customUnitLabel = $unitLogNames[$acUnitNumber] ?? ("AC {$acUnitNumber}");
                @endphp
                <div class="py-3 px-3 hover:bg-slate-50 rounded-2xl transition border border-slate-100 bg-white flex flex-col md:flex-row md:items-center justify-between gap-2.5 shadow-2xs">
                    <!-- Mobile Top Row / Desktop Left Side -->
                    <div class="flex items-center justify-between md:justify-start gap-2 sm:gap-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            <span class="text-slate-500 font-bold text-[11px] bg-slate-100 px-2 py-0.5 rounded-md font-mono">
                                {{ \Illuminate\Support\Carbon::parse($log->recorded_at)->format('H:i:s') }}
                            </span>
                            <span class="{{ $badgeColor }} border px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider whitespace-nowrap">
                                {{ $customUnitLabel }} • {{ $acState }}
                            </span>
                        </div>
                        <span class="text-[#1D1616] font-bold text-xs font-mono bg-slate-50 px-2 py-0.5 rounded border border-slate-200">
                            {{ $log->device_id ?? 'RPI3B_PINDAD_ROOM_1' }}
                        </span>
                        <span class="hidden md:inline text-slate-300">→</span>
                        <!-- Current & Wattage Desktop View -->
                        <div class="hidden md:flex items-center gap-1.5 text-xs text-slate-600 font-mono">
                            <span>Arus:</span>
                            <strong class="text-[#1D1616] font-black">{{ number_format($log->current_ampere, 4) }} A</strong>
                            <span class="text-[10.5px] text-slate-400 font-medium">({{ round($log->current_ampere * 220) }} W @ 220V)</span>
                        </div>
                    </div>

                    <!-- Mobile Bottom Row / Desktop Right Side -->
                    <div class="flex items-center justify-between md:justify-end gap-3 pt-1.5 md:pt-0 border-t border-slate-100 md:border-0">
                        <div class="md:hidden flex items-center gap-1.5 text-xs text-slate-600 font-mono bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200">
                            <span class="text-[11px] text-slate-500">Arus:</span>
                            <strong class="text-[#1D1616] font-black">{{ number_format($log->current_ampere, 4) }} A</strong>
                            <span class="text-[10px] text-slate-400 font-semibold">({{ round($log->current_ampere * 220) }}W)</span>
                        </div>
                        <span class="text-slate-400 text-[10px] font-sans shrink-0">
                            {{ \Illuminate\Support\Carbon::parse($log->recorded_at)->diffForHumans() }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-slate-400 italic">Belum ada riwayat telemetri tercatat untuk filter ini.</div>
            @endforelse
        </div>

        <!-- 2. TABS: KHUSUS UNIT AC (1..N) -->
        @for($u = 1; $u <= ($logNumAc ?? 2); $u++)
        @php
            $unitLogs = $recentLogsByUnit[$u] ?? [];
            $customUnitLabel = $unitLogNames[$u] ?? ("AC {$u}");
            $badgeColor = match($u) {
                1 => 'bg-rose-50 text-[#D84040] border-rose-200',
                2 => 'bg-[#8E1616]/10 text-[#8E1616] border-[#8E1616]/20',
                3 => 'bg-amber-50 text-amber-800 border-amber-200',
                4 => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                5 => 'bg-cyan-50 text-cyan-800 border-cyan-200',
                default => 'bg-purple-50 text-purple-800 border-purple-200',
            };
        @endphp
        <div x-show="logTab === 'ac{{ $u }}'" class="space-y-2 font-mono text-xs max-h-[520px] overflow-y-auto pr-1">
            @forelse($unitLogs as $log)
                @php
                    $acState = str_contains($log->active_ac, 'ON') ? 'ON' : 'OFF';
                @endphp
                <div class="py-3 px-3 hover:bg-slate-50 rounded-2xl transition border border-slate-100 bg-white flex flex-col md:flex-row md:items-center justify-between gap-2.5 shadow-2xs">
                    <div class="flex items-center justify-between md:justify-start gap-2 sm:gap-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            <span class="text-slate-500 font-bold text-[11px] bg-slate-100 px-2 py-0.5 rounded-md font-mono">
                                {{ \Illuminate\Support\Carbon::parse($log->recorded_at)->format('H:i:s') }}
                            </span>
                            <span class="{{ $badgeColor }} border px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider whitespace-nowrap">
                                {{ $customUnitLabel }} • {{ $acState }}
                            </span>
                        </div>
                        <span class="text-[#1D1616] font-bold text-xs font-mono bg-slate-50 px-2 py-0.5 rounded border border-slate-200">{{ $log->device_id ?? 'RPI3B_PINDAD_ROOM_1' }}</span>
                        <span class="hidden md:inline text-slate-300">→</span>
                        <div class="hidden md:flex items-center gap-1.5 text-xs text-slate-600 font-mono">
                            <span>Arus:</span>
                            <strong class="text-[#1D1616] font-black">{{ number_format($log->current_ampere, 4) }} A</strong>
                            <span class="text-[10.5px] text-slate-400 font-medium">({{ round($log->current_ampere * 220) }} W @ 220V)</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between md:justify-end gap-3 pt-1.5 md:pt-0 border-t border-slate-100 md:border-0">
                        <div class="md:hidden flex items-center gap-1.5 text-xs text-slate-600 font-mono bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200">
                            <span class="text-[11px] text-slate-500">Arus:</span>
                            <strong class="text-[#1D1616] font-black">{{ number_format($log->current_ampere, 4) }} A</strong>
                            <span class="text-[10px] text-slate-400 font-semibold">({{ round($log->current_ampere * 220) }}W)</span>
                        </div>
                        <span class="text-slate-400 text-[10px] font-sans shrink-0">
                            {{ \Illuminate\Support\Carbon::parse($log->recorded_at)->diffForHumans() }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-slate-400 italic">Belum ada riwayat khusus {{ $customUnitLabel }}.</div>
            @endforelse
        </div>
        @endfor
    </div>

@if($isSuperAdmin)
    <!-- ================= MODAL KONFIRMASI BERSIHKAN LOG ================= -->
    <div x-show="modalConfirmClearLogs" 
         x-cloak 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1D1616]/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div @click.away="modalConfirmClearLogs = false"
             class="bg-white rounded-[32px] max-w-md w-full p-6 sm:p-7 shadow-2xl border border-rose-100 space-y-5"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-[22px] bg-rose-100 text-rose-600 flex items-center justify-center text-2xl font-black shrink-0">
                    🗑️
                </div>
                <div>
                    <h3 class="text-lg font-black text-[#1D1616]">Konfirmasi Pembersihan Log</h3>
                    <p class="text-xs text-slate-500 font-semibold">Tindakan permanen & tidak dapat dibatalkan</p>
                </div>
            </div>

            <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 text-xs space-y-2">
                <span class="font-bold text-slate-500 uppercase tracking-wider block text-[10px]">Target Perangkat:</span>
                <div class="flex items-center gap-2 font-mono font-black text-slate-800 bg-white p-2.5 rounded-xl border border-slate-200">
                    <span x-text="selectedLogDevice === 'all' ? '🌐' : '📱'"></span>
                    <span class="truncate text-xs" x-text="selectedLogDevice === 'all' ? 'Seluruh Armada (Semua Perangkat)' : selectedLogDevice"></span>
                </div>
                <p class="text-[11px] text-slate-500 leading-relaxed pt-1">
                    Seluruh riwayat telemetri sensor ACS712 dan histori saklar relay untuk target di atas akan dihapus bersih dari database MongoDB.
                </p>
            </div>

            <form action="{{ route('logs.clear') }}" method="POST" class="flex items-center justify-end gap-3 pt-2">
                @csrf
                <input type="hidden" name="device_id" :value="selectedLogDevice">

                <button type="button" 
                        @click="modalConfirmClearLogs = false"
                        class="px-5 py-3 rounded-2xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold text-xs uppercase tracking-wider cursor-pointer transition">
                    Batal
                </button>

                <button type="submit" 
                        class="px-6 py-3 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-black text-xs uppercase tracking-wider shadow-lg shadow-rose-600/30 cursor-pointer active:scale-95 transition flex items-center gap-2">
                    <span>🗑️</span>
                    <span>Ya, Bersihkan Sekarang</span>
                </button>
            </form>
        </div>
    </div>
    @endif

</div>

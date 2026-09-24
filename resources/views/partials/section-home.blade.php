@php
    $isSuperAdmin = (session('user_role') === 'admin' || session('user_nip') === 'PINDAD-IOT-2026');
    $isOperator = !$isSuperAdmin;
@endphp
<script>
function homeFleetComponent() {
    return {
        viewMode: localStorage.getItem('pindad_home_view') || 'fleet',
        modalNewDevice: false,
        modalEditDevice: false,
        modalSchedule: false,
        modalEditSchedule: false,
        modalRpiSetup: false,
        modalRequestDevice: false,
        rpiSetupData: { name: '', device_id: '', script_name: '', download_url: '', command: '' },
        copySuccess: false,
        copyDepSuccess: false,
        editDeviceData: { id: '', name: '', template_id: '', location: '', device_id: '', ip_address: '', num_ac: 2 },
        editScheduleData: { id: '', label: '', target_ac: 'all', start_time: '06:00', end_time: '18:00', is_active: true },
        openRpiSetup(dev) {
            const cleanId = (dev.device_id || '').toLowerCase().replace(/[^a-z0-9_]/g, '_');
            const scriptName = 'pindad_node_' + cleanId + '.py';
            let serverHost = '{{ $serverLanHost ?? '' }}' || window.location.hostname || '192.168.196.236';
            if (serverHost === '127.0.0.1' || serverHost === 'localhost') {
                serverHost = '192.168.196.236';
            }
            const serverPort = window.location.port ? (':' + window.location.port) : ':8000';
            
            this.rpiSetupData = {
                name: dev.name || '',
                device_id: dev.device_id || '',
                ip_address: dev.ip_address || '192.168.197.64',
                script_name: scriptName,
                server_host: serverHost,
                server_port: serverPort,
                download_url: '',
                command: ''
            };
            this.updateRpiSetupUrls();
            this.copySuccess = false;
            this.modalRpiSetup = true;
        },
        updateRpiSetupUrls() {
            const scriptName = 'pindad_node.py';
            const host = (this.rpiSetupData.server_host || '192.168.196.236').trim();
            const port = this.rpiSetupData.server_port || (window.location.port ? (':' + window.location.port) : ':8000');
            const proto = window.location.protocol || 'http:';
            const downloadUrl = `${proto}//${host}${port}/scripts/download/device?device_id=${encodeURIComponent(this.rpiSetupData.device_id || '')}&broker_host=${host}`;
            const depUrl = `${proto}//${host}${port}/scripts/download/setup_raspberry_pi.sh`;
            
            this.rpiSetupData.script_name = scriptName;
            this.rpiSetupData.download_url = downloadUrl;
            this.rpiSetupData.dep_url = depUrl;
            this.rpiSetupData.dep_command = `curl -sSL "${depUrl}" | bash`;
            this.rpiSetupData.command = `sudo systemctl stop pindad* *telepon* 2>/dev/null; sudo pkill -9 -f 'server_telepon' 2>/dev/null; sudo pkill -9 -f 'pindad' 2>/dev/null; sudo pkill -9 -f python 2>/dev/null; (crontab -l 2>/dev/null | grep -v -E 'pindad|telepon|python') | crontab -; (sudo crontab -l 2>/dev/null | grep -v -E 'pindad|telepon|python') | sudo crontab - 2>/dev/null; sudo rm -f /etc/systemd/system/*pindad* /etc/systemd/system/*telepon* 2>/dev/null; sudo systemctl daemon-reload 2>/dev/null; rm -f ~/pindad_*.py ~/node_config.json ~/node.log 2>/dev/null; curl -sSL "${downloadUrl}" -o ~/pindad_node.py && (crontab -l 2>/dev/null | grep -v 'pindad_node'; echo "@reboot sleep 10 && python3 -u ~/pindad_node.py > ~/node.log 2>&1 &") | crontab - && nohup python3 -u ~/pindad_node.py > ~/node.log 2>&1 &`;
        },
        copyDepCommand() {
            const text = this.rpiSetupData.dep_command;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    this.copyDepSuccess = true;
                    setTimeout(() => { this.copyDepSuccess = false; }, 3000);
                }).catch(() => {
                    this.fallbackCopy(text);
                });
            } else {
                this.fallbackCopy(text);
            }
        },
        copyCommand() {
            const text = this.rpiSetupData.command;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    this.copySuccess = true;
                    setTimeout(() => { this.copySuccess = false; }, 3000);
                }).catch(() => {
                    this.fallbackCopy(text);
                });
            } else {
                this.fallbackCopy(text);
            }
        },
        fallbackCopy(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.opacity = "0";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    this.copySuccess = true;
                    setTimeout(() => { this.copySuccess = false; }, 3000);
                }
            } catch (err) {
                console.error('Fallback copy failed', err);
            }
            document.body.removeChild(textArea);
        },
        openEditDevice(dev) {
            this.editDeviceData = {
                id: dev.id,
                name: dev.name,
                template_id: dev.template_id || '',
                location: dev.location,
                device_id: dev.device_id,
                ip_address: dev.ip_address || '',
                num_ac: dev.num_ac || 2
            };
            this.modalEditDevice = true;
        },
        openEditSchedule(sch) {
            this.editScheduleData = {
                id: sch.id,
                label: sch.label,
                target_ac: sch.target_ac || 'all',
                start_time: sch.start_time ? sch.start_time.substring(0, 5) : '06:00',
                end_time: sch.end_time ? sch.end_time.substring(0, 5) : '18:00',
                is_active: sch.is_active ? true : false
            };
            this.modalEditSchedule = true;
        },
        setView(mode) {
            this.viewMode = mode;
            localStorage.setItem('pindad_home_view', mode);
            if (mode === 'fleet' && window.history.pushState) {
                window.history.pushState({}, '', '/');
            }
        }
    };
}
</script>

<!-- ================= MODUL 1: HOME (UNIVERSAL IOT FLEET OVERVIEW & DEVICE DRILLDOWN) ================= -->
<div class="space-y-8 pb-20" x-data="homeFleetComponent()" @reset-home-view.window="setView('fleet')">

    <!-- ========================================================================= -->
    <!-- VIEW MODE 1: FLEET CENTRAL OVERVIEW (DAFTAR SEMUA DEVICE & TOTAL DAYA) -->
    <!-- ========================================================================= -->
    <div x-show="viewMode === 'fleet'" 
         x-transition:enter="transition ease-out duration-300 transform opacity-0 scale-98"
         x-transition:enter-start="opacity-0 scale-98"
         x-transition:enter-end="opacity-100 scale-100"
         class="space-y-6">

        <!-- 1. HEADER & ACTION BUTTONS -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#8E1616]/20 pb-4">
            <div>
                <span class="text-[11px] font-extrabold uppercase tracking-widest text-[#8E1616] flex items-center gap-1.5">
                    <span>⚡</span>
                    <span>CENTRAL IOT OVERVIEW • PT PINDAD</span>
                </span>
                <h2 class="text-2xl sm:text-3xl font-black text-[#1D1616] tracking-tight mt-0.5">
                    Armada Perangkat Pintar IoT
                </h2>
                <p class="text-xs font-semibold text-slate-500 mt-1">
                    Pantau akumulasi konsumsi daya seluruh pabrik dan kelola setiap perangkat dari satu dasbor terpusat.
                </p>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                @if($isSuperAdmin)
                <button @click="modalNewDevice = true" 
                        type="button"
                        class="bg-[#D84040] hover:bg-[#8E1616] text-white rounded-[24px] text-xs font-black uppercase tracking-wider py-3.5 px-6 shadow-lg shadow-[#D84040]/30 transition flex items-center space-x-2 shrink-0 cursor-pointer active:scale-95">
                    <span class="text-base leading-none font-black">+</span>
                    <span>Tambah Perangkat Baru</span>
                </button>
                @else
                <button @click="modalRequestDevice = true" 
                        type="button"
                        class="bg-gradient-to-r from-[#8E1616] to-[#1D1616] hover:opacity-95 text-white rounded-[24px] text-xs font-black uppercase tracking-wider py-3.5 px-5 shadow-lg shadow-[#8E1616]/30 transition flex items-center space-x-2 shrink-0 cursor-pointer active:scale-95">
                    <span class="text-sm leading-none font-black">📋</span>
                    <span>Ajukan Perangkat Baru</span>
                </button>
                @endif
            </div>
        </div>

        @if(!$isSuperAdmin && isset($myDeviceRequests) && count($myDeviceRequests) > 0)
        <!-- STATUS TIKET PENGAJUAN OPERATOR -->
        <div class="bg-white rounded-[28px] p-4 sm:p-5 border border-slate-200 shadow-xs space-y-3">
            <div class="flex items-center gap-2">
                <span class="text-sm">📋</span>
                <h4 class="text-xs font-black uppercase tracking-wider text-[#1D1616]">Riwayat Pengajuan Perangkat</h4>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                @foreach($myDeviceRequests as $myReq)
                <div class="p-3 rounded-2xl border {{ $myReq->status === 'approved' ? 'bg-emerald-50/60 border-emerald-200' : ($myReq->status === 'rejected' ? 'bg-rose-50/60 border-rose-200' : 'bg-amber-50/60 border-amber-200') }} flex items-start justify-between gap-3">
                    <div>
                        <div class="font-black text-xs text-[#1D1616]">{{ $myReq->room_name }}</div>
                        <div class="text-[10px] text-slate-500 font-medium">📍 {{ $myReq->location }} &bull; {{ $myReq->request_type === 'new_device' ? 'Pengadaan Baru' : 'Izin Akses' }}</div>
                        @if($myReq->admin_notes)
                            <div class="text-[10px] text-slate-600 mt-1 italic bg-white/70 px-2 py-0.5 rounded border border-black/5">Admin: {{ $myReq->admin_notes }}</div>
                        @endif
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider shrink-0 {{ $myReq->status === 'approved' ? 'bg-emerald-600 text-white' : ($myReq->status === 'rejected' ? 'bg-rose-600 text-white' : 'bg-amber-500 text-white') }}">
                        {{ $myReq->status === 'approved' ? 'Disetujui' : ($myReq->status === 'rejected' ? 'Ditolak' : 'Menunggu Review') }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if(!empty($activeAnomalies))
        <!-- BANNER PERINGATAN ANOMALI KEGAGALAN AC (TELEGRAM ALERT ACTIVE) -->
        <div class="bg-gradient-to-r from-rose-600 via-rose-700 to-red-800 text-white p-4 sm:p-5 rounded-[28px] shadow-xl border border-rose-400/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3 animate-pulse">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center font-black text-xl shrink-0">
                    🚨
                </div>
                <div>
                    <h4 class="font-black text-sm text-white uppercase tracking-wider">Peringatan Kritis: Terdeteksi AC Gagal Menyala / Mati!</h4>
                    <div class="text-xs text-rose-100 font-semibold space-y-0.5 mt-0.5">
                        @foreach($activeAnomalies as $anom)
                        <p>• <b>{{ $anom['room_name'] ?? $anom['device_id'] }}</b> (Unit {{ $anom['ac_number'] }} - {{ $anom['unit_name'] ?? 'AC' }}): Status ON tapi Arus Terbaca <code>{{ $anom['current_ampere'] ?? 0 }} A</code>.</p>
                        @endforeach
                    </div>
                </div>
            </div>
            <span class="text-[10.5px] font-black bg-white text-rose-800 px-3.5 py-1.5 rounded-full uppercase tracking-wider shrink-0 shadow-xs self-start sm:self-center flex items-center gap-1.5">
                <span>🤖</span>
                <span>Notifikasi Dikirim ke Telegram</span>
            </span>
        </div>
        @endif

        <!-- 2. CENTRAL KPI HERO: TOTAL BEBAN SELURUH ALAT IOT -->
        <div class="bg-[#1D1616] rounded-[40px] p-6 sm:p-8 text-white shadow-[0_25px_60px_-15px_rgba(29,22,22,0.35)] border border-[#8E1616]/30 flex flex-col md:flex-row md:items-center justify-between gap-6 relative overflow-hidden">
            <div class="absolute -right-20 -top-20 w-80 h-80 bg-[#8E1616]/25 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-20 -bottom-20 w-80 h-80 bg-[#D84040]/15 rounded-full blur-3xl pointer-events-none"></div>

            <div class="space-y-2 relative z-10">
                <div class="flex items-center space-x-2">
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#D84040] bg-[#D84040]/15 px-3 py-0.5 rounded-full border border-[#D84040]/30">
                        Total Beban Seluruh Armada
                    </span>
                    <span class="text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-2.5 py-0.5 rounded-full border border-emerald-500/20 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>{{ $onlineCount ?? count($devices) }} Node Aktif</span>
                    </span>
                </div>

                <div class="flex flex-wrap items-baseline gap-2 sm:gap-3">
                    <span class="text-3xl sm:text-4xl lg:text-5xl font-black font-mono text-white tracking-tight">
                        {{ number_format($totalFleetCurrent, 2) }} <span class="text-xl sm:text-2xl font-sans font-bold text-[#EEEEEE]/80">Ampere</span>
                    </span>
                    <span class="text-base sm:text-lg lg:text-xl font-extrabold text-amber-400 font-mono">
                        ≈ {{ number_format($totalFleetWatt) }} Watt
                    </span>
                </div>

                <p class="text-xs text-[#EEEEEE]/70 font-medium">
                    Total konsumsi daya listrik terukur dari seluruh unit AC yang terhubung.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3 shrink-0 relative z-10 w-full sm:w-auto">
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                    <span class="text-[10px] font-bold text-[#EEEEEE]/60 uppercase block">Total Perangkat</span>
                    <span class="text-xl font-black text-white mt-0.5 block">{{ count($devices) }} Node</span>
                </div>
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                    <span class="text-[10px] font-bold text-[#EEEEEE]/60 uppercase block">Total Unit Pendingin</span>
                    <span class="text-xl font-black text-[#D84040] mt-0.5 block">{{ $devices->sum('num_ac') }} AC</span>
                </div>
            </div>
        </div>

        <!-- 3. GRID KARTU PERANGKAT PINTAR IOT -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-black text-[#1D1616] tracking-tight flex items-center gap-2">
                    <span>Daftar Perangkat Terpasang</span>
                    <span class="text-xs font-bold text-[#8E1616] bg-[#8E1616]/10 px-2.5 py-0.5 rounded-full">{{ count($devices) }} Unit</span>
                </h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                @forelse($devices as $dev)
                @php
                    $devStat = $fleetStats[$dev->device_id] ?? ['is_online' => false, 'total_watt' => 0, 'total_current' => 0, 'last_seen' => 'Standby'];
                    $isAcType = ($dev->type === 'ac_monitoring' || $dev->device_id === 'RPI3B_PINDAD_ROOM_1');
                @endphp
                <div class="bg-white rounded-[32px] p-6 shadow-[0_20px_45px_-12px_rgba(29,22,22,0.08)] border-2 transition-all duration-300 hover:shadow-xl relative flex flex-col justify-between {{ $selectedDeviceId === $dev->device_id ? 'border-[#D84040] ring-4 ring-[#D84040]/10' : 'border-slate-100 hover:border-slate-300' }}">
                    
                    <div class="space-y-4">
                        <!-- Card Header -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl shrink-0 {{ $devStat['is_online'] ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-slate-100 text-slate-400 border border-slate-200' }}">
                                    {{ $dev->icon ?? ($isAcType ? '❄️' : '💡') }}
                                </div>
                                <div class="min-w-0">
                                    <h4 class="font-black text-sm sm:text-base text-[#1D1616] leading-snug break-words">{{ $dev->name }}</h4>
                                    <p class="text-xs text-slate-500 flex items-center gap-1 mt-0.5" title="{{ $dev->location }}">
                                        <svg class="w-3.5 h-3.5 text-[#8E1616] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        </svg>
                                        <span class="truncate">{{ $dev->location }}</span>
                                    </p>
                                </div>
                            </div>

                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider shrink-0 {{ $devStat['is_online'] ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                <span class="w-2 h-2 rounded-full {{ $devStat['is_online'] ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                                <span>{{ $devStat['is_online'] ? 'Online' : 'Standby' }}</span>
                            </span>
                        </div>

                        <!-- 2x2 Clean Telemetry Info -->
                        <div class="grid grid-cols-2 gap-2.5">
                            <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Beban Daya</span>
                                <span class="text-xs font-black text-emerald-600 mt-0.5 block">
                                    {{ $devStat['total_watt'] }} W <span class="text-[10px] text-slate-400 font-normal">({{ $devStat['total_current'] }} A)</span>
                                </span>
                            </div>

                            <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Kapasitas</span>
                                <span class="text-xs font-black text-[#D84040] mt-0.5 block truncate">
                                    {{ $isAcType ? ($dev->num_ac . ' Unit AC') : ($dev->hardware_type) }}
                                </span>
                            </div>

                            <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Alamat IP</span>
                                <span class="text-[11px] font-bold text-slate-700 mt-0.5 block font-mono truncate">
                                    {{ $dev->ip_address ?? '192.168.197.64' }}
                                </span>
                            </div>

                            <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">ID MQTT</span>
                                <span class="text-[10px] font-mono font-bold text-[#1D1616] mt-0.5 block break-all" title="{{ $dev->device_id }}">
                                    {{ $dev->device_id }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button Tier: Clean 2-Tier Hierarchy (No Text Wrap Glitch) -->
                    <div class="mt-5 pt-4 border-t border-slate-100 flex flex-col gap-2">
                        <!-- Primary Action: Full Width Kontrol & Jadwal -->
                        <a href="{{ route('dashboard', ['device_id' => $dev->device_id]) }}" 
                           @if($selectedDeviceId === $dev->device_id) @click.prevent="setView('detail')" @else @click="setView('detail')" @endif
                           class="w-full h-11 px-4 rounded-2xl text-center font-black text-xs uppercase tracking-wider bg-[#1D1616] hover:bg-[#8E1616] text-white shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer active:scale-95">
                            <span>Kontrol & Jadwal</span>
                            <span class="text-sm">➔</span>
                        </a>

                        @if($isSuperAdmin)
                        <!-- Secondary Utilities: Setup Node, Edit, Hapus (Super Admin Only) -->
                        <div class="flex items-center gap-2">
                            <!-- Setup Node Button -->
                            <button @click="openRpiSetup({
                                name: '{{ addslashes($dev->name) }}',
                                device_id: '{{ $dev->device_id }}',
                                ip_address: '{{ $dev->ip_address }}'
                            })" 
                            type="button" 
                            class="flex-1 h-9 px-3 rounded-xl bg-slate-900 hover:bg-[#8E1616] text-white transition cursor-pointer active:scale-95 flex items-center justify-center gap-1.5 shadow-xs font-black text-[11px] uppercase tracking-wide" 
                            title="Panduan Setup & Salin Perintah Auto-Start Raspberry Pi">
                                <span class="text-amber-400">⚡</span>
                                <span>Setup Node</span>
                            </button>

                            <!-- Edit Device Button -->
                            <button @click="openEditDevice({
                                id: '{{ $dev->id }}',
                                name: '{{ addslashes($dev->name) }}',
                                template_id: '{{ $dev->template_id }}',
                                location: '{{ addslashes($dev->location) }}',
                                device_id: '{{ $dev->device_id }}',
                                ip_address: '{{ $dev->ip_address }}',
                                num_ac: {{ $dev->num_ac ?? 2 }}
                            })" 
                            type="button" 
                            class="w-9 h-9 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 transition cursor-pointer active:scale-95 flex items-center justify-center shrink-0 shadow-xs" 
                            title="Edit Informasi Perangkat">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>

                            <!-- Delete Device Button -->
                            <form action="{{ route('devices.destroy', $dev->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus perangkat {{ $dev->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-9 h-9 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 transition cursor-pointer active:scale-95 flex items-center justify-center shrink-0 shadow-xs" title="Hapus Perangkat">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>
                @empty
                <div class="col-span-full bg-white rounded-[36px] p-8 sm:p-12 text-center border-2 border-dashed border-slate-200 shadow-sm space-y-4">
                    @if($isSuperAdmin)
                    <div class="w-16 h-16 rounded-3xl bg-rose-50 text-[#D84040] text-3xl flex items-center justify-center mx-auto shadow-inner">
                        ✨
                    </div>
                    <div class="max-w-md mx-auto space-y-1.5">
                        <h4 class="text-xl font-black text-[#1D1616]">Belum Ada Perangkat Terdaftar</h4>
                        <p class="text-xs font-semibold text-slate-500">
                            Sistem belum memiliki node perangkat IoT yang terdaftar. Silakan daftarkan node pertama Anda untuk memulai monitoring armada.
                        </p>
                    </div>
                    <div class="pt-2">
                        <button @click="modalNewDevice = true" 
                                type="button"
                                class="inline-flex items-center gap-2 bg-[#D84040] hover:bg-[#8E1616] text-white rounded-2xl text-xs font-black uppercase tracking-wider py-3.5 px-6 shadow-lg shadow-[#D84040]/30 transition cursor-pointer active:scale-95">
                            <span class="text-base font-black">+</span>
                            <span>Tambah Perangkat Baru Sekarang</span>
                        </button>
                    </div>
                    @else
                    <div class="w-16 h-16 rounded-3xl bg-rose-50 text-[#8E1616] text-3xl flex items-center justify-center mx-auto shadow-inner">
                        🔒
                    </div>
                    <div class="max-w-md mx-auto space-y-1.5">
                        <h4 class="text-xl font-black text-[#1D1616]">Belum Ada Akses Perangkat Ruangan</h4>
                        <p class="text-xs font-semibold text-slate-500">
                            Akun Operator Anda (<code>{{ session('user_nip') }}</code>) belum memiliki izin akses ke perangkat AC manapun. Anda dapat mengajukan permohonan pengadaan atau izin akses ruangan baru ke Super Administrator.
                        </p>
                    </div>
                    <div class="pt-2">
                        <button @click="modalRequestDevice = true" 
                                type="button"
                                class="inline-flex items-center gap-2 bg-gradient-to-r from-[#8E1616] to-[#1D1616] hover:opacity-95 text-white rounded-2xl text-xs font-black uppercase tracking-wider py-3.5 px-6 shadow-lg shadow-[#8E1616]/30 transition cursor-pointer active:scale-95">
                            <span class="text-sm font-black">📋</span>
                            <span>Ajukan Permohonan Perangkat / Akses Ruangan</span>
                        </button>
                    </div>
                    @endif
                </div>
                @endforelse
            </div>
        </div>
    </div>


    <!-- ========================================================================= -->
    <!-- VIEW MODE 2: DEVICE DETAIL VIEW (KONTROL SAKLAR, ARUS PER AC & JADWAL RUANGAN) -->
    <!-- ========================================================================= -->
    <div x-show="viewMode === 'detail'" 
         x-transition:enter="transition ease-out duration-300 transform opacity-0 scale-98"
         x-transition:enter-start="opacity-0 scale-98"
         x-transition:enter-end="opacity-100 scale-100"
         class="space-y-8">

        @if(!$currentDevice)
        <div class="bg-white rounded-[36px] p-8 sm:p-12 text-center border-2 border-dashed border-slate-200 shadow-sm space-y-4">
            <div class="w-16 h-16 rounded-3xl bg-rose-50 text-[#D84040] text-3xl flex items-center justify-center mx-auto shadow-inner">
                🔍
            </div>
            <div class="max-w-md mx-auto space-y-1.5">
                <h4 class="text-xl font-black text-[#1D1616]">Belum Ada Perangkat Dipilih</h4>
                <p class="text-xs font-semibold text-slate-500">
                    Akun Anda belum memiliki perangkat yang dipilih atau terdaftar. Silakan pilih atau daftarkan node IoT pertama Anda.
                </p>
            </div>
            <div class="pt-2 flex items-center justify-center gap-3">
                <button @click="setView('fleet')" type="button" class="px-5 py-2.5 rounded-2xl bg-[#1D1616] hover:bg-[#8E1616] text-white font-bold text-xs uppercase transition cursor-pointer">
                    Kembali ke Daftar Armada
                </button>
                @if($isSuperAdmin)
                <button @click="modalNewDevice = true" type="button" class="px-5 py-2.5 rounded-2xl bg-[#D84040] hover:bg-[#8E1616] text-white font-bold text-xs uppercase shadow-md transition cursor-pointer">
                    + Tambah Perangkat
                </button>
                @endif
            </div>
        </div>
        @else

        <!-- 1. DETAIL HEADER DENGAN TOMBOL KEMBALI KE SEMUA DEVICE -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#8E1616]/20 pb-4">
            <div class="flex items-center space-x-3.5">
                <button @click="setView('fleet')" 
                        type="button"
                        class="w-12 h-12 rounded-[20px] bg-white border border-[#8E1616]/30 text-[#8E1616] hover:bg-[#8E1616] hover:text-white transition flex items-center justify-center font-black text-lg shadow-sm cursor-pointer"
                        title="Kembali ke Daftar Semua Perangkat">
                    ⬅
                </button>
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616]">
                            KONTROL & MONITORING SPESIFIK RUANGAN
                        </span>
                        <span class="text-[9px] font-mono font-bold bg-slate-200 text-slate-700 px-2 py-0.5 rounded-md">
                            {{ $currentDevice->device_id ?? 'RPI3B_PINDAD_ROOM_1' }}
                        </span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-black text-[#1D1616] tracking-tight">
                        {{ $currentDevice->name ?? 'Ruang Server Utama (Lt. 1)' }}
                    </h2>
                    <p class="text-xs font-semibold text-slate-500">
                        📍 {{ $currentDevice->location ?? 'Gedung Divisi Mutu & TI' }} • IP: {{ $currentDevice->ip_address ?? '192.168.197.64' }}
                    </p>
                </div>
            </div>

            <!-- Download Standalone Python Script for This Room -->
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('scripts.download', ['type' => 'device', 'device_id' => $currentDevice->device_id ?? 'RPI3B_PINDAD_ROOM_1']) }}" 
                   class="px-4 py-2.5 rounded-2xl bg-[#1D1616] hover:bg-[#8E1616] text-white font-black text-xs uppercase tracking-wider transition cursor-pointer flex items-center gap-2 shadow-sm active:scale-95"
                   title="Unduh 1 file Python siap pakai untuk perangkat ini tanpa perlu file json">
                    <span>📥</span>
                    <span>Unduh Skrip (.py)</span>
                </a>
            </div>
        </div>

        <!-- 2. SUMMARY HERO STATUS KHUSUS RUANGAN INI -->
        <div class="bg-[#1D1616] rounded-[40px] p-6 sm:p-7 text-white shadow-[0_20px_50px_-12px_rgba(29,22,22,0.35)] border border-[#8E1616]/30 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-1">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#D84040] block">Total Konsumsi Ruangan Ini</span>
                @php
                    $sumAmpereRoom = collect($unitData ?? [])->sum('ampere');
                    $sumWattRoom = collect($unitData ?? [])->sum('watt');
                @endphp
                <div class="flex items-baseline space-x-3">
                    <span id="stat-total-current" class="text-3xl sm:text-4xl font-black font-mono text-white tracking-tight">
                        {{ number_format($sumAmpereRoom, 4) }} A
                    </span>
                    <span id="stat-total-watt" class="text-base sm:text-lg font-extrabold text-[#EEEEEE]/80">
                        ≈ {{ $sumWattRoom }} Watt
                    </span>
                </div>
                <span class="text-xs font-bold text-[#D84040] block">⚡ Sumber: Sensor Arus ACS712 & Relai Industri</span>
            </div>

            @php
                $isSelectedDevOnline = $fleetStats[$selectedDeviceId]['is_online'] ?? false;
            @endphp
            <div class="flex flex-col sm:flex-row items-end gap-2.5 shrink-0">
                <div id="ws-realtime-badge" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-400/40 text-emerald-300 text-[10px] font-black uppercase tracking-wider transition-all duration-300 shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>⚡ WebSocket Live (&lt; 5ms)</span>
                </div>
                <div id="node-online-status-pill" class="bg-white/10 backdrop-blur-md rounded-[28px] px-6 py-3.5 border border-white/10 flex items-center space-x-4 shrink-0">
                    <span class="text-2xl {{ $isSelectedDevOnline ? 'animate-pulse' : '' }}">
                        {{ $isSelectedDevOnline ? '🟢' : '⚪' }}
                    </span>
                    <div>
                        <span class="text-sm font-black text-white block">
                            {{ $isSelectedDevOnline ? 'Node Online (Live Telemetri)' : 'Node Standby (Menunggu Sinyal)' }}
                        </span>
                        <span class="text-xs text-[#EEEEEE]/70 font-semibold">
                            {{ $isSelectedDevOnline ? 'Sinkronisasi 5 Detik' : 'Perangkat Belum Terhubung' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. KONTROL SAKLAR & ARUS PER UNIT AC (FLEKSIBEL 1, 2, 4, ATAU N UNIT) -->
        <div class="grid grid-cols-1 md:grid-cols-2 {{ count($unitData ?? []) > 2 ? 'xl:grid-cols-2 2xl:grid-cols-4' : '' }} gap-6"
             x-data="{
                 @foreach($unitData ?? [] as $uNum => $u)
                     ac{{ $uNum }}On: {{ $u['is_on'] ? 'true' : 'false' }},
                 @endforeach
                 loadingNum: null,
                 lastToggledAt: {},
                 updateFromTelemetry(detail) {
                     if (!detail || !detail.units) return;
                     let now = Date.now();
                     Object.keys(detail.units).forEach(num => {
                         // Anti-flicker: Jangan timpa status saklar jika baru diklik user dalam kurun 4 detik
                         if ((now - (this.lastToggledAt[num] || 0)) < 4000) {
                             return;
                         }
                         if (this.loadingNum != num) {
                             this['ac' + num + 'On'] = (detail.units[num].status === 'ON');
                         }
                     });
                 },
                 async toggleSwitch(acNumber) {
                     let key = 'ac' + acNumber + 'On';
                     let currentState = this[key];
                     let nextState = !currentState;
                     
                     // Instant visual state transition (0ms UI lag)
                     this[key] = nextState;
                     this.lastToggledAt[acNumber] = Date.now();
                     this.loadingNum = acNumber;

                     try {
                         let res = await fetch('{{ route('ac.control') }}', {
                             method: 'POST',
                             headers: {
                                 'Content-Type': 'application/json',
                                 'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                 'Accept': 'application/json',
                                 'X-Requested-With': 'XMLHttpRequest'
                             },
                             body: JSON.stringify({
                                 ac_number: acNumber,
                                 command: nextState ? 'ON' : 'OFF',
                                 state: nextState ? 'ON' : 'OFF',
                                 device_id: '{{ $selectedDeviceId }}'
                             })
                         });
                         let json = await res.json();
                         if (json.success || json.status === 'success') {
                             this[key] = (json.state === 'ON' || json.command === 'ON');
                         } else {
                             this[key] = currentState;
                         }
                     } catch (e) {
                         console.error('Toggle error:', e);
                         this[key] = currentState;
                     } finally {
                         this.loadingNum = null;
                     }
                 }
             }"
             @telemetry-updated.window="updateFromTelemetry($event.detail)">
            
            @foreach($unitData ?? [] as $acNum => $unit)
            <!-- AC UNIT {{ $acNum }} CARD -->
            <div class="bg-white rounded-[40px] p-6 sm:p-7 shadow-[0_20px_50px_-12px_rgba(29,22,22,0.08)] border border-[#8E1616]/20 space-y-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 rounded-[20px] bg-[#EEEEEE] text-[#8E1616] font-black text-xl flex items-center justify-center">
                                ❄️
                            </div>
                            <div>
                                <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616]">UNIT {{ $acNum }} • PIN GPIO {{ $unit['gpio'] }}</span>
                                <h3 class="text-xl font-black text-[#1D1616]">{{ $unit['name'] }}</h3>
                            </div>
                        </div>
                        <span id="badge-status-ac{{ $acNum }}" 
                              class="px-3.5 py-1 rounded-full text-xs font-black uppercase tracking-wider transition-colors"
                              :class="ac{{ $acNum }}On ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'"
                              x-text="ac{{ $acNum }}On ? 'ONLINE' : 'STANDBY'">
                            {{ $unit['is_on'] ? 'ONLINE' : 'STANDBY' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-5">
                        <div class="bg-[#EEEEEE]/60 rounded-[28px] p-4 border border-[#8E1616]/10">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 block">Beban Arus Real-Time</span>
                            <span id="val-current-ac{{ $acNum }}" class="text-2xl font-black font-mono text-[#1D1616] mt-1 block">
                                {{ number_format((float)$unit['ampere'], 4) }} A
                            </span>
                            <span id="val-watt-ac{{ $acNum }}" class="text-xs font-bold text-slate-400 block mt-0.5">
                                ≈ {{ $unit['watt'] }} Watt
                            </span>
                        </div>

                        <div class="bg-[#EEEEEE]/60 rounded-[28px] p-4 border border-[#8E1616]/10">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 block">Jadwal Shift DS3231</span>
                            <span id="shift-text-ac{{ $acNum }}" class="text-xs {{ $unit['shift'] === 'Belum Ada Jadwal' ? 'font-semibold text-slate-400 italic' : 'font-extrabold text-[#8E1616]' }} mt-1.5 block leading-snug">
                                {{ $unit['shift'] }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Toggle Switch AC {{ $acNum }} (Zero Reload AJAX) -->
                <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-black text-[#1D1616] block">Saklar Manual AC {{ $acNum }}</span>
                        <span class="text-[10px] text-slate-400 font-semibold">Klik saklar untuk ON/OFF langsung</span>
                    </div>

                    <button @click="toggleSwitch({{ $acNum }})" 
                            type="button" 
                            :title="ac{{ $acNum }}On ? 'Klik untuk mematikan AC {{ $acNum }}' : 'Klik untuk menyalakan AC {{ $acNum }}'"
                            class="group relative inline-flex items-center h-10 w-24 rounded-full transition-all duration-300 p-1 cursor-pointer select-none shadow-md active:scale-95"
                            :class="ac{{ $acNum }}On ? 'bg-gradient-to-r from-emerald-500 to-teal-600 ring-2 ring-emerald-400/50 shadow-emerald-200' : 'bg-gradient-to-r from-slate-300 to-slate-400 shadow-slate-200'">
                        
                        <!-- Label Text Inside Switch -->
                        <span class="w-full text-center text-[10px] font-black uppercase tracking-wider transition-all font-mono"
                              :class="ac{{ $acNum }}On ? 'text-white pr-7' : 'text-slate-600 pl-7'"
                              x-text="ac{{ $acNum }}On ? 'ON' : 'OFF'">
                        </span>

                        <!-- Sliding Circular Knob -->
                        <span class="absolute top-1 left-1 bg-white w-8 h-8 rounded-full shadow-lg transform transition-transform duration-300 flex items-center justify-center"
                              :class="ac{{ $acNum }}On ? 'translate-x-14' : 'translate-x-0'">
                            <span class="w-2.5 h-2.5 rounded-full transition-colors"
                                  :class="ac{{ $acNum }}On ? 'bg-emerald-500 ring-2 ring-emerald-200' : 'bg-slate-400'"></span>
                        </span>
                    </button>
                </div>
            </div>
            @endforeach
        </div>

        <!-- 5. PUSAT PENJADWALAN SHIFT 12 JAM KHUSUS RUANGAN INI -->
        <div class="bg-white rounded-[40px] p-6 sm:p-8 shadow-[0_20px_50px_-12px_rgba(29,22,22,0.08)] border border-[#8E1616]/20 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616]">OTOMASI HARDWARE RTC DS3231</span>
                    <h3 class="text-xl font-black text-[#1D1616] tracking-tight mt-0.5">Jadwal Shift & Rotasi AC Ruangan Ini</h3>
                    <p class="text-xs font-medium text-slate-500 mt-0.5">Aturan jadwal pergantian shift 12 jam otomatis tersinkronisasi ke modul RTC Raspberry Pi.</p>
                </div>

                <button @click="modalSchedule = true" 
                        class="bg-[#D84040] hover:bg-[#8E1616] text-white rounded-2xl text-xs font-black uppercase tracking-wider py-2.5 px-5 shadow-md transition flex items-center space-x-2 cursor-pointer shrink-0">
                    <span>+ Tambah Aturan Jadwal</span>
                </button>
            </div>

            <!-- Schedule Rules List -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($schedules as $sch)
                <div class="rounded-[28px] p-5 border flex items-center justify-between gap-4 transition-all {{ $sch->is_active ? 'bg-slate-50 border-slate-200 shadow-xs' : 'bg-slate-100/70 border-slate-300/80 opacity-65' }}">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <h4 class="font-black text-sm text-[#1D1616]">{{ $sch->label }}</h4>
                            <span class="text-[9px] font-black uppercase px-2.5 py-0.5 rounded-full flex items-center gap-1 {{ $sch->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $sch->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                <span>{{ $sch->is_active ? 'Aktif' : 'Non-Aktif' }}</span>
                            </span>
                        </div>
                        <p class="text-xs font-mono font-bold text-[#8E1616]">
                            ⏰ {{ substr($sch->start_time, 0, 5) }} - {{ substr($sch->end_time, 0, 5) }} WIB
                        </p>
                        <p class="text-[11px] text-slate-500">
                            Target: 
                            @if($sch->target_ac === 'all')
                                Seluruh Unit AC Sekaligus ({{ count($unitData ?? []) }} Unit)
                            @elseif(isset($unitData[(int)$sch->target_ac]))
                                {{ $unitData[(int)$sch->target_ac]['name'] }}
                            @else
                                Unit AC {{ $sch->target_ac }}
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <!-- Edit Schedule Button (Pencil Icon) -->
                        <button @click="openEditSchedule({
                            id: '{{ $sch->id }}',
                            label: '{{ addslashes($sch->label) }}',
                            target_ac: '{{ $sch->target_ac }}',
                            start_time: '{{ $sch->start_time }}',
                            end_time: '{{ $sch->end_time }}',
                            is_active: {{ $sch->is_active ? 'true' : 'false' }}
                        })" 
                        type="button" 
                        class="p-2.5 rounded-2xl bg-amber-100 hover:bg-amber-200 text-amber-800 transition cursor-pointer text-xs font-bold flex items-center justify-center shrink-0 active:scale-95 shadow-xs" 
                        title="Edit Jam & Pengaturan Jadwal">
                            ✏️
                        </button>

                        <!-- Delete Schedule Button -->
                        <form action="{{ route('schedules.destroy', $sch->id) }}" method="POST" onsubmit="return confirm('Hapus aturan jadwal ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-2.5 rounded-2xl bg-rose-100 hover:bg-rose-200 text-rose-700 transition cursor-pointer text-xs font-bold flex items-center justify-center shrink-0 active:scale-95 shadow-xs" title="Hapus Jadwal">
                                🗑️
                            </button>
                        </form>
                    </div>
                </div>
                @empty
                <div class="col-span-2 text-center py-6 text-slate-400 text-xs">Belum ada aturan jadwal khusus untuk ruangan ini.</div>
                @endforelse
            </div>
        </div>
        @endif
    </div>


    <!-- ========================================================================= -->
@if($isSuperAdmin)
    <!-- MODAL 1: TAMBAH PERANGKAT IOT BARU (PILIH TEMPLATE & DAFTARKAN) -->
    <!-- ========================================================================= -->
    <div x-show="modalNewDevice" 
         x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalNewDevice = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-lg w-full shadow-2xl border border-slate-200 space-y-3.5 sm:space-y-4 relative max-h-[82vh] sm:max-h-[88vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 sm:pb-3.5">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl sm:rounded-[20px] bg-[#D84040]/10 text-[#D84040] flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        +
                    </div>
                    <div>
                        <h4 class="text-base sm:text-lg font-black text-[#1D1616]">Daftarkan Perangkat Baru</h4>
                        <p class="text-[11px] sm:text-xs text-slate-500">Pilih template blueprint & hubungkan kontroler</p>
                    </div>
                </div>
                <button @click="modalNewDevice = false" class="text-slate-400 hover:text-[#D84040] text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form action="{{ route('devices.store') }}" method="POST" class="space-y-3 sm:space-y-3.5" 
                  x-data="{ 
                      devName: '', 
                      devId: '', 
                      devIp: '',
                      selectedTmpl: '{{ $templates->first()->id ?? '' }}',
                      devNumAc: 2,
                      existingIps: {
                          @foreach($devices as $d)
                          '{{ trim($d->ip_address) }}': '{{ addslashes($d->name) }} ({{ $d->device_id }})',
                          @endforeach
                      },
                      get ipConflict() {
                          let ip = (this.devIp || '').trim();
                          if (!ip || ip === '192.168.196.x') return null;
                          return this.existingIps[ip] || null;
                      },
                      templatesMap: {
                          @foreach($templates as $t)
                          '{{ $t->id }}': {
                              name: '{{ addslashes($t->name) }}',
                              hardware: '{{ addslashes($t->hardware_type) }}',
                              conn: '{{ addslashes($t->connection_type) }}',
                              icon: '{{ addslashes($t->icon ?? '⚡') }}',
                              numAc: {{ count(collect($t->datastreams ?? [])->filter(fn($ds) => str_starts_with($ds['pin'], 'V') && ($ds['type'] ?? '') === 'Integer' && ($ds['max'] ?? 1) == 1 && !str_contains(strtolower($ds['name'] ?? ''), 'total') && !str_contains(strtolower($ds['name'] ?? ''), 'turbo') && !str_contains(strtolower($ds['name'] ?? ''), 'priority') && !str_contains(strtolower($ds['name'] ?? ''), 'arus') && !str_contains(strtolower($ds['name'] ?? ''), 'ampere'))) ?: 1 }}
                          },
                          @endforeach
                      },
                      updateSlug() {
                          this.devId = 'RPI3B_' + this.devName.toUpperCase().replace(/[^A-Z0-9]/g, '_').replace(/_+/g, '_');
                      }
                  }"
                  x-init="$watch('selectedTmpl', val => { if(templatesMap[val]) { devNumAc = templatesMap[val].numAc; } }); if(templatesMap[selectedTmpl]) { devNumAc = templatesMap[selectedTmpl].numAc; }">
                @csrf
                
                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Blueprint Template Perangkat *</label>
                    <select name="template_id" 
                            x-model="selectedTmpl"
                            required
                            class="w-full px-3.5 py-2.5 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm bg-white focus:ring-2 focus:ring-[#D84040] outline-none cursor-pointer">
                        @foreach($templates as $t)
                        <option value="{{ $t->id }}">{{ $t->icon ?? '⚡' }} {{ $t->name }} ({{ $t->hardware_type }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3.5">
                    <div>
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Nama Perangkat / Ruangan *</label>
                        <input type="text" name="name" x-model="devName" @input="updateSlug()" required placeholder="Contoh: Ruang Server 2" class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#D84040] outline-none">
                    </div>

                    <div>
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Lokasi Gedung / Ruangan *</label>
                        <input type="text" name="location" required placeholder="Contoh: Gedung TIK" class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#D84040] outline-none">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider">ID Perangkat (MQTT) *</label>
                        <span class="text-[9px] font-bold text-slate-400 flex items-center gap-1 bg-slate-100 px-2 py-0.5 rounded-md">
                            <span>🔒</span>
                            <span>Auto-Generated</span>
                        </span>
                    </div>
                    <input type="text" name="device_id" x-model="devId" readonly required placeholder="RPI3B_..." class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm font-mono font-bold uppercase bg-slate-100 text-slate-600 cursor-not-allowed outline-none select-none shadow-2xs">
                    <p class="text-[10px] text-slate-400 mt-1">ID MQTT digenerate otomatis dari nama perangkat.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3.5">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider">IP Address</label>
                            <span x-show="ipConflict" class="text-[9px] font-bold text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded-md flex items-center gap-1">
                                <span>⚠️</span>
                                <span>Terpakai</span>
                            </span>
                        </div>
                        <input type="text" 
                               name="ip_address" 
                               x-model="devIp"
                               placeholder="Contoh: 192.168.196.51" 
                               required
                               class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border text-xs sm:text-sm font-mono transition outline-none"
                               :class="ipConflict ? 'border-rose-400 bg-rose-50/40 text-rose-900 focus:ring-2 focus:ring-rose-400' : 'border-slate-200 focus:ring-2 focus:ring-[#D84040]'">
                        
                        <p x-show="ipConflict" class="text-[10px] font-semibold text-rose-600 mt-1 flex items-center gap-1 truncate">
                            <span>⚠️</span>
                            <span>Dipakai oleh: <b class="font-bold underline" x-text="ipConflict"></b></span>
                        </p>
                    </div>

                    <div>
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1">Kapasitas AC (Unit)</label>
                        <input type="number" name="num_ac" x-model="devNumAc" min="1" max="8" class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm font-mono focus:ring-2 focus:ring-[#D84040] outline-none">
                    </div>
                </div>

                <div class="pt-2.5 sm:pt-3 flex items-center justify-end gap-2.5 sm:gap-3 border-t border-slate-100">
                    <button @click="modalNewDevice = false" type="button" class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs uppercase cursor-pointer">Batal</button>
                    <button type="submit" 
                            :disabled="Boolean(ipConflict)" 
                            :class="ipConflict ? 'opacity-40 cursor-not-allowed bg-slate-400' : 'bg-gradient-to-r from-[#D84040] to-[#8E1616] hover:opacity-95 cursor-pointer shadow-md active:scale-95'"
                            class="px-5 sm:px-6 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl text-white font-bold text-xs uppercase transition">
                        Daftarkan Node
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- ========================================================================= -->
@endif

    <!-- MODAL 2: TAMBAH ATURAN JADWAL SHIFT -->
    <!-- ========================================================================= -->
    <div x-show="modalSchedule" 
         x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalSchedule = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-lg w-full shadow-2xl border border-slate-200 space-y-3.5 sm:space-y-4 relative max-h-[82vh] sm:max-h-[88vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 sm:pb-3.5">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl sm:rounded-[20px] bg-[#8E1616]/10 text-[#8E1616] flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        ⏰
                    </div>
                    <div>
                        <h4 class="text-base sm:text-lg font-black text-[#1D1616]">Tambah Jadwal Shift AC</h4>
                        <p class="text-[11px] sm:text-xs text-slate-500">Konfigurasi waktu rotasi hardware RTC DS3231</p>
                    </div>
                </div>
                <button @click="modalSchedule = false" class="text-slate-400 hover:text-[#8E1616] text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form action="{{ route('schedules.store') }}" method="POST" class="space-y-3 sm:space-y-3.5">
                @csrf
                <input type="hidden" name="device_id" value="{{ $selectedDeviceId }}">

                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Label / Nama Shift *</label>
                    <input type="text" name="label" required placeholder="Contoh: Shift Siang (AC 1)" class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#8E1616] outline-none">
                </div>

                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Target Unit AC *</label>
                    <select name="target_ac" class="w-full px-3.5 py-2.5 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm bg-white focus:ring-2 focus:ring-[#8E1616] outline-none cursor-pointer">
                        @foreach($unitData ?? [] as $acNum => $unit)
                            <option value="{{ $acNum }}">{{ $unit['name'] }} (Pin GPIO {{ $unit['gpio'] }})</option>
                        @endforeach
                        <option value="all">Seluruh Unit AC Sekaligus (Semua {{ count($unitData ?? []) }} Unit)</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2.5 sm:gap-3.5">
                    <div>
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Jam Mulai (WIB) *</label>
                        <input type="time" name="start_time" required value="06:00" class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#8E1616] outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Jam Berakhir (WIB) *</label>
                        <input type="time" name="end_time" required value="18:00" class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#8E1616] outline-none">
                    </div>
                </div>

                <div class="pt-2.5 sm:pt-3 flex items-center justify-end gap-2.5 sm:gap-3 border-t border-slate-100">
                    <button @click="modalSchedule = false" type="button" class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs uppercase cursor-pointer">Batal</button>
                    <button type="submit" class="px-5 sm:px-6 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-gradient-to-r from-[#8E1616] to-[#1D1616] text-white font-bold text-xs uppercase shadow-md hover:opacity-95 cursor-pointer">Simpan Jadwal</button>
                </div>
            </form>
        </div>
    </div>


    <!-- ========================================================================= -->
    <!-- MODAL 3: EDIT ATURAN JADWAL SHIFT & ROTASI -->
    <!-- ========================================================================= -->
    <div x-show="modalEditSchedule" 
         x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalEditSchedule = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-lg w-full shadow-2xl border border-slate-200 space-y-3.5 sm:space-y-4 relative max-h-[82vh] sm:max-h-[88vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 sm:pb-3.5">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl sm:rounded-[20px] bg-amber-100 text-amber-800 flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        ✏️
                    </div>
                    <div>
                        <h4 class="text-base sm:text-lg font-black text-[#1D1616]">Edit Jadwal Shift AC</h4>
                        <p class="text-[11px] sm:text-xs text-slate-500">Ubah jam mulai, jam berakhir, atau unit target</p>
                    </div>
                </div>
                <button @click="modalEditSchedule = false" class="text-slate-400 hover:text-amber-800 text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form :action="'/schedules/' + editScheduleData.id" method="POST" class="space-y-3 sm:space-y-3.5">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Label / Nama Shift *</label>
                    <input type="text" 
                           name="label" 
                           x-model="editScheduleData.label"
                           required 
                           placeholder="Contoh: Shift Siang (AC 1)" 
                           class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#8E1616] outline-none">
                </div>

                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Target Unit AC *</label>
                    <select name="target_ac" 
                            x-model="editScheduleData.target_ac"
                            class="w-full px-3.5 py-2.5 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm bg-white focus:ring-2 focus:ring-[#8E1616] outline-none cursor-pointer">
                        @foreach($unitData ?? [] as $acNum => $unit)
                            <option value="{{ $acNum }}">{{ $unit['name'] }} (Pin GPIO {{ $unit['gpio'] }})</option>
                        @endforeach
                        <option value="all">Seluruh Unit AC Sekaligus (Semua {{ count($unitData ?? []) }} Unit)</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2.5 sm:gap-3.5">
                    <div>
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Jam Mulai (WIB) *</label>
                        <input type="time" 
                               name="start_time" 
                               x-model="editScheduleData.start_time"
                               required 
                               class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm font-mono focus:ring-2 focus:ring-[#8E1616] outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Jam Berakhir (WIB) *</label>
                        <input type="time" 
                               name="end_time" 
                               x-model="editScheduleData.end_time"
                               required 
                               class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm font-mono focus:ring-2 focus:ring-[#8E1616] outline-none">
                    </div>
                </div>

                <div class="pt-0.5">
                    <label class="flex items-center gap-2.5 sm:gap-3 cursor-pointer p-2.5 sm:p-3.5 rounded-xl sm:rounded-2xl border transition"
                           :class="editScheduleData.is_active ? 'bg-emerald-50/70 border-emerald-300' : 'bg-slate-50 border-slate-200'">
                        <input type="checkbox" name="is_active" value="1" x-model="editScheduleData.is_active" class="w-4 h-4 sm:w-5 sm:h-5 accent-emerald-600 rounded cursor-pointer">
                        <div>
                            <span class="text-[11px] sm:text-xs font-black text-[#1D1616] block">Status Aturan Jadwal</span>
                            <span class="text-[10px] font-medium" :class="editScheduleData.is_active ? 'text-emerald-700 font-bold' : 'text-slate-500'" x-text="editScheduleData.is_active ? '🟢 Jadwal AKTIF otomatis' : '⚪ Jadwal NON-AKTIF (Dijeda)'"></span>
                        </div>
                    </label>
                </div>

                <div class="pt-2.5 sm:pt-3 flex items-center justify-end gap-2.5 sm:gap-3 border-t border-slate-100">
                    <button @click="modalEditSchedule = false" type="button" class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs uppercase cursor-pointer">Batal</button>
                    <button type="submit" class="px-5 sm:px-6 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-gradient-to-r from-amber-600 to-amber-700 text-white font-bold text-xs uppercase shadow-md hover:opacity-95 cursor-pointer">Perbarui Jadwal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
@if($isSuperAdmin)
    <!-- MODAL 4: EDIT INFORMASI PERANGKAT IOT -->
    <!-- ========================================================================= -->
    <div x-show="modalEditDevice" 
         x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalEditDevice = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-lg w-full shadow-2xl border border-slate-200 space-y-3.5 sm:space-y-4 relative max-h-[82vh] sm:max-h-[88vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 sm:pb-3.5">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl sm:rounded-[20px] bg-amber-100 text-amber-800 flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        ✏️
                    </div>
                    <div>
                        <h4 class="text-base sm:text-lg font-black text-[#1D1616]">Edit Perangkat IoT</h4>
                        <p class="text-[11px] sm:text-xs text-slate-500">Perbarui konfigurasi ruangan & template</p>
                    </div>
                </div>
                <button @click="modalEditDevice = false" class="text-slate-400 hover:text-amber-800 text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form :action="'/devices/' + editDeviceData.id" method="POST" class="space-y-3 sm:space-y-3.5"
                  x-data="{
                      templatesMap: {
                          @foreach($templates as $t)
                          '{{ $t->id }}': {
                              numAc: {{ count(collect($t->datastreams ?? [])->filter(fn($ds) => str_starts_with($ds['pin'], 'V') && ($ds['type'] ?? '') === 'Integer' && ($ds['max'] ?? 1) == 1 && !str_contains(strtolower($ds['name'] ?? ''), 'total') && !str_contains(strtolower($ds['name'] ?? ''), 'turbo') && !str_contains(strtolower($ds['name'] ?? ''), 'priority') && !str_contains(strtolower($ds['name'] ?? ''), 'arus') && !str_contains(strtolower($ds['name'] ?? ''), 'ampere'))) ?: 1 }}
                          },
                          @endforeach
                      }
                  }"
                  x-init="$watch('editDeviceData.template_id', val => { if(templatesMap[val]) { editDeviceData.num_ac = templatesMap[val].numAc; } })">
                @csrf
                @method('PUT')
                
                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Pilih Template Blueprint *</label>
                    <select name="template_id" x-model="editDeviceData.template_id" required class="w-full px-3.5 py-2.5 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm bg-white focus:ring-2 focus:ring-[#D84040] outline-none cursor-pointer">
                        @foreach($templates as $tmpl)
                        <option value="{{ $tmpl->id }}">{{ $tmpl->icon }} {{ $tmpl->name }} ({{ $tmpl->hardware_type }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Nama Perangkat / Ruangan *</label>
                    <input type="text" 
                           name="name" 
                           x-model="editDeviceData.name" 
                           required 
                           placeholder="Contoh: Ruang Server 2" 
                           class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#D84040] outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3.5">
                    <div>
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Lokasi / Gedung *</label>
                        <input type="text" 
                               name="location" 
                               x-model="editDeviceData.location" 
                               required 
                               placeholder="Contoh: Gedung TIK" 
                               class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#D84040] outline-none">
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider">ID Perangkat</label>
                            <span class="text-[9px] font-bold text-slate-600 bg-slate-100 border border-slate-300 px-1.5 py-0.5 rounded-full flex items-center gap-1">
                                <span>Terkunci</span>
                            </span>
                        </div>
                        <input type="text" 
                               name="device_id" 
                               x-model="editDeviceData.device_id" 
                               readonly
                               class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border-2 border-dashed border-slate-300 text-xs sm:text-sm font-mono uppercase bg-slate-100/90 text-slate-700 font-bold cursor-not-allowed select-all outline-none">
                        <p class="text-[10px] text-slate-400 mt-1">ID MQTT permanen.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3.5">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider">Alamat IP</label>
                            <span class="text-[9px] font-bold text-slate-600 bg-slate-100 border border-slate-300 px-1.5 py-0.5 rounded-full flex items-center gap-1">
                                <span>Terkunci</span>
                            </span>
                        </div>
                        <input type="text" 
                               name="ip_address" 
                               x-model="editDeviceData.ip_address" 
                               readonly
                               class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border-2 border-dashed border-slate-300 text-xs sm:text-sm font-mono uppercase bg-slate-100/90 text-slate-700 font-bold cursor-not-allowed select-all outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1">Kapasitas AC (Unit)</label>
                        <input type="number" 
                               name="num_ac" 
                               x-model="editDeviceData.num_ac" 
                               min="0" 
                               max="8" 
                               class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#D84040] outline-none">
                    </div>
                </div>

                <div class="pt-2.5 sm:pt-3 flex items-center justify-end gap-2.5 sm:gap-3 border-t border-slate-100">
                    <button @click="modalEditDevice = false" type="button" class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs uppercase cursor-pointer">Batal</button>
                    <button type="submit" class="px-5 sm:px-6 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-gradient-to-r from-amber-600 to-amber-700 text-white font-bold text-xs uppercase shadow-md hover:opacity-95 cursor-pointer">Perbarui Perangkat</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 5: PANDUAN CEPAT SETUP & SALIN PERINTAH AUTO-START RASPBERRY PI -->
    <!-- ========================================================================= -->
    <div x-show="modalRpiSetup" 
         x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalRpiSetup = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-lg w-full shadow-2xl border border-slate-200 space-y-3.5 sm:space-y-4 relative max-h-[82vh] sm:max-h-[88vh] overflow-y-auto">
            
            <!-- HEADER MODAL -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 sm:pb-3.5">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl sm:rounded-[20px] bg-amber-100 text-amber-800 flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        ⚡
                    </div>
                    <div>
                        <h4 class="text-base sm:text-lg font-black text-[#1D1616]" x-text="'Setup Node: ' + rpiSetupData.name"></h4>
                        <p class="text-[11px] sm:text-xs text-slate-500 font-semibold flex items-center gap-1.5">
                            <span>ID Perangkat:</span>
                            <code class="text-amber-800 font-mono font-bold bg-amber-50 border border-amber-200 px-1.5 py-0.2 rounded text-[10.5px]" x-text="rpiSetupData.device_id"></code>
                        </p>
                    </div>
                </div>
                <button @click="modalRpiSetup = false" class="text-slate-400 hover:text-amber-800 text-2xl font-bold cursor-pointer transition">&times;</button>
            </div>

            <!-- FORM & SETUP CONTENT -->
            <div class="space-y-3 sm:space-y-3.5">
                <!-- 1. PENGATURAN ALAMAT IP HOST SERVER (LAN / CLOUD) -->
                <div>
                    <div class="flex items-center justify-between mb-1 sm:mb-1.5">
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider">
                            IP Server Host (Komputer Dashboard) *
                        </label>
                        <span class="text-[10px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200 font-mono">Port :8000</span>
                    </div>
                    <input type="text" 
                           x-model="rpiSetupData.server_host" 
                           @input="updateRpiSetupUrls()"
                           placeholder="Contoh: 192.168.196.236" 
                           class="w-full px-3.5 py-2 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm font-mono font-bold text-slate-800 bg-slate-50/50 focus:bg-white focus:ring-2 focus:ring-amber-500 outline-none transition">
                    <p class="text-[10.5px] text-slate-500 font-medium leading-tight mt-1.5 flex items-start gap-1">
                        <span>💡</span>
                        <span>Dapat diedit jika ganti IP LAN atau sudah dideploy ke domain server. Perintah cURL otomatis menyesuaikan.</span>
                    </p>
                </div>

                <!-- STEP 1 (KHUSUS RASPBERRY PI BARU): INSTALL DEPENDENSI & HARDWARE I2C -->
                <div class="bg-amber-50 rounded-2xl p-3 sm:p-3.5 border border-amber-200/80 space-y-2 text-slate-800">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[11px] sm:text-xs font-black uppercase tracking-wider text-amber-900 flex items-center gap-1.5">
                            <span>📦 1. Pasang Dependensi & I2C (Khusus Raspi Baru)</span>
                        </span>
                        <button @click="copyDepCommand()" 
                                type="button" 
                                class="px-2.5 py-1 rounded-xl text-[10px] font-bold uppercase tracking-wider transition flex items-center gap-1 cursor-pointer active:scale-95 shrink-0 bg-white border border-amber-300 hover:bg-amber-100 text-amber-900 shadow-2xs">
                            <span x-text="copyDepSuccess ? '✓' : '📋'"></span>
                            <span x-text="copyDepSuccess ? 'Tersalin!' : 'Salin Perintah'"></span>
                        </button>
                    </div>
                    <div class="bg-black/90 rounded-xl p-2 sm:p-2.5 border border-amber-300/40 font-mono text-[9.5px] sm:text-[10.5px] text-amber-300 break-all select-all"
                         x-text="rpiSetupData.dep_command">
                    </div>
                    <p class="text-[10px] text-slate-500 leading-tight">
                        💡 Jalankan sekali pada Raspberry Pi baru untuk mengaktifkan antarmuka I2C dan menginstall library sensor (paho-mqtt, adafruit ads1115 & ds3231).
                    </p>
                </div>

                <!-- 2. PERINTAH 1-KLIK TERMINAL (AUTO-START) -->
                <div class="bg-slate-900 rounded-2xl p-3.5 sm:p-4 border border-slate-800 space-y-2.5 text-white shadow-md">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[11px] sm:text-xs font-black uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                            <span>⚡ Perintah Terminal (Auto-Start)</span>
                        </span>
                        <button @click="copyCommand()" 
                                type="button" 
                                class="px-3 py-1 rounded-xl text-[10.5px] font-bold uppercase tracking-wider transition flex items-center gap-1 cursor-pointer active:scale-95 shrink-0 shadow-xs"
                                :class="copySuccess ? 'bg-emerald-500 text-white' : 'bg-amber-500 hover:bg-amber-600 text-slate-950 font-black'">
                            <span x-text="copySuccess ? '✓' : '📋'"></span>
                            <span x-text="copySuccess ? 'Tersalin!' : 'Salin Perintah'"></span>
                        </button>
                    </div>
                    
                    <div class="bg-black/80 rounded-xl p-2.5 sm:p-3 border border-white/10 font-mono text-[10px] sm:text-[10.5px] text-emerald-400 break-all select-all leading-relaxed max-h-20 overflow-y-auto" 
                         x-text="rpiSetupData.command">
                    </div>

                    <p class="text-[10px] text-slate-400 leading-tight">
                        💡 Jalankan di terminal Raspberry Pi. Skrip otomatis diunduh dan aktif saat boot.
                    </p>
                </div>

                <!-- 3. CEK LOG & OPSI MANUAL (2 KOLOM KOMPAK) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3 text-xs">
                    <!-- Kolom 1: Cek Log -->
                    <div class="bg-slate-50 rounded-xl sm:rounded-2xl p-2.5 sm:p-3 border border-slate-200 space-y-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 block">Periksa Log Real-Time</span>
                        <code class="font-mono text-[10px] sm:text-[10.5px] font-bold text-slate-800 block bg-white px-2 py-1 rounded-lg border border-slate-200 select-all truncate">tail -f ~/node.log</code>
                    </div>

                    <!-- Kolom 2: Unduh Manual -->
                    <div class="bg-slate-50 rounded-xl sm:rounded-2xl p-2.5 sm:p-3 border border-slate-200 flex flex-col justify-between">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 block">Transfer Manual (SCP/USB)</span>
                        <a :href="rpiSetupData.download_url" 
                           class="mt-1 w-full text-center py-1 rounded-lg bg-white hover:bg-slate-100 text-slate-700 font-bold text-[10.5px] border border-slate-200 transition flex items-center justify-center gap-1">
                            <span>📥 Unduh .py</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- FOOTER AKSI (SEIRAMA DENGAN EDIT PERANGKAT) -->
            <div class="pt-2.5 sm:pt-3 flex items-center justify-end gap-2.5 sm:gap-3 border-t border-slate-100">
                <button @click="modalRpiSetup = false" 
                        type="button" 
                        class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs uppercase cursor-pointer transition">
                    Tutup
                </button>
                <button @click="copyCommand()" 
                        type="button" 
                        class="px-5 sm:px-6 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-gradient-to-r from-amber-600 to-amber-700 text-white font-bold text-xs uppercase shadow-md hover:opacity-95 cursor-pointer transition flex items-center gap-1.5 active:scale-95">
                    <span x-text="copySuccess ? '✓ Perintah Tersalin' : '📋 Salin Perintah'"></span>
                </button>
            </div>
        </div>
    </div>

@endif

    <!-- ================= MODAL PENGAJUAN PERANGKAT / AKSES (OPERATOR) ================= -->
    <div x-show="modalRequestDevice" 
         x-cloak 
         class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalRequestDevice = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-lg w-full shadow-2xl border border-slate-200 space-y-4 relative max-h-[90vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3.5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-rose-100 text-[#8E1616] flex items-center justify-center font-black text-xl shrink-0 shadow-xs">
                        📋
                    </div>
                    <div>
                        <h4 class="text-base font-black text-[#1D1616]">Pengajuan Perangkat / Akses Ruangan</h4>
                        <p class="text-[11px] text-slate-500">Tiket permohonan ke Super Admin (PINDAD-IOT-2026)</p>
                    </div>
                </div>
                <button @click="modalRequestDevice = false" type="button" class="text-slate-400 hover:text-rose-700 text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form action="{{ route('operator.device-requests.store') }}" method="POST" class="space-y-3.5">
                @csrf

                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Jenis Pengajuan: <span class="text-rose-600">*</span></label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 hover:border-[#8E1616] cursor-pointer transition has-checked:border-[#8E1616] has-checked:bg-rose-50/50">
                            <input type="radio" name="request_type" value="new_device" checked class="text-[#8E1616] focus:ring-[#8E1616]">
                            <div class="text-[11px] leading-tight">
                                <span class="font-bold text-[#1D1616] block">Pengadaan Baru</span>
                                <span class="text-[10px] text-slate-400">Pemasangan alat IoT baru</span>
                            </div>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 hover:border-[#8E1616] cursor-pointer transition has-checked:border-[#8E1616] has-checked:bg-rose-50/50">
                            <input type="radio" name="request_type" value="access_existing" class="text-[#8E1616] focus:ring-[#8E1616]">
                            <div class="text-[11px] leading-tight">
                                <span class="font-bold text-[#1D1616] block">Izin Akses Ruangan</span>
                                <span class="text-[10px] text-slate-400">Ruangan sudah terpasang</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Nama Ruangan / Perangkat AC: <span class="text-rose-600">*</span></label>
                    <input type="text" name="room_name" required class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-200 focus:border-[#8E1616] focus:ring-1 focus:ring-[#8E1616] outline-none" placeholder="Contoh: Ruang Server B / Lab Komputer">
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Lokasi / Gedung: <span class="text-rose-600">*</span></label>
                    <input type="text" name="location" required class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-200 focus:border-[#8E1616] focus:ring-1 focus:ring-[#8E1616] outline-none" placeholder="Contoh: Gedung Koperasi Lt. 2 / Gedung Direktorat">
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Alasan & Keterangan Kebutuhan:</label>
                    <textarea name="notes" rows="2" class="w-full px-3.5 py-2 text-xs rounded-xl border border-slate-200 focus:border-[#8E1616] focus:ring-1 focus:ring-[#8E1616] outline-none resize-none" placeholder="Contoh: Ruangan server baru membutuhkan pemantauan suhu otomatis dan rotasi jadwal AC 24 jam."></textarea>
                </div>

                <div class="p-3 bg-rose-50/70 rounded-xl border border-rose-100 text-[11px] text-slate-600 leading-relaxed">
                    💡 <strong>Informasi:</strong> Pengajuan Anda akan masuk ke akun Super Administrator (PINDAD-IOT-2026) untuk ditinjau dan dicek secara langsung di lapangan sebelum disetujui.
                </div>

                <div class="pt-2 flex items-center justify-end gap-2.5 border-t border-slate-100">
                    <button @click="modalRequestDevice = false" type="button" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">Batal</button>
                    <button type="submit" class="px-5 py-2.5 text-xs font-bold bg-gradient-to-r from-[#8E1616] to-[#1D1616] text-white rounded-xl shadow-md hover:opacity-95 cursor-pointer flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        <span>Kirim Pengajuan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
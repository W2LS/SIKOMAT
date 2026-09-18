<!-- ================= MODUL 2: DEVELOPER ZONE (PINDAD HARDWARE TEMPLATES & DATASTREAMS CONSOLE) ================= -->
<div class="space-y-6 pb-20" x-data="{ 
    selectedTemplateId: '{{ session('selected_template_id') }}' || localStorage.getItem('pindad_selected_template_id') || '{{ (string)($templates->first()?->_id ?? $templates->first()?->id ?? '') }}',
    modalNewTemplate: false,
    modalEditTemplate: false,
    modalNewDatastream: false,
    modalPresetTemplate: false,
    modalImportTemplate: false,
    selectedPreset: 'relay_2ch',
    importFileName: '',
    editTemplate: { id: '', name: '', hardware_type: '', connection_type: '', icon: '', description: '' }
}" x-init="
    if ('{{ session('selected_template_id') }}') {
        selectedTemplateId = '{{ session('selected_template_id') }}';
        localStorage.setItem('pindad_selected_template_id', '{{ session('selected_template_id') }}');
    } else {
        const saved = localStorage.getItem('pindad_selected_template_id');
        if (saved) selectedTemplateId = saved;
    }
    $watch('selectedTemplateId', val => {
        if (val) localStorage.setItem('pindad_selected_template_id', val);
    });
">

    <!-- 1. PAGE HEADER & ACTION BUTTONS -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#8E1616]/20 pb-4">
        <div>
            <span class="text-[11px] font-extrabold uppercase tracking-widest text-[#8E1616] flex items-center gap-1.5">
                <span>🛠️</span>
                <span>DEVELOPER ZONE • PINDAD TEMPLATE ENGINE</span>
            </span>
            <h2 class="text-2xl sm:text-3xl font-black text-[#1D1616] tracking-tight mt-0.5">
                Manajemen Template & Datastreams
            </h2>
            <p class="text-xs font-semibold text-slate-500 mt-1">
                Rancang blueprint perangkat keras dan konfigurasikan saluran Virtual Pin (V0 - V255) PINDAD Datastreams Standard.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <!-- 1-Click Relay Presets -->
            <button @click="modalPresetTemplate = true" 
                    type="button"
                    class="bg-amber-500 hover:bg-amber-600 text-slate-950 rounded-[22px] text-xs font-black uppercase tracking-wider py-3 px-4 shadow-md shadow-amber-500/20 transition flex items-center gap-1.5 cursor-pointer active:scale-95">
                <span>⚡</span>
                <span>Preset Modul Relay</span>
            </button>

            <!-- Import Blueprint JSON -->
            <button @click="modalImportTemplate = true" 
                    type="button"
                    class="bg-slate-900 hover:bg-black text-white rounded-[22px] text-xs font-black uppercase tracking-wider py-3 px-4 shadow-md transition flex items-center gap-1.5 cursor-pointer active:scale-95 border border-slate-800">
                <span>📤</span>
                <span>Import (.json)</span>
            </button>

            <!-- Manual Custom Template -->
            <button @click="modalNewTemplate = true" 
                    type="button"
                    class="bg-[#D84040] hover:bg-[#8E1616] text-white rounded-[22px] text-xs font-black uppercase tracking-wider py-3 px-4.5 shadow-lg shadow-[#D84040]/30 transition flex items-center gap-1.5 cursor-pointer active:scale-95">
                <span class="text-base leading-none font-black">+</span>
                <span>Buat Kustom</span>
            </button>
        </div>
    </div>

    <!-- 2. TEMPLATE SELECTOR TABS & BLUEPRINT DETAILS -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- LEFT: TEMPLATES LIST -->
        <div class="lg:col-span-4 xl:col-span-3 space-y-3">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 px-1">Pilih Blueprint Template</h3>
            
            @foreach($templates as $tmpl)
            <div @click="selectedTemplateId = '{{ $tmpl->id }}'; localStorage.setItem('pindad_selected_template_id', '{{ $tmpl->id }}')"
                 class="p-4 rounded-[24px] border-2 cursor-pointer transition-all duration-200 flex items-center justify-between gap-3 shadow-xs group"
                 :class="selectedTemplateId === '{{ $tmpl->id }}' ? 'bg-[#1D1616] border-[#8E1616] text-white shadow-md' : 'bg-white border-slate-100 hover:border-slate-300 text-[#1D1616]'">
                
                <div class="flex items-center gap-3.5 min-w-0 flex-1">
                    <div class="w-10 h-10 rounded-2xl flex items-center justify-center font-black text-xl shrink-0 shadow-2xs"
                         :class="selectedTemplateId === '{{ $tmpl->id }}' ? 'bg-white/10 text-amber-400' : 'bg-rose-50 text-[#8E1616]'">
                        {{ $tmpl->icon ?? '⚡' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <h4 class="font-black text-xs sm:text-sm leading-tight tracking-tight">{{ $tmpl->name }}</h4>
                        <p class="text-[11px] font-medium opacity-70 mt-1 truncate">{{ $tmpl->hardware_type }}</p>
                    </div>
                </div>

                <div class="text-right shrink-0">
                    <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-xl whitespace-nowrap"
                          :class="selectedTemplateId === '{{ $tmpl->id }}' ? 'bg-[#8E1616] text-white' : 'bg-slate-100 text-slate-600'">
                        {{ count($tmpl->datastreams ?? []) }} Pins
                    </span>
                </div>
            </div>
            @endforeach
        </div>

        <!-- RIGHT: ACTIVE TEMPLATE DATASTREAMS CONSOLE -->
        <div class="lg:col-span-8 xl:col-span-9 space-y-6">
            @foreach($templates as $tmpl)
            <div x-show="selectedTemplateId === '{{ $tmpl->id }}'" 
                 x-transition:enter="transition ease-out duration-200 transform opacity-0"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="space-y-6">
                
                <!-- Blueprint Summary Card -->
                <div class="bg-white rounded-[32px] p-6 sm:p-7 shadow-sm border border-[#8E1616]/20 space-y-5">
                    
                    <!-- TOP ROW: TITLE & ALL ACTION BUTTONS -->
                    <div class="flex flex-col 2xl:flex-row 2xl:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-200 text-[#8E1616] font-black text-2xl flex items-center justify-center shrink-0 shadow-xs">
                                {{ $tmpl->icon ?? '⚡' }}
                            </div>
                            <div class="min-w-0 space-y-0.5">
                                <h3 class="text-lg sm:text-xl font-black text-[#1D1616] tracking-tight leading-snug">
                                    {{ $tmpl->name }}
                                </h3>
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="font-bold text-[#8E1616]">{{ count($tmpl->datastreams ?? []) }} Virtual Pins Terdaftar</span>
                                    <span class="text-slate-300">•</span>
                                    <span class="text-slate-500 font-medium">{{ $tmpl->hardware_type }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- ACTION BUTTONS: Neat Horizontal Bar -->
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <!-- Add Datastream -->
                            <button @click="modalNewDatastream = true"
                                    type="button"
                                    class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-[#D84040] to-[#8E1616] text-white font-black text-xs uppercase tracking-wider shadow-md hover:opacity-95 transition cursor-pointer flex items-center gap-1.5 active:scale-95">
                                <span class="text-sm font-black">+</span>
                                <span>Add Datastream</span>
                            </button>

                            <!-- Export JSON Button -->
                            <a href="{{ route('templates.export', $tmpl->id) }}" 
                               class="px-3.5 py-2.5 rounded-xl bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 transition cursor-pointer flex items-center gap-1.5 text-xs font-bold shadow-xs active:scale-95" 
                               title="Unduh / Backup Blueprint Template ke File JSON">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>Export (.json)</span>
                            </a>

                            <!-- Rename / Edit Button -->
                            <button @click="editTemplate = {
                                        id: '{{ $tmpl->id }}',
                                        name: '{{ addslashes($tmpl->name) }}',
                                        hardware_type: '{{ addslashes($tmpl->hardware_type) }}',
                                        connection_type: '{{ addslashes($tmpl->connection_type) }}',
                                        icon: '{{ addslashes($tmpl->icon ?? '⚡') }}',
                                        description: '{{ addslashes($tmpl->description ?? '') }}'
                                    }; modalEditTemplate = true"
                                    type="button"
                                    class="px-3 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition cursor-pointer flex items-center gap-1.5 text-xs font-bold"
                                    title="Rename / Edit Informasi Template">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                <span>Rename</span>
                            </button>

                            <!-- Delete Button -->
                            <form action="{{ route('templates.destroy', $tmpl->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Template {{ $tmpl->name }} beserta seluruh Datastreams-nya?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                        class="px-3 py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 transition cursor-pointer flex items-center gap-1 text-xs font-bold" 
                                        title="Hapus Template Ini">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    <span>Hapus</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- BOTTOM ROW: FULL WIDTH DESCRIPTION & METADATA -->
                    <div class="space-y-3">
                        <p class="text-xs text-slate-600 leading-relaxed">
                            {{ $tmpl->description }}
                        </p>

                        <div class="flex flex-wrap items-center gap-2.5 text-xs font-bold text-slate-700">
                            <span class="bg-slate-50 px-3.5 py-1.5 rounded-xl border border-slate-200 flex items-center gap-2">
                                <span>📟</span>
                                <span>Hardware: <b class="text-[#1D1616]">{{ $tmpl->hardware_type }}</b></span>
                            </span>
                            <span class="bg-slate-50 px-3.5 py-1.5 rounded-xl border border-slate-200 flex items-center gap-2">
                                <span>🌐</span>
                                <span>Koneksi: <b class="text-[#1D1616]">{{ $tmpl->connection_type }}</b></span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Datastreams Table (Virtual Pins) -->
                <div class="bg-white rounded-[28px] sm:rounded-[32px] p-4 sm:p-6 shadow-sm border border-[#8E1616]/20 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3.5">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h4 class="font-black text-sm text-[#1D1616]">Daftar Virtual Pins (Datastreams)</h4>
                            <span class="text-[10px] font-black text-[#8E1616] bg-[#8E1616]/10 px-2.5 py-0.5 rounded-full whitespace-nowrap">{{ count($tmpl->datastreams ?? []) }} Terdaftar</span>
                        </div>
                        <span class="text-[11px] sm:text-xs text-slate-400 font-mono tracking-wide">PINDAD V-Pin Standard</span>
                    </div>

                    <div class="overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
                        <table class="w-full min-w-[620px] text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-400">
                                    <th class="py-3 px-3 whitespace-nowrap">Virtual Pin</th>
                                    <th class="py-3 px-3 min-w-[190px]">Nama Datastream</th>
                                    <th class="py-3 px-3 whitespace-nowrap text-center">Tipe Data</th>
                                    <th class="py-3 px-3 whitespace-nowrap text-center">Rentang Nilai</th>
                                    <th class="py-3 px-3 whitespace-nowrap text-center">Default</th>
                                    <th class="py-3 px-3 whitespace-nowrap text-center">Satuan</th>
                                    <th class="py-3 px-3 text-right whitespace-nowrap">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($tmpl->datastreams ?? [] as $ds)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-3.5 px-3 whitespace-nowrap align-middle">
                                        <span class="inline-flex items-center justify-center font-mono font-black text-xs px-2.5 py-1 rounded-lg bg-[#1D1616] text-white shadow-xs">
                                            {{ $ds['pin'] }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-3 min-w-[190px] align-middle">
                                        <div class="font-bold text-[#1D1616] text-xs leading-snug">{{ $ds['name'] }}</div>
                                        @if(!empty($ds['desc']) && $ds['desc'] !== '-')
                                            <div class="text-[10px] text-slate-400 font-normal leading-normal mt-0.5">{{ $ds['desc'] }}</div>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-3 whitespace-nowrap text-center align-middle">
                                        <span class="inline-block px-2.5 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wide {{ $ds['type'] === 'Integer' ? 'bg-blue-50 text-blue-700 border border-blue-100' : ($ds['type'] === 'Double' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-purple-50 text-purple-700 border border-purple-100') }}">
                                            {{ $ds['type'] }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-3 font-mono font-semibold text-slate-600 whitespace-nowrap text-center align-middle">
                                        {{ $ds['min'] ?? 0 }} &ndash; {{ $ds['max'] ?? 1 }}
                                    </td>
                                    <td class="py-3.5 px-3 font-mono font-bold text-slate-700 whitespace-nowrap text-center align-middle">
                                        <span class="bg-slate-100 px-2.5 py-0.5 rounded text-xs border border-slate-200 inline-block min-w-[24px] text-center">{{ $ds['default_value'] ?? ($ds['min'] ?? 0) }}</span>
                                    </td>
                                    <td class="py-3.5 px-3 font-bold text-slate-700 whitespace-nowrap text-center align-middle">
                                        {{ !empty($ds['unit']) ? $ds['unit'] : '-' }}
                                    </td>
                                    <td class="py-3.5 px-3 text-right whitespace-nowrap align-middle">
                                        <form action="{{ route('templates.deleteDatastream', ['id' => $tmpl->id, 'pin' => $ds['pin']]) }}" method="POST" onsubmit="return confirm('Hapus Datastream {{ $ds['pin'] }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 transition cursor-pointer inline-flex items-center justify-center shadow-2xs" title="Hapus Datastream">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="py-12 text-center">
                                        <div class="space-y-2 max-w-sm mx-auto">
                                            <span class="text-3xl block">📋</span>
                                            <span class="font-bold text-slate-700 text-xs block">Belum ada Virtual Pin (Datastream) terdaftar</span>
                                            <span class="text-[11px] text-slate-400 block leading-relaxed">Template ini masih kosong. Klik tombol <b class="text-[#D84040]">+ Add Datastream</b> di atas untuk menambahkan saluran pin baru.</span>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>


    <!-- ================= MODAL 1: TAMBAH TEMPLATE BARU ================= -->
    <div x-show="modalNewTemplate" 
         x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalNewTemplate = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-lg w-full shadow-2xl border border-slate-200 space-y-3.5 sm:space-y-4 relative max-h-[82vh] sm:max-h-[88vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 sm:pb-3.5">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl sm:rounded-[20px] bg-[#D84040]/10 text-[#D84040] flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        🛠️
                    </div>
                    <div>
                        <h4 class="text-base sm:text-lg font-black text-[#1D1616]">Buat Device Template Baru</h4>
                        <p class="text-[11px] sm:text-xs text-slate-500">Rancang blueprint IoT baru PT PINDAD</p>
                    </div>
                </div>
                <button @click="modalNewTemplate = false" class="text-slate-400 hover:text-[#D84040] text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form action="{{ route('templates.store') }}" method="POST" class="space-y-3 sm:space-y-3.5">
                @csrf
                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Nama Template *</label>
                    <input type="text" name="name" required placeholder="Contoh: Smart Sensor Suhu Server Rack" class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#D84040] outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3.5">
                    <div>
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Tipe Hardware *</label>
                        <select name="hardware_type" class="w-full px-3.5 py-2.5 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm bg-white focus:ring-2 focus:ring-[#D84040] outline-none">
                            <option value="Raspberry Pi 3B+">Raspberry Pi 3B+</option>
                            <option value="Raspberry Pi 4 Model B">Raspberry Pi 4 Model B</option>
                            <option value="ESP32 Dual-Core IoT">ESP32 Dual-Core IoT</option>
                            <option value="Industrial PLC Gateway">Industrial PLC Gateway</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Tipe Koneksi *</label>
                        <select name="connection_type" class="w-full px-3.5 py-2.5 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm bg-white focus:ring-2 focus:ring-[#D84040] outline-none cursor-pointer">
                            <option value="Ethernet LAN">Ethernet LAN (Kabel RJ45)</option>
                            <option value="WiFi (IEEE 802.11 b/g/n)">WiFi (IEEE 802.11 b/g/n)</option>
                            <option value="MQTT Broker (TCP 1883)">MQTT Broker Lokal (TCP 1883)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Ikon Representasi</label>
                    <input type="text" name="icon" value="⚡" class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#D84040] outline-none">
                </div>

                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Deskripsi Template</label>
                    <textarea name="description" rows="2" placeholder="Tuliskan spesifikasi umum blueprint..." class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#D84040] outline-none"></textarea>
                </div>

                <div class="pt-2.5 sm:pt-3 flex items-center justify-end gap-2.5 sm:gap-3 border-t border-slate-100">
                    <button @click="modalNewTemplate = false" type="button" class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs uppercase cursor-pointer">Batal</button>
                    <button type="submit" class="px-5 sm:px-6 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-gradient-to-r from-[#D84040] to-[#8E1616] text-white font-bold text-xs uppercase shadow-md hover:opacity-95 cursor-pointer">Buat Template</button>
                </div>
            </form>
        </div>
    </div>


    <!-- ================= MODAL 2: EDIT / RENAME TEMPLATE ================= -->
    <div x-show="modalEditTemplate" 
         x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalEditTemplate = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-lg w-full shadow-2xl border border-slate-200 space-y-3.5 sm:space-y-4 relative max-h-[82vh] sm:max-h-[88vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 sm:pb-3.5">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl sm:rounded-[20px] bg-[#8E1616]/10 text-[#8E1616] flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        ✏️
                    </div>
                    <div>
                        <h4 class="text-base sm:text-lg font-black text-[#1D1616]">Rename & Edit Template</h4>
                        <p class="text-[11px] sm:text-xs text-slate-500">Perbarui nama dan konfigurasi blueprint</p>
                    </div>
                </div>
                <button @click="modalEditTemplate = false" class="text-slate-400 hover:text-[#8E1616] text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form :action="'/templates/' + editTemplate.id" method="POST" class="space-y-3 sm:space-y-3.5">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Nama Template *</label>
                    <input type="text" name="name" x-model="editTemplate.name" required class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#8E1616] outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3.5">
                    <div>
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Tipe Hardware *</label>
                        <select name="hardware_type" x-model="editTemplate.hardware_type" class="w-full px-3.5 py-2.5 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm bg-white focus:ring-2 focus:ring-[#8E1616] outline-none">
                            <option value="Raspberry Pi 3B+">Raspberry Pi 3B+</option>
                            <option value="Raspberry Pi 4 Model B">Raspberry Pi 4 Model B</option>
                            <option value="ESP32 Dual-Core IoT">ESP32 Dual-Core IoT</option>
                            <option value="Industrial PLC Gateway">Industrial PLC Gateway</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Tipe Koneksi *</label>
                        <select name="connection_type" x-model="editTemplate.connection_type" class="w-full px-3.5 py-2.5 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm bg-white focus:ring-2 focus:ring-[#8E1616] outline-none cursor-pointer">
                            <option value="Ethernet LAN">Ethernet LAN (Kabel RJ45)</option>
                            <option value="WiFi (IEEE 802.11 b/g/n)">WiFi (IEEE 802.11 b/g/n)</option>
                            <option value="MQTT Broker (TCP 1883)">MQTT Broker Lokal (TCP 1883)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Ikon</label>
                    <input type="text" name="icon" x-model="editTemplate.icon" class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#8E1616] outline-none">
                </div>

                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Deskripsi Template</label>
                    <textarea name="description" x-model="editTemplate.description" rows="2" class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#8E1616] outline-none"></textarea>
                </div>

                <div class="pt-2.5 sm:pt-3 flex items-center justify-end gap-2.5 sm:gap-3 border-t border-slate-100">
                    <button @click="modalEditTemplate = false" type="button" class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs uppercase cursor-pointer">Batal</button>
                    <button type="submit" class="px-5 sm:px-6 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-gradient-to-r from-[#8E1616] to-[#1D1616] text-white font-bold text-xs uppercase shadow-md hover:opacity-95 cursor-pointer">Perbarui Template</button>
                </div>
            </form>
        </div>
    </div>


    <!-- ================= MODAL 3: TAMBAH DATASTREAM (VIRTUAL PIN) ================= -->
    <div x-show="modalNewDatastream" 
         x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalNewDatastream = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-lg w-full shadow-2xl border border-slate-200 space-y-3.5 sm:space-y-4 relative max-h-[82vh] sm:max-h-[88vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 sm:pb-3.5">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl sm:rounded-[20px] bg-[#8E1616]/10 text-[#8E1616] flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        📍
                    </div>
                    <div>
                        <h4 class="text-base sm:text-lg font-black text-[#1D1616]">Tambah Datastream Pin</h4>
                        <p class="text-[11px] sm:text-xs text-slate-500">Kaitkan parameter telemetri / kontrol</p>
                    </div>
                </div>
                <button @click="modalNewDatastream = false" class="text-slate-400 hover:text-[#8E1616] text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form :action="'/templates/' + selectedTemplateId + '/datastreams'" method="POST" class="space-y-3 sm:space-y-3.5">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3.5">
                    <div x-data="{ 
                        openPinDropdown: false, 
                        selectedPin: '', 
                        searchPin: '',
                        pins: Array.from({length: 64}, (_, i) => 'V' + i),
                        get filteredPins() {
                            if (!this.searchPin) return this.pins;
                            return this.pins.filter(p => p.toLowerCase().includes(this.searchPin.toLowerCase()));
                        }
                    }" class="relative">
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Virtual Pin *</label>
                        
                        <!-- Hidden input for form submit -->
                        <input type="hidden" name="pin" :value="selectedPin" required>

                        <!-- Trigger Button -->
                        <div @click="openPinDropdown = !openPinDropdown" 
                             @click.away="openPinDropdown = false"
                             class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm font-mono font-bold text-[#1D1616] bg-white focus-within:ring-2 focus-within:ring-[#8E1616] flex items-center justify-between cursor-pointer shadow-2xs hover:border-slate-300 transition">
                            <span :class="selectedPin ? 'text-[#1D1616] font-black' : 'text-slate-400 font-sans font-normal'" x-text="selectedPin ? selectedPin : '-- Pilih Virtual Pin --'"></span>
                            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="openPinDropdown ? 'rotate-180 text-[#8E1616]' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>

                        <!-- Dropdown Panel -->
                        <div x-show="openPinDropdown" 
                             x-cloak
                             x-transition:enter="transition ease-out duration-150 transform opacity-0 scale-95"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             class="absolute left-0 right-0 z-50 mt-1.5 bg-white rounded-2xl shadow-2xl border border-slate-200 p-2 space-y-1.5 max-h-52 overflow-y-auto">
                            
                            <div class="px-1 pt-1 pb-1">
                                <input type="text" 
                                       x-model="searchPin" 
                                       @click.stop
                                       placeholder="🔍 Cari pin (cth: V0, V12)..." 
                                       class="w-full px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-[#8E1616] outline-none">
                            </div>

                            <div class="divide-y divide-slate-50">
                                <template x-for="p in filteredPins" :key="p">
                                    <div @click="selectedPin = p; openPinDropdown = false" 
                                         :class="selectedPin === p ? 'bg-[#8E1616] text-white font-black' : 'text-slate-700 hover:bg-slate-100 font-bold'"
                                         class="px-3 py-2 rounded-xl text-xs font-mono cursor-pointer flex items-center justify-between transition">
                                        <span x-text="p"></span>
                                        <span x-show="selectedPin === p" class="text-[10px]">✓</span>
                                    </div>
                                </template>
                                <div x-show="filteredPins.length === 0" class="py-3 text-center text-xs text-slate-400 font-sans italic">
                                    Pin tidak ditemukan
                                </div>
                            </div>
                        </div>
                    </div>
                    <div x-data="{ 
                        openTypeDropdown: false, 
                        selectedType: 'Integer',
                        types: [
                            { value: 'Integer', label: 'Integer (0, 1, Bulat)', badge: 'INT', color: 'bg-blue-50 text-blue-700' },
                            { value: 'Double', label: 'Double (Float/Desimal)', badge: 'DBL', color: 'bg-emerald-50 text-emerald-700' },
                            { value: 'String', label: 'String (Teks/JSON)', badge: 'STR', color: 'bg-amber-50 text-amber-700' },
                            { value: 'Enum', label: 'Enum (Status Pilihan)', badge: 'ENUM', color: 'bg-purple-50 text-purple-700' }
                        ],
                        get currentLabel() {
                            const found = this.types.find(t => t.value === this.selectedType);
                            return found ? found.label : this.selectedType;
                        }
                    }" class="relative">
                        <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Tipe Data *</label>
                        
                        <!-- Hidden input for form submit -->
                        <input type="hidden" name="type" :value="selectedType" required>

                        <!-- Trigger Button -->
                        <div @click="openTypeDropdown = !openTypeDropdown" 
                             @click.away="openTypeDropdown = false"
                             class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm font-bold text-[#1D1616] bg-white focus-within:ring-2 focus-within:ring-[#8E1616] flex items-center justify-between cursor-pointer shadow-2xs hover:border-slate-300 transition truncate">
                            <span class="truncate pr-2" x-text="currentLabel"></span>
                            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200 shrink-0" :class="openTypeDropdown ? 'rotate-180 text-[#8E1616]' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>

                        <!-- Dropdown Panel -->
                        <div x-show="openTypeDropdown" 
                             x-cloak
                             x-transition:enter="transition ease-out duration-150 transform opacity-0 scale-95"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             class="absolute left-0 right-0 z-50 mt-1.5 bg-white rounded-2xl shadow-2xl border border-slate-200 p-2 space-y-1">
                            <template x-for="t in types" :key="t.value">
                                <div @click="selectedType = t.value; openTypeDropdown = false" 
                                     :class="selectedType === t.value ? 'bg-[#8E1616] text-white font-black' : 'text-slate-700 hover:bg-slate-100 font-bold'"
                                     class="px-3 py-2 rounded-xl text-xs cursor-pointer flex items-center justify-between transition">
                                    <span x-text="t.label" class="truncate pr-2"></span>
                                    <span x-show="selectedType === t.value" class="text-[10px] shrink-0 font-black">✓</span>
                                    <span x-show="selectedType !== t.value" :class="t.color" class="text-[9px] font-black px-1.5 py-0.5 rounded shrink-0 uppercase" x-text="t.badge"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Nama Datastream *</label>
                    <input type="text" name="name" required placeholder="Contoh: Sensor Arus ACS712" class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#8E1616] outline-none">
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 items-end">
                    <div class="flex flex-col justify-end">
                        <label class="block text-[10px] sm:text-[11px] font-black uppercase text-slate-700 tracking-wider mb-1 whitespace-nowrap truncate" title="Batas Nilai Minimum">Min Value</label>
                        <input type="number" step="any" name="min" value="0" class="w-full h-9 sm:h-10 px-3 py-1.5 sm:px-3.5 sm:py-2 rounded-lg sm:rounded-xl border border-slate-200 text-xs sm:text-sm font-mono focus:ring-2 focus:ring-[#8E1616] outline-none">
                    </div>
                    <div class="flex flex-col justify-end">
                        <label class="block text-[10px] sm:text-[11px] font-black uppercase text-slate-700 tracking-wider mb-1 whitespace-nowrap truncate" title="Batas Nilai Maksimum">Max Value</label>
                        <input type="number" step="any" name="max" value="100" class="w-full h-9 sm:h-10 px-3 py-1.5 sm:px-3.5 sm:py-2 rounded-lg sm:rounded-xl border border-slate-200 text-xs sm:text-sm font-mono focus:ring-2 focus:ring-[#8E1616] outline-none">
                    </div>
                    <div class="flex flex-col justify-end">
                        <label class="block text-[10px] sm:text-[11px] font-black uppercase text-slate-700 tracking-wider mb-1 whitespace-nowrap truncate" title="Nilai Awal / Default">Default Value</label>
                        <input type="number" step="any" name="default_value" value="0" class="w-full h-9 sm:h-10 px-3 py-1.5 sm:px-3.5 sm:py-2 rounded-lg sm:rounded-xl border border-slate-200 text-xs sm:text-sm font-mono focus:ring-2 focus:ring-[#8E1616] outline-none">
                    </div>
                    <div class="flex flex-col justify-end">
                        <label class="block text-[10px] sm:text-[11px] font-black uppercase text-slate-700 tracking-wider mb-1 whitespace-nowrap truncate" title="Satuan Unit">Satuan (Unit)</label>
                        <input type="text" name="unit" placeholder="cth: A, W, V, °C" class="w-full h-9 sm:h-10 px-3 py-1.5 sm:px-3.5 sm:py-2 rounded-lg sm:rounded-xl border border-slate-200 text-xs sm:text-sm font-mono focus:ring-2 focus:ring-[#8E1616] outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Keterangan / Fungsi Pin (Opsional)</label>
                    <textarea name="description" rows="2" placeholder="Tuliskan deskripsi pemakaian pin ini..." class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#8E1616] outline-none"></textarea>
                </div>

                <div class="pt-2.5 sm:pt-3 flex items-center justify-end gap-2.5 sm:gap-3 border-t border-slate-100">
                    <button @click="modalNewDatastream = false" type="button" class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs uppercase cursor-pointer">Batal</button>
                    <button type="submit" class="px-5 sm:px-6 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-gradient-to-r from-[#8E1616] to-[#1D1616] text-white font-bold text-xs uppercase shadow-md hover:opacity-95 cursor-pointer">Simpan Datastream</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= MODAL PRESET MODUL RELAY (1-CLICK QUICK BLUEPRINT) ================= -->
    <div x-show="modalPresetTemplate" 
         x-cloak 
         class="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div @click.away="modalPresetTemplate = false" 
             class="bg-white rounded-[36px] w-full max-w-2xl p-6 sm:p-7 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto border border-slate-100">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-800 font-black text-2xl flex items-center justify-center shadow-xs">
                        ⚡
                    </div>
                    <div>
                        <h4 class="text-lg font-black text-[#1D1616]">Preset Cepat Modul Relay Hardware</h4>
                        <p class="text-xs text-slate-500">Pilih tipe modul relay & template langsung terisi otomatis</p>
                    </div>
                </div>
                <button @click="modalPresetTemplate = false" class="text-slate-400 hover:text-[#8E1616] text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form action="{{ route('templates.preset') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="preset_type" :value="selectedPreset">

                <div class="space-y-3">
                    <label class="block text-xs font-black uppercase text-slate-700 tracking-wider">
                        Pilih Modul Relay Industri Yang Digunakan:
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        
                        <!-- 1-Channel -->
                        <div @click="selectedPreset = 'relay_1ch'" 
                             class="p-4 rounded-2xl border-2 cursor-pointer transition-all duration-200 flex flex-col justify-between space-y-2.5"
                             :class="selectedPreset === 'relay_1ch' ? 'bg-amber-50/70 border-amber-500 shadow-md' : 'bg-slate-50 border-slate-200 hover:border-slate-300'">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl">⚡</span>
                                    <span class="font-black text-sm text-slate-900">Relay 1 Channel</span>
                                </div>
                                <span class="w-4 h-4 rounded-full border-2 flex items-center justify-center text-[10px]"
                                      :class="selectedPreset === 'relay_1ch' ? 'border-amber-600 bg-amber-600 text-white font-bold' : 'border-slate-300'">
                                    <span x-show="selectedPreset === 'relay_1ch'">✓</span>
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-600 leading-snug">
                                Kontrol 1 unit AC / Beban Tunggal. Termasuk Pin Saklar V0 & Sensor Arus ACS712 V1.
                            </p>
                            <div class="flex flex-wrap gap-1 text-[10px] font-mono text-slate-500">
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V0: AC 1</span>
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V1: Arus AC1</span>
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V2: Total Arus</span>
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V3: Turbo</span>
                            </div>
                        </div>

                        <!-- 2-Channel (Standard PINDAD) -->
                        <div @click="selectedPreset = 'relay_2ch'" 
                             class="p-4 rounded-2xl border-2 cursor-pointer transition-all duration-200 flex flex-col justify-between space-y-2.5 relative overflow-hidden"
                             :class="selectedPreset === 'relay_2ch' ? 'bg-amber-50/70 border-amber-500 shadow-md' : 'bg-slate-50 border-slate-200 hover:border-slate-300'">
                            <span class="absolute top-0 right-0 bg-[#8E1616] text-white text-[8.5px] font-black uppercase px-2 py-0.5 rounded-bl-lg">STANDAR PINDAD</span>
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl">❄️</span>
                                    <span class="font-black text-sm text-slate-900">Relay 2 Channel</span>
                                </div>
                                <span class="w-4 h-4 rounded-full border-2 flex items-center justify-center text-[10px] mt-3"
                                      :class="selectedPreset === 'relay_2ch' ? 'border-amber-600 bg-amber-600 text-white font-bold' : 'border-slate-300'">
                                    <span x-show="selectedPreset === 'relay_2ch'">✓</span>
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-600 leading-snug">
                                Standar Ruang Server 2 AC dengan rotasi shift RTC DS3231 & dual sensor ACS712.
                            </p>
                            <div class="flex flex-wrap gap-1 text-[10px] font-mono text-slate-500">
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V0, V1: AC 1-2</span>
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V2, V3: Arus</span>
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V4: Total W</span>
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V5: Turbo</span>
                            </div>
                        </div>

                        <!-- 4-Channel -->
                        <div @click="selectedPreset = 'relay_4ch'" 
                             class="p-4 rounded-2xl border-2 cursor-pointer transition-all duration-200 flex flex-col justify-between space-y-2.5"
                             :class="selectedPreset === 'relay_4ch' ? 'bg-amber-50/70 border-amber-500 shadow-md' : 'bg-slate-50 border-slate-200 hover:border-slate-300'">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl">🏢</span>
                                    <span class="font-black text-sm text-slate-900">Relay 4 Channel</span>
                                </div>
                                <span class="w-4 h-4 rounded-full border-2 flex items-center justify-center text-[10px]"
                                      :class="selectedPreset === 'relay_4ch' ? 'border-amber-600 bg-amber-600 text-white font-bold' : 'border-slate-300'">
                                    <span x-show="selectedPreset === 'relay_4ch'">✓</span>
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-600 leading-snug">
                                Kapasitas 4 unit AC / Ruang Data Center dengan monitoring 4 saklar & 4 sensor arus.
                            </p>
                            <div class="flex flex-wrap gap-1 text-[10px] font-mono text-slate-500">
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V0-V3: AC 1-4</span>
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V4-V7: Arus</span>
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V8: Total W</span>
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V9: Turbo</span>
                            </div>
                        </div>

                        <!-- 8-Channel -->
                        <div @click="selectedPreset = 'relay_8ch'" 
                             class="p-4 rounded-2xl border-2 cursor-pointer transition-all duration-200 flex flex-col justify-between space-y-2.5"
                             :class="selectedPreset === 'relay_8ch' ? 'bg-amber-50/70 border-amber-500 shadow-md' : 'bg-slate-50 border-slate-200 hover:border-slate-300'">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl">🏭</span>
                                    <span class="font-black text-sm text-slate-900">Relay 8 Channel</span>
                                </div>
                                <span class="w-4 h-4 rounded-full border-2 flex items-center justify-center text-[10px]"
                                      :class="selectedPreset === 'relay_8ch' ? 'border-amber-600 bg-amber-600 text-white font-bold' : 'border-slate-300'">
                                    <span x-show="selectedPreset === 'relay_8ch'">✓</span>
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-600 leading-snug">
                                Kapasitas penuh 8 unit pendingin pabrik / chiller dengan total 18 Virtual Pins.
                            </p>
                            <div class="flex flex-wrap gap-1 text-[10px] font-mono text-slate-500">
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V0-V7: AC 1-8</span>
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V8-V15: Arus</span>
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V16: Total W</span>
                                <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200">V17: Turbo</span>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button @click="modalPresetTemplate = false" type="button" class="px-5 py-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs uppercase cursor-pointer">Batal</button>
                    <button type="submit" class="px-6 py-2.5 rounded-2xl bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 font-black text-xs uppercase shadow-md hover:opacity-95 cursor-pointer active:scale-95 flex items-center gap-2">
                        <span>🚀 Pasang Preset Ini (1-Klik)</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= MODAL IMPORT TEMPLATE (.JSON) ================= -->
    <div x-show="modalImportTemplate" 
         x-cloak 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div @click.away="modalImportTemplate = false" 
             class="bg-white rounded-[36px] w-full max-w-lg p-6 sm:p-7 shadow-2xl space-y-6 border border-slate-100">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-2xl bg-slate-900 text-white font-black text-2xl flex items-center justify-center shadow-xs">
                        📤
                    </div>
                    <div>
                        <h4 class="text-lg font-black text-[#1D1616]">Import Blueprint Template</h4>
                        <p class="text-xs text-slate-500">Pulihkan template dari file JSON hasil unduhan</p>
                    </div>
                </div>
                <button @click="modalImportTemplate = false" class="text-slate-400 hover:text-[#8E1616] text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form action="{{ route('templates.import') }}" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                @csrf
                
                <div>
                    <label class="block font-black uppercase text-slate-700 tracking-wider mb-2">
                        Pilih File Blueprint JSON (.json) *
                    </label>
                    
                    <div class="border-2 border-dashed border-slate-300 hover:border-slate-400 rounded-3xl p-6 text-center cursor-pointer bg-slate-50 transition relative">
                        <input type="file" 
                               name="template_file" 
                               accept=".json,application/json" 
                               @change="importFileName = $event.target.files[0] ? $event.target.files[0].name : ''" 
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        <div class="space-y-2 pointer-events-none">
                            <span class="text-3xl block">📄</span>
                            <div class="text-xs font-bold text-slate-700" x-text="importFileName ? importFileName : 'Klik atau seret file JSON template ke sini'"></div>
                            <p class="text-[10.5px] text-slate-400">File format .json standar PINDAD IoT</p>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-sky-50 rounded-2xl border border-sky-200 text-sky-800 text-[11px] leading-relaxed flex items-start gap-2">
                    <span class="text-base">💡</span>
                    <span>Seluruh saluran <b>Virtual Pin</b>, tipe data, nilai batas, dan nama komponen akan otomatis dipulihkan ke sistem secara instan.</span>
                </div>

                <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button @click="modalImportTemplate = false" type="button" class="px-5 py-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs uppercase cursor-pointer">Batal</button>
                    <button type="submit" class="px-6 py-2.5 rounded-2xl bg-[#1D1616] hover:bg-slate-900 text-white font-black text-xs uppercase shadow-md transition cursor-pointer active:scale-95 flex items-center gap-1.5">
                        <span>📥 Import & Pasang Template</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

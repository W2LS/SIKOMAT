<script>
function accountSectionComponent() {
    return {
        openItem: null,
        modalPassword: false,
        modal2faSetup: false,
        modal2faDisable: false,
        modal2faSuccess: false,
        twoFactorEnabled: {{ ($twoFactorSetting && $twoFactorSetting->is_enabled) ? 'true' : 'false' }},
        twoFactorData: { secret: '', qr_svg: '', otp_uri: '', recovery_codes: [] },
        twoFactorOtpInput: '',
        twoFactorDisablePassword: '',
        twoFactorLoading: false,
        twoFactorError: '',
        twoFactorSuccessMsg: '',
        copyAccSuccess: false,
        modalAddUser: false,
        modalEditUser: false,
        modalResetPasswordUser: false,
        modalDeleteUser: false,
        modalReset2faUser: false,
        modalApproveTicket: false,
        modalRejectTicket: false,
        selectedTicketForApprove: { id: '', operator_name: '', room_name: '', location: '', request_type: '' },
        selectedTicketForReject: { id: '', operator_name: '', room_name: '' },
        approveTicketUrl: '',
        rejectTicketUrl: '',
        selectedUserForEdit: { id: '', name: '', username: '', division: '', assigned_device_ids: [], status: 'active' },
        selectedUserForPassword: { id: '', name: '', username: '' },
        selectedUserForDelete: { id: '', name: '', username: '' },
        selectedUserForReset2fa: { id: '', name: '', username: '' },
        editActionUrl: '',
        passwordActionUrl: '',
        deleteActionUrl: '',
        reset2faActionUrl: '',
        newOperatorDevices: [],
        editOperatorDevices: [],
        openEditUser(user) {
            this.selectedUserForEdit = {
                id: user.id || user._id,
                name: user.name || '',
                username: user.username || user.nip || '',
                division: user.division || 'Divisi Sistem Informasi & Fasilitas',
                assigned_device_ids: Array.isArray(user.assigned_device_ids) ? [...user.assigned_device_ids] : [],
                status: user.status || 'active'
            };
            this.editOperatorDevices = Array.isArray(user.assigned_device_ids) ? [...user.assigned_device_ids] : [];
            this.editActionUrl = '/admin/users/' + (user.id || user._id);
            this.modalEditUser = true;
        },
        openResetPassword(user) {
            this.selectedUserForPassword = {
                id: user.id || user._id,
                name: user.name || '',
                username: user.username || user.nip || ''
            };
            this.passwordActionUrl = '/admin/users/' + (user.id || user._id) + '/password';
            this.modalResetPasswordUser = true;
        },
        openDeleteUser(user) {
            this.selectedUserForDelete = {
                id: user.id || user._id,
                name: user.name || '',
                username: user.username || user.nip || ''
            };
            this.deleteActionUrl = '/admin/users/' + (user.id || user._id);
            this.modalDeleteUser = true;
        },
        openReset2fa(user) {
            this.selectedUserForReset2fa = {
                id: user.id || user._id,
                name: user.name || '',
                username: user.username || user.nip || ''
            };
            this.reset2faActionUrl = '/admin/users/' + (user.id || user._id) + '/reset-2fa';
            this.modalReset2faUser = true;
        },
        openApproveTicket(ticket) {
            this.selectedTicketForApprove = {
                id: ticket.id || ticket._id,
                operator_name: ticket.operator_name || '',
                room_name: ticket.room_name || '',
                location: ticket.location || '',
                request_type: ticket.request_type || 'new_device'
            };
            this.approveTicketUrl = '/admin/device-requests/' + (ticket.id || ticket._id) + '/approve';
            this.modalApproveTicket = true;
        },
        openRejectTicket(ticket) {
            this.selectedTicketForReject = {
                id: ticket.id || ticket._id,
                operator_name: ticket.operator_name || '',
                room_name: ticket.room_name || ''
            };
            this.rejectTicketUrl = '/admin/device-requests/' + (ticket.id || ticket._id) + '/reject';
            this.modalRejectTicket = true;
        },
        selectAllNewDevices(allIds) {
            this.newOperatorDevices = [...allIds];
        },
        clearAllNewDevices() {
            this.newOperatorDevices = [];
        },
        selectAllEditDevices(allIds) {
            this.editOperatorDevices = [...allIds];
        },
        clearAllEditDevices() {
            this.editOperatorDevices = [];
        },
        testToken: '{{ $telegramSettings['bot_token'] ?? '' }}',
        testChatId: '{{ $telegramSettings['chat_id'] ?? '' }}',
        isTesting: false,
        testMessage: '',
        testSuccess: null,
        async open2faSetup() {
            this.twoFactorLoading = true;
            this.twoFactorError = '';
            this.twoFactorOtpInput = '';
            try {
                const res = await fetch('{{ route('profile.2fa.setup') }}');
                const data = await res.json();
                if (data.status === 'success') {
                    this.twoFactorData = data;
                    this.modal2faSetup = true;
                } else {
                    alert(data.message || 'Gagal memuat konfigurasi 2FA.');
                }
            } catch (err) {
                alert('Terjadi kesalahan jaringan.');
            } finally {
                this.twoFactorLoading = false;
            }
        },
        async submit2faEnable() {
            if (!this.twoFactorOtpInput || this.twoFactorOtpInput.length !== 6) {
                this.twoFactorError = 'Masukkan 6 digit angka dari aplikasi Authenticator.';
                return;
            }
            this.twoFactorLoading = true;
            this.twoFactorError = '';
            try {
                const res = await fetch('{{ route('profile.2fa.enable') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ code: this.twoFactorOtpInput })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.twoFactorEnabled = true;
                    this.modal2faSetup = false;
                    this.modal2faSuccess = true;
                    this.twoFactorSuccessMsg = data.message;
                } else {
                    this.twoFactorError = data.message || 'Kode verifikasi salah.';
                }
            } catch (err) {
                this.twoFactorError = 'Gagal menghubungi server.';
            } finally {
                this.twoFactorLoading = false;
            }
        },
        async submit2faDisable() {
            if (!this.twoFactorDisablePassword) {
                this.twoFactorError = 'Masukkan kata sandi akun Anda.';
                return;
            }
            this.twoFactorLoading = true;
            this.twoFactorError = '';
            try {
                const res = await fetch('{{ route('profile.2fa.disable') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ password: this.twoFactorDisablePassword })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.twoFactorEnabled = false;
                    this.modal2faDisable = false;
                    this.twoFactorDisablePassword = '';
                    alert(data.message);
                } else {
                    this.twoFactorError = data.message || 'Kata sandi salah.';
                }
            } catch (err) {
                this.twoFactorError = 'Gagal menghubungi server.';
            } finally {
                this.twoFactorLoading = false;
            }
        },
        copyRecoveryCodes() {
            if (!this.twoFactorData.recovery_codes || !this.twoFactorData.recovery_codes.length) return;
            const text = "SIKOMAT PT PINDAD - 2FA RECOVERY CODES\n" + this.twoFactorData.recovery_codes.join("\n");
            navigator.clipboard.writeText(text).then(() => {
                alert('8 Kode Cadangan (Recovery Codes) berhasil disalin ke clipboard! Simpan di tempat aman.');
            });
        },
        downloadRecoveryCodes() {
            if (!this.twoFactorData.recovery_codes || !this.twoFactorData.recovery_codes.length) return;
            const text = "=== SIKOMAT PT PINDAD (PERSERO) ===\nTWO-FACTOR AUTHENTICATION RECOVERY CODES\nUser: {{ session('user_nip') }}\nTanggal: " + new Date().toLocaleString() + "\n\nGunakan kode berikut jika Anda kehilangan akses ke HP/Authenticator (Setiap kode hanya bisa digunakan 1 kali):\n\n" + this.twoFactorData.recovery_codes.map((c, i) => (i+1) + ". " + c).join("\n") + "\n\nJAGA KERAHASIAAN DOKUMEN INI.";
            const blob = new Blob([text], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'SIKOMAT_2FA_Recovery_Codes_{{ session('user_nip') }}.txt';
            a.click();
            window.URL.revokeObjectURL(url);
        },
        
        copyAccCmd() {
            const host = '{{ $serverLanHost ?? '192.168.196.236' }}';
            const cmd = 'sudo systemctl stop pindad* *telepon* 2>/dev/null; sudo pkill -9 -f \'server_telepon\' 2>/dev/null; sudo pkill -9 -f \'pindad\' 2>/dev/null; sudo pkill -9 -f python 2>/dev/null; (crontab -l 2>/dev/null | grep -v -E \'pindad|telepon|python\') | crontab -; (sudo crontab -l 2>/dev/null | grep -v -E \'pindad|telepon|python\') | sudo crontab - 2>/dev/null; sudo rm -f /etc/systemd/system/*pindad* /etc/systemd/system/*telepon* 2>/dev/null; sudo systemctl daemon-reload 2>/dev/null; rm -f ~/pindad_*.py ~/node_config.json ~/node.log 2>/dev/null; curl -sSL "http://' + host + ':8000/scripts/download/device?device_id=RPI3B_SERVER_INTERNET&broker_host=' + host + '" -o ~/pindad_node.py && (crontab -l 2>/dev/null | grep -v \'pindad_node\'; echo "@reboot sleep 10 && python3 -u ~/pindad_node.py > ~/node.log 2>&1 &") | crontab - && nohup python3 -u ~/pindad_node.py > ~/node.log 2>&1 &';
            
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(cmd).then(() => {
                    this.copyAccSuccess = true;
                    setTimeout(() => { this.copyAccSuccess = false; }, 3000);
                }).catch(() => {
                    this.fallbackCopyAcc(cmd);
                });
            } else {
                this.fallbackCopyAcc(cmd);
            }
        },
        fallbackCopyAcc(text) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.top = '0';
            ta.style.left = '0';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try {
                document.execCommand('copy');
                this.copyAccSuccess = true;
                setTimeout(() => { this.copyAccSuccess = false; }, 3000);
            } catch(e) {}
            document.body.removeChild(ta);
        },
        async runTest() {
            if (!this.testToken || !this.testChatId) {
                this.testMessage = 'Harap isi Bot Token dan Chat ID terlebih dahulu!';
                this.testSuccess = false;
                return;
            }
            this.isTesting = true;
            this.testMessage = '';
            this.testSuccess = null;
            try {
                const res = await fetch('{{ route('settings.telegram.test') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        telegram_bot_token: this.testToken,
                        telegram_chat_id: this.testChatId
                    })
                });
                const data = await res.json();
                this.testSuccess = data.success;
                this.testMessage = data.message;
            } catch (e) {
                this.testSuccess = false;
                this.testMessage = 'Koneksi gagal: ' + e.message;
            } finally {
                this.isTesting = false;
            }
        }
    };
}
</script>

@php
    $isSuperAdmin = (session('user_role') === 'admin' || session('user_nip') === 'PINDAD-IOT-2026' || (isset($user) && $user && $user->role === 'admin'));
    $allFleetDeviceIds = ($allFleetDevices ?? collect())->pluck('device_id')->toArray();
@endphp

<!-- ================= MODUL 4: PUSAT INFORMASI AKUN & SISTEM IOT ================= -->
<div class="space-y-6 pb-20" x-data="accountSectionComponent()">
    
    <!-- 1. PAGE HEADER -->
    <div class="border-b border-[#8E1616]/20 pb-4">
        <span class="text-[11px] font-extrabold uppercase tracking-widest text-[#8E1616] flex items-center gap-1.5">
            <span>⚙️</span>
            <span>PUSAT PENGATURAN & INFORMASI SISTEM</span>
        </span>
        <h2 class="text-2xl sm:text-3xl font-black text-[#1D1616] tracking-tight mt-0.5">
            {{ $isSuperAdmin ? 'Pusat Kontrol Sistem & Pengaturan' : 'Akun Operator & Informasi Sistem' }}
        </h2>
        <p class="text-xs font-semibold text-slate-500 mt-1">
            {{ $isSuperAdmin ? 'Panduan lengkap penggunaan sistem, manajemen kredensial operator, dan spesifikasi arsitektur IoT PT PINDAD.' : 'Informasi kredensial profil operator, perubahan kata sandi, dan spesifikasi arsitektur IoT PT PINDAD.' }}
        </p>
    </div>

    <!-- 2. ACCORDIONS SECTION -->
    <div class="space-y-4">

        @if($isSuperAdmin)
        <!-- ITEM 0: TUTORIAL & PANDUAN LENGKAP PENGGUNAAN PLATFORM (SOP END-TO-END) -->
        <div class="bg-white rounded-[32px] border border-[#8E1616]/20 shadow-xs overflow-hidden transition-all duration-300">
            <button @click="openItem = openItem === 'tutorial' ? null : 'tutorial'" 
                    type="button" 
                    class="w-full p-4 sm:p-6 text-left flex items-center justify-between hover:bg-slate-50 transition cursor-pointer">
                <div class="flex items-center space-x-3 sm:space-x-4 min-w-0">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-rose-100 text-[#8E1616] flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        📖
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm sm:text-base font-black text-[#1D1616] leading-tight truncate">Panduan Setup Node IoT</h3>
                            <span class="bg-emerald-600 text-white text-[9px] font-black uppercase px-2 py-0.5 rounded-full whitespace-nowrap shrink-0 tracking-wider">SOP</span>
                        </div>
                        <p class="text-[11px] sm:text-xs font-semibold text-slate-500 mt-0.5 truncate sm:whitespace-normal">SOP template, pendaftaran node, dan skrip Raspberry Pi</p>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-full bg-[#EEEEEE] flex items-center justify-center text-slate-600 font-bold text-sm transition-transform duration-300 shrink-0 ml-2"
                     :class="openItem === 'tutorial' ? 'rotate-90 bg-[#8E1616] text-white' : ''">
                    ➔
                </div>
            </button>

            <!-- ACCORDION CONTENT: COMPLETE STEP-BY-STEP SOP -->
            <div x-show="openItem === 'tutorial'" x-cloak x-transition class="px-4 sm:px-6 pb-6 pt-4 border-t border-[#8E1616]/10 space-y-4 bg-slate-50/70">
                
                <!-- PROMINENT DOWNLOAD PDF BANNER -->
                <div class="bg-gradient-to-r from-[#1D1616] via-[#8E1616] to-[#D84040] text-white p-4 sm:p-5 rounded-2xl sm:rounded-3xl shadow-md flex flex-col sm:flex-row items-center justify-between gap-3 border border-white/10">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl sm:rounded-2xl bg-white/15 flex items-center justify-center text-xl sm:text-2xl shrink-0 shadow-inner">
                            📑
                        </div>
                        <div class="min-w-0">
                            <h4 class="font-black text-xs sm:text-sm text-white tracking-tight flex items-center gap-2">
                                <span>Buku Panduan & SOP Teknis Lengkap</span>
                                <span class="bg-amber-400 text-slate-950 text-[9px] font-black uppercase px-2 py-0.5 rounded-full whitespace-nowrap">PDF A4</span>
                            </h4>
                            <p class="text-[10.5px] sm:text-[11.5px] text-rose-100 leading-snug mt-0.5">
                                Panduan detail penggunaan web, skema wiring pinout, instalasi OS, kalibrasi sensor & troubleshooting.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('panduan.pdf') }}" 
                       target="_blank" 
                       class="w-full sm:w-auto px-5 py-2.5 rounded-xl sm:rounded-2xl bg-white hover:bg-rose-50 text-[#8E1616] font-black text-xs uppercase tracking-wider shadow-md hover:shadow-lg transition flex items-center justify-center gap-2 cursor-pointer active:scale-95 shrink-0 whitespace-nowrap">
                        <span>📥</span>
                        <span>Unduh PDF Manual</span>
                    </a>
                </div>

                <!-- STEP 1: PILIH / BUAT TEMPLATE DEVZONE -->
                <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-xs space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-800 flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-[#1D1616] text-white flex items-center justify-center text-[10px] font-black shrink-0">1</span>
                            <span>PILIH / BUAT TEMPLATE HARDWARE (MODUL DEVZONE)</span>
                        </span>
                        <span class="text-[9px] font-black uppercase bg-slate-100 text-slate-700 px-2 py-0.5 rounded-md font-sans shrink-0 whitespace-nowrap">Modul 2</span>
                    </div>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Buka menu <b>DevZone</b> untuk memilih blueprint template yang sesuai (misal: <i>Module Relay 1, 2, 4, atau 8 Channel</i>) atau buat template kustom baru dengan susunan <b>Virtual Pin</b> relay dan sensor yang diinginkan.
                    </p>
                </div>

                <!-- STEP 2: DAFTARKAN RUANGAN BARU DI HOME -->
                <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-xs space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-800 flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-[#1D1616] text-white flex items-center justify-center text-[10px] font-black shrink-0">2</span>
                            <span>DAFTARKAN RUANGAN / PERANGKAT (MODUL HOME)</span>
                        </span>
                        <span class="text-[9px] font-black uppercase bg-slate-100 text-slate-700 px-2 py-0.5 rounded-md font-sans shrink-0 whitespace-nowrap">Modul 1</span>
                    </div>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Buka menu <b>Home</b> &rarr; klik <b>`+ Tambah Perangkat Baru`</b>. Masukkan Nama Ruangan (misal: <i>Server Telepon</i>), IP Address Raspberry Pi, dan pilih template yang telah ditentukan di langkah 1.
                    </p>
                </div>

                <!-- STEP 3: PERINTAH 1-KLIK OTOMATIS -->
                <div class="bg-slate-900 rounded-2xl sm:rounded-3xl p-4 sm:p-5 border border-slate-800 space-y-3 text-white shadow-lg">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs sm:text-sm font-black uppercase tracking-wider text-amber-400 flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-amber-400 text-slate-900 flex items-center justify-center text-[10px] font-black shrink-0">3</span>
                            <span>JALANKAN 1 PERINTAH CEPAT (OTOMATIS UNDUH & AUTO-BOOT)</span>
                        </span>
                        <button @click="copyAccCmd()" 
                                type="button" 
                                class="px-3 py-1.5 rounded-xl text-xs font-bold uppercase tracking-wider transition flex items-center gap-1.5 cursor-pointer active:scale-95 shrink-0 shadow-xs whitespace-nowrap"
                                :class="copyAccSuccess ? 'bg-emerald-500 text-white' : 'bg-white/15 hover:bg-white/25 text-amber-300 border border-amber-400/30'">
                            <span x-text="copyAccSuccess ? '✓' : '📋'"></span>
                            <span x-text="copyAccSuccess ? 'Tersalin!' : 'Salin Contoh Perintah'"></span>
                        </button>
                    </div>
                    
                    <div class="bg-black/60 rounded-xl p-3 sm:p-3.5 border border-white/10 font-mono text-xs text-emerald-400 break-all select-all leading-relaxed">
                        sudo systemctl stop pindad* *telepon* 2>/dev/null; sudo pkill -9 -f 'server_telepon' 2>/dev/null; sudo pkill -9 -f 'pindad' 2>/dev/null; sudo pkill -9 -f python 2>/dev/null; (crontab -l 2>/dev/null | grep -v -E 'pindad|telepon|python') | crontab -; (sudo crontab -l 2>/dev/null | grep -v -E 'pindad|telepon|python') | sudo crontab - 2>/dev/null; sudo rm -f /etc/systemd/system/*pindad* /etc/systemd/system/*telepon* 2>/dev/null; sudo systemctl daemon-reload 2>/dev/null; rm -f ~/pindad_*.py ~/node_config.json ~/node.log 2>/dev/null; curl -sSL "http://{{ $serverLanHost ?? '192.168.196.236' }}:8000/scripts/download/device?device_id=RPI3B_SERVER_INTERNET&broker_host={{ $serverLanHost ?? '192.168.196.236' }}" -o ~/pindad_node.py && (crontab -l 2>/dev/null | grep -v 'pindad_node'; echo "@reboot sleep 10 && python3 -u ~/pindad_node.py > ~/node.log 2>&1 &") | crontab - && nohup python3 -u ~/pindad_node.py > ~/node.log 2>&1 &
                    </div>

                    <div class="bg-amber-400/10 rounded-xl p-3 border border-amber-400/20 text-xs text-amber-200/90 space-y-1.5 leading-relaxed">
                        <p>💡 <strong>Cara Pakai:</strong> Buka menu <b>Home</b> &rarr; klik tombol <b>`⚡ Setup Node`</b> pada kartu perangkat Anda. Salin perintah 1-baris yang muncul, lalu <em>paste</em> di terminal Raspberry Pi. Perintah tersebut <strong>otomatis mengunduh skrip dan mengaktifkan auto-start</strong> tanpa perlu membuat file manual dengan <code>sudo nano</code>.</p>
                        <p class="text-[11px] text-amber-300/90 pt-1 border-t border-amber-400/20">
                            🌐 <strong>Penyesuaian IP / Deploy:</strong> Pada modal Setup Node, Anda dapat mengubah <b>IP Server Host</b> ke IP LAN baru atau Domain saat sistem dideploy ke server permanen (misal: <code>sikomat.pindad.co.id</code>). Perintah cURL otomatis ter-update seketika.
                        </p>
                    </div>
                </div>

                <!-- STEP 4: CEK LOG BERJALAN -->
                <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-xs space-y-2">
                    <span class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-800 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-[#1D1616] text-white flex items-center justify-center text-[10px] font-black shrink-0">4</span>
                        <span>PERIKSA LOG PENGIRIMAN DATA LIVE</span>
                    </span>
                    <div class="flex items-center justify-between bg-slate-50 p-3 rounded-xl border border-slate-200">
                        <code class="text-xs sm:text-sm font-mono font-bold text-slate-800">tail -f ~/node.log</code>
                        <span class="text-[11px] font-semibold text-slate-400 shrink-0 whitespace-nowrap">Tekan Ctrl+C keluar</span>
                    </div>
                    <p class="text-[11px] text-slate-500">
                        Gunakan perintah di atas di terminal untuk memantau pengiriman data telemetri arus ampere ACS712, estimasi daya watt, dan status saklar relai secara live ke web dashboard.
                    </p>
                </div>

                <!-- HELPERS GRID: INSTALL DEPENDENCY & I2C VERIFICATION -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                    <!-- Helper 1: Install Library -->
                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-base shrink-0">📦</span>
                                <h4 class="font-black text-xs uppercase tracking-wider text-[#1D1616] truncate">Install Library Sensor</h4>
                            </div>
                            <span class="text-[9px] font-mono bg-slate-100 px-2 py-0.5 rounded text-slate-500 font-bold shrink-0">Pip 3</span>
                        </div>
                        <p class="text-[11px] text-slate-500">Jalankan sekali pada instalasi Raspberry Pi baru:</p>
                        <code class="block font-mono text-[10.5px] bg-slate-900 text-slate-100 p-2.5 rounded-xl select-all overflow-x-auto leading-relaxed">
                            pip3 install paho-mqtt adafruit-circuitpython-ads1x15 adafruit-circuitpython-ds3231 RPi.GPIO
                        </code>
                    </div>

                    <!-- Helper 2: Test I2C Detection -->
                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-base shrink-0">🔍</span>
                                <h4 class="font-black text-xs uppercase tracking-wider text-[#1D1616] truncate">Uji Deteksi Bus I2C</h4>
                            </div>
                            <span class="text-[9px] font-mono bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold shrink-0">i2cdetect</span>
                        </div>
                        <p class="text-[11px] text-slate-500">Pastikan alamat <b>0x48</b> (ADS1115 ADDR ke GND) & <b>0x68</b> (DS3231) muncul:</p>
                        <code class="block font-mono text-[10.5px] bg-slate-900 text-emerald-400 p-2.5 rounded-xl select-all overflow-x-auto leading-relaxed">
                            sudo i2cdetect -y 1
                        </code>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ITEM: TWO-FACTOR AUTHENTICATION (2FA TOTP RFC 6238) -->
        <div class="bg-white rounded-[32px] border border-[#8E1616]/20 shadow-xs overflow-hidden transition-all duration-300">
            <button @click="openItem = openItem === '2fa' ? null : '2fa'" 
                    type="button" 
                    class="w-full p-4 sm:p-6 text-left flex items-center justify-between hover:bg-slate-50 transition cursor-pointer">
                <div class="flex items-center space-x-3 sm:space-x-4 min-w-0">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-rose-100 text-[#8E1616] flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        🔐
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm sm:text-base font-black text-[#1D1616] leading-tight truncate">Autentikasi Dua Faktor (2FA)</h3>
                            <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full whitespace-nowrap shrink-0 tracking-wider"
                                  :class="twoFactorEnabled ? 'bg-emerald-600 text-white' : 'bg-[#8E1616] text-white'"
                                  x-text="twoFactorEnabled ? 'AKTIF' : 'SECURITY'"></span>
                        </div>
                        <p class="text-[11px] sm:text-xs font-semibold text-slate-500 mt-0.5 truncate sm:whitespace-normal">Google Authenticator TOTP RFC 6238 & 8 Recovery Codes</p>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-full bg-[#EEEEEE] flex items-center justify-center text-slate-600 font-bold text-sm transition-transform duration-300 shrink-0 ml-2"
                     :class="openItem === '2fa' ? 'rotate-90 bg-[#8E1616] text-white' : ''">
                    ➔
                </div>
            </button>

            <!-- ACCORDION CONTENT: 2FA CONFIGURATION -->
                <div x-show="openItem === '2fa'" x-cloak x-transition class="px-5 sm:px-6 pb-6 pt-2 border-t border-slate-100 space-y-4">
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <h4 class="text-xs sm:text-sm font-black text-[#1D1616] flex items-center gap-1.5">
                                <span>Status Keamanan Akun Operator:</span>
                                <span x-show="twoFactorEnabled" class="text-emerald-600 font-bold">Terproteksi 2FA</span>
                                <span x-show="!twoFactorEnabled" class="text-amber-600 font-bold">Hanya Kata Sandi Standar</span>
                            </h4>
                            <p class="text-[11px] text-slate-500 mt-1 max-w-xl">
                                Ketika 2FA aktif, sistem akan mewajibkan 6-digit kode acak dari Google Authenticator atau kode pemulihan cadangan setiap kali login ke SIKOMAT.
                            </p>
                        </div>
                        <div class="shrink-0 flex items-center gap-2">
                            <!-- Tombol Aksi Tunggal (Bebas Dual Informasi) -->
                            <button x-show="!twoFactorEnabled" 
                                    @click="open2faSetup()"
                                    :disabled="twoFactorLoading"
                                    type="button" 
                                    class="px-4.5 py-2.5 rounded-xl bg-gradient-to-r from-[#1D1616] via-[#8E1616] to-[#D84040] text-white text-xs font-bold shadow-sm hover:opacity-90 transition-all flex items-center gap-1.5 cursor-pointer active:scale-95">
                                <span x-show="!twoFactorLoading">🚀 Aktifkan 2FA Sekarang</span>
                                <span x-show="twoFactorLoading">⏳ Memuat QR Code...</span>
                            </button>
                            <button x-show="twoFactorEnabled" 
                                    @click="modal2faDisable = true; twoFactorError = '';"
                                    type="button" 
                                    class="px-4.5 py-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold hover:bg-rose-100 transition-all flex items-center gap-1.5 cursor-pointer active:scale-95 shadow-2xs">
                                <span>🔒 Nonaktifkan 2FA</span>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-left">
                        <div class="p-3 rounded-xl bg-white border border-slate-200">
                            <div class="text-[10px] uppercase tracking-wider font-black text-slate-400">Pilar 1</div>
                            <div class="text-xs font-bold text-[#1D1616] mt-0.5">Zero External API</div>
                            <div class="text-[10.5px] text-slate-500 mt-1">QR Code SVG di-render 100% lokal murni di server tanpa pihak ketiga.</div>
                        </div>
                        <div class="p-3 rounded-xl bg-white border border-slate-200">
                            <div class="text-[10px] uppercase tracking-wider font-black text-slate-400">Pilar 2</div>
                            <div class="text-xs font-bold text-[#1D1616] mt-0.5">Anti Replay & Brute Force</div>
                            <div class="text-[10.5px] text-slate-500 mt-1">Dilindungi Cache 60s dan RateLimiter maksimal 5x percobaan/menit.</div>
                        </div>
                        <div class="p-3 rounded-xl bg-white border border-slate-200">
                            <div class="text-[10px] uppercase tracking-wider font-black text-slate-400">Pilar 3</div>
                            <div class="text-xs font-bold text-[#1D1616] mt-0.5">8 Recovery Codes</div>
                            <div class="text-[10.5px] text-slate-500 mt-1">Kode cadangan ter-hash (Bcrypt) yang otomatis terhapus saat dipakai.</div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- ITEM: NOTIFIKASI BOT TELEGRAM (PERINGATAN ANOMALI KEGAGALAN AC) -->
        <div class="bg-white rounded-[32px] border border-[#8E1616]/20 shadow-xs overflow-hidden transition-all duration-300">
            <button @click="openItem = openItem === 'telegram' ? null : 'telegram'" 
                    type="button" 
                    class="w-full p-4 sm:p-6 text-left flex items-center justify-between hover:bg-slate-50 transition cursor-pointer">
                <div class="flex items-center space-x-3 sm:space-x-4 min-w-0">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-rose-100 text-[#8E1616] flex items-center justify-center font-black text-xl sm:text-2xl shrink-0">
                        🤖
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm sm:text-base font-black text-[#1D1616] leading-tight truncate">Notifikasi Bot Telegram</h3>
                            <span class="bg-sky-600 text-white text-[9px] font-black uppercase px-2 py-0.5 rounded-full whitespace-nowrap shrink-0 tracking-wider">ALARM</span>
                        </div>
                        <p class="text-[11px] sm:text-xs font-semibold text-slate-500 mt-0.5 truncate sm:whitespace-normal">Alarm darurat otomatis teknisi saat anomali atau kegagalan AC</p>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-full bg-[#EEEEEE] flex items-center justify-center text-slate-600 font-bold text-sm transition-transform duration-300 shrink-0 ml-2"
                     :class="openItem === 'telegram' ? 'rotate-90 bg-[#8E1616] text-white' : ''">
                    ➔
                </div>
            </button>

            <!-- ACCORDION CONTENT: TELEGRAM CONFIGURATION & TESTING -->
            <div x-show="openItem === 'telegram'" x-cloak x-transition class="px-5 sm:px-6 pb-6 pt-4 border-t border-sky-100 space-y-6 bg-slate-50/70">
                
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    
                    <!-- FORM CONFIGURATION -->
                    <div class="lg:col-span-7 bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h4 class="text-sm font-black text-[#1D1616] uppercase tracking-wider flex items-center gap-2">
                                <span>⚙️</span>
                                <span>Konfigurasi Akun Bot Telegram</span>
                            </h4>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="'{{ ($telegramSettings['is_enabled'] ?? true) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}'">
                                {{ ($telegramSettings['is_enabled'] ?? true) ? '🟢 Aktif' : '⚪ Nonaktif' }}
                            </span>
                        </div>

                        <form action="{{ route('settings.telegram') }}" method="POST" class="space-y-4 text-xs">
                            @csrf
                            
                            <div>
                                <label class="block font-black uppercase text-slate-700 tracking-wider mb-1.5">
                                    Telegram Bot Token (dari @BotFather) *
                                </label>
                                <input type="text" 
                                       name="telegram_bot_token" 
                                       x-model="testToken" 
                                       required 
                                       placeholder="Contoh: 7891234567:AAHdef123456xyz..." 
                                       class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-xs sm:text-sm focus:ring-2 focus:ring-sky-500 outline-none">
                                <p class="text-[10.5px] text-slate-400 mt-1">Dibuat melalui bot resmi <b>@BotFather</b> di aplikasi Telegram.</p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="block font-black uppercase text-slate-700 tracking-wider mb-1.5">
                                        Chat ID / ID Grup Teknisi *
                                    </label>
                                    <input type="text" 
                                           name="telegram_chat_id" 
                                           x-model="testChatId" 
                                           required 
                                           placeholder="Contoh: 123456789 atau -100..." 
                                           class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-xs sm:text-sm focus:ring-2 focus:ring-sky-500 outline-none">
                                    <p class="text-[10.5px] text-slate-400 mt-1">Gunakan <b>@userinfobot</b> atau <b>@getidsbot</b> untuk mengetahui ID.</p>
                                </div>

                                <div>
                                    <label class="block font-black uppercase text-slate-700 tracking-wider mb-1.5">
                                        Jeda Waktu Cooldown (Menit)
                                    </label>
                                    <input type="number" 
                                           name="telegram_cooldown_minutes" 
                                           value="{{ $telegramSettings['cooldown_minutes'] ?? 15 }}" 
                                           min="1" 
                                           max="120" 
                                           class="w-full px-4 py-2.5 rounded-2xl border border-slate-200 font-mono text-xs sm:text-sm focus:ring-2 focus:ring-sky-500 outline-none">
                                    <p class="text-[10.5px] text-slate-400 mt-1">Mencegah spam notifikasi berulang untuk anomali yang sama.</p>
                                </div>
                            </div>

                            <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between">
                                <div>
                                    <span class="font-black text-slate-800 block text-xs">Aktifkan Notifikasi Darurat Otomatis</span>
                                    <span class="text-[10.5px] text-slate-500">Kirim pesan seketika saat AC gagal hidup / terdeteksi 0 Ampere</span>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="telegram_alert_enabled" value="1" {{ ($telegramSettings['is_enabled'] ?? true) ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-500"></div>
                                </label>
                            </div>

                            <!-- ACTION BUTTONS -->
                            <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-100">
                                <button @click="runTest()" 
                                        type="button" 
                                        :disabled="isTesting"
                                        class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 font-bold text-xs uppercase tracking-wider transition flex items-center justify-center gap-1.5 cursor-pointer active:scale-95 disabled:opacity-50">
                                    <span x-show="!isTesting">🧪 Uji Coba Kirim Pesan</span>
                                    <span x-show="isTesting" class="animate-spin">⏳</span>
                                    <span x-show="isTesting">Mengirim...</span>
                                </button>

                                <button type="submit" 
                                        class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-[#1D1616] hover:bg-slate-900 text-white font-black text-xs uppercase tracking-wider shadow-md transition cursor-pointer active:scale-95">
                                    💾 Simpan Pengaturan
                                </button>
                            </div>

                            <!-- LIVE TEST FEEDBACK ALERT -->
                            <div x-show="testMessage" x-cloak class="p-3.5 rounded-2xl text-xs font-bold transition flex items-center gap-2"
                                 :class="testSuccess ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'">
                                <span x-text="testSuccess ? '✅' : '❌'"></span>
                                <span x-text="testMessage"></span>
                            </div>
                        </form>
                    </div>

                    <!-- STEP BY STEP SETUP GUIDE & MESSAGE PREVIEW -->
                    <div class="lg:col-span-5 space-y-4">
                        
                        <!-- 3 Step Tutorial -->
                        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-3">
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-2">
                                <span>📋</span>
                                <span>Panduan 3 Langkah Buat Bot Telegram</span>
                            </h4>

                            <div class="space-y-2.5 text-[11px] text-slate-600">
                                <div class="flex items-start gap-2">
                                    <span class="w-4 h-4 rounded-full bg-sky-500 text-white flex items-center justify-center text-[9px] font-black shrink-0 mt-0.5">1</span>
                                    <p>Buka Telegram, cari <b>@BotFather</b> lalu ketik <code>/newbot</code>. Beri nama bot dan salin <b>HTTP API Token</b> yang diberikan.</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="w-4 h-4 rounded-full bg-sky-500 text-white flex items-center justify-center text-[9px] font-black shrink-0 mt-0.5">2</span>
                                    <p>Buka bot baru Anda lalu tekan <b>Start</b>. Untuk grup, masukkan bot ke grup teknisi lalu cari Chat ID via <b>@userinfobot</b> / <b>@getidsbot</b>.</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="w-4 h-4 rounded-full bg-sky-500 text-white flex items-center justify-center text-[9px] font-black shrink-0 mt-0.5">3</span>
                                    <p>Paste Token & Chat ID ke form di samping, lalu klik tombol <b>🧪 Uji Coba Kirim Pesan</b>.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Preview Message Box -->
                        <div class="bg-slate-900 text-slate-200 p-4 rounded-3xl border border-slate-800 space-y-2 shadow-md">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-widest text-sky-400 flex items-center gap-1.5">
                                    <span>📱</span>
                                    <span>Contoh Pesan Darurat di Telegram:</span>
                                </span>
                                <span class="text-[9px] font-mono bg-white/10 px-2 py-0.5 rounded text-amber-300">Live Preview</span>
                            </div>
                            <div class="bg-black/50 p-3 rounded-2xl border border-white/10 font-mono text-[10.5px] leading-relaxed text-slate-200 space-y-1">
                                <p class="text-rose-400 font-bold">🚨 [PERINGATAN KRITIS • PT PINDAD]</p>
                                <p class="text-amber-300 font-bold">⚠️ GANGGUAN: AC GAGAL MENYALA / MATI!</p>
                                <p class="pt-1 text-slate-300">📍 <b>Ruangan:</b> Server Telepon (Gedung Koperasi)</p>
                                <p class="text-slate-300">❄️ <b>Unit AC:</b> Unit 1 (Panasonic 1)</p>
                                <p class="text-rose-400">⚡ <b>Arus:</b> 0.0000 A (Kompresor Mati / 0 W)</p>
                                <p class="text-slate-400">⚙️ <b>Status:</b> DIPERINTAHKAN ON (WAKTU NYALA)</p>
                                <p class="text-sky-300 pt-1">👨‍🔧 <b>Tindakan:</b> Mohon teknisi segera cek MCB & unit AC di Server Telepon!</p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>

        

        <!-- ================= ITEM: TIKET PENGAJUAN PERANGKAT OPERATOR (SUPER ADMIN) ================= -->
        <div class="bg-white rounded-[32px] border border-[#8E1616]/20 shadow-xs overflow-hidden transition-all duration-300">
            <!-- ACCORDION HEADER -->
            <button @click="openItem = openItem === 'tickets' ? null : 'tickets'" 
                    type="button" 
                    class="w-full p-4 sm:p-6 text-left flex items-center justify-between hover:bg-slate-50 transition cursor-pointer">
                <div class="flex items-center space-x-3 sm:space-x-4 min-w-0">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center font-black text-xl sm:text-2xl shrink-0">
                        📋
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm sm:text-base font-black text-[#1D1616] leading-tight truncate">Tiket Pengajuan Perangkat</h3>
                            @if(($pendingRequestCount ?? 0) > 0)
                                <span class="bg-[#8E1616] text-white text-[9px] font-black uppercase px-2 py-0.5 rounded-full whitespace-nowrap shrink-0 tracking-wider animate-pulse">{{ $pendingRequestCount }} MENUNGGU REVIEW</span>
                            @else
                                <span class="bg-slate-200 text-slate-700 text-[9px] font-black uppercase px-2 py-0.5 rounded-full whitespace-nowrap shrink-0 tracking-wider">SEMUA DITINJAU</span>
                            @endif
                        </div>
                        <p class="text-[11px] sm:text-xs font-semibold text-slate-500 mt-0.5 truncate sm:whitespace-normal">
                            Tinjau permohonan perangkat dan izin akses operator
                        </p>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-full bg-[#EEEEEE] flex items-center justify-center text-slate-600 font-bold text-sm transition-transform duration-300 shrink-0 ml-2"
                     :class="openItem === 'tickets' ? 'rotate-90 bg-[#8E1616] text-white' : ''">
                    ➔
                </div>
            </button>

            <!-- ACCORDION CONTENT -->
            <div x-show="openItem === 'tickets'" 
                 x-collapse
                 class="border-t border-slate-100 p-4 sm:p-6 bg-slate-50/50 space-y-4">
                
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                        <h4 class="text-xs font-black text-[#1D1616] uppercase tracking-wider">Daftar Tiket Pengajuan</h4>
                        <span class="text-[11px] text-slate-500 font-medium">Tinjau permohonan perangkat & akses operator</span>
                    </div>

                    @if(empty($deviceRequests) || count($deviceRequests) === 0)
                    <div class="p-8 text-center space-y-3">
                        <div class="w-14 h-14 mx-auto rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-2xl">
                            📋
                        </div>
                        <h5 class="text-sm font-bold text-slate-700">Belum Ada Pengajuan</h5>
                        <p class="text-xs text-slate-500 max-w-md mx-auto">Pengajuan perangkat atau izin akses dari operator akan muncul di sini untuk ditinjau.</p>
                    </div>
                    @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[620px] text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                                    <th class="py-3.5 px-4 whitespace-nowrap">Operator</th>
                                    <th class="py-3.5 px-4 whitespace-nowrap">Ruangan & Lokasi</th>
                                    <th class="py-3.5 px-4 whitespace-nowrap">Jenis & Kebutuhan</th>
                                    <th class="py-3.5 px-4 whitespace-nowrap text-center">Status</th>
                                    <th class="py-3.5 px-4 whitespace-nowrap text-center">Tanggal</th>
                                    <th class="py-3.5 px-4 whitespace-nowrap text-right">Aksi Review</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($deviceRequests as $req)
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <!-- 1. OPERATOR -->
                                    <td class="py-3.5 px-4 align-middle whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#8E1616] to-[#1D1616] text-white font-black text-xs flex items-center justify-center shrink-0 shadow-xs">
                                                {{ strtoupper(substr($req->operator_name ?? 'OP', 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="font-black text-[#1D1616] text-xs leading-tight">{{ $req->operator_name }}</div>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="text-[11px] text-[#8E1616] font-mono font-bold">{{ $req->operator_username }}</span>
                                                    <span class="text-[10px] text-slate-300">•</span>
                                                    <span class="text-[10px] text-slate-400 font-medium">{{ $req->operator_division ?? 'Divisi Fasilitas' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 2. RUANGAN & LOKASI -->
                                    <td class="py-3.5 px-4 align-middle whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm">📍</span>
                                            <div>
                                                <div class="font-bold text-xs text-[#1D1616] leading-tight">{{ $req->room_name }}</div>
                                                <div class="text-[10px] text-slate-400 font-medium mt-0.5">{{ $req->location ?? 'Gedung & Fasilitas' }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 3. JENIS & KEBUTUHAN -->
                                    <td class="py-3.5 px-4 align-middle">
                                        <div class="flex flex-col items-start gap-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider whitespace-nowrap {{ $req->request_type === 'new_device' ? 'bg-rose-100 text-[#8E1616] border border-rose-200' : 'bg-blue-100 text-blue-800 border border-blue-200' }}">
                                                {{ $req->request_type === 'new_device' ? 'Pengadaan Baru' : 'Izin Akses Ruangan' }}
                                            </span>
                                            @if($req->notes)
                                                <div class="text-[11px] text-slate-500 line-clamp-1 max-w-[200px]" title="{{ $req->notes }}">{{ $req->notes }}</div>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- 4. STATUS -->
                                    <td class="py-3.5 px-4 align-middle text-center whitespace-nowrap">
                                        @if($req->status === 'approved')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                Disetujui
                                            </span>
                                        @elseif($req->status === 'rejected')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                Ditolak
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black bg-amber-50 text-amber-800 border border-amber-200 animate-pulse">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                Menunggu Review
                                            </span>
                                        @endif
                                    </td>

                                    <!-- 5. TANGGAL -->
                                    <td class="py-3.5 px-4 align-middle text-center text-[11px] text-slate-500 font-mono whitespace-nowrap">
                                        {{ $req->created_at ? $req->created_at->format('d M Y, H:i') : '-' }}
                                    </td>

                                    <!-- 6. AKSI REVIEW -->
                                    <td class="py-3.5 px-4 align-middle text-right whitespace-nowrap">
                                        @if($req->status === 'pending')
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            <button 
                                                @click="openApproveTicket({{ json_encode($req) }})"
                                                type="button" 
                                                title="Setujui dan Alokasikan Perangkat"
                                                class="px-2.5 py-1.5 text-[11px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg inline-flex items-center gap-1 transition-colors cursor-pointer shadow-2xs whitespace-nowrap">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span>Setujui</span>
                                            </button>
                                            <button 
                                                @click="openRejectTicket({{ json_encode($req) }})"
                                                type="button" 
                                                title="Tolak Pengajuan"
                                                class="px-2.5 py-1.5 text-[11px] font-bold bg-slate-100 hover:bg-rose-50 hover:text-rose-700 text-slate-700 rounded-lg inline-flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                <span>Tolak</span>
                                            </button>
                                        </div>
                                        @else
                                        <div class="text-[10px] text-slate-400 italic">
                                            @if($req->admin_notes) Catatan: {{ $req->admin_notes }} @else Selesai Ditinjau @endif
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>

            </div>
        </div>

        <!-- ================= ITEM: MANAJEMEN USER OPERATOR & HAK AKSES RUANGAN (SUPER ADMIN) ================= -->
        <div class="bg-white rounded-[32px] border border-[#8E1616]/20 shadow-xs overflow-hidden transition-all duration-300">
            <!-- ACCORDION HEADER (IDENTIK & SEIRAS 100% DENGAN KOTAK LAIN) -->
            <button @click="openItem = openItem === 'users' ? null : 'users'" 
                    type="button" 
                    class="w-full p-4 sm:p-6 text-left flex items-center justify-between hover:bg-slate-50 transition cursor-pointer">
                <div class="flex items-center space-x-3 sm:space-x-4 min-w-0">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-rose-100 text-[#8E1616] flex items-center justify-center font-black text-xl sm:text-2xl shrink-0">
                        👥
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm sm:text-base font-black text-[#1D1616] leading-tight truncate">Manajemen Akun Operator</h3>
                            <span class="bg-[#8E1616] text-white text-[9px] font-black uppercase px-2 py-0.5 rounded-full whitespace-nowrap shrink-0 tracking-wider">SUPER ADMIN</span>
                        </div>
                        <p class="text-[11px] sm:text-xs font-semibold text-slate-500 mt-0.5 truncate sm:whitespace-normal">
                            Kelola akun operator dan izin kontrol AC ruangan
                        </p>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-full bg-[#EEEEEE] flex items-center justify-center text-slate-600 font-bold text-sm transition-transform duration-300 shrink-0 ml-2"
                     :class="openItem === 'users' ? 'rotate-90 bg-[#8E1616] text-white' : ''">
                    ➔
                </div>
            </button>

            <!-- ACCORDION CONTENT -->
            <div x-show="openItem === 'users'" 
                 x-collapse
                 class="border-t border-slate-100 p-4 sm:p-7 bg-slate-50/50 space-y-6">

                <!-- TOP SUMMARY & CTA BAR -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs">
                    <div class="flex items-center gap-3 flex-wrap">
                        <div class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-100/80 border border-slate-200 text-xs font-bold text-slate-700">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#8E1616] inline-block"></span>
                            <span>Total Operator: <strong class="text-[#1D1616]">{{ count($allOperators ?? []) }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-100/80 border border-slate-200 text-xs font-bold text-slate-700">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 inline-block"></span>
                            <span>Ruangan Fleet Tersedia: <strong class="text-[#1D1616]">{{ count($allFleetDevices ?? []) }}</strong></span>
                        </div>
                    </div>

                    <button 
                        @click="modalAddUser = true; newOperatorDevices = []" 
                        type="button" 
                        class="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-[#D84040] to-[#8E1616] hover:opacity-95 text-white text-xs font-black rounded-xl shadow-md shadow-[#8E1616]/20 flex items-center justify-center gap-2 transition-all cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        <span>+ Tambah User Operator Baru</span>
                    </button>
                </div>

                <!-- OPERATOR LIST TABLE -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                        <h4 class="text-xs font-black text-[#1D1616] uppercase tracking-wider">Daftar Akun Operator SIKOMAT</h4>
                        <span class="text-[11px] text-slate-500 font-medium">Hak akses otomatis disinkronkan saat operator login</span>
                    </div>

                    @if(empty($allOperators) || count($allOperators) === 0)
                    <div class="p-8 text-center space-y-3">
                        <div class="w-14 h-14 mx-auto rounded-full bg-rose-50 text-[#8E1616] flex items-center justify-center text-2xl">
                            👥
                        </div>
                        <h5 class="text-sm font-bold text-slate-700">Belum Ada Operator Tambahan</h5>
                        <p class="text-xs text-slate-500 max-w-md mx-auto">Klik tombol <strong>+ Tambah User Operator Baru</strong> di atas untuk membuat akun operator pertama serta memilih ruangan mana saja yang boleh mereka pantau.</p>
                    </div>
                    @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[620px] text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                                    <th class="py-3.5 px-4 whitespace-nowrap">Operator</th>
                                    <th class="py-3.5 px-4 whitespace-nowrap">Hak Akses Ruangan / Perangkat</th>
                                    <th class="py-3.5 px-4 whitespace-nowrap text-center">Status Akun</th>
                                    <th class="py-3.5 px-4 whitespace-nowrap text-center">Status 2FA</th>
                                    <th class="py-3.5 px-4 whitespace-nowrap text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($allOperators as $op)
                                @php
                                    $assignedIds = $op->assigned_device_ids ?? [];
                                    $opDevices = ($allFleetDevices ?? collect())->whereIn('device_id', $assignedIds);
                                @endphp
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <!-- 1. OPERATOR -->
                                    <td class="py-3.5 px-4 align-middle whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#8E1616] to-[#1D1616] text-white font-black text-xs flex items-center justify-center shrink-0 shadow-xs">
                                                {{ strtoupper(substr($op->name ?? 'OP', 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="font-black text-[#1D1616] text-xs leading-tight">{{ $op->name }}</div>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="text-[11px] text-[#8E1616] font-mono font-bold">{{ $op->username ?? $op->nip }}</span>
                                                    <span class="text-[10px] text-slate-300">•</span>
                                                    <span class="text-[10px] text-slate-400 font-medium">{{ $op->division ?? 'Divisi Fasilitas' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 2. HAK AKSES RUANGAN / PERANGKAT -->
                                    <td class="py-3.5 px-4 align-middle">
                                        @if(empty($assignedIds) || count($assignedIds) === 0)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 whitespace-nowrap">
                                                ⚠️ Belum Ada Akses Ruangan
                                            </span>
                                        @else
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                @foreach($assignedIds as $aId)
                                                @php
                                                    $dObj = ($allFleetDevices ?? collect())->firstWhere('device_id', $aId);
                                                    $roomName = $dObj ? ($dObj->name ?? $dObj->location ?? $aId) : $aId;
                                                    $locationName = $dObj?->location;
                                                @endphp
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-800 border border-slate-200 whitespace-nowrap" title="{{ $aId }}">
                                                    <span>📍 {{ $roomName }}</span>
                                                    @if($locationName && $locationName !== $roomName)
                                                        <span class="text-slate-400 font-normal">({{ $locationName }})</span>
                                                    @endif
                                                </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>

                                    <!-- 3. STATUS AKUN -->
                                    <td class="py-3.5 px-4 align-middle text-center whitespace-nowrap">
                                        @if(($op->status ?? 'active') === 'active')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                Aktif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200">
                                                Nonaktif
                                            </span>
                                        @endif
                                    </td>

                                    <!-- 4. STATUS 2FA -->
                                    <td class="py-3.5 px-4 align-middle text-center whitespace-nowrap">
                                        @if($op->hasTwoFactorEnabled())
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                2FA Aktif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                                Belum Setup
                                            </span>
                                        @endif
                                    </td>

                                    <!-- 5. AKSI -->
                                    <td class="py-3.5 px-4 align-middle text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <!-- EDIT RUANGAN BUTTON -->
                                            <button 
                                                @click="openEditUser({{ json_encode($op) }})"
                                                type="button" 
                                                title="Edit Hak Akses Ruangan & Profil"
                                                class="px-2.5 py-1.5 text-[11px] font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg inline-flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                <span>Edit</span>
                                            </button>

                                            <!-- RESET PASSWORD BUTTON -->
                                            <button 
                                                @click="openResetPassword({{ json_encode($op) }})"
                                                type="button" 
                                                title="Ganti Kata Sandi Operator"
                                                class="px-2.5 py-1.5 text-[11px] font-bold bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg inline-flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                </svg>
                                                <span>Password</span>
                                            </button>

                                            <!-- RESET 2FA BUTTON -->
                                            <button 
                                                @click="openReset2fa({{ json_encode($op) }})"
                                                type="button" 
                                                title="Reset Kunci 2FA Operator"
                                                class="px-2.5 py-1.5 text-[11px] font-bold bg-purple-50 hover:bg-purple-100 text-purple-800 rounded-lg inline-flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                                <span>Reset 2FA</span>
                                            </button>

                                            <!-- DELETE OPERATOR BUTTON -->
                                            <button 
                                                @click="openDeleteUser({{ json_encode($op) }})"
                                                type="button" 
                                                title="Hapus Operator"
                                                class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>

            </div>
        </div>
        @endif
        
        <!-- ITEM 1: INFORMASI AKUN & PROFIL OPERATOR -->
        <div class="bg-white rounded-[32px] border border-[#8E1616]/20 shadow-xs overflow-hidden transition-all duration-300">
            <button @click="openItem = openItem === 'akun' ? null : 'akun'" 
                    type="button" 
                    class="w-full p-4 sm:p-6 text-left flex items-center justify-between hover:bg-slate-50 transition cursor-pointer">
                <div class="flex items-center space-x-3 sm:space-x-4 min-w-0">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-rose-100 text-[#8E1616] flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        👤
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm sm:text-base font-black text-[#1D1616] leading-snug">Profil & Akun {{ $isSuperAdmin ? 'Super Administrator' : 'Operator' }}</h3>
                        <p class="text-[11px] sm:text-xs font-semibold text-slate-500 mt-0.5 truncate sm:whitespace-normal">Informasi pengguna aktif dan hak akses kontrol dashboard</p>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-full bg-[#EEEEEE] flex items-center justify-center text-slate-600 font-bold text-sm transition-transform duration-300 shrink-0 ml-2"
                     :class="openItem === 'akun' ? 'rotate-90 bg-[#8E1616] text-white' : ''">
                    ➔
                </div>
            </button>

            <!-- ACCORDION CONTENT -->
            <div x-show="openItem === 'akun'" x-cloak x-transition class="px-4 sm:px-6 pb-6 pt-2 border-t border-[#8E1616]/10 space-y-4 bg-slate-50/60">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs pt-2">
                    <div class="bg-white p-4 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616] block">Nama Lengkap</span>
                        <span class="font-black text-[#1D1616] text-sm block mt-0.5">{{ $user->name ?? 'Dicky Akbar Syah Putra' }}</span>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616] block">Email Operator</span>
                        <span class="font-mono font-bold text-slate-700 text-xs block mt-0.5">{{ $user->email ?? 'dicky.akbar@pindad.com' }}</span>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616] block">Divisi</span>
                        <span class="font-bold text-[#1D1616] block mt-0.5">Mutu & TI / Fasilitas Gedung</span>
                    </div>
                    <div class="bg-white p-4 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616] block">Peran Sistem</span>
                        @if($isSuperAdmin)
                            <span class="font-bold text-emerald-600 block mt-0.5">● Super Administrator</span>
                        @else
                            <span class="font-bold text-sky-600 block mt-0.5">● Operator Ruangan</span>
                        @endif
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200 text-xs flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div class="flex items-center gap-3">
                        <button @click="modalPassword = true" 
                                type="button"
                                class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs uppercase tracking-wider transition cursor-pointer">
                            🔒 Ubah Kata Sandi
                        </button>
                    </div>

                    <!-- FORM LOGOUT -->
                    <form action="{{ route('logout') }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin keluar dari sesi operator?')">
                        @csrf
                        <button type="submit" 
                                class="px-5 py-2.5 rounded-xl bg-[#D84040] hover:bg-[#8E1616] text-white text-xs font-black uppercase tracking-wider shadow-md transition cursor-pointer flex items-center space-x-2">
                            <span>🚪</span>
                            <span>Keluar / Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ITEM 2: INFORMASI SISTEM & ARSITEKTUR WEB -->
        <div class="bg-white rounded-[32px] border border-[#8E1616]/20 shadow-xs overflow-hidden transition-all duration-300">
            <button @click="openItem = openItem === 'sistem' ? null : 'sistem'" 
                    type="button" 
                    class="w-full p-4 sm:p-6 text-left flex items-center justify-between hover:bg-slate-50 transition cursor-pointer">
                <div class="flex items-center space-x-3 sm:space-x-4 min-w-0">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-rose-100 text-[#8E1616] flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        🌐
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm sm:text-base font-black text-[#1D1616] leading-snug">Informasi Web & Server Platform</h3>
                        <p class="text-[11px] sm:text-xs font-semibold text-slate-500 mt-0.5 truncate sm:whitespace-normal">Spesifikasi software engine, database MongoDB, broker MQTT, WebSocket, dan security</p>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-full bg-[#EEEEEE] flex items-center justify-center text-slate-600 font-bold text-sm transition-transform duration-300 shrink-0 ml-2"
                     :class="openItem === 'sistem' ? 'rotate-90 bg-[#8E1616] text-white' : ''">
                    ❯
                </div>
            </button>

            <!-- ACCORDION CONTENT -->
            <div x-show="openItem === 'sistem'" x-cloak x-transition class="px-4 sm:px-6 pb-6 pt-2 border-t border-[#8E1616]/10 space-y-4 bg-slate-50/60">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 text-xs pt-2">
                    <div class="bg-white p-4 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616] block">Versi Platform</span>
                        <span class="font-black text-[#1D1616] text-sm block mt-0.5">v2.6.0 (PINDAD Industrial IoT)</span>
                        <span class="text-[10.5px] text-slate-500 mt-1 block">Full RBAC & Auto-Pruning Enabled</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616] block">Backend Engine</span>
                        <span class="font-bold text-[#1D1616] block mt-0.5">Laravel 11.x (PHP 8.2+)</span>
                        <span class="text-[10.5px] text-slate-500 mt-1 block">REST API & High-Speed Controller</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616] block">Database Mesin</span>
                        <span class="font-bold text-emerald-600 block mt-0.5">🍃 MongoDB Atlas / Local</span>
                        <span class="text-[10.5px] text-slate-500 mt-1 block">NoSQL Time-Series Logs & Hourly Summary</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616] block">Real-Time WebSocket</span>
                        <span class="font-bold text-emerald-600 block mt-0.5">⚡ Python AsyncIO (:8080)</span>
                        <span class="text-[10.5px] text-slate-500 mt-1 block">Ultra-Low Latency Telemetry (&lt; 5ms)</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616] block">MQTT Message Broker</span>
                        <span class="font-bold text-emerald-600 block mt-0.5">📡 Mosquitto TCP Port 1883</span>
                        <span class="text-[10.5px] text-slate-500 mt-1 block">Bidirectional Publish/Subscribe (QoS 0/1)</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616] block">Multi-Engine Launcher</span>
                        <span class="font-bold text-[#1D1616] block mt-0.5">start-lan-server.bat</span>
                        <span class="text-[10.5px] text-slate-500 mt-1 block">Simultaneous Web, WS & MQTT Startup</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616] block">Notifikasi Kritis</span>
                        <span class="font-bold text-sky-600 block mt-0.5">✈️ Telegram Bot Alert</span>
                        <span class="text-[10.5px] text-slate-500 mt-1 block">Deteksi Otomatis AC Mati (Arus 0A saat ON)</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616] block">Keamanan & Akses</span>
                        <span class="font-bold text-[#1D1616] block mt-0.5">2FA TOTP (RFC 6238) + RBAC</span>
                        <span class="text-[10.5px] text-slate-500 mt-1 block">Matriks Super Admin vs Operator Ruangan</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616] block">Firewall & Jaringan</span>
                        <span class="font-bold text-[#1D1616] block mt-0.5">Sophos Captive Portal Auto-Auth</span>
                        <span class="text-[10.5px] text-slate-500 mt-1 block">Koneksi Otomatis Jaringan Korporat PT PINDAD</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ITEM 3: SPESIFIKASI PERANGKAT KERAS IOT & WIRING PINOUT -->
        <div class="bg-white rounded-[32px] border border-[#8E1616]/20 shadow-xs overflow-hidden transition-all duration-300">
            <button @click="openItem = openItem === 'hardware' ? null : 'hardware'" 
                    type="button" 
                    class="w-full p-4 sm:p-6 text-left flex items-center justify-between hover:bg-slate-50 transition cursor-pointer">
                <div class="flex items-center space-x-3 sm:space-x-4 min-w-0">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-rose-100 text-[#8E1616] flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        📟
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm sm:text-base font-black text-[#1D1616] leading-snug">Spesifikasi Hardware & Skema Wiring</h3>
                        <p class="text-[11px] sm:text-xs font-semibold text-slate-500 mt-0.5 truncate sm:whitespace-normal">Daftar komponen sensor ACS712, ADC ADS1115 (Pin ADDR ke GND), RTC DS3231, dan modul relay</p>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-full bg-[#EEEEEE] flex items-center justify-center text-slate-600 font-bold text-sm transition-transform duration-300 shrink-0 ml-2"
                     :class="openItem === 'hardware' ? 'rotate-90 bg-[#8E1616] text-white' : ''">
                    ❯
                </div>
            </button>

            <!-- ACCORDION CONTENT -->
            <div x-show="openItem === 'hardware'" x-cloak x-transition class="px-4 sm:px-6 pb-6 pt-2 border-t border-[#8E1616]/10 space-y-5 bg-slate-50/60">
                <!-- 4 KARTU SPESIFIKASI INTI -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs pt-2">
                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616]">Kontroler Utama</span>
                            <span class="text-[9px] font-mono bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-bold">Linux / Pi OS</span>
                        </div>
                        <span class="font-black text-[#1D1616] text-sm block mt-1">Raspberry Pi 3 Model B+</span>
                        <p class="text-[11px] text-slate-500 mt-1">Quad-Core 1.4GHz Broadcom BCM2837B0, 1GB LPDDR2 SDRAM, Dual-Band Wi-Fi & Gigabit LAN.</p>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616]">Sensor Arus & ADC Presisi</span>
                            <span class="text-[9px] font-mono bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold">I2C 0x48</span>
                        </div>
                        <span class="font-black text-[#1D1616] text-sm block mt-1">2x ACS712 Hall-Effect + ADS1115 (16-Bit)</span>
                        <p class="text-[11px] text-slate-500 mt-1">Kanal A0 (AC 1) & A1 (AC 2). <b>Pin ADDR disambung ke GND</b> untuk mengunci alamat I2C stabil di <code class="font-mono text-emerald-700 bg-emerald-50 px-1 rounded">0x48</code>.</p>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616]">Hardware Clock (RTC)</span>
                            <span class="font-mono text-[9px] bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-bold">I2C 0x68</span>
                        </div>
                        <span class="font-black text-[#1D1616] text-sm block mt-1">Maxim DS3231 High-Precision RTC</span>
                        <p class="text-[11px] text-slate-500 mt-1">Paralel bus I2C (GPIO 2 & 3). Baterai CR2032 terintegrasi menjamin ketepatan jadwal shift saat offline/listrik padam.</p>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#8E1616]">Modul Saklar Relai</span>
                            <span class="text-[9px] font-mono bg-rose-100 text-rose-800 px-2 py-0.5 rounded font-bold">Optocoupler 5V</span>
                        </div>
                        <span class="font-black text-[#1D1616] text-sm block mt-1">Dual-Channel 5V Relay Industrial</span>
                        <p class="text-[11px] text-slate-500 mt-1">IN1 di <b>GPIO 17 (Pin 11)</b> untuk AC 1, IN2 di <b>GPIO 27 (Pin 13)</b> untuk AC 2. Kontak COM & NO memutus fasa AC 220V.</p>
                    </div>
                </div>

                <!-- TABEL PINOUT WIRING LENGKAP -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase text-[#1D1616] tracking-wider flex items-center gap-1.5">
                            <span>🔌</span>
                            <span>Tabel Pemetaan Pinout Wiring Fisik Hardware</span>
                        </span>
                        <span class="text-[10px] font-mono font-bold bg-slate-100 text-slate-600 px-2.5 py-0.5 rounded-full">Standard PINDAD Pinout</span>
                    </div>

                    <div class="overflow-x-auto -mx-2 sm:mx-0 px-2 sm:px-0">
                        <table class="w-full min-w-[580px] text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 text-[10px] font-black uppercase text-slate-500 border-b border-slate-200">
                                    <th class="py-2.5 px-3">Modul / Komponen</th>
                                    <th class="py-2.5 px-3">Pin Komponen</th>
                                    <th class="py-2.5 px-3">Pin Raspberry Pi 3B+</th>
                                    <th class="py-2.5 px-3">Fungsi / Deskripsi Jalur</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-[11.5px]">
                                <tr class="hover:bg-slate-50/70">
                                    <td class="py-2 px-3 font-bold text-[#1D1616]">Power Common</td>
                                    <td class="py-2 px-3 font-mono text-rose-600">VCC (5V) / GND</td>
                                    <td class="py-2 px-3 font-mono font-bold">Pin 2, 4 (5V) & Pin 6, 9 (GND)</td>
                                    <td class="py-2 px-3 text-slate-600">Distribusi catu daya 5V DC dan Common Ground ke semua modul</td>
                                </tr>
                                <tr class="hover:bg-slate-50/70">
                                    <td class="py-2 px-3 font-bold text-[#1D1616]">Modul Relai 2-Ch</td>
                                    <td class="py-2 px-3 font-mono text-indigo-600">IN1 / IN2</td>
                                    <td class="py-2 px-3 font-mono font-bold">GPIO 17 (Pin 11) & GPIO 27 (Pin 13)</td>
                                    <td class="py-2 px-3 text-slate-600">Kontrol trigger saklar Unit AC 1 dan Unit AC 2</td>
                                </tr>
                                <tr class="hover:bg-slate-50/70">
                                    <td class="py-2 px-3 font-bold text-[#1D1616]">ADC ADS1115 (16-Bit)</td>
                                    <td class="py-2 px-3 font-mono text-emerald-600">SDA / SCL</td>
                                    <td class="py-2 px-3 font-mono font-bold">GPIO 2 (Pin 3) & GPIO 3 (Pin 5)</td>
                                    <td class="py-2 px-3 text-slate-600">Komunikasi data I2C telemetri (Alamat <code class="font-bold">0x48</code>)</td>
                                </tr>
                                <tr class="hover:bg-slate-50/70">
                                    <td class="py-2 px-3 font-bold text-[#1D1616]">ADC ADS1115 (16-Bit)</td>
                                    <td class="py-2 px-3 font-mono text-amber-600">ADDR</td>
                                    <td class="py-2 px-3 font-mono font-bold">GND (Common Ground)</td>
                                    <td class="py-2 px-3 text-slate-600"><b>Wajib ke GND:</b> Mengunci alamat I2C stabil di <code class="font-bold">0x48</code> & anti-noise</td>
                                </tr>
                                <tr class="hover:bg-slate-50/70">
                                    <td class="py-2 px-3 font-bold text-[#1D1616]">ADC ADS1115 (16-Bit)</td>
                                    <td class="py-2 px-3 font-mono text-emerald-600">A0 / A1</td>
                                    <td class="py-2 px-3 font-mono">OUT Sensor ACS712 Unit 1 & 2</td>
                                    <td class="py-2 px-3 text-slate-600">Pembacaan tegangan analog sensor arus AC 1 & AC 2</td>
                                </tr>
                                <tr class="hover:bg-slate-50/70">
                                    <td class="py-2 px-3 font-bold text-[#1D1616]">RTC DS3231 Clock</td>
                                    <td class="py-2 px-3 font-mono text-purple-600">SDA / SCL</td>
                                    <td class="py-2 px-3 font-mono font-bold">GPIO 2 (Pin 3) & GPIO 3 (Pin 5)</td>
                                    <td class="py-2 px-3 text-slate-600">Paralel I2C (Alamat <code class="font-bold">0x68</code>) untuk pewaktu presisi jadwal shift</td>
                                </tr>
                                <tr class="hover:bg-slate-50/70">
                                    <td class="py-2 px-3 font-bold text-[#1D1616]">Beban Listrik AC 220V</td>
                                    <td class="py-2 px-3 font-mono text-slate-700">COM / NO Relay</td>
                                    <td class="py-2 px-3 font-mono">Kabel Fasa AC 220V Seri ke ACS712</td>
                                    <td class="py-2 px-3 text-slate-600">Jalur arus beban daya lampu indikator / kompresor AC</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- FOTO PROTOTYPE & WIRING AC LENGKAP -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase text-[#1D1616] tracking-wider flex items-center gap-1.5">
                            <span>📷</span>
                            <span>Foto Prototype Rangkaian Hardware & Wiring AC</span>
                        </span>
                        <span class="text-[9.5px] font-bold text-[#8E1616] bg-rose-50 px-2.5 py-0.5 rounded-full border border-rose-100">Lab Wiring AC</span>
                    </div>
                    <div class="rounded-2xl overflow-hidden bg-slate-50 border border-slate-200 p-2 sm:p-4 flex items-center justify-center">
                        <img src="/images/WIRING_AC.png" 
                             alt="Foto Prototype Skema Wiring AC Hardware SIKOMAT PT PINDAD" 
                             class="max-h-80 sm:max-h-96 w-auto max-w-full object-contain rounded-xl shadow-xs"
                             onerror="this.src='/WIRING_AC.png';">
                    </div>
                    <p class="text-[11px] text-slate-600 leading-relaxed">
                        Dokumentasi wiring simulasi & fisik unit kontroler: <b>Raspberry Pi 3B+</b>, <b>2x Sensor Arus ACS712</b>, <b>Modul ADC ADS1115 I2C (Pin ADDR ke GND)</b>, <b>RTC DS3231 (0x68)</b>, <b>Modul Relai 2-Channel (GPIO 17 & 27)</b>, dan beban lampu indikator AC 220V PLN.
                    </p>
                </div>
            </div>
        </div>
    </div>


    <!-- ================= MODAL UBAH PASSWORD ================= -->
    <div x-show="modalPassword" 
         x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalPassword = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-md w-full shadow-2xl border border-slate-200 space-y-3.5 sm:space-y-4 relative max-h-[82vh] sm:max-h-[88vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 sm:pb-3.5">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl sm:rounded-[20px] bg-[#8E1616]/10 text-[#8E1616] flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        🔒
                    </div>
                    <div>
                        <h4 class="text-base sm:text-lg font-black text-[#1D1616]">Ubah Kata Sandi Akun</h4>
                        <p class="text-[11px] sm:text-xs text-slate-500">Perbarui kata sandi login operator</p>
                    </div>
                </div>
                <button @click="modalPassword = false" class="text-slate-400 hover:text-[#8E1616] text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form action="{{ route('profile.password') }}" method="POST" class="space-y-3 sm:space-y-3.5">
                @csrf
                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Kata Sandi Saat Ini *</label>
                    <div class="relative" x-data="{ show: false }">
                        <input :type="show ? 'text' : 'password'" name="current_password" required class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 pr-10 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#8E1616] outline-none">
                        <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 focus:outline-none cursor-pointer">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show" x-cloak class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Kata Sandi Baru *</label>
                    <div class="relative" x-data="{ show: false }">
                        <input :type="show ? 'text' : 'password'" name="new_password" required minlength="6" class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 pr-10 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#8E1616] outline-none">
                        <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 focus:outline-none cursor-pointer">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show" x-cloak class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] sm:text-xs font-black uppercase text-slate-700 tracking-wider mb-1 sm:mb-1.5">Konfirmasi Kata Sandi Baru *</label>
                    <div class="relative" x-data="{ show: false }">
                        <input :type="show ? 'text' : 'password'" name="new_password_confirmation" required minlength="6" class="w-full px-3.5 py-2 sm:px-4 sm:py-2.5 pr-10 rounded-xl sm:rounded-2xl border border-slate-200 text-xs sm:text-sm focus:ring-2 focus:ring-[#8E1616] outline-none">
                        <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 focus:outline-none cursor-pointer">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show" x-cloak class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>

                <div class="pt-2.5 sm:pt-3 flex items-center justify-end gap-2.5 sm:gap-3 border-t border-slate-100">
                    <button @click="modalPassword = false" type="button" class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs uppercase cursor-pointer">Batal</button>
                    <button type="submit" class="px-5 sm:px-6 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-gradient-to-r from-[#8E1616] to-[#1D1616] text-white font-bold text-xs uppercase shadow-md hover:opacity-95 cursor-pointer">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    @if($isSuperAdmin)
    <!-- ================= MODAL SETUP 2FA ================= -->
    <div x-show="modal2faSetup" 
         x-cloak 
         class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modal2faSetup = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-lg w-full shadow-2xl border border-slate-200 space-y-4 relative max-h-[88vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 sm:pb-3.5">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl sm:rounded-[20px] bg-[#8E1616]/10 text-[#8E1616] flex items-center justify-center font-black text-lg sm:text-xl shrink-0">
                        🔐
                    </div>
                    <div>
                        <h4 class="text-base sm:text-lg font-black text-[#1D1616]">Aktivasi 2FA Authenticator</h4>
                        <p class="text-[11px] sm:text-xs text-slate-500">Scan QR Code dengan Google Authenticator di HP Anda</p>
                    </div>
                </div>
                <button @click="modal2faSetup = false" class="text-slate-400 hover:text-[#8E1616] text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <div class="py-2 space-y-4">
                <!-- QR Code Display (100% Offline SVG) -->
                <div class="text-center space-y-2">
                    <div class="inline-block p-3 bg-white rounded-2xl border-2 border-slate-100 shadow-md" x-html="twoFactorData.qr_svg"></div>
                    <div class="text-left bg-slate-50 p-3 rounded-2xl border border-slate-200">
                        <div class="text-[10px] font-black uppercase text-slate-400">Kunci Rahasia Manual (Secret Key):</div>
                        <div class="text-xs font-mono font-bold text-[#8E1616] select-all break-all tracking-wider" x-text="twoFactorData.secret"></div>
                    </div>
                </div>

                <!-- Input OTP Konfirmasi -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700">Masukkan 6 Digit Kode dari Aplikasi untuk Konfirmasi: *</label>
                    <input type="text" 
                           x-model="twoFactorOtpInput" 
                           maxlength="6" 
                           placeholder="000000" 
                           class="w-full text-center text-2xl font-mono font-black tracking-[0.4em] px-4 py-3 rounded-2xl border-2 border-slate-200 focus:border-[#8E1616] focus:ring-2 focus:ring-[#8E1616]/20 outline-none">
                    <p x-show="twoFactorError" x-text="twoFactorError" class="text-xs text-rose-600 font-bold text-center mt-1"></p>
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2.5 border-t border-slate-100">
                <button @click="modal2faSetup = false" type="button" class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs uppercase cursor-pointer">Batal</button>
                <button @click="submit2faEnable()" :disabled="twoFactorLoading" type="button" class="px-5 sm:px-6 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-gradient-to-r from-[#8E1616] to-[#1D1616] text-white font-bold text-xs uppercase shadow-md hover:opacity-95 cursor-pointer">
                    <span x-text="twoFactorLoading ? 'Memverifikasi...' : 'Konfirmasi & Aktifkan ➔'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ================= MODAL SUKSES & RECOVERY CODES ================= -->
    <div x-show="modal2faSuccess" 
         x-cloak 
         class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modal2faSuccess = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-lg w-full shadow-2xl border border-slate-200 space-y-4 relative">
            
            <div class="text-center space-y-2">
                <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-2xl font-black">✓</div>
                <h4 class="text-base sm:text-lg font-black text-[#1D1616]">2FA Berhasil Diaktifkan!</h4>
                <p class="text-xs text-slate-500">Simpan 8 Kode Cadangan (Recovery Codes) berikut di tempat aman. Setiap kode hanya bisa digunakan 1 kali jika Anda kehilangan HP.</p>
            </div>

            <div class="p-3.5 bg-slate-900 text-emerald-400 font-mono text-xs rounded-2xl grid grid-cols-2 gap-2 text-center select-all">
                <template x-for="(code, idx) in twoFactorData.recovery_codes" :key="idx">
                    <div class="p-1.5 bg-slate-800/80 rounded-lg tracking-wider font-bold" x-text="(idx+1) + '. ' + code"></div>
                </template>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-2 pt-2">
                <button @click="copyRecoveryCodes()" type="button" class="w-full py-2.5 text-xs font-bold bg-slate-100 hover:bg-slate-200 text-[#1D1616] rounded-xl flex items-center justify-center gap-1.5 cursor-pointer">
                    📋 Salin Semua Kode
                </button>
                <button @click="downloadRecoveryCodes()" type="button" class="w-full py-2.5 text-xs font-bold bg-slate-100 hover:bg-slate-200 text-[#1D1616] rounded-xl flex items-center justify-center gap-1.5 cursor-pointer">
                    💾 Download (.TXT)
                </button>
                <button @click="modal2faSuccess = false" type="button" class="w-full py-2.5 text-xs font-bold bg-gradient-to-r from-[#8E1616] to-[#1D1616] text-white hover:opacity-95 rounded-xl cursor-pointer">
                    Selesai
                </button>
            </div>
        </div>
    </div>

    <!-- ================= MODAL DISABLE 2FA ================= -->
    <div x-show="modal2faDisable" 
         x-cloak 
         class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modal2faDisable = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-md w-full shadow-2xl border border-slate-200 space-y-4 relative">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h4 class="text-base font-black text-rose-700">Nonaktifkan 2FA</h4>
                <button @click="modal2faDisable = false" class="text-slate-400 hover:text-rose-700 text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <div class="space-y-3">
                <p class="text-xs text-slate-600">Masukkan kata sandi akun operator Anda untuk mengonfirmasi penonaktifan 2FA.</p>
                <div x-data="{ showPass: false }">
                    <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Kata Sandi Akun:</label>
                    <div class="relative">
                        <input :type="showPass ? 'text' : 'password'" x-model="twoFactorDisablePassword" class="w-full px-3.5 py-2.5 pr-10 text-xs rounded-xl border border-slate-200 focus:border-rose-600 focus:ring-1 focus:ring-rose-600 outline-none font-mono" placeholder="••••••••">
                        <button @click="showPass = !showPass" type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 p-1 cursor-pointer">
                            <svg x-show="!showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showPass" x-cloak class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                    <p x-show="twoFactorError" x-text="twoFactorError" class="text-xs text-rose-600 font-bold mt-1.5"></p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                <button @click="modal2faDisable = false" type="button" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">Batal</button>
                <button @click="submit2faDisable()" :disabled="twoFactorLoading" type="button" class="px-5 py-2 text-xs font-bold bg-rose-600 text-white rounded-xl hover:bg-rose-700 shadow-md cursor-pointer">
                    <span x-text="twoFactorLoading ? 'Memproses...' : 'Nonaktifkan 2FA'"></span>
                </button>
            </div>
        </div>
    </div>


    @endif

    <!-- ================= MODAL TAMBAH USER OPERATOR BARU ================= -->
    <div x-show="modalAddUser" 
         x-cloak 
         class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalAddUser = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-xl w-full shadow-2xl border border-slate-200 space-y-5 relative max-h-[90vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3.5">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-rose-600 to-[#1D1616] text-white flex items-center justify-center font-black">
                        +
                    </div>
                    <div>
                        <h4 class="text-base font-black text-[#1D1616]">Tambah User Operator Baru</h4>
                        <p class="text-[11px] text-slate-500">Buat akun operator dan centang ruangan yang boleh dikontrol</p>
                    </div>
                </div>
                <button @click="modalAddUser = false" type="button" class="text-slate-400 hover:text-rose-700 text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form action="{{ route('admin.users.store') }}" method="POST" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Nama Lengkap Operator: <span class="text-rose-600">*</span></label>
                        <input type="text" name="name" required class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-200 focus:border-rose-600 focus:ring-1 focus:ring-rose-600 outline-none" placeholder="Contoh: Ahmad Rizky">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Username / NIP Login: <span class="text-rose-600">*</span></label>
                        <input type="text" name="username" required class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-200 focus:border-rose-600 focus:ring-1 focus:ring-rose-600 outline-none font-mono" placeholder="Contoh: user1 / operator_koprasi">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div x-data="{ showPass: false }">
                        <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Kata Sandi Awal: <span class="text-rose-600">*</span></label>
                        <div class="relative">
                            <input :type="showPass ? 'text' : 'password'" name="password" required minlength="6" class="w-full px-3.5 py-2.5 pr-10 text-xs rounded-xl border border-slate-200 focus:border-rose-600 focus:ring-1 focus:ring-rose-600 outline-none font-mono" placeholder="Minimal 6 karakter">
                            <button @click="showPass = !showPass" type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 p-1 cursor-pointer transition-colors" title="Lihat / Sembunyikan Kata Sandi">
                                <svg x-show="!showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="showPass" x-cloak class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Divisi / Bagian:</label>
                        <input type="text" name="division" class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-200 focus:border-rose-600 focus:ring-1 focus:ring-rose-600 outline-none" placeholder="Divisi Fasilitas & Gedung" value="Divisi Sistem Informasi & Fasilitas">
                    </div>
                </div>

                <!-- CHECKBOX DAFTAR RUANGAN / DEVICE -->
                <div class="pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <label class="block text-[11px] font-black uppercase text-slate-800">Hak Akses Ruangan / Perangkat AC:</label>
                            <p class="text-[10px] text-slate-500">Pilih ruangan yang boleh dilihat & dikontrol saklar AC-nya oleh operator ini</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="selectAllNewDevices({{ json_encode($allFleetDeviceIds) }})" type="button" class="text-[10px] font-bold text-rose-700 hover:underline cursor-pointer">Pilih Semua</button>
                            <span class="text-slate-300 text-xs">|</span>
                            <button @click="clearAllNewDevices()" type="button" class="text-[10px] font-bold text-slate-500 hover:underline cursor-pointer">Kosongkan</button>
                        </div>
                    </div>

                    @if(empty($allFleetDevices) || count($allFleetDevices) === 0)
                        <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-800">
                            Belum ada perangkat armada yang terdaftar di sistem. Daftarkan perangkat di menu Home terlebih dahulu.
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-48 overflow-y-auto p-1">
                            @foreach($allFleetDevices as $fd)
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:border-rose-400 bg-slate-50/50 hover:bg-rose-50/30 transition-all cursor-pointer">
                                <input type="checkbox" 
                                       name="assigned_device_ids[]" 
                                       value="{{ $fd->device_id }}" 
                                       :checked="newOperatorDevices.includes('{{ $fd->device_id }}')"
                                       @change="if($event.target.checked) { if(!newOperatorDevices.includes('{{ $fd->device_id }}')) newOperatorDevices.push('{{ $fd->device_id }}') } else { newOperatorDevices = newOperatorDevices.filter(id => id !== '{{ $fd->device_id }}') }"
                                       class="mt-1 rounded text-rose-600 focus:ring-rose-500 w-4 h-4 cursor-pointer">
                                <div class="min-w-0">
                                    <div class="font-bold text-xs text-[#1D1616] truncate">{{ $fd->name ?: $fd->device_id }}</div>
                                    <div class="text-[10px] text-slate-500 truncate">📍 {{ $fd->location ?: 'PT PINDAD' }}</div>
                                    <div class="text-[9px] font-mono text-rose-700 font-semibold">{{ $fd->device_id }}</div>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                    <button @click="modalAddUser = false" type="button" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">Batal</button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold bg-gradient-to-r from-rose-700 to-[#1D1616] text-white rounded-xl hover:opacity-95 shadow-md cursor-pointer">
                        Simpan Akun Operator
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= MODAL EDIT HAK AKSES RUANGAN OPERATOR ================= -->
    <div x-show="modalEditUser" 
         x-cloak 
         class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalEditUser = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-xl w-full shadow-2xl border border-slate-200 space-y-5 relative max-h-[90vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3.5">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-slate-800 text-white flex items-center justify-center font-black">
                        ✎
                    </div>
                    <div>
                        <h4 class="text-base font-black text-[#1D1616]">Edit Hak Akses Operator</h4>
                        <p class="text-[11px] text-slate-500" x-text="'Operator: ' + selectedUserForEdit.name + ' (' + selectedUserForEdit.username + ')'"></p>
                    </div>
                </div>
                <button @click="modalEditUser = false" type="button" class="text-slate-400 hover:text-rose-700 text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form :action="editActionUrl" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Nama Lengkap Operator: <span class="text-rose-600">*</span></label>
                        <input type="text" name="name" x-model="selectedUserForEdit.name" required class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-200 focus:border-rose-600 focus:ring-1 focus:ring-rose-600 outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Divisi / Bagian:</label>
                        <input type="text" name="division" x-model="selectedUserForEdit.division" class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-200 focus:border-rose-600 focus:ring-1 focus:ring-rose-600 outline-none">
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <label class="block text-[11px] font-black uppercase text-slate-800">Hak Akses Ruangan / Perangkat AC:</label>
                            <p class="text-[10px] text-slate-500">Centang ruangan yang diizinkan untuk dikontrol oleh operator ini</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="selectAllEditDevices({{ json_encode($allFleetDeviceIds) }})" type="button" class="text-[10px] font-bold text-rose-700 hover:underline cursor-pointer">Pilih Semua</button>
                            <span class="text-slate-300 text-xs">|</span>
                            <button @click="clearAllEditDevices()" type="button" class="text-[10px] font-bold text-slate-500 hover:underline cursor-pointer">Kosongkan</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-48 overflow-y-auto p-1">
                        @foreach($allFleetDevices as $fd)
                        <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:border-rose-400 bg-slate-50/50 hover:bg-rose-50/30 transition-all cursor-pointer">
                            <input type="checkbox" 
                                   name="assigned_device_ids[]" 
                                   value="{{ $fd->device_id }}" 
                                   :checked="editOperatorDevices.includes('{{ $fd->device_id }}')"
                                   @change="if($event.target.checked) { if(!editOperatorDevices.includes('{{ $fd->device_id }}')) editOperatorDevices.push('{{ $fd->device_id }}') } else { editOperatorDevices = editOperatorDevices.filter(id => id !== '{{ $fd->device_id }}') }"
                                   class="mt-1 rounded text-rose-600 focus:ring-rose-500 w-4 h-4 cursor-pointer">
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-[#1D1616] truncate">{{ $fd->name ?: $fd->device_id }}</div>
                                <div class="text-[10px] text-slate-500 truncate">📍 {{ $fd->location ?: 'PT PINDAD' }}</div>
                                <div class="text-[9px] font-mono text-rose-700 font-semibold">{{ $fd->device_id }}</div>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                    <button @click="modalEditUser = false" type="button" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">Batal</button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold bg-slate-800 hover:bg-black text-white rounded-xl shadow-md cursor-pointer">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= MODAL RESET PASSWORD OPERATOR ================= -->
    <div x-show="modalResetPasswordUser" 
         x-cloak 
         class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalResetPasswordUser = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-md w-full shadow-2xl border border-slate-200 space-y-4 relative">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h4 class="text-base font-black text-amber-800">Ganti Kata Sandi Operator</h4>
                    <p class="text-[11px] text-slate-500" x-text="'Operator: ' + selectedUserForPassword.name + ' (' + selectedUserForPassword.username + ')'"></p>
                </div>
                <button @click="modalResetPasswordUser = false" type="button" class="text-slate-400 hover:text-rose-700 text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form :action="passwordActionUrl" method="POST" class="space-y-4">
                @csrf
                <div x-data="{ showPass: false }">
                    <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Kata Sandi Baru:</label>
                    <div class="relative">
                        <input :type="showPass ? 'text' : 'password'" name="password" required minlength="6" class="w-full px-3.5 py-2.5 pr-10 text-xs rounded-xl border border-slate-200 focus:border-amber-600 focus:ring-1 focus:ring-amber-600 outline-none font-mono" placeholder="Minimal 6 karakter">
                        <button @click="showPass = !showPass" type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 p-1 cursor-pointer transition-colors" title="Lihat / Sembunyikan Kata Sandi">
                            <svg x-show="!showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg x-show="showPass" x-cloak class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                    <button @click="modalResetPasswordUser = false" type="button" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">Batal</button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold bg-amber-600 text-white rounded-xl hover:bg-amber-700 shadow-md cursor-pointer">
                        Ubah Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= MODAL RESET 2FA OPERATOR ================= -->
    <div x-show="modalReset2faUser" 
         x-cloak 
         class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalReset2faUser = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-md w-full shadow-2xl border border-purple-200 space-y-4 relative">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-purple-100 text-purple-800 flex items-center justify-center font-bold text-sm">
                        🔄
                    </div>
                    <h4 class="text-base font-black text-purple-900">Reset 2FA Operator</h4>
                </div>
                <button @click="modalReset2faUser = false" class="text-slate-400 hover:text-purple-900 text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <div class="space-y-2">
                <p class="text-xs text-slate-700">Apakah Anda yakin ingin mereset kunci Google Authenticator untuk operator berikut?</p>
                <div class="p-3 bg-purple-50 rounded-xl border border-purple-100">
                    <div class="font-black text-xs text-purple-900" x-text="selectedUserForReset2fa.name"></div>
                    <div class="text-[11px] font-mono text-purple-700" x-text="'Username: ' + selectedUserForReset2fa.username"></div>
                </div>
                <p class="text-[11px] text-slate-500">
                    Setelah di-reset, status 2FA operator akan kembali menjadi <em>Belum Setup</em> dan operator akan diminta memindai QR Code baru saat login berikutnya.
                </p>
            </div>

            <form :action="reset2faActionUrl" method="POST" class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                @csrf
                <button @click="modalReset2faUser = false" type="button" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">Batal</button>
                <button type="submit" class="px-5 py-2 text-xs font-bold bg-purple-800 text-white rounded-xl hover:bg-purple-900 shadow-md cursor-pointer flex items-center gap-1.5">
                    <span>Konfirmasi Reset 2FA</span>
                </button>
            </form>
        </div>
    </div>

    <!-- ================= MODAL KONFIRMASI HAPUS OPERATOR ================= -->
    <div x-show="modalDeleteUser" 
         x-cloak 
         class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalDeleteUser = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-md w-full shadow-2xl border border-slate-200 space-y-4 relative">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h4 class="text-base font-black text-rose-700">Hapus Akun Operator</h4>
                <button @click="modalDeleteUser = false" class="text-slate-400 hover:text-rose-700 text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <div class="space-y-2">
                <p class="text-xs text-slate-700">Apakah Anda yakin ingin menghapus akun operator berikut dari sistem?</p>
                <div class="p-3 bg-rose-50 rounded-xl border border-rose-100">
                    <div class="font-black text-xs text-rose-900" x-text="selectedUserForDelete.name"></div>
                    <div class="text-[11px] font-mono text-rose-700" x-text="'Username: ' + selectedUserForDelete.username"></div>
                </div>
                <p class="text-[11px] text-slate-500">Operator yang dihapus tidak akan dapat login kembali ke sistem pemantauan.</p>
            </div>

            <form :action="deleteActionUrl" method="POST" class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                @csrf
                @method('DELETE')
                <button @click="modalDeleteUser = false" type="button" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">Batal</button>
                <button type="submit" class="px-5 py-2 text-xs font-bold bg-rose-600 text-white rounded-xl hover:bg-rose-700 shadow-md cursor-pointer">
                    Ya, Hapus Akun
                </button>
            </form>
        </div>
    </div>


    <!-- ================= MODAL SETUJUI TIKET PENGAJUAN (ADMIN) ================= -->
    <div x-show="modalApproveTicket" 
         x-cloak 
         class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalApproveTicket = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-md w-full shadow-2xl border border-emerald-200 space-y-4 relative">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-sm">
                        ✓
                    </div>
                    <h4 class="text-base font-black text-emerald-900">Setujui Pengajuan Perangkat</h4>
                </div>
                <button @click="modalApproveTicket = false" class="text-slate-400 hover:text-emerald-900 text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <div class="space-y-2">
                <p class="text-xs text-slate-700">Setujui permohonan dari operator <strong class="text-[#1D1616]" x-text="selectedTicketForApprove.operator_name"></strong> untuk ruangan <strong class="text-emerald-800" x-text="selectedTicketForApprove.room_name"></strong>?</p>
                
                <form :action="approveTicketUrl" method="POST" class="space-y-3 pt-2">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Hubungkan ke Perangkat / Ruangan Terpasang (Opsional):</label>
                        <select name="assigned_device_id" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 outline-none">
                            <option value="">-- Hubungkan Nanti / Pengadaan Fisik Baru --</option>
                            @foreach($allFleetDevices ?? [] as $fd)
                                <option value="{{ $fd->device_id }}">{{ $fd->name }} ({{ $fd->location }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Catatan Persetujuan / Hasil Survei Lapangan:</label>
                        <textarea name="admin_notes" rows="2" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 outline-none resize-none" placeholder="Contoh: Disetujui setelah survei kelayakan listrik & AC di lokasi."></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                        <button @click="modalApproveTicket = false" type="button" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">Batal</button>
                        <button type="submit" class="px-5 py-2 text-xs font-bold bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 shadow-md cursor-pointer flex items-center gap-1.5">
                            <span>Konfirmasi Setujui</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================= MODAL TOLAK TIKET PENGAJUAN (ADMIN) ================= -->
    <div x-show="modalRejectTicket" 
         x-cloak 
         class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-6 pb-28 sm:pb-6 bg-black/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        
        <div @click.away="modalRejectTicket = false" 
             class="bg-white rounded-[28px] sm:rounded-[36px] p-5 sm:p-7 max-w-md w-full shadow-2xl border border-rose-200 space-y-4 relative">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-800 flex items-center justify-center font-bold text-sm">
                        ✕
                    </div>
                    <h4 class="text-base font-black text-rose-900">Tolak Pengajuan Perangkat</h4>
                </div>
                <button @click="modalRejectTicket = false" class="text-slate-400 hover:text-rose-900 text-2xl font-bold cursor-pointer">&times;</button>
            </div>

            <form :action="rejectTicketUrl" method="POST" class="space-y-3 pt-2">
                @csrf
                <p class="text-xs text-slate-700">Tolak permohonan dari operator <strong class="text-[#1D1616]" x-text="selectedTicketForReject.operator_name"></strong> untuk ruangan <strong class="text-rose-800" x-text="selectedTicketForReject.room_name"></strong>?</p>
                
                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-700 mb-1">Alasan Penolakan: <span class="text-rose-600">*</span></label>
                    <textarea name="admin_notes" required rows="2" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:border-rose-600 focus:ring-1 focus:ring-rose-600 outline-none resize-none" placeholder="Contoh: Lokasi belum siap instalasi / kapasitas daya tidak mencukupi."></textarea>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                    <button @click="modalRejectTicket = false" type="button" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">Batal</button>
                    <button type="submit" class="px-5 py-2 text-xs font-bold bg-rose-700 text-white rounded-xl hover:bg-rose-800 shadow-md cursor-pointer flex items-center gap-1.5">
                        <span>Konfirmasi Tolak</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
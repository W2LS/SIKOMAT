<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktivasi 2FA Wajib — Sistem Monitoring AC IoT PT PINDAD</title>
    
    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        pindad: {
                            dark: '#1D1616',
                            maroon: '#8E1616',
                            red: '#D84040',
                            light: '#EEEEEE'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace']
                    }
                }
            }
        }
    </script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        .otp-input {
            letter-spacing: 10px;
        }
        .otp-input::placeholder {
            letter-spacing: 2px;
        }
    </style>
</head>
<body class="min-h-full bg-gradient-to-br from-[#1D1616] via-[#4a0c0c] to-[#8E1616] text-slate-800 flex items-center justify-center p-3 sm:p-5 font-sans">

    <!-- COMPACT CARD CONTAINER (SEUKURAN MODAL SIKOMAT) -->
    <div class="w-full max-w-md bg-white rounded-[32px] sm:rounded-[36px] shadow-2xl border border-white/20 p-5 sm:p-6 space-y-4 relative overflow-hidden" x-data="{ showCodes: false }">
        
        <!-- HEADER -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-3.5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-rose-100 text-[#8E1616] flex items-center justify-center font-black text-xl shrink-0 shadow-xs">
                    🔐
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm sm:text-base font-black text-[#1D1616] leading-tight">Aktivasi 2FA Wajib</h3>
                        <span class="bg-[#8E1616] text-white text-[8px] font-black uppercase px-2 py-0.5 rounded-full tracking-wider shrink-0">SECURITY</span>
                    </div>
                    <p class="text-[11px] text-slate-500 font-medium truncate mt-0.5">
                        Hubungkan akun <strong class="text-[#1D1616]">{{ $userName }}</strong> ({{ $nip }})
                    </p>
                </div>
            </div>
            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse shrink-0" title="Koneksi Aman"></div>
        </div>

        @if(isset($errors) && $errors->has('setup_error'))
            <div class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ $errors->first('setup_error') }}</span>
            </div>
        @endif

        <!-- STEP 1: SCAN QR CODE -->
        <div class="space-y-2 text-center">
            <p class="text-[11px] text-slate-500 font-medium">
                Pindai QR Code ini dengan aplikasi <strong>Google Authenticator</strong>:
            </p>
            
            <!-- QR CODE BOX (CENTERED & CLEAN) -->
            <div class="inline-flex items-center justify-center p-2.5 bg-white rounded-2xl border border-slate-200 shadow-sm mx-auto">
                <div class="w-[170px] h-[170px] flex items-center justify-center [&>svg]:w-full [&>svg]:h-full [&>svg]:rounded-xl">
                    {!! $qrSvg !!}
                </div>
            </div>

            <!-- SECRET KEY MANUAL -->
            <div class="bg-slate-50 p-2 sm:p-2.5 rounded-xl border border-slate-200 flex items-center justify-between gap-2 text-left">
                <div class="min-w-0 flex-1">
                    <div class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Kunci Rahasia Manual:</div>
                    <div class="text-xs font-mono font-bold text-[#8E1616] tracking-wider select-all truncate" id="secretKeyText">{{ $secret }}</div>
                </div>
                <button type="button" onclick="copySecretKey()" class="px-2 py-1 text-[11px] font-bold bg-white hover:bg-slate-100 text-slate-700 rounded-lg border border-slate-200 flex items-center gap-1 shrink-0 cursor-pointer shadow-2xs transition-colors" title="Salin Kunci">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span>Salin</span>
                </button>
            </div>
        </div>

        <!-- STEP 2: RECOVERY CODES (COMPACT ACCORDION) -->
        <div class="border-t border-slate-100 pt-2 space-y-1.5">
            <div class="flex items-center justify-between">
                <button type="button" @click="showCodes = !showCodes" class="text-[11px] font-bold text-slate-600 hover:text-[#8E1616] flex items-center gap-1.5 cursor-pointer">
                    <span>🔑 8 Kode Pemulihan Darurat</span>
                    <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="showCodes ? 'rotate-180 text-[#8E1616]' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <button type="button" onclick="copyAllRecoveryCodes()" class="text-[10px] font-bold text-rose-700 hover:underline cursor-pointer">
                    Salin Semua
                </button>
            </div>
            <div x-show="showCodes" x-cloak class="grid grid-cols-2 gap-1.5 bg-slate-50 p-2.5 rounded-xl border border-slate-200 font-mono text-[10px] text-center text-slate-700">
                @foreach($recoveryCodes as $code)
                    <div class="bg-white py-1 px-1 rounded-md border border-slate-100 font-bold select-all">{{ $code }}</div>
                @endforeach
            </div>
        </div>

        <!-- STEP 3: OTP CONFIRMATION FORM -->
        <div class="border-t border-slate-100 pt-2.5">
            <form action="{{ route('login.2fa.setup.confirm') }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-[11px] font-black uppercase text-slate-700 tracking-wider mb-1 text-center">
                        Masukkan 6-Digit Kode dari Aplikasi:
                    </label>
                    <input 
                        type="text" 
                        name="code" 
                        id="otpCodeInput" 
                        class="w-full text-center font-mono text-xl sm:text-2xl font-black py-2 rounded-xl sm:rounded-2xl border-2 border-[#8E1616] focus:ring-2 focus:ring-[#8E1616] outline-none text-[#1D1616] otp-input shadow-inner" 
                        placeholder="000000" 
                        maxlength="6" 
                        pattern="[0-9]{6}" 
                        inputmode="numeric" 
                        autocomplete="one-time-code" 
                        required 
                        autofocus>
                </div>

                <button type="submit" class="w-full py-2.5 sm:py-3 rounded-xl sm:rounded-2xl bg-gradient-to-r from-[#8E1616] to-[#1D1616] text-white font-bold text-xs uppercase shadow-md hover:opacity-95 active:scale-[0.99] transition-all cursor-pointer flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Verifikasi & Masuk Dashboard</span>
                </button>
            </form>

            <!-- CANCEL / LOGOUT BUTTON -->
            <form action="{{ route('login.2fa.cancel') }}" method="POST" class="text-center pt-2">
                @csrf
                <button type="submit" class="text-xs text-slate-400 hover:text-slate-600 font-semibold cursor-pointer">
                    &larr; Batalkan & Keluar
                </button>
            </form>
        </div>

    </div>

    <!-- TOAST NOTIFICATION -->
    <div id="copyToast" class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-[#1D1616] text-white text-xs font-semibold px-4 py-2 rounded-xl shadow-xl border border-white/10 hidden z-50">
        Teks berhasil disalin!
    </div>

    <script>
    function showToast(msg) {
        const t = document.getElementById('copyToast');
        t.innerText = msg;
        t.classList.remove('hidden');
        setTimeout(() => { t.classList.add('hidden'); }, 2500);
    }

    function copySecretKey() {
        const text = document.getElementById('secretKeyText').innerText.trim();
        navigator.clipboard.writeText(text).then(() => {
            showToast('Kunci rahasia 2FA berhasil disalin!');
        }).catch(() => {
            alert('Gagal menyalin. Silakan salin manual: ' + text);
        });
    }

    function copyAllRecoveryCodes() {
        const codes = @json($recoveryCodes);
        const text = "SIKOMAT PT PINDAD - Kode Pemulihan 2FA (" + "{{ $nip }}" + "):\n" + codes.join("\n");
        navigator.clipboard.writeText(text).then(() => {
            showToast('8 Kode pemulihan berhasil disalin!');
        }).catch(() => {
            alert('Gagal menyalin kode pemulihan.');
        });
    }

    // Auto-submit when 6 digits are typed
    const input = document.getElementById('otpCodeInput');
    if (input) {
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
    }
    </script>

</body>
</html>

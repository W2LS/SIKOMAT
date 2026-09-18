<!DOCTYPE html>
<html lang="id" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi 2FA â€” Sistem Monitoring AC IoT PT PINDAD</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    
    <style>
        :root {
            --pindad-dark: #1D1616;
            --pindad-maroon: #8E1616;
            --pindad-red: #D84040;
            --pindad-light: #EEEEEE;
        }

        html, body {
            height: 100vh;
            max-height: 100vh;
            overflow: hidden;
            background: linear-gradient(135deg, #1D1616 0%, #8E1616 50%, #D84040 100%);
            color: #ffffff;
            font-family: 'Inter', sans-serif;
        }

        .login-wrapper {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-login {
            background: #ffffff;
            color: #1D1616;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: none;
        }

        .btn-pindad {
            background: linear-gradient(90deg, #1D1616 0%, #8E1616 50%, #D84040 100%);
            color: #ffffff;
            border: none;
            font-weight: 800;
            letter-spacing: 0.5px;
            padding: 12px 20px;
            border-radius: 14px;
            transition: all 0.3s ease;
        }

        .btn-pindad:hover {
            opacity: 0.92;
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(216, 64, 64, 0.4);
        }

        .otp-input {
            width: 48px;
            height: 56px;
            font-size: 24px;
            font-weight: 800;
            text-align: center;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            transition: all 0.2s;
            color: #1D1616;
        }

        .otp-input:focus {
            border-color: #8E1616;
            box-shadow: 0 0 0 0.2rem rgba(142, 22, 22, 0.25);
            outline: none;
        }

        .badge-shield {
            background: rgba(142, 22, 22, 0.1);
            color: #8E1616;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .fs-8 {
            font-size: 11px;
        }
    </style>
</head>
<body class="h-100 overflow-hidden">

<div class="login-wrapper container-fluid px-3 px-md-5">
    <div class="row w-100 justify-content-center align-items-center g-4 max-w-6xl">
        
        <!-- LEFT SIDE: BRAND HERO -->
        <div class="col-12 col-md-6 text-white px-md-4">
            <div class="text-white fw-bold text-uppercase mb-3" style="letter-spacing: 2px; font-size: 11px; opacity: 0.95;">
                PT PINDAD (PERSERO) â€” SIKOMAT SECURITY
            </div>

            <div class="mb-4">
                <img src="{{ asset('SIKOMAT.png') }}" 
                     alt="SIKOMAT Logo PT PINDAD" 
                     class="img-fluid select-none"
                     style="max-height: 85px; width: auto; filter: brightness(0) invert(1);">
            </div>

            <div class="d-flex align-items-center gap-3 text-white-50 mt-2">
                <span class="badge badge-shield text-white" style="background: rgba(255,255,255,0.2);">
                    🔒 TOTP RFC 6238 Standard
                </span>
                <span class="fs-8">Rate Limiter Active</span>
            </div>
        </div>

        <!-- RIGHT SIDE: 2FA CARD -->
        <div class="col-12 col-md-6 col-lg-5 col-xl-4">
            <div class="card card-login p-3 p-sm-4">
                <div class="card-body p-2">
                    
                    <div class="text-center mb-3">
                        <div class="badge-shield mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shield-lock" viewBox="0 0 16 16">
                              <path d="M5.338 1.59a61 61 0 0 0-2.837.856.48.48 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.7 10.7 0 0 0 2.287 2.233c.346.244.652.42.893.533q.18.085.293.118a1 1 0 0 0 .101.025 1 1 0 0 0 .1-.025q.114-.034.294-.118c.24-.113.546-.29.893-.533a10.7 10.7 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.596 4.477-.787 7.795-2.465 9.99a11.8 11.8 0 0 1-2.517 2.453 7 7 0 0 1-1.048.625c-.28.132-.581.24-.829.24s-.548-.108-.829-.24a7 7 0 0 1-1.048-.625 11.8 11.8 0 0 1-2.517-2.453C1.928 10.487.545 7.169 1.141 2.692A1.54 1.54 0 0 1 2.185 1.43 63 63 0 0 1 5.072.56"/>
                              <path d="M9.5 6.5a1.5 1.5 0 0 1-1 1.415v2.585a.5.5 0 0 1-1 0V7.915a1.5 1.5 0 1 1 2-1.415"/>
                            </svg>
                            Two-Factor Authentication
                        </div>
                        <h2 class="h5 fw-black text-dark mb-1">Verifikasi Keamanan</h2>
                        <p class="fs-7 text-secondary m-0">Masukkan 6 digit kode dari <strong>Google Authenticator</strong> untuk akun <span class="text-dark fw-bold">{{ $nip }}</span></p>
                    </div>

                    <!-- ERROR NOTIFICATIONS -->
                    @if($errors->has('2fa_error'))
                        <div class="alert alert-danger p-2 fs-7 font-semibold rounded-3 mb-3 border-0 text-center" role="alert">
                            ⚠️ {{ $errors->first('2fa_error') }}
                        </div>
                    @endif

                    @if(session('success'))
                        <div class="alert alert-success p-2 fs-7 font-semibold rounded-3 mb-3 border-0 text-center" role="alert">
                            ✓ {{ session('success') }}
                        </div>
                    @endif

                    <!-- FORM 2FA -->
                    <form action="{{ route('login.2fa.verify') }}" method="POST" id="form2fa">
                        @csrf

                        <!-- Hidden single input yang dikirim ke backend -->
                        <input type="hidden" name="code" id="fullCode">

                        <!-- Mode TOTP: 6 Digit Boxes -->
                        <div id="totpSection">
                            <div class="d-flex justify-content-center gap-2 mb-3">
                                <input type="text" class="otp-input form-control" maxlength="1" inputmode="numeric" pattern="[0-9]*" autofocus autocomplete="off">
                                <input type="text" class="otp-input form-control" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                                <input type="text" class="otp-input form-control" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                                <input type="text" class="otp-input form-control" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                                <input type="text" class="otp-input form-control" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                                <input type="text" class="otp-input form-control" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                            </div>
                        </div>

                        <!-- Mode Recovery Code (Tersembunyi by default) -->
                        <div id="recoverySection" style="display: none;" class="mb-3">
                            <label class="form-label fs-7 fw-bold text-secondary mb-1">Kode Cadangan (Recovery Code)</label>
                            <input type="text" class="form-control py-2 fs-6 text-center font-monospace rounded-3 text-uppercase fw-bold" id="recoveryInput" placeholder="CONTOH: ABCD-1234">
                            <p class="fs-8 text-muted mt-1 mb-0 text-center">Gunakan salah satu dari 8 kode cadangan satu-kali-pakai Anda.</p>
                        </div>

                        <div class="d-grid mb-2">
                            <button class="btn btn-pindad fs-7" type="submit" id="btnSubmit">
                                Verifikasi & Masuk ➔
                            </button>
                        </div>

                        <!-- Switch Mode Button -->
                        <div class="text-center mt-2">
                            <button type="button" class="btn btn-link p-0 text-decoration-none fs-8 text-secondary" id="toggleRecoveryBtn">
                                🔑 Masuk dengan Kode Cadangan (Recovery Code)
                            </button>
                        </div>

                    </form>

                    <!-- Cancel Form -->
                    <form action="{{ route('login.2fa.cancel') }}" method="POST" class="text-center mt-3">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fs-8">
                            ← Batal & Kembali ke Login
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <p class="m-0 text-secondary fs-8">
                            © 2026 PT PINDAD (Persero)
                        </p>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('.otp-input');
    const fullCode = document.getElementById('fullCode');
    const form = document.getElementById('form2fa');
    const totpSection = document.getElementById('totpSection');
    const recoverySection = document.getElementById('recoverySection');
    const recoveryInput = document.getElementById('recoveryInput');
    const toggleBtn = document.getElementById('toggleRecoveryBtn');
    let isRecoveryMode = false;

    // Auto-advance & paste handler for 6-digit OTP
    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            const val = e.target.value.replace(/[^0-9]/g, '');
            e.target.value = val ? val[0] : '';

            if (e.target.value && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }

            checkAndAutoSubmit();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
            }
        });

        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text').trim().replace(/[^0-9]/g, '');
            if (pasteData.length >= 6) {
                inputs.forEach((inp, i) => {
                    inp.value = pasteData[i] || '';
                });
                inputs[5].focus();
                checkAndAutoSubmit();
            }
        });
    });

    function checkAndAutoSubmit() {
        let code = '';
        inputs.forEach(inp => code += inp.value);
        if (code.length === 6) {
            fullCode.value = code;
            form.submit();
        }
    }

    form.addEventListener('submit', (e) => {
        if (isRecoveryMode) {
            fullCode.value = recoveryInput.value.trim();
        } else {
            let code = '';
            inputs.forEach(inp => code += inp.value);
            fullCode.value = code;
        }

        if (!fullCode.value) {
            e.preventDefault();
            alert('Silakan masukkan kode autentikasi terlebih dahulu.');
        }
    });

    // Toggle Recovery Mode
    toggleBtn.addEventListener('click', () => {
        isRecoveryMode = !isRecoveryMode;
        if (isRecoveryMode) {
            totpSection.style.display = 'none';
            recoverySection.style.display = 'block';
            toggleBtn.textContent = '📱 Masuk dengan Aplikasi Authenticator (6 Digit)';
            recoveryInput.focus();
        } else {
            recoverySection.style.display = 'none';
            totpSection.style.display = 'block';
            toggleBtn.textContent = '🔑 Masuk dengan Kode Cadangan (Recovery Code)';
            inputs[0].focus();
        }
    });
});
</script>

</body>
</html>

{{-- 忘記密碼：輸入註冊帳號時填的 Email，系統會寄臨時密碼過去（對應 PasswordRecoveryController）。樣式和登入頁一樣。 --}}
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>忘記密碼 - 學校設備維保電子化系統</title>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/theme-b.css') }}?v={{ filemtime(public_path('css/theme-b.css')) }}" rel="stylesheet">
    <style>
        .login-band { background: var(--tb-accent); color: #FFFFFF; padding: 14px 24px; }
        .login-band .brand { font-family: var(--tb-font-heading, 'Noto Serif TC', serif); font-weight: 700; font-size: 1.25rem; letter-spacing: 0.04em; }
        .login-card { width: 100%; max-width: 420px; border-radius: 20px; }
        .login-title { font-family: var(--tb-font-heading, 'Noto Serif TC', serif); font-weight: 700; letter-spacing: 0.02em; }
        .login-card .form-control { min-height: 46px; }
        .login-card .btn-primary { min-height: 48px; font-size: 1.05rem; }
    </style>
</head>
<body class="tb-body">
    <div class="login-band"><span class="brand">學校設備維保電子化系統</span></div>
    <div class="container d-flex align-items-center justify-content-center py-5" style="min-height: calc(100vh - 60px);">
        <div class="card shadow-sm login-card">
            <div class="card-body p-4 p-sm-5">
                <h1 class="h3 login-title text-center mb-1">忘記密碼</h1>
                <p class="text-center text-muted mb-4">輸入註冊帳號時填的 Email，我們會寄一組臨時密碼給你。</p>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">註冊時的 Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" autocomplete="email" autofocus required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">寄送臨時密碼</button>
                </form>

                <div class="small text-muted mt-4">
                    <div class="mb-1">1. 到信箱收信（沒收到請看垃圾郵件匣），臨時密碼 {{ \App\Http\Controllers\Auth\PasswordRecoveryController::TEMPORARY_PASSWORD_MINUTES }} 分鐘內有效。</div>
                    <div class="mb-1">2. 回到登入頁，用帳號與臨時密碼登入。</div>
                    <div>3. 登入後系統會請你設定新密碼。</div>
                </div>
                <div class="text-center mt-4"><a href="{{ route('login') }}">回到登入頁</a></div>
            </div>
        </div>
    </div>
    @include('layouts.partials.form-guard')
</body>
</html>

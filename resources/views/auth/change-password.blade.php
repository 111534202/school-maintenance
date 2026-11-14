{{-- 設定新密碼（對應 PasswordRecoveryController::showChangeForm / change）。
     用臨時密碼登入後會被帶到這裡，而且在設定好之前哪裡都不能去（$forced＝true）；平常登入的人也可以自己來改。 --}}
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>設定新密碼 - 學校設備維保電子化系統</title>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/theme-b.css') }}?v={{ filemtime(public_path('css/theme-b.css')) }}" rel="stylesheet">
    <style>
        .login-band { background: var(--tb-accent); color: #FFFFFF; padding: 14px 24px; }
        .login-band .brand { font-family: var(--tb-font-heading, 'Noto Serif TC', serif); font-weight: 700; font-size: 1.25rem; letter-spacing: 0.04em; }
        .login-card { width: 100%; max-width: 420px; border-radius: 20px; }
        .login-title { font-family: var(--tb-font-heading, 'Noto Serif TC', serif); font-weight: 700; letter-spacing: 0.02em; }
        .login-card .form-control { min-height: 46px; }
        .login-card .btn { min-height: 48px; }
    </style>
</head>
<body class="tb-body">
    <div class="login-band"><span class="brand">學校設備維保電子化系統</span></div>
    <div class="container d-flex align-items-center justify-content-center py-5" style="min-height: calc(100vh - 60px);">
        <div class="card shadow-sm login-card">
            <div class="card-body p-4 p-sm-5">
                <h1 class="h3 login-title text-center mb-1">設定新密碼</h1>
                @if ($forced)
                    <p class="text-center text-muted mb-4">你是用臨時密碼登入的。請先設定一組新密碼，之後才能使用系統。</p>
                @else
                    <p class="text-center text-muted mb-4">設定新密碼後，其他裝置上的登入會被登出。</p>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="password" class="form-label">新密碼</label>
                        <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" autofocus required>
                        <div class="form-text">至少 8 個字元。</div>
                    </div>
                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">再輸入一次新密碼</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">儲存新密碼</button>
                </form>

                {{-- 不想現在設定：登出（用臨時密碼登入的人下次要重新按「忘記密碼」）。 --}}
                @if ($forced)
                    <form method="POST" action="{{ route('logout') }}" class="text-center mt-3" data-no-guard>
                        @csrf
                        <button type="submit" class="btn btn-link">先登出，晚點再設定</button>
                    </form>
                @else
                    <div class="text-center mt-3"><a href="{{ route('dashboard') }}">回到首頁</a></div>
                @endif
            </div>
        </div>
    </div>
    @include('layouts.partials.form-guard')
</body>
</html>

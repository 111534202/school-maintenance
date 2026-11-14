{{-- 登入頁：不套用主版面（因為還沒登入，不需要側邊選單），自己是一份完整的 HTML。 --}}
{{-- 表單送到 POST /login，由 LoginController::login 處理；帳號欄位可以填「帳號名稱」或「Email」。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>登入 - 學校設備維保電子化系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- 跟登入後的頁面同一套風格 B（墨綠主色、暖白底、思源黑體／宋體），見 public/css/theme-b.css。 --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&family=Noto+Serif+TC:wght@600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/theme-b.css') }}?v={{ filemtime(public_path('css/theme-b.css')) }}" rel="stylesheet">
    <style>
        /* 登入頁：上方一條墨綠色的網站名稱列，下方暖白底置中一張圓角卡片。 */
        .login-band { background: var(--tb-accent); color: #FFFFFF; padding: 14px 24px; }
        .login-band .brand { font-family: var(--tb-font-heading, 'Noto Serif TC', serif); font-weight: 700; font-size: 1.25rem; letter-spacing: 0.04em; }
        .login-card { width: 100%; max-width: 420px; border-radius: 20px; }
        .login-title { font-family: var(--tb-font-heading, 'Noto Serif TC', serif); font-weight: 700; letter-spacing: 0.02em; }
        .login-card .form-control { min-height: 46px; }
        .login-card .btn-primary { min-height: 48px; font-size: 1.05rem; }
        .login-card .login-schedule-btn { min-height: 46px; display: flex; align-items: center; justify-content: center; }
        .login-divider { display: flex; align-items: center; gap: 12px; color: #8a8a8a; font-size: .875rem; }
        .login-divider::before, .login-divider::after { content: ''; flex: 1; border-top: 1px solid #e3ded3; }
        /* 密碼欄右邊的小眼睛按鈕：跟輸入框一樣高、一樣的底色與框線，看起來是同一個欄位。 */
        .login-card .pw-group .form-control { border-right: 0; }
        .login-card .pw-toggle { min-width: 46px; border: 1px solid var(--bs-border-color); border-left: 0; background: var(--bs-body-bg); color: #6c757d; }
        .login-card .pw-toggle:hover, .login-card .pw-toggle:focus { background: var(--bs-body-bg); color: var(--tb-accent); }
    </style>
</head>
<body class="tb-body">
    <div class="login-band"><span class="brand">學校設備維保電子化系統</span></div>
    {{-- 整個畫面垂直、水平置中，把登入卡片放在螢幕正中間。 --}}
    <div class="container d-flex align-items-center justify-content-center py-5" style="min-height: calc(100vh - 60px);">
        {{-- 登入卡片：手機上佔滿寬度，大螢幕最寬 400px。 --}}
        <div class="card shadow-sm login-card">
            <div class="card-body p-4 p-sm-5">
                <h1 class="h3 login-title text-center mb-1">登入</h1>
                <p class="text-center text-muted mb-4">請使用學校帳號登入</p>

                {{-- 登入失敗或欄位沒填時，把錯誤訊息顯示在這裡。 --}}
                @if ($errors->any())
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                {{-- 登入表單：送出到名為 login 的 POST 路由（routes/web.php）。 --}}
                <form method="POST" action="{{ route('login') }}">
                    {{-- 表單防偽 token（CSRF）：Laravel 要求所有 POST 表單都要有。 --}}
                    @csrf
                    <div class="mb-3">
                        <label for="login" class="form-label">帳號</label>
                        {{-- 帳號欄位；old('login') 會在登入失敗導回時，自動帶回剛剛輸入的帳號（密碼則不會帶回）。 --}}
                        <input type="text" class="form-control" id="login" name="login" value="{{ old('login') }}" placeholder="帳號名稱或 Email" autocomplete="account" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">密碼</label>
                        {{-- 密碼欄位：type=password 會把輸入內容顯示成圓點。 --}}
                        {{-- 右邊的小眼睛：按一下顯示輸入的密碼，再按一下藏回去（只是切換顯示方式，不會送出任何東西）。 --}}
                        <div class="input-group pw-group">
                            <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                            <button type="button" class="btn pw-toggle" id="passwordToggle" aria-label="顯示密碼" title="顯示密碼"><i class="bi bi-eye" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        {{-- 「記住我」：勾選後，瀏覽器關閉再開也維持登入狀態。 --}}
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">記住我</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">登入</button>
                </form>
                {{-- 不用登入也能查詢教室課表（/schedule）；要預約時再登入。 --}}
                <div class="login-divider my-4"><span>或</span></div>
                <a href="{{ route('schedule') }}" class="btn btn-outline-primary w-100 login-schedule-btn">查詢課表</a>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var input = document.getElementById('password');
            var button = document.getElementById('passwordToggle');
            button.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                var label = show ? '隱藏密碼' : '顯示密碼';
                button.setAttribute('aria-label', label);
                button.title = label;
                button.innerHTML = '<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '" aria-hidden="true"></i>';
            });
        })();
    </script>
</body>
</html>

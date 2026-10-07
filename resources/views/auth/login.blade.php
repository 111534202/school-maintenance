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
</head>
<body class="bg-light">
    {{-- 整個畫面垂直、水平置中，把登入卡片放在螢幕正中間。 --}}
    <div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        {{-- 登入卡片：手機上佔滿寬度，大螢幕最寬 400px。 --}}
        <div class="card shadow-sm" style="width: 100%; max-width: 400px;">
            <div class="card-body p-4">
                <h4 class="card-title text-center mb-4">學校設備維保電子化系統</h4>

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
                        <input type="text" class="form-control" id="login" name="login" value="{{ old('login') }}" placeholder="帳號名稱或 Email" autocomplete="username" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">密碼</label>
                        {{-- 密碼欄位：type=password 會把輸入內容顯示成圓點。 --}}
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3 form-check">
                        {{-- 「記住我」：勾選後，瀏覽器關閉再開也維持登入狀態。 --}}
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">記住我</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">登入</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

{{-- 錯誤頁共用外框（403、404、419、500、503 都用它；413 另外有自己的頁面）。
     不依賴外部網站的樣式（網路有問題時也要看得到），不依賴登入狀態（出錯時 session 不一定可用）。
     子頁面提供：$code（錯誤代碼）、title（標題）、body（說明）、actions（額外按鈕，可不填）。 --}}
@php
    // 出錯時登入狀態不一定讀得到，讀不到就當成沒登入。
    try { $errorLoggedIn = auth()->check(); } catch (\Throwable $e) { $errorLoggedIn = false; }
@endphp
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - 學校設備維保電子化系統</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #F7F5F0; color: #2B2A26;
               font-family: 'Noto Sans TC', system-ui, -apple-system, 'Microsoft JhengHei', sans-serif; padding: 16px; }
        .box { max-width: 480px; width: 100%; background: #fff; border: 1px solid #E7E1D6; border-radius: 18px; padding: 28px 32px; }
        .code { color: #8A8578; font-size: .85rem; letter-spacing: .08em; margin-bottom: 4px; }
        h1 { font-size: 1.4rem; margin: 0 0 12px; }
        p { margin: 0 0 12px; line-height: 1.75; }
        .hint { color: #6B665A; font-size: .92rem; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 22px; border-radius: 999px; font-size: 1rem; text-decoration: none; cursor: pointer; border: 1px solid #2F6B4F; }
        .btn-primary { background: #2F6B4F; color: #fff; }
        .btn-outline { background: #fff; color: #2F6B4F; }
    </style>
</head>
<body>
    <main class="box">
        <div class="code">錯誤代碼 {{ $code }}</div>
        <h1>@yield('title')</h1>
        @yield('body')
        <div class="actions">
            @yield('actions')
            <a class="btn btn-outline" href="javascript:history.back()" onclick="if (history.length > 1) { history.back(); return false; }">回到上一頁</a>
            @if ($errorLoggedIn)
                <a class="btn btn-primary" href="{{ url('/dashboard') }}">回到首頁</a>
            @else
                <a class="btn btn-primary" href="{{ url('/login') }}">前往登入</a>
            @endif
        </div>
    </main>
</body>
</html>

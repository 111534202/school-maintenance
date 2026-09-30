<!DOCTYPE html>
{{--
    暫用版面（placeholder layout）。
    全組規則要求「UI 使用全組共用 Layout/元件，不另做一套完全不同風格」，
    但共用 Layout 目前在同學的共用 Repository 裡，這個獨立專案還拿不到，
    所以先用最簡單的 Bootstrap 5 CDN 頂著開發，之後接回共用 Repo 時
    要把這個檔案換成大家共用的那一份。

    RWD（響應式網頁設計）：導覽列在窄螢幕（手機/平板直向，< md 斷點）
    改用漢堡選單收合；表格統一包一層 .table-responsive，
    畫面太窄時表格本身可以左右滑動，不會撐開整個頁面版面。
--}}
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '學校設備維保電子化系統')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
        <div class="container">
            <span class="navbar-brand">學校設備維保電子化系統</span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false"
                aria-label="展開/收合選單">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNavbar">
                <div class="navbar-nav ms-md-auto">
                    <a class="nav-link" href="{{ route('maintenance-items.index') }}">保養項目</a>
                    <a class="nav-link" href="{{ route('maintenance-plans.index') }}">保養計畫</a>
                    <a class="nav-link" href="{{ route('maintenance-orders.index') }}">保養工單</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

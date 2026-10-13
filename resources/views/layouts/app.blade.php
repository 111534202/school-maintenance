{{-- 各頁面自己的內容會被放在這裡（兩處是登入後與未登入的版面）。 --}}
{{-- 【Blade 版面（layout）是什麼？】所有頁面共用的外框：最上方的導覽列、左邊的選單、訊息區，中間 @yield('content') 才是各頁面自己的內容。 --}}
{{-- 各頁面用 @extends('layouts.app') 套用這個外框，再用 @section('content') 填入內容。 --}}
{{-- 想改全站共用的外觀（顏色、導覽列、選單位置）改這個檔案；想改選單有哪些連結，改 layouts/partials/nav-links.blade.php。 --}}
{{-- 【Blade 版面（layout）是什麼？】所有頁面共用的外框：最上方的導覽列、左邊的選單、訊息區，中間 @yield('content') 才是各頁面自己的內容。 --}}
{{-- 各頁面用 @extends('layouts.app') 套用這個外框，再用 @section('content') 填入內容。 --}}
{{-- 想改全站共用的外觀（顏色、導覽列、選單位置）改這個檔案；想改選單有哪些連結，改 layouts/partials/nav-links.blade.php。 --}}
{{-- Blade 語法速查：雙大括號包住變數 = 輸出文字（會自動防止惡意程式碼）；@if / @foreach 是條件與迴圈；@can('權限') 有權限才顯示；大括號加兩個減號的寫法是不會輸出到網頁的註解。 --}}
<!DOCTYPE html>
{{-- 網頁的語言標記，依目前選的語言自動變成 zh-TW 或 en。 --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    {{-- 讓手機瀏覽器用正確的寬度顯示（響應式網頁 RWD 必備）。 --}}
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- 瀏覽器分頁標題：各頁面用 @section('title', ...) 提供；沒提供就用網站名稱。 --}}
    <title>@yield('title', __('common.site_title'))</title>
    {{-- 載入 Bootstrap 5 樣式（網格、按鈕、表格、表單等，從網路 CDN 載入，離線時不會有樣式）。 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons：全站按鈕統一用圖示（筆=編輯、垃圾桶=刪除…），不使用 emoji。 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- 以下是本站自訂的樣式，補強 Bootstrap 不足的地方。 --}}
    <style>
        /* 頁面至少和視窗一樣高。 */
        body { min-height: 100vh; }
        /* 圖示按鈕：固定正方形，列表操作欄的按鈕大小一致、不會互相擠壓。 */
        .icon-btn { width: 2rem; height: 2rem; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
        /* 篩選列：一律單行不換行，視窗太窄時整列左右捲動。 */
        .filter-bar { display: flex; flex-wrap: nowrap; align-items: flex-end; gap: 0.75rem; overflow-x: auto; padding-bottom: 0.75rem; margin-bottom: -0.5rem; }
        /* 手機寬度：頁面標題列的標題與按鈕放不下時整組換行，不要互相擠壓。 */
        .page-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.5rem; }
        @media (min-width: 768px) { .fs-md-5 { font-size: 1.25rem !important; } }
        /* 觸控裝置：圖示按鈕放大到好點的尺寸；表單欄位字級不低於 16px，避免 iOS 點進去自動放大整個畫面。 */
        @media (pointer: coarse) {
            .icon-btn { width: 2.5rem; height: 2.5rem; }
            .form-control, .form-select, .form-control-sm, .form-select-sm { font-size: 1rem; }
        }
        /* 篩選列裡的每個欄位保持原本大小，不被壓縮。 */
        .filter-bar > * { flex: 0 0 auto; }
        .filter-bar label { white-space: nowrap; }
        /* 窄版頁面（新增／編輯表單）限制最大寬度，避免在大螢幕上被拉得太寬。 */
        .page-narrow { max-width: 880px; }
        /* 表單分區標題的字型。 */
        .form-section-title { font-size: 0.95rem; font-weight: 600; color: #495057; }
        /* 桌面側邊欄至少撐到視窗底部（扣掉頂部列 56px）。 */
        .app-sidebar { min-height: calc(100vh - 56px); }
        /* 選單連結平常是深灰色，目前所在頁面（.active）是藍底白字。 */
        .app-sidebar .nav-link, .offcanvas .nav-link { color: #495057; }
        .app-sidebar .nav-link.active, .offcanvas .nav-link.active { color: #fff; background-color: #0d6efd; }
        /* 讓內容區可以比內容窄，否則寬表格會把整個頁面撐開（水平捲動交給表格自己處理）。 */
        .app-content { min-width: 0; }
        @media (max-width: 575.98px) {
            .app-content { padding: 1rem !important; }
        }
        /* 中文在窄欄位會逐字換行，讓表格看起來直排；改成不換行，
           太寬就交給 .table-responsive 的水平捲動處理 */
        .table-responsive table th, .table-responsive table td {
            white-space: nowrap;
        }
        /* i18n 語言切換下拉選單：跟登出按鈕放在同一排右上角。 */
        .locale-switcher select {
            background: transparent; color: #fff; border: 1px solid rgba(255,255,255,0.5);
            border-radius: 4px; padding: 0.25rem 0.4rem; font-size: 0.85rem;
        }
        .locale-switcher select option { color: #212529; }
        /* 選單裡可展開的群組（主檔）：箭頭展開時翻轉，子項目縮排並用左邊線標示層級。 */
        .nav-group-toggle .nav-chevron { transition: transform .2s; }
        .nav-group-toggle[aria-expanded="true"] .nav-chevron { transform: rotate(180deg); }
        .app-sidebar .nav-group-toggle, .offcanvas .nav-group-toggle { color: #495057; cursor: pointer; }
    </style>
</head>
{{-- 頁面主體：淺灰色背景。 --}}
<body class="bg-light">
    {{-- 最上方的深色導覽列。 --}}
    <nav class="navbar navbar-dark bg-dark">
        {{-- RWD：頂部列永遠單排（漢堡 / 標題 / 右側工具），標題太長就省略號，不會換成兩排。 --}}
        <div class="container-fluid flex-nowrap gap-2">
            @auth
                {{-- 手機／平板才出現的「漢堡」按鈕：點了會打開下面的 #sidebarOffcanvas 側邊選單。 --}}
                <button class="btn btn-outline-light btn-sm d-lg-none flex-shrink-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="開啟選單">
                    <i class="bi bi-list fs-5"></i>
                </button>
            @endauth
            {{-- 網站名稱（左側標題）。 --}}
            <span class="navbar-brand text-truncate me-auto mb-0 fs-6 fs-md-5" style="min-width: 0;">{{ __('common.site_title') }}</span>
            <div class="d-flex align-items-center gap-2 gap-md-3 flex-shrink-0">
                {{-- i18n 語言切換：目前先隱藏（config('app.locale_switcher') 預設 false，之後要開放把
                     .env 的 APP_LOCALE_SWITCHER 設成 true 就好，翻譯與切換功能都還在）。 --}}
                @if (config('app.locale_switcher'))
                    <div class="locale-switcher">
                        <select id="locale-select" aria-label="{{ __('common.locale.zh_TW') }} / {{ __('common.locale.en') }}">
                            <option value="{{ route('locale.switch', 'zh_TW') }}" @selected(app()->getLocale() === 'zh_TW')>{{ __('common.locale.zh_TW') }}</option>
                            <option value="{{ route('locale.switch', 'en') }}" @selected(app()->getLocale() === 'en')>{{ __('common.locale.en') }}</option>
                        </select>
                    </div>
                @endif
                @auth
                    {{-- 通知鈴鐺：有「新報修待派工」「待驗收」「指派給我的案件」時顯示紅色數字，
                         點開清單，點其中一項直接跳到對應的案件列表。資料由 AppServiceProvider 的 view composer 算好。 --}}
                    <div class="dropdown">
                        <button class="btn btn-outline-light btn-sm icon-btn position-relative" type="button" id="notificationBell"
                            data-bs-toggle="dropdown" aria-expanded="false"
                            title="{{ __('notifications.title') }}" aria-label="{{ __('notifications.title') }}">
                            <i class="bi {{ ($notificationTotal ?? 0) > 0 ? 'bi-bell-fill' : 'bi-bell' }}"></i>
                            @if (($notificationTotal ?? 0) > 0)
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger" id="notificationBadge">
                                    {{ $notificationTotal > 99 ? '99+' : $notificationTotal }}
                                </span>
                            @endif
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notificationBell" style="min-width: 17rem;">
                            <li><h6 class="dropdown-header">{{ __('notifications.title') }}</h6></li>
                            {{-- @forelse：逐項列出通知；沒有任何通知時改走 @empty 區塊（顯示「目前沒有待處理事項」）。 --}}
                            @forelse (($notificationItems ?? []) as $item)
                                <li>
                                    <a class="dropdown-item d-flex justify-content-between align-items-center gap-3" href="{{ $item['url'] }}">
                                        <span><i class="bi {{ $item['icon'] }} me-2"></i>{{ $item['text'] }}</span>
                                        <span class="badge rounded-pill text-bg-danger">{{ $item['count'] }}</span>
                                    </a>
                                </li>
                            @empty
                                <li><span class="dropdown-item-text text-muted small">{{ __('notifications.empty') }}</span></li>
                            @endforelse
                        </ul>
                    </div>

                    <span class="text-light small d-none d-sm-inline">
                        {{ Auth::user()->name }}｜{{ Auth::user()->role->name ?? '尚未指派角色' }}
                    </span>
                    {{-- 手機寬度放不下姓名文字，改顯示人像圖示，長按/滑過仍可看到姓名與角色。 --}}
                    <i class="bi bi-person-circle text-light fs-5 d-sm-none" title="{{ Auth::user()->name }}｜{{ Auth::user()->role->name ?? '尚未指派角色' }}"></i>
                    {{-- 登出按鈕其實是一個送出 POST 的表單（登出是「動作」，不能用一般連結）。 --}}
                    <form method="POST" action="{{ route('logout') }}">
                        {{-- 表單防偽 token（CSRF）：Laravel 要求所有 POST 表單都要有，沒有會被擋下（錯誤 419）。 --}}
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm icon-btn" title="登出" aria-label="登出"><i class="bi bi-box-arrow-right"></i></button>
                    </form>
                @endauth
            </div>
        </div>
    </nav>

    @if (config('app.locale_switcher'))
        <script>
            document.getElementById('locale-select').addEventListener('change', function () {
                window.location.href = this.value;
            });
        </script>
    @endif

    @auth
        {{-- 手機與平板的側邊選單（< lg 才會出現按鈕觸發） --}}
        {{-- 手機版的滑出側邊選單（桌面版不顯示，改用右邊的固定側邊欄）。 --}}
        <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas">
            <div class="offcanvas-header">
                <h6 class="offcanvas-title">選單</h6>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="關閉"></button>
            </div>
            <div class="offcanvas-body">
                <div class="nav flex-column">
                    @include('layouts.partials.nav-links', ['navId' => 'drawer'])
                </div>
            </div>
        </div>

        <div class="d-flex">
            {{-- 桌面版的固定側邊欄（d-none d-lg-block：小螢幕隱藏、大螢幕顯示）。 --}}
            <div class="app-sidebar bg-white border-end d-none d-lg-block flex-shrink-0" style="width: 220px;">
                <div class="nav flex-column p-2">
                    {{-- 引入同一份選單內容；navId 不同是因為手機版與桌面版各有一份，展開收合用的 id 不能重複。 --}}
                    @include('layouts.partials.nav-links', ['navId' => 'side'])
                </div>
            </div>

            <div class="flex-grow-1 app-content p-3 p-md-4">
                {{-- 操作成功訊息（Controller 用 ->with('success', ...) 帶過來，只顯示一次）。 --}}
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                {{-- 操作失敗訊息（Controller 用 ->with('error', ...) 帶過來）。 --}}
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                {{-- 表單驗證錯誤：把所有錯誤訊息列在一個紅色框裡。 --}}
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    @else
        <div class="app-content p-3 p-md-4">
            @yield('content')
        </div>
    @endauth

    {{-- 載入 Bootstrap 的 JavaScript（下拉選單、側邊滑出選單、可收合群組都靠它）。 --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

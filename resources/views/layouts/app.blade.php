<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('common.site_title'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons：全站按鈕統一用圖示（筆=編輯、垃圾桶=刪除…），不使用 emoji。 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
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
        .filter-bar > * { flex: 0 0 auto; }
        .filter-bar label { white-space: nowrap; }
        .page-narrow { max-width: 880px; }
        .form-section-title { font-size: 0.95rem; font-weight: 600; color: #495057; }
        .app-sidebar { min-height: calc(100vh - 56px); }
        .app-sidebar .nav-link, .offcanvas .nav-link { color: #495057; }
        .app-sidebar .nav-link.active, .offcanvas .nav-link.active { color: #fff; background-color: #0d6efd; }
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
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        {{-- RWD：頂部列永遠單排（漢堡 / 標題 / 右側工具），標題太長就省略號，不會換成兩排。 --}}
        <div class="container-fluid flex-nowrap gap-2">
            @auth
                <button class="btn btn-outline-light btn-sm d-lg-none flex-shrink-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="開啟選單">
                    <i class="bi bi-list fs-5"></i>
                </button>
            @endauth
            <span class="navbar-brand text-truncate me-auto mb-0 fs-6 fs-md-5" style="min-width: 0;">{{ __('common.site_title') }}</span>
            <div class="d-flex align-items-center gap-2 gap-md-3 flex-shrink-0">
                {{-- i18n 語言切換：選了直接跳轉到 /locale/{locale}，value 本身就是完整網址。 --}}
                <div class="locale-switcher">
                    <select id="locale-select" aria-label="{{ __('common.locale.zh_TW') }} / {{ __('common.locale.en') }}">
                        <option value="{{ route('locale.switch', 'zh_TW') }}" @selected(app()->getLocale() === 'zh_TW')>{{ __('common.locale.zh_TW') }}</option>
                        <option value="{{ route('locale.switch', 'en') }}" @selected(app()->getLocale() === 'en')>{{ __('common.locale.en') }}</option>
                    </select>
                </div>
                @auth
                    <span class="text-light small d-none d-sm-inline">
                        {{ Auth::user()->name }}｜{{ Auth::user()->role->name ?? '尚未指派角色' }}
                    </span>
                    {{-- 手機寬度放不下姓名文字，改顯示人像圖示，長按/滑過仍可看到姓名與角色。 --}}
                    <i class="bi bi-person-circle text-light fs-5 d-sm-none" title="{{ Auth::user()->name }}｜{{ Auth::user()->role->name ?? '尚未指派角色' }}"></i>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm icon-btn" title="登出" aria-label="登出"><i class="bi bi-box-arrow-right"></i></button>
                    </form>
                @endauth
            </div>
        </div>
    </nav>

    <script>
        document.getElementById('locale-select').addEventListener('change', function () {
            window.location.href = this.value;
        });
    </script>

    @auth
        {{-- 手機與平板的側邊選單（< lg 才會出現按鈕觸發） --}}
        <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas">
            <div class="offcanvas-header">
                <h6 class="offcanvas-title">選單</h6>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="關閉"></button>
            </div>
            <div class="offcanvas-body">
                <div class="nav flex-column">
                    @include('layouts.partials.nav-links')
                </div>
            </div>
        </div>

        <div class="d-flex">
            <div class="app-sidebar bg-white border-end d-none d-lg-block flex-shrink-0" style="width: 220px;">
                <div class="nav flex-column p-2">
                    @include('layouts.partials.nav-links')
                </div>
            </div>

            <div class="flex-grow-1 app-content p-3 p-md-4">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

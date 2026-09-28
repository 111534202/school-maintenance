<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '學校設備維保電子化系統')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; }
        .app-sidebar { min-height: calc(100vh - 56px); }
        .app-sidebar .nav-link, .offcanvas .nav-link { color: #495057; }
        .app-sidebar .nav-link.active, .offcanvas .nav-link.active { color: #fff; background-color: #0d6efd; }
        .app-content { min-width: 0; }
        @media (max-width: 575.98px) {
            .app-content { padding: 1rem !important; }
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <button class="btn btn-outline-light d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="開啟選單">
                <span class="navbar-toggler-icon"></span>
            </button>
            <span class="navbar-brand text-truncate">學校設備維保電子化系統</span>
            <div class="d-flex align-items-center gap-2 gap-md-3">
                <span class="text-light small d-none d-sm-inline">
                    {{ Auth::user()->name }}｜{{ Auth::user()->role->name ?? '尚未指派角色' }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">登出</button>
                </form>
            </div>
        </div>
    </nav>

    {{-- 手機版側邊選單（< md 才會出現按鈕觸發） --}}
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
        <div class="app-sidebar bg-white border-end d-none d-md-block" style="width: 220px;">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

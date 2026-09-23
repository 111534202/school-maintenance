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
        .app-sidebar .nav-link { color: #495057; }
        .app-sidebar .nav-link.active { color: #fff; background-color: #0d6efd; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">學校設備維保電子化系統</span>
            <div class="d-flex align-items-center gap-3">
                <span class="text-light small">
                    {{ Auth::user()->name }}｜{{ Auth::user()->role->name ?? '尚未指派角色' }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">登出</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="d-flex">
        <div class="app-sidebar bg-white border-end" style="width: 220px;">
            <div class="nav flex-column p-2">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">主控台</a>
                @if (in_array(Auth::user()->role?->slug, ['admin', 'it_manager']))
                    <a class="nav-link {{ request()->routeIs('classrooms.*') ? 'active' : '' }}" href="{{ route('classrooms.index') }}">教室管理</a>
                    <a class="nav-link {{ request()->routeIs('device-categories.*') ? 'active' : '' }}" href="{{ route('device-categories.index') }}">設備類別</a>
                    <a class="nav-link {{ request()->routeIs('devices.*') ? 'active' : '' }}" href="{{ route('devices.index') }}">設備管理</a>
                @endif
            </div>
        </div>

        <div class="flex-grow-1 p-4">
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
</body>
</html>

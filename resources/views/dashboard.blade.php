<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>主控台 - 學校設備維保電子化系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">學校設備維保電子化系統</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline-light btn-sm">登出</button>
            </form>
        </div>
    </nav>
    <div class="container mt-4">
        <h3>歡迎，{{ Auth::user()->name }}</h3>
        <p>角色：{{ Auth::user()->role->name ?? '尚未指派角色' }}</p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '知識庫') - {{ config('app.name') }}</title>
    <style>
        body { font-family: -apple-system, "Microsoft JhengHei", Arial, sans-serif; margin: 0; background: #f5f6f8; color: #1f2933; }
        header { background: #1f2933; color: #fff; padding: 1rem 1.5rem; }
        header a { color: #fff; text-decoration: none; font-weight: 600; }
        main { max-width: 1100px; margin: 2rem auto; padding: 0 1rem; }
        /* 表格外面包一層可以左右捲動的容器，欄位文字就不會被硬擠到下一行。 */
        .table-scroll { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td {
            padding: 0.6rem 0.8rem; border-bottom: 1px solid #e4e7eb; text-align: left; vertical-align: top;
            white-space: nowrap; /* 同一列的文字一律保持在同一行，太長就讓外層容器左右捲動 */
        }
        th { background: #eceff1; }
        .btn { display: inline-block; padding: 0.4rem 0.9rem; border-radius: 4px; text-decoration: none; font-size: 0.9rem; cursor: pointer; border: none; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-secondary { background: #9aa5b1; color: #fff; }
        form.inline { display: inline; }
        .field { margin-bottom: 1rem; }
        .field label { display: block; font-weight: 600; margin-bottom: 0.3rem; }
        .field input[type=text], .field textarea, .field select {
            width: 100%; padding: 0.5rem; border: 1px solid #cbd2d9; border-radius: 4px; box-sizing: border-box;
        }
        .field textarea { min-height: 6rem; }
        .errors { background: #fde8e8; color: #9b1c1c; padding: 0.8rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .status { background: #e3f9e5; color: #0e6245; padding: 0.8rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .badge { display: inline-block; padding: 0.1rem 0.5rem; border-radius: 3px; font-size: 0.8rem; }
        .badge-on { background: #e3f9e5; color: #0e6245; }
        .badge-off { background: #eceff1; color: #616e7c; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <header style="display:flex; justify-content: space-between; align-items:center;">
        <a href="{{ route('knowledge-base.index') }}">學校設備維保電子化系統</a>
        <nav>
            <a href="{{ route('knowledge-base.index') }}" style="margin-left:1rem;">自助知識庫</a>
            <a href="{{ route('repair-requests.index') }}" style="margin-left:1rem;">我的報修</a>
        </nav>
    </header>
    <main>
        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="errors">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                <ul style="margin:0; padding-left: 1.2rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>

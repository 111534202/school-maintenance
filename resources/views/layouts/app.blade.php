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
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem; }

        /* 篩選表單（依第五週 RWD 收尾）：桌面版並排顯示，手機畫面不夠寬時自動換行，
           每個欄位至少保留 140px，不會被硬擠到選項文字都看不完整。 */
        .filter-form { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; align-items: flex-end; }
        .filter-form .field { margin-bottom: 0; flex: 1 1 140px; min-width: 140px; }

        /* 漢堡選單（依《第五週個人工作計畫》第 3 項 RWD 收尾）：手機畫面窄的時候，
           把導覽連結收進左上角的選單按鈕裡，不會跟標題擠在一起換行。 */
        .hamburger-btn {
            background: none; border: none; color: #fff; font-size: 1.5rem; line-height: 1;
            cursor: pointer; padding: 0.2rem 0.6rem; margin-right: 0.75rem;
        }
        .site-title { color: #fff; text-decoration: none; font-weight: 600; }
        .hamburger-menu {
            position: absolute; top: 100%; left: 0; background: #1f2933; min-width: 200px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2); z-index: 10;
        }
        /* [hidden] 是瀏覽器內建屬性，比用 class 切換 display 更簡單可靠。 */
        .hamburger-menu[hidden] { display: none; }
        .hamburger-menu a {
            display: block; padding: 0.7rem 1.2rem; color: #fff; text-decoration: none;
        }
        .hamburger-menu a:hover { background: #374151; }
    </style>
</head>
<body>
    <header style="display:flex; align-items:center; position:relative;">
        {{-- 左上角漢堡選單按鈕：點下去展開／收合下面的導覽選單，純前端 JS 切換，不用任何框架。 --}}
        <button type="button" class="hamburger-btn" id="menu-toggle" aria-expanded="false" aria-controls="site-menu">
            ☰
        </button>
        <a href="{{ route('knowledge-base.index') }}" class="site-title">學校設備維保電子化系統</a>

        <nav id="site-menu" class="hamburger-menu" hidden>
            <a href="{{ route('knowledge-base.index') }}">自助知識庫</a>
            <a href="{{ route('repair-requests.index') }}">我的報修（維修案件看板）</a>
        </nav>
    </header>

    <script>
        // 純陽春的顯示/隱藏切換：點按鈕就打開或關閉選單；點選單以外的地方也會自動關閉。
        (function () {
            var toggleButton = document.getElementById('menu-toggle');
            var menu = document.getElementById('site-menu');

            toggleButton.addEventListener('click', function (event) {
                event.stopPropagation();
                var isHidden = menu.hasAttribute('hidden');
                if (isHidden) {
                    menu.removeAttribute('hidden');
                } else {
                    menu.setAttribute('hidden', '');
                }
                toggleButton.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
            });

            document.addEventListener('click', function (event) {
                if (!menu.contains(event.target) && event.target !== toggleButton) {
                    menu.setAttribute('hidden', '');
                    toggleButton.setAttribute('aria-expanded', 'false');
                }
            });
        })();
    </script>
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

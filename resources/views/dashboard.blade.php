{{-- 主控台（登入後的首頁，對應 DashboardController）：最上面是洋蔥式的「功能入口」，接著是數字卡片，下半部是圖表。 --}}
{{-- 每張卡片、每一塊圖表點下去都會跳到對應的功能區（並帶好篩選條件）；沒有權限的人看不到對應的統計。 --}}
{{-- 畫圖使用 Chart.js 這個外部函式庫（從網路 CDN 載入，離線時圖表不會顯示，但數字卡片仍正常）。 --}}
{{-- 【想新增一張卡片】在下面 PHP 區塊的 $cards 陣列加一筆；【想新增一張圖表】在 $charts 加一筆（需要的資料先在 DashboardController 算好傳進來）。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('dashboard.title') . ' - ' . __('common.site_title'))

@php
    // 圖表顏色：工單狀態跟看板上的狀態徽章同一套顏色，看圖就知道對應哪個徽章。
    // 各種報修狀態對應的圖表顏色。
    $repairColors = ['pending' => '#6c757d', 'assigned' => '#0dcaf0', 'in_progress' => '#ffc107', 'pending_review' => '#0d6efd', 'completed' => '#198754'];
    // 各種設備狀態對應的圖表顏色。
    $deviceColors = ['normal' => '#198754', 'repairing' => '#ffc107', 'retired' => '#6c757d', 'disabled' => '#dc3545'];
    // 長條圖用的一組輪替顏色（身分比較多時依序使用）。
    $palette = ['#0d6efd', '#6610f2', '#d63384', '#fd7e14', '#198754', '#20c997', '#0dcaf0', '#6c757d'];

    // 數字卡片清單：標題、數字、單位、圖示、顏色、點擊後要跳去的網址、右下角的備註文字。前三張（報修）所有人都看得到。
    $cards = [
        ['label' => __('dashboard.cards.open_repairs'), 'value' => $openCount, 'unit' => __('dashboard.cards.unit_repairs'), 'icon' => 'bi-tools', 'color' => 'primary', 'url' => route('repairs.index'), 'note' => null],
        ['label' => __('dashboard.cards.pending_dispatch'), 'value' => $pendingCount, 'unit' => __('dashboard.cards.unit_repairs'), 'icon' => 'bi-inbox', 'color' => 'secondary', 'url' => route('repairs.index', ['status' => 'pending']), 'note' => null],
        ['label' => __('dashboard.cards.pending_review'), 'value' => $pendingReviewCount, 'unit' => __('dashboard.cards.unit_repairs'), 'icon' => 'bi-clipboard-check', 'color' => 'warning', 'url' => route('repairs.index', ['status' => 'pending_review']), 'note' => null],
    ];
    // 下面三張卡片依權限才加入：Controller 沒算（null）就代表這個人沒有對應主檔的權限。
    if ($deviceTotal !== null) {
        $cards[] = ['label' => __('dashboard.cards.devices'), 'value' => $deviceTotal, 'unit' => __('dashboard.cards.unit_devices'), 'icon' => 'bi-pc-display', 'color' => 'success', 'url' => route('devices.index'), 'note' => null];
    }
    if ($userTotal !== null) {
        $cards[] = ['label' => __('dashboard.cards.users'), 'value' => $userTotal, 'unit' => __('dashboard.cards.unit_users'), 'icon' => 'bi-people', 'color' => 'info', 'url' => route('users.index'), 'note' => null];
    }
    if ($classroomTotal !== null) {
        $cards[] = ['label' => __('dashboard.cards.classrooms'), 'value' => $classroomTotal, 'unit' => __('dashboard.cards.unit_classrooms'), 'icon' => 'bi-building', 'color' => 'danger', 'url' => route('classrooms.index'),
            'note' => $classroomAbnormal > 0 ? __('dashboard.cards.classrooms_abnormal', ['count' => $classroomAbnormal]) : null];
    }

    // 圖表資料統一整理成 {labels, counts, colors, urls}，前端 JS 只要照著畫、點了就跳 urls。
    $charts = [];
    // 圖表一：報修單各狀態的數量（甜甜圈圖），點哪一塊就跳到該狀態的看板。
    $charts['repairStatus'] = [
        'title' => __('dashboard.charts.repair_status'),
        'type' => 'doughnut',
        'labels' => array_column($repairStatuses, 'label'),
        'counts' => array_column($repairStatuses, 'count'),
        'colors' => array_map(fn ($s) => $repairColors[$s['key']] ?? '#6c757d', $repairStatuses),
        'urls' => array_column($repairStatuses, 'url'),
    ];
    // 圖表二：近 14 天每天新增的報修單數（折線圖），點圖跳到報修看板。
    $charts['repairTrend'] = [
        'title' => __('dashboard.charts.repair_trend'),
        'type' => 'line',
        'labels' => array_column($trend, 'label'),
        'counts' => array_column($trend, 'count'),
        'colors' => ['#0d6efd'],
        'urls' => array_fill(0, count($trend), route('repairs.index')),
        'series' => __('dashboard.charts.repair_trend_series'),
    ];
    if ($deviceStatuses !== null) {
        // 圖表三：設備各狀態的數量（甜甜圈圖，需要設備主檔權限），點哪一塊就跳到該狀態的設備主檔。
        $charts['deviceStatus'] = [
            'title' => __('dashboard.charts.device_status'),
            'type' => 'doughnut',
            'labels' => array_column($deviceStatuses, 'label'),
            'counts' => array_column($deviceStatuses, 'count'),
            'colors' => array_map(fn ($s) => $deviceColors[$s['key']] ?? '#6c757d', $deviceStatuses),
            'urls' => array_column($deviceStatuses, 'url'),
        ];
    }
    if ($roleDistribution !== null) {
        // 圖表四：各身分的用戶數（長條圖，需要用戶主檔權限），點哪一根就跳到該身分的用戶主檔。
        $charts['roleDistribution'] = [
            'title' => __('dashboard.charts.role_distribution'),
            'type' => 'bar',
            'labels' => array_column($roleDistribution, 'label'),
            'counts' => array_column($roleDistribution, 'count'),
            'colors' => array_map(fn ($i) => $palette[$i % count($palette)], array_keys($roleDistribution)),
            'urls' => array_column($roleDistribution, 'url'),
            'series' => __('dashboard.cards.users'),
        ];
    }
@endphp

@section('content')
    {{-- 頁面頂部：歡迎訊息與目前的身分、操作提示。 --}}
    <div class="mb-2 mb-lg-3">
        <h1 class="h4 mb-1"><i class="bi bi-speedometer2 me-2"></i>{{ __('dashboard.welcome', ['name' => Auth::user()->name]) }}</h1>
        <div class="text-muted small">
            {{ __('dashboard.role_line', ['role' => Auth::user()->role->name ?? __('dashboard.no_role')]) }}　·　{{ __('dashboard.click_hint') }}
        </div>
    </div>

    {{-- 功能入口（洋蔥式）：第一層是幾張大卡片，點「分類」往內一層看到裡面的功能，點「返回」退回；點單一功能直接前往。 --}}
    {{-- 項目與權限跟側邊選單同一份資料（app/Support/NavMenu.php），沒有權限的人看不到。 --}}
    @php
        ['menu' => $entryMenu, 'groups' => $entryGroups] = \App\Support\NavMenu::build(auth()->user());
        // 第一層入口：不放「主控台」自己（已經在這一頁了）。
        $entryRoot = array_values(array_filter($entryMenu, fn ($e) => ($e['show'] ?? false) && ! (($e['type'] === 'link') && ($e['active'] ?? false) && $e['href'] === route('dashboard'))));
    @endphp
    <section class="mb-3" id="home-entry" data-home-entry aria-label="功能入口">
        <div class="d-flex align-items-center gap-2 mb-2" style="min-height: 2rem;">
            <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-entry-back><i class="bi bi-chevron-left me-1"></i>返回</button>
            <h2 class="h6 mb-0 text-muted" data-entry-title data-root-title="功能入口">功能入口</h2>
        </div>

        {{-- 第一層 --}}
        <div class="row g-3" data-entry-panel="root">
            @foreach ($entryRoot as $entry)
                <div class="col-6 col-md-4 col-lg">
                    @if ($entry['type'] === 'link')
                        <a href="{{ $entry['href'] }}" class="card shadow-sm h-100 text-decoration-none text-body entry-tile">
                            <div class="card-body d-flex align-items-center gap-3">
                                <span class="entry-icon"><i class="bi {{ $entry['icon'] }}"></i></span>
                                <span class="fw-semibold">{{ $entry['label'] }}</span>
                            </div>
                        </a>
                    @else
                        <button type="button" class="card shadow-sm h-100 w-100 text-start text-body entry-tile" data-entry-open="{{ $entry['id'] }}">
                            <div class="card-body d-flex align-items-center gap-3 w-100">
                                <span class="entry-icon"><i class="bi {{ $entry['icon'] }}"></i></span>
                                <span class="flex-grow-1">
                                    <span class="fw-semibold d-block">{{ $entry['label'] }}</span>
                                    <span class="text-muted small">{{ count($entry['children']) }} 項功能</span>
                                </span>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </div>
                        </button>
                    @endif
                </div>
            @endforeach
            {{-- 手機版專用：圖表收進「數據圖表」這一層（電腦版圖表直接顯示在下面），一次只看一張。 --}}
            <div class="col-6 col-md-4 d-lg-none">
                <button type="button" class="card shadow-sm h-100 w-100 text-start text-body entry-tile" data-charts-open>
                    <div class="card-body d-flex align-items-center gap-3 w-100">
                        <span class="entry-icon"><i class="bi bi-bar-chart-line"></i></span>
                        <span class="flex-grow-1">
                            <span class="fw-semibold d-block">數據圖表</span>
                            <span class="text-muted small">{{ count($charts) }} 張圖</span>
                        </span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </div>
                </button>
            </div>
        </div>

        {{-- 第二層：每個分類一組。 --}}
        @foreach ($entryGroups as $group)
            <div class="row g-3 d-none" data-entry-panel="{{ $group['id'] }}" data-entry-label="{{ $group['label'] }}">
                @foreach ($group['children'] as $child)
                    <div class="col-6 col-md-4 col-lg">
                        <a href="{{ $child['href'] }}" class="card shadow-sm h-100 text-decoration-none text-body entry-tile">
                            <div class="card-body d-flex align-items-center gap-3">
                                <span class="entry-icon"><i class="bi {{ $child['icon'] }}"></i></span>
                                <span class="fw-semibold">{{ $child['label'] }}</span>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endforeach
    </section>

    <style>
        .entry-tile { border: 0; min-height: 4rem; transition: transform .15s ease, box-shadow .15s ease; }
        .entry-tile:hover, .entry-tile:focus-visible { transform: translateY(-2px); box-shadow: 0 .4rem .9rem rgba(0,0,0,.12) !important; color: inherit; }
        .entry-icon { width: 2.5rem; height: 2.5rem; flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; border-radius: .6rem; background: #e7f1ff; color: #0d6efd; font-size: 1.25rem; }
        @media (min-width: 992px) { .entry-tile { min-height: 3.5rem; } .entry-tile .card-body { padding: .5rem .75rem; gap: .5rem !important; } .entry-icon { width: 2.25rem; height: 2.25rem; font-size: 1.1rem; } }
        /* 手機：入口格子緊湊一點（小圖示、拿掉箭頭），一個畫面就能看到所有入口與數字卡片。 */
        @media (max-width: 575.98px) {
            .entry-tile { min-height: 3.25rem; }
            .entry-tile .card-body { padding: .5rem .6rem; gap: .5rem !important; }
            .entry-tile .bi-chevron-right { display: none; }
            .entry-icon { width: 2rem; height: 2rem; font-size: 1rem; }
            .entry-tile .small { font-size: .75rem; }
        }
        @media (prefers-reduced-motion: reduce) { .entry-tile { transition: none; } }
    </style>
    <script>
        // 功能入口的切換：往內點 → 顯示該分類的功能；返回 → 回到第一層。
        (function () {
            var box = document.getElementById('home-entry');
            if (!box) return;
            var back = box.querySelector('[data-entry-back]');
            var title = box.querySelector('[data-entry-title]');
            function show(id) {
                box.querySelectorAll('[data-entry-panel]').forEach(function (p) {
                    p.classList.toggle('d-none', p.getAttribute('data-entry-panel') !== id);
                });
                var panel = box.querySelector('[data-entry-panel="' + id + '"]');
                var label = panel && panel.getAttribute('data-entry-label');
                title.textContent = label || title.getAttribute('data-root-title');
                back.classList.toggle('d-none', id === 'root');
            }
            box.addEventListener('click', function (e) {
                var opener = e.target.closest('[data-entry-open]');
                if (opener) { show(opener.getAttribute('data-entry-open')); return; }
                if (e.target.closest('[data-entry-back]')) show('root');
            });
        })();
    </script>

    {{-- 數字卡片：整張卡片就是連結，點了跳到對應的功能。 --}}
    <div class="row g-2 g-lg-3 mb-3">
        {{-- 逐張畫出數字卡片；整張卡片是一個連結（<a>），點了就跳轉。 --}}
        @foreach ($cards as $card)
            {{-- 卡片內有不換行的文字（標題、異常備註），一排放太多張會撐破卡片，所以最多一排 3 張。 --}}
            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ $card['url'] }}" class="card shadow-sm h-100 text-decoration-none text-body kpi-card">
                    <div class="card-body d-flex align-items-center justify-content-between gap-2 py-2 px-3">
                        <div class="min-w-0">
                            <div class="text-muted small text-nowrap">{{ $card['label'] }}</div>
                            <div class="fs-4 fw-semibold lh-1 text-nowrap">{{ $card['value'] }}<span class="fs-6 fw-normal text-muted ms-1">{{ $card['unit'] }}</span></div>
                            {{-- 有備註（例如「設備異常教室 1 間」）才用紅字顯示在數字下面（空間不夠時可以換行）。 --}}
                            @if ($card['note'])
                                <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $card['note'] }}</div>
                            @endif
                        </div>
                        <span class="kpi-icon text-bg-{{ $card['color'] }}"><i class="bi {{ $card['icon'] }}"></i></span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    {{-- 圖表：點圖上的扇形／長條／折線，跳到對應功能區（已經帶好篩選條件）。 --}}
    {{-- 電腦版：圖表直接排在下面，一排放得下就一排。手機版：整區變成從右邊滑進來的一層（#home-charts.is-open），一次只看一張，上面的按鈕切換。 --}}
    <section id="home-charts" aria-label="數據圖表">
      <div class="charts-top d-lg-none">
        <div class="d-flex align-items-center gap-2 p-2 border-bottom bg-white">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-charts-back><i class="bi bi-chevron-left me-1"></i>返回</button>
            <span class="fw-semibold">數據圖表</span>
        </div>
        <div class="d-flex flex-wrap gap-2 p-2 pb-0" data-charts-tabs></div>
      </div>
      <div class="charts-body">
    <div class="row g-3">
        {{-- 逐張畫出圖表的外框；每張圖用 canvas（畫布）當容器，id 是 chart- 加上圖表名稱。 --}}
        @foreach ($charts as $id => $chart)
            <div class="col-12 col-md-6 chart-col" data-chart-col data-chart-title="{{ $chart['title'] }}">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white form-section-title py-2">{{ $chart['title'] }}</div>
                    <div class="card-body">
                        {{-- 資料全部是 0 就顯示「目前沒有資料」，不畫空圖表。 --}}
                        @if (array_sum($chart['counts']) === 0)
                            <div class="text-center text-muted py-5">{{ __('dashboard.charts.no_data') }}</div>
                        @else
                            <div class="chart-box"><canvas id="chart-{{ $id }}" role="img" aria-label="{{ $chart['title'] }}"></canvas></div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
      </div>
    </section>

    {{-- 以下是主控台專用的樣式：卡片滑過時微微上浮、圓形圖示、圖表固定高度。 --}}
    <style>
        .kpi-card { transition: transform .15s, box-shadow .15s; }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.12) !important; }
        .kpi-icon { width: 2.25rem; height: 2.25rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        /* 圖表高度：電腦版由下面的 JS 量剩下的畫面高度來決定（讓整個主控台剛好一個畫面）；手機版在「數據圖表」那一層裡佔大半個畫面。 */
        .chart-box { position: relative; height: 240px; }
        .min-w-0 { min-width: 0; }
        /* 手機／平板：圖表那一區變成滑進來的一層，一次只顯示目前選的那一張。 */
        @media (max-width: 991.98px) {
            #home-charts { position: fixed; inset: 0; z-index: 1060; background: #f8f9fa; overflow-y: auto; transform: translateX(100%); visibility: hidden; transition: transform .25s ease, visibility 0s linear .25s; }
            #home-charts.is-open { transform: translateX(0); visibility: visible; transition: transform .25s ease, visibility 0s; }
            #home-charts .charts-top { position: sticky; top: 0; z-index: 1; background: #f8f9fa; }
            #home-charts .charts-body { padding: .75rem; }
            #home-charts .chart-col:not(.is-current) { display: none; }
            #home-charts .chart-col { flex: 0 0 100%; max-width: 100%; }
            .chart-box { height: min(55dvh, 420px); height: min(55vh, 420px); }
        }
        /* 寬螢幕：所有圖表排成同一排、平均分寬度（圖表數量不同也會填滿）。 */
        @media (min-width: 1200px) {
            #home-charts .charts-body > .row { flex-wrap: nowrap; }
            #home-charts .chart-col { flex: 1 1 0; width: auto; max-width: none; min-width: 0; }
        }
        @media (prefers-reduced-motion: reduce) { #home-charts { transition: none !important; } }
    </style>

    {{-- 載入 Chart.js 函式庫（提供全域的 Chart 類別）。 --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            // 把 PHP 整理好的圖表資料轉成 JavaScript 物件。
            var charts = @json($charts);

            // ---- 電腦版：量一下剩下的畫面高度，讓圖表剛好填滿，整個主控台不用往下滑 ----
            var desktop = window.matchMedia('(min-width: 992px)');
            function sizeCharts() {
                var boxes = document.querySelectorAll('#home-charts .chart-box');
                if (!boxes.length) return;
                if (!desktop.matches) { boxes.forEach(function (b) { b.style.height = ''; }); return; }
                var box = boxes[0];
                var wrapTop = box.getBoundingClientRect().top;
                // 圖表底下留給卡片內距與頁面下方空白的高度（約 56px）。
                var h = Math.floor(window.innerHeight - wrapTop - 56);
                h = Math.max(150, Math.min(h, 320));
                boxes.forEach(function (b) { b.style.height = h + 'px'; });
            }
            sizeCharts();
            window.addEventListener('resize', sizeCharts);

            // ---- 手機版：「數據圖表」這一層的開關與切換圖表 ----
            var panel = document.getElementById('home-charts');
            if (panel) {
                var cols = Array.prototype.slice.call(panel.querySelectorAll('[data-chart-col]'));
                var tabs = panel.querySelector('[data-charts-tabs]');
                var tabBtns = [];
                function current(i) {
                    cols.forEach(function (c, j) { c.classList.toggle('is-current', j === i); });
                    tabBtns.forEach(function (b, j) { b.classList.toggle('btn-primary', j === i); b.classList.toggle('btn-outline-primary', j !== i); });
                    cols.forEach(function (c) { var cv = c.querySelector('canvas'); var ch = cv && window.Chart && Chart.getChart(cv); if (ch) ch.resize(); });
                }
                cols.forEach(function (c, i) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'btn btn-sm btn-outline-primary';
                    b.textContent = c.getAttribute('data-chart-title');
                    b.addEventListener('click', function () { current(i); });
                    tabs.appendChild(b);
                    tabBtns.push(b);
                });
                current(0);
                function syncInert() { if (desktop.matches || panel.classList.contains('is-open')) panel.removeAttribute('inert'); else panel.setAttribute('inert', ''); }
                function openCharts() { panel.classList.add('is-open'); syncInert(); document.documentElement.style.overflow = 'hidden'; panel.scrollTop = 0; current(0); var bk = panel.querySelector('[data-charts-back]'); if (bk) bk.focus({ preventScroll: true }); }
                function closeCharts() { panel.classList.remove('is-open'); syncInert(); document.documentElement.style.overflow = ''; var op = document.querySelector('[data-charts-open]'); if (op) op.focus({ preventScroll: true }); }
                document.addEventListener('click', function (e) {
                    if (e.target.closest('[data-charts-open]')) openCharts();
                    else if (e.target.closest('[data-charts-back]')) closeCharts();
                });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('is-open')) closeCharts(); });
                desktop.addEventListener('change', function () { if (desktop.matches) { panel.classList.remove('is-open'); document.documentElement.style.overflow = ''; } syncInert(); sizeCharts(); });
                syncInert();
            }

            // 逐張圖表處理：找到對應的畫布，找不到（例如沒權限、沒資料）就跳過。
            Object.keys(charts).forEach(function (id) {
                var spec = charts[id];
                var canvas = document.getElementById('chart-' + id);
                if (!canvas) { return; }

                // doughnut 是甜甜圈圖；下面依圖表種類決定要不要顯示圖例、背景色與邊框。
                var isRound = spec.type === 'doughnut';
                var dataset = {
                    data: spec.counts,
                    backgroundColor: isRound || spec.type === 'bar' ? spec.colors : 'rgba(13,110,253,.15)',
                    borderColor: spec.type === 'line' ? spec.colors[0] : '#fff',
                    borderWidth: spec.type === 'line' ? 2 : 1,
                    label: spec.series || '',
                };
                if (spec.type === 'line') { dataset.fill = true; dataset.tension = 0.3; dataset.pointRadius = 3; }

                // 建立圖表：type 是圖表種類、data 是標籤與數值、options 是外觀與點擊行為。
                new Chart(canvas, {
                    type: spec.type,
                    data: { labels: spec.labels, datasets: [dataset] },
                    options: {
                        responsive: true,
                        // 不鎖定長寬比，讓圖表跟著外框（固定 280px 高）調整大小。
                        maintainAspectRatio: false,
                        plugins: { legend: { display: isRound, position: 'bottom' } },
                        scales: isRound ? {} : { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        // 點圖表跳到對應功能區：圓餅／長條點哪一塊就跳哪一塊的網址，折線圖點哪裡都去報修看板。
                        onClick: function (event, elements) {
                            if (spec.type === 'line') { window.location.href = spec.urls[0]; return; }
                            if (elements.length) { window.location.href = spec.urls[elements[0].index]; }
                        },
                        onHover: function (event, elements) {
                            event.native.target.style.cursor = (elements.length || spec.type === 'line') ? 'pointer' : 'default';
                        },
                    },
                });
            });
        })();
    </script>
@endsection

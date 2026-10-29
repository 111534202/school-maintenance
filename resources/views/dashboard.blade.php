{{-- 主控台（登入後的首頁，對應 DashboardController）：上半部是數字卡片，下半部是四張圖表。 --}}
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
    <div class="mb-4">
        <h1 class="h4 mb-1"><i class="bi bi-speedometer2 me-2"></i>{{ __('dashboard.welcome', ['name' => Auth::user()->name]) }}</h1>
        <div class="text-muted small">
            {{ __('dashboard.role_line', ['role' => Auth::user()->role->name ?? __('dashboard.no_role')]) }}　·　{{ __('dashboard.click_hint') }}
        </div>
    </div>

    {{-- 數字卡片：整張卡片就是連結，點了跳到對應的功能。 --}}
    <div class="row g-3 mb-4">
        {{-- 逐張畫出數字卡片；整張卡片是一個連結（<a>），點了就跳轉。 --}}
        @foreach ($cards as $card)
            {{-- 卡片內有不換行的文字（標題、異常備註），一排放太多張會撐破卡片，所以最多一排 3 張。 --}}
            <div class="col-12 col-sm-6 col-lg-4">
                <a href="{{ $card['url'] }}" class="card shadow-sm h-100 text-decoration-none text-body kpi-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="kpi-icon text-bg-{{ $card['color'] }}"><i class="bi {{ $card['icon'] }}"></i></span>
                        <div class="min-w-0">
                            <div class="text-muted small text-nowrap">{{ $card['label'] }}</div>
                            <div class="fs-3 fw-semibold lh-1">{{ $card['value'] }}<span class="fs-6 fw-normal text-muted ms-1">{{ $card['unit'] }}</span></div>
                            {{-- 有備註（例如「設備異常教室 1 間」）才用紅字顯示在數字下面。 --}}
                            @if ($card['note'])
                                <div class="text-danger small mt-1 text-nowrap"><i class="bi bi-exclamation-circle me-1"></i>{{ $card['note'] }}</div>
                            @endif
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    {{-- 圖表：點圖上的扇形／長條／折線，跳到對應功能區（已經帶好篩選條件）。 --}}
    <div class="row g-3">
        {{-- 逐張畫出圖表的外框；每張圖用 canvas（畫布）當容器，id 是 chart- 加上圖表名稱。 --}}
        @foreach ($charts as $id => $chart)
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white form-section-title">{{ $chart['title'] }}</div>
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

    {{-- 以下是主控台專用的樣式：卡片滑過時微微上浮、圓形圖示、圖表固定高度。 --}}
    <style>
        .kpi-card { transition: transform .15s, box-shadow .15s; }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.12) !important; }
        .kpi-icon { width: 2.75rem; height: 2.75rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
        .chart-box { position: relative; height: 280px; }
        .min-w-0 { min-width: 0; }
    </style>

    {{-- 載入 Chart.js 函式庫（提供全域的 Chart 類別）。 --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            // 把 PHP 整理好的圖表資料轉成 JavaScript 物件。
            var charts = @json($charts);

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

@extends('layouts.app')

@section('title', '零件庫存列表')

@section('content')
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">零件庫存列表</h1>
            <p class="tb-subtitle">點選任一列查看零件履歷；庫存由採購紀錄與使用紀錄增減。</p>
        </div>
        <a href="{{ route('parts.create') }}" class="tb-btn tb-btn-primary" data-modal-frame data-modal-title="新增零件">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>新增零件
        </a>
    </div>

    {{-- 低庫存提示列：列出低於安全庫存的零件，按鈕開啟「目前低庫存品項」視窗（可匯出 Excel）。 --}}
    @if ($lowStockParts->isNotEmpty())
        <div class="tb-alert" role="status">
            <div class="tb-alert-icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></div>
            <div style="flex: 1 1 300px; min-width: 0">
                <div class="tb-alert-title">有 {{ $lowStockParts->count() }} 項零件低於安全庫存</div>
                <div class="tb-note mt-1">
                    {{ $lowStockParts->take(3)->map(fn ($p) => $p->part_code . ' ' . $p->name . ($p->specification ? '（' . $p->specification . '）' : ''))->join('、') }}{{ $lowStockParts->count() > 3 ? ' 等' : '' }}
                </div>
            </div>
            <button type="button" class="tb-btn tb-btn-danger-outline" data-bs-toggle="modal" data-bs-target="#lowStockModal">
                查看目前低庫存品項
                <span class="tb-pill tb-pill-danger" style="padding: 1px 9px">{{ $lowStockParts->count() }}</span>
            </button>
        </div>
    @else
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="tb-note"><i class="bi bi-check-circle me-1 tb-c-accent" aria-hidden="true"></i>目前沒有低於安全庫存的零件。</span>
            <button type="button" class="tb-btn tb-btn-outline tb-btn-sm" data-bs-toggle="modal" data-bs-target="#lowStockModal">
                查看目前低庫存品項 <span class="tb-muted">{{ $lowStockParts->count() }}</span>
            </button>
        </div>
    @endif

    <form method="GET" action="{{ route('parts.index') }}" class="tb-form-row" role="search">
        <label for="keyword" class="visually-hidden">搜尋（零件編號／名稱／規格／品牌／型號）</label>
        <input type="text" name="keyword" id="keyword" class="tb-input tb-input-pill" style="flex: 1 1 320px; min-width: 0"
            value="{{ request('keyword') }}" placeholder="搜尋零件編號、名稱、規格、品牌或型號，例如 PART-1 或 HDMI">
        <button type="submit" class="tb-btn tb-btn-dark">搜尋</button>
        @if (request()->filled('keyword'))
            <a href="{{ route('parts.index') }}" class="tb-btn tb-btn-outline">清除</a>
        @endif
    </form>

    @if ($parts->isEmpty())
        <div class="tb-card tb-empty">
            {{ request()->filled('keyword') ? '找不到符合的零件。' : '目前沒有零件資料。' }}
        </div>
    @else
        <div class="tb-table-wrap">
            <div class="table-responsive">
                {{-- 平均單價：目前庫存各採購批次的加權平均（用料成本採先進先出，詳見零件履歷）。 --}}
                {{-- 點這一行的任何地方都會進入零件履歷（也可以用 Tab 移到該行後按 Enter）。 --}}
                {{-- 欄寬：標了 fit 的欄位只佔內容需要的寬度，剩下的空間都給「品牌／型號」。 --}}
                {{-- data-onion-panel-title：清單檢視點一筆時，對話框標題固定顯示「零件庫存」。 --}}
                <table class="tb-table tb-table-hover part-rows table-fit" data-onion-primary="1,2" data-onion-panel-title="零件庫存">
                    <thead>
                        <tr>
                            <th class="fit">零件編號</th>
                            <th class="fit">名稱</th>
                            <th class="fit">規格</th>
                            <th>品牌／型號</th>
                            <th class="fit text-end">平均單價</th>
                            <th class="fit text-end">目前庫存</th>
                            <th class="fit text-end">安全庫存</th>
                            <th class="fit text-center">其他</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($parts as $part)
                            @php($average = $part->averageUnitPrice())
                            <tr class="part-row" data-href="{{ route('parts.show', $part) }}" tabindex="0" role="link" aria-label="查看 {{ $part->part_code }} {{ $part->name }} 的零件履歷">
                                <td class="tb-code">{{ $part->part_code }}</td>
                                <td>{{ $part->name }}</td>
                                <td>{{ $part->specification ?? '—' }}</td>
                                <td class="tb-muted">{{ $part->brandModel() ?? '—' }}</td>
                                <td class="text-end text-nowrap tb-num">{{ $average === null ? '—' : 'NT$ ' . rtrim(rtrim(number_format($average, 1), '0'), '.') }}</td>
                                <td class="text-end text-nowrap tb-num">
                                    @if ($part->isLowStock())
                                        <span class="tb-pill tb-pill-danger me-1">⚠ 低庫存</span>
                                        <span class="fw-bold tb-c-danger">{{ $part->withUnit($part->current_stock) }}</span>
                                    @else
                                        <span class="fw-medium">{{ $part->withUnit($part->current_stock) }}</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap tb-num tb-muted">{{ $part->withUnit($part->safety_stock) }}</td>
                                <td class="text-center text-nowrap">
                                    <a href="{{ route('parts.show', $part) }}" class="tb-pill tb-pill-ok text-decoration-none">查看履歷</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $parts->links() }}</div>
    @endif
</div>

    {{-- 低庫存品項視窗：列出所有「目前庫存低於安全庫存」的零件，點一行可進入零件履歷。 --}}
    <div class="modal fade" id="lowStockModal" tabindex="-1" aria-labelledby="lowStockModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="lowStockModalTitle">目前低庫存品項（{{ $lowStockParts->count() }}）</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
                </div>
                <div class="modal-body">
                    @if ($lowStockParts->isEmpty())
                        <p class="text-muted text-center my-4">目前沒有低庫存的零件。</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0 part-rows">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-nowrap">零件編號</th>
                                        <th>名稱</th>
                                        <th>規格</th>
                                        <th>品牌／型號</th>
                                        <th class="text-end text-nowrap">目前庫存</th>
                                        <th class="text-end text-nowrap">安全庫存</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lowStockParts as $part)
                                        <tr class="part-row" data-href="{{ route('parts.show', $part) }}" tabindex="0" role="link" aria-label="查看 {{ $part->part_code }} {{ $part->name }} 的零件履歷">
                                            <td class="text-nowrap">{{ $part->part_code }}</td>
                                            <td>{{ $part->name }}</td>
                                            <td>{{ $part->specification ?? '—' }}</td>
                                            <td>{{ $part->brandModel() ?? '—' }}</td>
                                            <td class="text-end text-nowrap text-danger fw-semibold">{{ $part->withUnit($part->current_stock) }}</td>
                                            <td class="text-end text-nowrap">{{ $part->withUnit($part->safety_stock) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    @if ($lowStockParts->isNotEmpty())
                        <a href="{{ route('parts.low-stock.export') }}" class="btn btn-sm btn-success">
                            <i class="bi bi-file-earmark-excel me-1"></i>匯出 Excel
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>
        .part-row { cursor: pointer; }
        /* 桌面寬度：fit 欄位縮到剛好放得下內容，表格仍拉滿畫面，多出的寬度由「品牌／型號」吃掉；手機版仍由 onion 版面改成卡片式。 */
        @media (min-width: 768px) { .table-fit th.fit { width: 1%; white-space: nowrap; } }
        .part-row:focus-visible { outline: 2px solid var(--tb-accent); outline-offset: -2px; }
    </style>
    <script>
    // 整行可點：點任何一格（或按 Enter）都進入該零件的履歷；點到行內的按鈕、連結時照按鈕本身的功能。
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.part-row').forEach(function (row) {
            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, select, textarea')) { return; }
                if (window.getSelection().toString()) { return; }   // 正在選取文字時不跳頁
                window.location.href = row.dataset.href;
            });
            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') { window.location.href = row.dataset.href; }
            });
        });
    });
    </script>
@endsection

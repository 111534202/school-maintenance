@extends('layouts.app')

@section('title', '零件庫存列表')

@section('content')
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">零件庫存列表</h1>
        </div>
        {{-- 右上角：「目前低庫存零件 N」（有低庫存零件時才出現，開啟低庫存視窗，可預覽匯出 Excel／PDF）＋「新增零件」。
             不論螢幕寬窄都一樣，不再顯示紅色提示卡片。 --}}
        <div class="d-flex flex-wrap gap-2 justify-content-end parts-actions">
            @if ($lowStockParts->isNotEmpty())
                <button type="button" class="tb-btn tb-btn-danger" data-bs-toggle="modal" data-bs-target="#lowStockModal">
                    <i class="bi bi-exclamation-triangle d-none d-sm-inline" aria-hidden="true"></i><span class="d-none d-sm-inline">目前低庫存零件</span><span class="d-sm-none">低庫存</span>
                    <span class="tb-btn-count">{{ $lowStockParts->count() }}</span>
                </button>
            @endif
            <a href="{{ route('parts.create') }}" class="tb-btn tb-btn-primary" data-modal-frame data-modal-title="新增零件">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>新增零件
            </a>
        </div>
    </div>

    {{-- 搜尋框和「搜尋」按鈕不論寬窄都在同一行（tb-form-row-nowrap）。 --}}
    <form method="GET" action="{{ route('parts.index') }}" class="tb-form-row tb-form-row-nowrap" role="search">
        <label for="keyword" class="visually-hidden">搜尋（零件編號／名稱／規格／品牌／型號）</label>
        <input type="text" name="keyword" id="keyword" class="tb-input tb-input-pill" style="flex: 1 1 0; min-width: 0"
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
        {{-- 零件表格：螢幕夠大用表格、不夠用清單（全站自動切換）；點一列先開「零件庫存」對話框。 --}}
        <section class="tb-table-wrap" data-no-fit aria-label="零件列表">
            @include('parts._table', ['parts' => $parts])
        </section>

        {{-- 分頁：全站共用 layouts/pager（左邊「顯示第 1 到 15 筆，共 N 筆」，右邊切換頁數）。 --}}
        @include('layouts.pager', ['paginator' => $parts])

        {{-- 匯出：先打開預覽視窗，勾選要匯出的欄位與零件，再選 Excel 或 PDF。 --}}
        <div class="d-flex justify-content-end">
            <button type="button" class="tb-btn tb-btn-outline" data-bs-toggle="modal" data-bs-target="#partsExportModal">
                <i class="bi bi-download" aria-hidden="true"></i> 匯出 Excel／PDF
            </button>
        </div>
    @endif
</div>

    @include('parts._export-modal', ['modalId' => 'partsExportModal', 'modalTitle' => '匯出零件清單', 'exportParts' => $exportParts, 'withKeyword' => true])
    @include('parts._export-modal', ['modalId' => 'lowStockExportModal', 'modalTitle' => '匯出低庫存品項', 'exportParts' => $lowStockParts, 'withKeyword' => false])

    {{-- 低庫存品項視窗：列出所有「目前庫存低於安全庫存」的零件，點一行可進入零件履歷。 --}}
    <div class="modal fade" id="lowStockModal" tabindex="-1" aria-labelledby="lowStockModalTitle" aria-hidden="true">
        {{-- 視窗加寬（low-stock-dialog）：放得下跟列表一樣的表格；放不下（窄螢幕）才用清單。 --}}
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable low-stock-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="lowStockModalTitle">目前低庫存品項（{{ $lowStockParts->count() }}）</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
                </div>
                <div class="modal-body">
                    @if ($lowStockParts->isEmpty())
                        <p class="text-muted text-center my-4">目前沒有低庫存的零件。</p>
                    @else
                        {{-- 跟零件庫存列表一樣的表格（螢幕夠大用表格、不夠用清單）；點一列先開「零件庫存」對話框。 --}}
                        <div class="tb low-stock-list">
                            <div class="tb-table-wrap">
                                @include('parts._table', ['parts' => $lowStockParts, 'label' => '低庫存品項'])
                            </div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    {{-- 匯出 Excel／PDF：換到預覽視窗（只含低庫存品項），可勾選要匯出的欄位與零件。 --}}
                    @if ($lowStockParts->isNotEmpty())
                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#lowStockExportModal">
                            <i class="bi bi-download me-1" aria-hidden="true"></i>匯出 Excel／PDF
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>
        .low-stock-list { gap: 0; padding-bottom: 0; }
        @media (min-width: 1200px) { .low-stock-dialog { max-width: min(1400px, calc(100vw - 2rem)); } }
    </style>
@endsection

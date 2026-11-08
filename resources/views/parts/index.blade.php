@extends('layouts.app')

@section('title', '零件庫存列表')

@section('content')
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">零件庫存列表</h1>
            <p class="tb-subtitle">點選任一列查看零件履歷；庫存由採購紀錄與使用紀錄增減。</p>
        </div>
        <a href="{{ route('parts.create') }}" class="tb-btn tb-btn-primary d-none d-sm-inline-flex" data-modal-frame data-modal-title="新增零件">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>新增零件
        </a>
    </div>

    {{-- 窄畫面（手機）：只留兩個按鈕排成一行——「新增零件」和「查看低庫存品項 N」（不顯示紅色提示卡片，功能相同）。 --}}
    <div class="tb-narrow-actions d-flex d-sm-none">
        <a href="{{ route('parts.create') }}" class="tb-btn tb-btn-primary" data-modal-frame data-modal-title="新增零件">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>新增零件
        </a>
        <button type="button" class="tb-btn {{ $lowStockParts->isNotEmpty() ? 'tb-btn-danger-outline' : 'tb-btn-outline' }}" data-bs-toggle="modal" data-bs-target="#lowStockModal">
            查看低庫存品項
            <span class="tb-pill {{ $lowStockParts->isNotEmpty() ? 'tb-pill-danger' : 'tb-pill-ok' }}" style="padding: 1px 9px">{{ $lowStockParts->count() }}</span>
        </button>
    </div>

    {{-- 低庫存提示列（寬一點的畫面）：標題與「查看目前低庫存品項」按鈕排在同一行；按鈕開啟視窗（可預覽匯出 Excel／PDF）。 --}}
    @if ($lowStockParts->isNotEmpty())
        <div class="tb-alert d-none d-sm-flex" role="status">
            <div class="tb-alert-icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></div>
            {{-- 只顯示幾項低於安全庫存；是哪些零件請按右邊的按鈕查看（不在這裡列出，標題與按鈕才能排在同一行）。 --}}
            <div class="tb-alert-title" style="flex: 1 1 auto; min-width: 0">有 {{ $lowStockParts->count() }} 項零件低於安全庫存</div>
            <button type="button" class="tb-btn tb-btn-danger-outline" data-bs-toggle="modal" data-bs-target="#lowStockModal">
                查看目前低庫存品項
                <span class="tb-pill tb-pill-danger" style="padding: 1px 9px">{{ $lowStockParts->count() }}</span>
            </button>
        </div>
    @else
        <div class="d-none d-sm-flex flex-wrap align-items-center gap-3">
            <span class="tb-note"><i class="bi bi-check-circle me-1 tb-c-accent" aria-hidden="true"></i>目前沒有低於安全庫存的零件。</span>
            <button type="button" class="tb-btn tb-btn-outline tb-btn-sm" data-bs-toggle="modal" data-bs-target="#lowStockModal">
                查看目前低庫存品項 <span class="tb-muted">{{ $lowStockParts->count() }}</span>
            </button>
        </div>
    @endif

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
        {{-- 零件放在同一張大卡片裡（tb-list-grouped，只有外框四角是圓角），一筆一列；點一列進入零件履歷。 --}}
        <section class="tb-list tb-list-grouped" aria-label="零件列表">
            @foreach ($parts as $part)
                @include('parts._list-card', ['part' => $part])
            @endforeach
        </section>

        {{-- 分頁：左邊「顯示第 1 到 15 筆，共 N 筆」，右邊切換頁數；只有一頁時也顯示筆數。 --}}
        @if ($parts->hasPages())
            <div>{{ $parts->links() }}</div>
        @else
            <p class="small text-muted mb-0">顯示第 {{ $parts->firstItem() }} 到 {{ $parts->lastItem() }} 筆，共 {{ $parts->total() }} 筆</p>
        @endif

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
                        {{-- 跟零件庫存列表一樣的清單（沒有表格檢視）；點一列進入零件履歷。 --}}
                        <div class="tb low-stock-list">
                            <div class="tb-list tb-list-grouped">
                                @foreach ($lowStockParts as $part)
                                    @include('parts._list-card', ['part' => $part])
                                @endforeach
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
    </style>
@endsection

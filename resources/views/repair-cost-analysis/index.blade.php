@extends('layouts.app')

@section('title', __('common.nav.cost_analysis'))

@section('content')
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">{{ __('common.nav.cost_analysis') }}</h1>
            <p class="tb-subtitle">依維修紀錄建立時間統計備品材料成本；單價為扣料當下該批採購的單價。</p>
        </div>

    </div>

    {{-- 篩選：直接顯示在頁面上（data-no-onion：不收成右邊滑出的「篩選條件」面板）；寬度不夠時自動換行，不用左右滑動。
         兩個日期都不填時，檢視全部日期。 --}}
    <div class="tb-card tb-card-pad">
    <form method="GET" action="{{ route('repair-cost-analysis.index') }}" class="filter-bar tb-form-row cost-filter" data-no-onion>
        <div class="tb-field">
            <label for="start_date" class="form-label small mb-1">開始日期</label>
            <input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="{{ $startDate ?? '' }}">
        </div>
        <div class="tb-field">
            <label for="end_date" class="form-label small mb-1">結束日期</label>
            <input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="{{ $endDate ?? '' }}">
        </div>
        <div class="tb-field">
            <label for="status" class="form-label small mb-1">案件狀態</label>
            <select id="status" name="status" class="form-select form-select-sm">
                <option value="" @selected(($status ?? '') === '')>全部狀態</option>
                <option value="pending" @selected(($status ?? '') === 'pending')>待處理</option>
                <option value="assigned" @selected(($status ?? '') === 'assigned')>已指派</option>
                <option value="in_progress" @selected(($status ?? '') === 'in_progress')>處理中</option>
                <option value="pending_review" @selected(($status ?? '') === 'pending_review')>待驗收</option>
                <option value="completed" @selected(($status ?? '') === 'completed')>已完成</option>
            </select>
        </div>
        {{-- 報廢損失：零件沒用就壞掉、過期或遺失的損失（依報廢日期，不受案件狀態影響）。 --}}
        <div class="tb-field">
            <label for="scraps" class="form-label small mb-1">報廢損失</label>
            <select id="scraps" name="scraps" class="form-select form-select-sm">
                {{-- 預設（空白）＝一起顯示，所以沒改這一欄時不會算成一個篩選條件。 --}}
                <option value="" @selected($includeScraps)>一起顯示</option>
                <option value="exclude" @selected(! $includeScraps)>不顯示</option>
            </select>
        </div>
        <div class="d-inline-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1" aria-hidden="true"></i>查詢</button>
            <a href="{{ route('repair-cost-analysis.index') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center">清除篩選</a>
        </div>
    </form>
    </div>

    {{-- 成本摘要：缺少單價時，只呈現已知成本，不把缺價當成零元。 --}}
    <div class="tb-card tb-stat-band {{ $includeScraps ? 'tb-stat-band-4' : '' }}">
        <div>
            <div class="tb-stat-label">已知材料費小計</div>
            <div class="tb-stat-value tb-c-accent tb-num">NT$ {{ number_format($knownSubtotal) }}</div>
            <div class="tb-stat-note">依零件使用當下的歷史單價計算。</div>
        </div>
        <div>
            <div class="tb-stat-label">缺少單價的明細數</div>
            <div class="tb-stat-value tb-num {{ $hasIncompleteCost ? 'tb-c-warn' : '' }}">{{ number_format($missingPriceCount) }} 筆</div>
            @if ($hasIncompleteCost)
                <div class="tb-stat-note tb-c-danger"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>部分明細缺少歷史單價，材料費總額尚不完整。</div>
            @else
                <div class="tb-stat-note tb-c-accent"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>目前篩選範圍內沒有缺少單價的明細。</div>
            @endif
        </div>
        @if ($includeScraps)
            <div>
                <div class="tb-stat-label">報廢損失</div>
                <div class="tb-stat-value tb-num {{ $scrapLoss > 0 ? 'tb-c-danger' : '' }}">NT$ {{ number_format($scrapLoss) }}</div>
                <div class="tb-stat-note">共 {{ number_format($scrapCount) }} 筆報廢；材料費＋報廢損失 NT$ {{ number_format($totalCost) }}。</div>
            </div>
        @endif
        <div>
            <div class="tb-stat-label">涉及維修案件數</div>
            <div class="tb-stat-value tb-num">{{ number_format($repairRequestCount) }} 件</div>
            <div class="tb-stat-note">同一案件即使有多筆維修紀錄，也只計算一次。</div>
        </div>
    </div>

    {{-- 備品使用彙總：依備品分組，以橫條比較各備品的已知材料費；缺價明細仍納入使用數量。 --}}
    <section class="tb-card tb-card-pad d-flex flex-column gap-3" aria-labelledby="summaryTitle">
        <h2 id="summaryTitle" class="tb-section-title">備品使用彙總</h2>

        @if ($partUsageSummary->isEmpty())
            <p class="tb-note">此篩選範圍內沒有備品使用紀錄。</p>
        @else
            @php
                $maxSubtotal = max(1, (int) $partUsageSummary->max('known_subtotal'));
            @endphp
            <div class="tb-bars">
                @foreach ($partUsageSummary as $item)
                    @php
                        $barWidth = max(2, (int) round(((int) $item->known_subtotal) / $maxSubtotal * 100));
                    @endphp
                    <div class="tb-bar-row">
                        <div class="fw-medium">{{ $item->part?->name ?? '零件資料不存在' }}</div>
                        <div class="tb-bar-track" aria-hidden="true"><div class="tb-bar-fill" style="width: {{ $barWidth }}%"></div></div>
                        {{-- 金額與「共 N 條」固定排在一起（數量在金額右邊）。 --}}
                        <div class="tb-bar-value tb-num">
                            <span class="fw-bold">NT$ {{ number_format($item->known_subtotal) }}</span>
                            <span class="tb-note">共 {{ number_format($item->total_quantity) }} {{ $item->part?->unit }}</span>
                            @if ((int) $item->missing_price_count > 0)
                                <span class="tb-c-danger tb-bar-missing">缺少單價 {{ number_format($item->missing_price_count) }} 筆</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>


            <p class="tb-note">使用總數量包含缺少歷史單價的明細；已知材料費小計只計算有歷史單價的明細。</p>
        @endif
    </section>

    {{-- 零件使用明細。 --}}
    {{-- 材料費明細：寬螢幕顯示表格、窄螢幕自動切換成清單（auto-table）；清單接在標題列下方。 --}}
    <section class="tb-table-wrap" aria-labelledby="detailTitle">
        <div class="tb-section-head" data-onion-header>
            <h2 id="detailTitle" class="tb-section-title">材料費明細</h2>
        </div>
            @if ($partUsages->isEmpty())
                <p class="tb-note" style="padding: 16px 24px 20px">此日期範圍內沒有可檢視的零件使用紀錄。</p>
            @else
                <div class="table-responsive">
                    <table class="tb-table tb-table-hover cost-table" style="font-size: 0.9rem" data-onion-panel-title="材料費明細" data-onion-list-only>
                        <thead>
                            <tr>
                                <th class="cost-col-first">維修紀錄日期</th>
                                <th>維修案件</th>
                                <th>案件狀態</th>
                                <th>設備</th>
                                <th>教室／地點</th>
                                <th>零件名稱</th>
                                <th class="text-end">數量</th>
                                <th class="text-end">歷史單價</th>
                                <th class="text-end">小計</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($partUsages as $usage)
                                @php
                                    $repairRequest = $usage->repairLog?->repairRequest;
                                    $device = $repairRequest?->device;
                                    $classroom = $repairRequest?->classroom ?? $device?->classroom;   // 報修單填的地點優先，沒有才用設備所在教室

                                    $deviceLabel = $device
                                        ? ($device->device_code ?: $device->asset_code ?: '設備未編碼')
                                        : ($repairRequest?->device_note ?: '未關聯設備');

                                    $locationLabel = $classroom
                                        ? trim(($classroom->name ?? '') . ' ' . ($classroom->classroom_code ?? ''))
                                        : '未記錄地點';
                                @endphp

                                {{-- 點這一列換頁到那張維修案件（跟下面報廢明細點一列到零件履歷一樣）。 --}}
                                <tr @if ($repairRequest) data-href="{{ route('repairs.show', $repairRequest) }}" @endif>
                                    <td class="tb-num text-nowrap">
                                        {{ $usage->repairLog?->created_at?->format('Y-m-d') ?? '—' }}
                                    </td>
                                    <td>
                                        @if ($repairRequest)
                                            <div class="fw-bold">{{ $repairRequest->title }}</div>
                                            <small class="tb-muted">案件 #{{ $repairRequest->id }} · 維修紀錄 #{{ $usage->repair_log_id }}</small>
                                        @else
                                            <span class="text-muted">案件資料不存在</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($repairRequest?->status)
                                            @include('repairs._status-badge', [
                                                'status' => $repairRequest->status,
                                            ])
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $deviceLabel }}</td>
                                    <td>{{ $locationLabel }}</td>
                                    <td>{{ $usage->part?->name ?? '零件資料不存在' }}</td>
                                    <td class="text-end text-nowrap">{{ number_format($usage->quantity) }} {{ $usage->part?->unit }}</td>
                                    <td class="text-end">
                                        @if ($usage->unit_price === null)
                                            <span class="text-danger">缺少單價</span>
                                        @else
                                            NT$ {{ number_format($usage->unit_price) }}
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($usage->unit_price === null)
                                            —
                                        @else
                                            <span class="fw-bold">NT$ {{ number_format($usage->quantity * $usage->unit_price) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
    </section>

    {{-- 報廢明細（篩選「報廢損失：一起顯示」時才有）：依報廢日期篩選；損失金額＝扣到的各批數量×那一批的單價。 --}}
    @if ($includeScraps)
        <section class="tb-table-wrap" aria-labelledby="scrapTitle">
            <div class="tb-section-head" data-onion-header>
                <div class="d-flex flex-wrap align-items-baseline gap-2">
                    <h2 id="scrapTitle" class="tb-section-title">報廢明細</h2>
                    {{-- 沒資料時寫在同一行，不另外多一段（同步 integrate/all eed2947） --}}
                    <span class="tb-note">共 {{ number_format($scrapCount) }} 筆，損失 NT$ {{ number_format($scrapLoss) }}{{ $scraps->isEmpty() ? '；此日期範圍內沒有報廢紀錄。' : '' }}</span>
                </div>
            </div>
            @if ($scraps->isNotEmpty())
                <div class="table-responsive">
                    <table class="tb-table tb-table-hover cost-table" style="font-size: 0.9rem" data-onion-panel-title="報廢明細" data-onion-list-only>
                        <thead>
                            <tr>
                                {{-- 第一欄跟材料費明細一樣寬：「零件名稱」跟上面的「維修案件」對齊。 --}}
                                <th class="cost-col-first">報廢日期</th>
                                <th>零件名稱</th>
                                <th class="text-end">數量</th>
                                <th class="text-end">損失金額</th>
                                <th>登記人</th>
                                <th>原因</th>
                                <th>備註</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($scraps as $scrap)
                                <tr @if ($scrap->part) data-href="{{ route('parts.show', $scrap->part) }}" @endif>
                                    <td class="text-nowrap">{{ $scrap->scrap_date->format('Y-m-d') }}</td>
                                    <td>{{ $scrap->part?->name ?? '零件資料不存在' }}{{ $scrap->part?->specification ? '（' . $scrap->part->specification . '）' : '' }}</td>
                                    <td class="text-end text-nowrap">{{ number_format($scrap->quantity) }} {{ $scrap->part?->unit }}</td>
                                    <td class="text-end text-nowrap"><span class="fw-bold">NT$ {{ number_format($scrap->lossAmount()) }}</span></td>
                                    <td>{{ $scrap->recorder?->name ?? '—' }}</td>
                                    <td>{{ $scrap->reason }}</td>
                                    <td>{{ $scrap->note ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    {{-- 匯出：先打開預覽視窗，勾選要匯出的區塊與欄位，再選 Excel 或 PDF（跟零件庫存列表一樣）。 --}}
    <div class="d-flex justify-content-end">
        <button type="button" class="tb-btn tb-btn-outline" data-bs-toggle="modal" data-bs-target="#costExportModal">
            <i class="bi bi-download" aria-hidden="true"></i> 匯出 Excel／PDF
        </button>
    </div>
</div>

@include('exports._sections-modal', [
    'modalId' => 'costExportModal',
    'modalTitle' => '匯出成本分析報表',
    'excelUrl' => route('repair-cost-analysis.export.excel'),
    'pdfUrl' => route('repair-cost-analysis.export.pdf'),
    'hidden' => request()->only(['start_date', 'end_date', 'status', 'scraps']),
    'note' => '範圍跟著目前的篩選：' . ($startDate ?: '不限') . ' 至 ' . ($endDate ?: '不限') . '；勾選要匯出的區塊與欄位，取消勾選的欄位在預覽裡會隱藏。',
    'sections' => $exportSections,
])

{{-- 材料費明細與報廢的明細表（cost-table）：第一欄左邊跟區塊標題（.tb-section-head，左右 24px）對齊，標題和第一欄標題的第一個字切齊；
     第一欄兩張表一樣寬，下面「零件名稱」才會跟上面「維修案件」垂直對齊。 --}}
<style>
    .tb .cost-table > thead > tr > th:first-child,
    .tb .cost-table > tbody > tr > td:first-child { padding-left: 24px; }
    .tb .cost-table .cost-col-first { width: 9.5rem; min-width: 9.5rem; }
    .tb .cost-table > tbody > tr[data-href] { cursor: pointer; }
</style>
<style>
    /* 篩選欄位放得下就排成一列，不夠寬時換行（不用左右滑動）。 */
    .cost-filter.filter-bar { flex-wrap: wrap; overflow: visible; padding-bottom: 0; margin-bottom: 0; }
    .cost-filter.filter-bar > .tb-field { flex: 1 1 160px; min-width: 0; }
    .cost-filter.filter-bar > .d-inline-flex { flex: 0 0 auto; }
    /* 備品使用彙總：金額右邊緊接著「共 N 條」。 */
    .tb-bar-value { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: baseline; column-gap: 12px; text-align: right; }
    .tb-bar-value .tb-bar-missing { flex-basis: 100%; font-size: 0.85rem; }
</style>
@endsection
@extends('layouts.app')

@section('title', __('common.nav.cost_analysis'))

@section('content')
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">{{ __('common.nav.cost_analysis') }}</h1>
            <p class="tb-subtitle">依維修紀錄建立時間統計備品材料成本；單價為扣料當下該批採購的單價。</p>
        </div>

        <div class="tb-actions">
            {{-- 匯出：先打開預覽視窗，勾選要匯出的區塊與欄位，再選 Excel 或 PDF（跟零件庫存列表一樣）。 --}}
            <button type="button" class="tb-btn tb-btn-outline" data-bs-toggle="modal" data-bs-target="#costExportModal" title="匯出成本分析報表（Excel／PDF）" aria-label="匯出成本分析報表">
                <i class="bi bi-download" aria-hidden="true"></i><span class="d-none d-sm-inline">匯出 Excel／PDF</span>
            </button>
        </div>
    </div>

    {{-- 日期篩選：兩個日期都不填時，檢視全部日期。 --}}
    {{-- 篩選列：用全站共用的 filter-bar，洋蔥式會收成「篩選條件」摘要列，點開從右邊滑出（見 layouts/partials/filter-onion）。 --}}
    <div class="tb-card tb-card-pad">
    <form method="GET" action="{{ route('repair-cost-analysis.index') }}" class="filter-bar tb-form-row">
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
    {{-- 日期等驗證錯誤：放在篩選表單外面（表單會被洋蔥收進滑出的面板，錯誤提示要留在頁面上看得到）。 --}}
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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
                        <div class="text-end fw-bold tb-num">NT$ {{ number_format($item->known_subtotal) }}</div>
                        <div class="text-end tb-note tb-num">
                            共 {{ number_format($item->total_quantity) }}
                            @if ((int) $item->missing_price_count > 0)
                                <span class="tb-c-danger d-block">缺少單價 {{ number_format($item->missing_price_count) }} 筆</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- 同樣的數字也用表格列出，方便對照與螢幕報讀。 --}}
            <details>
                <summary class="tb-note" style="cursor: pointer">以表格檢視</summary>
                <div class="table-responsive mt-2">
                    <table class="tb-table">
                        <thead>
                            <tr>
                                <th>備品名稱</th>
                                <th class="text-end">使用總數量</th>
                                <th class="text-end">已知材料費小計</th>
                                <th class="text-end">缺少單價明細數</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($partUsageSummary as $item)
                                <tr>
                                    <td>{{ $item->part?->name ?? '零件資料不存在' }}</td>
                                    <td class="text-end tb-num">{{ number_format($item->total_quantity) }}</td>
                                    <td class="text-end tb-num">NT$ {{ number_format($item->known_subtotal) }}</td>
                                    <td class="text-end tb-num">
                                        @if ((int) $item->missing_price_count > 0)
                                            <span class="tb-c-danger">{{ number_format($item->missing_price_count) }} 筆</span>
                                        @else
                                            0 筆
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>

            <p class="tb-note">使用總數量包含缺少歷史單價的明細；已知材料費小計只計算有歷史單價的明細。</p>
        @endif
    </section>

    {{-- 零件使用明細。 --}}
    <section class="tb-table-wrap" aria-labelledby="detailTitle">
        <h2 id="detailTitle" class="tb-section-title" style="padding: 20px 24px 12px">材料費明細</h2>

        <div>
            @if ($partUsages->isEmpty())
                <p class="tb-note" style="padding: 0 24px 20px">此日期範圍內沒有可檢視的零件使用紀錄。</p>
            @else
                <div class="table-responsive">
                    <table class="tb-table tb-table-hover" style="font-size: 0.9rem">
                        <thead>
                            <tr>
                                <th>維修紀錄日期</th>
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

                                <tr>
                                    <td class="tb-num text-nowrap">
                                        {{ $usage->repairLog?->created_at?->format('Y-m-d H:i') ?? '—' }}
                                    </td>
                                    <td>
                                        @if ($repairRequest)
                                            <div class="fw-bold">{{ $repairRequest->title }}</div>
                                            <small class="tb-muted">
                                                案件 #{{ $repairRequest->id }}
                                                · 維修紀錄 #{{ $usage->repair_log_id }}
                                            </small>
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
                                    <td class="text-end">{{ number_format($usage->quantity) }}</td>
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
        </div>
    </section>

    {{-- 報廢明細（篩選「報廢損失：一起顯示」時才有）：依報廢日期篩選；損失金額＝扣到的各批數量×那一批的單價。 --}}
    @if ($includeScraps)
        <section class="tb-table-wrap" aria-labelledby="scrapTitle">
            <div class="tb-section-head">
                <div class="d-flex flex-wrap align-items-baseline gap-2">
                    <h2 id="scrapTitle" class="tb-section-title">報廢明細</h2>
                    <span class="tb-note">共 {{ number_format($scrapCount) }} 筆，損失 NT$ {{ number_format($scrapLoss) }}{{ $scraps->isEmpty() ? '；此日期範圍內沒有報廢紀錄。' : '' }}</span>
                </div>
            </div>
            @unless ($scraps->isEmpty())
                <div class="table-responsive">
                    <table class="tb-table tb-table-hover" style="font-size: 0.9rem">
                        <thead>
                            <tr>
                                <th>報廢日期</th>
                                <th>零件名稱</th>
                                <th>原因</th>
                                <th class="text-end">數量</th>
                                <th class="text-end">損失金額</th>
                                <th>登記人</th>
                                <th>備註</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($scraps as $scrap)
                                <tr @if ($scrap->part) data-href="{{ route('parts.show', $scrap->part) }}" @endif>
                                    <td class="text-nowrap">{{ $scrap->scrap_date->format('Y-m-d') }}</td>
                                    <td>{{ $scrap->part?->name ?? '零件資料不存在' }}{{ $scrap->part?->specification ? '（' . $scrap->part->specification . '）' : '' }}</td>
                                    <td>{{ $scrap->reason }}</td>
                                    <td class="text-end text-nowrap">{{ number_format($scrap->quantity) }} {{ $scrap->part?->unit }}</td>
                                    <td class="text-end text-nowrap"><span class="fw-bold">NT$ {{ number_format($scrap->lossAmount()) }}</span></td>
                                    <td>{{ $scrap->recorder?->name ?? '—' }}</td>
                                    <td>{{ $scrap->note ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endunless
        </section>
    @endif
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
@endsection
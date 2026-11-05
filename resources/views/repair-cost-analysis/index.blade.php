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
            <a href="{{ route('repair-cost-analysis.export.excel', request()->only(['start_date', 'end_date', 'status'])) }}" class="tb-btn tb-btn-outline">
                <i class="bi bi-file-earmark-excel" style="color: #1D6B3F" aria-hidden="true"></i>匯出 Excel
            </a>
            <a href="{{ route('repair-cost-analysis.export.pdf', request()->only(['start_date', 'end_date', 'status'])) }}" class="tb-btn tb-btn-outline">
                <i class="bi bi-file-earmark-pdf" style="color: #A3301F" aria-hidden="true"></i>匯出 PDF
            </a>
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
        <div class="d-inline-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1" aria-hidden="true"></i>查詢</button>
            <a href="{{ route('repair-cost-analysis.index') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center">清除篩選</a>
        </div>
    </form>
    </div>

    {{-- 成本摘要：缺少單價時，只呈現已知成本，不把缺價當成零元。 --}}
    <div class="tb-card tb-stat-band">
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
                            共 {{ number_format($item->total_quantity) }} {{ $item->part?->unit }}
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
                    {{-- 這張是「以表格檢視」本身，不轉成洋蔥式清單。 --}}
                    <table class="tb-table" data-no-onion>
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
                                    <td class="text-end tb-num text-nowrap">{{ number_format($item->total_quantity) }} {{ $item->part?->unit }}</td>
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
    {{-- 標題列（data-onion-header）：洋蔥式的「表格／清單檢視」切換鈕會放在它的右邊，清單接在標題列下方（跟零件履歷的採購紀錄一樣）。 --}}
    <section class="tb-table-wrap" aria-labelledby="detailTitle">
        <div class="tb-section-head" data-onion-header>
            <h2 id="detailTitle" class="tb-section-title">材料費明細</h2>
        </div>
            @if ($partUsages->isEmpty())
                <p class="tb-note" style="padding: 16px 24px 20px">此日期範圍內沒有可檢視的零件使用紀錄。</p>
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
</div>
@endsection
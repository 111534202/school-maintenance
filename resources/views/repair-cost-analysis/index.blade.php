@extends('layouts.app')

@section('title', '第一版成本分析')

@section('content')
<div class="container-fluid">
    <div class="page-header mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">第一版成本分析</h1>
            <p class="text-muted mb-0">
                依維修紀錄建立時間統計備品材料成本。
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a
                href="{{ route('repair-cost-analysis.export.excel', request()->only(['start_date', 'end_date', 'status'])) }}"
                class="btn btn-success"
            >
                <i class="bi bi-file-earmark-excel me-1"></i>
                匯出 Excel
            </a>

            <a
                href="{{ route('repair-cost-analysis.export.pdf', request()->only(['start_date', 'end_date', 'status'])) }}"
                class="btn btn-danger"
            >
                <i class="bi bi-file-earmark-pdf me-1"></i>
                匯出 PDF
            </a>
        </div>
    </div>

    {{-- 日期篩選：兩個日期都不填時，檢視全部日期。 --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET"
                  action="{{ route('repair-cost-analysis.index') }}"
                  class="row g-3 align-items-end">

                <div class="col-12 col-md-4">
                    <label for="start_date" class="form-label">開始日期</label>
                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        class="form-control"
                        value="{{ $startDate ?? '' }}"
                    >
                </div>

                <div class="col-12 col-md-4">
                    <label for="end_date" class="form-label">結束日期</label>
                    <input
                        type="date"
                        id="end_date"
                        name="end_date"
                        class="form-control"
                        value="{{ $endDate ?? '' }}"
                    >
                </div>

                <div class="col-12 col-md-4">
                    <label for="status" class="form-label">案件狀態</label>
                    <select
                        id="status"
                        name="status"
                        class="form-select"
                    >
                        <option value="" @selected(($status ?? '') === '')>全部狀態</option>
                        <option value="pending" @selected(($status ?? '') === 'pending')>待處理</option>
                        <option value="assigned" @selected(($status ?? '') === 'assigned')>已指派</option>
                        <option value="in_progress" @selected(($status ?? '') === 'in_progress')>處理中</option>
                        <option value="pending_review" @selected(($status ?? '') === 'pending_review')>待驗收</option>
                        <option value="completed" @selected(($status ?? '') === 'completed')>已完成</option>
                    </select>
                </div>

                <div class="col-12 col-md-4 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i>查詢
                    </button>

                    <a
                        href="{{ route('repair-cost-analysis.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        清除篩選
                    </a>
                </div>
            </form>

            @if ($errors->any())
                <div class="alert alert-danger mt-3 mb-0" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    {{-- 成本摘要：缺少單價時，只呈現已知成本，不把缺價當成零元。 --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">已知材料費小計</div>
                    <div class="fs-3 fw-semibold">
                        NT$ {{ number_format($knownSubtotal) }}
                    </div>
                    <div class="text-muted small mt-2">
                        依零件使用當下的歷史單價計算。
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">缺少單價的明細數</div>
                    <div class="fs-3 fw-semibold">
                        {{ number_format($missingPriceCount) }} 筆
                    </div>

                    @if ($hasIncompleteCost)
                        <div class="text-danger small mt-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            部分明細缺少歷史單價，材料費總額尚不完整。
                        </div>
                    @else
                        <div class="text-success small mt-2">
                            <i class="bi bi-check-circle me-1"></i>
                            目前篩選範圍內沒有缺少單價的明細。
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">涉及維修案件數</div>
                    <div class="fs-3 fw-semibold">
                        {{ number_format($repairRequestCount) }} 件
                    </div>
                    <div class="text-muted small mt-2">
                        同一案件即使有多筆維修紀錄，也只計算一次。
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 備品使用彙總：依備品分組，缺價明細仍納入使用數量。 --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">備品使用彙總</h2>
        </div>

        <div class="card-body">
            @if ($partUsageSummary->isEmpty())
                <p class="text-muted mb-0">
                    此篩選範圍內沒有備品使用紀錄。
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light">
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
                                    <td class="text-end">
                                        {{ number_format($item->total_quantity) }}
                                    </td>
                                    <td class="text-end">
                                        NT$ {{ number_format($item->known_subtotal) }}
                                    </td>
                                    <td class="text-end">
                                        @if ((int) $item->missing_price_count > 0)
                                            <span class="text-danger">
                                                {{ number_format($item->missing_price_count) }} 筆
                                            </span>
                                        @else
                                            0 筆
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-muted small mt-3 mb-0">
                    使用總數量包含缺少歷史單價的明細；
                    已知材料費小計只計算有歷史單價的明細。
                </p>
            @endif
        </div>
    </div>

    {{-- 零件使用明細。 --}}
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">材料費明細</h2>
        </div>

        <div class="card-body">
            @if ($partUsages->isEmpty())
                <p class="text-muted mb-0">
                    此日期範圍內沒有可檢視的零件使用紀錄。
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light">
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
                                    $classroom = $device?->classroom;

                                    $deviceLabel = $device
                                        ? ($device->device_code ?: $device->asset_code ?: '設備未編碼')
                                        : ($repairRequest?->device_note ?: '未關聯設備');

                                    $locationLabel = $classroom
                                        ? trim(($classroom->name ?? '') . ' ' . ($classroom->classroom_code ?? ''))
                                        : ($repairRequest?->location ?: '未記錄地點');
                                @endphp

                                <tr>
                                    <td>
                                        {{ $usage->repairLog?->created_at?->format('Y-m-d H:i') ?? '—' }}
                                    </td>
                                    <td>
                                        @if ($repairRequest)
                                            <div>{{ $repairRequest->title }}</div>
                                            <small class="text-muted">
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
                                            NT$ {{ number_format($usage->quantity * $usage->unit_price) }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
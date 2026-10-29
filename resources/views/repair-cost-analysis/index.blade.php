@extends('layouts.app')

@section('title', '第一版成本分析')

@section('content')
<div class="container-fluid">
    <div class="page-header mb-4">
        <div>
            <h1 class="h3 mb-1">第一版成本分析</h1>
            <p class="text-muted mb-0">
                依維修紀錄建立時間統計備品材料成本。
            </p>
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

                <div class="col-12 col-md-4 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i>查詢
                    </button>

                    <a
                        href="{{ route('repair-cost-analysis.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        全部日期
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
        <div class="col-12 col-md-6">
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

        <div class="col-12 col-md-6">
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
                                <th>維修紀錄編號</th>
                                <th>零件名稱</th>
                                <th class="text-end">數量</th>
                                <th class="text-end">歷史單價</th>
                                <th class="text-end">小計</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($partUsages as $usage)
                                <tr>
                                    <td>
                                        {{ $usage->repairLog?->created_at?->format('Y-m-d H:i') ?? '—' }}
                                    </td>
                                    <td>
                                        #{{ $usage->repair_log_id }}
                                    </td>
                                    <td>
                                        {{ $usage->part?->name ?? '零件資料不存在' }}
                                    </td>
                                    <td class="text-end">
                                        {{ number_format($usage->quantity) }}
                                    </td>
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
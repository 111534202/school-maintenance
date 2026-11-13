{{-- 修改保養工單頁（對應 MaintenanceOrderController::edit / update）：需要 maintenance.manage，只能改「排定保養日期」。
     已完成（已有保養結果）的工單不能改。 --}}
@extends('layouts.app')

@section('title', '修改保養工單 #' . $maintenanceOrder->id)

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-pencil-square me-2"></i>修改保養工單 #{{ $maintenanceOrder->id }}</h1>

        <form method="POST" action="{{ route('maintenance-orders.update', $maintenanceOrder) }}">
            @csrf
            @method('PUT')

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">來源保養計畫</label>
                        <div class="form-control-plaintext">{{ $maintenanceOrder->maintenancePlan?->name ?? ($maintenanceOrder->source === 'ai' ? 'AI 預防保養' : '—') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="scheduled_date" class="form-label">排定保養日期</label>
                        <input type="date" id="scheduled_date" name="scheduled_date" class="form-control @error('scheduled_date') is-invalid @enderror" required
                            value="{{ old('scheduled_date', $maintenanceOrder->scheduled_date?->toDateString()) }}">
                        @error('scheduled_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('maintenance-orders.show', $maintenanceOrder) }}">取消</a>
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>儲存修改</button>
            </div>
        </form>
    </div>
@endsection

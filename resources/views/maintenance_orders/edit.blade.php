{{-- 修改保養工單頁（對應 MaintenanceOrderController::edit / update）：需要 maintenance.manage，只能改「排定保養日期」。
     已完成（已有保養結果）的工單不能改。 --}}
@extends('layouts.app')

@section('title', '修改保養工單 ' . $maintenanceOrder->displayNumber())

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-pencil-square me-2"></i>修改保養工單 {{ $maintenanceOrder->displayNumber() }}</h1>

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
                        {{-- 設備主檔記的設備位置（教室）就是這裡的課表：挑沒課的日子，不會排到上課、已核准預約的時候。 --}}
                        @php $ttClassroom = $maintenanceOrder->device?->classroom; @endphp
                        @if ($ttClassroom)
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#timetableModal" data-target-input="scheduled_date">
                                <i class="bi bi-calendar-week me-1"></i>從 {{ $ttClassroom->name }} 的課表挑日期
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('maintenance-orders.show', $maintenanceOrder) }}">取消</a>
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>儲存修改</button>
            </div>
        </form>
    </div>
    @if ($ttClassroom)
        @include('layouts.partials.classroom-timetable-modal', ['classroom' => $ttClassroom, 'url' => route('devices.timetable', $maintenanceOrder->device), 'mode' => 'date', 'busyLabel' => '同教室已排保養', 'busyHint' => '日期標題下方會列出同一間教室其他設備已排的保養工單'])
    @endif
@endsection

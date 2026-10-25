@extends('layouts.app')

@section('title', '新增空間預約')

@section('content')
<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="{{ route('reservations.index') }}" class="text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>
            返回預約列表
        </a>
        <h1 class="h3 mt-3 mb-1">新增空間預約</h1>
        <p class="text-muted mb-0">請填寫教室及使用時段，送出後將進入待審核狀態。</p>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            @if ($classrooms->isEmpty())
                <div class="alert alert-warning mb-0">
                    目前沒有可供預約的教室。請確認教室是否已啟用，以及是否正常開放外借。
                </div>
            @else
                <form method="POST" action="{{ route('reservations.store') }}">
                    @csrf

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="classroom_id" class="form-label">
                                預約教室 <span class="text-danger">*</span>
                            </label>
                            <select
                                id="classroom_id"
                                name="classroom_id"
                                class="form-select @error('classroom_id') is-invalid @enderror"
                                required
                            >
                                <option value="">請選擇教室</option>
                                @foreach ($classrooms as $classroom)
                                    <option
                                        value="{{ $classroom->id }}"
                                        @selected((string) old('classroom_id') === (string) $classroom->id)
                                    >
                                        {{ $classroom->name }}
                                        （{{ $classroom->classroom_code }}）
                                    </option>
                                @endforeach
                            </select>
                            @error('classroom_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="class_name" class="form-label">
                                班級名稱 <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                id="class_name"
                                name="class_name"
                                class="form-control @error('class_name') is-invalid @enderror"
                                value="{{ old('class_name') }}"
                                maxlength="100"
                                placeholder="例如：一年甲班"
                                required
                            >
                            @error('class_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="reservation_date" class="form-label">
                                預約日期 <span class="text-danger">*</span>
                            </label>
                            <input
                                type="date"
                                id="reservation_date"
                                name="reservation_date"
                                class="form-control @error('reservation_date') is-invalid @enderror"
                                value="{{ old('reservation_date') }}"
                                min="{{ now()->toDateString() }}"
                                required
                            >
                            @error('reservation_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <label for="start_time" class="form-label">
                                開始時間 <span class="text-danger">*</span>
                            </label>
                            <input
                                type="time"
                                id="start_time"
                                name="start_time"
                                class="form-control @error('start_time') is-invalid @enderror"
                                value="{{ old('start_time') }}"
                                required
                            >
                            @error('start_time')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <label for="end_time" class="form-label">
                                結束時間 <span class="text-danger">*</span>
                            </label>
                            <input
                                type="time"
                                id="end_time"
                                name="end_time"
                                class="form-control @error('end_time') is-invalid @enderror"
                                value="{{ old('end_time') }}"
                                required
                            >
                            @error('end_time')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="purpose" class="form-label">使用用途</label>
                            <textarea
                                id="purpose"
                                name="purpose"
                                class="form-control @error('purpose') is-invalid @enderror"
                                rows="3"
                                maxlength="255"
                                placeholder="請簡述借用教室的用途（選填）"
                            >{{ old('purpose') }}</textarea>
                            @error('purpose')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="alert alert-info mt-4 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        送出後會先進入待審核狀態。同一間教室在相同日期的重疊時段，不可重複申請。
                    </div>

                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                        <a href="{{ route('reservations.index') }}" class="btn btn-outline-secondary">
                            取消
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i>
                            送出預約申請
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
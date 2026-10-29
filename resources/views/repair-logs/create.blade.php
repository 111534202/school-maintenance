{{-- 維修填單頁（對應 RepairLogController::create / store）：維修人員處理完填故障原因、處置方式、起訖時間、使用備品與照片。 --}}
{{-- 送出後案件狀態自動從「處理中」變成「待驗收」。（Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
{{-- 維修填單頁：維修原因、處置方式、起訖時間、備品明細與附件。 --}}
@extends('layouts.app')

@section('title', __('repair_logs.create_title'))

@section('content')
    @php
        $usageRows = old('part_usages', [[], [], []]);
    @endphp

    <div class="page-narrow">
        <h1 class="h4 mb-1">
            <i class="bi bi-journal-plus me-2"></i>{{ __('repair_logs.create_title') }}
        </h1>

        <p class="text-muted mb-4">
            {{ __('repair_logs.case_line', [
                'title' => $repairRequest->title,
                'location' => $repairRequest->device->device_code
                    ?? $repairRequest->device_note
                    ?? $repairRequest->location
                    ?? __('repair_logs.not_filled_location')
            ]) }}
        </p>

        <form method="POST"
              action="{{ route('repair-logs.store', $repairRequest) }}"
              enctype="multipart/form-data">
            @csrf

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">維修處理資料</h2>

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="cause" class="form-label">
                                {{ __('repair_logs.form.cause') }}
                            </label>
                            <textarea
                                class="form-control @error('cause') is-invalid @enderror"
                                id="cause"
                                name="cause"
                                rows="3"
                                required>{{ old('cause') }}</textarea>
                            @error('cause')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="resolution" class="form-label">
                                {{ __('repair_logs.form.resolution') }}
                            </label>
                            <textarea
                                class="form-control @error('resolution') is-invalid @enderror"
                                id="resolution"
                                name="resolution"
                                rows="3"
                                required>{{ old('resolution') }}</textarea>
                            @error('resolution')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="started_at" class="form-label">
                                {{ __('repair_logs.form.started_at') }}
                            </label>
                            <input
                                type="datetime-local"
                                class="form-control @error('started_at') is-invalid @enderror"
                                id="started_at"
                                name="started_at"
                                value="{{ old('started_at') }}"
                                required>
                            @error('started_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="ended_at" class="form-label">
                                {{ __('repair_logs.form.ended_at') }}
                            </label>
                            <input
                                type="datetime-local"
                                class="form-control @error('ended_at') is-invalid @enderror"
                                id="ended_at"
                                name="ended_at"
                                value="{{ old('ended_at') }}"
                                required>
                            @error('ended_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- 正式零件使用明細 --}}
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-2">
                        <i class="bi bi-box-seam me-2"></i>使用備品明細
                    </h2>

                    <p class="text-muted small">
                        如有使用備品，請選擇零件並輸入數量。
                        送出成功後，系統會建立使用明細並扣除庫存。
                        不使用備品時可以留白。
                    </p>

                    @if ($parts->isEmpty())
                        <div class="alert alert-info mb-0">
                            目前沒有庫存大於零的零件可供選擇。
                        </div>
                    @else
                        @foreach ($usageRows as $index => $usage)
                            <div class="row g-2 mb-3">
                                <div class="col-md-8">
                                    <label
                                        for="part_id_{{ $index }}"
                                        class="form-label">
                                        零件 {{ $index + 1 }}
                                    </label>

                                    <select
                                        class="form-select @error("part_usages.$index.part_id") is-invalid @enderror"
                                        id="part_id_{{ $index }}"
                                        name="part_usages[{{ $index }}][part_id]">
                                        <option value="">不選擇零件</option>

                                        @foreach ($parts as $part)
                                            <option
                                                value="{{ $part->id }}"
                                                @selected((string) data_get($usage, 'part_id') === (string) $part->id)>
                                                {{ $part->name }}
                                                @if ($part->specification)
                                                    （{{ $part->specification }}）
                                                @endif
                                                — 庫存：{{ $part->current_stock }}
                                            </option>
                                        @endforeach
                                    </select>

                                    @error("part_usages.$index.part_id")
                                        <div class="text-danger small mt-1">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label
                                        for="quantity_{{ $index }}"
                                        class="form-label">
                                        使用數量
                                    </label>

                                    <input
                                        type="number"
                                        class="form-control @error("part_usages.$index.quantity") is-invalid @enderror"
                                        id="quantity_{{ $index }}"
                                        name="part_usages[{{ $index }}][quantity]"
                                        value="{{ data_get($usage, 'quantity') }}"
                                        min="1"
                                        step="1"
                                        placeholder="例如：2">

                                    @error("part_usages.$index.quantity")
                                        <div class="text-danger small mt-1">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                        @endforeach

                        <div class="form-text">
                            目前提供 {{ count($usageRows) }} 列。
                            同一種零件請勿重複選取；庫存不足時系統會拒絕送出。
                        </div>
                    @endif

                    @error('part_usages')
                        <div class="alert alert-danger mt-2 mb-0">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>

            {{-- 保留舊的備品文字備註，不作為正式扣庫存依據 --}}
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="parts_used_note" class="form-label">
                            {{ __('repair_logs.form.parts_used_note') }}
                        </label>

                        <input
                            type="text"
                            class="form-control @error('parts_used_note') is-invalid @enderror"
                            id="parts_used_note"
                            name="parts_used_note"
                            value="{{ old('parts_used_note') }}">

                        <div class="form-text">
                            可填寫其他備註；正式零件庫存請使用上方明細。
                        </div>

                        @error('parts_used_note')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label for="attachments" class="form-label">
                            {{ __('repair_logs.form.attachments') }}
                        </label>

                        <input
                            type="file"
                            class="form-control @error('attachments') is-invalid @enderror"
                            id="attachments"
                            name="attachments[]"
                            multiple
                            accept=".jpg,.jpeg,.png,.pdf,.mp4,.mov,.webm">

                        <div class="form-text">
                            最多 5 個附件，每個檔案不超過 20 MB。
                        </div>

                        @error('attachments')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror

                        @foreach (old('attachments', []) as $index => $unused)
                            @error("attachments.$index")
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a
                    class="btn btn-outline-secondary"
                    href="{{ route('repairs.show', $repairRequest) }}">
                    <i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.cancel') }}
                </a>

                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-send me-1"></i>{{ __('repair_logs.submit') }}
                </button>
            </div>
        </form>
    </div>
@endsection

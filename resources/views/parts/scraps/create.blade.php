{{-- 登記零件報廢（零件履歷的「報廢紀錄 → 登記報廢」，在畫面中的對話框開啟）。
     報廢會從目前庫存扣掉，扣哪一批跟維修、保養用料一樣先進先出（PartStockService::scrap）。 --}}
@extends('layouts.app')

@section('title', '登記報廢：' . $part->part_code)

@section('content')
    <h1 data-modal-hide class="tb-title mb-1">登記報廢</h1>
    <p class="text-muted mb-3">{{ $part->name }}{{ $part->specification ? '（' . $part->specification . '）' : '' }}</p>

    <div>
        @if ($part->current_stock < 1)
            <div class="alert alert-warning mb-3">目前沒有庫存，不能登記報廢。</div>
        @endif

        <form method="POST" action="{{ route('parts.scraps.store', $part) }}">
            @csrf
            <div class="card shadow-sm">
                <div class="card-header bg-white form-section-title"><i class="bi bi-trash3 me-2"></i>報廢資料</div>
                <div class="card-body">

            <div class="row g-3">
                <div class="col-sm-4">
                    <label for="scrap_date" class="form-label">報廢日期 <span class="text-danger">*</span></label>
                    <input type="date" id="scrap_date" name="scrap_date" max="{{ now()->toDateString() }}" required
                        class="form-control @error('scrap_date') is-invalid @enderror"
                        value="{{ old('scrap_date', now()->toDateString()) }}">
                    @error('scrap_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                {{-- 時間：同一天的使用與報廢依時間先後扣批次（預設現在）。 --}}
                <div class="col-sm-3">
                    <label for="scrap_time" class="form-label">時間 <span class="text-danger">*</span></label>
                    <input type="time" id="scrap_time" name="scrap_time" required
                        class="form-control @error('scrap_time') is-invalid @enderror"
                        value="{{ old('scrap_time', now()->format('H:i')) }}">
                    @error('scrap_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-5">
                    <label for="quantity" class="form-label">報廢數量 <span class="text-danger">*</span></label>
                    <div class="input-group has-validation">
                        <input type="number" id="quantity" name="quantity" min="1" max="{{ max(1, $part->current_stock) }}" required
                            class="form-control @error('quantity') is-invalid @enderror" value="{{ old('quantity', 1) }}">
                        @if ($part->unit)
                            <span class="input-group-text">{{ $part->unit }}</span>
                        @endif
                        @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="form-text mb-3">目前庫存 {{ $part->withUnit($part->current_stock) }}；報廢後依日期與時間，從當時最早買進的批次扣除（先進先出）。</div>

            <div class="mb-3">
                <label for="reason" class="form-label">報廢原因 <span class="text-danger">*</span></label>
                <input type="text" id="reason" name="reason" maxlength="50" required list="scrapReasons" autocomplete="off"
                    class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" placeholder="例如 損壞、過期、遺失">
                <datalist id="scrapReasons">
                    @foreach ($reasons as $reason)
                        <option value="{{ $reason }}">
                    @endforeach
                </datalist>
                {{-- 常見原因：點一下就填入。 --}}
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach ($reasons as $reason)
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-scrap-reason="{{ $reason }}">{{ $reason }}</button>
                    @endforeach
                </div>
                @error('reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-4">
                <label for="note" class="form-label">備註</label>
                <input type="text" id="note" name="note" maxlength="255" placeholder="例如 哪裡壞掉、在哪裡發現"
                    class="form-control @error('note') is-invalid @enderror" value="{{ old('note') }}">
                @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            </div>
            </div>

            {{-- 取消／送出按鈕放在外框外面、靠右。 --}}
            <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="{{ route('parts.show', $part) }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>取消</a>
                <button type="submit" class="btn btn-danger" @disabled($part->current_stock < 1)>登記報廢並扣除庫存</button>
            </div>
        </form>
    </div>

    <script>
        document.querySelectorAll('[data-scrap-reason]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('reason').value = button.dataset.scrapReason;
            });
        });
    </script>
@endsection

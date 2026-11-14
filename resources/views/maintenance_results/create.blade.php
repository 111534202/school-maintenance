@extends('layouts.app')

@section('title', '回報保養結果')

@section('content')
    <h1 data-modal-hide class="tb-title mb-3">回報保養結果</h1>

    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-1"><strong>工單編號：</strong>#{{ $order->id }}</p>
            <p class="mb-1"><strong>來源計畫：</strong>@if ($order->maintenancePlan)@include('maintenance_orders._plan_name', ['plan' => $order->maintenancePlan])@else — @endif</p>
            {{-- 跟保養紀錄一樣：「設備編號（類別）」，設備編號可點到設備履歷；沒有設備時只顯示類別。 --}}
            <p class="mb-0"><strong>設備 / 類別：</strong>@include('maintenance_orders._device_category', ['order' => $order])</p>
        </div>
    </div>

    <form action="{{ route('maintenance-orders.results.store', $order) }}" method="POST">
        @csrf
        <div class="card shadow-sm">
            <div class="card-header bg-white form-section-title"><i class="bi bi-clipboard-check me-2"></i>保養結果</div>
            <div class="card-body">

        <div class="mb-3">
            <label class="form-label">保養結果 <span class="text-danger">*</span></label>
            <div>
                <div class="form-check form-check-inline">
                    <input type="radio" name="result" id="result_ok" value="ok" class="form-check-input"
                        {{ old('result') !== 'ng' ? 'checked' : '' }}>
                    <label for="result_ok" class="form-check-label">OK 正常</label>
                </div>
                <div class="form-check form-check-inline">
                    <input type="radio" name="result" id="result_ng" value="ng" class="form-check-input"
                        {{ old('result') === 'ng' ? 'checked' : '' }}>
                    <label for="result_ng" class="form-check-label">NG 異常</label>
                </div>
            </div>
            @error('result')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
            <div class="form-text">選擇 NG 送出後，會自動建立報修單，並記錄在工單上。</div>
        </div>

        <div class="mb-3">
            <label class="form-label">執行人</label>
            {{-- 執行人固定是登入帳號（寫入時由系統帶入 users.id），所以只顯示、不讓人改。 --}}
            {{-- 跟零件資料的「目前庫存」一樣：灰色不能改的輸入框，下面是同樣顏色、帶鎖頭的說明。 --}}
            <input type="text" class="form-control" value="{{ auth()->user()->name }}" disabled>
            <div class="form-text text-warning-emphasis"><i class="bi bi-lock"></i> 由登入帳號自動帶入，不能手動修改</div>
        </div>

        <div class="mb-3">
            <label for="executed_at" class="form-label">執行時間 <span class="text-danger">*</span></label>
            <input type="datetime-local" name="executed_at" id="executed_at"
                class="form-control @error('executed_at') is-invalid @enderror"
                value="{{ old('executed_at', now()->format('Y-m-d\TH:i')) }}" required>
            @error('executed_at')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <label for="note" class="form-label">備註</label>
            <textarea name="note" id="note" class="form-control @error('note') is-invalid @enderror"
                rows="3" maxlength="2000" placeholder="NG 時可說明異常狀況">{{ old('note') }}</textarea>
            @error('note')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        {{-- 保養用料（可不填）：和維修紀錄共用同一個零件選擇器；送出時依先進先出扣庫存，庫存不足會擋下。 --}}
        <div class="mb-4">
            <label class="form-label">零件使用明細</label>
            @include('parts._usage-picker', ['parts' => $parts])
        </div>
        </div>
        </div>

        {{-- 取消／送出按鈕放在外框外面、靠右。 --}}
        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('maintenance-orders.show', $order) }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>取消</a>
            <button type="submit" class="btn btn-primary">送出</button>
        </div>
    </form>
@endsection

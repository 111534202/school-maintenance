@extends('layouts.app')

@section('title', '回報保養結果')

@section('content')
    <h1 class="h3 mb-3">回報保養結果</h1>

    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-1"><strong>工單編號：</strong>#{{ $order->id }}</p>
            <p class="mb-1"><strong>來源計畫：</strong>{{ $order->maintenancePlan?->name ?? '—' }}</p>
            <p class="mb-0"><strong>設備類別：</strong></p>{{ $order->categoryName() ?? '—' }}
        </div>
    </div>

    <form action="{{ route('maintenance-orders.results.store', $order) }}" method="POST" class="bg-white p-4 rounded shadow-sm">
        @csrf

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
            <div class="form-text">選擇 NG 送出後，會標記這張工單待轉報修（等彭仕衡的報修介面串接後會自動建立）。</div>
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

        <button type="submit" class="btn btn-primary">送出</button>
        <a href="{{ route('maintenance-orders.show', $order) }}" class="btn btn-outline-secondary">取消</a>
    </form>
@endsection

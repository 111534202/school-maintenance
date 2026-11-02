@extends('layouts.app')

@section('title', '回報保養結果')

@section('content')
    <h1 class="h3 mb-3">回報保養結果</h1>

    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-1"><strong>工單編號：</strong>#{{ $order->id }}</p>
            <p class="mb-1"><strong>來源計畫：</strong>{{ $order->maintenancePlan?->name ?? '—' }}</p>
            <p class="mb-0"><strong>設備類別：</strong>{{ $order->categoryName() ?? '—' }}</p>
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
            <label class="form-label">執行人</label>
            {{-- 執行人固定是登入帳號（寫入時由系統帶入 users.id），所以只顯示、不讓人改。 --}}
            <input type="text" class="form-control" value="{{ auth()->user()->name }}" readonly>
            <div class="form-text">由登入帳號自動帶入，不能手動修改。</div>
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

        {{-- 保養用料（可不填）：送出時依先進先出扣庫存，庫存不足會擋下。 --}}
        <div class="mb-4">
            <label class="form-label">使用零件</label>
            <div class="form-text mb-2">保養時有更換零件才需要填；送出後會自動扣除零件庫存。</div>
            @php($usageRows = old('part_usages', [[], []]))
            @foreach ($usageRows as $index => $usage)
                <div class="row g-2 mb-2">
                    <div class="col-md-8">
                        <select name="part_usages[{{ $index }}][part_id]" aria-label="零件 {{ $index + 1 }}"
                            class="form-select @error("part_usages.$index.part_id") is-invalid @enderror">
                            <option value="">不選擇零件</option>
                            @foreach ($parts as $part)
                                <option value="{{ $part->id }}" @selected((string) data_get($usage, 'part_id') === (string) $part->id)>
                                    {{ $part->part_code }} {{ $part->name }}@if ($part->specification)（{{ $part->specification }}）@endif — 庫存：{{ $part->current_stock }}
                                </option>
                            @endforeach
                        </select>
                        @error("part_usages.$index.part_id")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <input type="number" min="1" name="part_usages[{{ $index }}][quantity]" aria-label="零件 {{ $index + 1 }} 數量"
                            placeholder="數量" value="{{ data_get($usage, 'quantity') }}"
                            class="form-control @error("part_usages.$index.quantity") is-invalid @enderror">
                        @error("part_usages.$index.quantity")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            @endforeach
            @if ($parts->isEmpty())
                <div class="form-text">目前沒有可用庫存的零件。</div>
            @endif
        </div>

        <button type="submit" class="btn btn-primary">送出</button>
        <a href="{{ route('maintenance-orders.show', $order) }}" class="btn btn-outline-secondary">取消</a>
    </form>
@endsection

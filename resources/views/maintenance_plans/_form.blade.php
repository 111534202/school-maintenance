@csrf

<div class="mb-3">
    <label for="name" class="form-label">計畫名稱 <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $plan->name) }}" required maxlength="200">
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="device_category" class="form-label">設備類別</label>
    <input type="text" name="device_category" id="device_category"
        class="form-control @error('device_category') is-invalid @enderror"
        value="{{ old('device_category', $plan->device_category) }}" maxlength="100"
        placeholder="例如：空調、電力設備（選填，工程實作欄位，待與林政寬確認 devices 關聯後可能調整）">
    @error('device_category')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="cycle_days" class="form-label">保養週期（天） <span class="text-danger">*</span></label>
        <input type="number" name="cycle_days" id="cycle_days" min="1" max="3650"
            class="form-control @error('cycle_days') is-invalid @enderror"
            value="{{ old('cycle_days', $plan->cycle_days) }}" required>
        @error('cycle_days')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="start_date" class="form-label">起始日 <span class="text-danger">*</span></label>
        <input type="date" name="start_date" id="start_date"
            class="form-control @error('start_date') is-invalid @enderror"
            value="{{ old('start_date', $plan->start_date?->format('Y-m-d')) }}" required>
        <div class="form-text">下次到期日會依「起始日 + 週期天數」自動計算。</div>
        @error('start_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label">保養項目（可複選） <span class="text-danger">*</span></label>
    <div class="border rounded p-3 @error('item_ids') is-invalid @enderror" style="max-height: 260px; overflow-y: auto;">
        @forelse ($items as $availableItem)
            <div class="form-check">
                <input type="checkbox" name="item_ids[]" value="{{ $availableItem->id }}"
                    id="item_{{ $availableItem->id }}" class="form-check-input"
                    {{ in_array($availableItem->id, old('item_ids', $selectedItemIds)) ? 'checked' : '' }}>
                <label for="item_{{ $availableItem->id }}" class="form-check-label">
                    {{ $availableItem->name }}
                    @if ($availableItem->category)
                        <span class="text-muted small">（{{ $availableItem->category }}）</span>
                    @endif
                </label>
            </div>
        @empty
            <p class="text-muted mb-0">目前沒有啟用中的保養項目，請先到「保養項目」新增。</p>
        @endforelse
    </div>
    @error('item_ids')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
    @error('item_ids.*')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="form-check mb-4">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1"
        {{ old('is_active', $plan->is_active) ? 'checked' : '' }}>
    <label for="is_active" class="form-check-label">啟用</label>
</div>

<button type="submit" class="btn btn-primary">儲存</button>
<a href="{{ route('maintenance-plans.index') }}" class="btn btn-outline-secondary">取消</a>

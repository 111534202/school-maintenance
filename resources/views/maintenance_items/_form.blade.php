@csrf

<div class="mb-3">
    <label for="name" class="form-label">名稱 <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $item->name) }}" required maxlength="150">
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="device_category_id" class="form-label">適用設備類別</label>
    <select name="device_category_id" id="device_category_id" class="form-select @error('device_category_id') is-invalid @enderror">
        <option value="">— 不指定 —</option>
        @foreach ($deviceCategories as $deviceCategory)
            <option value="{{ $deviceCategory->id }}" @selected((string) old('device_category_id', $item->device_category_id) === (string) $deviceCategory->id)>{{ $deviceCategory->name }}</option>
        @endforeach
    </select>
    @error('device_category_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="description" class="form-label">說明</label>
    <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror"
        rows="3">{{ old('description', $item->description) }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="default_cycle_days" class="form-label">預設保養週期（天）</label>
    <input type="number" name="default_cycle_days" id="default_cycle_days" min="1" max="3650"
        class="form-control @error('default_cycle_days') is-invalid @enderror"
        value="{{ old('default_cycle_days', $item->default_cycle_days) }}" placeholder="例如：90">
    <div class="form-text">建立保養計畫時可覆寫此預設值（工程實作欄位）。</div>
    @error('default_cycle_days')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-check mb-4">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1"
        {{ old('is_active', $item->is_active) ? 'checked' : '' }}>
    <label for="is_active" class="form-check-label">啟用</label>
</div>

<button type="submit" class="btn btn-primary">儲存</button>
<a href="{{ route('maintenance-items.index') }}" class="btn btn-outline-secondary">取消</a>

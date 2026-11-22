@csrf

<div class="card shadow-sm">
    <div class="card-header bg-white form-section-title"><i class="bi bi-calendar-check me-2"></i>計畫內容</div>
    <div class="card-body">
<div>
<div class="mb-3">
    <label for="name" class="form-label">計畫名稱 <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $plan->name) }}" required maxlength="200">
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- 保養對象：一個計畫一個——一台設備或一個設備類別。可輸入關鍵字搜尋，只能從清單裡選。送出 targets[]＝device:編號 或 category:編號。 --}}
@php
    $optionByValue = $targets->keyBy('value');
    $displayLabel = fn (array $option) => ($option['group'] === '設備類別' ? '類別：' : '設備：') . $option['label'];
    $currentValues = old('targets', $plan->exists
        ? array_filter([$plan->device_id ? 'device:' . $plan->device_id : ($plan->device_category_id ? 'category:' . $plan->device_category_id : null)])
        : []);
    $currentValues = array_slice(array_values(array_filter((array) $currentValues, fn ($value) => is_string($value) && $optionByValue->has($value))), 0, 1);
    if ($currentValues === []) {
        $currentValues = [''];   // 至少一列空白的讓人選
    }
    $targetError = $errors->first('targets');
@endphp
<div class="mb-3">
    <label for="target_search_0" class="form-label">設備／設備類別 <span class="text-danger">*</span></label>
    <div id="planTargets">
        @foreach ($currentValues as $index => $value)
            <div class="plan-target-row d-flex gap-2 mb-2">
                <div class="flex-grow-1 position-relative">
                    <input type="text" id="target_search_{{ $index }}" class="form-control target-search @error('targets.' . $index) is-invalid @enderror"
                        @if ($loop->first) required @endif
                        placeholder="輸入設備編號、類別名稱搜尋" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false"
                        value="{{ $value !== '' ? $displayLabel($optionByValue[$value]) : '' }}">
                    <input type="hidden" name="targets[]" class="target-value" value="{{ $value }}">
                    @error('targets.' . $index)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        @endforeach
    </div>
    <div class="form-text">選「設備類別」＝這一類的設備一起保養；選「設備」＝只保養這台。</div>
    @if ($targetError)
        <div class="invalid-feedback d-block">{{ $targetError }}</div>
    @endif

    {{-- 共用的選項清單：點哪一列的搜尋框，清單就搬到那一列下面。 --}}
    <div id="target_options" class="list-group position-absolute w-100 shadow-sm" role="listbox" style="display:none; z-index:1050; max-height:260px; overflow-y:auto; top:100%; left:0;">
        @foreach ($targets as $option)
            @if ($loop->first || $targets[$loop->index - 1]['group'] !== $option['group'])
                <div class="list-group-item small text-muted bg-light py-1" data-group-header="{{ $option['group'] }}">{{ $option['group'] }}</div>
            @endif
            <button type="button" class="list-group-item list-group-item-action text-start" role="option"
                data-value="{{ $option['value'] }}" data-group="{{ $option['group'] }}" data-search="{{ $option['search'] }}"
                data-label="{{ $displayLabel($option) }}">{{ $option['label'] }}</button>
        @endforeach
        <div id="target_no_results" class="list-group-item text-muted" style="display:none;">找不到符合的設備或類別</div>
    </div>
</div>
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
        <div class="form-text">下次到期日＝起始日 + 週期天數；到期日前 3 天會自動產生保養工單（排定在到期日），工單完成或取消後，下次到期日再往後推一個週期。</div>
        @error('start_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div>
<div class="mb-3">
    <label for="description" class="form-label">說明 <span class="text-danger">*</span></label>
    <textarea name="description" id="description" rows="4" maxlength="2000" required
        class="form-control @error('description') is-invalid @enderror" placeholder="要做哪些保養，例如：清潔濾網、檢查燈泡亮度">{{ old('description', $plan->description) }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-check">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1"
        {{ old('is_active', $plan->is_active) ? 'checked' : '' }}>
    <label for="is_active" class="form-check-label">啟用</label>
</div>
</div>
</div>
</div>

{{-- 取消／送出按鈕放在外框外面、靠右。 --}}
<div class="d-flex justify-content-end gap-2 mt-3">
    <a href="{{ route('maintenance-plans.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>取消</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>儲存</button>
</div>

<script>
// 設備／設備類別選擇器（一個）：輸入關鍵字篩選或點開清單直接選，只能選清單裡的項目（沒選就清空）。
document.addEventListener('DOMContentLoaded', function () {
    const row = document.querySelector('#planTargets .plan-target-row');
    const list = document.getElementById('target_options');
    if (!row || !list) { return; }
    const noResults = document.getElementById('target_no_results');
    const options = Array.from(list.querySelectorAll('button[data-value]'));
    const headers = Array.from(list.querySelectorAll('[data-group-header]'));
    const search = row.querySelector('.target-search');
    const hidden = row.querySelector('.target-value');
    search.parentElement.appendChild(list);   // 清單放在搜尋框下面

    function closeList() { list.style.display = 'none'; search.setAttribute('aria-expanded', 'false'); }
    function choose(option) {
        hidden.value = option.dataset.value;
        search.value = option.dataset.label;
        search.classList.remove('is-invalid');
        closeList();
    }
    function filter() {
        const keyword = hidden.value ? '' : search.value.trim().toLowerCase();
        const shownGroups = new Set();
        options.forEach(function (option) {
            const matched = !keyword || option.dataset.search.includes(keyword);
            option.style.display = matched ? '' : 'none';
            if (matched) { shownGroups.add(option.dataset.group); }
        });
        headers.forEach(function (header) { header.style.display = shownGroups.has(header.dataset.groupHeader) ? '' : 'none'; });
        noResults.style.display = shownGroups.size === 0 ? '' : 'none';
        list.style.display = '';
        search.setAttribute('aria-expanded', 'true');
    }
    search.addEventListener('focus', filter);
    search.addEventListener('input', function () { hidden.value = ''; filter(); });
    search.addEventListener('blur', function () { closeList(); if (!hidden.value) { search.value = ''; } });
    search.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { closeList(); }
        if (event.key === 'Enter') {
            event.preventDefault();
            const first = options.find(function (o) { return o.style.display !== 'none'; });
            if (first) { choose(first); }
        }
    });
    options.forEach(function (option) {
        option.addEventListener('mousedown', function (event) { event.preventDefault(); choose(option); });
    });
});
</script>

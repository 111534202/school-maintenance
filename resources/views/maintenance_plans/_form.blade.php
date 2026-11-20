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

{{-- 保養對象：可以有好幾個，每一列是一台設備或一個設備類別（至少一列）。每一列可輸入關鍵字搜尋，只能從清單裡選；
     按「新增設備／類別」多一列、按 ✕ 拿掉一列（跟零件使用明細一樣）。送出 targets[]＝device:編號 或 category:編號。
     建立工單時每個對象各建一張工單。 --}}
@php
    $optionByValue = $targets->keyBy('value');
    $displayLabel = fn (array $option) => ($option['group'] === '設備類別' ? '類別：' : '設備：') . $option['label'];
    $currentValues = old('targets', $plan->exists
        ? ($plan->targets->isNotEmpty() ? $plan->targets->map->value()->all() : array_filter([$plan->device_id ? 'device:' . $plan->device_id : ($plan->device_category_id ? 'category:' . $plan->device_category_id : null)]))
        : []);
    $currentValues = array_values(array_filter((array) $currentValues, fn ($value) => is_string($value) && $optionByValue->has($value)));
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
                <button type="button" class="btn btn-outline-danger icon-btn target-remove flex-shrink-0" title="拿掉這一列" aria-label="拿掉這一列"><i class="bi bi-x-lg"></i></button>
            </div>
        @endforeach
    </div>
    <button type="button" id="planTargetAdd" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>新增設備／類別</button>
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
        <div class="form-text">第一期排定日＝起始日 + 週期天數；啟用的計畫存檔就產生這一期的保養工單，之後每到一期的排定日，就產生下一期的工單。</div>
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
// 設備／設備類別選擇器（可以好幾列）：每一列輸入關鍵字篩選或點開清單直接選，只能選清單裡的項目（沒選就清空）；
// 已經被別列選走的項目不再列出（不能重複）。「新增設備／類別」多一列，✕ 拿掉一列（只剩一列時改成清空）。
document.addEventListener('DOMContentLoaded', function () {
    const rowsBox = document.getElementById('planTargets');
    const list = document.getElementById('target_options');
    const addButton = document.getElementById('planTargetAdd');
    if (!rowsBox || !list || !addButton) { return; }
    const noResults = document.getElementById('target_no_results');
    const options = Array.from(list.querySelectorAll('button[data-value]'));
    const headers = Array.from(list.querySelectorAll('[data-group-header]'));
    let active = null;   // 目前正在選的那一列 { search, hidden }
    let counter = rowsBox.querySelectorAll('.plan-target-row').length;

    function rowParts(row) { return { search: row.querySelector('.target-search'), hidden: row.querySelector('.target-value') }; }
    function takenValues(except) {
        return Array.from(rowsBox.querySelectorAll('.target-value')).filter(function (h) { return h !== except && h.value; }).map(function (h) { return h.value; });
    }
    function closeList() { list.style.display = 'none'; if (active) { active.search.setAttribute('aria-expanded', 'false'); } }
    function choose(option) {
        active.hidden.value = option.dataset.value;
        active.search.value = option.dataset.label;
        active.search.classList.remove('is-invalid');
        closeList();
    }
    function filter() {
        if (!active) { return; }
        const keyword = active.hidden.value ? '' : active.search.value.trim().toLowerCase();
        const taken = takenValues(active.hidden);
        const shownGroups = new Set();
        options.forEach(function (option) {
            const matched = taken.indexOf(option.dataset.value) === -1 && (!keyword || option.dataset.search.includes(keyword));
            option.style.display = matched ? '' : 'none';
            if (matched) { shownGroups.add(option.dataset.group); }
        });
        headers.forEach(function (header) { header.style.display = shownGroups.has(header.dataset.groupHeader) ? '' : 'none'; });
        noResults.style.display = shownGroups.size === 0 ? '' : 'none';
        list.style.display = '';
        active.search.setAttribute('aria-expanded', 'true');
    }
    function bind(row) {
        const parts = rowParts(row);
        parts.search.addEventListener('focus', function () {
            active = parts;
            parts.search.parentElement.appendChild(list);   // 清單搬到這一列下面
            filter();
        });
        parts.search.addEventListener('input', function () { parts.hidden.value = ''; filter(); });
        parts.search.addEventListener('blur', function () { closeList(); if (!parts.hidden.value) { parts.search.value = ''; } });
        parts.search.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { closeList(); }
            if (event.key === 'Enter') {
                event.preventDefault();
                const first = options.find(function (o) { return o.style.display !== 'none'; });
                if (first) { choose(first); }
            }
        });
        row.querySelector('.target-remove').addEventListener('click', function () {
            const rows = rowsBox.querySelectorAll('.plan-target-row');
            if (rows.length === 1) { parts.search.value = ''; parts.hidden.value = ''; return; }
            if (list.parentElement === parts.search.parentElement) { rowsBox.parentElement.appendChild(list); closeList(); }
            row.remove();
            const first = rowsBox.querySelector('.target-search');
            if (first) { first.required = true; first.id = 'target_search_0'; }
        });
    }
    options.forEach(function (option) {
        option.addEventListener('mousedown', function (event) { event.preventDefault(); if (active) { choose(option); } });
    });
    rowsBox.querySelectorAll('.plan-target-row').forEach(bind);
    addButton.addEventListener('click', function () {
        const template = rowsBox.querySelector('.plan-target-row');
        const row = template.cloneNode(true);
        row.querySelectorAll('.invalid-feedback, #target_options').forEach(function (el) { if (el.id !== 'target_options') { el.remove(); } });
        const box = row.querySelector('#target_options');
        if (box) { box.remove(); }
        const parts = rowParts(row);
        parts.search.value = '';
        parts.search.required = false;
        parts.search.classList.remove('is-invalid');
        parts.search.id = 'target_search_' + (counter++);
        parts.hidden.value = '';
        rowsBox.appendChild(row);
        bind(row);
        parts.search.focus();
    });
});
</script>

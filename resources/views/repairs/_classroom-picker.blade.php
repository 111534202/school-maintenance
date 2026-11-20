{{-- 發現地點選擇器（新增報修、修改報修單共用）：可以輸入教室名稱或代碼搜尋，也可以點開清單直接選；
     只能選清單裡的教室（和空間預約相同），沒從清單選就清空。外層容器要加 position-relative。
     參數：$classrooms（教室清單）、$selectedId（目前選的教室編號，可以是 null）、$label（欄位名稱）。
     送出的欄位是 classroom_id；掃描設備時 window.setRepairClassroom(id, label) 會自動帶入設備所在的教室。 --}}
@php
    $selectedClassroom = $classrooms->firstWhere('id', (int) $selectedId);
@endphp
<label for="classroom_search" class="form-label">{{ $label }}</label>
<input type="text" id="classroom_search" class="form-control @error('classroom_id') is-invalid @enderror"
    placeholder="選擇或輸入教室名稱、代碼" autocomplete="off" role="combobox" aria-autocomplete="list"
    aria-controls="classroom_options" aria-expanded="false"
    value="{{ $selectedClassroom ? $selectedClassroom->name . '（' . $selectedClassroom->classroom_code . '）' : '' }}">
<input type="hidden" id="classroom_id" name="classroom_id" value="{{ $selectedClassroom?->id }}">
<div id="classroom_options" class="list-group position-absolute shadow-sm" role="listbox"
    style="display:none; z-index:1050; max-height:240px; overflow-y:auto; left:calc(var(--bs-gutter-x, 0px) * .5); right:calc(var(--bs-gutter-x, 0px) * .5);">
    @foreach ($classrooms as $classroom)
        <button type="button" class="list-group-item list-group-item-action text-start" role="option"
            data-id="{{ $classroom->id }}"
            data-search="{{ mb_strtolower($classroom->name . ' ' . $classroom->classroom_code) }}"
            data-label="{{ $classroom->name }}（{{ $classroom->classroom_code }}）">{{ $classroom->name }}（{{ $classroom->classroom_code }}）</button>
    @endforeach
    <div id="classroom_no_results" class="list-group-item text-muted" style="display:none;">找不到符合的教室</div>
</div>
@error('classroom_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

<script>
// 發現地點選擇器：輸入關鍵字篩選教室，或點開清單直接選；只能選清單裡的教室。
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('classroom_search');
    const hidden = document.getElementById('classroom_id');
    const list = document.getElementById('classroom_options');
    if (!search || !hidden || !list) { return; }
    const noResults = document.getElementById('classroom_no_results');
    const options = Array.from(list.querySelectorAll('button[data-id]'));

    function closeList() { list.style.display = 'none'; search.setAttribute('aria-expanded', 'false'); }

    function filter() {
        const keyword = hidden.value ? '' : search.value.trim().toLowerCase();
        let shown = 0;
        options.forEach(function (option) {
            const matched = !keyword || option.dataset.search.includes(keyword) || option.dataset.label.toLowerCase().includes(keyword);
            option.style.display = matched ? '' : 'none';
            if (matched) { shown++; }
        });
        noResults.style.display = shown === 0 ? '' : 'none';
        list.style.display = '';
        search.setAttribute('aria-expanded', 'true');
    }

    // 給條碼查詢用：查到設備後自動帶入它所在的教室。
    window.setRepairClassroom = function (id, label) {
        hidden.value = id;
        search.value = label;
        search.classList.remove('is-invalid');
    };

    search.addEventListener('focus', filter);
    search.addEventListener('input', function () { hidden.value = ''; filter(); });
    search.addEventListener('blur', function () {
        closeList();
        if (!hidden.value) { search.value = ''; }   // 沒有從清單選就清掉，避免看起來像選好了
    });
    search.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { closeList(); }
        if (event.key === 'Enter') {
            event.preventDefault();
            const first = options.find(function (o) { return o.style.display !== 'none'; });
            if (first) { window.setRepairClassroom(first.dataset.id, first.dataset.label); closeList(); }
        }
    });
    options.forEach(function (option) {
        option.addEventListener('mousedown', function (event) {
            event.preventDefault();
            window.setRepairClassroom(option.dataset.id, option.dataset.label);
            closeList();
        });
    });
});
</script>

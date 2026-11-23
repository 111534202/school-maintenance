{{-- 發現地點選擇器（新增報修、修改報修單共用）：可以輸入教室名稱或代碼搜尋，也可以點開清單直接選；
     只能選清單裡的教室（和空間預約相同），沒從清單選就清空。外層容器要加 position-relative。
     參數：$classrooms（教室清單）、$selectedId（目前選的教室編號，可以是 null）、$label（欄位名稱）。
     送出的欄位是 classroom_id；掃描設備時 window.setRepairClassroom(id, label) 會自動帶入設備所在的教室。
     選了設備、發現地點又改成別間教室時，下方出現「確認發現地點與設備登記的教室不同」勾選框，沒勾不能送出（後端也會檢查）。
     選填參數：$deviceClassroom（已綁定／已選設備所在的教室）、$originalClassroomId（修改報修單：存檔前的發現地點；原本就不同、這次沒改的不用再勾）。 --}}
@php
    $selectedClassroom = $classrooms->firstWhere('id', (int) $selectedId);
@endphp
<label for="classroom_search" class="form-label">{{ $label }}</label>
<input type="text" id="classroom_search" class="form-control @error('classroom_id') is-invalid @enderror"
    placeholder="選擇或輸入教室名稱、代碼" autocomplete="off" role="combobox" aria-autocomplete="list"
    aria-controls="classroom_options" aria-expanded="false"
    value="{{ $selectedClassroom ? $selectedClassroom->name . '（' . $selectedClassroom->classroom_code . '）' : '' }}">
<input type="hidden" id="classroom_id" name="classroom_id" value="{{ $selectedClassroom?->id }}"
    data-original="{{ $originalClassroomId ?? '' }}"
    data-device-classroom-id="{{ ($deviceClassroom ?? null)?->id }}"
    data-device-classroom-label="{{ ($deviceClassroom ?? null) ? $deviceClassroom->name . '（' . $deviceClassroom->classroom_code . '）' : '' }}">
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
{{-- 發現地點和設備登記的教室不同時才出現；出現時一定要勾才能送出。 --}}
<div id="classroom_differs_box" class="form-check mt-2 text-warning-emphasis" style="display:none;">
    <input class="form-check-input" type="checkbox" value="1" id="location_differs_confirmed" name="location_differs_confirmed" @checked(old('location_differs_confirmed'))>
    <label class="form-check-label small" for="location_differs_confirmed">
        確認發現地點與設備登記的教室不同（設備登記在：<span id="classroom_differs_device"></span>）
    </label>
</div>

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

    // 「確認發現地點與設備登記的教室不同」：有設備、發現地點不是設備所在的教室時出現，出現時必勾。
    const differsBox = document.getElementById('classroom_differs_box');
    const differsCheck = document.getElementById('location_differs_confirmed');
    const differsDevice = document.getElementById('classroom_differs_device');
    let deviceRoom = hidden.dataset.deviceClassroomId ? { id: hidden.dataset.deviceClassroomId, label: hidden.dataset.deviceClassroomLabel } : null;
    let deviceChanged = false;   // 這次才掃描／選了設備（修改報修單補設備）
    function refreshDiffers() {
        if (!differsBox) { return; }
        const differs = !!(deviceRoom && hidden.value && String(hidden.value) !== String(deviceRoom.id));
        // 修改報修單：原本就不同、這次沒改發現地點也沒換設備的，不用再勾。
        const untouched = hidden.dataset.original && String(hidden.value) === String(hidden.dataset.original) && !deviceChanged;
        const need = differs && !untouched;
        differsBox.style.display = need ? '' : 'none';
        differsCheck.required = need;
        if (!need) { differsCheck.checked = false; }
        if (differsDevice && deviceRoom) { differsDevice.textContent = deviceRoom.label; }
    }
    // 給條碼查詢用：查到設備時記下它登記的教室（查不到就傳 null）。
    window.setRepairDeviceClassroom = function (room) {
        deviceRoom = room && room.id ? room : null;
        deviceChanged = true;
        refreshDiffers();
    };

    // 給條碼查詢用：查到設備後自動帶入它所在的教室。
    window.setRepairClassroom = function (id, label) {
        hidden.value = id;
        search.value = label;
        search.classList.remove('is-invalid');
        refreshDiffers();
    };
    refreshDiffers();

    search.addEventListener('focus', filter);
    search.addEventListener('input', function () { hidden.value = ''; refreshDiffers(); filter(); });
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

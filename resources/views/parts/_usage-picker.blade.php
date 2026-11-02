{{--
    零件使用明細選擇器（維修紀錄、保養結果共用）。
    - 預設沒有任何一列；按「新增使用紀錄」才會多一列「零件」＋「使用數量」。
    - 零件可以輸入關鍵字搜尋（編號、名稱、規格、品牌、型號），也可以直接從清單點選，和預約教室的選法相同。
    - 選了零件，使用數量就必填；同一種零件不能在兩列重複選。
    - 沒有庫存的零件也會列出來（顯示庫存 0），但不能選。
    送出的欄位：part_usages[索引][part_id]、part_usages[索引][quantity]（後端驗證規則不變）。
    參數：$parts（零件集合）。
--}}
@php
    $partOptions = $parts->map(fn ($part) => [
        'id' => $part->id,
        'label' => $part->part_code . ' ' . $part->name . ($part->specification ? '（' . $part->specification . '）' : ''),
        'search' => mb_strtolower(implode(' ', array_filter([$part->part_code, $part->name, $part->specification, $part->brand, $part->model]))),
        'stock' => (int) $part->current_stock,
        'unit' => (string) $part->unit,
    ])->values();

    // 送出失敗時，把剛才填的列與錯誤訊息帶回畫面（索引要和錯誤訊息的 key 對得上）。
    $oldRows = collect(old('part_usages', []))
        ->filter(fn ($row) => is_array($row))
        ->map(fn ($row) => ['part_id' => $row['part_id'] ?? '', 'quantity' => $row['quantity'] ?? ''])
        ->all();
    $rowErrors = collect($errors->getMessages())
        ->filter(fn ($messages, $key) => str_starts_with($key, 'part_usages.'))
        ->map(fn ($messages) => $messages[0])
        ->all();
@endphp

<div id="partUsagePicker">
    <div id="partUsageRows"></div>

    <div id="partUsageEmpty" class="text-muted small mb-2">尚未新增使用紀錄；沒有使用零件時不用新增。</div>

    <button type="button" id="partUsageAdd" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-plus-lg me-1"></i>新增使用紀錄
    </button>

    @error('part_usages')
        <div class="alert alert-danger mt-2 mb-0">{{ $message }}</div>
    @enderror
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const parts = @json($partOptions);
    const oldRows = @json((object) $oldRows);
    const rowErrors = @json((object) $rowErrors);

    const rowsBox = document.getElementById('partUsageRows');
    const emptyHint = document.getElementById('partUsageEmpty');
    const addButton = document.getElementById('partUsageAdd');
    const form = rowsBox.closest('form');
    let nextIndex = 0;

    function partById(id) {
        return parts.find(function (p) { return String(p.id) === String(id); }) || null;
    }

    // 其他列已經選走的零件編號（同一種零件不能重複選）。
    function selectedIds(exceptRow) {
        return Array.from(rowsBox.querySelectorAll('.part-usage-row'))
            .filter(function (row) { return row !== exceptRow; })
            .map(function (row) { return row.querySelector('.part-id').value; })
            .filter(Boolean);
    }

    function refreshEmptyHint() {
        emptyHint.hidden = rowsBox.querySelector('.part-usage-row') !== null;
    }

    function addRow(values, index) {
        const i = index !== undefined ? Number(index) : nextIndex;
        nextIndex = Math.max(nextIndex, i + 1);

        const row = document.createElement('div');
        row.className = 'part-usage-row border rounded p-2 mb-2 bg-light';
        row.innerHTML =
            '<div class="row g-2">' +
                '<div class="col-md-7 position-relative">' +
                    '<label class="form-label small mb-1" for="part_search_' + i + '">零件</label>' +
                    '<input type="text" id="part_search_' + i + '" class="form-control part-search" placeholder="選擇或輸入零件編號、名稱" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="part_options_' + i + '">' +
                    '<input type="hidden" class="part-id" name="part_usages[' + i + '][part_id]">' +
                    '<div id="part_options_' + i + '" class="list-group part-options position-absolute w-100 shadow-sm" role="listbox" style="display:none; z-index:1050; max-height:240px; overflow-y:auto;"></div>' +
                    '<div class="invalid-feedback part-error"></div>' +
                '</div>' +
                '<div class="col-8 col-md-3">' +
                    '<label class="form-label small mb-1" for="part_qty_' + i + '">使用數量</label>' +
                    '<input type="number" id="part_qty_' + i + '" class="form-control part-qty" name="part_usages[' + i + '][quantity]" min="1" step="1" placeholder="例如：2">' +
                    '<div class="invalid-feedback qty-error"></div>' +
                '</div>' +
                '<div class="col-4 col-md-2 d-flex align-items-end">' +
                    '<button type="button" class="btn btn-outline-danger w-100 part-remove" aria-label="移除這一列">移除</button>' +
                '</div>' +
            '</div>';
        rowsBox.appendChild(row);

        const search = row.querySelector('.part-search');
        const hiddenId = row.querySelector('.part-id');
        const qty = row.querySelector('.part-qty');
        const list = row.querySelector('.part-options');

        function choose(part) {
            hiddenId.value = part.id;
            search.value = part.label;
            search.setCustomValidity('');
            search.classList.remove('is-invalid');
            qty.required = true;          // 選了零件，使用數量就必填
            qty.max = part.stock;
            closeList();
            if (!qty.value) { qty.focus(); }
        }

        function clearChoice() {
            hiddenId.value = '';
            qty.required = false;
            qty.removeAttribute('max');
        }

        function closeList() {
            list.style.display = 'none';
            search.setAttribute('aria-expanded', 'false');
        }

        function renderList() {
            const keyword = search.value.trim().toLowerCase();
            const taken = selectedIds(row);
            list.innerHTML = '';
            let shown = 0;

            parts.forEach(function (part) {
                if (keyword && !(part.search + ' ' + part.label.toLowerCase()).includes(keyword)) { return; }
                shown++;

                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action text-start d-flex justify-content-between gap-2';
                item.setAttribute('role', 'option');

                const name = document.createElement('span');
                name.textContent = part.label;
                const note = document.createElement('span');
                note.className = 'small text-nowrap';

                if (taken.includes(String(part.id))) {
                    item.disabled = true;
                    note.textContent = '已在其他列選擇';
                } else if (part.stock <= 0) {
                    item.disabled = true;
                    note.textContent = '庫存：0 ' + part.unit;
                } else {
                    note.className += ' text-muted';
                    note.textContent = '庫存：' + part.stock + ' ' + part.unit;
                }

                item.append(name, note);
                // 用 mousedown，避免輸入框先失去焦點把清單關掉、點不到選項。
                item.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    if (!item.disabled) { choose(part); }
                });
                list.appendChild(item);
            });

            if (shown === 0) {
                const none = document.createElement('div');
                none.className = 'list-group-item text-muted';
                none.textContent = '找不到符合的零件';
                list.appendChild(none);
            }

            list.style.display = '';
            search.setAttribute('aria-expanded', 'true');
        }

        search.addEventListener('focus', renderList);
        search.addEventListener('input', function () {
            clearChoice();
            search.setCustomValidity('');
            renderList();
        });
        search.addEventListener('blur', function () {
            closeList();
            // 只打了字、沒有從清單選，就清掉文字，避免看起來像選好了。
            if (!hiddenId.value) { search.value = ''; }
        });
        search.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { closeList(); }
            if (event.key === 'Enter') {
                event.preventDefault();   // Enter 選第一個可選的零件，不要直接送出表單
                const first = list.querySelector('button.list-group-item:not([disabled])');
                if (first) { first.dispatchEvent(new MouseEvent('mousedown')); }
            }
        });
        qty.addEventListener('input', function () { qty.setCustomValidity(''); qty.classList.remove('is-invalid'); });

        row.querySelector('.part-remove').addEventListener('click', function () {
            row.remove();
            refreshEmptyHint();
        });

        // 帶回送出失敗前填的值與錯誤訊息。
        if (values && values.part_id) {
            const part = partById(values.part_id);
            if (part) {
                hiddenId.value = part.id;
                search.value = part.label;
                qty.required = true;
                qty.max = part.stock;
            }
        }
        if (values && values.quantity) { qty.value = values.quantity; }
        const partError = rowErrors['part_usages.' + i + '.part_id'];
        const qtyError = rowErrors['part_usages.' + i + '.quantity'];
        if (partError) { search.classList.add('is-invalid'); row.querySelector('.part-error').textContent = partError; }
        if (qtyError) { qty.classList.add('is-invalid'); row.querySelector('.qty-error').textContent = qtyError; }

        refreshEmptyHint();
        return row;
    }

    addButton.addEventListener('click', function () {
        addRow(null).querySelector('.part-search').focus();
    });

    Object.keys(oldRows).forEach(function (index) { addRow(oldRows[index], index); });
    refreshEmptyHint();

    // 送出前檢查：有填數量卻沒選零件、或兩列選到同一個零件，就擋下來。
    if (form) {
        form.addEventListener('submit', function (event) {
            const seen = [];
            let firstBad = null;

            rowsBox.querySelectorAll('.part-usage-row').forEach(function (row) {
                const search = row.querySelector('.part-search');
                const id = row.querySelector('.part-id').value;
                const qty = row.querySelector('.part-qty');

                search.setCustomValidity('');
                if (!id && qty.value) {
                    search.setCustomValidity('請從清單選擇零件。');
                } else if (id && seen.includes(id)) {
                    search.setCustomValidity('同一種零件不能重複選擇。');
                }
                if (id) { seen.push(id); }
                if (!firstBad && !search.checkValidity()) { firstBad = search; }
            });

            if (firstBad) {
                event.preventDefault();
                firstBad.reportValidity();
            }
        });
    }
});
</script>

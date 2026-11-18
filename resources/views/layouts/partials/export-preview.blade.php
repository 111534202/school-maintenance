{{-- 列表共用的「匯出 Excel」預覽視窗（設備、用戶、報修單、空間申請）：先預覽，勾選要匯出的欄位（上方）與資料（每一列左邊），
     再按「匯出 Excel」。資料範圍＝目前搜尋與篩選條件下的資料；網址上的篩選參數用隱藏欄位一起送出。
     預覽最多列 $limit 筆：符合的資料比這更多（$total）時，全選＝匯出符合條件的全部（送出時不帶 ids[]），
     取消勾選某幾筆才只匯出預覽裡勾選的。
     用法：@include('layouts.partials.export-preview', [
         'modalId' => 'deviceExportModal', 'title' => '匯出設備清單', 'action' => route('devices.export'),
         'columnsClass' => \App\Support\DeviceExportColumns::class, 'items' => $exportDevices, 'total' => $exportDevicesTotal, 'unit' => '台設備',
     ])
     欄位定義（columnsClass）要有 COLUMNS、NUMERIC、values()；有 label() 的話當作每列勾選框的說明。 --}}
@php
    $limit = $limit ?? 500;
    $total = max($total ?? $items->count(), $items->count());   // 符合條件的總筆數（預覽可能只列其中一部分）
    $exportColumns = $columnsClass::COLUMNS;
    $numericColumns = $columnsClass::NUMERIC;
    // 網址上的搜尋與篩選條件（不含分頁、對話框參數），匯出時一起送出，範圍才會跟畫面一樣。
    $keepQuery = collect(request()->query())->except(['page', 'modal', 'table', 'columns', 'ids', 'format'])
        ->filter(fn ($value) => is_scalar($value) && filled($value));
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="GET" action="{{ $action }}" class="modal-content export-preview-form" data-export-unit="{{ $unit }}" data-export-total="{{ $total }}">
            <div class="modal-header">
                <h2 class="modal-title h5" id="{{ $modalId }}Title">{{ $title }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
            </div>
            <div class="modal-body">
                @foreach ($keepQuery as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                @if (request()->filled('keyword'))
                    <p class="small text-muted">目前搜尋「{{ request('keyword') }}」，只列出符合的資料。</p>
                @endif

                <fieldset class="mb-3">
                    <legend class="form-label fw-semibold fs-6 mb-2">匯出欄位</legend>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach ($exportColumns as $key => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="columns[]" value="{{ $key }}" id="{{ $modalId }}_col_{{ $key }}" data-export-col="{{ $key }}" checked>
                                <label class="form-check-label" for="{{ $modalId }}_col_{{ $key }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <span class="fw-semibold">預覽</span>
                    <span class="small text-muted" data-export-summary aria-live="polite"></span>
                </div>
                @if ($items->isEmpty())
                    <p class="text-muted text-center my-4">沒有符合條件的資料可以匯出。</p>
                @else
                    <div class="table-responsive">
                        {{-- data-no-onion：這張是預覽用的表格，不要變成清單。 --}}
                        <table class="tb-table tb-table-sm align-middle mb-0" data-no-onion>
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 2.5rem;">
                                        <input class="form-check-input" type="checkbox" data-export-all checked aria-label="全選／全不選">
                                    </th>
                                    @foreach ($exportColumns as $key => $label)
                                        <th class="text-nowrap {{ in_array($key, $numericColumns, true) ? 'text-end' : '' }}" data-export-cell="{{ $key }}">{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    @php $values = $columnsClass::values($item); @endphp
                                    <tr>
                                        <td class="text-center">
                                            <input class="form-check-input" type="checkbox" name="ids[]" value="{{ $item->getKey() }}" data-export-row checked
                                                aria-label="匯出 {{ method_exists($columnsClass, 'label') ? $columnsClass::label($item) : '#' . $item->getKey() }}">
                                        </td>
                                        @foreach ($exportColumns as $key => $label)
                                            <td class="{{ in_array($key, $numericColumns, true) ? 'text-end text-nowrap' : '' }}" data-export-cell="{{ $key }}">{{ $values[$key] ?? '—' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($total > $items->count())
                        <p class="small text-muted mt-2 mb-0">共 {{ $total }} 筆符合，預覽只列前 {{ $items->count() }} 筆；全選時會匯出符合條件的全部 {{ $total }} 筆，取消勾選某幾筆則只匯出預覽裡勾選的。</p>
                    @endif
                @endif
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success" data-export-submit @disabled($items->isEmpty())>
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> 匯出 Excel
                </button>
            </div>
        </form>
    </div>
</div>

@once
<script>
    // 匯出預覽（每個 .export-preview-form 各自運作）：取消勾選的欄位在預覽裡隱藏；沒有勾選任何欄位或資料時不能匯出。
    document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('.export-preview-form').forEach(function (form) {
        var all = form.querySelector('[data-export-all]');
        var summary = form.querySelector('[data-export-summary]');
        var unit = form.dataset.exportUnit || '筆';
        var total = parseInt(form.dataset.exportTotal, 10) || 0;
        var rows = Array.prototype.slice.call(form.querySelectorAll('[data-export-row]'));
        var cols = Array.prototype.slice.call(form.querySelectorAll('[data-export-col]'));

        function refresh() {
            cols.forEach(function (c) {
                form.querySelectorAll('[data-export-cell="' + c.dataset.exportCol + '"]').forEach(function (cell) { cell.hidden = !c.checked; });
            });
            var rowCount = rows.filter(function (r) { return r.checked; }).length;
            var colCount = cols.filter(function (c) { return c.checked; }).length;
            if (all) { all.checked = rows.length > 0 && rowCount === rows.length; all.indeterminate = rowCount > 0 && rowCount < rows.length; }
            // 資料比預覽多、而且全選：匯出符合條件的全部，摘要也顯示全部的筆數。
            var everything = total > rows.length && rowCount === rows.length;
            summary.textContent = everything
                ? '已選全部 ' + total + ' ' + unit + '、' + colCount + ' 個欄位'
                : '已選 ' + rowCount + ' / ' + (total > rows.length ? total : rows.length) + ' ' + unit + '、' + colCount + ' 個欄位';
            form.querySelectorAll('[data-export-submit]').forEach(function (b) { b.disabled = rowCount === 0 || colCount === 0; });
        }

        if (all) { all.addEventListener('change', function () { rows.forEach(function (r) { r.checked = all.checked; }); refresh(); }); }
        form.addEventListener('change', refresh);
        // 送出：資料比預覽多且全選時不帶 ids[]（伺服器就匯出符合條件的全部）；送出後恢復，下次開視窗照常。
        form.addEventListener('submit', function () {
            var everything = total > rows.length && rows.every(function (r) { return r.checked; });
            rows.forEach(function (r) { r.disabled = everything; });
            setTimeout(function () { rows.forEach(function (r) { r.disabled = false; }); }, 0);
        });
        refresh();
    }); });
</script>
@endonce

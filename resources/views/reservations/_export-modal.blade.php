{{-- 空間申請匯出視窗：先預覽，勾選要匯出的欄位（上方）與申請（每一列左邊），再按「匯出 Excel」。
     申請範圍＝目前搜尋與狀態篩選下、你看得到的申請（有審核權限的人是全部，其他人只有自己提出的；最多列 500 筆）。
     用法：@include('reservations._export-modal', ['exportReservations' => $exportReservations]) --}}
@php
    $exportColumns = \App\Support\ReservationExportColumns::COLUMNS;
    $exportKeys = request()->only(['keyword', 'status']);
@endphp
<div class="modal fade" id="reservationExportModal" tabindex="-1" aria-labelledby="reservationExportModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="GET" action="{{ route('reservations.export') }}" class="modal-content export-preview-form">
            <div class="modal-header">
                <h2 class="modal-title h5" id="reservationExportModalTitle">匯出空間申請</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
            </div>
            <div class="modal-body">
                @foreach ($exportKeys as $name => $value)
                    @if (filled($value))
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endif
                @endforeach
                @if (request()->filled('keyword'))
                    <p class="small text-muted">目前搜尋「{{ request('keyword') }}」，只列出符合的申請。</p>
                @endif

                <fieldset class="mb-3">
                    <legend class="form-label fw-semibold fs-6 mb-2">匯出欄位</legend>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach ($exportColumns as $key => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="columns[]" value="{{ $key }}" id="reservationExport_col_{{ $key }}" data-export-col="{{ $key }}" checked>
                                <label class="form-check-label" for="reservationExport_col_{{ $key }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <span class="fw-semibold">預覽</span>
                    <span class="small text-muted" data-export-summary aria-live="polite"></span>
                </div>
                @if ($exportReservations->isEmpty())
                    <p class="text-muted text-center my-4">沒有符合條件的申請可以匯出。</p>
                @else
                    <div class="table-responsive">
                        {{-- data-no-onion：這張是預覽用的表格，不要變成清單。 --}}
                        <table class="tb-table tb-table-sm align-middle mb-0" data-no-onion>
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 2.5rem;">
                                        <input class="form-check-input" type="checkbox" data-export-all checked aria-label="全選／全不選申請">
                                    </th>
                                    @foreach ($exportColumns as $key => $label)
                                        <th class="text-nowrap {{ in_array($key, \App\Support\ReservationExportColumns::NUMERIC, true) ? 'text-end' : '' }}" data-export-cell="{{ $key }}">{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($exportReservations as $reservation)
                                    @php $values = \App\Support\ReservationExportColumns::values($reservation); @endphp
                                    <tr>
                                        <td class="text-center">
                                            <input class="form-check-input" type="checkbox" name="ids[]" value="{{ $reservation->id }}" data-export-row checked aria-label="匯出申請 {{ $reservation->id }}">
                                        </td>
                                        @foreach ($exportColumns as $key => $label)
                                            <td class="{{ in_array($key, \App\Support\ReservationExportColumns::NUMERIC, true) ? 'text-end text-nowrap' : '' }}" data-export-cell="{{ $key }}">{{ $values[$key] ?? '—' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($exportReservations->count() >= 500)
                        <p class="small text-muted mt-2 mb-0">最多列出 500 筆；要匯出更多，請先用搜尋或「未審核／已審核」縮小範圍。</p>
                    @endif
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-success" data-export-submit @disabled($exportReservations->isEmpty())>
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> 匯出 Excel
                </button>
            </div>
        </form>
    </div>
</div>

@once
<script>
    // 匯出預覽（每個 .export-preview-form 各自運作）：取消勾選的欄位在預覽裡隱藏；沒有勾選任何欄位或申請時不能匯出。
    document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('.export-preview-form').forEach(function (form) {
        var all = form.querySelector('[data-export-all]');
        var summary = form.querySelector('[data-export-summary]');
        var rows = Array.prototype.slice.call(form.querySelectorAll('[data-export-row]'));
        var cols = Array.prototype.slice.call(form.querySelectorAll('[data-export-col]'));

        function refresh() {
            cols.forEach(function (c) {
                form.querySelectorAll('[data-export-cell="' + c.dataset.exportCol + '"]').forEach(function (cell) { cell.hidden = !c.checked; });
            });
            var rowCount = rows.filter(function (r) { return r.checked; }).length;
            var colCount = cols.filter(function (c) { return c.checked; }).length;
            if (all) { all.checked = rows.length > 0 && rowCount === rows.length; all.indeterminate = rowCount > 0 && rowCount < rows.length; }
            summary.textContent = '已選 ' + rowCount + ' / ' + rows.length + ' 筆申請、' + colCount + ' 個欄位';
            form.querySelectorAll('[data-export-submit]').forEach(function (b) { b.disabled = rowCount === 0 || colCount === 0; });
        }

        if (all) { all.addEventListener('change', function () { rows.forEach(function (r) { r.checked = all.checked; }); refresh(); }); }
        form.addEventListener('change', refresh);
        refresh();
    }); });
</script>
@endonce

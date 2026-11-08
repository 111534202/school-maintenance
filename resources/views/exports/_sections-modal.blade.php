{{-- 「先預覽、勾選區塊與欄位再匯出」的共用視窗（成本分析報表、零件履歷）。
     參數：$modalId、$modalTitle、$excelUrl、$pdfUrl、$hidden（要一起送出的篩選條件）、$note（說明文字）、
     $sections（見 App\Support\ExportSections）。每個區塊預覽前 10 筆；匯出時包含全部筆數。 --}}
@php($previewLimit = 10)
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="GET" action="{{ $excelUrl }}" class="modal-content sections-export-form" id="{{ $modalId }}Form">
            <div class="modal-header">
                <h2 class="modal-title h5" id="{{ $modalId }}Title">{{ $modalTitle }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
            </div>
            <div class="modal-body">
                @foreach ($hidden ?? [] as $name => $value)
                    @if (filled($value))
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endif
                @endforeach
                <p class="small text-muted">{{ $note }}</p>

                @foreach ($exportSections as $key => $section)
                    <fieldset class="border rounded-3 p-3 mb-3" data-export-section="{{ $key }}">
                        <legend class="float-none w-auto px-2 mb-0 fs-6">
                            <span class="form-check d-inline-flex align-items-center gap-2 mb-0">
                                <input class="form-check-input mt-0" type="checkbox" name="sections[]" value="{{ $key }}" id="{{ $modalId }}_section_{{ $key }}" data-export-section-toggle checked>
                                <label class="form-check-label fw-semibold" for="{{ $modalId }}_section_{{ $key }}">{{ $section['title'] }}</label>
                                <span class="small text-muted">（共 {{ count($section['rows']) }} 筆）</span>
                            </span>
                        </legend>

                        <div class="d-flex flex-wrap gap-3 mb-2 mt-1">
                            @foreach ($section['headings'] as $i => $heading)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cols[{{ $key }}][]" value="{{ $i }}" id="{{ $modalId }}_{{ $key }}_{{ $i }}" data-export-col="{{ $i }}" checked>
                                    <label class="form-check-label" for="{{ $modalId }}_{{ $key }}_{{ $i }}">{{ $heading }}</label>
                                </div>
                            @endforeach
                        </div>

                        <div class="table-responsive" data-export-preview>
                            {{-- data-no-onion：預覽用的表格，不要變成清單。 --}}
                            <table class="table table-bordered table-sm align-middle mb-0 small" data-no-onion>
                                <thead class="table-light">
                                    <tr>
                                        @foreach ($section['headings'] as $i => $heading)
                                            <th class="text-nowrap {{ in_array($i, $section['numeric'], true) ? 'text-end' : '' }}" data-export-cell="{{ $i }}">{{ $heading }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse (array_slice($section['rows'], 0, $previewLimit) as $row)
                                        <tr>
                                            @foreach ($row as $i => $value)
                                                <td class="{{ in_array($i, $section['numeric'], true) ? 'text-end text-nowrap' : '' }}" data-export-cell="{{ $i }}">{{ \App\Support\ExportSections::display($value) }}</td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr><td colspan="{{ count($section['headings']) }}" class="text-center text-muted">此篩選範圍內沒有資料。</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if (count($section['rows']) > $previewLimit)
                            <p class="small text-muted mb-0 mt-1">預覽前 {{ $previewLimit }} 筆，匯出時包含全部 {{ count($section['rows']) }} 筆。</p>
                        @endif
                    </fieldset>
                @endforeach
            </div>
            <div class="modal-footer">
                <span class="small text-muted me-auto" data-export-summary aria-live="polite"></span>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-success" formaction="{{ $excelUrl }}" data-export-submit>
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> 匯出 Excel
                </button>
                <button type="submit" class="btn btn-danger" formaction="{{ $pdfUrl }}" data-export-submit>
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> 匯出 PDF
                </button>
            </div>
        </form>
    </div>
</div>

@once
<script>
    // 取消勾選的區塊：欄位不送出、預覽變淡；取消勾選的欄位在預覽裡隱藏。沒有任何區塊（或勾選的區塊沒有欄位）時不能匯出。
    document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('.sections-export-form').forEach(function (form) {
        var summary = form.querySelector('[data-export-summary]');
        var sections = Array.prototype.slice.call(form.querySelectorAll('[data-export-section]'));

        function refresh() {
            var picked = 0, ok = true;
            sections.forEach(function (box) {
                var on = box.querySelector('[data-export-section-toggle]').checked;
                var cols = Array.prototype.slice.call(box.querySelectorAll('[data-export-col]'));
                cols.forEach(function (c) {
                    c.disabled = !on;
                    box.querySelectorAll('[data-export-cell="' + c.value + '"]').forEach(function (cell) { cell.hidden = !c.checked; });
                });
                box.querySelector('[data-export-preview]').style.opacity = on ? '' : '0.4';
                if (on) {
                    picked++;
                    if (!cols.some(function (c) { return c.checked; })) ok = false;
                }
            });
            summary.textContent = '已選 ' + picked + ' / ' + sections.length + ' 個區塊' + (ok ? '' : '（有區塊沒有勾選任何欄位）');
            form.querySelectorAll('[data-export-submit]').forEach(function (b) { b.disabled = picked === 0 || !ok; });
        }

        form.addEventListener('change', refresh);
        refresh();
    }); });
</script>
@endonce

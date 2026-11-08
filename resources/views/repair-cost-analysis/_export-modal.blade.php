{{-- 成本分析報表匯出視窗：先預覽，勾選要匯出的區塊（成本總覽、備品使用彙總、材料費明細、報廢明細）與每個區塊的欄位，
     再按「匯出 Excel」或「匯出 PDF」。範圍＝目前的篩選條件（開始／結束日期、案件狀態、報廢損失）。
     每個區塊預覽前 10 筆；匯出時包含全部筆數。 --}}
@php($previewLimit = 10)
<div class="modal fade" id="costExportModal" tabindex="-1" aria-labelledby="costExportTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="GET" action="{{ route('repair-cost-analysis.export.excel') }}" class="modal-content" id="costExportForm">
            <div class="modal-header">
                <h2 class="modal-title h5" id="costExportTitle">匯出成本分析報表</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
            </div>
            <div class="modal-body">
                @foreach (request()->only(['start_date', 'end_date', 'status', 'scraps']) as $name => $value)
                    @if (filled($value))
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endif
                @endforeach
                <p class="small text-muted">
                    範圍跟著目前的篩選：{{ $startDate ?: '不限' }} 至 {{ $endDate ?: '不限' }}；勾選要匯出的區塊與欄位，取消勾選的欄位在預覽裡會隱藏。
                </p>

                @foreach ($exportSections as $key => $section)
                    <fieldset class="border rounded-3 p-3 mb-3" data-export-section="{{ $key }}">
                        <legend class="float-none w-auto px-2 mb-0 fs-6">
                            <span class="form-check d-inline-flex align-items-center gap-2 mb-0">
                                <input class="form-check-input mt-0" type="checkbox" name="sections[]" value="{{ $key }}" id="export_section_{{ $key }}" data-export-section-toggle checked>
                                <label class="form-check-label fw-semibold" for="export_section_{{ $key }}">{{ $section['title'] }}</label>
                                <span class="small text-muted">（共 {{ count($section['rows']) }} 筆）</span>
                            </span>
                        </legend>

                        <div class="d-flex flex-wrap gap-3 mb-2 mt-1">
                            @foreach ($section['headings'] as $i => $heading)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cols[{{ $key }}][]" value="{{ $i }}" id="export_{{ $key }}_{{ $i }}" data-export-col="{{ $i }}" checked>
                                    <label class="form-check-label" for="export_{{ $key }}_{{ $i }}">{{ $heading }}</label>
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
                                                <td class="{{ in_array($i, $section['numeric'], true) ? 'text-end text-nowrap' : '' }}" data-export-cell="{{ $i }}">{{ \App\Support\CostReportSections::display($value) }}</td>
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
                <span class="small text-muted me-auto" id="costExportSummary" aria-live="polite"></span>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-success" formaction="{{ route('repair-cost-analysis.export.excel') }}" data-export-submit>
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> 匯出 Excel
                </button>
                <button type="submit" class="btn btn-danger" formaction="{{ route('repair-cost-analysis.export.pdf') }}" data-export-submit>
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> 匯出 PDF
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // 取消勾選的區塊：欄位不送出、預覽變淡；取消勾選的欄位在預覽裡隱藏。沒有任何區塊（或勾選的區塊沒有欄位）時不能匯出。
    (function () {
        var form = document.getElementById('costExportForm');
        if (!form) return;
        var summary = document.getElementById('costExportSummary');
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
    })();
</script>

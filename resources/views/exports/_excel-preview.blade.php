{{-- 匯出 Excel 預覽視窗：按下帶有 data-excel-preview 的連結時，先顯示將匯出的表格，確認後才下載。
     用法：連結加上 data-excel-preview，頁面最後 @include('exports._excel-preview')。
     預覽資料來自同一個匯出網址加上 preview=1（見 App\Support\TableExportResponse），預覽不會寫操作紀錄。 --}}
<div class="modal fade" id="excelPreviewModal" tabindex="-1" aria-labelledby="excelPreviewModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="excelPreviewModalTitle">匯出 Excel 預覽</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" data-excel-summary aria-live="polite">載入中…</p>
                <div class="table-responsive">
                    {{-- data-no-onion：這張是預覽用的表格，不要變成清單。 --}}
                    <table class="tb-table tb-table-sm align-middle mb-0" data-no-onion data-excel-preview-table>
                        <thead><tr data-excel-head></tr></thead>
                        <tbody data-excel-body></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <a class="btn btn-success disabled" href="#" data-excel-download aria-disabled="true">
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> 下載 Excel
                </a>
            </div>
        </div>
    </div>
</div>

@once
<script>
    // 點「匯出 Excel」先預覽：用同一個網址加上 preview=1 取得表格資料，確認後才由「下載 Excel」真正下載。
    document.addEventListener('DOMContentLoaded', function () {
        var modalEl = document.getElementById('excelPreviewModal');
        if (!modalEl || !window.bootstrap) { return; }
        var head = modalEl.querySelector('[data-excel-head]');
        var body = modalEl.querySelector('[data-excel-body]');
        var summary = modalEl.querySelector('[data-excel-summary]');
        var download = modalEl.querySelector('[data-excel-download]');

        function cell(tag, text) { var el = document.createElement(tag); el.className = 'text-nowrap'; el.textContent = text == null ? '' : text; return el; }

        function setDownload(url) {
            download.href = url || '#';
            download.classList.toggle('disabled', !url);
            download.setAttribute('aria-disabled', url ? 'false' : 'true');
        }

        document.querySelectorAll('a[data-excel-preview]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                var url = link.href;
                var previewUrl = new URL(url, window.location.href);
                previewUrl.searchParams.set('preview', '1');
                head.innerHTML = ''; body.innerHTML = '';
                summary.textContent = '載入中…';
                setDownload(null);
                window.bootstrap.Modal.getOrCreateInstance(modalEl).show();

                fetch(previewUrl.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (response) { if (!response.ok) { throw new Error('http ' + response.status); } return response.json(); })
                    .then(function (data) {
                        data.headings.forEach(function (h) { head.appendChild(cell('th', h)); });
                        data.rows.forEach(function (row) {
                            var tr = document.createElement('tr');
                            row.forEach(function (value) { tr.appendChild(cell('td', value)); });
                            body.appendChild(tr);
                        });
                        summary.textContent = data.total === 0
                            ? '目前的篩選條件下沒有資料可以匯出。'
                            : '檔案「' + data.file + '」共 ' + data.total + ' 筆'
                                + (data.total > data.rows.length ? '（預覽只顯示前 ' + data.rows.length + ' 筆，下載的 Excel 會有全部）' : '') + '。確認內容後按「下載 Excel」。';
                        setDownload(data.total === 0 ? null : url);
                    })
                    .catch(function () { summary.textContent = '預覽載入失敗，請關閉視窗後再試一次。'; });
            });
        });
    });
</script>
@endonce

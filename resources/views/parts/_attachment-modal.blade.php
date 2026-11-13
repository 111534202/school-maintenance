{{-- 附件預覽視窗：圖片直接顯示、PDF 用內嵌檢視器；其他格式提供下載連結。
     用法：按鈕加上 data-bs-toggle="modal" data-bs-target="#attachmentModal" data-title，以及
       - 一個附件：data-url data-mime data-filename；
       - 好幾個附件：data-attachments='[{"url":…,"mime":…,"filename":…}, …]'，視窗裡用「‹ ›」左右切換（鍵盤左右鍵也可以），標題旁顯示第幾個／共幾個。
     零件履歷（parts/show）與修改採購（parts/purchases/form）共用。 --}}
<div class="modal fade" id="attachmentModal" tabindex="-1" aria-labelledby="attachmentModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable attachment-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 text-truncate" id="attachmentModalTitle">附件</h2>
                <span class="text-muted small text-truncate ms-2" id="attachmentModalName"></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
            </div>
            <div class="modal-body text-center bg-light position-relative" id="attachmentModalBody"></div>
            <div class="modal-footer justify-content-between">
                <div class="d-flex align-items-center gap-2" id="attachmentModalNav">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="attachmentModalPrev" aria-label="上一個附件"><i class="bi bi-chevron-left"></i></button>
                    <span class="small text-muted" id="attachmentModalCount" aria-live="polite"></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="attachmentModalNext" aria-label="下一個附件"><i class="bi bi-chevron-right"></i></button>
                </div>
                <div class="d-flex gap-2 ms-auto">
                    <a href="#" id="attachmentModalOpen" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">在新分頁開啟／下載</a>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">關閉</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('attachmentModal');
    const body = document.getElementById('attachmentModalBody');
    const nav = document.getElementById('attachmentModalNav');
    const prev = document.getElementById('attachmentModalPrev');
    const next = document.getElementById('attachmentModalNext');
    const count = document.getElementById('attachmentModalCount');
    let items = [];
    let index = 0;
    let title = '';

    function render() {
        const item = items[index];
        document.getElementById('attachmentModalTitle').textContent = items.length > 1 ? title + ' ' + (index + 1) : title;
        // 標題旁邊顯示檔案原本的名稱（上傳時的檔名）。
        document.getElementById('attachmentModalName').textContent = item.filename || '';
        document.getElementById('attachmentModalOpen').href = item.url;
        nav.classList.toggle('invisible', items.length < 2);
        count.textContent = (index + 1) + ' / ' + items.length;
        prev.disabled = index === 0;
        next.disabled = index === items.length - 1;
        body.innerHTML = '';

        const mime = item.mime || '';
        if (mime.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = item.url;
            img.alt = item.filename || title;
            img.className = 'img-fluid rounded';
            img.style.maxHeight = '75vh';
            body.appendChild(img);
        } else if (mime === 'application/pdf') {
            const frame = document.createElement('iframe');
            frame.src = item.url;
            frame.title = item.filename || title;
            frame.style.width = '100%';
            frame.style.height = '75vh';
            frame.style.border = '0';
            body.appendChild(frame);
        } else {
            body.innerHTML = '<p class="text-muted my-4">這個檔案格式無法在這裡預覽，請按下方「在新分頁開啟／下載」。</p>';
        }
    }

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        title = button.dataset.title || '附件';
        try {
            items = button.dataset.attachments ? JSON.parse(button.dataset.attachments) : [];
        } catch (e) {
            items = [];
        }
        if (!items.length) items = [{ url: button.dataset.url, mime: button.dataset.mime, filename: button.dataset.filename }];
        index = 0;
        render();
    });

    prev.addEventListener('click', function () { if (index > 0) { index--; render(); } });
    next.addEventListener('click', function () { if (index < items.length - 1) { index++; render(); } });
    modal.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowLeft') prev.click();
        if (event.key === 'ArrowRight') next.click();
    });

    // 關閉時清空，避免下次打開先閃過上一個附件。
    modal.addEventListener('hidden.bs.modal', function () { body.innerHTML = ''; });
});
</script>

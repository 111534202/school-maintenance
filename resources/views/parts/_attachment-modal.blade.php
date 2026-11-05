{{-- 附件預覽視窗：圖片直接顯示、PDF 用內嵌檢視器；其他格式提供下載連結。
     用法：按鈕加上 data-bs-toggle="modal" data-bs-target="#attachmentModal" data-url data-mime data-title。
     零件履歷（parts/show）與修改採購（parts/purchases/form）共用。 --}}
<div class="modal fade" id="attachmentModal" tabindex="-1" aria-labelledby="attachmentModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable attachment-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 text-truncate" id="attachmentModalTitle">附件</h2>
                <span class="text-muted small text-truncate ms-2" id="attachmentModalName"></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
            </div>
            <div class="modal-body text-center bg-light" id="attachmentModalBody"></div>
            <div class="modal-footer">
                <a href="#" id="attachmentModalOpen" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">在新分頁開啟／下載</a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">關閉</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('attachmentModal');
    const body = document.getElementById('attachmentModalBody');

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const url = button.dataset.url;
        const mime = button.dataset.mime || '';
        document.getElementById('attachmentModalTitle').textContent = button.dataset.title;
        document.getElementById('attachmentModalOpen').href = url;
    // 標題旁邊顯示檔案原本的名稱（上傳時的檔名）。
    document.getElementById('attachmentModalName').textContent = button.dataset.filename || '';
        body.innerHTML = '';

        if (mime.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = url;
            img.alt = button.dataset.title;
            img.className = 'img-fluid rounded';
            img.style.maxHeight = '75vh';
            body.appendChild(img);
        } else if (mime === 'application/pdf') {
            const frame = document.createElement('iframe');
            frame.src = url;
            frame.title = button.dataset.title;
            frame.style.width = '100%';
            frame.style.height = '75vh';
            frame.style.border = '0';
            body.appendChild(frame);
        } else {
            body.innerHTML = '<p class="text-muted my-4">這個檔案格式無法在這裡預覽，請按下方「在新分頁開啟／下載」。</p>';
        }
    });

    // 關閉時清空，避免下次打開先閃過上一個附件。
    modal.addEventListener('hidden.bs.modal', function () { body.innerHTML = ''; });
});
</script>

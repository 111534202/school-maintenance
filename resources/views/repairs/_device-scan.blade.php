{{-- 被 repairs/create.blade.php 用 @include 引入：新增報修頁的「設備掃描」功能（HTML 視窗 + JavaScript）。 --}}
{{-- 三種輸入方式共用同一個查詢流程：條碼槍（等於鍵盤輸入加 Enter）、手動輸入、手機／筆電鏡頭掃 QR 或條碼。 --}}
{{-- 查詢是用 fetch 呼叫後端 repairs.device-lookup（RepairRequestController::deviceLookup），找到設備後自動填入表單。 --}}
{{-- 相機掃描用的是 html5-qrcode 這個外部函式庫（從網路 CDN 載入）；瀏覽器只允許在 HTTPS 或 localhost 開啟相機。 --}}
{{-- 新增報修頁的「設備掃描」：條碼槍／手動輸入／相機掃描三種方式，共用同一個查詢流程。 --}}

{{-- 相機掃描視窗：開啟時才啟動鏡頭，關閉就釋放鏡頭。 --}}
{{-- Bootstrap 的彈出視窗（modal）：預設隱藏，按相機按鈕才會打開。 --}}
<div class="modal fade" id="scanModal" tabindex="-1" aria-labelledby="scanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="scanModalLabel"><i class="bi bi-camera me-2"></i>{{ __('repair_requests.create.scan_title') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.buttons.cancel') }}"></button>
            </div>
            <div class="modal-body">
                {{-- 鏡頭畫面會被 html5-qrcode 顯示在這個空的區塊裡。 --}}
                <div id="scan_reader" class="w-100"></div>
                {{-- 掃描說明或錯誤訊息的顯示位置。 --}}
                <div id="scan_message" class="small text-muted mt-3">{{ __('repair_requests.create.scan_hint') }}</div>
            </div>
        </div>
    </div>
</div>

{{-- 載入掃描函式庫（會提供全域的 Html5Qrcode 類別）。 --}}
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    // 用一個立即執行的函式把所有變數包起來，避免和頁面其他程式的變數名稱衝突。
    (function () {
        // 查詢設備的後端網址。json 轉換函式會把 PHP 的值安全地轉成 JavaScript 能用的字串。
        var lookupUrl = @json(route('repairs.device-lookup'));
        // 以下是會用到的頁面元素：條碼輸入框、錯誤提示、設備確認框、隱藏的設備編號欄位、報修標題欄位。
        var input = document.getElementById('device_code_input');
        var errorBox = document.getElementById('device_lookup_error');
        var displayBox = document.getElementById('device_display');
        var displayText = document.getElementById('device_display_text');
        var deviceIdField = document.getElementById('device_id');
        var titleField = document.getElementById('title');

        // 條碼槍對電腦來說就是鍵盤輸入 + 最後自動送出 Enter，所以偵測 Enter 或欄位失焦時觸發查詢即可。
        // 後端能辨識設備編號、資產編號、序號，以及 QR 貼紙上的整串網址（.../d/設備編號），
        // 所以這裡直接把掃到的原文送出去，不在前端自己拆。
        // 查詢設備的主函式：把輸入框的內容送到後端，依結果更新畫面。
        function lookup() {
            // 取出輸入內容並去掉前後空白；沒輸入就什麼都不做。
            var code = input.value.trim();
            if (!code) {
                return;
            }

            // fetch：在背景向後端發出請求（不換頁）；encodeURIComponent 把特殊字元轉成網址可用的格式。
            fetch(lookupUrl + '?code=' + encodeURIComponent(code), {
                headers: { 'Accept': 'application/json' },
            })
                .then(function (response) {
                    if (!response.ok) {
                        // 後端回 404（找不到設備）就丟出錯誤，由下面的 catch 顯示錯誤提示。
                        throw new Error('not found');
                    }
                    return response.json();
                })
                .then(function (data) {
                    // 查到了：隱藏錯誤、填入設備編號、顯示設備資訊；標題還是空的就自動帶入建議標題。
                    errorBox.style.display = 'none';
                    input.value = data.device_code; // 把掃到的整串網址換成乾淨的設備編號
                    deviceIdField.value = data.id;
                    displayText.textContent = data.device_code + ' ' + data.display;
                    displayBox.style.display = '';
                    if (!titleField.value) {
                        titleField.value = data.title_suggestion;
                    }
                })
                // 找不到設備或網路錯誤：清掉設備編號、隱藏設備資訊、顯示紅字錯誤提示。
                .catch(function () {
                    deviceIdField.value = '';
                    displayBox.style.display = 'none';
                    errorBox.style.display = '';
                });
        }

        // 按 Enter（條碼槍掃完會自動送出 Enter）就查詢；preventDefault 避免 Enter 直接把整張表單送出。
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                lookup();
            }
        });
        // 輸入框失去焦點（點到別處）時也查詢。
        input.addEventListener('blur', lookup);

        // ---- 相機掃描 ----
        // 以下是相機掃描：彈出視窗、說明文字、預設說明、掃描器物件。
        var scanModalEl = document.getElementById('scanModal');
        var scanMessage = document.getElementById('scan_message');
        var defaultMessage = scanMessage.textContent;
        var scanner = null;

        // 在視窗裡顯示紅色的錯誤訊息。
        function showScanError(text) {
            scanMessage.textContent = text;
            scanMessage.className = 'small text-danger mt-3';
        }

        // 停止並釋放鏡頭（視窗關閉時一定要呼叫，否則鏡頭會一直開著）。
        function stopScanner() {
            if (!scanner) {
                return Promise.resolve();
            }
            var current = scanner;
            scanner = null;
            return current.stop().catch(function () {}).then(function () { current.clear(); });
        }

        // 視窗完全打開之後才啟動鏡頭。
        scanModalEl.addEventListener('shown.bs.modal', function () {
            scanMessage.textContent = defaultMessage;
            scanMessage.className = 'small text-muted mt-3';

            // 瀏覽器只允許在 HTTPS 或 localhost 開啟相機，其他網址（例如區網 IP 的 http）會直接被擋。
            if (!window.isSecureContext || !navigator.mediaDevices) {
                showScanError(@json(__('repair_requests.create.scan_insecure')));
                return;
            }
            if (typeof Html5Qrcode === 'undefined') {
                showScanError(@json(__('repair_requests.create.scan_library_error')));
                return;
            }

            // 建立掃描器並啟動：environment 代表使用後鏡頭；fps 每秒掃 10 次；qrbox 是畫面中間 250x250 的掃描框。
            scanner = new Html5Qrcode('scan_reader');
            scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                function (decodedText) {
                    // 掃到內容後：填入輸入框、關閉視窗、立刻查詢設備。
                    input.value = decodedText;
                    bootstrap.Modal.getInstance(scanModalEl).hide();
                    lookup();
                },
                function () { /* 每一格畫面沒掃到東西都會呼叫，不用處理 */ }
            ).catch(function () {
                scanner = null;
                showScanError(@json(__('repair_requests.create.scan_camera_error')));
            });
        });

        // 視窗關閉時停止掃描、釋放鏡頭。
        scanModalEl.addEventListener('hidden.bs.modal', stopScanner);
    })();
</script>

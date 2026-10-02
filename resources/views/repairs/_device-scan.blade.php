{{-- 新增報修頁的「設備掃描」：條碼槍／手動輸入／相機掃描三種方式，共用同一個查詢流程。 --}}

{{-- 相機掃描視窗：開啟時才啟動鏡頭，關閉就釋放鏡頭。 --}}
<div class="modal fade" id="scanModal" tabindex="-1" aria-labelledby="scanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="scanModalLabel"><i class="bi bi-camera me-2"></i>{{ __('repair_requests.create.scan_title') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.buttons.cancel') }}"></button>
            </div>
            <div class="modal-body">
                <div id="scan_reader" class="w-100"></div>
                <div id="scan_message" class="small text-muted mt-3">{{ __('repair_requests.create.scan_hint') }}</div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    (function () {
        var lookupUrl = @json(route('repairs.device-lookup'));
        var input = document.getElementById('device_code_input');
        var errorBox = document.getElementById('device_lookup_error');
        var displayBox = document.getElementById('device_display');
        var displayText = document.getElementById('device_display_text');
        var deviceIdField = document.getElementById('device_id');
        var titleField = document.getElementById('title');

        // 條碼槍對電腦來說就是鍵盤輸入 + 最後自動送出 Enter，所以偵測 Enter 或欄位失焦時觸發查詢即可。
        // 後端能辨識設備編號、資產編號、序號，以及 QR 貼紙上的整串網址（.../d/設備編號），
        // 所以這裡直接把掃到的原文送出去，不在前端自己拆。
        function lookup() {
            var code = input.value.trim();
            if (!code) {
                return;
            }

            fetch(lookupUrl + '?code=' + encodeURIComponent(code), {
                headers: { 'Accept': 'application/json' },
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('not found');
                    }
                    return response.json();
                })
                .then(function (data) {
                    errorBox.style.display = 'none';
                    input.value = data.device_code; // 把掃到的整串網址換成乾淨的設備編號
                    deviceIdField.value = data.id;
                    displayText.textContent = data.device_code + ' ' + data.display;
                    displayBox.style.display = '';
                    if (!titleField.value) {
                        titleField.value = data.title_suggestion;
                    }
                })
                .catch(function () {
                    deviceIdField.value = '';
                    displayBox.style.display = 'none';
                    errorBox.style.display = '';
                });
        }

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                lookup();
            }
        });
        input.addEventListener('blur', lookup);

        // ---- 相機掃描 ----
        var scanModalEl = document.getElementById('scanModal');
        var scanMessage = document.getElementById('scan_message');
        var defaultMessage = scanMessage.textContent;
        var scanner = null;

        function showScanError(text) {
            scanMessage.textContent = text;
            scanMessage.className = 'small text-danger mt-3';
        }

        function stopScanner() {
            if (!scanner) {
                return Promise.resolve();
            }
            var current = scanner;
            scanner = null;
            return current.stop().catch(function () {}).then(function () { current.clear(); });
        }

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

            scanner = new Html5Qrcode('scan_reader');
            scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                function (decodedText) {
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

        scanModalEl.addEventListener('hidden.bs.modal', stopScanner);
    })();
</script>

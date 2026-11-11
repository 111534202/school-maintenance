{{-- 共用的「相機掃描」元件（QR Code 與各種條碼）。新增報修、新增／編輯設備都用這一份。
     用法：頁面裡 @include('scanner._camera')（一頁只會載入一次），再在任何按鈕加：
         data-scan-target="#要填入的輸入框"        掃到後填進這個輸入框，並觸發 input／change／code-scanned 事件
         data-scan-extract="device-code"（選填）   掃到的是 QR 網址（…/d/設備編號）時，只取出設備編號
         data-scan-title="視窗標題"（選填）
     掃得準的做法（比照一般掃碼 App）：
       1. 用高解析度（最高 1080p）後鏡頭、連續自動對焦，每一格畫面都用原始解析度解碼（不縮小）；
       2. 每一輪依序用「原大、放大 2 倍、放大 3 倍」三種大小解碼：小條碼、細條碼放大後才讀得出來（等於自動數位變焦）；
       3. 解碼引擎：瀏覽器內建的 BarcodeDetector（有就用，最快）；沒有就載入 zxing-wasm（BarcodeDetector 的相容實作，從 CDN 載入）；
       4. 鏡頭支援時可以用滑桿光學放大、開手電筒；
       5. 還是掃不到：「拍照／選照片掃描」用照片原始解析度解碼。
     瀏覽器只允許在 HTTPS 或 localhost 開啟相機。 --}}
@once
    <div class="modal fade" id="codeScannerModal" tabindex="-1" aria-labelledby="codeScannerTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="codeScannerTitle"><i class="bi bi-camera me-2"></i><span data-scanner-title>{{ __('scanner.title') }}</span></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.buttons.cancel') }}"></button>
                </div>
                <div class="modal-body">
                    {{-- 鏡頭畫面。playsinline：iPhone 不要自動全螢幕；muted：允許自動播放。 --}}
                    <div class="scanner-view">
                        <video id="codeScannerVideo" playsinline muted autoplay></video>
                        <div class="scanner-guide" aria-hidden="true"></div>
                    </div>
                    {{-- 光學放大與手電筒：鏡頭支援時才會出現。 --}}
                    <div class="d-flex align-items-center gap-2 mt-3" data-scanner-tools hidden>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-scanner-zoom-out aria-label="{{ __('scanner.zoom_out') }}"><i class="bi bi-zoom-out"></i></button>
                        <input type="range" class="form-range flex-grow-1" data-scanner-zoom min="1" max="1" step="0.1" value="1" aria-label="{{ __('scanner.zoom') }}">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-scanner-zoom-in aria-label="{{ __('scanner.zoom_in') }}"><i class="bi bi-zoom-in"></i></button>
                        <span class="small text-muted text-nowrap" data-scanner-zoom-label style="min-width: 2.5rem;">1.0x</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-scanner-torch hidden aria-label="{{ __('scanner.torch') }}"><i class="bi bi-lightbulb"></i></button>
                    </div>
                    <div id="codeScannerMessage" class="small text-muted mt-3" role="status">{{ __('scanner.hint') }}</div>
                    {{-- 還是掃不到：拍一張照片（手機會直接開相機）或選圖片，用照片原始大小解碼。 --}}
                    <div class="mt-3">
                        <label class="btn btn-outline-primary btn-sm mb-0">
                            <i class="bi bi-image me-1"></i>{{ __('scanner.from_photo') }}
                            <input type="file" accept="image/*" capture="environment" class="d-none" data-scanner-file>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* 鏡頭畫面：寬度填滿，高度依鏡頭比例；中間的框只是讓人知道要把條碼放在哪裡。 */
        #codeScannerModal .scanner-view { position: relative; background: #000; border-radius: .5rem; overflow: hidden; min-height: 160px; }
        #codeScannerModal .scanner-view video { display: block; width: 100%; height: auto; max-height: 60vh; object-fit: contain; }
        #codeScannerModal .scanner-guide { position: absolute; inset: 22% 8%; border: 2px solid rgba(255, 255, 255, .85); border-radius: .5rem; box-shadow: 0 0 0 9999px rgba(0, 0, 0, .25); pointer-events: none; }
    </style>

    <script>
    (function () {
        // 一頁只載入一次。
        if (window.CodeScanner) return;

        var PONYFILL_URL = 'https://cdn.jsdelivr.net/npm/barcode-detector@3.0.8/+esm';   // zxing-wasm 版 BarcodeDetector
        var FORMATS = ['qr_code', 'code_128', 'code_39', 'code_93', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf', 'codabar', 'data_matrix'];
        var DIGITAL_ZOOMS = [1, 2, 3];        // 每一輪依序用這幾種放大倍率解碼（裁切畫面中央再放大）
        var TEXT = {
            hint: @json(__('scanner.hint')),
            loading: @json(__('scanner.loading')),
            insecure: @json(__('scanner.insecure')),
            cameraError: @json(__('scanner.camera_error')),
            libraryError: @json(__('scanner.library_error')),
            photoNotFound: @json(__('scanner.photo_not_found')),
            photoReading: @json(__('scanner.photo_reading')),
        };

        var modalEl = document.getElementById('codeScannerModal');
        var video = document.getElementById('codeScannerVideo');
        var message = document.getElementById('codeScannerMessage');
        var tools = modalEl.querySelector('[data-scanner-tools]');
        var zoomRange = modalEl.querySelector('[data-scanner-zoom]');
        var zoomLabel = modalEl.querySelector('[data-scanner-zoom-label]');
        var torchBtn = modalEl.querySelector('[data-scanner-torch]');
        var fileInput = modalEl.querySelector('[data-scanner-file]');
        var titleEl = modalEl.querySelector('[data-scanner-title]');
        var defaultTitle = titleEl.textContent;

        var detector = null;     // 解碼器（載入一次，之後共用）
        var detectorPromise = null;
        var stream = null;       // 目前的鏡頭串流
        var track = null;
        var capabilities = {};
        var torchOn = false;
        var running = false;     // 掃描迴圈是否繼續
        var tick = 0;
        var current = null;      // 目前這次掃描的設定 { onResult, title }
        var handled = false;     // 已經掃到了（避免重複觸發）
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d', { willReadFrequently: true });

        function say(text, isError) {
            message.textContent = text;
            message.className = 'small mt-3 ' + (isError ? 'text-danger' : 'text-muted');
        }

        // 取得解碼器：瀏覽器有內建的就用，沒有才載入 zxing-wasm 版（第一次用到才載入）。
        function getDetector() {
            if (detector) return Promise.resolve(detector);
            if (detectorPromise) return detectorPromise;
            detectorPromise = (async function () {
                if ('BarcodeDetector' in window) {
                    try {
                        var supported = await window.BarcodeDetector.getSupportedFormats();
                        var usable = FORMATS.filter(function (f) { return supported.indexOf(f) !== -1; });
                        // 內建的要涵蓋 QR 與常見條碼才用；只支援一部分（例如只有 QR）就改用 zxing-wasm。
                        if (usable.indexOf('qr_code') !== -1 && usable.indexOf('code_128') !== -1) {
                            return (detector = new window.BarcodeDetector({ formats: usable }));
                        }
                    } catch (e) { /* 內建的有問題就改用 zxing-wasm */ }
                }
                var mod = await import(PONYFILL_URL);
                return (detector = new mod.BarcodeDetector({ formats: FORMATS }));
            })().catch(function (e) { detectorPromise = null; throw e; });
            return detectorPromise;
        }

        // 把來源（畫面或照片）裁切中央 1/z 再放大回原尺寸，交給解碼器。z=1 就是原圖。
        async function detectAt(source, w, h, z) {
            var sw = w / z, sh = h / z;
            canvas.width = Math.round(w);
            canvas.height = Math.round(h);
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(source, (w - sw) / 2, (h - sh) / 2, sw, sh, 0, 0, canvas.width, canvas.height);
            var found = await detector.detect(canvas);
            return found && found.length ? found[0].rawValue : null;
        }

        function finish(text) {
            if (handled || !current) return;
            handled = true;
            var cb = current.onResult;
            if (navigator.vibrate) { try { navigator.vibrate(60); } catch (e) {} }
            stopCamera();
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            if (cb) cb(String(text).trim());
        }

        // 掃描迴圈：每一格畫面輪流用 1x、2x、3x 解碼，掃到就結束。
        async function loop() {
            if (!running) return;
            try {
                if (video.readyState >= 2 && video.videoWidth) {
                    var z = DIGITAL_ZOOMS[tick++ % DIGITAL_ZOOMS.length];
                    var text = await detectAt(video, video.videoWidth, video.videoHeight, z);
                    if (text && running) return finish(text);
                }
            } catch (e) { /* 這一格解不出來，繼續下一格 */ }
            if (running) setTimeout(loop, 60);
        }

        function applyZoom(value) {
            if (!capabilities.zoom) return;
            var v = Math.max(capabilities.zoom.min, Math.min(capabilities.zoom.max, value));
            zoomRange.value = v;
            zoomLabel.textContent = v.toFixed(1) + 'x';
            track.applyConstraints({ advanced: [{ zoom: v }] }).catch(function () {});
        }

        // 鏡頭啟動後：支援連續對焦就開、支援光學放大與手電筒才顯示控制項。
        function setupTrack() {
            capabilities = (track.getCapabilities && track.getCapabilities()) || {};
            tools.hidden = true;
            torchBtn.hidden = true;
            torchOn = false;
            if (capabilities.focusMode && capabilities.focusMode.indexOf('continuous') !== -1) {
                track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(function () {});
            }
            if (capabilities.zoom && capabilities.zoom.max > capabilities.zoom.min) {
                zoomRange.min = capabilities.zoom.min;
                zoomRange.max = capabilities.zoom.max;
                zoomRange.step = capabilities.zoom.step || 0.1;
                tools.hidden = false;
                applyZoom(capabilities.zoom.min);
            }
            if (capabilities.torch) {
                torchBtn.hidden = false;
                tools.hidden = false;
            }
        }

        function stopCamera() {
            running = false;
            if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); }
            stream = track = null;
            capabilities = {};
            video.srcObject = null;
            tools.hidden = true;
        }

        async function openStream() {
            // 優先後鏡頭 + 1080p；條件太嚴格（例如筆電只有前鏡頭）就退回一般條件。
            try {
                return await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } } });
            } catch (e) {
                return await navigator.mediaDevices.getUserMedia({ audio: false, video: true });
            }
        }

        async function startCamera() {
            if (!window.isSecureContext || !navigator.mediaDevices) return say(TEXT.insecure, true);
            say(TEXT.loading, false);
            try {
                await getDetector();
            } catch (e) {
                return say(TEXT.libraryError, true);
            }
            try {
                stream = await openStream();
            } catch (e) {
                return say(TEXT.cameraError, true);
            }
            if (!modalEl.classList.contains('show') || handled) { stopCamera(); return; }   // 載入期間視窗已經關了
            track = stream.getVideoTracks()[0];
            video.srcObject = stream;
            try { await video.play(); } catch (e) { /* autoplay 已設定，失敗就等它自己播 */ }
            setupTrack();
            say(TEXT.hint, false);
            running = true;
            tick = 0;
            loop();
        }

        // 拍照／選照片：停掉鏡頭，用照片原始解析度解碼（也會放大 2、3 倍再試）；解不出來再把鏡頭開回來。
        fileInput.addEventListener('change', async function () {
            var file = fileInput.files && fileInput.files[0];
            fileInput.value = '';
            if (!file) return;
            say(TEXT.photoReading, false);
            stopCamera();
            try {
                await getDetector();
                var bmp = await createImageBitmap(file);
                var text = null;
                for (var i = 0; i < DIGITAL_ZOOMS.length && !text; i++) {
                    text = await detectAt(bmp, bmp.width, bmp.height, DIGITAL_ZOOMS[i]);
                }
                if (bmp.close) bmp.close();
                if (text) return finish(text);
                say(TEXT.photoNotFound, true);
            } catch (e) {
                say(TEXT.photoNotFound, true);
            }
            startCamera();
        });

        zoomRange.addEventListener('input', function () { applyZoom(parseFloat(zoomRange.value)); });
        modalEl.querySelector('[data-scanner-zoom-in]').addEventListener('click', function () { applyZoom(parseFloat(zoomRange.value) + 0.5); });
        modalEl.querySelector('[data-scanner-zoom-out]').addEventListener('click', function () { applyZoom(parseFloat(zoomRange.value) - 0.5); });
        torchBtn.addEventListener('click', function () {
            if (!track) return;
            torchOn = !torchOn;
            track.applyConstraints({ advanced: [{ torch: torchOn }] }).catch(function () { torchOn = !torchOn; });
            torchBtn.classList.toggle('active', torchOn);
        });

        modalEl.addEventListener('shown.bs.modal', startCamera);
        modalEl.addEventListener('hidden.bs.modal', function () { stopCamera(); current = null; });

        // 掃到 QR 網址（…/d/設備編號）時只取設備編號；其他內容（條碼的序號、資產編號）原樣使用。
        function normalize(text, mode) {
            if (mode === 'device-code') {
                var m = /\/d\/([^\/?#\s]+)/.exec(text);
                if (m) { try { return decodeURIComponent(m[1]); } catch (e) { return m[1]; } }
            }
            return text;
        }

        window.CodeScanner = {
            // open({ title, onResult(text) })
            open: function (options) {
                current = options || {};
                handled = false;
                titleEl.textContent = current.title || defaultTitle;
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            },
        };

        // 任何帶 data-scan-target 的按鈕：掃到後填進指定輸入框。
        document.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-scan-target]');
            if (!btn) return;
            event.preventDefault();
            var target = document.querySelector(btn.getAttribute('data-scan-target'));
            if (!target) return;
            var mode = btn.getAttribute('data-scan-extract');
            window.CodeScanner.open({
                title: btn.getAttribute('data-scan-title'),
                onResult: function (text) {
                    target.value = normalize(text, mode);
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                    target.dispatchEvent(new Event('change', { bubbles: true }));
                    target.dispatchEvent(new CustomEvent('code-scanned', { bubbles: true, detail: { text: text } }));
                    target.focus();
                },
            });
        });
    })();
    </script>
@endonce

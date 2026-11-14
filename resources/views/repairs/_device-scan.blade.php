{{-- 被 repairs/create.blade.php 用 @include 引入：新增報修頁的「設備掃描」功能（HTML 視窗 + JavaScript）。 --}}
{{-- 三種輸入方式共用同一個查詢流程：條碼槍（等於鍵盤輸入加 Enter）、手動輸入、手機／筆電鏡頭掃 QR 或條碼。 --}}
{{-- 查詢是用 fetch 呼叫後端 repairs.device-lookup（RepairRequestController::deviceLookup），找到設備後自動填入表單。 --}}
{{-- 相機掃描見 scanner/_camera（解碼函式庫放在 public/vendor；瀏覽器只允許在 HTTPS 或 localhost 開啟相機）。 --}}
{{-- 新增報修頁的「設備掃描」：條碼槍／手動輸入／相機掃描三種方式，共用同一個查詢流程。 --}}

{{-- 相機掃描視窗與掃描器已搬到共用元件 scanner/_camera（新增設備也用同一份）；掃到內容後它會填入輸入框並觸發 code-scanned 事件。 --}}
@include('scanner._camera')

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
                    displayText.textContent = data.device_code + ' ' + data.display + (data.serial_number ? '（序號 ' + data.serial_number + '）' : '');
                    displayBox.style.display = '';
                    // 設備地點自動帶入設備所在的教室（例如掃到 DEV-0001 → 資訊教室一）。
                    if (data.classroom && window.setRepairClassroom) {
                        window.setRepairClassroom(data.classroom.id, data.classroom.label);
                    }
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
        // 共用元件掃到內容後會把原文填進輸入框並觸發 code-scanned：立刻查詢設備（後端認得設備編號、資產編號、序號與 QR 網址）。
        input.addEventListener('code-scanned', lookup);
    })();
</script>

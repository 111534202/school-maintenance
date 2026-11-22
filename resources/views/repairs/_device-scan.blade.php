{{-- 被 repairs/create.blade.php 用 @include 引入：新增報修頁的「設備掃描」功能（HTML 視窗 + JavaScript）。 --}}
{{-- 三種輸入方式共用同一個查詢流程：條碼槍（等於鍵盤輸入加 Enter）、手動輸入、手機／筆電鏡頭掃 QR 或條碼。 --}}
{{-- 查詢是用 fetch 呼叫後端 repairs.device-lookup（RepairRequestController::deviceLookup），找到設備後自動填入表單。 --}}
{{-- 相機掃描見 scanner/_camera（解碼函式庫放在 public/vendor；瀏覽器只允許在 HTTPS 或 localhost 開啟相機）。 --}}
{{-- 新增報修頁的「設備掃描」：條碼槍／手動輸入／相機掃描三種方式，共用同一個查詢流程。 --}}

{{-- 相機掃描視窗與掃描器已搬到共用元件 scanner/_camera（新增設備也用同一份）；掃到內容後它會填入輸入框並觸發 code-scanned 事件。 --}}
@include('scanner._camera')

{{-- 設備條碼框也可以打字搜尋：列出還在使用的設備（設備編號＋類別、教室），輸入關鍵字會篩選，選一個就自動查詢帶入。 --}}
@php
    $deviceOptions = \App\Models\Device::query()->with(['category', 'classroom'])->whereNotIn('status', ['retired'])->orderBy('device_code')->get();
@endphp
<datalist id="device_code_options">
    @foreach ($deviceOptions as $option)
        <option value="{{ $option->device_code }}">{{ trim(($option->category?->name ?? '') . ' ' . ($option->brand ?? '') . ' ' . ($option->model ?? '') . '（' . ($option->classroom?->name ?? '—') . '）') }}</option>
    @endforeach
</datalist>

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
                    if (response.status === 422) {
                        // 設備已淘汰等「找得到但不能報修」的情況：顯示後端給的原因。
                        return response.json().then(function (body) {
                            throw new Error(body.message || 'not found');
                        });
                    }
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
                    lookedUpCode = data.device_code;
                    deviceIdField.value = data.id;
                    displayText.textContent = data.device_code + ' ' + data.display + (data.serial_number ? '（序號 ' + data.serial_number + '）' : '');
                    // 「掃描到的設備：…」只在用相機掃描時顯示（手動輸入、從清單選的，輸入框裡已經看得到設備，就不再多一個框）。
                    displayBox.style.display = fromCamera ? '' : 'none';
                    fromCamera = false;
                    // 發現地點自動帶入設備所在的教室（例如掃到 DEV-0001 → 資訊教室一）。
                    if (data.classroom && window.setRepairClassroom) {
                        window.setRepairClassroom(data.classroom.id, data.classroom.label);
                    }
                    if (!titleField.value) {
                        titleField.value = data.title_suggestion;
                    }
                })
                // 找不到設備或網路錯誤：清掉設備編號、隱藏設備資訊、顯示紅字錯誤提示。
                .catch(function (error) {
                    deviceIdField.value = '';
                    displayBox.style.display = 'none';
                    // 找不到設備沿用原本的提示；有明確原因（例如已淘汰）就改顯示原因。
                    var text = document.getElementById('device_lookup_error_text');
                    if (text) {
                        text.textContent = (error && error.message && error.message !== 'not found' && error.message.indexOf('fetch') === -1)
                            ? error.message
                            : text.dataset.defaultText;
                    }
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
        // 打字搜尋：接上設備清單（datalist），從清單選一個就馬上查詢帶入（不用再按 Enter）。
        input.setAttribute('list', 'device_code_options');
        input.addEventListener('change', lookup);

        // 設備條碼／編號可以留空（例如電燈壞了沒有條碼可掃），由維修人員填維修紀錄時再補上設備。
        // 輸入框內容一改（包含清空），就把之前查到的設備清掉，不會留著上一次掃到的設備；
        // 瀏覽器按上一頁、重新整理而自動還原表單時也一樣：輸入框是空的就不帶任何設備。
        var lookedUpCode = input.value.trim();
        function clearDevice() {
            deviceIdField.value = '';
            displayBox.style.display = 'none';
            errorBox.style.display = 'none';
        }
        input.addEventListener('input', function () {
            if (input.value.trim() !== lookedUpCode) {
                clearDevice();
            }
        });
        function resetIfEmpty() {
            if (input.value.trim() === '') {
                clearDevice();
                lookedUpCode = '';
            }
        }
        window.addEventListener('pageshow', resetIfEmpty);
        resetIfEmpty();

        // ---- 相機掃描 ----
        // 共用元件掃到內容後會把原文填進輸入框並觸發 code-scanned：立刻查詢設備（後端認得設備編號、資產編號、序號與 QR 網址）。
        var fromCamera = false;
        input.addEventListener('code-scanned', function () { fromCamera = true; lookup(); });
    })();
</script>

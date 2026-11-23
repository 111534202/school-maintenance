{{-- 讓日期時間欄位的「最晚時間」（max）一直等於「伺服器現在的時間」（精確到分鐘）。
     為什麼不能只在頁面產生時寫死 max：使用者打開表單、過了幾分鐘才填「現在」，寫死的 max 已經過時，瀏覽器會誤擋。
     這裡用「伺服器時間 − 這台電腦時間」的差距推算，所以就算使用者電腦的時鐘不準、或在別的時區也一樣。
     用法：@include('partials.live-max-now', ['selectors' => ['#started_at', '#ended_at']])（放在表單後面）。
     伺服端仍然會檢查（不能晚於送出的那一刻），這裡只是讓瀏覽器提早提醒。 --}}
<script>
(function () {
    var selectors = @json($selectors);
    var offset = {{ now()->getTimestamp() * 1000 }} - Date.now();   // 伺服器與這台電腦的時間差（毫秒）
    var utcOffsetMinutes = {{ now()->utcOffset() }};                // 系統時區（台北是 +480 分鐘）
    function pad(n) { return String(n).padStart(2, '0'); }
    function serverNowStamp() {
        var d = new Date(Date.now() + offset + utcOffsetMinutes * 60000);
        return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()) + 'T' + pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes());
    }
    function refresh() {
        var stamp = serverNowStamp();
        selectors.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (el) { el.max = stamp; });
        });
    }
    // 開始填、每次輸入、按下送出前都更新一次；放著不動時每 5 秒更新。
    document.addEventListener('focusin', function (event) { if (event.target.matches && selectors.some(function (s) { return event.target.matches(s); })) refresh(); });
    document.addEventListener('input', refresh);
    document.addEventListener('click', function (event) { if (event.target.closest && event.target.closest('button[type=submit], input[type=submit]')) refresh(); }, true);
    setInterval(refresh, 5000);
    refresh();
})();
</script>

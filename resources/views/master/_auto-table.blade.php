{{-- 主檔頁面的「表格／清單」自動切換（用戶、身分、部門、教室、設備類別、設備）。
     目的：螢幕夠大時用表格，一次看到足夠多的欄位；螢幕不夠大時用清單，不用左右滑動。
     規則（三個條件都符合才用表格，否則用清單）：
       1. 視窗寬度至少 MIN_WIDTH；
       2. 寬高比至少 MIN_RATIO（直立或接近正方形的視窗不用表格）；
       3. 表格真的放得下（不需要左右捲動）。
     做法：直接切換洋蔥清單元件已經建好的兩個區塊（表格區 .table-responsive、清單區 .onion-list）的 hidden，
     不去改洋蔥元件檔案，也不寫入「全站共用的表格／清單記憶」（不會影響其他頁面）；
     這幾頁的手動切換鈕因為已經自動切換，所以收起來。視窗大小改變時（旋轉、拉視窗）會即時重新判斷。 --}}
<script>
    (function () {
        var MIN_WIDTH = 1100;
        var MIN_RATIO = 1.25;
        var views = [];
        var busy = false;   // 自己派發 resize 時，不要再觸發自己

        function collect() {
            document.querySelectorAll('.table-responsive').forEach(function (wrap) {
                var list = wrap.nextElementSibling;
                if (!list || !list.classList.contains('onion-list')) return;   // 這張表沒有被洋蔥轉成清單（例如沒有 JS 或欄位太少）
                var bar = wrap.previousElementSibling;
                if (bar && bar.querySelector('button') && !bar.querySelector('table')) bar.style.setProperty('display', 'none', 'important');   // 手動切換鈕（bar 帶有 d-flex，Bootstrap 的 !important 會蓋掉 hidden 與一般的 display，所以要加 important）
                views.push({ wrap: wrap, list: list });
            });
        }

        function show(v, asTable) {
            v.wrap.hidden = !asTable;
            v.list.hidden = asTable;
        }

        function apply() {
            var roomy = window.innerWidth >= MIN_WIDTH && window.innerWidth / window.innerHeight >= MIN_RATIO;
            views.forEach(function (v) {
                if (!roomy) { show(v, false); return; }
                // 先試試用表格，量一下放不放得下；放不下就退回清單。
                show(v, true);
                if (v.wrap.scrollWidth > v.wrap.clientWidth + 1) show(v, false);
            });
            // 讓「一個畫面」（fit-screen）依新的內容重新計算高度。
            busy = true;
            window.dispatchEvent(new Event('resize'));
            busy = false;
        }

        var timer = null;
        function schedule() { if (busy) return; clearTimeout(timer); timer = setTimeout(apply, 120); }

        // 洋蔥元件在頁面最後才建好清單，所以等頁面載入完成再處理。
        document.addEventListener('DOMContentLoaded', function () {
            collect();
            apply();
            window.addEventListener('resize', schedule);
            window.addEventListener('orientationchange', schedule);
        });
    })();
</script>

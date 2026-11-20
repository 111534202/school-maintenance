{{-- 讓列表頁「剛好一個畫面」：不用整頁一直往下滑。 --}}
{{-- 電腦版：頁面外框（標題、篩選列、分頁）固定在畫面內，只有清單／表格本身在框內捲動（表頭固定在最上面）。 --}}
{{-- 手機版：清單一頁顯示 15 筆（跟伺服器分頁一樣），所以直接用伺服器的分頁「‹ 1 / 10 ›」（pagination/tb）；
     只有固定筆數（data-fit-per-page）的清單才在畫面上自己分頁，同樣是「‹ 1 / 3 ›」。
     兩種分頁的目前頁數都放在有外框的輸入框裡，可以直接改成要跳到的頁數。 --}}
{{-- 做法：載入後量畫面高度再調整；改變視窗大小或切換「清單／表格」檢視時重新量。沒有 JavaScript 時就是原本的長頁面，什麼都不會少。 --}}
{{-- 不想套用的清單：在清單外層或表格加 data-no-fit。 --}}
{{-- 想固定一頁顯示幾筆（不管畫面大小、電腦或手機）：在表格外層加 data-fit-per-page="5"，清單檢視就固定 5 筆一頁。 --}}
<style>
    /* 電腦版：清單在固定高度的框內捲動，表頭固定。 */
    .fit-scroll { overflow-y: auto; overscroll-behavior: contain; }
    .fit-scroll .table thead th { position: sticky; top: 0; z-index: 1; }
    /* 頂部列的實際高度有小數（約 56.4px），側邊欄少算 1px，整頁才不會多出一點點捲動。 */
    @media (min-width: 992px) {
        .app-sidebar { min-height: calc(100vh - 57px) !important; }
        .app-sidebar .drill { height: calc(100vh - 57px) !important; }
    }
    /* 手機版：自己的分頁列。 */
    /* 「‹ 1 / 2 ›」置中，上下留白，圓形按鈕不會壓到卡片外框。 */
    .fit-pager { display: flex; align-items: center; justify-content: center; gap: .75rem; margin: 0; padding: .75rem 0; }
    .fit-pager .btn { width: 2.25rem; height: 2.25rem; padding: 0; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; }
    .fit-pager .fit-label { flex: 0 0 auto; min-width: 3.5em; text-align: center; font-size: .95rem; line-height: 1.2; font-variant-numeric: tabular-nums; }
    .fit-pager .fit-label small { display: block; color: #6c757d; }
    /* 伺服器分頁的手機版（pagination/tb）：外觀和上面相同。 */
    .tb-page-jump { padding: .5rem 0; }
    .tb-page-jump .btn { width: 2.25rem; height: 2.25rem; padding: 0; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; }
    /* 左右箭頭按鈕：不管是連結還是停用的按鈕都一樣大、一樣高；箭頭圖示用固定行高置中（Windows 字型的圖示基線會偏，造成左右看起來高低不同）。 */
    .tb-page-jump .btn, .fit-pager .btn { flex: 0 0 2.25rem; min-height: 0; margin: 0; line-height: 1; box-sizing: border-box; vertical-align: middle; }
    .tb-page-jump .btn .bi, .fit-pager .btn .bi { display: block; line-height: 1; font-size: 1rem; }
    .tb-page-jump .btn .bi::before, .fit-pager .btn .bi::before { vertical-align: top; }
    .tb-page-label { white-space: nowrap; font-variant-numeric: tabular-nums; }
    .tb-page-input { width: 3.2em; padding: .15rem .25rem; text-align: center; }
</style>
<script>
    // 頁數輸入框：目前頁數放在有外框的輸入框裡，直接改數字，按 Enter（或點旁邊）就跳過去；
    // 不是數字、小於 1 或超過總頁數就不跳，輸入框還原成目前頁數。按 Esc 也還原。
    window.tbBindPageInput = function (input, total, jump) {
        function restore() { input.value = input.dataset.current; }
        function go() {
            var text = input.value.trim();
            var n = /^\d+$/.test(text) ? parseInt(text, 10) : NaN;
            if (!n || n < 1 || n > total || String(n) === input.dataset.current) { restore(); return; }
            jump(n);
        }
        input.addEventListener('focus', function () { input.select(); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); go(); }
            if (e.key === 'Escape') { restore(); input.blur(); }
        });
        input.addEventListener('change', go);
    };

    // 伺服器分頁的手機版（pagination/tb）：跳頁＝換成那一頁的網址（保留篩選條件）。
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.tb-page-jump').forEach(function (box) {
            var input = box.querySelector('.tb-page-input');
            if (!input) return;
            window.tbBindPageInput(input, parseInt(box.dataset.lastPage, 10) || 1, function (page) {
                var url = new URL(box.dataset.pageUrl, window.location.href);
                url.searchParams.set(box.dataset.pageName || 'page', page);
                window.location.href = url.href;
            });
        });
    });

    (function () {
        var desktop = window.matchMedia('(min-width: 992px)');
        var pagers = [];        // 手機版建立過的分頁列，重新量的時候先拆掉
        var timer = null;

        function visible(el) { return !!(el && !el.hidden && el.offsetParent !== null); }

        // 找出「主要的那份清單」：頁面上第一個看得到、而且資料夠多（6 筆以上）的清單或表格。
        function mainList() {
            var cands = document.querySelectorAll('.app-content .onion-list, .app-content .table-responsive');
            for (var i = 0; i < cands.length; i++) {
                var el = cands[i];
                if (el.closest('[data-no-fit]') || el.hasAttribute('data-no-fit') || el.querySelector('[data-no-fit]')) continue;
                if (!visible(el)) continue;
                var rows = el.classList.contains('onion-list') ? el.children.length : el.querySelectorAll('tbody > tr').length;
                var per = parseInt(el.getAttribute('data-fit-per-page'), 10);
                if (per > 0 ? rows > per : rows >= 6) return el;
            }
            return null;
        }

        // ---- 還原：把上一次調整的東西全部拆掉，重新量才準 ----
        function reset() {
            document.querySelectorAll('.fit-scroll').forEach(function (el) { el.classList.remove('fit-scroll'); el.style.maxHeight = ''; });
            pagers.forEach(function (p) {
                if (p.bar && p.bar.parentNode) p.bar.parentNode.removeChild(p.bar);
                if (p.items) p.items.forEach(function (it) { it.style.removeProperty('display'); });
                if (p.serverNav) p.serverNav.hidden = false;
            });
            pagers = [];
        }

        // ---- 電腦版：算出整頁超出畫面多少，從清單的高度扣掉 ----
        function fitDesktop(el) {
            var doc = document.documentElement;
            var over = doc.scrollHeight - window.innerHeight;
            if (over <= 0) return;
            // 多扣 2px：小數點的進位誤差會讓整頁多出 1px 的捲動。
            var h = Math.max(220, el.offsetHeight - over - 2);
            el.classList.add('fit-scroll');
            el.style.maxHeight = h + 'px';
        }

        // ---- 手機版：一次只顯示放得下的幾筆 ----
        function serverNavOf(list) {
            var link = document.querySelector('.app-content a[rel="next"], .app-content a[rel="prev"]');
            var nav = link && link.closest('nav');
            if (!nav) { var pg = document.querySelector('.app-content .pagination'); nav = pg && pg.closest('nav'); }
            if (!nav) return null;
            // 外面只包著它自己的小容器（例如 mt-3）也一起藏起來。
            var p = nav.parentNode;
            return (p && p.classList && !p.classList.contains('app-content') && p.children.length === 1) ? p : nav;
        }
        function serverInfo() {
            var next = document.querySelector('.app-content a[rel="next"]');
            var prev = document.querySelector('.app-content a[rel="prev"]');
            var active = document.querySelector('.app-content .page-item.active .page-link');
            var pg = document.querySelector('.app-content .pagination[data-last-page]');
            var max = pg ? parseInt(pg.dataset.lastPage, 10) : 0;
            if (!max) document.querySelectorAll('.app-content .pagination .page-link').forEach(function (a) { var n = parseInt(a.textContent, 10); if (n > max) max = n; });
            return { next: next ? next.href : null, prev: prev ? prev.href : null, page: active ? parseInt(active.textContent, 10) : 1, total: max || 1 };
        }

        function fitMobile(list) {
            if (!list.classList.contains('onion-list')) return;     // 表格檢視本來就是左右捲動的完整表格，不分頁
            var items = Array.prototype.slice.call(list.children);
            var srv = serverInfo();
            if (items.length === 0) return;

            var per = parseInt(list.getAttribute('data-fit-per-page'), 10);
            // 一頁固定 15 筆（跟伺服器的分頁一樣，全站一致）；有設定 data-fit-per-page 的清單照設定。
            var n = per > 0 ? per : 15;
            var pages = Math.ceil(items.length / n);
            if (pages <= 1) return;        // 一頁放得下：不用畫面分頁（有下一批資料時就用伺服器的分頁「‹ 1 / 10 ›」）

            var page = 0;
            var serverNav = serverNavOf(list);
            if (serverNav) serverNav.hidden = true;

            var bar = document.createElement('div');
            bar.className = 'fit-pager';
            // 精簡成「‹ 1 / 2 ›」：左右兩個圓形箭頭按鈕，中間是頁數。
            bar.innerHTML = '<button type="button" class="btn btn-outline-primary btn-sm icon-btn" data-fit-prev aria-label="上一頁"><i class="bi bi-chevron-left"></i></button>' +
                '<span class="fit-label"></span>' +
                '<button type="button" class="btn btn-outline-primary btn-sm icon-btn" data-fit-next aria-label="下一頁"><i class="bi bi-chevron-right"></i></button>';
            list.parentNode.insertBefore(bar, list.nextSibling);
            var prevBtn = bar.querySelector('[data-fit-prev]');
            var nextBtn = bar.querySelector('[data-fit-next]');
            var label = bar.querySelector('.fit-label');

            function render() {
                items.forEach(function (it, i) { var on = i >= page * n && i < (page + 1) * n; if (on) it.style.removeProperty('display'); else it.style.setProperty('display', 'none', 'important'); });
                label.innerHTML = '<input type="text" inputmode="numeric" pattern="[0-9]*" class="form-control form-control-sm d-inline-block tb-page-input" value="' + (page + 1) + '" data-current="' + (page + 1) + '" aria-label="可輸入要跳到的頁數（共 ' + pages + ' 頁）"> / ' + pages;
                window.tbBindPageInput(label.querySelector('.tb-page-input'), pages, function (n) { page = n - 1; render(); });
                prevBtn.disabled = page === 0 && !srv.prev;
                nextBtn.disabled = page >= pages - 1 && !srv.next;
            }
            prevBtn.addEventListener('click', function () {
                if (page > 0) { page--; render(); } else if (srv.prev) { window.location.href = srv.prev; }
            });
            nextBtn.addEventListener('click', function () {
                if (page < pages - 1) { page++; render(); } else if (srv.next) { window.location.href = srv.next; }
            });
            render();
            pagers.push({ bar: bar, items: items, serverNav: serverNav });
        }

        function fit() {
            reset();
            var list = mainList();
            if (!list) return;
            if (list.hasAttribute('data-fit-per-page')) fitMobile(list);     // 固定筆數：電腦、手機都一樣分頁
            else if (desktop.matches) fitDesktop(list); else fitMobile(list);
        }
        function schedule() { clearTimeout(timer); timer = setTimeout(fit, 120); }

        // 載入完成、改變視窗大小、手機轉向、切換清單／表格檢視時都重新量。
        window.addEventListener('load', fit);
        window.addEventListener('resize', schedule);
        window.addEventListener('orientationchange', schedule);
        document.querySelectorAll('.onion-list').forEach(function (l) {
            new MutationObserver(function () { schedule(); }).observe(l, { attributes: true, attributeFilter: ['hidden'] });
        });
        if (document.readyState === 'complete') fit();
    })();
</script>

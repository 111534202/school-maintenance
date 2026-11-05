{{-- 讓列表頁「剛好一個畫面」：不用整頁一直往下滑。 --}}
{{-- 電腦版：頁面外框（標題、篩選列、分頁）固定在畫面內，只有清單／表格本身在框內捲動（表頭固定在最上面）。 --}}
{{-- 手機版：清單一次只顯示一個畫面放得下的幾筆，底下用「上一頁／下一頁」切換（跟伺服器的分頁合併成同一組按鈕，不會有兩排分頁）。 --}}
{{-- 做法：載入後量畫面高度再調整；改變視窗大小或切換「清單／表格」檢視時重新量。沒有 JavaScript 時就是原本的長頁面，什麼都不會少。 --}}
{{-- 不想套用的清單：在清單外層或表格加 data-no-fit。 --}}
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
    .fit-pager { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-top: .5rem; }
    .fit-pager .btn { min-height: 2.75rem; min-width: 5.5rem; }
    .fit-pager .fit-label { flex: 1 1 auto; text-align: center; font-size: .9rem; line-height: 1.2; }
    .fit-pager .fit-label small { display: block; color: #6c757d; }
</style>
<script>
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
                if (rows >= 6) return el;
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
            var max = 0;
            document.querySelectorAll('.app-content .pagination .page-link').forEach(function (a) { var n = parseInt(a.textContent, 10); if (n > max) max = n; });
            return { next: next ? next.href : null, prev: prev ? prev.href : null, page: active ? parseInt(active.textContent, 10) : 1, total: max || 1 };
        }

        function fitMobile(list) {
            if (!list.classList.contains('onion-list')) return;     // 表格檢視本來就是左右捲動的完整表格，不分頁
            var items = Array.prototype.slice.call(list.children);
            var srv = serverInfo();
            if (items.length === 0) return;

            var first = items[0];
            var itemH = first.offsetHeight || 56;
            var top = list.getBoundingClientRect().top + window.pageYOffset;
            var reserve = 64 + 24;       // 分頁列（約 64px）＋頁面下方的空白
            var n = Math.max(3, Math.floor((window.innerHeight - top - reserve) / itemH));
            var pages = Math.ceil(items.length / n);
            if (pages <= 1 && !srv.next && !srv.prev) return;        // 一個畫面放得下、也沒有下一批資料：不用分頁

            var page = 0;
            var serverNav = serverNavOf(list);
            if (serverNav) serverNav.hidden = true;

            var bar = document.createElement('div');
            bar.className = 'fit-pager';
            bar.innerHTML = '<button type="button" class="btn btn-outline-primary" data-fit-prev><i class="bi bi-chevron-left"></i> 上一頁</button>' +
                '<span class="fit-label"></span>' +
                '<button type="button" class="btn btn-outline-primary" data-fit-next>下一頁 <i class="bi bi-chevron-right"></i></button>';
            list.parentNode.insertBefore(bar, list.nextSibling);
            var prevBtn = bar.querySelector('[data-fit-prev]');
            var nextBtn = bar.querySelector('[data-fit-next]');
            var label = bar.querySelector('.fit-label');

            function render() {
                items.forEach(function (it, i) { var on = i >= page * n && i < (page + 1) * n; if (on) it.style.removeProperty('display'); else it.style.setProperty('display', 'none', 'important'); });
                var text = '第 ' + (page + 1) + ' / ' + pages + ' 頁';
                if (srv.total > 1) text += '<small>資料第 ' + srv.page + ' / ' + srv.total + ' 批</small>';
                label.innerHTML = text;
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
            if (desktop.matches) fitDesktop(list); else fitMobile(list);
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

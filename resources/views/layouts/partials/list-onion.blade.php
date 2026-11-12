{{-- 清單的洋蔥式檢視：列表頁的寬表格自動變成一列一列的簡短清單（一眼看到每筆的重點，不用左右滑動），
     點某一筆，詳細內容在畫面中央的對話框打開（右上角 ✕ 或點外面關掉），每一欄的內容與原本的操作按鈕（編輯、刪除…）都在裡面。 --}}
{{-- 原本的表格仍然在網頁裡，只是預設收起來；右上角的按鈕可以切回表格檢視（每一張表各自記在這台瀏覽器）。
     不想套用的表格：在 <table> 加 data-no-onion。只要清單、不要「表格檢視」切換按鈕：在 <table> 加 data-onion-list-only。
     沒有 JavaScript 時就是原本的表格，什麼都不會少。 --}}
<style>
    .onion-item { text-align: left; min-height: 3.25rem; }
    .onion-item .onion-title { display: block; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .onion-item .onion-sub { display: block; font-size: .85rem; color: #6c757d; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .onion-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 1059; }
    /* 詳細內容：畫面中央的對話框（跟附件預覽一樣），內容太長時對話框裡面捲動。 */
    .onion-panel {
        position: fixed; top: 50%; left: 50%; width: min(94vw, 640px); max-height: 86vh; background: #fff; z-index: 1060;
        border-radius: 1rem; box-shadow: 0 1rem 2.5rem rgba(0,0,0,.25); overflow: hidden;
        transform: translate(-50%, -46%); opacity: 0; visibility: hidden;
        display: flex; flex-direction: column; transition: transform .2s ease, opacity .2s ease, visibility 0s linear .2s;
    }
    .onion-panel.is-open { transform: translate(-50%, -50%); opacity: 1; visibility: visible; transition: transform .2s ease, opacity .2s ease, visibility 0s; }
    .onion-panel-top { display: flex; align-items: center; gap: .5rem; padding: .75rem .75rem .75rem 1.25rem; border-bottom: 1px solid #dee2e6; }
    .onion-panel-top .onion-panel-title { font-weight: 600; font-size: 1.15rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1 1 auto; }
    .onion-panel-top .onion-back { border: 0; background: transparent; color: #6c757d; display: inline-flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 50%; font-size: 1.1rem; flex-shrink: 0; }
    .onion-panel-top .onion-back:hover { background: #f1f3f5; color: #212529; }
    .onion-panel-body { overflow-y: auto; padding: 1rem 1.25rem 1.25rem; flex: 1 1 auto; }
    .onion-dl { margin: 0; }
    .onion-dl > div { padding: .6rem 0; border-bottom: 1px solid #eef0f2; }
    .onion-dl dt { font-size: .8rem; font-weight: 600; color: #6c757d; margin-bottom: .15rem; }
    .onion-dl dd { margin: 0; word-break: break-word; }
    .onion-actions { display: flex; flex-wrap: wrap; gap: .5rem; padding-top: 1rem; }
    @media (prefers-reduced-motion: reduce) { .onion-panel, .onion-panel.is-open { transition: none; } }
</style>
<script>
    (function () {
        var KEY = 'onionListMode';
        var panel, overlay, lastFocus;
        var views = [];

        // 每一張表格各自記住「清單／表格」：同一頁有好幾張表（例如零件履歷的採購紀錄與使用紀錄）時，切換一張只影響那一張。
        // 記憶的鍵＝頁面路徑（網址裡的編號換成 *，所以每個零件的履歷頁共用同一組設定）＋這張表在頁面上的順序。
        function keyFor(index) { return KEY + ':' + location.pathname.replace(/\/\d+(?=\/|$)/g, '/*') + '#' + index; }
        function getMode(key) { try { return localStorage.getItem(key) === 'table' ? 'table' : 'list'; } catch (e) { return 'list'; } }
        function setMode(key, m) { try { localStorage.setItem(key, m); } catch (e) { /* 這台瀏覽器不能存設定就算了 */ } }
        // 把一格拆成「主要文字」和「備註」（徽章不算；小字、灰字算備註），清單的標題才不會擠成一長串。
        function split(td) {
            var c = td.cloneNode(true);
            var notes = [];
            Array.prototype.forEach.call(c.querySelectorAll('.badge'), function (b) { b.remove(); });
            Array.prototype.forEach.call(c.querySelectorAll('small, .small, .text-muted'), function (n) { var t = text(n); if (t) notes.push(t); n.remove(); });
            return { main: text(c), note: notes.join(' ') };
        }
        function text(el) { return (el.textContent || '').replace(/\s+/g, ' ').trim(); }

        // ---- 右側滑入的詳細面板（整頁共用一個）----
        function ensurePanel() {
            if (panel) return;
            overlay = document.createElement('div');
            overlay.className = 'onion-overlay';
            overlay.hidden = true;
            panel = document.createElement('aside');
            panel.className = 'onion-panel';
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-modal', 'true');
            panel.setAttribute('inert', '');
            panel.innerHTML = '<div class="onion-panel-top"><span class="onion-panel-title"></span><button type="button" class="onion-back" aria-label="關閉"><i class="bi bi-x-lg"></i></button></div>' +
                '<div class="onion-panel-body"><dl class="onion-dl"></dl><div class="onion-actions"></div></div>';
            document.body.appendChild(overlay);
            document.body.appendChild(panel);
            overlay.addEventListener('click', closePanel);
            panel.querySelector('.onion-back').addEventListener('click', closePanel);
            // 按 Esc 關掉詳細對話框；但如果上面還開著別的視窗（附件預覽、修改表單），Esc 只關那一個。
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('is-open') && !(e.target.closest && e.target.closest('.modal')) && !document.querySelector('.modal.show')) closePanel(); });
        }

        function openPanel(data, trigger) {
            ensurePanel();
            lastFocus = trigger;
            panel.querySelector('.onion-panel-title').textContent = data.title;
            panel.setAttribute('aria-label', data.title);
            var dl = panel.querySelector('.onion-dl');
            var actions = panel.querySelector('.onion-actions');
            dl.innerHTML = '';
            actions.innerHTML = '';
            // 這一列在表格裡可以點進詳細頁（<tr data-href>）：面板最前面放「查看詳細」，清單檢視才也進得去。
            if (data.href) {
                var open = document.createElement('a');
                open.className = 'btn btn-primary';
                open.href = data.href;
                open.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i>查看詳細';
                actions.appendChild(open);
            }
            // 已經有「查看詳細」時，內容只是一個連到同一頁的連結（例如零件清單「其他」欄的「查看履歷」）就不再重複列出。
            function onlyLinksToDetail(f) {
                if (!data.href) return false;
                var target = new URL(data.href, location.href).href;
                var hasLink = false;
                var onlyLinks = f.nodes.every(function (n) {
                    if (n.nodeType === Node.TEXT_NODE) return !n.textContent.trim();
                    if (n.nodeType !== Node.ELEMENT_NODE) return true;
                    var links = n.matches('a[href]') ? [n] : Array.prototype.slice.call(n.querySelectorAll('a[href]'));
                    if (!links.length || n.textContent.trim() !== links.map(function (a) { return a.textContent; }).join('').trim()) return false;
                    hasLink = true;
                    return links.every(function (a) { return a.href === target; });
                });
                return onlyLinks && hasLink;
            }
            data.fields.forEach(function (f) {
                if (onlyLinksToDetail(f)) return;
                var row = document.createElement('div');
                var dt = document.createElement('dt');
                var dd = document.createElement('dd');
                dt.textContent = f.label;
                f.nodes.forEach(function (n) { dd.appendChild(n.cloneNode(true)); });
                row.appendChild(dt);
                row.appendChild(dd);
                dl.appendChild(row);
            });
            data.actions.forEach(function (cell) {
                Array.prototype.forEach.call(cell.childNodes, function (n) {
                    var c = n.cloneNode(true);
                    if (c.nodeType !== 1) return;
                    // 跟「查看詳細」連到同一頁的按鈕不重複放。
                    if (data.href && c.matches('a[href]') && c.href === new URL(data.href, location.href).href) return;
                    c.removeAttribute('id');
                    // 只有圖示的按鈕補上文字（用它的 title），在面板裡才看得懂。
                    var btns = c.matches('.btn') ? [c] : Array.prototype.slice.call(c.querySelectorAll('.btn'));
                    btns.forEach(function (b) {
                        b.classList.remove('icon-btn');
                        if (!text(b)) {
                            var label = b.getAttribute('title') || b.getAttribute('aria-label');
                            if (label) { var s = document.createElement('span'); s.className = 'ms-1'; s.textContent = label; b.appendChild(s); }
                        }
                    });
                    actions.appendChild(c);
                });
            });
            overlay.hidden = false;
            panel.removeAttribute('inert');
            panel.classList.add('is-open');
            document.documentElement.style.overflow = 'hidden';
            panel.querySelector('.onion-panel-body').scrollTop = 0;
            panel.querySelector('.onion-back').focus({ preventScroll: true });
        }

        function closePanel() {
            if (!panel) return;
            panel.classList.remove('is-open');
            panel.setAttribute('inert', '');
            overlay.hidden = true;
            document.documentElement.style.overflow = '';
            if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
        }

        // ---- 把一張表格變成清單 ----
        function build(wrap, table, key) {
            if (table.hasAttribute('data-no-onion') || table.querySelector('table')) return;
            var heads = Array.prototype.map.call(table.querySelectorAll('thead th'), text);
            if (heads.length < 3) return;
            // 表格裡有要填的欄位（不是隱藏欄位）就不轉：清單沒辦法填。
            if (table.querySelector('tbody input:not([type=hidden]), tbody select, tbody textarea')) return;
            var statusCol = heads.findIndex(function (h) { return /狀態|status/i.test(h); });
            var isAction = heads.map(function (h) { return h === '' || /^(操作|actions?)$/i.test(h); });

            var rows = Array.prototype.filter.call(table.querySelectorAll('tbody > tr'), function (tr) {
                return tr.children.length === heads.length;   // 「目前沒有資料」那種合併儲存格的列不轉
            });
            if (rows.length === 0) return;

            var list = document.createElement('div');
            list.className = 'list-group shadow-sm mb-3 onion-list';

            rows.forEach(function (tr) {
                var cells = Array.prototype.slice.call(tr.children);
                var main = [];      // 每個欄位：{label, nodes}
                var actions = [];
                var badge = null;
                var badgeCell = -1;
                cells.forEach(function (td, i) {
                    if (isAction[i]) { actions.push(td); return; }
                    // 右邊的小徽章優先用「狀態」欄的，沒有狀態欄就用第一個找到的徽章。
                    var wantsThis = statusCol >= 0 ? i === statusCol : true;
                    if (!badge && wantsThis) { var b = td.querySelector('.badge'); if (b) { badge = b.cloneNode(true); badgeCell = i; } }
                });
                var texts = [];
                cells.forEach(function (td, i) {
                    if (isAction[i]) return;
                    main.push({ label: heads[i] || '', nodes: Array.prototype.slice.call(td.childNodes) });
                    var parts = split(td);
                    if (parts.main && parts.main !== '—' && parts.main !== '-') texts.push(parts.main);
                    if (parts.note) texts.push(parts.note);
                });
                var title = texts[0] || text(cells[0]) || '（無標題）';
                var sub = texts.slice(1, 3).join(' · ');

                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action d-flex align-items-center gap-2 onion-item';
                var body = document.createElement('span');
                body.className = 'flex-grow-1';
                body.style.minWidth = '0';
                var t1 = document.createElement('span');
                t1.className = 'onion-title';
                t1.textContent = title;
                body.appendChild(t1);
                if (sub) { var t2 = document.createElement('span'); t2.className = 'onion-sub'; t2.textContent = sub; body.appendChild(t2); }
                item.appendChild(body);
                if (badge) { badge.classList.add('flex-shrink-0'); item.appendChild(badge); }
                var chev = document.createElement('i');
                chev.className = 'bi bi-chevron-right text-muted flex-shrink-0';
                item.appendChild(chev);
                item.addEventListener('click', function () {
                    // 這一列設定成「點了在畫面中的對話框打開」（<tr data-modal-frame-href>）：直接打開對話框，不先開詳細面板。
                    if (tr.hasAttribute('data-modal-frame-href') && window.tbOpenRow) { window.tbOpenRow(tr); return; }
                    // 對話框的標題：預設是這一筆的標題（第一欄）；表格有 data-onion-panel-title 時改用它（例如零件履歷的採購紀錄顯示零件名稱與規格）。
                    openPanel({ title: table.getAttribute('data-onion-panel-title') || title, href: tr.getAttribute('data-href'), fields: main.filter(function (f) { return f.nodes.length; }), actions: actions }, item);
                });
                list.appendChild(item);
            });

            // 切換「清單／表格」的小按鈕。
            var bar = document.createElement('div');
            bar.className = 'd-flex justify-content-end mb-2 onion-viewbar';
            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'btn btn-sm btn-outline-secondary';
            bar.appendChild(toggle);

            // data-onion-list-only：固定清單檢視，不顯示切換按鈕。
            var listOnly = table.hasAttribute('data-onion-list-only');
            function apply() {
                var mode = listOnly ? 'list' : getMode(key);
                wrap.hidden = mode === 'list';
                list.hidden = mode === 'table';
                toggle.innerHTML = mode === 'list' ? '<i class="bi bi-table me-1"></i>表格檢視' : '<i class="bi bi-list-ul me-1"></i>清單檢視';
                toggle.setAttribute('title', mode === 'list' ? '切換成完整表格（可以左右捲動看全部欄位）' : '切換成一列一列的清單');
            }
            // 只切換這一張表。
            toggle.addEventListener('click', function () { setMode(key, getMode(key) === 'list' ? 'table' : 'list'); apply(); });
            // 表格包在卡片裡（.card、.tb-table-wrap）時，切換按鈕放在卡片外面的右上方，卡片裡只放清單或表格，
            // 不會在卡片頂端多出一條只有按鈕的空白；清單也貼齊卡片（樣式見 public/css/theme-b.css「4. 洋蔥式元件」）。
            // 卡片有標題列（.card-header，或風格 B 頁面標了 data-onion-header 的標題列）時，
            // 切換按鈕放進標題列的右邊（跟「新增採購」這類按鈕排在一起）。
            var host = wrap.parentNode;
            if (listOnly) {
                host.insertBefore(list, wrap.nextSibling);
                return { apply: apply };
            }
            wrap.onionViewbar = bar;   // 讓 auto-table（表格／清單自動切換）找得到這張表自己的切換鈕，只收起它、不動標題列
            var header = wrap.previousElementSibling;
            // 表格放在卡片內文（.card-body）的最上面、卡片有標題列時（例如報修詳細頁的「材料費明細」），切換按鈕也放進標題列。
            if (host.matches('.card-body') && host.firstElementChild === wrap && host.previousElementSibling
                && host.previousElementSibling.classList.contains('card-header')) {
                header = host.previousElementSibling;
            }
            if (host.matches('.card, .tb-table-wrap, .card-body') && header && (header.classList.contains('card-header') || header.hasAttribute('data-onion-header'))) {
                bar.className = 'onion-viewbar onion-viewbar-inline ms-auto';
                var kids = header.children;
                if (kids.length >= 2) { header.insertBefore(bar, kids[kids.length - 1]); } else { header.appendChild(bar); }
                if (getComputedStyle(header).display !== 'flex') { header.classList.add('d-flex', 'flex-wrap', 'align-items-center', 'gap-2'); }
            } else if (host.matches('.card, .tb-table-wrap') && host.firstElementChild === wrap) {
                host.parentNode.insertBefore(bar, host);
            } else {
                host.insertBefore(bar, wrap);
            }
            host.insertBefore(list, wrap.nextSibling);
            return { apply: apply };
        }

        document.querySelectorAll('.table-responsive').forEach(function (wrap, index) {
            var table = wrap.querySelector('table');
            if (!table) return;
            var v = build(wrap, table, keyFor(index));
            if (v) { v.apply(); views.push(v); }
        });
    })();
</script>

{{-- 清單的洋蔥式檢視：列表頁的寬表格自動變成一列一列的簡短清單（一眼看到每筆的重點，不用左右滑動），
     點某一筆，詳細內容從右邊滑進來（有「返回」），每一欄的內容與原本的操作按鈕（編輯、刪除…）都在裡面。 --}}
{{-- 原本的表格仍然在網頁裡，只是預設收起來；右上角的按鈕可以切回表格檢視（每一張表各自記在這台瀏覽器）。
     不想套用的表格：在 <table> 加 data-no-onion。沒有 JavaScript 時就是原本的表格，什麼都不會少。 --}}
<style>
    .onion-item { text-align: left; min-height: 3.25rem; }
    .onion-item .onion-title { display: block; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .onion-item .onion-sub { display: block; font-size: .85rem; color: #6c757d; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .onion-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 1059; }
    .onion-panel {
        position: fixed; top: 0; right: 0; bottom: 0; width: min(100vw, 520px); background: #fff; z-index: 1060;
        transform: translateX(100%); visibility: hidden; box-shadow: -6px 0 18px rgba(0,0,0,.15);
        display: flex; flex-direction: column; transition: transform .25s ease, visibility 0s linear .25s;
    }
    .onion-panel.is-open { transform: translateX(0); visibility: visible; transition: transform .25s ease, visibility 0s; }
    .onion-panel-top { display: flex; align-items: center; gap: .25rem; padding: .35rem .5rem; border-bottom: 1px solid #dee2e6; }
    .onion-panel-top .onion-back { border: 0; background: transparent; color: #0d6efd; display: inline-flex; align-items: center; gap: .15rem; min-height: 2.5rem; padding: 0 .5rem; font-size: 1rem; }
    .onion-panel-top .onion-panel-title { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .onion-panel-body { overflow-y: auto; padding: 1rem; flex: 1 1 auto; }
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
            panel.innerHTML = '<div class="onion-panel-top"><button type="button" class="onion-back"><i class="bi bi-chevron-left"></i>返回</button><span class="onion-panel-title"></span></div>' +
                '<div class="onion-panel-body"><dl class="onion-dl"></dl><div class="onion-actions"></div></div>';
            document.body.appendChild(overlay);
            document.body.appendChild(panel);
            overlay.addEventListener('click', closePanel);
            panel.querySelector('.onion-back').addEventListener('click', closePanel);
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('is-open')) closePanel(); });
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
            data.fields.forEach(function (f) {
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
                    openPanel({ title: title, fields: main.filter(function (f) { return f.nodes.length; }), actions: actions }, item);
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

            function apply() {
                var mode = getMode(key);
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
            var header = wrap.previousElementSibling;
            if (host.matches('.card, .tb-table-wrap') && header && (header.classList.contains('card-header') || header.hasAttribute('data-onion-header'))) {
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

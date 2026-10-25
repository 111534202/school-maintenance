/*
 * 「洋蔥式」響應式版面的行為（長相在 public/css/onion.css，只在 < 768px 生效）。
 * 這支程式只做兩件事：
 *   1. 依頁面結構替元素加上 class（哪個欄位是第二層、哪個區塊可收合…）；
 *   2. 處理點擊展開／收合。
 * 桌面版不受影響：class 加了也沒有對應的樣式。
 *
 * 想控制某一欄在手機上要不要放第一層：在 <th> 加 data-onion="primary"（第一層）或 data-onion="secondary"（收進詳情）。
 * 不寫的話：第一欄、「狀態／結果」欄、「操作」欄（或表頭空白的欄）放第一層，其餘放第二層；
 * 也可以在 <table> 加 data-onion-primary="1,5"，把第 2、6 欄（從 0 算起）一起放第一層。
 * 想讓某個區塊不要收合：在該 .card 加 data-onion="off"。畫圖表的卡片會自動略過。
 * 按鈕文字從 <body> 的 data-onion-* 屬性讀（由語言檔提供，所以中英文都會對）。
 */
(function () {
    'use strict';

    var body = document.body;
    var text = {
        filter: body.dataset.onionFilter || '篩選',
        details: body.dataset.onionDetails || '詳情',
        collapse: body.dataset.onionCollapse || '收合'
    };

    // 建立一顆帶圖示與文字的按鈕。
    function makeButton(className, label, icon) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.setAttribute('aria-expanded', 'false');
        var iconEl = document.createElement('i');
        iconEl.className = 'bi ' + icon;
        var labelEl = document.createElement('span');
        labelEl.className = 'onion-label';
        labelEl.textContent = label;
        button.appendChild(labelEl);
        button.appendChild(iconEl);
        return button;
    }

    // ---------- 1. 表格 → 卡片，次要欄位收進「詳情」 ----------
    var STATUS_HEADER = /狀態|結果|Status|Result/i;
    var ACTION_HEADER = /操作|Actions?/i;

    document.querySelectorAll('.table-responsive > table').forEach(function (table) {
        var headRows = table.querySelectorAll('thead tr');
        if (headRows.length !== 1) { return; }                     // 多層表頭的表格不轉換，維持橫向捲動
        var heads = Array.prototype.slice.call(headRows[0].children);
        var labels = heads.map(function (th) { return th.textContent.trim(); });
        // 表格上的 data-onion-primary="1,5" 代表「除了預設的以外，第 2、6 欄也放第一層」（欄位從 0 算起）。
        var extra = (table.dataset.onionPrimary || '').split(',').map(function (n) { return parseInt(n, 10); });
        var isAction = function (i) { return labels[i] === '' || ACTION_HEADER.test(labels[i]); };   // 表頭空白的欄位通常是操作按鈕
        var primary = heads.map(function (th, i) {
            if (th.dataset.onion) { return th.dataset.onion === 'primary'; }
            return i === 0 || extra.indexOf(i) !== -1 || STATUS_HEADER.test(labels[i]) || isAction(i);
        });
        var actionIndex = heads.findIndex(function (th, i) { return isAction(i); });

        table.classList.add('onion-table');

        table.querySelectorAll('tbody tr').forEach(function (row) {
            var cells = Array.prototype.slice.call(row.children);
            if (cells.length !== heads.length) { row.classList.add('onion-single'); return; }   // 例如 colspan 的「尚無資料」

            var hasSecondary = false;
            cells.forEach(function (cell, i) {
                cell.dataset.label = labels[i];
                if (i === 0) { cell.classList.add('onion-title'); }
                if (i === actionIndex) { cell.classList.add('onion-actions'); }
                if (!primary[i]) { cell.classList.add('onion-secondary'); hasSecondary = true; }
            });

            // 有第二層才放「詳情」按鈕；放在標題格裡（手機才顯示），不會多出一個欄位影響桌面版。
            if (hasSecondary) {
                var toggle = makeButton('btn btn-outline-secondary btn-sm onion-toggle', text.details, 'bi-chevron-down');
                toggle.addEventListener('click', function () {
                    var open = row.classList.toggle('onion-open');
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    toggle.querySelector('.onion-label').textContent = open ? text.collapse : text.details;
                });
                cells[0].appendChild(toggle);
            }
        });
    });

    // ---------- 2. 篩選列 → 一顆「篩選」按鈕 ----------
    document.querySelectorAll('.filter-bar').forEach(function (bar) {
        // 已套用幾個篩選條件：有填文字、或下拉選了不是「全部」的選項。
        var applied = Array.prototype.filter.call(bar.querySelectorAll('input:not([type=hidden]):not([type=submit]), select'), function (field) {
            return field.value !== '';
        }).length;

        var toggle = makeButton('btn btn-outline-secondary btn-sm onion-filter-toggle', text.filter, 'bi-chevron-down');
        toggle.querySelector('.onion-label').insertAdjacentHTML('afterbegin', '<i class="bi bi-funnel me-1"></i>');
        if (applied > 0) {
            var badge = document.createElement('span');
            badge.className = 'badge text-bg-primary ms-1';
            badge.textContent = applied;
            toggle.querySelector('.onion-label').appendChild(badge);
        }
        bar.classList.add('onion-collapsed');
        bar.parentNode.insertBefore(toggle, bar);
        toggle.addEventListener('click', function () {
            var collapsed = bar.classList.toggle('onion-collapsed');
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        });
    });

    // ---------- 3. 有標題的區塊 → 可收合 ----------
    // 只處理「有標題列、又有內容」的卡片；畫圖表的卡片、標示 data-onion="off" 的卡片略過。
    var cards = Array.prototype.filter.call(document.querySelectorAll('.app-content .card'), function (card) {
        var header = card.querySelector(':scope > .card-header');
        return header && card.children.length > 1 && card.dataset.onion !== 'off' && !card.querySelector('canvas');
    });

    cards.forEach(function (card, index) {
        var header = card.querySelector(':scope > .card-header');
        var chevron = document.createElement('i');
        chevron.className = 'bi bi-chevron-down onion-chevron';
        header.appendChild(chevron);
        card.classList.add('onion-card');

        // 預設：第一個區塊展開、其餘收起；含有錯誤訊息的區塊一律展開（不然使用者看不到哪裡填錯）。
        var hasError = card.querySelector('.is-invalid, .alert-danger, .text-danger.small');
        if (index > 0 && !hasError) { card.classList.add('onion-collapsed'); }

        header.addEventListener('click', function (event) {
            if (event.target.closest('a, button, input, select, label')) { return; }   // 標題列裡的連結、按鈕照常運作
            card.classList.toggle('onion-collapsed');
        });
    });

    // 表單送出時，如果某個必填欄位被收起來的區塊擋住，瀏覽器沒辦法對焦並提示；所以遇到「欄位無效」就先把它所在的區塊展開。
    document.addEventListener('invalid', function (event) {
        var card = event.target.closest && event.target.closest('.card.onion-collapsed');
        if (card) { card.classList.remove('onion-collapsed'); }
    }, true);
})();

{{-- 篩選列的洋蔥式：列表頁上方那一長排「狀態／教室／地點…」的篩選欄位，自動收成一條簡短的「篩選條件」摘要列。 --}}
{{-- 摘要列會用小標籤顯示目前套用了哪些條件；點「篩選條件」，篩選欄位從右邊滑進來、直向排列（不用左右滑），按「套用篩選」送出。 --}}
{{-- 做法：把原本的 <form class="filter-bar"> 整個搬進滑出的面板，所以欄位名稱、送出方式（GET）、清除連結都跟原本一模一樣，功能不會少。 --}}
{{-- 不想套用的篩選列：在 <form> 加 data-no-onion。欄位少於兩個的篩選列本來就放得下，不轉。沒有 JavaScript 時就是原本的篩選列。 --}}
<style>
    .fo-bar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .fo-bar .fo-open { display: inline-flex; align-items: center; gap: .4rem; min-height: 2.5rem; }
    .fo-bar .fo-chip { display: inline-flex; align-items: center; max-width: 100%; padding: .2rem .6rem; border-radius: 1rem; background: #e7f1ff; color: #0a58ca; font-size: .85rem; }
    .fo-bar .fo-chip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fo-bar .fo-none { color: #6c757d; font-size: .9rem; }
    .fo-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 1059; }
    .fo-panel {
        position: fixed; top: 0; right: 0; bottom: 0; width: min(100vw, 420px); background: #fff; z-index: 1060;
        transform: translateX(100%); visibility: hidden; box-shadow: -6px 0 18px rgba(0,0,0,.15);
        display: flex; flex-direction: column; transition: transform .25s ease, visibility 0s linear .25s;
    }
    .fo-panel.is-open { transform: translateX(0); visibility: visible; transition: transform .25s ease, visibility 0s; }
    .fo-top { display: flex; align-items: center; gap: .25rem; padding: .35rem .5rem; border-bottom: 1px solid #dee2e6; }
    .fo-top .fo-back { border: 0; background: transparent; color: #0d6efd; display: inline-flex; align-items: center; gap: .15rem; min-height: 2.5rem; padding: 0 .5rem; font-size: 1rem; }
    .fo-top .fo-title { font-weight: 600; }
    .fo-body { overflow-y: auto; padding: 1rem; flex: 1 1 auto; }
    /* 搬進面板後的篩選表單：欄位一個一個直向排、撐滿寬度，不再左右捲動。 */
    .fo-body form.filter-bar { flex-direction: column; align-items: stretch; flex-wrap: nowrap; overflow: visible; gap: 1rem; padding-bottom: 0; margin-bottom: 0; }
    .fo-body form.filter-bar > * { flex: 0 0 auto; }
    .fo-body form.filter-bar .form-control, .fo-body form.filter-bar .form-select { width: 100% !important; min-height: 2.5rem; font-size: 1rem; }
    .fo-body form.filter-bar .form-label { font-size: .9rem; }
    .fo-body form.filter-bar .d-inline-flex { display: flex !important; gap: .5rem !important; }
    .fo-body form.filter-bar .d-inline-flex .btn { flex: 1 1 0; min-height: 2.75rem; width: auto; }
    @media (prefers-reduced-motion: reduce) { .fo-panel, .fo-panel.is-open { transition: none; } }
</style>
<script>
    (function () {
        function text(el) { return (el.textContent || '').replace(/\s+/g, ' ').trim(); }

        // 找出這張表單裡「使用者看得到、可以填」的欄位。
        function controls(form) {
            return Array.prototype.filter.call(form.querySelectorAll('select[name], input[name], textarea[name]'), function (c) {
                return !/^(hidden|submit|button|reset|checkbox|radio)$/i.test(c.type || '');
            });
        }
        function labelOf(form, c) {
            var l = c.id ? form.querySelector('label[for="' + c.id + '"]') : null;
            return l ? text(l) : (c.getAttribute('placeholder') || c.name);
        }
        // 目前有填的條件（顯示用的文字：下拉選單用選項的名字，不用內部的值）。
        function activeChips(form) {
            var chips = [];
            controls(form).forEach(function (c) {
                var v = '';
                if (c.tagName === 'SELECT') {
                    v = c.value === '' ? '' : text(c.options[c.selectedIndex]);
                } else {
                    v = (c.value || '').trim();
                }
                if (v !== '') chips.push({ label: labelOf(form, c), value: v });
            });
            return chips;
        }

        function build(form) {
            if (form.hasAttribute('data-no-onion') || form.dataset.filterOnion) return;
            if (controls(form).length < 2) return;
            form.dataset.filterOnion = '1';

            var chips = activeChips(form);
            var clearLink = form.querySelector('a[href]');   // 原本的「清除篩選」連結（有套用條件時才有）

            // ---- 摘要列 ----
            var bar = document.createElement('div');
            bar.className = 'fo-bar';
            var openBtn = document.createElement('button');
            openBtn.type = 'button';
            openBtn.className = 'btn btn-outline-primary btn-sm fo-open';
            openBtn.setAttribute('aria-haspopup', 'dialog');
            openBtn.innerHTML = '<i class="bi bi-funnel"></i><span>篩選條件</span>';
            if (chips.length) {
                var count = document.createElement('span');
                count.className = 'badge text-bg-primary rounded-pill';
                count.textContent = chips.length;
                openBtn.appendChild(count);
            }
            bar.appendChild(openBtn);
            if (chips.length) {
                chips.forEach(function (c) {
                    var chip = document.createElement('span');
                    chip.className = 'fo-chip';
                    var s = document.createElement('span');
                    s.textContent = c.label + '：' + c.value;
                    chip.appendChild(s);
                    bar.appendChild(chip);
                });
                if (clearLink) {
                    var clr = clearLink.cloneNode(false);
                    clr.className = 'btn btn-outline-secondary btn-sm';
                    clr.innerHTML = '<i class="bi bi-x-circle me-1"></i>清除';
                    bar.appendChild(clr);
                }
            } else {
                var none = document.createElement('span');
                none.className = 'fo-none';
                none.textContent = '目前顯示全部';
                bar.appendChild(none);
            }

            // ---- 滑入的面板 ----
            var overlay = document.createElement('div');
            overlay.className = 'fo-overlay';
            overlay.hidden = true;
            var panel = document.createElement('aside');
            panel.className = 'fo-panel';
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-modal', 'true');
            panel.setAttribute('aria-label', '篩選條件');
            panel.setAttribute('inert', '');
            panel.innerHTML = '<div class="fo-top"><button type="button" class="fo-back"><i class="bi bi-chevron-left"></i>返回</button><span class="fo-title">篩選條件</span></div><div class="fo-body"></div>';
            var body = panel.querySelector('.fo-body');

            // 原本只有圖示的按鈕，在面板裡補上文字（用它的 title）。
            Array.prototype.forEach.call(form.querySelectorAll('.btn'), function (b) {
                b.classList.remove('icon-btn');
                if (!text(b)) {
                    var label = b.getAttribute('title') || b.getAttribute('aria-label');
                    if (label) { var s = document.createElement('span'); s.className = 'ms-1'; s.textContent = label; b.appendChild(s); }
                }
            });

            form.parentNode.insertBefore(bar, form);
            body.appendChild(form);     // 整個表單搬進面板：欄位、送出方式、清除連結都沒變
            document.body.appendChild(overlay);
            document.body.appendChild(panel);

            function open() {
                overlay.hidden = false;
                panel.removeAttribute('inert');
                panel.classList.add('is-open');
                document.documentElement.style.overflow = 'hidden';
                body.scrollTop = 0;
                var first = controls(form)[0];
                if (first) first.focus({ preventScroll: true });
            }
            function close() {
                panel.classList.remove('is-open');
                panel.setAttribute('inert', '');
                overlay.hidden = true;
                document.documentElement.style.overflow = '';
                openBtn.focus({ preventScroll: true });
            }
            openBtn.addEventListener('click', open);
            overlay.addEventListener('click', close);
            panel.querySelector('.fo-back').addEventListener('click', close);
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('is-open')) close(); });
        }

        Array.prototype.forEach.call(document.querySelectorAll('form.filter-bar'), build);
    })();
</script>

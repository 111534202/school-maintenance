{{-- 表單防呆（全站共用，layouts/app 與登入頁都會引入）：
     1. 防連點：表單送出後，送出按鈕立刻停用並顯示「處理中…」，避免連點兩下建立兩筆資料。
     2. 離開提醒：填寫到一半（內容有改過）要關閉或離開頁面時，瀏覽器會先跳出確認。
     3. 自動暫存：填寫中的文字每隔一下就存在這台電腦的瀏覽器（localStorage，依登入帳號分開、保留 3 天），
        下次打開同一個表單會自動還原並提示「已還原上次沒送出的內容」，可以按「清除重填」；成功送出後暫存就清掉。
        登入逾時（表單過期）、網路中斷、不小心關掉分頁，填過的內容都還找得回來。
     不會暫存：密碼欄位、檔案、隱藏欄位（例如查詢帶入的設備編號）、含密碼欄位的表單；表單或欄位加上 data-no-draft 也不暫存。
     表單加上 data-no-guard 則三項都不做。
     搜尋、篩選這種 GET 表單不受影響。 --}}
@php($guardUserId = auth()->id())
<script>
(function () {
    'use strict';
    var USER = @json($guardUserId);
    var TTL = 3 * 24 * 60 * 60 * 1000;   // 暫存保留 3 天
    var PENDING_KEY = 'fg:pending';

    function isPost(form) { return String(form.method || '').toLowerCase() === 'post'; }
    function hasPassword(form) { return !!form.querySelector('input[type=password]'); }

    // ---------- 1. 防連點 ----------
    function busy(form, submitter) {
        form.dataset.guardSubmitting = '1';
        var buttons = form.querySelectorAll('button[type=submit], input[type=submit], button:not([type])');
        // 等瀏覽器先把欄位（含按下的那顆按鈕的名稱與值）收集完，再停用按鈕。
        setTimeout(function () {
            buttons.forEach(function (b) {
                b.disabled = true;
                b.dataset.guardLabel = b.tagName === 'BUTTON' ? b.innerHTML : b.value;
            });
            if (submitter && submitter.tagName === 'BUTTON') {
                submitter.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>處理中…';
            }
        }, 0);
        // 安全網：10 秒後還在這一頁（伺服器沒回應、下載檔案不換頁…），就把按鈕還原讓使用者可以再試。
        setTimeout(function () { release(form); }, 10000);
    }
    function release(form) {
        if (form.dataset.guardSubmitting !== '1') return;
        delete form.dataset.guardSubmitting;
        form.querySelectorAll('[data-guard-label]').forEach(function (b) {
            if (b.tagName === 'BUTTON') b.innerHTML = b.dataset.guardLabel; else b.value = b.dataset.guardLabel;
            b.disabled = false;
            delete b.dataset.guardLabel;
        });
    }
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
        if (!isPost(form) || form.hasAttribute('data-no-guard') || (form.target && form.target !== '_self')) return;
        if (form.dataset.guardSubmitting === '1') { event.preventDefault(); return; }   // 已經送出了：忽略第二次
        if (form.dataset.guardDraftKey) markPending(form.dataset.guardDraftKey);
        form.dataset.guardLeaveOk = '1';   // 送出時換頁不要跳「離開提醒」
        busy(form, event.submitter);
    }, false);
    // 按「上一頁」回到這個頁面（瀏覽器快取）時，按鈕要還原。
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) document.querySelectorAll('form[data-guard-submitting]').forEach(function (f) { delete f.dataset.guardLeaveOk; release(f); });
    });

    // ---------- 2、3. 離開提醒與暫存 ----------
    function guardable(form) {
        if (!isPost(form) || form.hasAttribute('data-no-guard') || hasPassword(form)) return false;
        return !!form.querySelector('textarea, input:not([type=hidden]):not([type=submit]):not([type=button]):not([type=file]):not([type=checkbox]):not([type=radio]):not([type=password]), select');
    }
    function draftable(el) {
        if (!el.name || el.disabled || el.readOnly || el.closest('[data-no-draft]')) return false;
        if (el.getAttribute('role') === 'combobox') return false;
        var t = (el.type || '').toLowerCase();
        return !(t === 'hidden' || t === 'password' || t === 'file' || t === 'submit' || t === 'button' || t === 'reset' || el.name === '_token' || el.name === '_method');
    }
    function fieldsOf(form) { return Array.prototype.filter.call(form.elements, draftable); }
    function snapshot(form) {
        var data = {};
        fieldsOf(form).forEach(function (el) {
            var t = (el.type || '').toLowerCase();
            if (t === 'checkbox') { (data[el.name] = data[el.name] || {})[el.value] = el.checked; }
            else if (t === 'radio') { if (el.checked) data[el.name] = el.value; }
            else data[el.name] = el.value;
        });
        return data;
    }
    function same(a, b) { return JSON.stringify(a) === JSON.stringify(b); }
    function keyFor(form, index) { return 'fg:v1:' + (USER === null ? 'guest' : USER) + ':' + location.pathname + ':' + index; }
    function readDraft(key) {
        try {
            var d = JSON.parse(localStorage.getItem(key) || 'null');
            if (d && Date.now() - d.t < TTL) return d;
            localStorage.removeItem(key);
        } catch (e) { /* 這台瀏覽器不能存設定就算了 */ }
        return null;
    }
    function markPending(key) { try { var p = JSON.parse(sessionStorage.getItem(PENDING_KEY) || '[]'); if (p.indexOf(key) === -1) p.push(key); sessionStorage.setItem(PENDING_KEY, JSON.stringify(p)); } catch (e) {} }
    // 上一頁送出的表單：這一頁有「成功」訊息就代表已經存好，把暫存清掉；
    // 有驗證錯誤（導回同一個表單）、錯誤頁（沒有這支程式）都保留暫存。
    function settlePending() {
        try {
            var p = JSON.parse(sessionStorage.getItem(PENDING_KEY) || '[]');
            if (!p.length) return;
            if (document.querySelector('.alert-success')) { p.forEach(function (k) { localStorage.removeItem(k); }); sessionStorage.removeItem(PENDING_KEY); }
        } catch (e) {}
    }
    function apply(form, data) {
        fieldsOf(form).forEach(function (el) {
            if (!(el.name in data)) return;
            var t = (el.type || '').toLowerCase(), v = data[el.name];
            if (t === 'checkbox') el.checked = !!(v && v[el.value]);
            else if (t === 'radio') el.checked = (v === el.value);
            else el.value = v;
        });
    }
    function banner(form, when, clear) {
        var box = document.createElement('div');
        box.className = 'alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 small';
        box.setAttribute('role', 'status');
        var d = new Date(when);
        var time = (d.getMonth() + 1) + '/' + d.getDate() + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        box.innerHTML = '<span><i class="bi bi-clock-history me-1"></i>已還原你上次沒送出的內容（' + time + '）。</span>';
        var btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'btn btn-outline-secondary btn-sm'; btn.textContent = '清除，重新填寫';
        btn.addEventListener('click', function () { clear(); box.remove(); });
        box.appendChild(btn);
        form.parentNode.insertBefore(box, form);
    }

    var dirtyForms = [];
    function init() {
        settlePending();
        var forms = Array.prototype.filter.call(document.forms, guardable);
        forms.forEach(function (form, index) {
            var key = keyFor(form, index);
            form.dataset.guardDraftKey = key;
            var hasErrors = !!document.querySelector('.is-invalid, .invalid-feedback.d-block');   // 驗證失敗導回來：欄位已經由伺服器帶回，不要蓋掉
            var draft = readDraft(key);
            var restored = false;
            if (draft && !hasErrors && !form.hasAttribute('data-no-draft')) {
                var before = snapshot(form);
                apply(form, draft.v);
                restored = !same(before, snapshot(form));
                if (restored) banner(form, draft.t, function () { apply(form, before); try { localStorage.removeItem(key); } catch (e) {} initial = before; });
            }
            var initial = snapshot(form);
            if (restored) { initial = snapshot(form); }
            var timer = null;
            function onEdit() {
                var now = snapshot(form);
                var isDirty = !same(now, initial);
                form.dataset.guardDirty = isDirty ? '1' : '';
                if (form.hasAttribute('data-no-draft')) return;
                clearTimeout(timer);
                timer = setTimeout(function () {
                    try {
                        if (isDirty) localStorage.setItem(key, JSON.stringify({ t: Date.now(), v: now }));
                        else localStorage.removeItem(key);
                    } catch (e) { /* 空間不足或不能存就算了 */ }
                }, 400);
            }
            form.addEventListener('input', onEdit);
            form.addEventListener('change', onEdit);
            dirtyForms.push(form);
        });
    }
    // 離開提醒：有任何表單改過、而且不是剛按送出，才跳出瀏覽器的確認。
    window.addEventListener('beforeunload', function (event) {
        var dirty = dirtyForms.some(function (f) { return f.dataset.guardDirty === '1' && f.dataset.guardLeaveOk !== '1'; });
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 0); });
    else setTimeout(init, 0);
})();
</script>

{{-- 必填欄位（全站共用，app 版型引入）：
     1. 欄位有 required 屬性 → 它的欄位名稱（label）後面自動加紅色星號「*」（已經有星號或寫了「必填」的不重複加）。
     2. 送出時漏填 → 不跳瀏覽器預設的小提示泡泡，改成欄位紅框，下面顯示「此欄位為必填」，並捲到第一個漏填的欄位；
        開始填寫（或選擇）後紅框和提示自動消失。其他格式錯誤（例如長度、格式不符）仍照瀏覽器原本的提示。
     要把欄位設成必填，只要在 <input>／<select>／<textarea> 加上 required，畫面上的星號與漏填提示就會自動套用。 --}}
<style>
    .required-mark { color: var(--bs-danger, #dc3545); margin-left: .15rem; }
</style>
<script>
(function () {
    var MESSAGE = '此欄位為必填';

    // 找到欄位對應的名稱：先找 <label for="id">；單選／勾選框找它所在區塊的 .form-label；再找同一個區塊裡的 .form-label。
    function labelFor(field) {
        if (field.type === 'radio' || field.type === 'checkbox') {
            var group = field.closest('.mb-3, .mb-4, .col, [class*="col-"], fieldset');
            var groupLabel = group && group.querySelector('.form-label, legend');
            if (groupLabel) return groupLabel;
        }
        if (field.id) {
            var byFor = document.querySelector('label[for="' + CSS.escape(field.id) + '"]');
            if (byFor) return byFor;
        }
        var wrap = field.closest('.mb-3, .mb-4, .col, [class*="col-"]');
        return wrap ? wrap.querySelector('.form-label') : null;
    }

    function markLabels(root) {
        (root || document).querySelectorAll('input[required], select[required], textarea[required]').forEach(function (field) {
            if (field.type === 'hidden') return;
            var label = labelFor(field);
            if (!label || label.querySelector('.required-mark')) return;
            var text = label.textContent || '';
            if (/\*\s*$/.test(text.trim()) || text.indexOf('必填') !== -1 || label.querySelector('.text-danger')) return;
            var star = document.createElement('span');
            star.className = 'required-mark';
            star.setAttribute('aria-hidden', 'true');
            star.textContent = '*';
            label.appendChild(star);
        });
    }

    // 提示放在哪裡：單選／勾選框放在整組下面；在 input-group（例如掃描按鈕）裡的放在整組下面；其他放在欄位下面。
    function messageAnchor(field) {
        if (field.type === 'radio' || field.type === 'checkbox') {
            return field.closest('.mb-3, .mb-4, .col, [class*="col-"], fieldset') || field.parentElement;
        }
        return field.closest('.input-group') || field;
    }

    function showMissing(field) {
        var anchor = messageAnchor(field);
        var grouped = anchor !== field && (field.type === 'radio' || field.type === 'checkbox');
        field.classList.add('is-invalid');
        if (field.name && grouped) {
            anchor.querySelectorAll('input[name="' + CSS.escape(field.name) + '"]').forEach(function (f) { f.classList.add('is-invalid'); });
        }
        var existing = grouped ? anchor.querySelector(':scope > .required-feedback') : (anchor.nextElementSibling && anchor.nextElementSibling.classList.contains('required-feedback') ? anchor.nextElementSibling : null);
        if (existing) return;
        var msg = document.createElement('div');
        msg.className = 'invalid-feedback d-block required-feedback';
        msg.textContent = MESSAGE;
        if (grouped) anchor.appendChild(msg); else anchor.insertAdjacentElement('afterend', msg);
    }

    function clearMissing(field) {
        if (!field.classList || !field.classList.contains('is-invalid')) return;
        if (field.validity && field.validity.valueMissing) return;
        var anchor = messageAnchor(field);
        var grouped = anchor !== field && (field.type === 'radio' || field.type === 'checkbox');
        var msg = grouped ? anchor.querySelector(':scope > .required-feedback') : (anchor.nextElementSibling && anchor.nextElementSibling.classList.contains('required-feedback') ? anchor.nextElementSibling : null);
        if (!msg) return;   // 不是這裡加的紅框（例如後端驗證的錯誤），保留給原本的程式處理
        msg.remove();
        field.classList.remove('is-invalid');
        if (field.name && grouped) {
            anchor.querySelectorAll('input[name="' + CSS.escape(field.name) + '"]').forEach(function (f) { f.classList.remove('is-invalid'); });
        }
    }

    var firstInvalid = null;
    document.addEventListener('invalid', function (event) {
        var field = event.target;
        if (!field.validity || !field.validity.valueMissing) return;   // 只接手「漏填」，其他錯誤照瀏覽器原本的提示
        event.preventDefault();
        showMissing(field);
        if (!firstInvalid) {
            firstInvalid = field;
            setTimeout(function () {
                var target = firstInvalid;
                firstInvalid = null;
                if (target && target.offsetParent !== null) {
                    target.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    try { target.focus({ preventScroll: true }); } catch (e) { target.focus(); }
                }
            }, 0);
        }
    }, true);

    ['input', 'change'].forEach(function (type) {
        document.addEventListener(type, function (event) { clearMissing(event.target); }, true);
    });

    // 上一次送出有附件、但驗證失敗退回來：瀏覽器不能把剛選的檔案自動填回去，在每個附件欄位下面提醒要重新選擇。
    var uploadsDiscarded = @json((bool) session('uploads_discarded'));
    function warnDiscardedUploads() {
        if (!uploadsDiscarded) return;
        document.querySelectorAll('.app-content input[type="file"]').forEach(function (input) {
            var anchor = input.closest('.input-group') || input;
            if (anchor.parentElement.querySelector('.upload-discarded-hint')) return;
            var hint = document.createElement('div');
            hint.className = 'form-text text-danger upload-discarded-hint';
            hint.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>上面有欄位需要修正，剛剛選的附件沒有保留，請重新選擇檔案。';
            anchor.insertAdjacentElement('afterend', hint);
        });
    }

    document.addEventListener('DOMContentLoaded', function () { markLabels(); warnDiscardedUploads(); });
    window.tbMarkRequiredLabels = markLabels;   // 動態加進來的欄位可以再呼叫一次
})();
</script>

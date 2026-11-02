{{--
    零件編號欄位（新增與修改共用）。
    - 新增：可以自己填；留空就自動產生 PART-順序數字。修改：必填，可以改成別的編號。
    - 輸入時會即時檢查有沒有和其他零件重複；重複時不能送出。送出時伺服器還會再檢查一次。
    參數：$part（修改時是零件，新增時是 null）、$nextCode（新增時的下一個自動編號）。
--}}
<div class="mb-3">
    <label for="part_code" class="form-label">
        零件編號 @if ($part)<span class="text-danger">*</span>@endif
    </label>
    <input type="text" id="part_code" name="part_code" maxlength="20" autocomplete="off"
        pattern="[A-Za-z0-9._\-]+" @required($part)
        class="form-control @error('part_code') is-invalid @enderror"
        value="{{ old('part_code', $part?->part_code) }}"
        placeholder="{{ $part ? '' : '留空會自動產生 ' . $nextCode }}"
        data-check-url="{{ route('parts.check-code') }}" data-ignore="{{ $part?->id }}">
    <div id="part_code_feedback" class="small mt-1" aria-live="polite"></div>
    @error('part_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    <div class="form-text">
        英文字母、數字、「-」、「_」、「.」，最多 20 個字，不能和其他零件重複（大小寫視為相同）。
        同一種零件的不同規格（例如 HDMI 線 2 公尺、5 公尺）請分開新增，各用一個編號。
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('part_code');
    const feedback = document.getElementById('part_code_feedback');
    const form = input.closest('form');
    let timer = null;
    let lastChecked = null;   // { code, available }

    function show(available, message) {
        feedback.textContent = message;
        feedback.className = 'small mt-1 ' + (available ? 'text-success' : 'text-danger');
        input.classList.toggle('is-invalid', !available);
        input.setCustomValidity(available ? '' : message);
    }

    async function check() {
        const code = input.value.trim();
        const url = new URL(input.dataset.checkUrl, window.location.origin);
        url.searchParams.set('code', code);
        if (input.dataset.ignore) { url.searchParams.set('ignore', input.dataset.ignore); }

        try {
            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const result = await response.json();
            if (input.value.trim() !== code) { return null; }   // 等待期間又改了字，以最新的為準
            lastChecked = { code: code, available: result.available };
            show(result.available, result.message);
            return result.available;
        } catch (error) {
            feedback.textContent = '';   // 連線失敗就交給送出時的伺服器檢查
            input.setCustomValidity('');
            return true;
        }
    }

    input.addEventListener('input', function () {
        input.setCustomValidity('');
        feedback.textContent = '';
        clearTimeout(timer);
        timer = setTimeout(check, 400);
    });

    // 送出前一定要確認過目前這個編號沒有重複。
    form.addEventListener('submit', async function (event) {
        const code = input.value.trim();
        if (lastChecked && lastChecked.code === code) {
            if (!lastChecked.available) { event.preventDefault(); input.reportValidity(); }
            return;
        }
        event.preventDefault();
        const available = await check();
        if (available === false) { input.reportValidity(); return; }
        form.requestSubmit();   // 再送一次：這次編號已確認過，並會照常檢查其他必填欄位
    });
});
</script>

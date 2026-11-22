{{-- 新增、編輯設備共用的表單欄位（檔名開頭的底線代表「被別的檔案引入的片段」）。 --}}
{{-- 被 devices/create.blade.php 與 devices/edit.blade.php 用 @include 引入；編輯時會多一個 $device 變數帶入目前資料。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 表單防偽 token（CSRF）：Laravel 要求所有 POST 表單都要有。 --}}
@csrf
{{-- @isset：有傳 $device 進來（= 編輯）才加上 @method('PUT')，把 POST 偽裝成 PUT。 --}}
@isset($device)
    @method('PUT')
@endisset

<div class="card shadow-sm">
<div class="card-header bg-white form-section-title"><i class="bi bi-pc-display me-2"></i>設備資料</div>
<div class="card-body p-4">
{{-- 基本資料、規格與保固、狀態放在同一頁（不分上一步／下一步），依序往下排。 --}}
<div class="row g-3">
    <div class="col-md-6">
        <label for="device_code" class="form-label">{{ __('devices.form.code') }}</label>
        {{-- 設備編號：整個系統不可重複，QR Code 網址就是用它。old(欄位, 預設值) 會在驗證失敗導回時帶回剛輸入的內容。 --}}
        {{-- 輸入框右邊的相機按鈕：掃 QR 或條碼，內容直接填進這個欄位（見 scanner/_camera）。 --}}
        <div class="input-group">
            <input type="text" id="device_code" name="device_code" class="form-control @error('device_code') is-invalid @enderror" value="{{ old('device_code', $device->device_code ?? '') }}" required
                autocomplete="off" data-check-url="{{ route('devices.check-code') }}" data-ignore="{{ $device->id ?? '' }}">
            <button type="button" class="btn btn-outline-primary" data-scan-target="#device_code" data-scan-extract="device-code"
                data-scan-title="{{ __('scanner.button') }}：{{ __('devices.form.code') }}"
                title="{{ __('scanner.button') }}" aria-label="{{ __('scanner.button') }}"><i class="bi bi-upc-scan"></i></button>
        </div>
        {{-- 規則說明一直顯示；輸入時即時檢查格式與重複（跟零件編號一樣）：結果顯示在說明下面；有問題時不能送出，送出時伺服器還會再檢查一次。 --}}
        <div class="form-text">{{ \App\Support\EquipmentCode::RULE_TEXT }}</div>
        <div id="device_code_feedback" class="small mt-1" aria-live="polite"></div>
        @error('device_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="asset_code" class="form-label">{{ __('devices.form.asset_code') }}</label>
        {{-- 資產編號（選填，學校財產編號）。 --}}
        {{-- 輸入框右邊的相機按鈕：掃 QR 或條碼，內容直接填進這個欄位（見 scanner/_camera）。 --}}
        <div class="input-group">
            <input type="text" id="asset_code" name="asset_code" class="form-control @error('asset_code') is-invalid @enderror" value="{{ old('asset_code', $device->asset_code ?? '') }}">
            <button type="button" class="btn btn-outline-primary" data-scan-target="#asset_code"
                data-scan-title="{{ __('scanner.button') }}：{{ __('devices.form.asset_code') }}"
                title="{{ __('scanner.button') }}" aria-label="{{ __('scanner.button') }}"><i class="bi bi-upc-scan"></i></button>
        </div>
    </div>
    <div class="col-md-6">
        <label for="device_category_id" class="form-label">{{ __('devices.form.category') }}</label>
        {{-- 類別下拉選單（必選）。 --}}
        <select id="device_category_id" name="device_category_id" class="form-select @error('device_category_id') is-invalid @enderror" required>
            <option value="">{{ __('devices.form.please_select') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('device_category_id', $device->device_category_id ?? '') == $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label for="classroom_id" class="form-label">{{ __('devices.form.classroom') }}</label>
        {{-- 所在教室下拉選單（必選），顯示「教室代碼 - 教室名稱」。 --}}
        <select id="classroom_id" name="classroom_id" class="form-select @error('classroom_id') is-invalid @enderror" required>
            <option value="">{{ __('devices.form.please_select') }}</option>
            @foreach ($classrooms as $classroom)
                <option value="{{ $classroom->id }}" @selected(old('classroom_id', $device->classroom_id ?? '') == $classroom->id)>
                    {{ $classroom->classroom_code }} - {{ $classroom->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label for="brand" class="form-label">{{ __('devices.form.brand') }}</label>
        {{-- 品牌（選填）。 --}}
        <input type="text" id="brand" name="brand" class="form-control @error('brand') is-invalid @enderror" value="{{ old('brand', $device->brand ?? '') }}">
    </div>
    <div class="col-md-4">
        <label for="model" class="form-label">{{ __('devices.form.model') }}</label>
        {{-- 型號（選填）。 --}}
        <input type="text" id="model" name="model" class="form-control @error('model') is-invalid @enderror" value="{{ old('model', $device->model ?? '') }}">
    </div>
    <div class="col-md-4">
        <label for="serial_number" class="form-label">{{ __('devices.form.serial_number') }}</label>
        {{-- 序號（選填）。 --}}
        {{-- 輸入框右邊的相機按鈕：掃 QR 或條碼，內容直接填進這個欄位（見 scanner/_camera）。 --}}
        <div class="input-group">
            <input type="text" id="serial_number" name="serial_number" class="form-control @error('serial_number') is-invalid @enderror" value="{{ old('serial_number', $device->serial_number ?? '') }}">
            <button type="button" class="btn btn-outline-primary" data-scan-target="#serial_number"
                data-scan-title="{{ __('scanner.button') }}：{{ __('devices.form.serial_number') }}"
                title="{{ __('scanner.button') }}" aria-label="{{ __('scanner.button') }}"><i class="bi bi-upc-scan"></i></button>
        </div>
    </div>
    <div class="col-md-4">
        <label for="warranty_end_date" class="form-label">{{ __('devices.form.warranty_end_date') }}</label>
        {{-- 保固期限（日期選擇器）；optional(...) 是「沒有資料也不要報錯」。 --}}
        <input type="date" id="warranty_end_date" name="warranty_end_date" class="form-control @error('warranty_end_date') is-invalid @enderror" value="{{ old('warranty_end_date', optional($device->warranty_end_date ?? null)->format('Y-m-d')) }}">
    </div>

    <div class="col-md-4">
        <label for="status" class="form-label">{{ __('devices.form.status') }}</label>
        {{-- 狀態下拉選單：選項來自 Device::STATUSES（正常、已淘汰、停用；「待維修」由報修單自動判斷，不能手動選）。
             目前是「待維修」（有已派工、待驗收的報修單）的設備：不能直接改成正常，只能維持待維修，或改成已淘汰、停用。
             已淘汰／停用的設備要改回正常時，送出會檢查有沒有沒結案的報修單（新報修也算），有就擋下。 --}}
        @php $awaitingRepair = isset($device) && $device->exists && $device->isAwaitingRepair(); @endphp
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach (\App\Models\Device::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $device->status ?? 'normal') == $status)>{{ $awaitingRepair && $status === 'normal' ? \App\Models\Device::statusLabel('repairing') : \App\Models\Device::statusLabel($status) }}</option>
            @endforeach
        </select>
        <div class="form-text">已淘汰是定案：存檔後設備資料鎖住，不能再修改、停用（不小心淘汰時，請系統管理員在設備詳細按「取消淘汰」）。</div>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
</div>
</div>


{{-- 表單底部的「取消／儲存」按鈕（和用戶、部門主檔的表單一致）：在外框下面、靠右。 --}}
<div class="d-flex justify-content-end gap-2 mt-3">
    <a class="btn btn-outline-secondary" href="{{ route('devices.index') }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.cancel') }}</a>
    <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>{{ __('common.buttons.save') }}</button>
</div>


{{-- 設備編號即時檢查（跟零件編號 parts/_code-field 同一套做法）：停止輸入 0.4 秒後問伺服器有沒有重複；
     掃描器填入編號時也會觸發 input 事件，一樣會檢查。送出前一定要確認過目前的編號沒有重複。 --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('device_code');
    const feedback = document.getElementById('device_code_feedback');
    if (!input || !feedback) { return; }
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
        if (code === '') { feedback.textContent = ''; input.classList.remove('is-invalid'); input.setCustomValidity(''); lastChecked = null; return null; }
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
        input.classList.remove('is-invalid');
        clearTimeout(timer);
        timer = setTimeout(check, 400);
    });

    form.addEventListener('submit', async function (event) {
        const code = input.value.trim();
        if (code === '') { return; }   // 空白交給 required 處理
        if (lastChecked && lastChecked.code === code) {
            if (!lastChecked.available) { event.preventDefault(); input.reportValidity(); }
            return;
        }
        event.preventDefault();
        const available = await check();
        if (available === false) { input.reportValidity(); return; }
        form.requestSubmit();
    });
});
</script>
{{-- 把設備改成「已淘汰」是定案、無法復原：送出前先跳出確認視窗，列出會一併中止的進行中項目。 --}}
@if (isset($device) && $device->exists && $device->status !== 'retired')
    <script>
        (function () {
            var select = document.getElementById('status');
            var form = select && select.form;
            if (!form) return;
            var impact = @json($retireImpact ?? ['repairs' => 0, 'orders' => 0, 'plans' => 0]);
            var attrs = ['data-confirm', 'data-confirm-title', 'data-confirm-ok', 'data-confirm-detail'];
            function sync() {
                if (select.value === 'retired') {
                    form.setAttribute('data-confirm', '確定要把這台設備改成「已淘汰」嗎？淘汰後設備資料會鎖住，不能再編輯、報修或建立保養工單（不小心淘汰時，只有系統管理員能「取消淘汰」）。這台設備進行中的項目會一併中止。');
                    form.setAttribute('data-confirm-title', '確認淘汰設備');
                    form.setAttribute('data-confirm-ok', '確認淘汰');
                    form.setAttribute('data-confirm-detail', JSON.stringify([
                        ['設備', @json($device->device_code)],
                        ['進行中的報修單', impact.repairs + ' 張（中止）'],
                        ['未完成的保養工單', impact.orders + ' 張（中止）'],
                        ['啟用中的保養計畫', impact.plans + ' 份（停用）']
                    ]));
                } else {
                    attrs.forEach(function (a) { form.removeAttribute(a); });
                }
            }
            select.addEventListener('change', sync);
            sync();
        })();
    </script>
@endif

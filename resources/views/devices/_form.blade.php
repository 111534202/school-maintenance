{{-- 新增、編輯設備共用的表單欄位（檔名開頭的底線代表「被別的檔案引入的片段」）。 --}}
{{-- 被 devices/create.blade.php 與 devices/edit.blade.php 用 @include 引入；編輯時會多一個 $device 變數帶入目前資料。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 表單防偽 token（CSRF）：Laravel 要求所有 POST 表單都要有。 --}}
@csrf
{{-- @isset：有傳 $device 進來（= 編輯）才加上 @method('PUT')，把 POST 偽裝成 PUT。 --}}
@isset($device)
    @method('PUT')
@endisset

<div class="row g-3">
    <div class="col-md-6">
        <label for="device_code" class="form-label">{{ __('devices.form.code') }}</label>
        {{-- 設備編號：整個系統不可重複，QR Code 網址就是用它。old(欄位, 預設值) 會在驗證失敗導回時帶回剛輸入的內容。 --}}
        <input type="text" id="device_code" name="device_code" class="form-control @error('device_code') is-invalid @enderror" value="{{ old('device_code', $device->device_code ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label for="asset_code" class="form-label">{{ __('devices.form.asset_code') }}</label>
        {{-- 資產編號（選填，學校財產編號）。 --}}
        <input type="text" id="asset_code" name="asset_code" class="form-control @error('asset_code') is-invalid @enderror" value="{{ old('asset_code', $device->asset_code ?? '') }}">
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
                    {{ $classroom->room_code }} - {{ $classroom->room_name }}
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
        <input type="text" id="serial_number" name="serial_number" class="form-control @error('serial_number') is-invalid @enderror" value="{{ old('serial_number', $device->serial_number ?? '') }}">
    </div>
    <div class="col-md-4">
        <label for="warranty_until" class="form-label">{{ __('devices.form.warranty_until') }}</label>
        {{-- 保固期限（日期選擇器）；optional(...) 是「沒有資料也不要報錯」。 --}}
        <input type="date" id="warranty_until" name="warranty_until" class="form-control @error('warranty_until') is-invalid @enderror" value="{{ old('warranty_until', optional($device->warranty_until ?? null)->format('Y-m-d')) }}">
    </div>
    <div class="col-md-4">
        <label for="status" class="form-label">{{ __('devices.form.status') }}</label>
        {{-- 狀態下拉選單：選項來自 Device::STATUSES，顯示名稱用 Device::statusLabel() 轉成中文。 --}}
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach (\App\Models\Device::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $device->status ?? 'normal') == $status)>{{ \App\Models\Device::statusLabel($status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check">
            {{-- 「核心設備」核取方塊：勾選代表這台設備故障時會讓教室被標示為異常。 --}}
            <input type="checkbox" name="is_core" value="1" class="form-check-input" id="is_core"
                   @checked(old('is_core', $device->is_core ?? false))>
            <label class="form-check-label" for="is_core">{{ __('devices.form.is_core') }}</label>
        </div>
    </div>
</div>

{{-- 表單底部的「取消／儲存」按鈕（和用戶、部門主檔的表單一致）。 --}}
<div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-outline-secondary" href="{{ route('devices.index') }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.cancel') }}</a>
    <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>{{ __('common.buttons.save') }}</button>
</div>

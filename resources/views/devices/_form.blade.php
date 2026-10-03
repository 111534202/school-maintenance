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
        <label class="form-label">設備編號</label>
        {{-- 設備編號：整個系統不可重複，QR Code 網址就是用它。old(欄位, 預設值) 會在驗證失敗導回時帶回剛輸入的內容。 --}}
        <input type="text" name="device_code" class="form-control" value="{{ old('device_code', $device->device_code ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">資產編號</label>
        {{-- 資產編號（選填，學校財產編號）。 --}}
        <input type="text" name="asset_code" class="form-control" value="{{ old('asset_code', $device->asset_code ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">類別</label>
        {{-- 類別下拉選單（必選）。 --}}
        <select name="device_category_id" class="form-select" required>
            <option value="">－ 請選擇 －</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('device_category_id', $device->device_category_id ?? '') == $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">所在教室</label>
        {{-- 所在教室下拉選單（必選），顯示「教室代碼 - 教室名稱」。 --}}
        <select name="classroom_id" class="form-select" required>
            <option value="">－ 請選擇 －</option>
            @foreach ($classrooms as $classroom)
                <option value="{{ $classroom->id }}" @selected(old('classroom_id', $device->classroom_id ?? '') == $classroom->id)>
                    {{ $classroom->room_code }} - {{ $classroom->room_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">品牌</label>
        {{-- 品牌（選填）。 --}}
        <input type="text" name="brand" class="form-control" value="{{ old('brand', $device->brand ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">型號</label>
        {{-- 型號（選填）。 --}}
        <input type="text" name="model" class="form-control" value="{{ old('model', $device->model ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">序號</label>
        {{-- 序號（選填）。 --}}
        <input type="text" name="serial_number" class="form-control" value="{{ old('serial_number', $device->serial_number ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">保固期限</label>
        {{-- 保固期限（日期選擇器）；optional(...) 是「沒有資料也不要報錯」。 --}}
        <input type="date" name="warranty_until" class="form-control" value="{{ old('warranty_until', optional($device->warranty_until ?? null)->format('Y-m-d')) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">狀態</label>
        {{-- 狀態下拉選單：選項來自 Device::STATUSES，顯示名稱用 Device::statusLabel() 轉成中文。 --}}
        <select name="status" class="form-select" required>
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
            <label class="form-check-label" for="is_core">核心設備</label>
        </div>
    </div>
</div>

{{-- 表單底部的「儲存／取消」按鈕。 --}}
<div class="mt-4">
    <button type="submit" class="btn btn-primary">儲存</button>
    <a href="{{ route('devices.index') }}" class="btn btn-outline-secondary">取消</a>
</div>

{{-- 新增、編輯教室共用的表單欄位（檔名開頭的底線代表「被別的檔案引入的片段」）。 --}}
{{-- 被 classrooms/create.blade.php 與 classrooms/edit.blade.php 用 @include 引入；編輯時會多一個 $classroom 變數帶入目前資料。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 表單防偽 token（CSRF）：Laravel 要求所有 POST 表單都要有。 --}}
@csrf
{{-- @isset：有傳 $classroom 進來（= 編輯）才加上 @method('PUT')，把 POST 偽裝成 PUT。 --}}
@isset($classroom)
    @method('PUT')
@endisset

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">所屬部門</label>
        {{-- 部門下拉選單：選項來自部門主檔（只列啟用中的，但會保留這間教室目前已選的部門）。 --}}
        <select name="department_id" class="form-select">
            <option value="">－ 未指定 －</option>
            @foreach ($departments as $department)
                {{-- old(欄位, 預設值)：驗證失敗導回時帶回剛剛選的；否則顯示目前資料；新增時沒有 $classroom，用 ?? '' 避免報錯。 --}}
                <option value="{{ $department->id }}" @selected(old('department_id', $classroom->department_id ?? '') == $department->id)>
                    {{ $department->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">管理人</label>
        {{-- 管理人下拉選單：列出所有用戶。 --}}
        <select name="manager_id" class="form-select">
            <option value="">－ 未指定 －</option>
            @foreach ($managers as $manager)
                <option value="{{ $manager->id }}" @selected(old('manager_id', $classroom->manager_id ?? '') == $manager->id)>
                    {{ $manager->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">校區</label>
        {{-- 校區（必填）。 --}}
        <input type="text" name="campus" class="form-control" value="{{ old('campus', $classroom->campus ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">大樓</label>
        {{-- 大樓（必填）。 --}}
        <input type="text" name="building" class="form-control" value="{{ old('building', $classroom->building ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">樓層</label>
        {{-- 樓層（必填）。 --}}
        <input type="text" name="floor" class="form-control" value="{{ old('floor', $classroom->floor ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">教室代碼</label>
        {{-- 教室代碼：整個系統不可重複（後端驗證）。 --}}
        <input type="text" name="room_code" class="form-control" value="{{ old('room_code', $classroom->room_code ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">教室名稱</label>
        {{-- 教室名稱（必填）。 --}}
        <input type="text" name="room_name" class="form-control" value="{{ old('room_name', $classroom->room_name ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">教室類型</label>
        {{-- 教室類型（選填，例如電腦教室、一般教室）。 --}}
        <input type="text" name="room_type" class="form-control" value="{{ old('room_type', $classroom->room_type ?? '') }}">
    </div>
</div>

{{-- 表單底部的「儲存／取消」按鈕。 --}}
<div class="mt-4">
    <button type="submit" class="btn btn-primary">儲存</button>
    <a href="{{ route('classrooms.index') }}" class="btn btn-outline-secondary">取消</a>
</div>

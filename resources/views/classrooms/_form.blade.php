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
        <label for="department_id" class="form-label">{{ __('classrooms.form.department') }}</label>
        {{-- 部門下拉選單：選項來自部門主檔（只列啟用中的，但會保留這間教室目前已選的部門）。 --}}
        <select id="department_id" name="department_id" class="form-select @error('department_id') is-invalid @enderror">
            <option value="">{{ __('classrooms.form.unassigned') }}</option>
            @foreach ($departments as $department)
                {{-- old(欄位, 預設值)：驗證失敗導回時帶回剛剛選的；否則顯示目前資料；新增時沒有 $classroom，用 ?? '' 避免報錯。 --}}
                <option value="{{ $department->id }}" @selected(old('department_id', $classroom->department_id ?? '') == $department->id)>
                    {{ $department->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label for="manager_id" class="form-label">{{ __('classrooms.form.manager') }}</label>
        {{-- 管理人下拉選單：列出所有用戶。 --}}
        <select id="manager_id" name="manager_id" class="form-select @error('manager_id') is-invalid @enderror">
            <option value="">{{ __('classrooms.form.unassigned') }}</option>
            @foreach ($managers as $manager)
                <option value="{{ $manager->id }}" @selected(old('manager_id', $classroom->manager_id ?? '') == $manager->id)>
                    {{ $manager->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label for="campus" class="form-label">{{ __('classrooms.form.campus') }}</label>
        {{-- 校區（必填）。 --}}
        <input type="text" id="campus" name="campus" class="form-control @error('campus') is-invalid @enderror" value="{{ old('campus', $classroom->campus ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label for="building" class="form-label">{{ __('classrooms.form.building') }}</label>
        {{-- 大樓（必填）。 --}}
        <input type="text" id="building" name="building" class="form-control @error('building') is-invalid @enderror" value="{{ old('building', $classroom->building ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label for="floor" class="form-label">{{ __('classrooms.form.floor') }}</label>
        {{-- 樓層（必填）。 --}}
        <input type="text" id="floor" name="floor" class="form-control @error('floor') is-invalid @enderror" value="{{ old('floor', $classroom->floor ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label for="room_code" class="form-label">{{ __('classrooms.form.room_code') }}</label>
        {{-- 教室代碼：整個系統不可重複（後端驗證）。 --}}
        <input type="text" id="room_code" name="room_code" class="form-control @error('room_code') is-invalid @enderror" value="{{ old('room_code', $classroom->room_code ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label for="room_name" class="form-label">{{ __('classrooms.form.room_name') }}</label>
        {{-- 教室名稱（必填）。 --}}
        <input type="text" id="room_name" name="room_name" class="form-control @error('room_name') is-invalid @enderror" value="{{ old('room_name', $classroom->room_name ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label for="room_type" class="form-label">{{ __('classrooms.form.room_type') }}</label>
        {{-- 教室類型（選填，例如電腦教室、一般教室）。 --}}
        <input type="text" id="room_type" name="room_type" class="form-control @error('room_type') is-invalid @enderror" value="{{ old('room_type', $classroom->room_type ?? '') }}">
    </div>
</div>

{{-- 表單底部的「取消／儲存」按鈕（和用戶、部門主檔的表單一致）。 --}}
<div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-outline-secondary" href="{{ route('classrooms.index') }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.cancel') }}</a>
    <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>{{ __('common.buttons.save') }}</button>
</div>

{{-- 新增、編輯部門共用的表單欄位（檔名開頭的底線代表「被別的檔案引入的片段」）。 --}}
{{-- 被 departments/create.blade.php 與 departments/edit.blade.php 用 @include 引入；編輯時會多一個 $department 變數帶入目前資料。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 沒有傳 $department 進來（= 新增）就當成 null。 --}}
@php($department = $department ?? null)

<div class="card shadow-sm">
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="code" class="form-label">{{ __('departments.form.code') }}</label>
                {{-- @error('欄位')：驗證失敗時讓框變紅；old('code', 預設值)：驗證失敗導回時帶回剛輸入的內容，否則顯示目前資料。 --}}
                <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code"
                    value="{{ old('code', $department->code ?? '') }}" autocomplete="off">
                <div class="form-text">{{ __('departments.form.code_hint') }}</div>
            </div>
            <div class="col-md-8">
                <label for="name" class="form-label">{{ __('departments.form.name') }}</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                    value="{{ old('name', $department->name ?? '') }}" required>
            </div>
            <div class="col-12">
                <label for="description" class="form-label">{{ __('departments.form.description') }}</label>
                <input type="text" class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                    value="{{ old('description', $department->description ?? '') }}">
            </div>
            <div class="col-12">
                {{-- 核取方塊沒勾時瀏覽器不會送出欄位，靠這個隱藏欄位確保一定送出 0（停用）或 1（啟用）。 --}}
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                        {{ old('is_active', $department->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">{{ __('departments.form.is_active') }}</label>
                </div>
            </div>
        </div>
    </div>
    {{-- 表單底部的「取消／儲存」按鈕。 --}}
    <div class="card-footer bg-white d-flex justify-content-end gap-2 py-3">
        <a class="btn btn-outline-secondary" href="{{ route('departments.index') }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.cancel') }}</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>{{ __('common.buttons.save') }}</button>
    </div>
</div>

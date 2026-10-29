{{-- 編輯設備類別頁（對應 DeviceCategoryController::edit）：只有一個「類別名稱」欄位。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('device_categories.form.edit_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-pencil-square me-2"></i>{{ __('device_categories.form.edit_title') }}</h1>
        <div class="card shadow-sm">
            <div class="card-body p-4">
                {{-- 表單送出到 PUT /device-categories/{id}（DeviceCategoryController::update）。 --}}
                <form method="POST" action="{{ route('device-categories.update', $category) }}">
                    {{-- 表單防偽 token（CSRF）：Laravel 要求所有 POST 表單都要有。 --}}
                    @csrf
                    {{-- 用隱藏欄位把 POST 偽裝成 PUT（Laravel 的更新慣例）。 --}}
                    @method('PUT')
                    <div class="mb-3">
                        <label for="name" class="form-label">{{ __('device_categories.form.name') }}</label>
                        {{-- old(欄位, 預設值)：驗證失敗導回時帶回剛輸入的；否則顯示目前的類別名稱。 --}}
                        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $category->name) }}" required autofocus>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('device-categories.index') }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.cancel') }}</a>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>{{ __('common.buttons.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

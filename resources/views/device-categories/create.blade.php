{{-- 新增設備類別頁（對應 DeviceCategoryController::create）：只有一個「類別名稱」欄位。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('device_categories.form.create_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-plus-circle me-2"></i>{{ __('device_categories.form.create_title') }}</h1>
        <div class="card shadow-sm">
            <div class="card-body p-4">
                {{-- 表單送出到 POST /device-categories（DeviceCategoryController::store）。 --}}
                <form method="POST" action="{{ route('device-categories.store') }}">
                    {{-- 表單防偽 token（CSRF）：Laravel 要求所有 POST 表單都要有。 --}}
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label">{{ __('device_categories.form.name') }}</label>
                        {{-- old('name')：驗證失敗導回時帶回剛輸入的名稱；autofocus：開頁面時游標自動停在這裡。 --}}
                        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autofocus>
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

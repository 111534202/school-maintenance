{{-- 新增設備類別頁（對應 DeviceCategoryController::create）：只有一個「類別名稱」欄位。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', '新增設備類別')

@section('content')
    <h3 class="mb-3">新增設備類別</h3>
    <div class="card p-4" style="max-width: 480px;">
        {{-- 表單送出到 POST /device-categories（DeviceCategoryController::store）。 --}}
        <form method="POST" action="{{ route('device-categories.store') }}">
            {{-- 表單防偽 token（CSRF）：Laravel 要求所有 POST 表單都要有。 --}}
            @csrf
            <div class="mb-3">
                <label class="form-label">類別名稱</label>
                {{-- old('name')：驗證失敗導回時帶回剛輸入的名稱；autofocus：開頁面時游標自動停在這裡。 --}}
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary">儲存</button>
            <a href="{{ route('device-categories.index') }}" class="btn btn-outline-secondary">取消</a>
        </form>
    </div>
@endsection

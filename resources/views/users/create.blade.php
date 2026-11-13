{{-- 新增用戶頁（對應 UserController::create），欄位在 users/_form.blade.php。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
@extends('layouts.app')

@section('title', __('users.form.create_title'))

@section('content')
    <div class="page-narrow">
        <h1 data-modal-hide class="tb-title mb-3">{{ __('users.form.create_title') }}</h1>

        {{-- 表單送出到 POST /users（UserController::store）。 --}}
        <form method="POST" action="{{ route('users.store') }}">
            @csrf
            {{-- 引入共用的欄位表單。 --}}
            @include('users._form')
        </form>
    </div>
@endsection

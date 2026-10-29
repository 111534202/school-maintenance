{{-- 新增頁（對應 DepartmentController::create），欄位內容在 departments/_form.blade.php。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面（導覽列、側邊選單都由它提供）。 --}}
@extends('layouts.app')

@section('title', __('departments.form.create_title'))

{{-- 從這裡開始是「放進主版面中間」的內容，到 @endsection 結束。 --}}
@section('content')
    {{-- 窄版容器：限制表單最大寬度，大螢幕上才不會被拉得太寬。 --}}
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-plus-circle me-2"></i>{{ __('departments.form.create_title') }}</h1>

        <form method="POST" action="{{ route('departments.store') }}">
            {{-- 表單防偽 token（CSRF）：Laravel 要求所有 POST 表單都要有，沒有會被擋下。 --}}
            @csrf
            {{-- 引入共用的欄位表單（新增與編輯長得一樣，只寫一份）。 --}}
            @include('departments._form')
        </form>
    </div>
@endsection

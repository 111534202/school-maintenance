{{-- 編輯頁（對應 DepartmentController::edit），欄位內容在 departments/_form.blade.php。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面（導覽列、側邊選單都由它提供）。 --}}
@extends('layouts.app')

@section('title', __('departments.form.edit_title'))

{{-- 從這裡開始是「放進主版面中間」的內容，到 @endsection 結束。 --}}
@section('content')
    {{-- 窄版容器：限制表單最大寬度，大螢幕上才不會被拉得太寬。 --}}
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-pencil-square me-2"></i>{{ __('departments.form.edit_title') }}</h1>

        <form method="POST" action="{{ route('departments.update', $department) }}">
            {{-- 表單防偽 token（CSRF）：Laravel 要求所有 POST 表單都要有，沒有會被擋下。 --}}
            @csrf
            {{-- 瀏覽器表單只能 GET/POST，用隱藏欄位把 POST 偽裝成 PUT（Laravel 的更新慣例）。 --}}
            @method('PUT')
            {{-- 引入共用的欄位表單（新增與編輯長得一樣，只寫一份）。 --}}
            @include('departments._form')
        </form>
    </div>
@endsection

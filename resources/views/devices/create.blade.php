{{-- 新增設備頁（對應 DeviceController::create），欄位在 devices/_form.blade.php（_form 內已含 @csrf）。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('devices.form.create_title'))

@section('content')
    <div class="page-narrow">
        <h1 data-modal-hide class="tb-title mb-3">{{ __('devices.form.create_title') }}</h1>
        {{-- 外框只包欄位（在 _form 裡），「取消／儲存」在外框下面、靠右。 --}}
        {{-- 表單送出到 POST /devices（DeviceController::store）。 --}}
        <form method="POST" action="{{ route('devices.store') }}">
            {{-- 引入共用的欄位表單。 --}}
            @include('devices._form')
        </form>
    </div>
    {{-- 相機掃描視窗（QR／條碼）：表單裡三個欄位的相機按鈕共用，放在表單外面。 --}}
    @include('scanner._camera')
@endsection

{{-- 新增設備頁（對應 DeviceController::create），欄位在 devices/_form.blade.php（_form 內已含 @csrf）。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('devices.form.create_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-plus-circle me-2"></i>{{ __('devices.form.create_title') }}</h1>
        <div class="card shadow-sm">
            <div class="card-body p-4">
                {{-- 表單送出到 POST /devices（DeviceController::store）。 --}}
                <form method="POST" action="{{ route('devices.store') }}">
                    {{-- 引入共用的欄位表單。 --}}
                    @include('devices._form')
                </form>
            </div>
        </div>
    </div>
@endsection

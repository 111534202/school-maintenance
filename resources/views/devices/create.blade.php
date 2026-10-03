{{-- 新增設備頁（對應 DeviceController::create），欄位在 devices/_form.blade.php（_form 內已含 @csrf）。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', '新增設備')

@section('content')
    <h3 class="mb-3">新增設備</h3>
    <div class="card p-4" style="max-width: 820px;">
        {{-- 表單送出到 POST /devices（DeviceController::store）。 --}}
        <form method="POST" action="{{ route('devices.store') }}">
            {{-- 引入共用的欄位表單。 --}}
            @include('devices._form')
        </form>
    </div>
@endsection

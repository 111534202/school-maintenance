{{-- 編輯設備頁（對應 DeviceController::edit），欄位在 devices/_form.blade.php（_form 內已含 @csrf 與 PUT 偽裝）。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', '編輯設備')

@section('content')
    <h3 class="mb-3">編輯設備</h3>
    <div class="card p-4" style="max-width: 820px;">
        {{-- 表單送出到 PUT /devices/{id}（DeviceController::update）。 --}}
        <form method="POST" action="{{ route('devices.update', $device) }}">
            {{-- 引入共用的欄位表單。 --}}
            @include('devices._form')
        </form>
    </div>
@endsection

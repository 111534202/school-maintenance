{{-- 新增教室頁（對應 ClassroomController::create），欄位在 classrooms/_form.blade.php（_form 內已含 @csrf）。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', '新增教室')

@section('content')
    <h3 class="mb-3">新增教室</h3>
    <div class="card p-4" style="max-width: 720px;">
        {{-- 表單送出到 POST /classrooms（ClassroomController::store）。 --}}
        <form method="POST" action="{{ route('classrooms.store') }}">
            {{-- 引入共用的欄位表單。 --}}
            @include('classrooms._form')
        </form>
    </div>
@endsection

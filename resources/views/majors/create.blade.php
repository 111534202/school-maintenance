{{-- 新增科系頁（對應 MajorController::create），欄位內容在 majors/_form.blade.php。 --}}
@extends('layouts.app')

@section('title', __('majors.form.create_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-plus-circle me-2"></i>{{ __('majors.form.create_title') }}</h1>

        <form method="POST" action="{{ route('majors.store') }}">
            @csrf
            @include('majors._form')
        </form>
    </div>
@endsection

{{-- 編輯科系頁（對應 MajorController::edit），欄位內容在 majors/_form.blade.php。 --}}
@extends('layouts.app')

@section('title', __('majors.form.edit_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-pencil-square me-2"></i>{{ __('majors.form.edit_title') }}</h1>

        <form method="POST" action="{{ route('majors.update', $major) }}">
            @csrf
            @method('PUT')
            @include('majors._form')
        </form>
    </div>
@endsection

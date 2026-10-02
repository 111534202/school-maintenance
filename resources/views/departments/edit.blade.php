@extends('layouts.app')

@section('title', __('departments.form.edit_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-pencil-square me-2"></i>{{ __('departments.form.edit_title') }}</h1>

        <form method="POST" action="{{ route('departments.update', $department) }}">
            @csrf
            @method('PUT')
            @include('departments._form')
        </form>
    </div>
@endsection

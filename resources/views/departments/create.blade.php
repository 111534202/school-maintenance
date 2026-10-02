@extends('layouts.app')

@section('title', __('departments.form.create_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-plus-circle me-2"></i>{{ __('departments.form.create_title') }}</h1>

        <form method="POST" action="{{ route('departments.store') }}">
            @csrf
            @include('departments._form')
        </form>
    </div>
@endsection

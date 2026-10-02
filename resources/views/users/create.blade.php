@extends('layouts.app')

@section('title', __('users.form.create_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-person-plus me-2"></i>{{ __('users.form.create_title') }}</h1>

        <form method="POST" action="{{ route('users.store') }}">
            @csrf
            @include('users._form')
        </form>
    </div>
@endsection

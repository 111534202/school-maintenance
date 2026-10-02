@extends('layouts.app')

@section('title', __('roles.form.create_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-plus-circle me-2"></i>{{ __('roles.form.create_title') }}</h1>

        <form method="POST" action="{{ route('roles.store') }}">
            @csrf
            @include('roles._form')
        </form>
    </div>
@endsection

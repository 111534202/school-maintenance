@extends('layouts.app')

@section('title', __('roles.form.edit_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-pencil-square me-2"></i>{{ __('roles.form.edit_title') }}</h1>

        <form method="POST" action="{{ route('roles.update', $role) }}">
            @csrf
            @method('PUT')
            @include('roles._form')
        </form>
    </div>
@endsection

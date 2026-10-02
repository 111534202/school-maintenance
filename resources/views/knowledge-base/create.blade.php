@extends('layouts.app')

@section('title', __('knowledge_base.create_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-plus-circle me-2"></i>{{ __('knowledge_base.create_title') }}</h1>

        <form method="POST" action="{{ route('knowledge-base.store') }}">
            @csrf
            @include('knowledge-base._form')
        </form>
    </div>
@endsection

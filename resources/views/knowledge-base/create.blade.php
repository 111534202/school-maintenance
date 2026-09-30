@extends('layouts.app')

@section('title', __('knowledge_base.create_title'))

@section('content')
    <h1>{{ __('knowledge_base.create_title') }}</h1>

    <form method="POST" action="{{ route('knowledge-base.store') }}">
        @csrf
        @include('knowledge-base._form')
    </form>
@endsection

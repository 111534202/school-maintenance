@extends('layouts.app')

@section('title', __('knowledge_base.edit_title'))

@section('content')
    <h1>{{ __('knowledge_base.edit_title') }}</h1>

    <form method="POST" action="{{ route('knowledge-base.update', $knowledgeBase) }}">
        @csrf
        @method('PUT')
        @include('knowledge-base._form', ['entry' => $knowledgeBase])
    </form>
@endsection

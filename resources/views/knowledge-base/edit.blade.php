@extends('layouts.app')

@section('title', __('knowledge_base.edit_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-pencil-square me-2"></i>{{ __('knowledge_base.edit_title') }}</h1>

        <form method="POST" action="{{ route('knowledge-base.update', $knowledgeBase) }}">
            @csrf
            @method('PUT')
            @include('knowledge-base._form', ['entry' => $knowledgeBase])
        </form>
    </div>
@endsection

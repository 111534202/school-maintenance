@extends('layouts.app')

@section('title', $knowledgeBase->title)

@section('content')
    <div class="toolbar">
        <h1>{{ $knowledgeBase->title }}</h1>
        <div>
            <a class="btn btn-secondary" href="{{ route('knowledge-base.edit', $knowledgeBase) }}">{{ __('common.buttons.edit') }}</a>
            <a class="btn btn-secondary" href="{{ route('knowledge-base.index') }}">{{ __('common.buttons.back_to_list') }}</a>
        </div>
    </div>

    <p>
        <strong>{{ __('knowledge_base.show.category_prefix') }}</strong>{{ $knowledgeBase->category ?? __('knowledge_base.uncategorized') }}<br>
        <strong>{{ __('knowledge_base.show.status_prefix') }}</strong>
        @if ($knowledgeBase->is_published)
            <span class="badge badge-on">{{ __('knowledge_base.published') }}</span>
        @else
            <span class="badge badge-off">{{ __('knowledge_base.unpublished') }}</span>
        @endif
    </p>

    <h3>{{ __('knowledge_base.show.symptom_heading') }}</h3>
    <p style="white-space: pre-line;">{{ $knowledgeBase->symptom }}</p>

    <h3>{{ __('knowledge_base.show.solution_heading') }}</h3>
    <p style="white-space: pre-line;">{{ $knowledgeBase->solution }}</p>

    <div class="field" style="margin-top: 2rem; border-top: 1px solid #e4e7eb; padding-top: 1.5rem;">
        <p><strong>{{ __('knowledge_base.show.resolved_prompt') }}</strong></p>
        <a class="btn btn-primary" href="{{ route('knowledge-base.resolved', $knowledgeBase) }}">{{ __('knowledge_base.show.resolved_button') }}</a>
        <a class="btn btn-danger" href="{{ route('repairs.create', ['from_kb' => $knowledgeBase->id]) }}">{{ __('knowledge_base.show.unresolved_button') }}</a>
    </div>
@endsection

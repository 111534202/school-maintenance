@extends('layouts.app')

@section('title', $knowledgeBase->title)

@section('content')
    <div class="page-narrow">
        <div class="d-flex justify-content-between align-items-start mb-3 gap-3">
            <h1 class="h4 mb-0">{{ $knowledgeBase->title }}</h1>
            <div class="d-inline-flex gap-1 flex-shrink-0">
                <a class="btn btn-outline-secondary icon-btn" href="{{ route('knowledge-base.index') }}"
                    title="{{ __('common.buttons.back_to_list') }}" aria-label="{{ __('common.buttons.back_to_list') }}"><i class="bi bi-arrow-left"></i></a>
                <a class="btn btn-outline-primary icon-btn" href="{{ route('knowledge-base.edit', $knowledgeBase) }}"
                    title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-3 mb-4 text-muted align-items-center">
            <span><i class="bi bi-folder2 me-1"></i>{{ $knowledgeBase->category ?? __('knowledge_base.uncategorized') }}</span>
            @if ($knowledgeBase->is_published)
                <span class="badge text-bg-success">{{ __('knowledge_base.published') }}</span>
            @else
                <span class="badge text-bg-secondary">{{ __('knowledge_base.unpublished') }}</span>
            @endif
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white"><i class="bi bi-exclamation-triangle me-2"></i>{{ __('knowledge_base.show.symptom_heading') }}</div>
            <div class="card-body" style="white-space: pre-line;">{{ $knowledgeBase->symptom }}</div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white"><i class="bi bi-list-check me-2"></i>{{ __('knowledge_base.show.solution_heading') }}</div>
            <div class="card-body" style="white-space: pre-line;">{{ $knowledgeBase->solution }}</div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <strong>{{ __('knowledge_base.show.resolved_prompt') }}</strong>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-success" href="{{ route('knowledge-base.resolved', $knowledgeBase) }}">
                        <i class="bi bi-check-circle me-1"></i>{{ __('knowledge_base.show.resolved_button') }}
                    </a>
                    <a class="btn btn-danger" href="{{ route('repairs.create', ['from_kb' => $knowledgeBase->id]) }}">
                        <i class="bi bi-tools me-1"></i>{{ __('knowledge_base.show.unresolved_button') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

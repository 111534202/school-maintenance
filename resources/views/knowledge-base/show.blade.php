{{-- 單篇知識庫文章頁（對應 KnowledgeBaseController::show）：顯示故障現象與排除步驟，最下面是「問題已解決」／「仍無法排除，前往報修」兩個按鈕。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

{{-- 瀏覽器分頁標題直接用文章標題。 --}}
@section('title', $knowledgeBase->title)

@section('content')
    <div class="page-narrow">
        <div class="d-flex justify-content-between align-items-start mb-3 gap-3">
            <h1 class="h4 mb-0">{{ $knowledgeBase->title }}</h1>
            <div class="d-inline-flex gap-1 flex-shrink-0">
                <a class="btn btn-outline-secondary icon-btn" href="{{ route('knowledge-base.index') }}"
                    title="{{ __('common.buttons.back_to_list') }}" aria-label="{{ __('common.buttons.back_to_list') }}"><i class="bi bi-arrow-left"></i></a>
                {{-- 只有管理權限者看得到「編輯」按鈕。 --}}
                @can('knowledge-base.manage')
                    <a class="btn btn-outline-primary icon-btn" href="{{ route('knowledge-base.edit', $knowledgeBase) }}"
                        title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
                @endcan
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
            {{-- white-space: pre-line 讓文字裡的換行照原樣顯示（排除步驟常常是一行一步）。 --}}
            <div class="card-body" style="white-space: pre-line;">{{ $knowledgeBase->symptom }}</div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white"><i class="bi bi-list-check me-2"></i>{{ __('knowledge_base.show.solution_heading') }}</div>
            <div class="card-body" style="white-space: pre-line;">{{ $knowledgeBase->solution }}</div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                {{-- 底部區塊：問使用者「問題解決了嗎？」。 --}}
                <strong>{{ __('knowledge_base.show.resolved_prompt') }}</strong>
                <div class="d-flex flex-wrap gap-2">
                    {{-- 「問題已解決」：只會跳回列表並顯示感謝訊息，不記錄額外資料。 --}}
                    <a class="btn btn-success" href="{{ route('knowledge-base.resolved', $knowledgeBase) }}">
                        <i class="bi bi-check-circle me-1"></i>{{ __('knowledge_base.show.resolved_button') }}
                    </a>
                    {{-- 有「提出報修」權限才顯示「仍無法排除，前往報修」；網址帶 from_kb 讓報修表單知道是看完哪篇文章來的。 --}}
                    @can('repairs.create')
                        <a class="btn btn-danger" href="{{ route('repairs.create', ['from_kb' => $knowledgeBase->id]) }}">
                            <i class="bi bi-tools me-1"></i>{{ __('knowledge_base.show.unresolved_button') }}
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endsection

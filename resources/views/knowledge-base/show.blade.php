@extends('layouts.app')

@section('title', $knowledgeBase->title)

@section('content')
    <div class="toolbar">
        <h1>{{ $knowledgeBase->title }}</h1>
        <div>
            <a class="btn btn-secondary" href="{{ route('knowledge-base.edit', $knowledgeBase) }}">編輯</a>
            <a class="btn btn-secondary" href="{{ route('knowledge-base.index') }}">返回列表</a>
        </div>
    </div>

    <p>
        <strong>分類：</strong>{{ $knowledgeBase->category ?? '未分類' }}<br>
        <strong>狀態：</strong>
        @if ($knowledgeBase->is_published)
            <span class="badge badge-on">已上架</span>
        @else
            <span class="badge badge-off">未上架</span>
        @endif
    </p>

    <h3>常見故障現象</h3>
    <p style="white-space: pre-line;">{{ $knowledgeBase->symptom }}</p>

    <h3>自助排除步驟</h3>
    <p style="white-space: pre-line;">{{ $knowledgeBase->solution }}</p>

    <div class="field" style="margin-top: 2rem; border-top: 1px solid #e4e7eb; padding-top: 1.5rem;">
        <p><strong>照著上面步驟排除後，問題解決了嗎？</strong></p>
        <a class="btn btn-primary" href="{{ route('knowledge-base.resolved', $knowledgeBase) }}">問題已解決</a>
        <a class="btn btn-danger" href="{{ route('repair-requests.create', ['from_kb' => $knowledgeBase->id]) }}">無法排除，前往報修</a>
    </div>
@endsection

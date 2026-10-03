{{-- 新增知識庫文章頁（對應 KnowledgeBaseController::create），欄位在 knowledge-base/_form.blade.php。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('knowledge_base.create_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-plus-circle me-2"></i>{{ __('knowledge_base.create_title') }}</h1>

        {{-- 表單送出到 POST /knowledge-base（KnowledgeBaseController::store）。 --}}
        <form method="POST" action="{{ route('knowledge-base.store') }}">
            {{-- 表單防偽 token（CSRF）：Laravel 要求所有 POST 表單都要有。 --}}
            @csrf
            {{-- 引入共用的欄位表單。 --}}
            @include('knowledge-base._form')
        </form>
    </div>
@endsection

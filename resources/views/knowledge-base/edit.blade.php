{{-- 編輯知識庫文章頁（對應 KnowledgeBaseController::edit），欄位在 knowledge-base/_form.blade.php。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('knowledge_base.edit_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-pencil-square me-2"></i>{{ __('knowledge_base.edit_title') }}</h1>

        {{-- 表單送出到 PUT /knowledge-base/{id}（KnowledgeBaseController::update）。 --}}
        <form method="POST" action="{{ route('knowledge-base.update', $knowledgeBase) }}">
            {{-- 表單防偽 token（CSRF）：Laravel 要求所有 POST 表單都要有。 --}}
            @csrf
            {{-- 用隱藏欄位把 POST 偽裝成 PUT（Laravel 的更新慣例）。 --}}
            @method('PUT')
            {{-- 引入共用的欄位表單，並把目前文章以 $entry 的名字傳進去。 --}}
            @include('knowledge-base._form', ['entry' => $knowledgeBase])
        </form>
    </div>
@endsection

@extends('layouts.app')

@section('title', '編輯知識庫項目')

@section('content')
    <h1>編輯知識庫項目</h1>

    <form method="POST" action="{{ route('knowledge-base.update', $knowledgeBase) }}">
        @csrf
        @method('PUT')
        @include('knowledge-base._form', ['entry' => $knowledgeBase])
    </form>
@endsection

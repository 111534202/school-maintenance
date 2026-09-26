@extends('layouts.app')

@section('title', '新增知識庫項目')

@section('content')
    <h1>新增知識庫項目</h1>

    <form method="POST" action="{{ route('knowledge-base.store') }}">
        @csrf
        @include('knowledge-base._form')
    </form>
@endsection

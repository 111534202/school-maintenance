@extends('layouts.app')

@section('title', '新增保養項目')

@section('content')
    <h1 class="h3 mb-3">新增保養項目</h1>

    <form action="{{ route('maintenance-items.store') }}" method="POST" class="bg-white p-4 rounded shadow-sm">
        @include('maintenance_items._form', ['item' => $item])
    </form>
@endsection

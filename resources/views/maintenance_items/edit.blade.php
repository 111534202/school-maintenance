@extends('layouts.app')

@section('title', '修改保養項目')

@section('content')
    <h1 class="h3 mb-3">修改保養項目</h1>

    <form action="{{ route('maintenance-items.update', $item) }}" method="POST" class="bg-white p-4 rounded shadow-sm">
        @method('PUT')
        @include('maintenance_items._form', ['item' => $item])
    </form>
@endsection

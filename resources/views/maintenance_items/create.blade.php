@extends('layouts.app')

@section('title', '新增保養項目')

@section('content')
    <h1 data-modal-hide class="tb-title mb-3">新增保養項目</h1>

    <form action="{{ route('maintenance-items.store') }}" method="POST">
        @include('maintenance_items._form', ['item' => $item])
    </form>
@endsection

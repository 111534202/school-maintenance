@extends('layouts.app')

@section('title', '新增保養計畫')

@section('content')
    <h1 data-modal-hide class="tb-title mb-3">新增保養計畫</h1>

    <form action="{{ route('maintenance-plans.store') }}" method="POST">
        @include('maintenance_plans._form', ['plan' => $plan, 'items' => $items, 'selectedItemIds' => $selectedItemIds])
    </form>
@endsection

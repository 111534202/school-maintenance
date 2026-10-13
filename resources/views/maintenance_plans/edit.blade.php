@extends('layouts.app')

@section('title', '修改保養計畫')

@section('content')
    <h1 class="h3 mb-3">修改保養計畫</h1>

    <form action="{{ route('maintenance-plans.update', $plan) }}" method="POST" class="bg-white p-4 rounded shadow-sm">
        @method('PUT')
        @include('maintenance_plans._form', ['plan' => $plan, 'items' => $items, 'selectedItemIds' => $selectedItemIds])
    </form>
@endsection

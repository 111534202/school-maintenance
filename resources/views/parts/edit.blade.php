
@extends('layouts.app')

@section('title', '修改零件')

@section('content')
    <h3 class="mb-3">修改零件</h3>

    <div class="card p-4" style="max-width: 480px;">
        <form method="POST" action="{{ route('parts.update', $part) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">零件名稱</label>
                <input
                    type="text"
                    name="name"
                    class="form-control"
                    value="{{ old('name', $part->name) }}"
                    required
                    autofocus
                >
            </div>

            <div class="mb-3">
                <label class="form-label">規格</label>
                <input
                    type="text"
                    name="specification"
                    class="form-control"
                    value="{{ old('specification', $part->specification) }}"
                >
            </div>

            <div class="mb-3">
                <label class="form-label">單價</label>
                <input
                    type="number"
                    name="unit_price"
                    class="form-control"
                    value="{{ old('unit_price', $part->unit_price) }}"
                    min="0"
                    step="1"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">目前庫存</label>
                <input
                    type="number"
                    name="current_stock"
                    class="form-control"
                    value="{{ old('current_stock', $part->current_stock) }}"
                    min="0"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">安全庫存</label>
                <input
                    type="number"
                    name="safety_stock"
                    class="form-control"
                    value="{{ old('safety_stock', $part->safety_stock) }}"
                    min="0"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary">儲存修改</button>
            <a href="{{ route('parts.index') }}" class="btn btn-outline-secondary">取消</a>
        </form>
    </div>
@endsection

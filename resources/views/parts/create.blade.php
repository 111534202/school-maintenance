
@extends('layouts.app')

@section('title', '新增零件')

@section('content')
    <h3 class="mb-3">新增零件</h3>

    <div class="card p-4" style="max-width: 480px;">
        <form method="POST" action="{{ route('parts.store') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">零件名稱</label>
                <input
                    type="text"
                    name="name"
                    class="form-control"
                    value="{{ old('name') }}"
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
                    value="{{ old('specification') }}"
                >
            </div>

            <div class="mb-3">
                <label class="form-label">單價</label>
                <input
                    type="number"
                    name="unit_price"
                    class="form-control"
                    value="{{ old('unit_price', 0) }}"
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
                    value="{{ old('current_stock', 0) }}"
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
                    value="{{ old('safety_stock', 0) }}"
                    min="0"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary">儲存</button>
            <a href="{{ route('parts.index') }}" class="btn btn-outline-secondary">取消</a>
        </form>
    </div>
@endsection

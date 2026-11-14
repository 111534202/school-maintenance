@extends('layouts.app')

@section('title', '新增零件')

@section('content')
    <h1 data-modal-hide class="tb-title mb-3">新增零件</h1>

    <div>
        <form method="POST" action="{{ route('parts.store') }}">
            @csrf
            <div class="card shadow-sm">
                <div class="card-header bg-white form-section-title"><i class="bi bi-box-seam me-2"></i>零件資料</div>
                <div class="card-body">

            @include('parts._code-field', ['part' => null, 'nextCode' => $nextCode])

            @include('parts._basic-fields', ['part' => null])

            <hr>
            <p class="fw-semibold mb-2">期初庫存</p>
            <p class="form-text mt-0">系統上線前就已經有的庫存可以填在這裡，會建立一筆「期初庫存」採購批次。之後進貨請在零件履歷按「新增採購」。</p>
            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <label for="current_stock" class="form-label">期初庫存數量</label>
                    <input type="number" id="current_stock" name="current_stock" min="0" required
                        class="form-control @error('current_stock') is-invalid @enderror" value="{{ old('current_stock', 0) }}">
                    @error('current_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label for="unit_price" class="form-label">期初單價（元）</label>
                    <input type="number" id="unit_price" name="unit_price" min="0"
                        class="form-control @error('unit_price') is-invalid @enderror" value="{{ old('unit_price') }}">
                    @error('unit_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            </div>
            </div>

            {{-- 取消／送出按鈕放在外框外面、靠右。 --}}
            <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="{{ route('parts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>取消</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>儲存</button>
            </div>
        </form>
    </div>
@endsection

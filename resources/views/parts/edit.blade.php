@extends('layouts.app')

@section('title', '修改零件基本資料：' . $part->part_code)

@section('content')
    <h1 data-modal-hide class="tb-title mb-3">修改零件基本資料</h1>

    <div>
        <form method="POST" action="{{ route('parts.update', $part) }}">
            @csrf
            <div class="card shadow-sm">
                <div class="card-header bg-white form-section-title"><i class="bi bi-box-seam me-2"></i>基本資料</div>
                <div class="card-body">
            @method('PUT')

            @include('parts._code-field', ['part' => $part, 'nextCode' => null])

            @include('parts._basic-fields', ['part' => $part])

            <div class="mb-4">
                <label class="form-label">目前庫存</label>
                <input type="text" class="form-control" value="{{ $part->withUnit($part->current_stock) }}" disabled>
                {{-- 跟「修改採購紀錄」鎖定數量、單價時的說明同樣顏色與鎖頭圖示。 --}}
                <div class="form-text text-warning-emphasis"><i class="bi bi-lock"></i> 庫存由採購與報廢紀錄、維修、保養用料自動計算</div>
            </div>
            </div>
            </div>

            {{-- 取消／送出按鈕放在外框外面、靠右。 --}}
            <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="{{ route('parts.show', $part) }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>取消</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>儲存</button>
            </div>
        </form>
    </div>
@endsection

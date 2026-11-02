@extends('layouts.app')

@section('title', '修改零件基本資料：' . $part->part_code)

@section('content')
    <h1 class="h3 mb-3">修改零件基本資料</h1>

    <div class="card p-4" style="max-width: 560px;">
        <form method="POST" action="{{ route('parts.update', $part) }}">
            @csrf
            @method('PUT')

            {{-- 零件編號建立後就不能修改，維修、保養用料與成本報表都靠它對應。 --}}
            <div class="mb-3">
                <label class="form-label">零件編號</label>
                <input type="text" class="form-control" value="{{ $part->part_code }}" disabled>
            </div>

            @include('parts._basic-fields', ['part' => $part])

            <div class="mb-4">
                <label class="form-label">目前庫存</label>
                <input type="text" class="form-control" value="{{ $part->current_stock }}" disabled>
                <div class="form-text">庫存由「採購紀錄」（進貨）與維修、保養用料自動計算，不能直接修改。</div>
            </div>

            <button type="submit" class="btn btn-primary">儲存</button>
            <a href="{{ route('parts.show', $part) }}" class="btn btn-outline-secondary">取消</a>
        </form>
    </div>
@endsection

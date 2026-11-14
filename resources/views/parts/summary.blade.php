@extends('layouts.app')

@section('title', '零件庫存：' . $part->displayName())

@section('content')
{{-- $inModal：在零件庫存列表的對話框裡打開（?modal=1）。 --}}
@php($inModal = request()->boolean('modal'))

{{-- 零件庫存（摘要）：零件庫存列表或低庫存視窗點一筆時，在畫面中的對話框（標題「零件庫存」）裡打開這一頁（?modal=1）。
     只顯示這個零件的基本資料（跟設備詳細一樣的左右兩欄清單，手機上內容短的也排在同一行），
     下面「查看履歷」進零件履歷（使用、採購、報廢紀錄都在履歷頁）。 --}}
<div class="part-summary">
    @unless ($inModal)
        <h1 class="tb-title mb-3">零件庫存</h1>
    @endunless

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <dl class="row mb-0 gy-2 tb-dl-flow">
                <dt class="col-sm-4 text-muted fw-normal">零件編號</dt>
                <dd class="col-sm-8 mb-0">{{ $values['part_code'] }}</dd>

                <dt class="col-sm-4 text-muted fw-normal">名稱</dt>
                <dd class="col-sm-8 mb-0">{{ $values['name'] }}</dd>

                <dt class="col-sm-4 text-muted fw-normal">規格</dt>
                <dd class="col-sm-8 mb-0">{{ $values['specification'] }}</dd>

                <dt class="col-sm-4 text-muted fw-normal">品牌/型號</dt>
                <dd class="col-sm-8 mb-0">{{ $values['brand_model'] }}</dd>

                <dt class="col-sm-4 text-muted fw-normal">平均單價</dt>
                <dd class="col-sm-8 mb-0">{{ $values['average_price'] }}</dd>

                <dt class="col-sm-4 text-muted fw-normal">目前庫存</dt>
                <dd class="col-sm-8 mb-0 {{ $part->isLowStock() ? 'text-danger fw-semibold' : '' }}">{{ $values['current_stock'] }}</dd>

                <dt class="col-sm-4 text-muted fw-normal">安全庫存</dt>
                <dd class="col-sm-8 mb-0">{{ $values['safety_stock'] }}</dd>

                <dt class="col-sm-4 text-muted fw-normal">庫存狀態</dt>
                <dd class="col-sm-8 mb-0">
                    @if ($part->isLowStock())
                        <span class="badge tb-pill tb-pill-danger">⚠ 低庫存</span>
                    @else
                        <span class="badge tb-pill tb-pill-ok">庫存充足</span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>

    <div class="d-flex justify-content-end">
        <a href="{{ route('parts.show', $part) }}" class="btn btn-primary">
            <i class="bi bi-journal-text me-1" aria-hidden="true"></i>查看履歷
        </a>
    </div>
</div>
@endsection

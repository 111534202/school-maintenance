@extends('layouts.app')

@section('title', '零件庫存：' . $part->displayName())

@section('content')
{{-- $inModal：在零件庫存列表的對話框裡打開（?modal=1）。 --}}
@php($inModal = request()->boolean('modal'))

{{-- 零件庫存（基本資料）：零件庫存列表或低庫存視窗點一筆時，在畫面中的對話框（標題「零件庫存」）裡打開這一頁（?modal=1）。
     名稱與內容固定左右並排（跟零件履歷的基本資料一樣）。按「查看詳細」進零件履歷。 --}}
@unless ($inModal)
    <h1 class="h3 mb-3">零件庫存</h1>
@endunless

<div class="{{ $inModal ? '' : 'card p-4' }}" style="max-width: 720px;">
    {{-- 名稱與內容固定同一行（.tb-dl）；零件編號、名稱、規格固定字數超過以「…」結尾，品牌/型號太長換行。 --}}
    <dl class="tb-dl mb-0" id="partSummary">
        <dt>庫存狀態</dt>
        <dd>
            @if ($part->isLowStock())
                <span class="badge text-bg-danger">⚠ 低庫存</span>
            @else
                <span class="badge text-bg-success">庫存充足</span>
            @endif
        </dd>

        @foreach (\App\Support\PartExportColumns::COLUMNS as $key => $label)
            <dt>{{ $label }}</dt>
            @php($clip = ['part_code' => 'tb-clip-code', 'name' => 'tb-clip-name', 'specification' => 'tb-clip-spec'][$key] ?? null)
            <dd class="{{ $key === 'brand_model' ? 'tb-dl-wrap' : '' }} {{ $key === 'current_stock' && $part->isLowStock() ? 'text-danger fw-semibold' : '' }}">
                @if ($clip)
                    <span class="tb-clip {{ $clip }}" title="{{ $values[$key] }}">{{ $values[$key] }}</span>
                @else
                    {{ $values[$key] }}
                @endif
            </dd>
        @endforeach
    </dl>

    <div class="d-flex mt-4">
        <a href="{{ route('parts.show', $part) }}" class="btn btn-success">
            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>查看詳細
        </a>
    </div>
</div>

@endsection

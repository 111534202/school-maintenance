@extends('layouts.app')

@section('title', '零件庫存：' . $part->displayName())

@section('content')
{{-- $inModal：在零件庫存列表的對話框裡打開（?modal=1）。 --}}
@php($inModal = request()->boolean('modal'))

{{-- 零件庫存（摘要）：零件庫存列表或低庫存視窗點一筆時，在畫面中的對話框（標題「零件庫存」）裡打開這一頁（?modal=1）。
     版面跟列表一致（風格 B）：
       上面：零件編號＋庫存狀態（跟列表同樣的膠囊標籤），下面是名稱、規格（不截斷，太長換行）、品牌/型號；
       中間：平均單價、目前庫存、安全庫存三個數字方塊（低庫存時目前庫存是紅字）；
       下面：「查看履歷」進零件履歷。 --}}
<div class="tb part-summary {{ $inModal ? '' : 'tb-card p-4' }}">
    @unless ($inModal)
        <h1 class="tb-title">零件庫存</h1>
    @endunless

    <div class="ps-head">
        <div class="ps-head-main">
            <div class="ps-code tb-code">{{ $values['part_code'] }}</div>
            <div class="ps-name">{{ $values['name'] }}</div>
            @if (filled($part->specification))
                <div class="ps-spec">{{ $part->specification }}</div>
            @endif
        </div>
        <span class="visually-hidden">庫存狀態</span>
        @if ($part->isLowStock())
            <span class="badge tb-pill tb-pill-danger">⚠ 低庫存</span>
        @else
            <span class="badge tb-pill tb-pill-ok">庫存充足</span>
        @endif
    </div>

    <div class="ps-row">
        <span class="ps-label">品牌/型號</span>
        <span class="ps-value">{{ $values['brand_model'] }}</span>
    </div>

    <div class="ps-stats">
        <div class="ps-stat">
            <div class="ps-label">平均單價</div>
            <div class="ps-stat-value">{{ $values['average_price'] }}</div>
        </div>
        <div class="ps-stat {{ $part->isLowStock() ? 'is-low' : '' }}">
            <div class="ps-label">目前庫存</div>
            <div class="ps-stat-value">{{ $values['current_stock'] }}</div>
        </div>
        <div class="ps-stat">
            <div class="ps-label">安全庫存</div>
            <div class="ps-stat-value">{{ $values['safety_stock'] }}</div>
        </div>
    </div>

    <div class="d-flex justify-content-end">
        <a href="{{ route('parts.show', $part) }}" class="tb-btn tb-btn-primary">
            <i class="bi bi-journal-text" aria-hidden="true"></i>查看履歷
        </a>
    </div>
</div>

<style>
    .part-summary { gap: 18px; max-width: 720px; }
    .part-summary .ps-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
    .part-summary .ps-head-main { min-width: 0; }
    .part-summary .ps-code { font-size: 0.95rem; font-weight: 700; }
    .part-summary .ps-name { font-family: var(--tb-font-heading); font-size: 1.35rem; font-weight: 700; line-height: 1.35; overflow-wrap: anywhere; }
    .part-summary .ps-spec { color: var(--tb-muted); overflow-wrap: anywhere; }
    .part-summary .ps-head .badge { flex: 0 0 auto; margin-top: 2px; }
    .part-summary .ps-row { display: flex; gap: 1rem; align-items: baseline; padding-top: 14px; border-top: 1px solid var(--tb-border); }
    .part-summary .ps-label { font-size: 0.875rem; color: var(--tb-muted); white-space: nowrap; }
    .part-summary .ps-value { min-width: 0; overflow-wrap: anywhere; }
    .part-summary .ps-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .part-summary .ps-stat { background: var(--tb-zebra); border: 1px solid var(--tb-border); border-radius: 14px; padding: 12px 14px; }
    .part-summary .ps-stat-value { font-size: 1.15rem; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .part-summary .ps-stat.is-low .ps-stat-value { color: var(--tb-danger, #B3261E); }
    @media (max-width: 420px) {
        .part-summary .ps-stats { grid-template-columns: minmax(0, 1fr); }
        .part-summary .ps-stat { display: flex; justify-content: space-between; align-items: baseline; padding: 10px 14px; }
    }
</style>
@endsection

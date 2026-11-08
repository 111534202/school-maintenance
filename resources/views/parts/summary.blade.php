@extends('layouts.app')

@section('title', '零件庫存：' . $part->displayName())

@section('content')
{{-- $inModal：在零件庫存列表的對話框裡打開（?modal=1）。 --}}
@php($inModal = request()->boolean('modal'))

{{-- 零件庫存（基本資料）：零件庫存列表或低庫存視窗點一筆時，在畫面中的對話框（標題「零件庫存」）裡打開這一頁（?modal=1）。
     版面與空間申請的「預約詳細」相同：名稱與內容放得下就左右並排，放不下才上下排列。按「查看詳細」進零件履歷。 --}}
@unless ($inModal)
    <h1 class="h3 mb-3">零件庫存</h1>
@endunless

<div class="{{ $inModal ? '' : 'card p-4' }}" style="max-width: 720px;">
    <dl class="mb-0 reservation-detail" id="partSummary">
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
            <dd class="{{ in_array($key, ['name', 'specification', 'brand_model'], true) ? 'rd-wrap' : '' }} {{ $key === 'current_stock' && $part->isLowStock() ? 'text-danger fw-semibold' : '' }}">{{ $values[$key] }}</dd>
        @endforeach
    </dl>

    <div class="d-flex mt-4">
        <a href="{{ route('parts.show', $part) }}" class="btn btn-success">
            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>查看詳細
        </a>
    </div>
</div>

<style>
    .reservation-detail { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: 0.5rem 2.5rem; }
    .reservation-detail dt { font-weight: 700; }
    .reservation-detail dd { margin: 0; min-width: 0; white-space: nowrap; }
    .reservation-detail dd.rd-wrap { white-space: normal; overflow-wrap: anywhere; }
    .reservation-detail.is-stacked { grid-template-columns: minmax(0, 1fr); gap: 0; }
    .reservation-detail.is-stacked dt:not(:first-child) { margin-top: 0.75rem; }
    .reservation-detail.is-stacked dd { white-space: normal; }
</style>
<script>
    // 有一項內容（名稱、規格、品牌型號以外）在這個寬度放不下時，才改成名稱在上、內容在下。
    (function () {
        var dl = document.getElementById('partSummary');
        function layout() {
            dl.classList.remove('is-stacked');
            var tooWide = Array.prototype.some.call(dl.querySelectorAll('dd:not(.rd-wrap)'), function (dd) {
                return dd.scrollWidth > dd.clientWidth + 1;
            });
            dl.classList.toggle('is-stacked', tooWide);
        }
        layout();
        window.addEventListener('resize', layout);
    })();
</script>
@endsection

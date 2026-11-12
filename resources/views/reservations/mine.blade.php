@extends('layouts.app')

@section('title', '我的申請')

@section('content')
{{-- 我的申請（有審核權限的人在空間申請右上角按「我的申請」，在畫面中的對話框打開這一頁；?modal=1 不顯示頁首）。
     自己提出的申請與目前狀態，表格／清單依視窗大小自動切換；點一筆在同一個對話框裡打開「預約詳細」。 --}}
<div class="tb">
    @unless (request()->boolean('modal'))
        <h1 class="tb-title">我的申請</h1>
    @endunless
    @if ($myReservations->isEmpty())
        <div class="tb-card tb-empty">目前還沒有申請紀錄。</div>
    @else
        <section class="tb-table-wrap" data-no-fit aria-label="我的申請">
            @include('reservations._table', ['reservations' => $myReservations, 'mine' => true])
        </section>
        {{-- data-modal-stay：在對話框裡按分頁，就在對話框裡換頁。 --}}
        @if ($myReservations->hasPages())
            <div data-modal-stay>{{ $myReservations->links() }}</div>
        @endif
    @endif
</div>
@endsection

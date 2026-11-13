{{-- 列表底部：左邊「顯示第 1 到 15 筆，共 24 筆」，右邊切換頁數（多頁時才有）；只有一頁時也顯示筆數。
     用法：@include('layouts.pager', ['paginator' => $users])（零件庫存、空間申請等列表頁共用）。 --}}
@if ($paginator->total() > 0)
    @if ($paginator->hasPages())
        <div>{{ $paginator->links() }}</div>
    @else
        <p class="small text-muted mb-0">顯示第 {{ $paginator->firstItem() }} 到 {{ $paginator->lastItem() }} 筆，共 {{ $paginator->total() }} 筆</p>
    @endif
@endif

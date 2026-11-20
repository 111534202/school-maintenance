{{-- 全站分頁（AppServiceProvider 設成預設的分頁畫面，layouts/pager 也用這份）。
     電腦版：「‹ 1 2 3 4 5 6 7 8 9 10 … ›」——一次列 10 頁，目前在第 11～20 頁時就列 11～20，以此類推；
            前後還有更多頁時顯示「…」，點「…」跳到前一組的最後一頁／下一組的第一頁。
     手機版：「‹ [1] / 10 ›」——目前頁數放在有外框的輸入框裡，可以直接改成要跳到的頁數（按 Enter 或點旁邊就跳轉）；
            超過總頁數或不是數字就不跳，輸入框還原成目前頁數。
     ul 上的 data-last-page 給 layouts/partials/fit-screen 讀總頁數用。 --}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $blockSize = 10;
        $blockStart = intdiv($current - 1, $blockSize) * $blockSize + 1;
        $blockEnd = min($blockStart + $blockSize - 1, $last);
    @endphp
    <nav class="d-flex justify-content-between align-items-center flex-wrap gap-2" aria-label="分頁">
        <p class="small text-muted mb-0 d-none d-sm-block">顯示第 {{ $paginator->firstItem() }} 到 {{ $paginator->lastItem() }} 筆，共 {{ $paginator->total() }} 筆</p>

        {{-- 電腦版：一組 10 頁 --}}
        <ul class="pagination mb-0 d-none d-sm-flex" data-last-page="{{ $last }}">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true"><span class="page-link" aria-hidden="true">&lsaquo;</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="上一頁">&lsaquo;</a></li>
            @endif

            @if ($blockStart > 1)
                <li class="page-item"><a class="page-link" href="{{ $paginator->url($blockStart - 1) }}" title="前 {{ $blockSize }} 頁">…</a></li>
            @endif

            @for ($page = $blockStart; $page <= $blockEnd; $page++)
                @if ($page === $current)
                    <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a></li>
                @endif
            @endfor

            @if ($blockEnd < $last)
                <li class="page-item"><a class="page-link" href="{{ $paginator->url($blockEnd + 1) }}" title="後 {{ $blockSize }} 頁">…</a></li>
            @endif

            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="下一頁">&rsaquo;</a></li>
            @else
                <li class="page-item disabled" aria-disabled="true"><span class="page-link" aria-hidden="true">&rsaquo;</span></li>
            @endif
        </ul>

        {{-- 手機版：‹ 1 / 10 ›，點兩下頁數可以輸入要跳到的頁數 --}}
        <div class="tb-page-jump d-flex d-sm-none align-items-center justify-content-center gap-3 w-100"
            data-page-url="{{ $paginator->url(1) }}" data-page-name="{{ $paginator->getPageName() }}" data-last-page="{{ $last }}">
            @if ($paginator->onFirstPage())
                <button type="button" class="btn btn-outline-primary btn-sm icon-btn" disabled aria-label="上一頁"><i class="bi bi-chevron-left"></i></button>
            @else
                <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="上一頁"><i class="bi bi-chevron-left"></i></a>
            @endif
            <span class="tb-page-label"><input type="text" inputmode="numeric" pattern="[0-9]*" class="form-control form-control-sm d-inline-block tb-page-input" value="{{ $current }}" data-current="{{ $current }}" aria-label="目前第 {{ $current }} 頁，可輸入要跳到的頁數（共 {{ $last }} 頁）"> / {{ $last }}</span>
            @if ($paginator->hasMorePages())
                <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="下一頁"><i class="bi bi-chevron-right"></i></a>
            @else
                <button type="button" class="btn btn-outline-primary btn-sm icon-btn" disabled aria-label="下一頁"><i class="bi bi-chevron-right"></i></button>
            @endif
        </div>
    </nav>
@endif

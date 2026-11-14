{{-- 列表上方的關鍵字搜尋列（共用）：$action 送出網址、$placeholder 提示文字。
     條件會出現在網址上（GET），換頁時保留；按「清除」回到全部。 --}}
<div class="tb-card tb-card-pad">
    <form method="GET" action="{{ $action }}" class="filter-bar tb-form-row tb-filter" data-no-onion>
        <div>
            <label for="keyword" class="form-label small mb-1">關鍵字</label>
            <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 18rem;"
                value="{{ request('keyword') }}" placeholder="{{ $placeholder }}">
        </div>
        @include('layouts.partials.filter-actions', ['reset' => $action, 'fields' => ['keyword']])
    </form>
</div>

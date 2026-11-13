{{-- 篩選列最右邊的按鈕（全站共用，改這裡所有篩選列一起改）：
     「篩選」送出表單；有套用任何篩選條件時才出現「清除」（回到沒有條件的列表）。
     用法：@include('layouts.partials.filter-actions', ['reset' => route('users.index'), 'fields' => ['keyword', 'status']])
       $reset：清除後要回到的網址；$fields：這個篩選列有哪些欄位（網址上的參數名稱），有一個有值就算有篩選。
       也可以直接傳 $active（true／false）決定要不要顯示「清除」。
     排版（手機上跟最後一個欄位排在同一行、不單獨換行）在 public/css/theme-b.css 的 .tb-filter-actions。 --}}
@php($active = $active ?? collect(request()->only($fields ?? []))->filter(fn ($value) => filled($value))->isNotEmpty())
<div class="d-inline-flex gap-1 tb-filter-actions">
    <button class="btn btn-primary btn-sm" type="submit">篩選</button>
    @if ($active)
        <a class="btn btn-outline-secondary btn-sm" href="{{ $reset }}">清除</a>
    @endif
</div>

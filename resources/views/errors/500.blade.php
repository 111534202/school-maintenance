{{-- 500：系統錯誤。 --}}
@extends('errors.layout', ['code' => 500])
@section('title', '系統發生錯誤')
@section('body')
    <p>很抱歉，剛剛的操作沒有完成。系統已經記錄這次錯誤。</p>
    <p class="hint">請稍後再試一次；如果一直出現，請告訴資訊組你剛剛在做什麼（例如哪個頁面、按了哪個按鈕）。</p>
@endsection

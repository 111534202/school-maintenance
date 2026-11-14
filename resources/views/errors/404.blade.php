{{-- 404：找不到。 --}}
@extends('errors.layout', ['code' => 404])
@section('title', '找不到這個頁面')
@section('body')
    <p>網址可能打錯了、連結已經過期，或這筆資料已經被取消、刪除。</p>
    <p class="hint">請回到上一頁，或從首頁的選單重新找一次。</p>
@endsection

{{-- 429：操作太頻繁。 --}}
@extends('errors.layout', ['code' => 429])
@section('title', '操作太頻繁，請稍後再試')
@section('body')
    <p>短時間內送出太多次了，系統暫時擋下。請等一兩分鐘再試。</p>
@endsection

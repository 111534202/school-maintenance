{{-- 422：這個操作現在不能做（例如已經處理過、狀態已經改變）。程式有給原因時一併顯示。 --}}
@extends('errors.layout', ['code' => 422])
@section('title', '這個操作現在無法進行')
@section('body')
    @if (! empty($exception) && $exception->getMessage() !== '' && preg_match('/\p{Han}/u', $exception->getMessage()))
        <p>{{ $exception->getMessage() }}</p>
    @else
        <p>這筆資料的狀態已經改變，或這個操作已經做過了。</p>
    @endif
    <p class="hint">請回到上一頁重新整理，確認目前的狀態後再操作。</p>
@endsection

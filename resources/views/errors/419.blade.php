{{-- 419：表單過期（閒置太久被自動登出，或在別的視窗登出了）。 --}}
@extends('errors.layout', ['code' => 419])
@section('title', '登入逾時，請重新登入')
@section('body')
    <p>為了安全，閒置超過 {{ (int) config('session.lifetime') }} 分鐘會自動登出，所以剛剛這次送出沒有成功。</p>
    <p class="hint">你剛剛填的文字內容有暫存在這台電腦。重新登入後，回到同一個表單就會自動還原，再送出一次即可。</p>
@endsection
@section('actions')
    <a class="btn btn-primary" href="{{ url('/login') }}">重新登入</a>
@endsection

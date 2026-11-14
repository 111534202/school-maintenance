{{-- 503：維護中。 --}}
@extends('errors.layout', ['code' => 503])
@section('title', '系統維護中')
@section('body')
    <p>系統正在更新或維護，暫時不能使用，請稍後再回來。</p>
@endsection

@extends('layouts.app')

@section('title', '主控台 - 學校設備維保電子化系統')

@section('content')
    <h3>歡迎，{{ Auth::user()->name }}</h3>
    <p>角色：{{ Auth::user()->role->name ?? '尚未指派角色' }}</p>
@endsection

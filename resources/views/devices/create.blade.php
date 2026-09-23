@extends('layouts.app')

@section('title', '新增設備')

@section('content')
    <h3 class="mb-3">新增設備</h3>
    <div class="card p-4" style="max-width: 820px;">
        <form method="POST" action="{{ route('devices.store') }}">
            @include('devices._form')
        </form>
    </div>
@endsection

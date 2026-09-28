@extends('layouts.app')

@section('title', '編輯設備')

@section('content')
    <h3 class="mb-3">編輯設備</h3>
    <div class="card p-4" style="max-width: 820px;">
        <form method="POST" action="{{ route('devices.update', $device) }}">
            @include('devices._form')
        </form>
    </div>
@endsection

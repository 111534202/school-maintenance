@extends('layouts.app')

@section('title', '新增教室')

@section('content')
    <h3 class="mb-3">新增教室</h3>
    <div class="card p-4" style="max-width: 720px;">
        <form method="POST" action="{{ route('classrooms.store') }}">
            @include('classrooms._form')
        </form>
    </div>
@endsection

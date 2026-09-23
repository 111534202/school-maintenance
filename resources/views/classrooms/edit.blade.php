@extends('layouts.app')

@section('title', '編輯教室')

@section('content')
    <h3 class="mb-3">編輯教室</h3>
    <div class="card p-4" style="max-width: 720px;">
        <form method="POST" action="{{ route('classrooms.update', $classroom) }}">
            @include('classrooms._form')
        </form>
    </div>
@endsection

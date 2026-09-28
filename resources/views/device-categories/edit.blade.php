@extends('layouts.app')

@section('title', '編輯設備類別')

@section('content')
    <h3 class="mb-3">編輯設備類別</h3>
    <div class="card p-4" style="max-width: 480px;">
        <form method="POST" action="{{ route('device-categories.update', $category) }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label class="form-label">類別名稱</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $category->name) }}" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary">儲存</button>
            <a href="{{ route('device-categories.index') }}" class="btn btn-outline-secondary">取消</a>
        </form>
    </div>
@endsection

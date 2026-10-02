@extends('layouts.app')

@section('title', __('users.form.edit_title'))

@section('content')
    <div class="page-narrow">
        <div class="page-header mb-4">
            <h1 class="h4 mb-0"><i class="bi bi-person-gear me-2"></i>{{ __('users.form.edit_title') }}</h1>
            <span class="text-muted small">
                {{ __('users.form.created_at') }}：{{ $user->created_at->format('Y-m-d H:i') }}
                {{ __('users.form.last_login_at') }}：{{ $user->last_login_at?->format('Y-m-d H:i') ?? __('users.never_logged_in') }}
            </span>
        </div>

        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf
            @method('PUT')
            @include('users._form')
        </form>

        {{-- 重設密碼：獨立的表單，只改密碼，不會動到上面的其他資料。 --}}
        <form method="POST" action="{{ route('users.reset-password', $user) }}">
            @csrf
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white form-section-title"><i class="bi bi-key me-2"></i>{{ __('users.form.section_reset_password') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="reset_password" class="form-label">{{ __('users.form.new_password') }}</label>
                            <input type="password" class="form-control" id="reset_password" name="password" autocomplete="new-password" required>
                            <div class="form-text">{{ __('users.form.password_hint') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label for="reset_password_confirmation" class="form-label">{{ __('users.form.password_confirmation') }}</label>
                            <input type="password" class="form-control" id="reset_password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                        </div>
                    </div>
                    <div class="form-text mt-2">{{ __('users.form.reset_password_hint') }}</div>
                </div>
                <div class="card-footer bg-white d-flex justify-content-end py-3">
                    <button class="btn btn-outline-danger" type="submit"><i class="bi bi-key me-1"></i>{{ __('users.actions.reset_password') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection

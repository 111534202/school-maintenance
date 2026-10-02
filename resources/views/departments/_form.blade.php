@php($department = $department ?? null)

<div class="card shadow-sm">
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="code" class="form-label">{{ __('departments.form.code') }}</label>
                <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code"
                    value="{{ old('code', $department->code ?? '') }}" autocomplete="off">
                <div class="form-text">{{ __('departments.form.code_hint') }}</div>
            </div>
            <div class="col-md-8">
                <label for="name" class="form-label">{{ __('departments.form.name') }}</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                    value="{{ old('name', $department->name ?? '') }}" required>
            </div>
            <div class="col-12">
                <label for="description" class="form-label">{{ __('departments.form.description') }}</label>
                <input type="text" class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                    value="{{ old('description', $department->description ?? '') }}">
            </div>
            <div class="col-12">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                        {{ old('is_active', $department->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">{{ __('departments.form.is_active') }}</label>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-end gap-2 py-3">
        <a class="btn btn-outline-secondary" href="{{ route('departments.index') }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.cancel') }}</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>{{ __('common.buttons.save') }}</button>
    </div>
</div>

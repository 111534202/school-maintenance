@php
    $role = $role ?? null;
    $isAdminRole = $role?->isAdmin() ?? false;
    // 勾選狀態：驗證失敗退回時用剛剛送出的值，否則用這個身分目前實際開放的權限。
    $checked = old('permissions', $role?->effectivePermissions() ?? []);
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white form-section-title"><i class="bi bi-person-badge me-2"></i>{{ __('roles.form.section_basic') }}</div>
    <div class="card-body">
        @if ($role?->is_system)
            <div class="alert alert-info py-2"><i class="bi bi-info-circle me-1"></i>{{ __('roles.form.system_notice') }}</div>
        @endif
        <div class="row g-3">
            <div class="col-md-5">
                <label for="name" class="form-label">{{ __('roles.form.name') }}</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                    value="{{ old('name', $role->name ?? '') }}" required>
            </div>
            <div class="col-md-7">
                <label for="description" class="form-label">{{ __('roles.form.description') }}</label>
                <input type="text" class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                    value="{{ old('description', $role->description ?? '') }}">
            </div>
        </div>
    </div>
</div>

<h2 class="h6 text-muted mb-1"><i class="bi bi-toggles me-2"></i>{{ __('roles.form.section_permissions') }}</h2>
<p class="text-muted small">{{ $isAdminRole ? __('roles.form.admin_notice') : __('roles.form.permissions_hint') }}</p>

@foreach ($groups as $groupKey => $permissionKeys)
    <div class="card shadow-sm mb-3" data-permission-group="{{ $groupKey }}">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="form-section-title">{{ __('permissions.groups.' . $groupKey) }}</span>
            @unless ($isAdminRole)
                <span class="d-inline-flex gap-2 small">
                    <a href="#" class="text-decoration-none" data-permission-all="{{ $groupKey }}">{{ __('roles.form.select_all') }}</a>
                    <span class="text-muted">|</span>
                    <a href="#" class="text-decoration-none" data-permission-none="{{ $groupKey }}">{{ __('roles.form.clear_all') }}</a>
                </span>
            @endunless
        </div>
        <ul class="list-group list-group-flush">
            @foreach ($permissionKeys as $permission)
                <li class="list-group-item">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                            id="perm_{{ $loop->parent->index }}_{{ $loop->index }}" name="permissions[]" value="{{ $permission }}"
                            @checked($isAdminRole || in_array($permission, $checked, true)) @disabled($isAdminRole)>
                        <label class="form-check-label" for="perm_{{ $loop->parent->index }}_{{ $loop->index }}">
                            <span class="fw-semibold">{{ __('permissions.items.' . $permission . '.name') }}</span>
                            <span class="d-block text-muted small">{{ __('permissions.items.' . $permission . '.description') }}</span>
                        </label>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endforeach

<div class="d-flex justify-content-end gap-2 mb-4">
    <a class="btn btn-outline-secondary" href="{{ route('roles.index') }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.cancel') }}</a>
    <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>{{ __('common.buttons.save') }}</button>
</div>

<script>
    // 每個分組的「全選／全部取消」：只勾選或取消同一張卡片裡的開關。
    document.querySelectorAll('[data-permission-all], [data-permission-none]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            var selectAll = link.hasAttribute('data-permission-all');
            var group = link.getAttribute(selectAll ? 'data-permission-all' : 'data-permission-none');
            document.querySelectorAll('[data-permission-group="' + group + '"] input[type=checkbox]').forEach(function (box) {
                box.checked = selectAll;
            });
        });
    });
</script>

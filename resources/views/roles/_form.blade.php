{{-- 新增、編輯身分共用的表單欄位：基本資料 + 一整排權限開關（像 Discord 的身分組）。 --}}
{{-- 權限清單來自 App\Support\PermissionCatalog::GROUPS（由 Controller 傳進來的 $groups），要新增權限選項改那個檔案，這裡不用動。 --}}
{{-- 被 roles/create.blade.php 與 roles/edit.blade.php 用 @include 引入。（Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
@php
    // 沒有傳 $role 進來（= 新增）就當成 null。
    $role = $role ?? null;
    // 這個身分是不是系統管理員（管理員的權限永遠全開、不能取消）。
    $isAdminRole = $role?->isAdmin() ?? false;
    // 勾選狀態：驗證失敗退回時用剛剛送出的值，否則用這個身分目前實際開放的權限。
    // 「目前要打勾的權限」清單。
    $checked = old('permissions', $role?->effectivePermissions() ?? []);
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white form-section-title"><i class="bi bi-person-badge me-2"></i>{{ __('roles.form.section_basic') }}</div>
    <div class="card-body">
        {{-- 編輯系統內建身分時，顯示提醒：代碼與刪除受限，但名稱、說明、權限可以調整。 --}}
        @if ($role?->is_system)
            <div class="alert alert-info py-2"><i class="bi bi-info-circle me-1"></i>{{ __('roles.form.system_notice') }}</div>
        @endif
        <div class="row g-3">
            <div class="col-md-5">
                <label for="name" class="form-label">{{ __('roles.form.name') }}</label>
                {{-- @error('欄位')：驗證失敗時讓框變紅；old('name', 預設值)：驗證失敗導回時帶回剛剛輸入的內容。 --}}
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

{{-- 每個權限分組（主檔管理、報修流程、知識庫）畫成一張卡片。 --}}
@foreach ($groups as $groupKey => $permissionKeys)
    {{-- data-permission-group：標記這張卡片屬於哪個分組，下面的「全選／全部取消」用它找到同組的開關。 --}}
    <div class="card shadow-sm mb-3" data-permission-group="{{ $groupKey }}">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="form-section-title">{{ __('permissions.groups.' . $groupKey) }}</span>
            {{-- 管理員不需要「全選／取消」（永遠全開），其他身分才顯示。 --}}
            @unless ($isAdminRole)
                <span class="d-inline-flex gap-2 small">
                    <a href="#" class="text-decoration-none" data-permission-all="{{ $groupKey }}">{{ __('roles.form.select_all') }}</a>
                    <span class="text-muted">|</span>
                    <a href="#" class="text-decoration-none" data-permission-none="{{ $groupKey }}">{{ __('roles.form.clear_all') }}</a>
                </span>
            @endunless
        </div>
        <ul class="list-group list-group-flush">
            {{-- 這個分組裡的每一個權限畫成一個開關。 --}}
            @foreach ($permissionKeys as $permission)
                <li class="list-group-item">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                            {{-- name 以 [] 結尾：多個勾選的值會合成一個陣列 permissions 送給後端（RoleController 的 permissions.*）。 --}}
                            id="perm_{{ $loop->parent->index }}_{{ $loop->index }}" name="permissions[]" value="{{ $permission }}"
                            {{-- 打勾條件：管理員全部打勾，其他身分依目前的權限清單；管理員的開關同時停用（@disabled），不能取消。 --}}
                            @checked($isAdminRole || in_array($permission, $checked, true)) @disabled($isAdminRole)>
                        <label class="form-check-label" for="perm_{{ $loop->parent->index }}_{{ $loop->index }}">
                            {{-- 權限的中文名稱與說明，來自 lang/各語言資料夾/permissions.php。 --}}
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

{{-- 全選／全部取消的小程式（JavaScript）：點同一張卡片上的「全選」或「全部取消」，一次勾選或取消該卡片裡的所有開關。 --}}
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

{{-- 新增、編輯用戶共用的表單欄位（檔名開頭的底線代表「被別的檔案引入的片段」）。 --}}
{{-- 被 users/create.blade.php 與 users/edit.blade.php 用 @include 引入；編輯時會多一個 $user 變數，帶入目前的資料。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 沒有傳 $user 進來（= 新增）就當成 null，下面用它判斷是新增還是編輯。 --}}
@php($user = $user ?? null)

{{-- 區塊一：帳號資料 --}}
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white form-section-title"><i class="bi bi-person-badge me-2"></i>{{ __('users.form.section_account') }}</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="username" class="form-label">{{ __('users.form.username') }}</label>
                {{-- @error('欄位')：這個欄位驗證失敗時，加上 is-invalid 讓框變紅。old('username', 預設值)：驗證失敗導回時帶回剛剛輸入的內容，否則顯示目前資料。 --}}
                <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" name="username"
                    value="{{ old('username', $user->username ?? '') }}" autocomplete="off" required>
                <div class="form-text">{{ __('users.form.username_hint') }}</div>
            </div>
            <div class="col-md-6">
                <label for="name" class="form-label">{{ __('users.form.name') }}</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                    value="{{ old('name', $user->name ?? '') }}" required>
            </div>
            <div class="col-12">
                <label for="email" class="form-label">{{ __('users.form.email') }}</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                    value="{{ old('email', $user->email ?? '') }}" required>
                <div class="form-text">{{ __('users.form.email_hint') }}</div>
            </div>
            <div class="col-12">
                {{-- 瀏覽器在 checkbox 未勾選時完全不會送出這個欄位，靠這個隱藏欄位確保一定會送出 0 或 1。 --}}
                <input type="hidden" name="is_active" value="0">
                {{-- 開關樣式的核取方塊（啟用／停用）。 --}}
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                        {{ old('is_active', $user->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">{{ __('users.form.is_active') }}</label>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 區塊二：聯絡與所屬 --}}
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white form-section-title"><i class="bi bi-diagram-3 me-2"></i>{{ __('users.form.section_contact') }}</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="role_id" class="form-label">{{ __('users.form.role') }}</label>
                {{-- 身分下拉選單：選項來自身分主檔（$roles）。 --}}
                <select class="form-select @error('role_id') is-invalid @enderror" id="role_id" name="role_id" required>
                    <option value="">{{ __('users.form.role_placeholder') }}</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id ?? '') === (string) $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="department_id" class="form-label">{{ __('users.form.department') }}</label>
                {{-- 部門下拉選單：選項來自部門主檔（$departments，只列啟用中的）。 --}}
                <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id">
                    <option value="">{{ __('users.form.department_placeholder') }}</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) old('department_id', $user->department_id ?? '') === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="phone" class="form-label">{{ __('users.form.phone') }}</label>
                <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone"
                    value="{{ old('phone', $user->phone ?? '') }}">
            </div>
        </div>
    </div>
</div>

{{-- 區塊三：登入密碼（只有新增時才在這裡設定；編輯時改用旁邊獨立的「重設密碼」，避免不小心改到密碼） --}}
{{-- @unless：不是編輯（= 新增）時才顯示密碼欄位。 --}}
@unless ($user)
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white form-section-title"><i class="bi bi-key me-2"></i>{{ __('users.form.section_password') }}</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="password" class="form-label">{{ __('users.form.password') }}</label>
                    {{-- 密碼（新增時必填，至少 8 碼）。autocomplete="new-password" 告訴瀏覽器這是設定新密碼，不要自動填入舊密碼。 --}}
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password"
                        autocomplete="new-password" required>
                    <div class="form-text">{{ __('users.form.password_hint') }}</div>
                </div>
                <div class="col-md-6">
                    <label for="password_confirmation" class="form-label">{{ __('users.form.password_confirmation') }}</label>
                    {{-- 確認密碼：必須和上面的密碼一致（後端 confirmed 規則會檢查）。 --}}
                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation"
                        autocomplete="new-password" required>
                </div>
            </div>
        </div>
    </div>
@endunless

<div class="d-flex justify-content-end gap-2 mb-4">
    <a class="btn btn-outline-secondary" href="{{ route('users.index') }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.cancel') }}</a>
    <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>{{ __('common.buttons.save') }}</button>
</div>

{{-- 身分主檔列表頁（對應 RoleController::index）：關鍵字篩選 + 資料表格 + 分頁。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面（導覽列、側邊選單都由它提供）。 --}}
@extends('layouts.app')

@section('title', __('roles.index_title'))

{{-- 從這裡開始是「放進主版面中間」的內容，到 @endsection 結束。 --}}
@section('content')
    {{-- 頁面標題列：左邊標題、右邊「新增身分」按鈕。 --}}
    <div class="page-header mb-4">
        <h1 class="h4 mb-0"><i class="bi bi-shield-lock me-2"></i>{{ __('roles.index_title') }}</h1>
        <a class="btn btn-primary" href="{{ route('roles.create') }}">
            <i class="bi bi-plus-lg me-1"></i>{{ __('roles.add_role') }}
        </a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            {{-- 篩選表單：用 GET 送出，條件會出現在網址上。filter-bar 樣式讓篩選欄位固定單行、不換行。 --}}
            <form method="GET" action="{{ route('roles.index') }}" class="filter-bar">
                <div>
                    <label for="keyword" class="form-label small mb-1">{{ __('roles.filter.keyword') }}</label>
                    <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 15rem;"
                        value="{{ request('keyword') }}" placeholder="{{ __('roles.filter.keyword_placeholder') }}">
                </div>
                <div class="d-inline-flex gap-1">
                    <button class="btn btn-primary btn-sm icon-btn" type="submit"
                        title="{{ __('common.buttons.filter') }}" aria-label="{{ __('common.buttons.filter') }}"><i class="bi bi-funnel"></i></button>
                    {{-- 有輸入關鍵字時，才顯示「清除篩選」按鈕。 --}}
                    @if (request('keyword'))
                        <a class="btn btn-outline-secondary btn-sm icon-btn" href="{{ route('roles.index') }}"
                            title="{{ __('common.buttons.clear_filter') }}" aria-label="{{ __('common.buttons.clear_filter') }}"><i class="bi bi-x-circle"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- 查不到任何身分時顯示提示；否則顯示表格。 --}}
    @if ($roles->isEmpty())
        <div class="alert alert-info mb-0">{{ __('roles.empty_list') }}</div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                {{-- 資料表格（有框線，全站表格都要有框線）。 --}}
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('roles.table.name') }}</th>
                            <th>{{ __('roles.table.description') }}</th>
                            <th class="text-center">{{ __('roles.table.users') }}</th>
                            <th class="text-center">{{ __('roles.table.permissions') }}</th>
                            <th class="text-center">{{ __('roles.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- 逐一列出每個身分，每個身分一列。 --}}
                        @foreach ($roles as $role)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $role->name }}</span>
                                    {{-- 系統內建的身分加上「系統內建」徽章（內建身分不能刪除）。 --}}
                                    @if ($role->is_system)
                                        <span class="badge text-bg-info ms-1">{{ __('roles.system_badge') }}</span>
                                    @endif
                                </td>
                                <td>{{ $role->description ?? '—' }}</td>
                                {{-- 屬於這個身分的用戶數（Controller 用 withCount 先算好）。 --}}
                                <td class="text-center">{{ $role->users_count }}</td>
                                <td class="text-center">
                                    {{-- 系統管理員顯示「全部權限」；其他身分顯示「已勾選 N 項權限」。 --}}
                                    @if ($role->isAdmin())
                                        <span class="badge text-bg-primary">{{ __('roles.admin_all_permissions') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('roles.permission_count', ['count' => count($role->permissions ?? [])]) }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        {{-- 編輯按鈕（鉛筆圖示）。 --}}
                                        <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('roles.edit', $role) }}"
                                            title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
                                        {{-- 刪除按鈕（垃圾桶圖示）：送出前先確認；系統內建身分的按鈕是停用狀態（@disabled）。 --}}
                                        <form method="POST" action="{{ route('roles.destroy', $role) }}"
                                            onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('roles.confirm.delete', ['name' => $role->name])) }});">
                                            @csrf
                                            {{-- 用隱藏欄位把 POST 偽裝成 DELETE。 --}}
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm icon-btn" type="submit" @disabled($role->is_system)
                                                title="{{ __('common.buttons.delete') }}" aria-label="{{ __('common.buttons.delete') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{-- 分頁按鈕。 --}}
            {{ $roles->links() }}
        </div>
    @endif
@endsection

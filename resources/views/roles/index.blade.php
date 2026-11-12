{{-- 身分主檔列表頁（對應 RoleController::index）：關鍵字篩選 + 資料表格 + 分頁。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面（導覽列、側邊選單都由它提供）。 --}}
@extends('layouts.app')

@section('title', __('roles.index_title'))

{{-- 從這裡開始是「放進主版面中間」的內容，到 @endsection 結束。 --}}
@section('content')
<div class="tb">
    {{-- 頁面標題列：左邊標題、右邊「新增身分」按鈕。 --}}
    <div class="tb-header">
        <div>
            <h1 class="tb-title">{{ __('roles.index_title') }}</h1>
        </div>
        <a class="tb-btn tb-btn-primary" href="{{ route('roles.create') }}" data-modal-frame data-modal-title="{{ __('roles.add_role') }}" data-modal-size="lg">
            <i class="bi bi-plus-lg"></i>{{ __('roles.add_role') }}
        </a>
    </div>

    <div class="tb-card tb-card-pad">
        <div>
            {{-- 篩選表單：用 GET 送出，條件會出現在網址上。filter-bar 樣式讓篩選欄位固定單行、不換行。 --}}
            <form method="GET" action="{{ route('roles.index') }}" class="filter-bar tb-form-row tb-filter" data-no-onion>
                <div>
                    <label for="keyword" class="form-label small mb-1">{{ __('roles.filter.keyword') }}</label>
                    <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 15rem;"
                        value="{{ request('keyword') }}" placeholder="{{ __('roles.filter.keyword_placeholder') }}">
                </div>
                @include('layouts.partials.filter-actions', ['reset' => route('roles.index'), 'fields' => ['keyword']])
            </form>
        </div>
    </div>

    {{-- 查不到任何身分時顯示提示；否則顯示表格。 --}}
    @if ($roles->isEmpty())
        <div class="tb-card tb-empty">{{ __('roles.empty_list') }}</div>
    @else
        <section class="tb-table-wrap" data-no-fit>
            <div class="table-responsive">
                {{-- 資料表格（有框線，全站表格都要有框線）。 --}}
                <table class="tb-table tb-table-hover" data-onion-primary="2">
                    <thead>
                        <tr>
                            <th>{{ __('roles.table.name') }}</th>
                            <th>{{ __('roles.table.description') }}</th>
                            <th class="text-center">{{ __('roles.table.users') }}</th>
                            <th class="text-center">{{ __('roles.table.permissions') }}</th>
                            <th class="text-end tb-col-fit">{{ __('roles.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- 逐一列出每個身分，每個身分一列。 --}}
                        @foreach ($roles as $role)
                            {{-- 點這一列的任何地方都會在畫面中的對話框打開編輯頁（data-modal-frame-href，行為在共用版型）。 --}}
                            <tr data-modal-frame-href="{{ route('roles.edit', $role) }}" data-modal-title="修改身分" data-modal-size="lg" tabindex="0" role="link">
                                <td>
                                    <span class="fw-semibold">{{ $role->name }}</span>
                                    {{-- 系統內建的身分加上「系統內建」徽章（內建身分不能刪除）。 --}}
                                    @if ($role->is_system)
                                        <span class="badge tb-pill tb-pill-info ms-1">{{ __('roles.system_badge') }}</span>
                                    @endif
                                </td>
                                <td>{{ $role->description ?? '—' }}</td>
                                {{-- 屬於這個身分的用戶數（Controller 用 withCount 先算好）。 --}}
                                <td class="text-center">{{ $role->users_count }}</td>
                                <td class="text-center">
                                    {{-- 系統管理員顯示「全部權限」；其他身分顯示「已勾選 N 項權限」。 --}}
                                    @if ($role->isAdmin())
                                        <span class="badge tb-pill tb-pill-info">{{ __('roles.admin_all_permissions') }}</span>
                                    @else
                                        <span class="badge tb-pill tb-pill-muted">{{ __('roles.permission_count', ['count' => count($role->permissions ?? [])]) }}</span>
                                    @endif
                                </td>
                                <td class="text-end tb-col-fit">
                                    <div class="d-inline-flex gap-1">
                                        {{-- 編輯按鈕（鉛筆圖示）。 --}}
                                        <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('roles.edit', $role) }}" data-modal-frame data-modal-title="修改身分" data-modal-size="lg"
                                            title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
                                        {{-- 刪除按鈕（垃圾桶圖示）：送出前先確認；系統內建身分的按鈕是停用狀態（@disabled）。 --}}
                                        <form method="POST" action="{{ route('roles.destroy', $role) }}"
                                            data-confirm="{{ __('roles.confirm.delete', ['name' => $role->name]) }}">
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
        </section>

        <div>
            {{-- 分頁按鈕。 --}}
            @include('layouts.pager', ['paginator' => $roles])
        </div>
    @endif
</div>
@endsection

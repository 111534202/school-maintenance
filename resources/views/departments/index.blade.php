{{-- 部門主檔列表頁（對應 DepartmentController::index）：關鍵字與狀態篩選 + 資料表格 + 分頁。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面（導覽列、側邊選單都由它提供）。 --}}
@extends('layouts.app')

@section('title', __('departments.index_title'))

{{-- 從這裡開始是「放進主版面中間」的內容，到 @endsection 結束。 --}}
@section('content')
<div class="tb">
    {{-- 頁面標題列：左邊標題、右邊「新增部門」按鈕。 --}}
    <div class="tb-header">
        <div>
            <h1 class="tb-title">{{ __('departments.index_title') }}</h1>
        </div>
        <a class="tb-btn tb-btn-primary" href="{{ route('departments.create') }}" data-modal-frame data-modal-title="{{ __('departments.add_department') }}" data-modal-size="lg">
            <i class="bi bi-plus-lg"></i>{{ __('departments.add_department') }}
        </a>
    </div>

    <div class="tb-card tb-card-pad">
        <div>
            {{-- 篩選表單：用 GET 送出，條件會出現在網址上；filter-bar 樣式讓欄位固定單行、不換行。 --}}
            <form method="GET" action="{{ route('departments.index') }}" class="filter-bar tb-form-row tb-filter" data-no-onion>
                <div>
                    <label for="keyword" class="form-label small mb-1">{{ __('departments.filter.keyword') }}</label>
                    <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 15rem;"
                        value="{{ request('keyword') }}" placeholder="{{ __('departments.filter.keyword_placeholder') }}">
                </div>
                <div>
                    <label for="status" class="form-label small mb-1">{{ __('departments.filter.status') }}</label>
                    <select id="status" name="status" class="form-select form-select-sm" style="width: 8rem;">
                        <option value="">{{ __('departments.filter.status_all') }}</option>
                        {{-- 狀態選項：啟用中、已停用。 --}}
                        @foreach (['active', 'inactive'] as $statusOption)
                            <option value="{{ $statusOption }}" @selected(request('status') === $statusOption)>{{ __('departments.status.' . $statusOption) }}</option>
                        @endforeach
                    </select>
                </div>
                @include('layouts.partials.filter-actions', ['reset' => route('departments.index'), 'fields' => ['keyword', 'status']])
            </form>
        </div>
    </div>

    {{-- 查不到任何部門時顯示提示；否則顯示表格。 --}}
    @if ($departments->isEmpty())
        <div class="tb-card tb-empty">{{ __('departments.empty_list') }}</div>
    @else
        <section class="tb-table-wrap" data-no-fit>
            <div class="table-responsive">
                {{-- 資料表格（有框線，全站表格都要有框線）。 --}}
                <table class="tb-table tb-table-hover" data-onion-primary="1">
                    <thead>
                        <tr>
                            <th>{{ __('departments.table.code') }}</th>
                            <th>{{ __('departments.table.name') }}</th>
                            <th>{{ __('departments.table.description') }}</th>
                            <th class="text-center">{{ __('departments.table.users') }}</th>
                            <th class="text-center">{{ __('departments.table.classrooms') }}</th>
                            <th class="text-center">{{ __('departments.table.status') }}</th>
                            <th class="text-end tb-col-fit">{{ __('departments.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- 逐一列出每個部門，每個部門一列。 --}}
                        @foreach ($departments as $department)
                            {{-- 點這一列的任何地方都會在畫面中的對話框打開編輯頁（data-modal-frame-href，行為在共用版型）。 --}}
                            <tr data-modal-frame-href="{{ route('departments.edit', $department) }}" data-modal-title="修改部門" data-modal-size="lg" tabindex="0" role="link">
                                <td>{{ $department->department_code ?? '—' }}</td>
                                <td class="fw-semibold">{{ $department->name }}</td>
                                <td>{{ $department->description ?? '—' }}</td>
                                {{-- 這個部門的用戶數（Controller 用 withCount 先算好）。 --}}
                                <td class="text-center">{{ $department->users_count }}</td>
                                <td class="text-center">{{ $department->classrooms_count }}</td>
                                <td class="text-center">
                                    {{-- 啟用狀態徽章：綠色「啟用」或灰色「停用」。 --}}
                                    @if ($department->is_active)
                                        <span class="badge tb-pill tb-pill-ok">{{ __('departments.status.active') }}</span>
                                    @else
                                        <span class="badge tb-pill tb-pill-muted">{{ __('departments.status.inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end tb-col-fit">
                                    <div class="d-inline-flex gap-1">
                                        {{-- 編輯按鈕（鉛筆圖示）。 --}}
                                        <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('departments.edit', $department) }}" data-modal-frame data-modal-title="修改部門" data-modal-size="lg"
                                            title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>

                                        {{-- 啟用／停用切換：目前啟用中才會先跳出確認視窗。 --}}
                                        <form method="POST" action="{{ route('departments.toggle', $department) }}"
                                            @if ($department->is_active) data-confirm="{{ __('departments.confirm.deactivate', ['name' => $department->name]) }}" data-confirm-ok="確認停用" @endif>
                                            @csrf
                                            {{-- 用隱藏欄位把 POST 偽裝成 PATCH。 --}}
                                            @method('PATCH')
                                            <button class="btn btn-outline-warning btn-sm icon-btn" type="submit"
                                                title="{{ $department->is_active ? __('departments.actions.deactivate') : __('departments.actions.activate') }}"
                                                aria-label="{{ $department->is_active ? __('departments.actions.deactivate') : __('departments.actions.activate') }}">
                                                <i class="bi {{ $department->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                            </button>
                                        </form>

                                        {{-- 刪除按鈕（垃圾桶圖示）：送出前先確認；若還有用戶或教室屬於這個部門，後端會擋下並說明。 --}}
                                        <form method="POST" action="{{ route('departments.destroy', $department) }}"
                                            data-confirm="{{ __('departments.confirm.delete', ['name' => $department->name]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm icon-btn" type="submit"
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
            @include('layouts.pager', ['paginator' => $departments])
        </div>
    @endif
</div>
@endsection

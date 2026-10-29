{{-- 設備類別主檔列表頁（對應 DeviceCategoryController::index）：名稱搜尋 + 資料表格 + 分頁。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('device_categories.index_title'))

@section('content')
    <div class="page-header mb-4">
        <h1 class="h4 mb-0"><i class="bi bi-tags me-2"></i>{{ __('device_categories.index_title') }}</h1>
        <a class="btn btn-primary" href="{{ route('device-categories.create') }}">
            <i class="bi bi-plus-lg me-1"></i>{{ __('device_categories.add_category') }}
        </a>
    </div>

    {{-- 搜尋列：單行不換行，按鈕一律用圖示。 --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            {{-- 搜尋表單：用 GET 送出，關鍵字會出現在網址上。 --}}
            <form method="GET" action="{{ route('device-categories.index') }}" class="filter-bar">
                <div>
                    <label for="keyword" class="form-label small mb-1">{{ __('device_categories.filter.keyword') }}</label>
                    <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 15rem;"
                        value="{{ request('keyword') }}" placeholder="{{ __('device_categories.filter.keyword_placeholder') }}">
                </div>
                <div class="d-inline-flex gap-1">
                    <button class="btn btn-primary btn-sm icon-btn" type="submit"
                        title="{{ __('common.buttons.filter') }}" aria-label="{{ __('common.buttons.filter') }}"><i class="bi bi-funnel"></i></button>
                    @if (request('keyword'))
                        <a class="btn btn-outline-secondary btn-sm icon-btn" href="{{ route('device-categories.index') }}"
                            title="{{ __('common.buttons.clear_filter') }}" aria-label="{{ __('common.buttons.clear_filter') }}"><i class="bi bi-x-circle"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            {{-- 資料表格（有框線）。 --}}
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('device_categories.table.name') }}</th>
                        <th class="text-center">{{ __('device_categories.table.devices_count') }}</th>
                        <th class="text-center">{{ __('device_categories.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- 逐筆列出類別；一筆都沒有時改顯示 @empty 的提示。 --}}
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category->name }}</td>
                            {{-- 這個類別底下有幾台設備（Controller 用 withCount 先算好）。 --}}
                            <td class="text-center">{{ $category->devices_count }}</td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('device-categories.edit', $category) }}"
                                        title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
                                    {{-- 刪除按鈕：送出前先確認；還有設備在用的類別，後端會擋下並說明。 --}}
                                    <form method="POST" action="{{ route('device-categories.destroy', $category) }}"
                                        onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('device_categories.confirm.delete')) }});">
                                        @csrf
                                        {{-- 用隱藏欄位把 POST 偽裝成 DELETE。 --}}
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm icon-btn" type="submit"
                                            title="{{ __('common.buttons.delete') }}" aria-label="{{ __('common.buttons.delete') }}"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    {{-- 沒有任何資料時顯示的提示列。 --}}
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">{{ __('device_categories.empty_list') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 分頁按鈕。 --}}
    <div class="mt-3">{{ $categories->links() }}</div>
@endsection

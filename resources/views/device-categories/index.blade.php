{{-- 設備類別主檔列表頁（對應 DeviceCategoryController::index）：名稱搜尋 + 資料表格 + 分頁。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('device_categories.index_title'))

@section('content')
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">{{ __('device_categories.index_title') }}</h1>
        </div>
        <div class="d-flex gap-2">
            @include('charts._button', ['buttonClass' => 'tb-btn tb-btn-outline'])
            <a class="tb-btn tb-btn-primary" href="{{ route('device-categories.create') }}" data-modal-frame data-modal-title="{{ __('device_categories.add_category') }}">
                <i class="bi bi-plus-lg"></i>{{ __('device_categories.add_category') }}
            </a>
        </div>
    </div>

    {{-- 搜尋列：單行不換行，按鈕一律用圖示。 --}}
    <div class="tb-card tb-card-pad">
        <div>
            {{-- 搜尋表單：用 GET 送出，關鍵字會出現在網址上。 --}}
            <form method="GET" action="{{ route('device-categories.index') }}" class="filter-bar tb-form-row tb-filter" data-no-onion>
                <div>
                    <label for="keyword" class="form-label small mb-1">{{ __('device_categories.filter.keyword') }}</label>
                    {{-- 可以輸入，也可以從下拉清單直接選一個類別（data-suggest，行為在共用版型；選了就直接篩選）。 --}}
                    <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 15rem;"
                        value="{{ request('keyword') }}" placeholder="{{ __('device_categories.filter.keyword_placeholder') }}"
                        data-suggest='@json($categoryNames)' data-suggest-submit>
                </div>
                @include('layouts.partials.filter-actions', ['reset' => route('device-categories.index'), 'fields' => ['keyword']])
            </form>
        </div>
    </div>

    <section class="tb-table-wrap" data-no-fit>
            <div class="table-responsive">
            {{-- 資料表格（有框線）。 --}}
            <table class="tb-table tb-table-hover" data-onion-primary="1">
                <thead>
                    <tr>
                        <th>{{ __('device_categories.table.name') }}</th>
                        <th class="text-center">{{ __('device_categories.table.devices_count') }}</th>
                        <th class="text-end tb-col-fit">{{ __('device_categories.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- 逐筆列出類別；一筆都沒有時改顯示 @empty 的提示。 --}}
                    @forelse ($categories as $category)
                        {{-- 點這一列的任何地方都會在畫面中的對話框打開編輯頁（data-modal-frame-href，行為在共用版型）。 --}}
                        <tr data-modal-frame-href="{{ route('device-categories.edit', $category) }}" data-modal-title="修改設備類別" tabindex="0" role="link">
                            <td>{{ $category->name }}</td>
                            {{-- 這個類別底下有幾台設備（Controller 用 withCount 先算好）。 --}}
                            <td class="text-center">{{ $category->devices_count }}</td>
                            <td class="text-end tb-col-fit">
                                <div class="d-inline-flex gap-1">
                                    <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('device-categories.edit', $category) }}" data-modal-frame data-modal-title="修改設備類別"
                                        title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
                                    {{-- 刪除按鈕：送出前先確認；還有設備在用的類別，後端會擋下並說明。 --}}
                                    <form method="POST" action="{{ route('device-categories.destroy', $category) }}"
                                        data-confirm="{{ __('device_categories.confirm.delete', ['name' => $category->name]) }}">
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
    </section>

    {{-- 分頁按鈕。 --}}
    <div>@include('layouts.pager', ['paginator' => $categories])</div>
    {{-- 統計圖表視窗（資料來自 App\Services\MasterCharts）。 --}}
    @include('charts._modal')
</div>
@endsection

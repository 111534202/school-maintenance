{{-- 設備類別（對應 DeviceCategoryController::index）：上面「新增類別」填寫框，下面「現有類別」清單（名稱、使用中設備數、修改、刪除）。 --}}
{{-- 平常從設備主檔右上角的「新增類別」在畫面中的對話框打開（?modal=1：不顯示頁首、搜尋列與統計圖表）；選單已經沒有「設備類別」這一項。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
@extends('layouts.app')

@section('title', __('device_categories.index_title'))

@section('content')
@php $inModal = request()->boolean('modal'); @endphp
<div class="tb">
    {{-- 直接打開網址（不在對話框裡）時才顯示頁首、統計圖表與搜尋列。 --}}
    @unless ($inModal)
        <div class="tb-header">
            <div>
                <h1 class="tb-title">{{ __('device_categories.index_title') }}</h1>
            </div>
            <div class="d-flex gap-2">
                @include('charts._button', ['buttonClass' => 'tb-btn tb-btn-outline'])
            </div>
        </div>
    @endunless

    {{-- 新增類別：填名稱按「新增」。名稱和現有類別重複時會被擋下，錯誤訊息顯示在上方（DeviceCategoryController::store）。 --}}
    <div class="tb-card tb-card-pad">
        <h2 class="h6 fw-semibold mb-2">{{ __('device_categories.add_category') }}</h2>
        <form method="POST" action="{{ route('device-categories.store') }}" class="d-flex gap-2 align-items-start" data-no-onion>
            @csrf
            <div class="flex-grow-1">
                <label for="name" class="visually-hidden">{{ __('device_categories.form.name') }}</label>
                <input type="text" id="name" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                    value="{{ old('name') }}" placeholder="{{ __('device_categories.form.name_placeholder') }}" maxlength="255" required>
            </div>
            <button class="tb-btn tb-btn-primary text-nowrap" type="submit"><i class="bi bi-plus-lg"></i>{{ __('device_categories.form.submit') }}</button>
        </form>
    </div>

    {{-- 搜尋現有類別的名稱（對話框裡也有；送出時帶著 modal=1，結果留在對話框裡）。直接打開網址時，搜尋框旁邊有下拉選單可以直接選一個類別。 --}}
    {{-- 不加外框；上下各留 1rem（my-3），跟上面「新增類別」框、下面清單的距離相等。 --}}
    <div class="my-3">
        <form method="GET" action="{{ route('device-categories.index') }}" class="tb-form-row tb-form-row-nowrap" role="search" data-no-onion>
            @if ($inModal)<input type="hidden" name="modal" value="1">@endif
            <label for="keyword" class="visually-hidden">{{ __('device_categories.filter.keyword') }}</label>
            <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="flex: 1 1 0; min-width: 0"
                value="{{ request('keyword') }}" placeholder="{{ __('device_categories.filter.search_placeholder') }}"
                @unless ($inModal) data-suggest='@json($categoryNames)' data-suggest-submit @endunless>
            {{-- 手機上「搜尋」「清除」只留圖示，搜尋框才夠寬。 --}}
            <button type="submit" class="tb-btn tb-btn-dark text-nowrap" aria-label="{{ __('device_categories.filter.search') }}"><i class="bi bi-search" aria-hidden="true"></i><span class="d-none d-sm-inline">{{ __('device_categories.filter.search') }}</span></button>
            @if (request()->filled('keyword'))
                <a href="{{ route('device-categories.index', $inModal ? ['modal' => 1] : []) }}" class="tb-btn tb-btn-outline text-nowrap" data-modal-stay aria-label="{{ __('device_categories.filter.clear') }}"><i class="bi bi-x-lg d-sm-none" aria-hidden="true"></i><span class="d-none d-sm-inline">{{ __('device_categories.filter.clear') }}</span></a>
            @endif
        </form>
    </div>

    <section class="tb-table-wrap" data-no-fit aria-label="{{ __('device_categories.existing') }}">
        <div class="table-responsive">
            {{-- 對話框裡固定用表格（data-no-onion）：只有三欄，手機也放得下，修改、刪除鍵直接看得到；直接打開網址時照全站規則，窄螢幕轉清單。 --}}
            <table class="tb-table" data-onion-primary="1" @if ($inModal) data-no-onion @endif>
                <thead>
                    <tr>
                        {{-- 第一欄標題用「現有類別」（上面不另外放標題）。 --}}
                        <th>{{ __('device_categories.existing') }}</th>
                        {{-- 對話框裡把「使用中設備數」放在名稱下面一行（手機上三欄放不下），不另開一欄。 --}}
                        @unless ($inModal)
                            <th class="text-center">{{ __('device_categories.table.devices_count') }}</th>
                        @endunless
                        <th class="text-end tb-col-fit">{{ __('device_categories.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>
                                {{ $category->name }}
                                @if ($inModal)
                                    <div class="small text-muted">{{ __('device_categories.table.devices_count') }}：{{ $category->devices_count }}</div>
                                @endif
                            </td>
                            {{-- 使用中設備數：這個類別底下的設備（含已淘汰；已停用的設備不列在設備主檔，所以不算）。 --}}
                            @unless ($inModal)
                                <td class="text-center">{{ $category->devices_count }}</td>
                            @endunless
                            <td class="text-end tb-col-fit">
                                <div class="d-inline-flex gap-1">
                                    {{-- 修改：在對話框上再疊一層打開修改頁，存好回到這份清單。 --}}
                                    <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('device-categories.edit', $category) }}" data-modal-frame data-modal-title="修改設備類別"
                                        title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
                                    {{-- 刪除：還有使用中設備時按鈕停用（後端也會再檢查，連已停用的設備都算）。 --}}
                                    <form method="POST" action="{{ route('device-categories.destroy', $category) }}"
                                        data-confirm="{{ __('device_categories.confirm.delete', ['name' => $category->name]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm icon-btn" type="submit" @disabled($category->devices_count > 0)
                                            title="{{ $category->devices_count > 0 ? __('device_categories.errors.in_use') : __('common.buttons.delete') }}" aria-label="{{ __('common.buttons.delete') }}"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $inModal ? 2 : 3 }}" class="text-center text-muted py-4">{{ __('device_categories.empty_list') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 分頁按鈕；data-modal-stay：在對話框裡按分頁，就在對話框裡換頁。 --}}
    <div data-modal-stay>@include('layouts.pager', ['paginator' => $categories])</div>
    @unless ($inModal)
        {{-- 統計圖表視窗（資料來自 App\Services\MasterCharts）。 --}}
        @include('charts._modal')
    @endunless
</div>
@endsection

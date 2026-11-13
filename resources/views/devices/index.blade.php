{{-- 設備主檔列表頁（對應 DeviceController::index）：關鍵字、教室、類別、狀態篩選 + 資料表格 + 分頁。 --}}
{{-- 主控台的「設備狀態圖」點擊會帶 ?status=xxx 進到這一頁。（Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面（導覽列、側邊選單都由它提供）。 --}}
@extends('layouts.app')

@section('title', __('devices.index_title'))

{{-- 從這裡開始是「放進主版面中間」的內容，到 @endsection 結束。 --}}
@section('content')
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">{{ __('devices.index_title') }}</h1>
        </div>
        <div class="d-flex gap-2">
            @include('charts._button', ['buttonClass' => 'tb-btn tb-btn-outline'])
            <a class="tb-btn tb-btn-primary" href="{{ route('devices.create') }}" data-modal-frame data-modal-title="{{ __('devices.add_device') }}" data-modal-size="xl">
                <i class="bi bi-plus-lg"></i>{{ __('devices.add_device') }}
            </a>
        </div>
    </div>

    {{-- 篩選列：直接顯示在頁面上（data-no-onion），放得下就排成一列，不夠寬時換行（tb-filter，不用左右捲動），按鈕一律用圖示。 --}}
    <div class="tb-card tb-card-pad">
        <div>
            {{-- 篩選表單：用 GET 送出，條件會出現在網址上。 --}}
            <form method="GET" action="{{ route('devices.index') }}" class="filter-bar tb-form-row tb-filter" data-no-onion>
                <div>
                    <label for="keyword" class="form-label small mb-1">{{ __('devices.filter.keyword') }}</label>
                    <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 16rem;"
                        value="{{ request('keyword') }}" placeholder="{{ __('devices.filter.keyword_placeholder') }}">
                </div>
                <div>
                    <label for="classroom_id" class="form-label small mb-1">{{ __('devices.filter.classroom') }}</label>
                    <select id="classroom_id" name="classroom_id" class="form-select form-select-sm" style="width: 13rem;">
                        <option value="">{{ __('devices.filter.classroom_all') }}</option>
                        {{-- 教室下拉選單的選項。 --}}
                        @foreach ($classrooms as $classroom)
                            <option value="{{ $classroom->id }}" @selected((string) request('classroom_id') === (string) $classroom->id)>{{ $classroom->classroom_code }} - {{ $classroom->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="device_category_id" class="form-label small mb-1">{{ __('devices.filter.category') }}</label>
                    <select id="device_category_id" name="device_category_id" class="form-select form-select-sm" style="width: 10rem;">
                        <option value="">{{ __('devices.filter.category_all') }}</option>
                        {{-- 設備類別下拉選單的選項。 --}}
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) request('device_category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="form-label small mb-1">{{ __('devices.filter.status') }}</label>
                    <select id="status" name="status" class="form-select form-select-sm" style="width: 9rem;">
                        <option value="">{{ __('devices.filter.status_all') }}</option>
                        {{-- 狀態下拉選單的選項：列出 Device::STATUSES 的每個狀態，顯示名稱用 Device::statusLabel() 轉成中文。 --}}
                        @foreach (\App\Models\Device::STATUSES as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Models\Device::statusLabel($status) }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- 從教室主檔連過來只看異常設備時（?abnormal=1），按「篩選」要一併帶著，才不會篩一次就跑掉。 --}}
                @if (request('abnormal'))
                    <input type="hidden" name="abnormal" value="1">
                @endif
                @include('layouts.partials.filter-actions', ['reset' => route('devices.index'), 'fields' => ['keyword', 'classroom_id', 'device_category_id', 'status', 'abnormal']])
            </form>
        </div>
    </div>

    {{-- 只看異常設備時的提醒（異常 = 維修中、已淘汰、停用），並提供清除的連結。 --}}
    @if (request('abnormal'))
        <div class="alert alert-warning py-2 d-flex justify-content-between align-items-center">
            <span><i class="bi bi-exclamation-triangle me-2"></i>{{ __('devices.filter.only_abnormal') }}</span>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('devices.index', request()->except(['abnormal', 'page'])) }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.clear_filter') }}</a>
        </div>
    @endif

    <section class="tb-table-wrap" data-no-fit>
            <div class="table-responsive">
            {{-- 資料表格（有框線）。 --}}
            <table class="tb-table tb-table-hover" data-onion-primary="3,6">
                <thead>
                    <tr>
                        <th>{{ __('devices.table.code') }}</th>
                        <th>{{ __('devices.table.category') }}</th>
                        <th>{{ __('devices.table.brand_model') }}</th>
                        <th>{{ __('devices.table.classroom') }}</th>
                        <th class="text-center">{{ __('devices.table.status') }}</th>
                        <th class="text-center">{{ __('devices.table.core') }}</th>
                        <th class="text-center">{{ __('devices.table.open_repairs') }}</th>
                        <th class="text-end tb-col-fit">{{ __('devices.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- 逐筆列出設備；一筆都沒有時改顯示 @empty 的提示。 --}}
                    @forelse ($devices as $device)
                        <tr data-modal-frame-href="{{ route('devices.show', $device) }}" data-modal-title="設備詳細" data-modal-size="xl" tabindex="0" role="link">
                            <td>
                                <a href="{{ route('devices.show', $device) }}" class="text-decoration-none">{{ $device->device_code }}</a>
                                {{-- 重複報修提醒：近 30 天報修達到門檻次數，點了跳到報修看板只看這台設備。 --}}
                                @if ($device->recent_repairs_count >= \App\Models\Device::REPEAT_REPAIR_THRESHOLD)
                                    <a href="{{ route('repairs.index', ['device_id' => $device->id]) }}" class="d-block small text-danger text-decoration-none"
                                        title="{{ __('devices.repeat_repair_hint') }}">
                                        <i class="bi bi-arrow-repeat"></i> {{ __('devices.repeat_repair_badge', ['count' => $device->recent_repairs_count]) }}
                                    </a>
                                @endif
                            </td>
                            <td>{{ $device->category->name ?? __('devices.no_value') }}</td>
                            <td>{{ $device->brand }} {{ $device->model }}</td>
                            <td>{{ $device->classroom->name ?? __('devices.no_value') }}</td>
                            {{-- 狀態徽章依狀態上色：正常綠、維修中黃、已淘汰灰、停用紅（和主控台的設備狀態圖同一套顏色）。 --}}
                            <td class="text-center"><span class="badge {{ \App\Models\Device::statusBadgeClass($device->status) }}">{{ \App\Models\Device::statusLabel($device->status) }}</span></td>
                            <td class="text-center">
                                {{-- 核心設備才顯示「核心」徽章。 --}}
                                @if ($device->is_core)
                                    <span class="badge tb-pill tb-pill-warn">{{ __('devices.core_badge') }}</span>
                                @endif
                            </td>
                            {{-- 這台設備進行中的報修單數（不含已結案）：大於 0 顯示藍色徽章，點了跳到報修看板，只看這台設備的報修單。 --}}
                            <td class="text-center">
                                @if ($device->open_repairs_count > 0)
                                    <a class="badge tb-pill tb-pill-info text-decoration-none" href="{{ route('repairs.index', ['device_id' => $device->id]) }}"
                                        title="{{ __('devices.links.view_repairs') }}"><i class="bi bi-tools me-1"></i>{{ $device->open_repairs_count }}</a>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td class="text-end tb-col-fit">
                                <div class="d-inline-flex gap-1">
                                    <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('devices.edit', $device) }}" data-modal-frame data-modal-title="修改設備" data-modal-size="lg"
                                        title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
                                    {{-- 停用按鈕：送出前先跳出全站共用的確認視窗；停用 = 狀態改 disabled 並軟刪除。
                                         （詳細資料：點這一列在對話框打開，所以不另外放「查看」按鈕。） --}}
                                    <form method="POST" action="{{ route('devices.disable', $device) }}"
                                        data-confirm="{{ __('devices.confirm.disable') }}" data-confirm-ok="確認停用">
                                        @csrf
                                        {{-- 用隱藏欄位把 POST 偽裝成 PATCH。 --}}
                                        @method('PATCH')
                                        <button class="btn btn-outline-danger btn-sm icon-btn" type="submit"
                                            title="{{ __('devices.actions.disable') }}" aria-label="{{ __('devices.actions.disable') }}"><i class="bi bi-slash-circle"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    {{-- 沒有任何資料時顯示的提示列。 --}}
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">{{ __('devices.empty_list') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 分頁按鈕。 --}}
    <div>@include('layouts.pager', ['paginator' => $devices])</div>
    {{-- 統計圖表視窗（資料來自 App\Services\MasterCharts）。 --}}
    @include('charts._modal')
</div>
@endsection

{{-- 設備主檔列表頁（對應 DeviceController::index）：關鍵字、教室、類別、狀態篩選 + 資料表格 + 分頁。 --}}
{{-- 主控台的「設備狀態圖」點擊會帶 ?status=xxx 進到這一頁。（Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面（導覽列、側邊選單都由它提供）。 --}}
@extends('layouts.app')

@section('title', __('devices.index_title'))

{{-- 從這裡開始是「放進主版面中間」的內容，到 @endsection 結束。 --}}
@section('content')
    <div class="page-header mb-4">
        <h1 class="h4 mb-0"><i class="bi bi-pc-display me-2"></i>{{ __('devices.index_title') }}</h1>
        <a class="btn btn-primary" href="{{ route('devices.create') }}">
            <i class="bi bi-plus-lg me-1"></i>{{ __('devices.add_device') }}
        </a>
    </div>

    {{-- 篩選列：全部放同一排、不換行（視窗太窄時整列左右捲動），按鈕一律用圖示。 --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            {{-- 篩選表單：用 GET 送出，條件會出現在網址上。 --}}
            <form method="GET" action="{{ route('devices.index') }}" class="filter-bar">
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
                            <option value="{{ $classroom->id }}" @selected((string) request('classroom_id') === (string) $classroom->id)>{{ $classroom->room_code }} - {{ $classroom->room_name }}</option>
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
                <div class="d-inline-flex gap-1">
                    <button class="btn btn-primary btn-sm icon-btn" type="submit"
                        title="{{ __('common.buttons.filter') }}" aria-label="{{ __('common.buttons.filter') }}"><i class="bi bi-funnel"></i></button>
                    {{-- 有套用任何篩選條件時，才顯示「清除篩選」按鈕。 --}}
                    @if (request('keyword') || request('classroom_id') || request('device_category_id') || request('status') || request('abnormal'))
                        <a class="btn btn-outline-secondary btn-sm icon-btn" href="{{ route('devices.index') }}"
                            title="{{ __('common.buttons.clear_filter') }}" aria-label="{{ __('common.buttons.clear_filter') }}"><i class="bi bi-x-circle"></i></a>
                    @endif
                </div>
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

    <div class="card shadow-sm">
        <div class="table-responsive">
            {{-- 資料表格（有框線）。 --}}
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('devices.table.code') }}</th>
                        <th>{{ __('devices.table.category') }}</th>
                        <th>{{ __('devices.table.brand_model') }}</th>
                        <th>{{ __('devices.table.classroom') }}</th>
                        <th class="text-center">{{ __('devices.table.status') }}</th>
                        <th class="text-center">{{ __('devices.table.core') }}</th>
                        <th class="text-center">{{ __('devices.table.open_repairs') }}</th>
                        <th class="text-center">{{ __('devices.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- 逐筆列出設備；一筆都沒有時改顯示 @empty 的提示。 --}}
                    @forelse ($devices as $device)
                        <tr>
                            <td><a href="{{ route('devices.show', $device) }}" class="text-decoration-none">{{ $device->device_code }}</a></td>
                            <td>{{ $device->category->name ?? __('devices.no_value') }}</td>
                            <td>{{ $device->brand }} {{ $device->model }}</td>
                            <td>{{ $device->classroom->room_name ?? __('devices.no_value') }}</td>
                            {{-- 狀態徽章依狀態上色：正常綠、維修中黃、已淘汰灰、停用紅（和主控台的設備狀態圖同一套顏色）。 --}}
                            <td class="text-center"><span class="badge {{ \App\Models\Device::statusBadgeClass($device->status) }}">{{ \App\Models\Device::statusLabel($device->status) }}</span></td>
                            <td class="text-center">
                                {{-- 核心設備才顯示「核心」徽章。 --}}
                                @if ($device->is_core)
                                    <span class="badge text-bg-warning">{{ __('devices.core_badge') }}</span>
                                @endif
                            </td>
                            {{-- 這台設備進行中的報修單數（不含已結案）：大於 0 顯示藍色徽章，點了跳到報修看板，只看這台設備的報修單。 --}}
                            <td class="text-center">
                                @if ($device->open_repairs_count > 0)
                                    <a class="badge text-bg-primary text-decoration-none" href="{{ route('repairs.index', ['device_id' => $device->id]) }}"
                                        title="{{ __('devices.links.view_repairs') }}"><i class="bi bi-tools me-1"></i>{{ $device->open_repairs_count }}</a>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a class="btn btn-outline-secondary btn-sm icon-btn" href="{{ route('devices.show', $device) }}"
                                        title="{{ __('devices.actions.detail') }}" aria-label="{{ __('devices.actions.detail') }}"><i class="bi bi-eye"></i></a>
                                    <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('devices.edit', $device) }}"
                                        title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
                                    {{-- 停用按鈕：送出前先跳出確認視窗；停用 = 狀態改 disabled 並軟刪除。 --}}
                                    <form method="POST" action="{{ route('devices.disable', $device) }}"
                                        onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('devices.confirm.disable')) }});">
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
    </div>

    {{-- 分頁按鈕。 --}}
    <div class="mt-3">{{ $devices->links() }}</div>
@endsection

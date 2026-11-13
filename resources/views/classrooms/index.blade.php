{{-- 教室主檔列表頁（對應 ClassroomController::index）：關鍵字、部門、啟用狀態篩選 + 資料表格 + 分頁。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面（導覽列、側邊選單都由它提供）。 --}}
@extends('layouts.app')

@section('title', __('classrooms.index_title'))

{{-- 從這裡開始是「放進主版面中間」的內容，到 @endsection 結束。 --}}
@section('content')
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">{{ __('classrooms.index_title') }}</h1>
        </div>
        <div class="d-flex gap-2">
            @include('charts._button', ['buttonClass' => 'tb-btn tb-btn-outline'])
            <a class="tb-btn tb-btn-primary" href="{{ route('classrooms.create') }}" data-modal-frame data-modal-title="{{ __('classrooms.add_classroom') }}" data-modal-size="lg">
                <i class="bi bi-plus-lg"></i>{{ __('classrooms.add_classroom') }}
            </a>
        </div>
    </div>

    {{-- 篩選列：直接顯示在頁面上（data-no-onion），放得下就排成一列，不夠寬時換行（tb-filter，不用左右捲動），按鈕一律用圖示。 --}}
    <div class="tb-card tb-card-pad">
        <div>
            {{-- 篩選表單：用 GET 送出，條件會出現在網址上；request('欄位') 把目前的條件帶回輸入框。 --}}
            <form method="GET" action="{{ route('classrooms.index') }}" class="filter-bar tb-form-row tb-filter" data-no-onion>
                <div>
                    <label for="keyword" class="form-label small mb-1">{{ __('classrooms.filter.keyword') }}</label>
                    <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 15rem;"
                        value="{{ request('keyword') }}" placeholder="{{ __('classrooms.filter.keyword_placeholder') }}">
                </div>
                <div>
                    <label for="department_id" class="form-label small mb-1">{{ __('classrooms.filter.department') }}</label>
                    <select id="department_id" name="department_id" class="form-select form-select-sm" style="width: 10rem;">
                        <option value="">{{ __('classrooms.filter.department_all') }}</option>
                        {{-- 部門下拉選單的選項（Controller 傳進來的全部部門）。 --}}
                        @foreach ($departments as $department)
                            {{-- @selected：這個選項和網址上的條件相同就標記為已選取。 --}}
                            <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="is_active" class="form-label small mb-1">{{ __('classrooms.filter.status') }}</label>
                    <select id="is_active" name="is_active" class="form-select form-select-sm" style="width: 8rem;">
                        <option value="">{{ __('classrooms.filter.status_all') }}</option>
                        <option value="1" @selected(request('is_active') === '1')>{{ __('classrooms.status.active') }}</option>
                        <option value="0" @selected(request('is_active') === '0')>{{ __('classrooms.status.inactive') }}</option>
                    </select>
                </div>
                <div>
                    <label for="abnormal" class="form-label small mb-1">{{ __('classrooms.filter.devices') }}</label>
                    {{-- 設備狀況：選「有異常設備」只列出至少有一台異常設備的教室（定義見 DeviceStatusService::PROBLEM_STATUSES）。 --}}
                    <select id="abnormal" name="abnormal" class="form-select form-select-sm" style="width: 9rem;">
                        <option value="">{{ __('classrooms.filter.devices_all') }}</option>
                        <option value="1" @selected(request('abnormal') === '1')>{{ __('classrooms.filter.devices_abnormal') }}</option>
                    </select>
                </div>
                @include('layouts.partials.filter-actions', ['reset' => route('classrooms.index'), 'fields' => ['keyword', 'department_id', 'is_active', 'abnormal']])
            </form>
        </div>
    </div>

    <section class="tb-table-wrap" data-no-fit>
            <div class="table-responsive">
            {{-- 資料表格（有框線）。 --}}
            <table class="tb-table tb-table-hover" data-onion-primary="1,6,7">
                <thead>
                    <tr>
                        <th>{{ __('classrooms.table.code') }}</th>
                        <th>{{ __('classrooms.table.name') }}</th>
                        <th>{{ __('classrooms.table.department') }}</th>
                        <th>{{ __('classrooms.table.location') }}</th>
                        <th>{{ __('classrooms.table.manager') }}</th>
                        <th class="text-center">{{ __('classrooms.table.devices') }}</th>
                        <th class="text-center">{{ __('classrooms.table.abnormal_devices') }}</th>
                        <th class="text-center">{{ __('classrooms.table.open_repairs') }}</th>
                        <th class="text-center">{{ __('classrooms.table.status') }}</th>
                        <th class="text-end tb-col-fit">{{ __('classrooms.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- @forelse：逐筆列出教室；一筆都沒有時改顯示下面 @empty 的「尚無教室資料」。 --}}
                    @forelse ($classrooms as $classroom)
                        {{-- 有異常設備的教室整列用淡紅色標示，一眼就能看出是哪間教室。 --}}
                        {{-- 點這一列的任何地方都會在畫面中的對話框打開編輯頁（data-modal-frame-href，行為在共用版型）。 --}}
                        <tr class="{{ $classroom->abnormal_devices_count > 0 ? 'table-danger' : '' }}" data-modal-frame-href="{{ route('classrooms.edit', $classroom) }}" data-modal-title="修改教室" data-modal-size="lg" tabindex="0" role="link">
                            <td>{{ $classroom->classroom_code }}</td>
                            <td>{{ $classroom->name }}</td>
                            <td>{{ $classroom->department->name ?? __('classrooms.no_value') }}</td>
                            <td>{{ $classroom->campus }} / {{ $classroom->building }} / {{ $classroom->floor }}</td>
                            <td>{{ $classroom->manager->name ?? __('classrooms.no_value') }}</td>
                            {{-- 設備數：點了跳到設備主檔，只看這間教室的設備。 --}}
                            <td class="text-center">
                                <a class="text-decoration-none" href="{{ route('devices.index', ['classroom_id' => $classroom->id]) }}"
                                    title="{{ __('classrooms.links.view_devices') }}">{{ $classroom->devices_count }}</a>
                            </td>
                            {{-- 異常設備數：大於 0 顯示紅色徽章，點了跳到設備主檔，只看這間教室的異常設備；
                                 如果其中有「核心設備」異常（教室被標成設備異常，會影響預約判斷），另外加一個「核心」徽章。 --}}
                            <td class="text-center">
                                @if ($classroom->abnormal_devices_count > 0)
                                    <a class="badge tb-pill tb-pill-danger text-decoration-none" href="{{ route('devices.index', ['classroom_id' => $classroom->id, 'abnormal' => 1]) }}"
                                        title="{{ __('classrooms.links.view_abnormal_devices') }}"><i class="bi bi-exclamation-triangle me-1"></i>{{ $classroom->abnormal_devices_count }}</a>
                                    @if ($classroom->reservation_status === 'abnormal')
                                        <span class="badge tb-pill tb-pill-muted" title="{{ __('classrooms.links.core_abnormal_hint') }}">{{ __('classrooms.core_abnormal') }}</span>
                                    @endif
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            {{-- 進行中的報修單數（不含已結案）：大於 0 顯示藍色徽章，點了跳到報修看板，只看這間教室設備的報修單。 --}}
                            <td class="text-center">
                                @if ($classroom->open_repairs_count > 0)
                                    <a class="badge tb-pill tb-pill-info text-decoration-none" href="{{ route('repairs.index', ['classroom_id' => $classroom->id]) }}"
                                        title="{{ __('classrooms.links.view_repairs') }}"><i class="bi bi-tools me-1"></i>{{ $classroom->open_repairs_count }}</a>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td class="text-center">
                                {{-- 啟用狀態徽章：綠色「啟用中」或灰色「已停用」。 --}}
                                @if ($classroom->is_active)
                                    <span class="badge tb-pill tb-pill-ok">{{ __('classrooms.status.active') }}</span>
                                @else
                                    <span class="badge tb-pill tb-pill-muted">{{ __('classrooms.status.inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-end tb-col-fit">
                                <div class="d-inline-flex gap-1">
                                    {{-- 編輯按鈕（鉛筆圖示）。 --}}
                                    <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('classrooms.edit', $classroom) }}" data-modal-frame data-modal-title="修改教室" data-modal-size="lg"
                                        title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
                                    {{-- 啟用／停用切換按鈕（教室不提供刪除，只能停用）。 --}}
                                    <form method="POST" action="{{ route('classrooms.toggle', $classroom) }}">
                                        @csrf
                                        {{-- 用隱藏欄位把 POST 偽裝成 PATCH。 --}}
                                        @method('PATCH')
                                        <button class="btn btn-outline-warning btn-sm icon-btn" type="submit"
                                            title="{{ $classroom->is_active ? __('classrooms.actions.deactivate') : __('classrooms.actions.activate') }}"
                                            aria-label="{{ $classroom->is_active ? __('classrooms.actions.deactivate') : __('classrooms.actions.activate') }}">
                                            <i class="bi {{ $classroom->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    {{-- 沒有任何資料時顯示的一列提示（colspan=10 讓它橫跨全部 10 欄）。 --}}
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">{{ __('classrooms.empty_list') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 分頁按鈕。 --}}
    <div>@include('layouts.pager', ['paginator' => $classrooms])</div>
    {{-- 統計圖表視窗（資料來自 App\Services\MasterCharts）。 --}}
    @include('charts._modal')
</div>
@endsection

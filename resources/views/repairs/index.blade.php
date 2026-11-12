{{-- 維修案件看板（報修單列表，對應 RepairRequestController::index）：狀態／地點／維修人員篩選 + 資料表格 + 分頁。 --}}
{{-- 主控台的圖表、通知鈴鐺的連結會帶 ?status=xxx 或 ?assignee=xxx 進到這一頁。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('repair_requests.board_title'))

@section('content')
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">{{ __('repair_requests.board_title') }}</h1>
        </div>
        {{-- 有「提出報修」權限才顯示「新增報修」按鈕。 --}}
        @can('repairs.create')
            <a class="tb-btn tb-btn-primary" href="{{ route('repairs.create') }}" data-modal-frame data-modal-title="{{ __('repair_requests.add_request') }}" data-modal-size="xl">
                <i class="bi bi-plus-lg"></i>{{ __('repair_requests.add_request') }}
            </a>
        @endcan
    </div>

    {{-- 篩選列：直接顯示在頁面上（data-no-onion），放得下就排成一列，不夠寬時換行（tb-filter，不用左右捲動），按鈕一律用圖示。 --}}
    <div class="tb-card tb-card-pad">
        <div>
            <form method="GET" action="{{ route('repairs.index') }}" class="filter-bar tb-form-row tb-filter" data-no-onion>
                <div>
                    <label for="status" class="form-label small mb-1">{{ __('repair_requests.filter.status') }}</label>
                    <select id="status" name="status" class="form-select form-select-sm" style="width: 9rem;">
                        <option value="">{{ __('repair_requests.filter.status_all') }}</option>
                        {{-- 狀態下拉選單：列出所有案件狀態（新報修、已派工、處理中、待驗收、已結案）。 --}}
                        @foreach ($statuses as $statusOption)
                            <option value="{{ $statusOption->value }}" @selected(request('status') === $statusOption->value)>
                                {{ $statusOption->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="classroom_id" class="form-label small mb-1">{{ __('repair_requests.filter.classroom') }}</label>
                    {{-- 教室下拉選單：只看「這間教室裡的設備」的報修單（教室主檔的「進行中工單」數字會連到這裡）。 --}}
                    <select id="classroom_id" name="classroom_id" class="form-select form-select-sm" style="width: 12rem;">
                        <option value="">{{ __('repair_requests.filter.classroom_all') }}</option>
                        @foreach ($classrooms as $classroom)
                            <option value="{{ $classroom->id }}" @selected((string) request('classroom_id') === (string) $classroom->id)>{{ $classroom->classroom_code }} - {{ $classroom->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="assignee" class="form-label small mb-1">{{ __('repair_requests.filter.assignee') }}</label>
                    <input type="text" id="assignee" name="assignee" class="form-control form-control-sm" style="width: 11rem;"
                        value="{{ request('assignee') }}" placeholder="{{ __('repair_requests.filter.assignee_placeholder') }}">
                </div>
                {{-- 從設備主檔連過來時網址帶有 device_id；按「篩選」時要一併帶著，才不會篩一次就跑掉。 --}}
                @if (request('device_id'))
                    <input type="hidden" name="device_id" value="{{ request('device_id') }}">
                @endif
                {{-- 從主控台「逾時工單」卡片連過來時網址帶有 overdue=1，同樣要帶著，不然一篩選就被洗掉。 --}}
                @if (request('overdue'))
                    <input type="hidden" name="overdue" value="1">
                @endif
                @include('layouts.partials.filter-actions', ['reset' => route('repairs.index'), 'fields' => ['status', 'assignee', 'classroom_id', 'device_id', 'overdue']])
            </form>
        </div>
    </div>

    {{-- 從主控台「逾時工單」卡片連過來（?overdue=1）時，提醒目前篩選範圍。 --}}
    @if (request('overdue'))
        <div class="alert alert-danger py-2 d-flex justify-content-between align-items-center">
            <span><i class="bi bi-alarm me-2"></i>{{ __('repair_requests.filter.only_overdue') }}</span>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('repairs.index', request()->except(['overdue', 'page'])) }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.clear_filter') }}</a>
        </div>
    @endif

    {{-- 從設備主檔連過來（?device_id=）時，提醒「目前只看這台設備」，並提供清除的連結。 --}}
    @if ($filteredDevice)
        <div class="alert alert-info py-2 d-flex justify-content-between align-items-center">
            <span><i class="bi bi-pc-display me-2"></i>{{ __('repair_requests.filter.only_device', ['device' => $filteredDevice->device_code]) }}</span>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('repairs.index', request()->except(['device_id', 'page'])) }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.clear_filter') }}</a>
        </div>
    @endif

    {{-- 查不到任何案件時顯示提示；否則顯示表格。 --}}
    @if ($repairRequests->isEmpty())
        <div class="tb-card tb-empty">{{ __('repair_requests.empty_list') }}</div>
    @else
        <section class="tb-table-wrap" data-no-fit>
            <div class="table-responsive">
                {{-- 資料表格（有框線）。 --}}
                <table class="tb-table tb-table-hover" data-onion-primary="1,5">
                    <thead>
                        <tr>
                            <th>{{ __('repair_requests.table.title') }}</th>
                            <th>{{ __('repair_requests.table.device_location') }}</th>
                            <th class="text-center">{{ __('repair_requests.table.impact_level') }}</th>
                            <th class="text-center">{{ __('repair_requests.table.is_class_affected') }}</th>
                            <th class="text-center">{{ __('repair_requests.table.status') }}</th>
                            <th>{{ __('repair_requests.table.assignee') }}</th>
                            <th>{{ __('repair_requests.table.submitted_at') }}</th>
                            <th>{{ __('repair_requests.table.waiting_time') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- 逐一列出每張報修單。 --}}
                        @foreach ($repairRequests as $repairRequest)
                            @php
                                // 影響程度 → 徽章顏色：輕微綠、中等黃、嚴重紅；未知值用灰色。
                                $impactBadge = ['low' => 'text-bg-success', 'medium' => 'text-bg-warning', 'high' => 'text-bg-danger'][$repairRequest->impact_level] ?? 'text-bg-secondary';
                            @endphp
                            {{-- 點這一列的任何地方都會在畫面中的對話框打開案件詳細（data-modal-frame-href，行為在共用版型）。 --}}
                            <tr data-modal-frame-href="{{ route('repairs.show', $repairRequest) }}" data-modal-title="報修案件" data-modal-size="xl" tabindex="0" role="link">
                                <td class="fw-semibold">{{ $repairRequest->title }}</td>
                                {{-- 設備欄：有綁定設備就顯示設備編號，否則顯示手填的設備描述；下面一行是地點（報修單的設備地點，沒有就用設備所在教室）。 --}}
                                @php($rowClassroom = $repairRequest->classroom ?? $repairRequest->device?->classroom)
                                <td>
                                    {{ $repairRequest->device->device_code ?? $repairRequest->device_note ?? ($rowClassroom ? '' : __('repair_requests.not_filled')) }}
                                    @if ($rowClassroom)
                                        <div class="small text-muted">{{ $rowClassroom->classroom_code }} {{ $rowClassroom->name }}</div>
                                    @endif
                                    {{-- 重複報修提醒：這台設備近 30 天報修達到門檻次數，看板上就能直接看出來，不用再切去設備主檔查。 --}}
                                    @if (($repairRequest->device->recent_repairs_count ?? 0) >= \App\Models\Device::REPEAT_REPAIR_THRESHOLD)
                                        <div class="small text-danger" title="{{ __('devices.repeat_repair_hint') }}">
                                            <i class="bi bi-arrow-repeat"></i> {{ __('devices.repeat_repair_badge', ['count' => $repairRequest->device->recent_repairs_count]) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $impactBadge }}">{{ __('repair_requests.impact_level.' . $repairRequest->impact_level) }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($repairRequest->is_class_affected)
                                        <span class="badge tb-pill tb-pill-danger">{{ __('repair_requests.yes') }}</span>
                                    @else
                                        <span class="badge tb-pill tb-pill-muted">{{ __('repair_requests.no') }}</span>
                                    @endif
                                </td>
                                {{-- 引入共用的狀態徽章（看板與詳細頁用同一份，顏色集中管理）。 --}}
                                <td class="text-center">
                                    @include('repairs._status-badge', ['status' => $repairRequest->status])
                                    {{-- 逾時提醒：還沒結案、且已經超過預計處理時間（見 RepairRequest::isOverdue）。 --}}
                                    @if ($repairRequest->is_overdue)
                                        <span class="badge tb-pill tb-pill-danger ms-1"><i class="bi bi-alarm"></i> {{ __('repair_requests.overdue') }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{-- 讓主管看到「這個人手上還有幾件沒結案」，跟名字放在同一格，方便判斷要不要再加派；
                                         系統不會自動幫忙排序或推薦人選。 --}}
                                    {{-- 維修人員：優先顯示指派的真實用戶姓名，沒有就用文字備註，再沒有顯示「未指派」。 --}}
                                    {{ $repairRequest->assignedTechnician->name ?? __('repair_requests.unassigned') }}
                                    {{-- 顯示該維修人員「手上還有 N 件未結案」，讓主管判斷是否要再加派（只顯示數字，不自動推薦）。 --}}
                                    @if ($repairRequest->assigned_to && ($activeCaseCountsByAssignee[$repairRequest->assigned_to] ?? 0) > 0)
                                        <span class="text-muted small">{{ __('repair_requests.active_case_suffix', ['count' => $activeCaseCountsByAssignee[$repairRequest->assigned_to]]) }}</span>
                                    @endif
                                </td>
                                <td>{{ $repairRequest->created_at->format('Y-m-d H:i') }}</td>
                                {{-- 等待時間：用「3 小時前」這種口語方式顯示；語言由 SetLocale 中介層決定。 --}}
                                <td>{{ $repairRequest->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div>
            {{-- 分頁按鈕。 --}}
            @include('layouts.pager', ['paginator' => $repairRequests])
        </div>
    @endif
</div>
@endsection

{{-- 報修單詳細頁（對應 RepairRequestController::show）：左欄是設備資料、故障描述與附件；右欄是「依目前狀態、依你的權限」才出現的派工與處理；最下面是維修紀錄與材料費明細。 --}}
{{-- 案件狀態流程：新報修 → 已派工 → 處理中 → 待驗收 → 已結案（驗收不通過會退回處理中），規則在 App\Services\RepairRequestWorkflow。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', $repairRequest->title)

@php
    // 影響程度 → 徽章顏色：輕微綠、中等黃、嚴重紅；未知值用灰色。
    $impactBadge = ['low' => 'text-bg-success', 'medium' => 'text-bg-warning', 'high' => 'text-bg-danger'][$repairRequest->impact_level] ?? 'text-bg-secondary';

    // 設備欄要顯示的文字：有綁定設備就顯示「設備編號 類別 品牌 型號（教室）」；沒有就退回手填描述、地點，都沒有顯示「未填寫」。
    $deviceDisplay = $repairRequest->device
        ? trim($repairRequest->device->device_code . ' ' . ($repairRequest->device->category->name ?? '') . ' ' . $repairRequest->device->brand . ' ' . $repairRequest->device->model . '（' . ($repairRequest->device->classroom->name ?? $repairRequest->device->classroom->classroom_code ?? '') . '）')
        : ($repairRequest->device_note ?? __('repair_requests.not_filled'));
    // 設備地點：報修單選的教室（掃描設備時自動帶入設備所在教室）。
    $locationDisplay = $repairRequest->locationLabel() ?? __('repair_requests.not_filled');
    // 派工時查課表用的教室：報修單的設備地點，沒有就用設備所在的教室。
    $timetableClassroom = $repairRequest->classroom ?? $repairRequest->device?->classroom;

    // 維修人員欄只顯示 assigned_to 關聯的真實使用者姓名。
    $assigneeDisplay = $repairRequest->assignedTechnician?->name ?? __('repair_requests.unassigned');

    // 目前的案件狀態（RepairRequestStatus 列舉）。
    $status = $repairRequest->status;
    // 每個按鈕都要「案件狀態對」而且「這位用戶有權限處理這一張單」才顯示（身分權限 + 這張單是不是你的，
    // 規則見 App\Policies\RepairRequestPolicy）；後端也會檢查，這裡只是不顯示按了也沒用的按鈕。
    $viewer = auth()->user();
    // 「派工」表單：案件是新報修，而且你有派工權限。
    $showDispatch = $status === \App\Enums\RepairRequestStatus::Pending && $viewer->can('repairs.dispatch');
    // 「開始處理」按鈕：案件已派工，而且你是被指派的維修人員（或管理員）。
    $showStart = $status === \App\Enums\RepairRequestStatus::Assigned && $viewer->can('start', $repairRequest);
    // 「填寫維修紀錄」按鈕：案件處理中，而且你是被指派的維修人員（或管理員）。
    $showFillLog = $status === \App\Enums\RepairRequestStatus::InProgress && $viewer->can('fillLog', $repairRequest);
    // 「驗收」區塊：案件待驗收，而且你是報修人（或管理員，或沒有報修人的案件由有驗收權限者處理）。
    $showAccept = $status === \App\Enums\RepairRequestStatus::PendingReview && $viewer->can('accept', $repairRequest);
    // 「重新指派」：案件是已派工或處理中，而且你有派工權限。
    $canReassign = in_array($status, [\App\Enums\RepairRequestStatus::Assigned, \App\Enums\RepairRequestStatus::InProgress], true)
        && $viewer->can('repairs.dispatch');
    // 只要有任何一個操作可做，才顯示右欄的操作卡片。
    $hasActions = $showDispatch || $showStart || $showFillLog || $showAccept || $canReassign;
@endphp

@section('content')
    {{-- 頁面標題列：案件標題 + 狀態徽章，右邊是回看板的箭頭按鈕。 --}}
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <h1 class="tb-title mb-0">{{ $repairRequest->title }}</h1>
            {{-- 引入共用的狀態徽章（看板與詳細頁用同一份）。 --}}
            @include('repairs._status-badge', ['status' => $status])
        </div>
        {{-- 修改、取消（刪除）：只給有派工權限的人。已結案不能改；已填維修紀錄（待驗收、已結案）不能取消（見 RepairRequestController::destroy）。
             取消與刪除是同一件事（軟刪除，資料與操作紀錄保留），送出前先跳出確認視窗。 --}}
        @can('repairs.dispatch')
            <div class="d-inline-flex gap-2 flex-shrink-0">
                @if ($status !== \App\Enums\RepairRequestStatus::Completed)
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('repairs.edit', $repairRequest) }}"><i class="bi bi-pencil me-1"></i>修改</a>
                @endif
                @unless (in_array($status, [\App\Enums\RepairRequestStatus::PendingReview, \App\Enums\RepairRequestStatus::Completed], true))
                    <form method="POST" action="{{ route('repairs.destroy', $repairRequest) }}"
                        data-confirm="請問確定要取消報修單「{{ $repairRequest->title }}」嗎？取消後會從看板與統計中移除，操作紀錄仍會保留。" data-confirm-ok="確認取消">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-circle me-1"></i>取消工單</button>
                    </form>
                @endunless
            </div>
        @endcan
    </div>

    {{-- 版面：寬螢幕（992px 以上）左欄是設備資料與故障描述，右欄是派工與處理；
         窄螢幕改成直式，依序是設備資料 → 故障描述 → 派工與處理。維修紀錄與材料費明細放在最下面、佔滿整列。 --}}
    <div class="row g-3 mb-3 align-items-start">
        {{-- 左欄：設備資料（設備、地點、影響程度、維修人員、送出時間）、最近一次驗收退回原因、故障描述與附件。
             沒有可做的操作時（例如已結案、或你沒有權限）不顯示右欄，左欄佔滿整列。 --}}
        <div class="col-12 {{ $hasActions ? 'col-lg-7' : '' }}">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    {{-- dl / dt / dd 是 HTML 的「名稱／內容」清單：dt 欄位名稱、dd 內容，Bootstrap 排成左右兩欄。 --}}
                    <dl class="row mb-0 gy-2">
                        <dt class="col-6 col-sm-5 text-muted fw-normal text-nowrap"><i class="bi bi-pc-display me-2"></i>{{ __('repair_requests.show.device_location_prefix') }}</dt>
                        <dd class="col-6 col-sm-7 mb-0">{{ $deviceDisplay }}</dd>

                        <dt class="col-6 col-sm-5 text-muted fw-normal text-nowrap"><i class="bi bi-geo-alt me-2"></i>設備地點：</dt>
                        <dd class="col-6 col-sm-7 mb-0">{{ $locationDisplay }}</dd>

                        <dt class="col-6 col-sm-5 text-muted fw-normal text-nowrap"><i class="bi bi-speedometer me-2"></i>{{ __('repair_requests.show.impact_level_prefix') }}</dt>
                        <dd class="col-6 col-sm-7 mb-0"><span class="badge {{ $impactBadge }}">{{ __('repair_requests.impact_level.' . $repairRequest->impact_level) }}</span></dd>

                        <dt class="col-6 col-sm-5 text-muted fw-normal text-nowrap"><i class="bi bi-mortarboard me-2"></i>{{ __('repair_requests.show.affects_class_prefix') }}</dt>
                        <dd class="col-6 col-sm-7 mb-0">
                            @if ($repairRequest->is_class_affected)
                                <span class="badge text-bg-danger">{{ __('repair_requests.yes') }}</span>
                            @else
                                <span class="badge text-bg-light border text-muted">{{ __('repair_requests.no') }}</span>
                            @endif
                        </dd>

                        <dt class="col-6 col-sm-5 text-muted fw-normal text-nowrap"><i class="bi bi-person-gear me-2"></i>{{ __('repair_requests.show.assignee_prefix') }}</dt>
                        <dd class="col-6 col-sm-7 mb-0">
                            {{ $assigneeDisplay }}
                            {{-- 有預計處理時間才顯示。 --}}
                            @if ($repairRequest->scheduled_at)
                                <span class="text-muted small ms-1">{{ __('repair_requests.show.scheduled_suffix', ['datetime' => $repairRequest->scheduled_at->format('Y-m-d H:i')]) }}</span>
                            @endif
                        </dd>

                        <dt class="col-6 col-sm-5 text-muted fw-normal text-nowrap"><i class="bi bi-clock me-2"></i>{{ __('repair_requests.show.submitted_prefix') }}</dt>
                        <dd class="col-6 col-sm-7 mb-0">{{ $repairRequest->created_at->format('Y-m-d H:i') }}</dd>
                    </dl>
                </div>
            </div>

            @if ($repairRequest->rejection_reason)
                {{-- 這張案件曾經被驗收退回，把最新一次的退回原因秀出來，讓維修人員知道要補做什麼。 --}}
                <div class="alert alert-danger d-flex gap-2">
                    <i class="bi bi-arrow-counterclockwise mt-1"></i>
                    <div><strong>{{ __('repair_requests.show.last_rejection_reason_prefix') }}</strong>{{ $repairRequest->rejection_reason }}</div>
                </div>
            @endif

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white form-section-title"><i class="bi bi-chat-left-text me-2"></i>{{ __('repair_requests.show.description_heading') }}</div>
                <div class="card-body" style="white-space: pre-line;">{{ $repairRequest->description }}</div>
            </div>

            {{-- 有上傳附件（故障照片／影片）才顯示附件卡片，點檔名會在新分頁開啟。 --}}
            @if ($repairRequest->attachments->isNotEmpty())
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white form-section-title"><i class="bi bi-paperclip me-2"></i>{{ __('repair_requests.show.attachments_heading') }}</div>
                    <ul class="list-group list-group-flush">
                        @foreach ($repairRequest->attachments as $attachment)
                            <li class="list-group-item"><a href="{{ $attachment->url() }}" target="_blank" class="text-decoration-none"><i class="bi bi-file-earmark me-2"></i>{{ $attachment->file_name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- 右欄：派工與處理（依目前狀態、依你的權限才出現）。 --}}
        @if ($hasActions)
        <div class="col-12 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-white form-section-title"><i class="bi bi-person-gear me-2"></i>派工與處理</div>
                    <div class="card-body">
                        {{-- 狀態一：新報修 → 顯示派工表單（挑維修人員 + 預計處理時間）。 --}}
                        @if ($showDispatch)
                            <h2 class="h6 mb-3"><i class="bi bi-person-check me-2"></i>{{ __('repair_requests.show.dispatch_heading') }}</h2>
                            <form method="POST" action="{{ route('repairs.assign', $repairRequest) }}">
                                @csrf
                                <div class="mb-3">
                                    <label for="assigned_to" class="form-label">{{ __('repair_requests.show.assignee_field_label') }}</label>
                                    <select class="form-select" id="assigned_to" name="assigned_to" required>
                                        <option value="">{{ __('repair_requests.show.assignee_field_placeholder') }}</option>
                                        {{-- 維修人員下拉選單：只列「身分有勾選可被指派、且帳號啟用中」的用戶（Controller 準備的 $technicians）。 --}}
                                        @foreach ($technicians as $technician)
                                            <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="scheduled_at" class="form-label">{{ __('repair_requests.show.scheduled_field_label') }}</label>
                                    <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at">
                                    @if ($timetableClassroom)
                                        <button type="button" class="btn btn-sm btn-outline-primary mt-2 w-100" data-bs-toggle="modal" data-bs-target="#timetableModal" data-target-input="scheduled_at">
                                            <i class="bi bi-calendar-week me-1"></i>從 {{ $timetableClassroom->name }} 的課表挑空堂
                                        </button>
                                    @endif
                                </div>
                                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-person-check me-1"></i>{{ __('repair_requests.show.confirm_dispatch') }}</button>
                            </form>
                        {{-- 狀態二：已派工 → 顯示「開始處理」按鈕。 --}}
                        @elseif ($showStart)
                            <form method="POST" action="{{ route('repairs.start', $repairRequest) }}">
                                @csrf
                                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-play-fill me-1"></i>{{ __('repair_requests.show.start_processing') }}</button>
                            </form>
                        {{-- 狀態三：處理中 → 顯示「填寫維修紀錄」按鈕（送出後案件自動變成待驗收）。 --}}
                        @elseif ($showFillLog)
                            <a class="btn btn-primary w-100" href="{{ route('repair-logs.create', $repairRequest) }}" data-modal-frame data-modal-title="{{ __('repair_requests.show.fill_repair_log') }}" data-modal-size="lg"><i class="bi bi-journal-plus me-1"></i>{{ __('repair_requests.show.fill_repair_log') }}</a>
                        {{-- 狀態四：待驗收 → 顯示驗收區：通過直接結案；不通過展開輸入框填退回原因。 --}}
                        @elseif ($showAccept)
                            <h2 class="h6 mb-3"><i class="bi bi-clipboard-check me-2"></i>{{ __('repair_requests.show.acceptance_heading') }}</h2>
                            {{-- 驗收兩條路：通過就結案（不用填原因），不通過要退回並說明原因。 --}}
                            <form method="POST" action="{{ route('repairs.complete', $repairRequest) }}" class="mb-2">
                                @csrf
                                <button class="btn btn-success w-100" type="submit"><i class="bi bi-check-circle me-1"></i>{{ __('repair_requests.show.accept_pass') }}</button>
                            </form>
                            {{-- 點這個按鈕會展開／收合下面 id="rejectBox" 的區塊（Bootstrap 的 collapse）。 --}}
                            <button class="btn btn-outline-danger w-100" type="button" data-bs-toggle="collapse" data-bs-target="#rejectBox" aria-expanded="false">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('repair_requests.show.accept_fail_summary') }}
                            </button>
                            <div class="collapse mt-3" id="rejectBox">
                                <form method="POST" action="{{ route('repairs.reject', $repairRequest) }}">
                                    @csrf
                                    <label for="rejection_reason" class="form-label">{{ __('repair_requests.show.rejection_reason_label') }}</label>
                                    <textarea class="form-control mb-3" id="rejection_reason" name="rejection_reason" rows="3" required></textarea>
                                    <button class="btn btn-danger w-100" type="submit"><i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('repair_requests.show.confirm_reject') }}</button>
                                </form>
                            </div>
                        @endif

                        {{-- 重新指派（依《第四週個人工作計畫》第 1 項）：已派工／處理中都可以換人，
                             不影響案件本身的狀態，跟「派工」（新報修 -> 已派工）是不同的動作。 --}}
                        {{-- 重新指派區塊：同樣用可收合的方式，點按鈕才展開。 --}}
                        @if ($canReassign)
                            <hr>
                            <button class="btn btn-outline-secondary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#reassignBox" aria-expanded="false">
                                <i class="bi bi-arrow-left-right me-1"></i>{{ __('repair_requests.show.reassign_summary') }}
                            </button>
                            <div class="collapse mt-3" id="reassignBox">
                                <form method="POST" action="{{ route('repairs.reassign', $repairRequest) }}">
                                    @csrf
                                    <div class="mb-3">
                                        <label for="reassign_assigned_to" class="form-label">{{ __('repair_requests.show.reassign_to_label') }}</label>
                                        <select class="form-select" id="reassign_assigned_to" name="assigned_to" required>
                                            <option value="">{{ __('repair_requests.show.assignee_field_placeholder') }}</option>
                                            @foreach ($technicians as $technician)
                                                {{-- 目前的維修人員預設為選取狀態。 --}}
                                                <option value="{{ $technician->id }}" @selected($repairRequest->assigned_to === $technician->id)>{{ $technician->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="reassign_scheduled_at" class="form-label">{{ __('repair_requests.show.scheduled_field_label') }}</label>
                                        <input type="datetime-local" class="form-control" id="reassign_scheduled_at" name="scheduled_at">
                                        @if ($timetableClassroom)
                                            <button type="button" class="btn btn-sm btn-outline-primary mt-2 w-100" data-bs-toggle="modal" data-bs-target="#timetableModal" data-target-input="reassign_scheduled_at">
                                                <i class="bi bi-calendar-week me-1"></i>從 {{ $timetableClassroom->name }} 的課表挑空堂
                                            </button>
                                        @endif
                                    </div>
                                    <button class="btn btn-secondary w-100" type="submit"><i class="bi bi-arrow-left-right me-1"></i>{{ __('repair_requests.show.confirm_reassign') }}</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
        </div>
        @endif
    </div>

        {{-- 有維修紀錄才顯示；驗收退回重修後會有多筆，這裡逐筆列出完整的處理過程。 --}}
        @if ($repairRequest->repairLogs->isNotEmpty())
            <h2 class="h6 text-muted mt-4 mb-2"><i class="bi bi-journal-text me-2"></i>{{ __('repair_requests.show.repair_logs_heading') }}</h2>
            {{-- 逐筆列出維修紀錄（時間、工時、原因、處置、備品、照片）。 --}}
            @foreach ($repairRequest->repairLogs as $log)
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white form-section-title d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <span class="fw-semibold me-2">維修紀錄 #{{ $log->id }}</span>
                            <span><i class="bi bi-clock-history me-2"></i>{{ $log->started_at->format('Y-m-d H:i') }} ～ {{ $log->ended_at->format('Y-m-d H:i') }}</span>
                        </div>
                        <span class="text-muted">{{ __('repair_requests.show.total_hours_suffix', ['hours' => $log->total_hours]) }}</span>
                    </div>

                    <div class="card-body">
                        <dl class="row mb-0 gy-2">
                            {{-- 每筆維修紀錄依序顯示：故障原因、處置方式，有填才顯示的使用備品與附件。 --}}
                            <dt class="col-4 col-sm-3 text-muted fw-normal text-nowrap">{{ __('repair_requests.show.cause_prefix') }}</dt>
                            <dd class="col-8 col-sm-9 mb-0">{{ $log->cause }}</dd>

                            <dt class="col-4 col-sm-3 text-muted fw-normal text-nowrap">{{ __('repair_requests.show.resolution_prefix') }}</dt>
                            <dd class="col-8 col-sm-9 mb-0">{{ $log->resolution }}</dd>

                            {{-- 使用備品說明有填才顯示。 --}}
                            @if ($log->parts_used_note)
                                <dt class="col-4 col-sm-3 text-muted fw-normal text-nowrap">{{ __('repair_requests.show.parts_used_prefix') }}</dt>
                                <dd class="col-8 col-sm-9 mb-0">{{ $log->parts_used_note }}</dd>
                            @endif

                            {{-- 這筆維修紀錄有附件（維修前後照片／影片）才顯示，點檔名在新分頁開啟。 --}}
                            @if ($log->attachments->isNotEmpty())
                                <dt class="col-4 col-sm-3 text-muted fw-normal text-nowrap">{{ __('repair_requests.show.log_attachments_prefix') }}</dt>
                                <dd class="col-8 col-sm-9 mb-0">
                                    <ul class="list-unstyled mb-0">
                                        @foreach ($log->attachments as $attachment)
                                            <li><a href="{{ $attachment->url() }}" target="_blank" class="text-decoration-none"><i class="bi bi-file-earmark me-1"></i>{{ $attachment->file_name }}</a></li>
                                        @endforeach
                                    </ul>
                                </dd>
                            @endif
                        </dl>
                    </div>
                </div>
            @endforeach
        @endif

    {{-- 材料費明細：彙總這張報修單所有維修紀錄的零件使用情況。 --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white form-section-title"><i class="bi bi-cash-coin me-2"></i>材料費明細</div>
        <div class="card-body">
            @if ($partUsages->isEmpty())
                <p class="text-muted mb-0">目前沒有零件使用紀錄。</p>
            @else
                <div class="table-responsive">
                    <table class="tb-table tb-table-sm align-middle mb-3">
                        <thead>
                            <tr>
                                <th>零件名稱</th>
                                <th>維修紀錄</th>
                                <th class="text-end">數量</th>
                                <th class="text-end">單價</th>
                                <th class="text-end">小計</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($repairRequest->repairLogs as $log)
                                @foreach ($log->partUsages as $usage)
                                    <tr>
                                        <td>{{ $usage->part?->name ?? '零件資料不存在' }}</td>
                                        <td>#{{ $log->id }}</td>
                                        <td class="text-end text-nowrap">{{ $usage->quantity }} {{ $usage->part?->unit }}</td>
                                        <td class="text-end">
                                            @if ($usage->unit_price === null)
                                                <span class="text-danger">缺少單價</span>
                                            @else
                                                NT$ {{ number_format($usage->unit_price) }}
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($usage->unit_price === null)
                                                —
                                            @else
                                                NT$ {{ number_format($usage->quantity * $usage->unit_price) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($hasIncompletePrice)
                    <div class="alert alert-warning mb-0">
                        部分零件缺少歷史單價，目前無法提供完整的材料費總額。
                    </div>
                @else
                    <div class="text-end">
                        <span class="text-muted me-2">目前記錄的材料費總額</span>
                        <strong class="fs-5">NT$ {{ number_format($materialCost) }}</strong>
                    </div>
                @endif
            @endif
        </div>
    </div>
    {{-- 教室課表視窗（派工、重新指派共用）：顯示設備地點那間教室一週的課表，點空堂就把時間填進「建議處理時間」。 --}}
    @if ($timetableClassroom && ($showDispatch || $canReassign))
        <div class="modal fade" id="timetableModal" tabindex="-1" aria-labelledby="timetableModalTitle" aria-hidden="true"
            data-url="{{ route('repairs.timetable', $repairRequest) }}">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="timetableModalTitle">{{ $timetableClassroom->name }}（{{ $timetableClassroom->classroom_code }}）課表</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="ttPrev"><i class="bi bi-chevron-left"></i> 上一週</button>
                            <input type="date" class="form-control form-control-sm" id="ttDate" style="width: 11rem;" aria-label="選擇日期">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="ttNext">下一週 <i class="bi bi-chevron-right"></i></button>
                            <span class="small text-muted ms-auto" id="ttRange"></span>
                        </div>
                        <div class="small mb-2 d-flex flex-wrap gap-3">
                            <span><span class="badge text-bg-success">空堂</span> 可以選</span>
                            <span><span class="badge text-bg-warning">有待審核預約</span> 可以選，但之後可能被核准</span>
                            <span><span class="badge text-bg-secondary">課程／已核准預約</span> 不能選</span>
                            <span><span class="badge text-bg-info">已排其他維修</span> 同一節已有別張報修單</span>
                        </div>
                        <div id="ttBody" class="table-responsive"><p class="text-muted my-4 text-center">載入中…</p></div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('timetableModal');
            const body = document.getElementById('ttBody');
            const dateInput = document.getElementById('ttDate');
            let targetInput = null;
            let current = null;   // 目前顯示的這一週任一天（YYYY-MM-DD）

            function shift(dateString, days) {
                const d = new Date(dateString + 'T00:00:00');
                d.setDate(d.getDate() + days);
                return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            }

            function cell(text, cls) {
                const td = document.createElement('td');
                td.className = 'small ' + (cls || '');
                td.textContent = text;
                td.style.whiteSpace = 'pre-line';
                return td;
            }

            async function load(dateString) {
                body.innerHTML = '<p class="text-muted my-4 text-center">載入中…</p>';
                try {
                    const response = await fetch(modal.dataset.url + '?date=' + encodeURIComponent(dateString), { headers: { 'Accept': 'application/json' } });
                    const data = await response.json();
                    if (!response.ok) { throw new Error(data.message || '課表載入失敗'); }
                    current = data.selected_date;
                    dateInput.value = data.selected_date;
                    document.getElementById('ttRange').textContent = data.week_start + ' ～ ' + data.week_end;
                    render(data);
                } catch (error) {
                    body.innerHTML = '';
                    const p = document.createElement('p');
                    p.className = 'text-danger my-4 text-center';
                    p.textContent = error.message;
                    body.appendChild(p);
                }
            }

            function render(data) {
                const now = new Date();
                const table = document.createElement('table');
                table.className = 'table table-bordered align-middle text-center mb-0';
                const head = table.createTHead().insertRow();
                head.appendChild(Object.assign(document.createElement('th'), { textContent: '節次', className: 'table-light text-nowrap' }));
                data.days.forEach(function (day) {
                    head.appendChild(Object.assign(document.createElement('th'), { textContent: day.weekday + ' ' + day.date.slice(5), className: 'table-light text-nowrap' }));
                });

                const tbody = table.createTBody();
                data.days[0].periods.forEach(function (period, index) {
                    const row = tbody.insertRow();
                    row.appendChild(cell((period.label || ('第 ' + period.period_number + ' 節')) + '\n' + period.start_time + '–' + period.end_time, 'text-nowrap table-light'));

                    data.days.forEach(function (day) {
                        const p = day.periods[index];
                        const approved = p.reservations.filter(function (r) { return r.status === 'approved'; });
                        const pending = p.reservations.filter(function (r) { return r.status === 'pending'; });
                        const start = new Date(day.date + 'T' + p.start_time + ':00');
                        let td;

                        if (p.is_class) {
                            td = cell((p.course_name || '課程') + '\n' + (p.class_name || ''), 'bg-secondary-subtle text-secondary');
                        } else if (approved.length) {
                            td = cell('已核准預約\n' + approved[0].class_name, 'bg-secondary-subtle text-secondary');
                        } else if (start < now) {
                            td = cell('已過', 'text-muted bg-light');
                        } else {
                            let label = '空堂';
                            let cls = 'bg-success-subtle';
                            if (pending.length) { label = '有待審核預約\n' + pending[0].class_name; cls = 'bg-warning-subtle'; }
                            if (p.repairs.length) { label += '\n已排維修 #' + p.repairs[0].id; cls = 'bg-info-subtle'; }
                            td = cell(label, cls + ' tt-free');
                            td.tabIndex = 0;
                            td.setAttribute('role', 'button');
                            td.setAttribute('aria-label', day.date + ' ' + (p.label || ('第 ' + p.period_number + ' 節')) + ' ' + p.start_time + '，選這個時間');
                            const choose = function () {
                                if (targetInput) { targetInput.value = day.date + 'T' + p.start_time; }
                                bootstrap.Modal.getInstance(modal).hide();
                            };
                            td.addEventListener('click', choose);
                            td.addEventListener('keydown', function (e) { if (e.key === 'Enter') { choose(); } });
                        }
                        td.style.whiteSpace = 'pre-line';
                        row.appendChild(td);
                    });
                });

                body.innerHTML = '';
                body.appendChild(table);
            }

            modal.addEventListener('show.bs.modal', function (event) {
                targetInput = document.getElementById(event.relatedTarget.dataset.targetInput);
                // 已經填過建議時間就從那一週開始看，否則從今天這一週。
                const start = targetInput && targetInput.value ? targetInput.value.slice(0, 10) : (current || shift(new Date().toLocaleDateString('en-CA'), 0));
                load(start);
            });
            document.getElementById('ttPrev').addEventListener('click', function () { load(shift(current, -7)); });
            document.getElementById('ttNext').addEventListener('click', function () { load(shift(current, 7)); });
            dateInput.addEventListener('change', function () { if (dateInput.value) { load(dateInput.value); } });
        });
        </script>
        <style>
            .tt-free { cursor: pointer; }
            .tt-free:hover, .tt-free:focus-visible { outline: 2px solid var(--bs-primary); outline-offset: -2px; }
        </style>
    @endif
@endsection

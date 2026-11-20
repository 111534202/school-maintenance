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

                        {{-- 驗收人：派工時指定的人；沒指定就是報修人本人。 --}}
                        <dt class="col-6 col-sm-5 text-muted fw-normal text-nowrap"><i class="bi bi-clipboard-check me-2"></i>驗收人：</dt>
                        <dd class="col-6 col-sm-7 mb-0">{{ $repairRequest->acceptor?->name ?? ($repairRequest->requester?->can('repairs.accept') ? '報修人本人' : '系統管理員（報修人沒有驗收權限或沒有報修人，未另外指定驗收人）') }}</dd>

                        <dt class="col-6 col-sm-5 text-muted fw-normal text-nowrap"><i class="bi bi-clock me-2"></i>{{ __('repair_requests.show.submitted_prefix') }}</dt>
                        <dd class="col-6 col-sm-7 mb-0">{{ $repairRequest->created_at->format('Y-m-d H:i') }}</dd>
                    </dl>
                </div>
            </div>

            {{-- 已結案的案件是定案了，不再顯示「驗收退回」的紅色提醒（那是給維修人員補做用的，結案後沒有要做的事）。 --}}
            @if ($repairRequest->rejection_reason && $repairRequest->status !== \App\Enums\RepairRequestStatus::Completed)
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
                    <div class="card-body">
                        @include('repairs._attachment-list', ['attachments' => $repairRequest->attachments])
                    </div>
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
                                    <label for="accepted_by" class="form-label">驗收人</label>
                                    <select class="form-select" id="accepted_by" name="accepted_by">
                                        <option value="">報修人本人驗收（預設）</option>
                                        @foreach ($acceptors as $acceptor)
                                            <option value="{{ $acceptor->id }}" @selected((int) old('accepted_by', $repairRequest->accepted_by) === $acceptor->id)>{{ $acceptor->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">維修完成後由這個人驗收；除了管理員，只有報修人、維修人員、驗收人看得到「待驗收」與「已結案」的案件。</div>
                                    @if ($repairRequest->requester_id === null)
                                        <div class="form-text text-danger">這張單沒有報修人（例如保養 NG 轉來的），請指定驗收人，否則只有系統管理員能驗收。</div>
                                    @elseif (! $repairRequest->requester?->can('repairs.accept'))
                                        <div class="form-text text-danger">報修人沒有驗收權限，請指定驗收人，否則只有系統管理員能驗收。</div>
                                    @endif
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
                                        <label for="reassign_accepted_by" class="form-label">驗收人</label>
                                        <select class="form-select" id="reassign_accepted_by" name="accepted_by">
                                            <option value="">報修人本人驗收（預設）</option>
                                            @foreach ($acceptors as $acceptor)
                                                <option value="{{ $acceptor->id }}" @selected($repairRequest->accepted_by === $acceptor->id)>{{ $acceptor->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="reassign_scheduled_at" class="form-label">{{ __('repair_requests.show.scheduled_field_label') }}</label>
                                        <input type="datetime-local" class="form-control" id="reassign_scheduled_at" name="scheduled_at" value="{{ old('scheduled_at', $repairRequest->scheduled_at?->format('Y-m-d\TH:i')) }}">
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
                                    @include('repairs._attachment-list', ['attachments' => $log->attachments])
                                </dd>
                            @endif
                        </dl>
                    </div>
                </div>
            @endforeach
        @endif

    {{-- 材料費明細：彙總這張報修單所有維修紀錄的零件使用情況。
         單價、小計與材料費總額屬於零件庫存的資料，只有有「零件庫存」權限（parts.manage）的人看得到；其他人只看零件名稱與數量。 --}}
    @php $canSeePrice = auth()->user()->can('parts.manage'); @endphp
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white form-section-title"><i class="bi bi-cash-coin me-2"></i>{{ $canSeePrice ? '材料費明細' : '使用零件' }}</div>
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
                                @if ($canSeePrice)
                                    <th class="text-end">單價</th>
                                    <th class="text-end">小計</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($repairRequest->repairLogs as $log)
                                @foreach ($log->partUsages as $usage)
                                    <tr>
                                        <td>{{ $usage->part?->name ?? '零件資料不存在' }}</td>
                                        <td>#{{ $log->id }}</td>
                                        <td class="text-end text-nowrap">{{ $usage->quantity }} {{ $usage->part?->unit }}</td>
                                        @if ($canSeePrice)
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
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($canSeePrice)
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
            @endif
        </div>
    </div>
    {{-- 驗收通過後的「結案資訊」彈出視窗：一進來就打開，點視窗外面（灰色背景）、按「知道了」或按 Esc 都會關閉。 --}}
    @if (session('closing_info'))
        <dialog id="closingInfoDialog" class="tb-confirm-dialog" aria-labelledby="closingInfoTitle">
            <form method="dialog">
                <h2 class="tb-confirm-title" id="closingInfoTitle"><i class="bi bi-check-circle-fill text-success me-2"></i>已結案</h2>
                <p class="tb-confirm-message">驗收通過，這張報修單已結案，以下是結案資訊。</p>
                <dl class="small border rounded p-2 mb-3">
                    @foreach (session('closing_info') as [$label, $value])
                        <div class="d-flex gap-2">
                            <dt class="fw-normal text-muted flex-shrink-0 mb-0">{{ $label }}</dt>
                            <dd class="mb-0">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <div class="tb-confirm-actions">
                    <button type="submit" class="btn btn-primary" autofocus>知道了</button>
                </div>
            </form>
        </dialog>
        <script>
            (function () {
                var d = document.getElementById('closingInfoDialog');
                if (!d || !d.showModal) return;
                d.showModal();
                d.addEventListener('click', function (event) { if (event.target === d) d.close(); });
            })();
        </script>
    @endif
    {{-- 教室課表視窗（派工、重新指派共用）：顯示設備地點那間教室一週的課表，點空堂就把時間填進「建議處理時間」。 --}}
    @if ($timetableClassroom && ($showDispatch || $canReassign))
        @include('layouts.partials.classroom-timetable-modal', ['classroom' => $timetableClassroom, 'url' => route('repairs.timetable', $repairRequest), 'mode' => 'datetime', 'busyLabel' => '已排維修', 'busyHint' => '同一節已有別張報修單'])
    @endif
@endsection

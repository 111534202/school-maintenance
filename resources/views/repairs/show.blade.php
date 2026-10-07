{{-- 報修單詳細頁（對應 RepairRequestController::show）：左欄是案件資訊、描述、附件、維修紀錄；右欄是「依目前狀態、依你的權限」才出現的操作按鈕。 --}}
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
        ? trim($repairRequest->device->device_code . ' ' . ($repairRequest->device->category->name ?? '') . ' ' . $repairRequest->device->brand . ' ' . $repairRequest->device->model . '（' . ($repairRequest->device->classroom->room_name ?? $repairRequest->device->classroom->room_code ?? '') . '）')
        : ($repairRequest->device_note ?? $repairRequest->location ?? __('repair_requests.not_filled'));

    // 維修人員欄要顯示的文字：優先用真實用戶姓名，其次文字備註，最後顯示「未指派」。
    $assigneeDisplay = $repairRequest->assignedTechnician->name ?? $repairRequest->assignee_note ?? __('repair_requests.unassigned');

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
            <h1 class="h4 mb-0">{{ $repairRequest->title }}</h1>
            {{-- 引入共用的狀態徽章（看板與詳細頁用同一份）。 --}}
            @include('repairs._status-badge', ['status' => $status])
        </div>
        <a class="btn btn-outline-secondary icon-btn flex-shrink-0" href="{{ route('repairs.index') }}"
            title="{{ __('repair_requests.show.back_to_board') }}" aria-label="{{ __('repair_requests.show.back_to_board') }}"><i class="bi bi-arrow-left"></i></a>
    </div>

    <div class="row g-3">
        {{-- 左欄：案件資訊、描述、附件、維修紀錄 --}}
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    {{-- dl / dt / dd 是 HTML 的「名稱／內容」清單：dt 欄位名稱、dd 內容，Bootstrap 排成左右兩欄。 --}}
                    <dl class="row mb-0 gy-2">
                        <dt class="col-6 col-sm-4 text-muted fw-normal text-nowrap"><i class="bi bi-pc-display me-2"></i>{{ __('repair_requests.show.device_location_prefix') }}</dt>
                        <dd class="col-6 col-sm-8 mb-0">{{ $deviceDisplay }}</dd>

                        <dt class="col-6 col-sm-4 text-muted fw-normal text-nowrap"><i class="bi bi-speedometer me-2"></i>{{ __('repair_requests.show.impact_level_prefix') }}</dt>
                        <dd class="col-6 col-sm-8 mb-0"><span class="badge {{ $impactBadge }}">{{ __('repair_requests.impact_level.' . $repairRequest->impact_level) }}</span></dd>

                        <dt class="col-6 col-sm-4 text-muted fw-normal text-nowrap"><i class="bi bi-mortarboard me-2"></i>{{ __('repair_requests.show.affects_class_prefix') }}</dt>
                        <dd class="col-6 col-sm-8 mb-0">
                            @if ($repairRequest->affects_class)
                                <span class="badge text-bg-danger">{{ __('repair_requests.yes') }}</span>
                            @else
                                <span class="badge text-bg-light border text-muted">{{ __('repair_requests.no') }}</span>
                            @endif
                        </dd>

                        <dt class="col-6 col-sm-4 text-muted fw-normal text-nowrap"><i class="bi bi-person-gear me-2"></i>{{ __('repair_requests.show.assignee_prefix') }}</dt>
                        <dd class="col-6 col-sm-8 mb-0">
                            {{ $assigneeDisplay }}
                            {{-- 有預計處理時間才顯示。 --}}
                            @if ($repairRequest->scheduled_at)
                                <span class="text-muted small ms-1">{{ __('repair_requests.show.scheduled_suffix', ['datetime' => $repairRequest->scheduled_at->format('Y-m-d H:i')]) }}</span>
                            @endif
                        </dd>

                        <dt class="col-6 col-sm-4 text-muted fw-normal text-nowrap"><i class="bi bi-clock me-2"></i>{{ __('repair_requests.show.submitted_prefix') }}</dt>
                        <dd class="col-6 col-sm-8 mb-0">{{ $repairRequest->created_at->format('Y-m-d H:i') }}</dd>
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
                <div class="card-header bg-white"><i class="bi bi-chat-left-text me-2"></i>{{ __('repair_requests.show.description_heading') }}</div>
                <div class="card-body" style="white-space: pre-line;">{{ $repairRequest->description }}</div>
            </div>

            {{-- 有上傳附件（故障照片／影片）才顯示附件卡片，點檔名會在新分頁開啟。 --}}
            @if ($repairRequest->attachments->isNotEmpty())
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><i class="bi bi-paperclip me-2"></i>{{ __('repair_requests.show.attachments_heading') }}</div>
                    <ul class="list-group list-group-flush">
                        @foreach ($repairRequest->attachments as $attachment)
                            <li class="list-group-item"><a href="{{ $attachment->url() }}" target="_blank" class="text-decoration-none"><i class="bi bi-file-earmark me-2"></i>{{ $attachment->original_name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- 有維修紀錄才顯示；驗收退回重修後會有多筆，這裡逐筆列出完整的處理過程。 --}}
            @if ($repairRequest->repairLogs->isNotEmpty())
                <h2 class="h6 text-muted mt-4 mb-2"><i class="bi bi-journal-text me-2"></i>{{ __('repair_requests.show.repair_logs_heading') }}</h2>
                {{-- 逐筆列出維修紀錄（時間、工時、原因、處置、備品、照片）。 --}}
                @foreach ($repairRequest->repairLogs as $log)
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
                            <span><i class="bi bi-clock-history me-2"></i>{{ $log->started_at->format('Y-m-d H:i') }} ～ {{ $log->ended_at->format('Y-m-d H:i') }}</span>
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
                                                <li><a href="{{ $attachment->url() }}" target="_blank" class="text-decoration-none"><i class="bi bi-file-earmark me-1"></i>{{ $attachment->original_name }}</a></li>
                                            @endforeach
                                        </ul>
                                    </dd>
                                @endif
                            </dl>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- 右欄：依目前狀態顯示可以做的操作 --}}
        {{-- 沒有任何可做的操作時（例如已結案、或你沒有權限），整個右欄不顯示。 --}}
        @if ($hasActions)
            <div class="col-lg-4">
                <div class="card shadow-sm">
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
                            <a class="btn btn-primary w-100" href="{{ route('repair-logs.create', $repairRequest) }}"><i class="bi bi-journal-plus me-1"></i>{{ __('repair_requests.show.fill_repair_log') }}</a>
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
@endsection

@extends('layouts.app')

@section('title', $repairRequest->title)

@php
    $impactBadge = ['low' => 'text-bg-success', 'medium' => 'text-bg-warning', 'high' => 'text-bg-danger'][$repairRequest->impact_level] ?? 'text-bg-secondary';

    $deviceDisplay = $repairRequest->device
        ? trim($repairRequest->device->device_code . ' ' . ($repairRequest->device->category->name ?? '') . ' ' . $repairRequest->device->brand . ' ' . $repairRequest->device->model . '（' . ($repairRequest->device->classroom->room_name ?? $repairRequest->device->classroom->room_code ?? '') . '）')
        : ($repairRequest->device_note ?? $repairRequest->location ?? __('repair_requests.not_filled'));

    $assigneeDisplay = $repairRequest->assignedTechnician->name ?? $repairRequest->assignee_note ?? __('repair_requests.unassigned');

    $status = $repairRequest->status;
    $canReassign = in_array($status, [\App\Enums\RepairRequestStatus::Assigned, \App\Enums\RepairRequestStatus::InProgress], true);
    $hasActions = $status !== \App\Enums\RepairRequestStatus::Completed;
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <h1 class="h4 mb-0">{{ $repairRequest->title }}</h1>
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

            @if ($repairRequest->repairLogs->isNotEmpty())
                <h2 class="h6 text-muted mt-4 mb-2"><i class="bi bi-journal-text me-2"></i>{{ __('repair_requests.show.repair_logs_heading') }}</h2>
                @foreach ($repairRequest->repairLogs as $log)
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
                            <span><i class="bi bi-clock-history me-2"></i>{{ $log->started_at->format('Y-m-d H:i') }} ～ {{ $log->ended_at->format('Y-m-d H:i') }}</span>
                            <span class="text-muted">{{ __('repair_requests.show.total_hours_suffix', ['hours' => $log->total_hours]) }}</span>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0 gy-2">
                                <dt class="col-4 col-sm-3 text-muted fw-normal text-nowrap">{{ __('repair_requests.show.cause_prefix') }}</dt>
                                <dd class="col-8 col-sm-9 mb-0">{{ $log->cause }}</dd>

                                <dt class="col-4 col-sm-3 text-muted fw-normal text-nowrap">{{ __('repair_requests.show.resolution_prefix') }}</dt>
                                <dd class="col-8 col-sm-9 mb-0">{{ $log->resolution }}</dd>

                                @if ($log->parts_used_note)
                                    <dt class="col-4 col-sm-3 text-muted fw-normal text-nowrap">{{ __('repair_requests.show.parts_used_prefix') }}</dt>
                                    <dd class="col-8 col-sm-9 mb-0">{{ $log->parts_used_note }}</dd>
                                @endif

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
        @if ($hasActions)
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        @if ($status === \App\Enums\RepairRequestStatus::Pending)
                            <h2 class="h6 mb-3"><i class="bi bi-person-check me-2"></i>{{ __('repair_requests.show.dispatch_heading') }}</h2>
                            <form method="POST" action="{{ route('repairs.assign', $repairRequest) }}">
                                @csrf
                                <div class="mb-3">
                                    <label for="assigned_to" class="form-label">{{ __('repair_requests.show.assignee_field_label') }}</label>
                                    <select class="form-select" id="assigned_to" name="assigned_to" required>
                                        <option value="">{{ __('repair_requests.show.assignee_field_placeholder') }}</option>
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
                        @elseif ($status === \App\Enums\RepairRequestStatus::Assigned)
                            <form method="POST" action="{{ route('repairs.start', $repairRequest) }}">
                                @csrf
                                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-play-fill me-1"></i>{{ __('repair_requests.show.start_processing') }}</button>
                            </form>
                        @elseif ($status === \App\Enums\RepairRequestStatus::InProgress)
                            <a class="btn btn-primary w-100" href="{{ route('repair-logs.create', $repairRequest) }}"><i class="bi bi-journal-plus me-1"></i>{{ __('repair_requests.show.fill_repair_log') }}</a>
                        @elseif ($status === \App\Enums\RepairRequestStatus::PendingReview)
                            <h2 class="h6 mb-3"><i class="bi bi-clipboard-check me-2"></i>{{ __('repair_requests.show.acceptance_heading') }}</h2>
                            {{-- 驗收兩條路：通過就結案（不用填原因），不通過要退回並說明原因。 --}}
                            <form method="POST" action="{{ route('repairs.complete', $repairRequest) }}" class="mb-2">
                                @csrf
                                <button class="btn btn-success w-100" type="submit"><i class="bi bi-check-circle me-1"></i>{{ __('repair_requests.show.accept_pass') }}</button>
                            </form>
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

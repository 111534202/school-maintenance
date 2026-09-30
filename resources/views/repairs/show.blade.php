@extends('layouts.app')

@section('title', $repairRequest->title)

@php
    $statusColors = [
        'pending' => '#eceff1',
        'assigned' => '#e3f2fd',
        'in_progress' => '#fff7e6',
        'pending_review' => '#fde8e8',
        'completed' => '#e3f9e5',
    ];
@endphp

@section('content')
    <div class="toolbar">
        <h1>{{ $repairRequest->title }}</h1>
        <a class="btn btn-secondary" href="{{ route('repairs.index') }}">{{ __('repair_requests.show.back_to_board') }}</a>
    </div>

    @php
        $impactLabel = __('repair_requests.impact_level.' . $repairRequest->impact_level);

        $deviceDisplay = $repairRequest->device
            ? trim($repairRequest->device->device_code . ' ' . ($repairRequest->device->category->name ?? '') . ' ' . $repairRequest->device->brand . ' ' . $repairRequest->device->model . '（' . ($repairRequest->device->classroom->room_name ?? $repairRequest->device->classroom->room_code ?? '') . '）')
            : ($repairRequest->device_note ?? $repairRequest->location ?? __('repair_requests.not_filled'));

        $assigneeDisplay = $repairRequest->assignedTechnician->name ?? $repairRequest->assignee_note ?? __('repair_requests.unassigned');
    @endphp

    <p>
        <strong>{{ __('repair_requests.show.device_location_prefix') }}</strong>{{ $deviceDisplay }}<br>
        <strong>{{ __('repair_requests.show.impact_level_prefix') }}</strong>
        <span class="badge {{ $repairRequest->impact_level === 'high' ? 'badge-off' : 'badge-on' }}">{{ $impactLabel }}</span><br>
        <strong>{{ __('repair_requests.show.affects_class_prefix') }}</strong>{{ $repairRequest->affects_class ? __('repair_requests.yes') : __('repair_requests.no') }}<br>
        <strong>{{ __('repair_requests.show.status_prefix') }}</strong>
        <span class="badge" style="background: {{ $statusColors[$repairRequest->status->value] }};">
            {{ $repairRequest->status->label() }}
        </span><br>
        <strong>{{ __('repair_requests.show.assignee_prefix') }}</strong>{{ $assigneeDisplay }}
        @if ($repairRequest->scheduled_at)
            {{ __('repair_requests.show.scheduled_suffix', ['datetime' => $repairRequest->scheduled_at->format('Y-m-d H:i')]) }}
        @endif
        <br>
        <strong>{{ __('repair_requests.show.submitted_prefix') }}</strong>{{ $repairRequest->created_at->format('Y-m-d H:i') }}
    </p>

    @if ($repairRequest->rejection_reason)
        {{-- 這張案件曾經被驗收退回，把最新一次的退回原因秀出來，讓維修人員知道要補做什麼。 --}}
        <div class="errors">
            <strong>{{ __('repair_requests.show.last_rejection_reason_prefix') }}</strong>{{ $repairRequest->rejection_reason }}
        </div>
    @endif

    <h3>{{ __('repair_requests.show.description_heading') }}</h3>
    <p style="white-space: pre-line;">{{ $repairRequest->description }}</p>

    @if ($repairRequest->attachments->isNotEmpty())
        <h3>{{ __('repair_requests.show.attachments_heading') }}</h3>
        <ul>
            @foreach ($repairRequest->attachments as $attachment)
                <li><a href="{{ $attachment->url() }}" target="_blank">{{ $attachment->original_name }}</a></li>
            @endforeach
        </ul>
    @endif

    <div class="field" style="margin-top: 2rem; border-top: 1px solid #e4e7eb; padding-top: 1.5rem;">
        @if ($repairRequest->status === \App\Enums\RepairRequestStatus::Pending)
            <h3>{{ __('repair_requests.show.dispatch_heading') }}</h3>
            <form method="POST" action="{{ route('repairs.assign', $repairRequest) }}">
                @csrf
                <div class="field">
                    <label for="assigned_to">{{ __('repair_requests.show.assignee_field_label') }}</label>
                    <select id="assigned_to" name="assigned_to" required>
                        <option value="">{{ __('repair_requests.show.assignee_field_placeholder') }}</option>
                        @foreach ($technicians as $technician)
                            <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="scheduled_at">{{ __('repair_requests.show.scheduled_field_label') }}</label>
                    <input type="datetime-local" id="scheduled_at" name="scheduled_at">
                </div>
                <button class="btn btn-primary" type="submit">{{ __('repair_requests.show.confirm_dispatch') }}</button>
            </form>
        @elseif ($repairRequest->status === \App\Enums\RepairRequestStatus::Assigned)
            <form method="POST" action="{{ route('repairs.start', $repairRequest) }}">
                @csrf
                <button class="btn btn-primary" type="submit">{{ __('repair_requests.show.start_processing') }}</button>
            </form>
        @elseif ($repairRequest->status === \App\Enums\RepairRequestStatus::InProgress)
            <a class="btn btn-primary" href="{{ route('repair-logs.create', $repairRequest) }}">{{ __('repair_requests.show.fill_repair_log') }}</a>
        @endif

        {{-- 重新指派（依《第四週個人工作計畫》第 1 項）：已派工／處理中都可以換人，
             不影響案件本身的狀態，跟上面「派工」（新報修 -> 已派工）是不同的動作。 --}}
        @if (in_array($repairRequest->status, [\App\Enums\RepairRequestStatus::Assigned, \App\Enums\RepairRequestStatus::InProgress], true))
            <details style="margin-top: 1rem;">
                <summary style="cursor:pointer; color:#616e7c;">{{ __('repair_requests.show.reassign_summary') }}</summary>
                <form method="POST" action="{{ route('repairs.reassign', $repairRequest) }}" style="margin-top: 0.8rem;">
                    @csrf
                    <div class="field">
                        <label for="reassign_assigned_to">{{ __('repair_requests.show.reassign_to_label') }}</label>
                        <select id="reassign_assigned_to" name="assigned_to" required>
                            <option value="">{{ __('repair_requests.show.assignee_field_placeholder') }}</option>
                            @foreach ($technicians as $technician)
                                <option value="{{ $technician->id }}" @selected($repairRequest->assigned_to === $technician->id)>{{ $technician->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="reassign_scheduled_at">{{ __('repair_requests.show.scheduled_field_label') }}</label>
                        <input type="datetime-local" id="reassign_scheduled_at" name="scheduled_at">
                    </div>
                    <button class="btn btn-secondary" type="submit">{{ __('repair_requests.show.confirm_reassign') }}</button>
                </form>
            </details>
        @endif

        @if ($repairRequest->status === \App\Enums\RepairRequestStatus::PendingReview)
            {{-- 驗收兩條路：通過就結案（不用填原因），不通過要退回並說明原因。 --}}
            <h3>{{ __('repair_requests.show.acceptance_heading') }}</h3>
            <form method="POST" action="{{ route('repairs.complete', $repairRequest) }}" class="inline">
                @csrf
                <button class="btn btn-primary" type="submit">{{ __('repair_requests.show.accept_pass') }}</button>
            </form>

            <details style="margin-top: 1rem;">
                <summary style="cursor:pointer; color:#dc2626;">{{ __('repair_requests.show.accept_fail_summary') }}</summary>
                <form method="POST" action="{{ route('repairs.reject', $repairRequest) }}" style="margin-top: 0.8rem;">
                    @csrf
                    <div class="field">
                        <label for="rejection_reason">{{ __('repair_requests.show.rejection_reason_label') }}</label>
                        <textarea id="rejection_reason" name="rejection_reason" required></textarea>
                    </div>
                    <button class="btn btn-danger" type="submit">{{ __('repair_requests.show.confirm_reject') }}</button>
                </form>
            </details>
        @endif
    </div>

    @if ($repairRequest->repairLogs->isNotEmpty())
        <h3>{{ __('repair_requests.show.repair_logs_heading') }}</h3>
        @foreach ($repairRequest->repairLogs as $log)
            <div style="border:1px solid #e4e7eb; border-radius:4px; padding:1rem; margin-bottom:1rem; background:#fff;">
                <p>
                    <strong>{{ __('repair_requests.show.processing_time_prefix') }}</strong>{{ $log->started_at->format('Y-m-d H:i') }} ～ {{ $log->ended_at->format('Y-m-d H:i') }}
                    {{ __('repair_requests.show.total_hours_suffix', ['hours' => $log->total_hours]) }}
                </p>
                <p><strong>{{ __('repair_requests.show.cause_prefix') }}</strong>{{ $log->cause }}</p>
                <p><strong>{{ __('repair_requests.show.resolution_prefix') }}</strong>{{ $log->resolution }}</p>
                @if ($log->parts_used_note)
                    <p><strong>{{ __('repair_requests.show.parts_used_prefix') }}</strong>{{ $log->parts_used_note }}</p>
                @endif
                @if ($log->attachments->isNotEmpty())
                    <p><strong>{{ __('repair_requests.show.log_attachments_prefix') }}</strong></p>
                    <ul>
                        @foreach ($log->attachments as $attachment)
                            <li><a href="{{ $attachment->url() }}" target="_blank">{{ $attachment->original_name }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    @endif
@endsection

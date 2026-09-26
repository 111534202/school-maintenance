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
        <a class="btn btn-secondary" href="{{ route('repair-requests.index') }}">返回看板</a>
    </div>

    @php($impactLabel = ['low' => '輕微', 'medium' => '中等', 'high' => '嚴重'][$repairRequest->impact_level] ?? $repairRequest->impact_level)

    <p>
        <strong>設備／地點：</strong>{{ $repairRequest->device_note ?? $repairRequest->location ?? '未填寫' }}<br>
        <strong>影響程度：</strong>
        <span class="badge {{ $repairRequest->impact_level === 'high' ? 'badge-off' : 'badge-on' }}">{{ $impactLabel }}</span><br>
        <strong>是否影響上課：</strong>{{ $repairRequest->affects_class ? '是' : '否' }}<br>
        <strong>案件狀態：</strong>
        <span class="badge" style="background: {{ $statusColors[$repairRequest->status->value] }};">
            {{ $repairRequest->status->label() }}
        </span><br>
        <strong>維修人員：</strong>{{ $repairRequest->assignee_note ?? '未指派' }}
        @if ($repairRequest->scheduled_at)
            （預計 {{ $repairRequest->scheduled_at->format('Y-m-d H:i') }} 處理）
        @endif
        <br>
        <strong>送出時間：</strong>{{ $repairRequest->created_at->format('Y-m-d H:i') }}
    </p>

    @if ($repairRequest->rejection_reason)
        {{-- 這張案件曾經被驗收退回，把最新一次的退回原因秀出來，讓維修人員知道要補做什麼。 --}}
        <div class="errors">
            <strong>上次驗收退回原因：</strong>{{ $repairRequest->rejection_reason }}
        </div>
    @endif

    <h3>故障描述</h3>
    <p style="white-space: pre-line;">{{ $repairRequest->description }}</p>

    @if ($repairRequest->attachments->isNotEmpty())
        <h3>報修附件</h3>
        <ul>
            @foreach ($repairRequest->attachments as $attachment)
                <li><a href="{{ $attachment->url() }}" target="_blank">{{ $attachment->original_name }}</a></li>
            @endforeach
        </ul>
    @endif

    <div class="field" style="margin-top: 2rem; border-top: 1px solid #e4e7eb; padding-top: 1.5rem;">
        @if ($repairRequest->status === \App\Enums\RepairRequestStatus::Pending)
            <h3>派工</h3>
            <form method="POST" action="{{ route('repair-requests.assign', $repairRequest) }}">
                @csrf
                <div class="field">
                    <label for="assignee_note">維修人員（users 表尚未合併，先用文字記錄）</label>
                    <input type="text" id="assignee_note" name="assignee_note" required>
                </div>
                <div class="field">
                    <label for="scheduled_at">預計處理日期（選填）</label>
                    <input type="datetime-local" id="scheduled_at" name="scheduled_at">
                </div>
                <button class="btn btn-primary" type="submit">確認派工</button>
            </form>
        @elseif ($repairRequest->status === \App\Enums\RepairRequestStatus::Assigned)
            <form method="POST" action="{{ route('repair-requests.start', $repairRequest) }}">
                @csrf
                <button class="btn btn-primary" type="submit">開始處理</button>
            </form>
        @elseif ($repairRequest->status === \App\Enums\RepairRequestStatus::InProgress)
            <a class="btn btn-primary" href="{{ route('repair-logs.create', $repairRequest) }}">填寫維修紀錄</a>
        @elseif ($repairRequest->status === \App\Enums\RepairRequestStatus::PendingReview)
            {{-- 驗收兩條路：通過就結案（不用填原因），不通過要退回並說明原因。 --}}
            <h3>驗收</h3>
            <form method="POST" action="{{ route('repair-requests.complete', $repairRequest) }}" class="inline">
                @csrf
                <button class="btn btn-primary" type="submit">驗收通過，結案</button>
            </form>

            <details style="margin-top: 1rem;">
                <summary style="cursor:pointer; color:#dc2626;">驗收不通過，退回重新處理</summary>
                <form method="POST" action="{{ route('repair-requests.reject', $repairRequest) }}" style="margin-top: 0.8rem;">
                    @csrf
                    <div class="field">
                        <label for="rejection_reason">退回原因（維修人員會看到，請具體說明還有什麼問題）</label>
                        <textarea id="rejection_reason" name="rejection_reason" required></textarea>
                    </div>
                    <button class="btn btn-danger" type="submit">確認退回</button>
                </form>
            </details>
        @endif
    </div>

    @if ($repairRequest->repairLogs->isNotEmpty())
        <h3>維修紀錄</h3>
        @foreach ($repairRequest->repairLogs as $log)
            <div style="border:1px solid #e4e7eb; border-radius:4px; padding:1rem; margin-bottom:1rem; background:#fff;">
                <p>
                    <strong>處理時間：</strong>{{ $log->started_at->format('Y-m-d H:i') }} ～ {{ $log->ended_at->format('Y-m-d H:i') }}
                    （共 {{ $log->total_hours }} 小時）
                </p>
                <p><strong>故障原因：</strong>{{ $log->cause }}</p>
                <p><strong>處置方式：</strong>{{ $log->resolution }}</p>
                @if ($log->attachments->isNotEmpty())
                    <p><strong>維修前後照片：</strong></p>
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

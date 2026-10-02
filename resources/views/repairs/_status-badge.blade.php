{{-- 案件狀態徽章：看板列表與詳細頁共用，顏色集中在這裡管理。需傳入 $status（RepairRequestStatus）。 --}}
@php
    $statusBadgeClass = [
        'pending' => 'text-bg-secondary',
        'assigned' => 'text-bg-info',
        'in_progress' => 'text-bg-warning',
        'pending_review' => 'text-bg-primary',
        'completed' => 'text-bg-success',
    ][$status->value] ?? 'text-bg-light';
@endphp
<span class="badge {{ $statusBadgeClass }}">{{ $status->label() }}</span>

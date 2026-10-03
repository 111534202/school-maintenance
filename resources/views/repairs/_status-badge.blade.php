{{-- 案件狀態徽章：看板列表與詳細頁共用，顏色集中在這裡管理。需傳入 $status（RepairRequestStatus）。 --}}
@php
    // 各狀態對應的 Bootstrap 徽章顏色（想改顏色改這裡，看板與詳細頁都會跟著變）。
    $statusBadgeClass = [
        'pending' => 'text-bg-secondary',
        'assigned' => 'text-bg-info',
        'in_progress' => 'text-bg-warning',
        'pending_review' => 'text-bg-primary',
        'completed' => 'text-bg-success',
    // 用目前狀態的代碼查出顏色；查不到（未知狀態）就用淺灰色。
    ][$status->value] ?? 'text-bg-light';
@endphp
{{-- 輸出徽章：顏色 + 狀態的中文名稱（label() 依目前語言翻譯）。 --}}
<span class="badge {{ $statusBadgeClass }}">{{ $status->label() }}</span>

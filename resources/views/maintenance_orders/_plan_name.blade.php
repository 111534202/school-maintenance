{{-- 保養計畫名稱（保養紀錄、保養結果詳細共用）。名稱裡有全形冒號（例如「AI 測試資料：DEV-AI-001 月檢計畫」）時，
     冒號前後各自是一整段：放不下時從冒號後面換行，不會把「DEV-AI-001」從中間切斷。
     傳入 $plan（MaintenancePlan，可以是 null）、$withCycle（要不要接「（週期 N 天）」）。 --}}
@php
    $planName = (string) ($plan?->name ?? '');
    $colonAt = mb_strpos($planName, '：');
    $planHead = $colonAt === false ? '' : mb_substr($planName, 0, $colonAt + 1);
    $planTail = $colonAt === false ? $planName : mb_substr($planName, $colonAt + 1);
@endphp
@if ($planHead !== '')<span class="d-inline-block">{{ $planHead }}</span>@endif<span class="d-inline-block">{{ $planTail }}@if (! empty($withCycle))<span class="text-muted ms-1">（週期 {{ $plan->cycle_days }} 天）</span>@endif</span>

{{-- 「設備 / 類別」欄：有設備時顯示「設備編號（類別）」，設備編號可點到設備履歷；沒有設備（整個類別的保養）時只顯示類別。 --}}
@if ($order->device)
    <a href="{{ route('device-profile.show', $order->device) }}">{{ $order->device->device_code }}</a>@if ($order->categoryName())<span class="text-muted">（{{ $order->categoryName() }}）</span>@endif
@else
    {{ $order->categoryName() ?? '—' }}
@endif

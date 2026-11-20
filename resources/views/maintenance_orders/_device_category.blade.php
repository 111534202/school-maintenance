{{-- 「設備 / 類別」欄：列出工單的每個保養對象——設備顯示「設備編號（類別）」，設備編號可點到設備履歷；類別顯示「類別名稱（整類）」。
     一張工單可以有好幾個對象（保養計畫選了好幾台設備／類別），用「、」隔開。 --}}
@forelse ($order->targetItems() as $item)
    @if (! $loop->first)、@endif
    @if ($item['device'])
        <a href="{{ route('device-profile.show', $item['device']) }}">{{ $item['device']->device_code }}</a><span class="text-muted">（{{ $item['category']?->name ?? '—' }}）</span>
    @else
        {{ $item['category']?->name ?? '—' }}<span class="text-muted">（整類）</span>
    @endif
@empty
    —
@endforelse

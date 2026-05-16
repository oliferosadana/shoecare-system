@php
    $services = $order->items->map(fn ($item) => $item->service?->name)->filter()->unique()->join(' + ');
    $isOverdue = $order->estimated_finished_at?->isPast() && ! in_array($order->status, ['diambil', 'dibatalkan'], true);
@endphp

<a class="schedule-card {{ $isOverdue ? 'is-overdue' : '' }} {{ $compact ?? false ? 'is-compact' : '' }}" href="{{ route('orders.show', $order) }}">
    <div>
        <strong>{{ $order->invoice_number }}</strong>
        <span>{{ $order->customer?->name ?? '-' }} · {{ $order->customer?->phone ?? '-' }}</span>
    </div>
    <div>
        <span>{{ $services ?: '-' }}</span>
        <small>{{ $order->items->sum('quantity') }} pasang</small>
    </div>
    <time>{{ $order->estimated_finished_at?->format('d M Y H:i') ?? '-' }} WITA</time>
            <b>{{ $order->displayStatusLabel() }}</b>
</a>

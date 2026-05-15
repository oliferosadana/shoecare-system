@props([
    'payment',
    'showActions' => false,
    'order' => null,
])

<article class="qris-payment-card">
    <div class="qris-preview-box">
        @if ($payment->qr_url)
            <img src="{{ $payment->qr_url }}" alt="QRIS {{ $payment->provider_transaction_id }}">
        @elseif ($payment->qr_string)
            <canvas x-init="renderQris($el, @js($payment->qr_string))" aria-label="QRIS {{ $payment->provider_transaction_id }}"></canvas>
        @else
            <div class="qris-preview-empty">
                <i data-lucide="qr-code"></i>
                <span>QR belum tersedia</span>
            </div>
        @endif
    </div>

    <div class="qris-payment-info">
        <strong>Rp {{ number_format($payment->requested_amount, 0, ',', '.') }}</strong>
        <small>
            ID: {{ $payment->provider_transaction_id }}
            @if($payment->expires_at)
                · Exp: {{ $payment->expires_at->format('d M Y H:i') }} WITA
            @endif
        </small>

        @if ($payment->qr_url)
            <a href="{{ $payment->qr_url }}" target="_blank" rel="noopener">Buka QRIS</a>
        @elseif ($payment->qr_string)
            <code>{{ $payment->qr_string }}</code>
        @endif
    </div>

    @if ($showActions && $order)
        <form method="POST" action="{{ route('orders.qris.check', [$order, $payment]) }}">
            @csrf
            @method('PATCH')
            <button type="submit"><i data-lucide="refresh-cw"></i><span>Cek</span></button>
        </form>
        <form method="POST" action="{{ route('orders.qris.cancel', [$order, $payment]) }}">
            @csrf
            @method('DELETE')
            <button type="submit"><i data-lucide="x"></i><span>Cancel</span></button>
        </form>
    @endif
</article>

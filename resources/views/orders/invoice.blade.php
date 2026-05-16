<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Nota {{ $order->invoice_number }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="invoice-body" x-data="shoeCareApp()" x-init="$nextTick(refreshIcons)">
        <main class="invoice-sheet">
            <header class="invoice-head">
                <img src="{{ asset('assets/logo-putih.png') }}" alt="ZOLIX Shoe Care">
                <div>
                    <h1>Nota Order</h1>
                    <strong>{{ $order->invoice_number }}</strong>
                </div>
            </header>

            <section class="invoice-meta">
                <div><span>Pelanggan</span><strong>{{ $order->customer->name }}</strong><small>{{ $order->customer->phone }}</small></div>
                <div><span>Tanggal</span><strong>{{ $order->received_at->format('d M Y H:i') }} WITA</strong><small>{{ $order->customer->address ?: '-' }}</small></div>
                <div><span>Estimasi Selesai</span><strong>{{ $order->estimated_finished_at?->format('d M Y H:i') ?? '-' }} WITA</strong></div>
            </section>

            @php
                $invoiceFlowSteps = [
                    ['status' => 'diterima', 'label' => 'Diterima', 'caption' => 'Order diterima outlet'],
                    ['status' => 'proses', 'label' => 'Proses', 'caption' => 'Sepatu sedang dikerjakan'],
                    ['status' => 'selesai', 'label' => $order->usesDelivery() ? 'Siap Diantar' : 'Selesai', 'caption' => $order->usesDelivery() ? 'Siap dikirim ke pelanggan' : 'Siap diambil pelanggan'],
                ];
                $invoiceFlowProgress = match ($order->status) {
                    'proses' => 1,
                    'selesai', 'menunggu_diambil', 'diambil' => 2,
                    default => 0,
                };
            @endphp

            <section class="invoice-flow" aria-label="Timeline status order">
                <div class="invoice-flow-head">
                    <span>Alur Order</span>
                </div>
                <div class="invoice-flow-steps">
                    @foreach ($invoiceFlowSteps as $index => $step)
                        <div class="invoice-flow-step {{ $index <= $invoiceFlowProgress ? 'is-complete' : '' }} {{ $index < $invoiceFlowProgress ? 'is-past' : '' }} {{ $index === $invoiceFlowProgress ? 'is-current' : '' }}">
                            <div class="invoice-flow-dot">
                                @if ($index < $invoiceFlowProgress)
                                    <i data-lucide="check"></i>
                                @else
                                    <span>{{ $index + 1 }}</span>
                                @endif
                            </div>
                            <strong>{{ $step['label'] }}</strong>
                            <small>{{ $step['caption'] }}</small>
                        </div>
                    @endforeach
                </div>
            </section>

            <table class="invoice-table">
                <thead>
                    <tr><th>Item</th><th>Layanan</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>{{ $item->item_name }} @if($item->size)<small>Size {{ $item->size }}</small>@endif</td>
                            <td>{{ $item->service?->name ?? '-' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($item->line_total, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <section class="invoice-total">
                @php
                    $amountPaid = min($order->payments->where('status', 'paid')->sum('amount_paid'), $order->total_amount);
                    $remainingPayment = max($order->total_amount - $amountPaid, 0);
                    $pendingQrisPayments = $order->payments->where('provider', 'autogopay')->where('status', 'pending');
                @endphp
                <div><span>Subtotal</span><strong>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</strong></div>
                <div><span>Diskon</span><strong>Rp {{ number_format($order->discount_amount, 0, ',', '.') }}</strong></div>
                <div><span>{{ match($order->pickup_delivery_type ?? 'none') { 'pickup' => 'Ongkos Pickup', 'delivery' => 'Ongkos Delivery', 'pickup_delivery' => 'Ongkos Pickup + Delivery', default => 'Pickup / Delivery' } }}</span><strong>Rp {{ number_format($order->pickup_delivery_fee ?? 0, 0, ',', '.') }}</strong></div>
                <div><span>Total</span><strong>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong></div>
                <div><span>Dibayar</span><strong>Rp {{ number_format($amountPaid, 0, ',', '.') }}</strong></div>
                <div><span>Sisa</span><strong>Rp {{ number_format($remainingPayment, 0, ',', '.') }}</strong></div>
                <div><span>Status</span><strong>{{ match($order->payment_status) { 'paid' => 'Lunas', 'partial' => 'Bayar Sebagian', default => 'Belum Bayar' } }}</strong></div>
            </section>

            <section class="invoice-payment-menu">
                <div class="invoice-payment-head">
                    <div>
                        <span>Menu Pembayaran</span>
                        <h2>{{ $remainingPayment <= 0 ? 'Pembayaran Lunas' : 'Bayar Tagihan Order' }}</h2>
                        <p>
                            @if ($remainingPayment <= 0)
                                Terima kasih, pembayaran order ini sudah tercatat lunas.
                            @elseif ($pendingQrisPayments->isNotEmpty())
                                Scan QRIS aktif di bawah ini untuk melunasi sisa pembayaran.
                            @else
                                QRIS belum dibuat. Hubungi admin untuk meminta link pembayaran.
                            @endif
                        </p>
                    </div>
                    <strong>Rp {{ number_format($remainingPayment, 0, ',', '.') }}</strong>
                </div>

                @if ($pendingQrisPayments->isNotEmpty())
                    <div class="qris-payment-list">
                        @foreach ($pendingQrisPayments->sortByDesc('created_at') as $payment)
                            <x-qris-payment-card :payment="$payment" />
                        @endforeach
                    </div>
                @elseif ($remainingPayment <= 0)
                    <div class="invoice-payment-state invoice-payment-state--paid">
                        <i data-lucide="badge-check"></i>
                        <span>Order sudah lunas</span>
                    </div>
                @else
                    <div class="invoice-payment-state">
                        <i data-lucide="message-circle"></i>
                        <span>Minta admin membuat QRIS pembayaran untuk order ini.</span>
                    </div>
                @endif

                <div class="invoice-payment-links">
                    <a href="{{ route('orders.track', $order->invoice_number) }}" target="_blank" rel="noopener">Buka Tracking Customer</a>
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Hubungi WhatsApp</a>
                </div>
            </section>

            <footer class="invoice-actions">
                <a href="{{ route('orders.show', $order) }}">Kembali</a>
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Kirim WhatsApp</a>
                <button type="button" onclick="window.print()">Print Nota</button>
            </footer>
        </main>
    </body>
</html>

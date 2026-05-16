<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Tracking {{ $order->invoice_number }} - ZOLIX</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="tracking-body" x-data="shoeCareApp()" x-init="$nextTick(refreshIcons)" x-on:keydown.escape.window="closePhotoPreview()">
        <main class="tracking-shell">
            <header class="tracking-header">
                <img src="{{ asset('assets/logo-putih.png') }}" alt="ZOLIX Shoe Care">
            </header>

            <section class="tracking-hero">
                <p>No. Nota</p>
                <h1>{{ $order->invoice_number }}</h1>
                <span>{{ $order->customer->name }} · {{ $order->received_at->format('d M Y H:i') }} WITA</span>
                <small>Estimasi selesai: {{ $order->estimated_finished_at?->format('d M Y H:i') ?? '-' }} WITA</small>
            </section>

            @php
                $trackingFlowSteps = [
                    ['status' => 'diterima', 'label' => 'Diterima', 'caption' => 'Order diterima outlet'],
                    ['status' => 'proses', 'label' => 'Proses', 'caption' => 'Sepatu sedang dikerjakan'],
                    ['status' => 'selesai', 'label' => $order->usesDelivery() ? 'Siap Diantar' : 'Selesai', 'caption' => $order->usesDelivery() ? 'Siap dikirim ke pelanggan' : 'Siap diambil pelanggan'],
                ];
                $trackingFlowProgress = match ($order->status) {
                    'proses' => 1,
                    'selesai', 'menunggu_diambil', 'diambil' => 2,
                    default => 0,
                };
                $trackingFlowFinished = $order->status === 'diambil';
            @endphp

            <section class="invoice-flow tracking-flow" aria-label="Timeline status order">
                <div class="invoice-flow-head">
                    <span>Progress Order</span>
                </div>
                <div class="invoice-flow-steps">
                    @foreach ($trackingFlowSteps as $index => $step)
                        <div class="invoice-flow-step {{ $index <= $trackingFlowProgress ? 'is-complete' : '' }} {{ $index < $trackingFlowProgress ? 'is-past' : '' }} {{ $index === $trackingFlowProgress ? 'is-current' : '' }}">
                            <div class="invoice-flow-dot">
                                @if ($trackingFlowFinished || $index < $trackingFlowProgress)
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

            @if (session('success'))
                <div class="dashboard-flash">
                    <strong>Berhasil</strong>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (isset($errors) && $errors->any())
                <div class="dashboard-flash dashboard-flash--error">
                    <strong>Gagal</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <section class="tracking-card">
                <h2>Sepatu Anda</h2>
                <div class="detail-item-list">
                    @foreach ($order->items as $item)
                        <div class="detail-item-card">
                            <div class="detail-photo-pair">
                                <div class="detail-photo">
                                    <b>Before</b>
                                    @if ($item->before_photo_path)
                                        <button class="detail-photo-button" type="button" x-on:click="openPhotoPreview(@js(Storage::url($item->before_photo_path)), @js('Before - ' . $item->item_name))">
                                            <img src="{{ Storage::url($item->before_photo_path) }}" alt="Before {{ $item->item_name }}">
                                            <span><i data-lucide="zoom-in"></i> Perbesar</span>
                                        </button>
                                    @else
                                        <i data-lucide="camera-off"></i>
                                    @endif
                                </div>
                                <div class="detail-photo">
                                    <b>After</b>
                                    @if ($item->after_photo_path)
                                        <button class="detail-photo-button" type="button" x-on:click="openPhotoPreview(@js(Storage::url($item->after_photo_path)), @js('After - ' . $item->item_name))">
                                            <img src="{{ Storage::url($item->after_photo_path) }}" alt="After {{ $item->item_name }}">
                                            <span><i data-lucide="zoom-in"></i> Perbesar</span>
                                        </button>
                                    @else
                                        <span>Belum tersedia</span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <span class="service-badge">{{ $item->service?->name ?? 'Layanan' }}</span>
                                <h3>{{ $item->item_name }}</h3>
                                <p>{{ $item->quantity }} Pasang @if($item->size) · Size {{ $item->size }} @endif</p>
                            </div>
                            <strong>Rp {{ number_format($item->line_total, 0, ',', '.') }}</strong>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="tracking-card">
                @php
                    $amountPaid = min($order->payments->where('status', 'paid')->sum('amount_paid'), $order->total_amount);
                    $remainingPayment = max($order->total_amount - $amountPaid, 0);
                    $pendingQrisPayments = $order->payments
                        ->where('provider', 'autogopay')
                        ->where('status', 'pending')
                        ->filter(fn ($payment) => ! $payment->expires_at || $payment->expires_at->isFuture());
                    $expiredQrisPayments = $order->payments
                        ->where('provider', 'autogopay')
                        ->filter(fn ($payment) => $payment->status === 'expired' || ($payment->status === 'pending' && $payment->expires_at && $payment->expires_at->isPast()));
                    $pendingTransferPayments = $order->payments->whereNull('provider')->where('method', 'transfer')->where('status', 'pending');
                @endphp
                <div class="reference-price-lines">
                    <div><span>Subtotal</span><strong>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</strong></div>
                    <div><span>Diskon</span><strong>Rp {{ number_format($order->discount_amount, 0, ',', '.') }}</strong></div>
                    <div><span>{{ match($order->pickup_delivery_type ?? 'none') { 'pickup' => 'Ongkos Pickup', 'delivery' => 'Ongkos Delivery', 'pickup_delivery' => 'Ongkos Pickup + Delivery', default => 'Pickup / Delivery' } }}</span><strong>Rp {{ number_format($order->pickup_delivery_fee ?? 0, 0, ',', '.') }}</strong></div>
                    <div><span>Dibayar</span><strong>Rp {{ number_format($amountPaid, 0, ',', '.') }}</strong></div>
                    <div><span>Sisa</span><strong>Rp {{ number_format($remainingPayment, 0, ',', '.') }}</strong></div>
                </div>
                <div class="total-pay-card"><span>Total</span><strong>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong></div>

                @if ($remainingPayment > 0)
                    <div class="tracking-method-grid">
                        <form method="POST" action="{{ route('orders.track.qris.generate', $order->invoice_number) }}">
                            @csrf
                            <button type="submit" class="tracking-method-card is-primary">
                                <i data-lucide="qr-code"></i>
                                <strong>QRIS Otomatis</strong>
                                <span>Generate dan bayar sekarang</span>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('orders.track.payment-method.store', $order->invoice_number) }}">
                            @csrf
                            <input type="hidden" name="method" value="cash">
                            <button type="submit" class="tracking-method-card">
                                <i data-lucide="store"></i>
                                <strong>Cash di Outlet</strong>
                                <span>Bayar saat ambil sepatu</span>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('orders.track.payment-method.store', $order->invoice_number) }}">
                            @csrf
                            <input type="hidden" name="method" value="transfer">
                            <button type="submit" class="tracking-method-card">
                                <i data-lucide="landmark"></i>
                                <strong>Transfer Manual</strong>
                                <span>Admin akan verifikasi manual</span>
                            </button>
                        </form>
                    </div>
                @endif

                @if ($pendingTransferPayments->isNotEmpty())
                    <div class="tracking-transfer-proof">
                        <h2>Upload Bukti Transfer</h2>
                        <p>Jika sudah transfer manual, upload bukti pembayaran agar admin bisa verifikasi.</p>
                        @foreach ($pendingTransferPayments->sortByDesc('created_at') as $payment)
                            <article>
                                <div>
                                    <strong>Transfer Manual</strong>
                                    <span>Nominal: Rp {{ number_format($payment->requested_amount, 0, ',', '.') }}</span>
                                    @if ($payment->proof_photo_path)
                                        <a href="{{ Storage::url($payment->proof_photo_path) }}" target="_blank" rel="noopener">Lihat bukti yang sudah diupload</a>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('orders.track.payment-method.proof', [$order->invoice_number, $payment]) }}" enctype="multipart/form-data">
                                    @csrf
                                    <label>
                                        <input type="file" name="proof_photo" accept="image/*" required onchange="this.form.submit()">
                                        <i data-lucide="upload-cloud"></i>
                                        <span>{{ $payment->proof_photo_path ? 'Ganti Bukti' : 'Upload Bukti' }}</span>
                                    </label>
                                </form>
                            </article>
                        @endforeach
                    </div>
                @endif

                @if ($expiredQrisPayments->isNotEmpty() && $remainingPayment > 0)
                    <div class="tracking-qris-expired">
                        <div>
                            <h2>QRIS sebelumnya expired</h2>
                            <p>QRIS melewati batas waktu pembayaran. Buat QRIS baru untuk melanjutkan pembayaran.</p>
                        </div>
                        <form method="POST" action="{{ route('orders.track.qris.generate', $order->invoice_number) }}">
                            @csrf
                            <button type="submit">
                                <i data-lucide="refresh-cw"></i>
                                <span>Buat QRIS Baru</span>
                            </button>
                        </form>
                    </div>
                @endif

                @if ($pendingQrisPayments->isNotEmpty())
                    <div id="tracking-qris-section" class="tracking-qris">
                        <h2>Scan QRIS Pembayaran</h2>
                        <p>QRIS aktif untuk pelunasan order ini.</p>
                        <div class="qris-payment-list">
                            @foreach ($pendingQrisPayments->sortByDesc('created_at') as $payment)
                                <x-qris-payment-card :payment="$payment" />
                                <form class="tracking-qris-check-form" method="POST" action="{{ route('orders.track.qris.check', [$order->invoice_number, $payment]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit">
                                        <i data-lucide="refresh-cw"></i>
                                        <span>Cek Status Pembayaran</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endif
                <!-- <a class="create-order-button tracking-whatsapp" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><i data-lucide="message-circle"></i><span>Hubungi WhatsApp</span></a> -->
            </section>
        </main>
        <div
            class="photo-preview-modal"
            x-cloak
            x-show="photoPreview.open"
            x-transition.opacity
            x-on:click.self="closePhotoPreview()"
            role="dialog"
            aria-modal="true"
            aria-label="Preview foto sepatu"
        >
            <div class="photo-preview-dialog" x-transition.scale.origin.center>
                <button class="photo-preview-close" type="button" x-on:click="closePhotoPreview()" aria-label="Tutup preview foto">
                    <i data-lucide="x"></i>
                </button>
                <img x-bind:src="photoPreview.src" x-bind:alt="photoPreview.title">
                <strong x-text="photoPreview.title"></strong>
            </div>
        </div>
    </body>
</html>

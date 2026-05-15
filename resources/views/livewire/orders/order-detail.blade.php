<section class="order-detail-layout" wire:poll.30s>
    <div class="order-detail-main">
        @if ($success)
            <div class="dashboard-flash">
                <strong>Berhasil</strong>
                <span>{{ $success }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="dashboard-flash dashboard-flash--error">
                <strong>Gagal</strong>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <article class="order-detail-card order-detail-hero">
            <div>
                <span class="service-badge">{{ match($order->payment_status) { 'paid' => 'Lunas', 'partial' => 'Bayar Sebagian', default => 'Belum Bayar' } }}</span>
                <h2>{{ $order->invoice_number }}</h2>
                <p>{{ $order->notes ?: 'Order tersimpan di sistem ZOLIX Shoe Care.' }}</p>
            </div>
            <span class="dashboard-status tag--{{ match($order->status) { 'proses' => 'proses', 'selesai' => 'selesai', 'diambil' => 'diambil', 'menunggu_diambil' => 'menunggu', 'dibatalkan' => 'dibatalkan', default => 'diterima' } }}">
                {{ match($order->status) { 'proses' => 'Proses', 'selesai' => 'Selesai', 'diambil' => 'Diambil', 'menunggu_diambil' => 'Menunggu Diambil', 'dibatalkan' => 'Dibatalkan', default => 'Diterima' } }}
            </span>
            @if ($order->estimated_finished_at && $order->estimated_finished_at->isPast() && ! in_array($order->status, ['diambil', 'dibatalkan'], true))
                <span class="deadline-pill is-overdue">Deadline lewat</span>
            @endif
        </article>

        <article class="order-detail-card">
            <div class="panel-head">
                <h2>Update Status</h2>
            </div>
            <form class="status-update-form" wire:submit.prevent="updateStatus">
                <label>
                    <span>Status Order</span>
                    <select wire:model="selectedStatus">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" wire:loading.attr="disabled" wire:target="updateStatus">
                    <i data-lucide="refresh-cw"></i>
                    <span wire:loading.remove wire:target="updateStatus">Update Status</span>
                    <span wire:loading wire:target="updateStatus">Memproses...</span>
                </button>
            </form>
        </article>

        <article class="order-detail-card">
            <div class="panel-head">
                <h2>Item & Foto Before</h2>
            </div>

            <div class="detail-item-list">
                @foreach ($order->items as $item)
                    <div class="detail-item-card" wire:key="detail-item-{{ $item->id }}">
                        <div class="detail-photo-pair">
                            <div class="detail-photo">
                                <b>Before</b>
                                @if ($item->before_photo_path)
                                    <img src="{{ Storage::url($item->before_photo_path) }}" alt="Foto before {{ $item->item_name }}">
                                @else
                                    <i data-lucide="camera-off"></i>
                                    <span>Belum ada foto</span>
                                @endif
                            </div>
                            <div class="detail-photo">
                                <b>After</b>
                                @if ($item->after_photo_path)
                                    <img src="{{ Storage::url($item->after_photo_path) }}" alt="Foto after {{ $item->item_name }}">
                                @else
                                    <label>
                                        <input type="file" wire:model="afterPhotos.{{ $item->id }}" accept="image/*">
                                        <i data-lucide="camera"></i>
                                        <span>Upload after</span>
                                    </label>
                                    @if (isset($afterPhotos[$item->id]))
                                        <button type="button" wire:click="uploadAfterPhoto({{ $item->id }})" wire:loading.attr="disabled" wire:target="uploadAfterPhoto({{ $item->id }})">
                                            <i data-lucide="upload"></i>
                                            <span>Simpan Foto</span>
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                        <div>
                            <span class="service-badge">{{ $item->service?->name ?? 'Layanan' }}</span>
                            <h3>{{ $item->item_name }}</h3>
                            <p>{{ $item->quantity }} Pasang @if ($item->size) · Size {{ $item->size }} @endif</p>
                            @if ($item->notes)
                                <small>{{ $item->notes }}</small>
                            @endif
                        </div>
                        <strong>Rp {{ number_format($item->line_total, 0, ',', '.') }}</strong>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="order-detail-card">
            <div class="panel-head">
                <h2>Timeline</h2>
            </div>
            <div class="detail-timeline">
                @forelse ($order->timelines->sortByDesc('logged_at') as $timeline)
                    <div wire:key="timeline-{{ $timeline->id }}">
                        <span></span>
                        <strong>{{ $timeline->label }}</strong>
                        <p>{{ $timeline->description }}</p>
                        <small>{{ $timeline->logged_at->format('d M Y H:i') }} WITA</small>
                    </div>
                @empty
                    <p>Belum ada timeline.</p>
                @endforelse
            </div>
        </article>
    </div>

    <aside class="order-detail-side">
        <article class="order-detail-card">
            <h2>Data Pelanggan</h2>
            <dl class="detail-data-list">
                <div><dt>Nama</dt><dd>{{ $order->customer->name }}</dd></div>
                <div><dt>WhatsApp</dt><dd>{{ $order->customer->phone }}</dd></div>
                <div><dt>Alamat</dt><dd>{{ $order->customer->address ?: '-' }}</dd></div>
                <div><dt>Tanggal Masuk</dt><dd>{{ $order->received_at->format('d M Y H:i') }} WITA</dd></div>
                <div><dt>Estimasi</dt><dd>{{ $order->estimated_finished_at?->format('d M Y H:i') ?? '-' }} WITA</dd></div>
            </dl>
            <div class="detail-action-stack">
                <a class="reset-order-button" href="{{ route('customers.show', $order->customer) }}">
                    <i data-lucide="user-round"></i>
                    <span>Lihat Profil Pelanggan</span>
                </a>
            </div>
        </article>

        <article class="order-detail-card">
            <h2>Ringkasan Pembayaran</h2>
            <div class="reference-price-lines">
                <div><span>Subtotal</span><strong>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</strong></div>
                <div><span>Diskon</span><strong>Rp {{ number_format($order->discount_amount, 0, ',', '.') }}</strong></div>
                <div><span>{{ match($order->pickup_delivery_type ?? 'none') { 'pickup' => 'Ongkos Pickup', 'delivery' => 'Ongkos Delivery', 'pickup_delivery' => 'Ongkos Pickup + Delivery', default => 'Pickup / Delivery' } }}</span><strong>Rp {{ number_format($order->pickup_delivery_fee ?? 0, 0, ',', '.') }}</strong></div>
                <div><span>Total</span><strong>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong></div>
                <div><span>Sudah Dibayar</span><strong>Rp {{ number_format($amountPaid, 0, ',', '.') }}</strong></div>
                <div><span>Sisa Bayar</span><strong>Rp {{ number_format($remainingPayment, 0, ',', '.') }}</strong></div>
            </div>
            <div class="total-pay-card">
                <span>Status Bayar</span>
                <strong>{{ match($order->payment_status) { 'paid' => 'Lunas', 'partial' => 'Sebagian', default => 'Belum Bayar' } }}</strong>
            </div>

            <form class="payment-update-form qris-generate-form" wire:submit.prevent="generateQris">
                <label>
                    <span>Nominal QRIS</span>
                    <input wire:model.live.debounce.300ms="qrisAmount" inputmode="numeric" placeholder="Contoh: 50000" @disabled($remainingPayment <= 0)>
                </label>
                <button type="submit" @disabled($remainingPayment <= 0) wire:loading.attr="disabled" wire:target="generateQris">
                    <i data-lucide="qr-code"></i>
                    <span wire:loading.remove wire:target="generateQris">Buat QRIS AutoGopay</span>
                    <span wire:loading wire:target="generateQris">Membuat...</span>
                </button>
            </form>

            @if ($pendingQrisPayments->isNotEmpty())
                <div class="qris-payment-list">
                    <h3>QRIS Pending</h3>
                    @foreach ($pendingQrisPayments as $payment)
                        <div wire:key="qris-pending-{{ $payment->id }}">
                            <x-qris-payment-card :payment="$payment" :order="$order" />
                            <div class="payment-verify-form">
                                <button type="button" wire:click="checkQris({{ $payment->id }})" wire:loading.attr="disabled" wire:target="checkQris({{ $payment->id }})">
                                    <i data-lucide="refresh-cw"></i>
                                    <span>Cek QRIS</span>
                                </button>
                                <button type="button" wire:click="cancelQris({{ $payment->id }})" wire:loading.attr="disabled" wire:target="cancelQris({{ $payment->id }})">
                                    <i data-lucide="x"></i>
                                    <span>Cancel</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($expiredQrisPayments->isNotEmpty())
                <div class="expired-qris-list">
                    <h3>QRIS Expired</h3>
                    @foreach ($expiredQrisPayments as $payment)
                        <article wire:key="qris-expired-{{ $payment->id }}">
                            <i data-lucide="timer-off"></i>
                            <div>
                                <strong>Rp {{ number_format($payment->requested_amount, 0, ',', '.') }}</strong>
                                <span>ID: {{ $payment->provider_transaction_id }}</span>
                                <small>Expired: {{ $payment->expires_at?->format('d M Y H:i') ?? '-' }} WITA</small>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            @if ($pendingManualPayments->isNotEmpty())
                <div class="pending-payment-request-list">
                    <h3>Pilihan Pembayaran Customer</h3>
                    @foreach ($pendingManualPayments as $payment)
                        <article wire:key="manual-payment-{{ $payment->id }}">
                            <i data-lucide="{{ $payment->method === 'cash' ? 'store' : 'landmark' }}"></i>
                            <div>
                                <strong>{{ $payment->method === 'cash' ? 'Cash di Outlet' : 'Transfer Manual' }}</strong>
                                <span>Nominal: Rp {{ number_format($payment->requested_amount, 0, ',', '.') }}</span>
                                <small>{{ $payment->notes ?: 'Menunggu konfirmasi admin.' }}</small>
                                @if ($payment->proof_photo_path)
                                    <a class="payment-proof-link" href="{{ Storage::url($payment->proof_photo_path) }}" target="_blank" rel="noopener">
                                        <img src="{{ Storage::url($payment->proof_photo_path) }}" alt="Bukti transfer {{ $order->invoice_number }}">
                                        <span>Lihat Bukti Transfer</span>
                                    </a>
                                @endif
                                <form class="payment-verify-form" wire:submit.prevent="verifyPaymentRequest({{ $payment->id }})">
                                    <button type="submit" @disabled($payment->method === 'transfer' && ! $payment->proof_photo_path) wire:loading.attr="disabled" wire:target="verifyPaymentRequest({{ $payment->id }})">
                                        <i data-lucide="badge-check"></i>
                                        <span>Verifikasi Pembayaran</span>
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            <form class="payment-update-form" wire:submit.prevent="recordPayment">
                <label>
                    <span>Tambah Pembayaran</span>
                    <input wire:model.live.debounce.300ms="amountPaid" inputmode="numeric" placeholder="Contoh: 50000" required>
                </label>
                <label>
                    <span>Metode</span>
                    <select wire:model="method" required>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>No. Referensi</span>
                    <input wire:model.live.debounce.400ms="referenceNumber" placeholder="Opsional">
                </label>
                <label>
                    <span>Catatan</span>
                    <input wire:model.live.debounce.400ms="paymentNotes" placeholder="Opsional">
                </label>
                <button type="submit" wire:loading.attr="disabled" wire:target="recordPayment">
                    <i data-lucide="wallet-cards"></i>
                    <span wire:loading.remove wire:target="recordPayment">Catat Pembayaran</span>
                    <span wire:loading wire:target="recordPayment">Menyimpan...</span>
                </button>
            </form>

            <div class="payment-history">
                <h3>Riwayat Pembayaran</h3>
                @forelse ($order->payments->where('status', 'paid')->sortByDesc('paid_at') as $payment)
                    <div class="payment-history-row" wire:key="paid-payment-{{ $payment->id }}">
                        <div class="payment-history-meta">
                            <span>{{ $paymentMethods[$payment->method] ?? ucfirst($payment->method) }}</span>
                            <strong>Rp {{ number_format($payment->amount_paid, 0, ',', '.') }}</strong>
                            <small>{{ $payment->paid_at?->format('d M Y H:i') ?? '-' }} WITA @if($payment->reference_number) · Ref: {{ $payment->reference_number }} @endif</small>
                            @if ($payment->notes)
                                <small>{{ $payment->notes }}</small>
                            @endif
                        </div>
                        <button type="button" title="Hapus pembayaran" wire:click="deletePayment({{ $payment->id }})" wire:confirm="Hapus transaksi pembayaran ini?">
                            <i data-lucide="trash-2"></i>
                        </button>
                    </div>
                @empty
                    <p>Belum ada pembayaran tercatat.</p>
                @endforelse
            </div>

            <div class="detail-action-stack">
                <a class="create-order-button" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">
                    <i data-lucide="message-circle"></i>
                    <span>Kirim WhatsApp</span>
                </a>
                <a class="reset-order-button whatsapp-reminder-button" href="{{ $whatsappReminderUrl }}" target="_blank" rel="noopener">
                    <i data-lucide="alarm-clock"></i>
                    <span>Reminder Terlambat</span>
                </a>
                <a class="reset-order-button whatsapp-ready-button" href="{{ $whatsappReadyPickupUrl }}" target="_blank" rel="noopener">
                    <i data-lucide="package-check"></i>
                    <span>Order Siap Diambil</span>
                </a>
                <a class="reset-order-button whatsapp-billing-button" href="{{ $whatsappBillingUrl }}" target="_blank" rel="noopener">
                    <i data-lucide="receipt-text"></i>
                    <span>Kirim Total Tagihan</span>
                </a>
                <a class="reset-order-button" href="{{ route('orders.invoice', $order) }}" target="_blank">
                    <i data-lucide="printer"></i>
                    <span>Cetak Nota</span>
                </a>
                <a class="reset-order-button" href="{{ route('orders.track', $order->invoice_number) }}" target="_blank">
                    <i data-lucide="external-link"></i>
                    <span>Tracking Customer</span>
                </a>
            </div>
        </article>
    </aside>
</section>

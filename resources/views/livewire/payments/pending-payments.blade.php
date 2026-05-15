<div wire:poll.30s>
    @if ($success)
        <div class="dashboard-flash">
            <strong>Berhasil</strong>
            <span>{{ $success }}</span>
        </div>
    @endif

    @if ($error)
        <div class="dashboard-flash dashboard-flash--error">
            <strong>Gagal</strong>
            <span>{{ $error }}</span>
        </div>
    @endif

    <section class="pending-payment-stats">
        <article><span>Total Pending</span><strong>{{ $summary['all'] }}</strong><small>Semua metode</small></article>
        <article><span>QRIS</span><strong>{{ $summary['qris'] }}</strong><small>Menunggu pembayaran</small></article>
        <article><span>Transfer</span><strong>{{ $summary['transfer'] }}</strong><small>{{ $summary['transfer_with_proof'] }} sudah upload bukti</small></article>
        <article><span>Cash Outlet</span><strong>{{ $summary['cash'] }}</strong><small>Bayar saat ambil</small></article>
        <article><span>QRIS Expired</span><strong>{{ $summary['expired_qris'] }}</strong><small>Perlu generate ulang</small></article>
    </section>

    <nav class="pending-payment-tabs">
        <button class="{{ $method === '' ? 'is-active' : '' }}" type="button" wire:click="setMethod('')">Semua</button>
        <button class="{{ $method === 'qris' ? 'is-active' : '' }}" type="button" wire:click="setMethod('qris')">QRIS</button>
        <button class="{{ $method === 'transfer' ? 'is-active' : '' }}" type="button" wire:click="setMethod('transfer')">Transfer Manual</button>
        <button class="{{ $method === 'cash' ? 'is-active' : '' }}" type="button" wire:click="setMethod('cash')">Cash Outlet</button>
        <button class="{{ $method === 'expired' ? 'is-active' : '' }}" type="button" wire:click="setMethod('expired')">QRIS Expired</button>
    </nav>

    <section class="pending-payment-list">
        <div class="pending-payment-head">
            <span>Invoice</span>
            <span>Customer</span>
            <span>Metode</span>
            <span>Nominal</span>
            <span>Status</span>
            <span>Aksi</span>
        </div>

        @forelse ($payments as $payment)
            @php
                $order = $payment->order;
                $methodLabel = match($payment->method) {
                    'qris' => 'QRIS AutoGopay',
                    'transfer' => 'Transfer Manual',
                    'cash' => 'Cash di Outlet',
                    default => ucfirst($payment->method ?? 'Lainnya'),
                };
            @endphp
            <article wire:key="pending-payment-{{ $payment->id }}">
                <div>
                    <strong>{{ $order?->invoice_number ?? '-' }}</strong>
                    <small>{{ $payment->created_at->format('d M Y H:i') }} WITA</small>
                </div>
                <div>
                    <strong>{{ $order?->customer?->name ?? '-' }}</strong>
                    <small>{{ $order?->customer?->phone ?? '-' }}</small>
                </div>
                <div>
                    <span class="pending-method pending-method--{{ $payment->method }}">{{ $methodLabel }}</span>
                    @if ($payment->proof_photo_path)
                        <a class="pending-proof-mini" href="{{ Storage::url($payment->proof_photo_path) }}" target="_blank" rel="noopener">Bukti tersedia</a>
                    @endif
                </div>
                <strong>Rp {{ number_format($payment->requested_amount, 0, ',', '.') }}</strong>
                <div>
                    @if ($payment->status === 'expired')
                        <span class="pending-status pending-status--expired">Expired</span>
                        @if ($payment->expires_at)
                            <small>{{ $payment->expires_at->format('d M Y H:i') }}</small>
                        @endif
                    @elseif ($payment->method === 'qris')
                        <span class="pending-status">Menunggu dibayar</span>
                        @if ($payment->expires_at)
                            <small>Exp: {{ $payment->expires_at->format('d M Y H:i') }}</small>
                        @endif
                    @elseif ($payment->method === 'transfer' && $payment->proof_photo_path)
                        <span class="pending-status pending-status--ready">Siap diverifikasi</span>
                    @else
                        <span class="pending-status">Menunggu customer</span>
                    @endif
                </div>
                <div class="pending-payment-actions">
                    @if ($payment->method === 'qris' && $payment->status === 'pending')
                        <button type="button" wire:click="checkQris({{ $payment->id }})" wire:loading.attr="disabled" wire:target="checkQris({{ $payment->id }})">
                            <i data-lucide="refresh-cw"></i><span>Cek</span>
                        </button>
                    @endif
                    @if ($payment->method === 'qris' && $payment->status === 'expired')
                        <button type="button" wire:click="regenerateQris({{ $payment->id }})" wire:loading.attr="disabled" wire:target="regenerateQris({{ $payment->id }})">
                            <i data-lucide="refresh-cw"></i><span>Generate Ulang</span>
                        </button>
                    @endif
                    @if (in_array($payment->method, ['transfer', 'cash'], true))
                        <button type="button" wire:click="verify({{ $payment->id }})" wire:loading.attr="disabled" @disabled($payment->method === 'transfer' && ! $payment->proof_photo_path)>
                            <i data-lucide="badge-check"></i><span>Verifikasi</span>
                        </button>
                    @endif
                    <a href="{{ route('orders.show', $order) }}"><i data-lucide="eye"></i><span>Detail</span></a>
                </div>
            </article>
        @empty
            <div class="pending-payment-empty">
                <strong>Tidak ada pembayaran pending</strong>
                <p>Semua pembayaran sudah terselesaikan atau belum ada request pembayaran customer.</p>
            </div>
        @endforelse
    </section>

    <footer class="dashboard-pagination">
        <p>Menampilkan {{ $payments->firstItem() ?? 0 }} - {{ $payments->lastItem() ?? 0 }} dari {{ $payments->total() }} pembayaran</p>
        <div></div>
        <div class="dashboard-pages">{{ $payments->links() }}</div>
    </footer>
</div>

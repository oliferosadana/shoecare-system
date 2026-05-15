<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $customer->name }} - ZOLIX Shoe Care</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="dashboard-body" x-data="shoeCareApp()" x-init="$nextTick(refreshIcons)">
        <div class="dashboard-shell">
            @include('partials.admin-sidebar', ['active' => 'customers'])

            <main class="dashboard-main">
                <header class="orders-page-header">
                    <div>
                        <h1>Detail Pelanggan</h1>
                        <p>{{ $customer->name }} · {{ $customer->phone }}</p>
                    </div>
                    <div class="orders-header-actions">
                        <a class="create-back-link" href="{{ route('customers.index') }}"><i data-lucide="arrow-left"></i><span>Kembali</span></a>
                        <a class="create-back-link" href="{{ route('customers.edit', $customer) }}"><i data-lucide="pencil"></i><span>Edit</span></a>
                        <a class="dashboard-add" href="{{ route('orders.create') }}"><i data-lucide="circle-plus"></i><span>Tambah Order</span></a>
                    </div>
                </header>

                @if (session('success'))
                    <div class="dashboard-flash"><strong>Berhasil</strong><span>{{ session('success') }}</span></div>
                @endif

                <section class="customer-detail-layout">
                    <aside class="order-detail-card customer-profile-card">
                        <div class="customer-profile-avatar">{{ strtoupper(substr($customer->name, 0, 1)) }}</div>
                        <h2>{{ $customer->name }}</h2>
                        <p>{{ $customer->notes ?: 'Belum ada catatan khusus.' }}</p>
                        <dl class="detail-data-list">
                            <div><dt>WhatsApp</dt><dd>{{ $customer->phone }}</dd></div>
                            <div><dt>Email</dt><dd>{{ $customer->email ?: '-' }}</dd></div>
                            <div><dt>Alamat</dt><dd>{{ $customer->address ?: '-' }}</dd></div>
                            <div><dt>Terdaftar</dt><dd>{{ $customer->created_at->format('d M Y') }}</dd></div>
                        </dl>
                    </aside>

                    <div class="customer-detail-main">
                        <section class="customer-stat-grid">
                            <article><span>Total Order</span><strong>{{ $customer->orders->count() }}</strong><small>Semua status</small></article>
                            <article><span>Total Belanja</span><strong>Rp {{ number_format($totalSpent, 0, ',', '.') }}</strong><small>Tidak termasuk batal</small></article>
                            <article><span>Total Dibayar</span><strong>Rp {{ number_format($totalPaid, 0, ',', '.') }}</strong><small>Dari riwayat pembayaran</small></article>
                        </section>

                        <article class="overview-panel report-order-panel">
                            <div class="panel-head">
                                <h2>Riwayat Order</h2>
                                <a href="{{ route('orders.index', ['q' => $customer->phone]) }}">Cari di List Order</a>
                            </div>
                            <div class="report-order-list">
                                <div class="report-order-head">
                                    <span>Nota</span><span>Layanan</span><span>Total</span><span>Dibayar</span><span>Sisa</span><span>Status</span>
                                </div>
                                @forelse ($customer->orders as $order)
                                    @php
                                        $paid = min($order->payments->where('status', 'paid')->sum('amount_paid'), $order->total_amount);
                                        $services = $order->items->map(fn ($item) => $item->service?->name)->filter()->unique()->join(' + ');
                                    @endphp
                                    <a class="report-order-row" href="{{ route('orders.show', $order) }}">
                                        <strong>{{ $order->invoice_number }}</strong>
                                        <span>{{ $services ?: '-' }}</span>
                                        <span>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                                        <span>Rp {{ number_format($paid, 0, ',', '.') }}</span>
                                        <span>Rp {{ number_format(max($order->total_amount - $paid, 0), 0, ',', '.') }}</span>
                                        <b>{{ match($order->status) { 'proses' => 'Proses', 'selesai' => 'Selesai', 'diambil' => 'Diambil', 'menunggu_diambil' => 'Menunggu', 'dibatalkan' => 'Batal', default => 'Diterima' } }}</b>
                                    </a>
                                @empty
                                    <div class="orders-table__empty"><strong>Belum ada order</strong><p>Buat order pertama untuk pelanggan ini.</p></div>
                                @endforelse
                            </div>
                        </article>
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>

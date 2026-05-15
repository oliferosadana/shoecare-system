<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Laporan Pendapatan - ZOLIX Shoe Care</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="dashboard-body" x-data="shoeCareApp()" x-init="$nextTick(refreshIcons)">
        <div class="dashboard-shell">
            @include('partials.admin-sidebar', ['active' => 'reports'])

            <main class="dashboard-main">
                <header class="orders-page-header">
                    <div>
                        <h1>Laporan Pendapatan</h1>
                        <p>Ringkasan omzet, pembayaran masuk, dan piutang order.</p>
                    </div>
                    <div class="orders-header-actions">
                        <a class="create-back-link" href="{{ route('reports.revenue.export', request()->query()) }}">
                            <i data-lucide="download"></i>
                            <span>Export CSV</span>
                        </a>
                        <button class="dashboard-add" type="button" onclick="window.print()">
                            <i data-lucide="printer"></i>
                            <span>Print</span>
                        </button>
                    </div>
                </header>

                <form class="report-filter-card" method="GET" action="{{ route('reports.revenue') }}">
                    <label>
                        <span>Dari Tanggal</span>
                        <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}">
                    </label>
                    <label>
                        <span>Sampai Tanggal</span>
                        <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}">
                    </label>
                    <button type="submit">
                        <i data-lucide="filter"></i>
                        <span>Terapkan Filter</span>
                    </button>
                </form>

                <section class="report-stat-grid">
                    <article><span>Omzet Order</span><strong>Rp {{ number_format($summary['gross_revenue'], 0, ',', '.') }}</strong><small>{{ $summary['orders_count'] }} order</small></article>
                    <article><span>Terbayar</span><strong>Rp {{ number_format($summary['paid_amount'], 0, ',', '.') }}</strong><small>{{ $summary['paid_count'] }} lunas</small></article>
                    <article><span>Piutang</span><strong>Rp {{ number_format($summary['outstanding'], 0, ',', '.') }}</strong><small>{{ $summary['partial_count'] }} sebagian, {{ $summary['unpaid_count'] }} belum bayar</small></article>
                    <article><span>Diskon</span><strong>Rp {{ number_format($summary['discount'], 0, ',', '.') }}</strong><small>Potongan periode ini</small></article>
                    <article><span>Dibatalkan</span><strong>Rp {{ number_format($summary['cancelled'], 0, ',', '.') }}</strong><small>Tidak dihitung omzet</small></article>
                </section>

                <section class="report-grid">
                    <article class="overview-panel">
                        <div class="panel-head">
                            <h2>Metode Pembayaran</h2>
                            <span>{{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}</span>
                        </div>
                        <div class="payment-method-list">
                            @forelse ($methodSummary as $method)
                                <div>
                                    <span>{{ $method['method'] }}</span>
                                    <strong>Rp {{ number_format($method['amount'], 0, ',', '.') }}</strong>
                                    <small>{{ $method['count'] }} transaksi</small>
                                </div>
                            @empty
                                <div>
                                    <span>Belum ada pembayaran</span>
                                    <strong>Rp 0</strong>
                                    <small>0 transaksi</small>
                                </div>
                            @endforelse
                        </div>
                    </article>

                    <article class="overview-panel report-order-panel">
                        <div class="panel-head">
                            <h2>Detail Order</h2>
                            <a href="{{ route('orders.index') }}">List Order</a>
                        </div>
                        <div class="report-order-list">
                            <div class="report-order-head">
                                <span>Nota</span><span>Pelanggan</span><span>Total</span><span>Dibayar</span><span>Sisa</span><span>Status</span>
                            </div>
                            @forelse ($orders as $order)
                                @php
                                    $paid = min($order->payments->where('status', 'paid')->sum('amount_paid'), $order->total_amount);
                                    $remaining = max($order->total_amount - $paid, 0);
                                @endphp
                                <a class="report-order-row" href="{{ route('orders.show', $order) }}">
                                    <strong>{{ $order->invoice_number }}</strong>
                                    <span>{{ $order->customer?->name ?? '-' }}</span>
                                    <span>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                                    <span>Rp {{ number_format($paid, 0, ',', '.') }}</span>
                                    <span>Rp {{ number_format($remaining, 0, ',', '.') }}</span>
                                    <b>{{ match($order->payment_status) { 'paid' => 'Lunas', 'partial' => 'Sebagian', default => 'Belum Bayar' } }}</b>
                                </a>
                            @empty
                                <div class="orders-table__empty">
                                    <strong>Belum ada order</strong>
                                    <p>Ubah filter tanggal atau buat order baru.</p>
                                </div>
                            @endforelse
                        </div>
                    </article>
                </section>
            </main>
        </div>
    </body>
</html>

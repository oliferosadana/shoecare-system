<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dashboard Admin - ZOLIX Shoe Care</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="dashboard-body" x-data='shoeCareApp({ orders: @json($recentOrdersForView) })' x-init="$nextTick(refreshIcons)">
        <div class="dashboard-shell">
            @include('partials.admin-sidebar', ['active' => 'dashboard'])

            <main class="dashboard-main dashboard-main--overview">
                <div class="dashboard-desktop-content">
                <header class="dashboard-topbar">
                    <div>
                        <h1>Dashboard</h1>
                        <p>Selamat datang kembali, {{ auth()->user()?->name ?? 'Zolix Admin' }}!</p>
                    </div>

                    @include('partials.admin-top-actions')
                </header>

                <section class="overview-stats">
                    <article class="overview-stat-card">
                        <div class="stat-icon stat-icon--lime"><i data-lucide="clipboard-list"></i></div>
                        <div>
                            <span>Total Order</span>
                            <strong>{{ $stats['total_orders'] }}</strong>
                            <small class="{{ $trends['total_orders']['class'] }}">{{ $trends['total_orders']['text'] }}</small>
                        </div>
                    </article>
                    <article class="overview-stat-card">
                        <div class="stat-icon stat-icon--orange"><i data-lucide="loader-circle"></i></div>
                        <div>
                            <span>Order Proses</span>
                            <strong>{{ $stats['proses'] }}</strong>
                            <small class="{{ $trends['proses']['class'] }}">{{ $trends['proses']['text'] }}</small>
                        </div>
                    </article>
                    <article class="overview-stat-card">
                        <div class="stat-icon stat-icon--green"><i data-lucide="circle-check"></i></div>
                        <div>
                            <span>Order Selesai</span>
                            <strong>{{ $stats['selesai'] }}</strong>
                            <small class="{{ $trends['selesai']['class'] }}">{{ $trends['selesai']['text'] }}</small>
                        </div>
                    </article>
                    <article class="overview-stat-card">
                        <div class="stat-icon stat-icon--gray"><i data-lucide="package-check"></i></div>
                        <div>
                            <span>Order Diambil</span>
                            <strong>{{ $stats['diambil'] }}</strong>
                            <small class="{{ $trends['diambil']['class'] }}">{{ $trends['diambil']['text'] }}</small>
                        </div>
                    </article>
                    <article class="overview-stat-card">
                        <div class="stat-icon stat-icon--red"><i data-lucide="clock-alert"></i></div>
                        <div>
                            <span>Menunggu Diambil</span>
                            <strong>{{ $stats['menunggu_diambil'] }}</strong>
                            <small class="{{ $trends['menunggu_diambil']['class'] }}">{{ $trends['menunggu_diambil']['text'] }}</small>
                        </div>
                    </article>
                    <article class="overview-stat-card overview-stat-card--alert">
                        <div class="stat-icon stat-icon--red"><i data-lucide="alarm-clock"></i></div>
                        <div>
                            <span>Deadline Lewat</span>
                            <strong>{{ $stats['overdue'] }}</strong>
                            <small class="trend-down">Perlu diprioritaskan</small>
                        </div>
                    </article>
                </section>

                <section class="overview-grid">
                    <article class="overview-panel chart-panel">
                        <div class="panel-head">
                            <h2>Grafik Order</h2>
                            <button type="button">7 Hari Terakhir <i data-lucide="chevron-down"></i></button>
                        </div>
                        <div class="line-legend">
                            <span class="legend-lime">Total Order</span>
                            <span class="legend-green">Selesai</span>
                            <span class="legend-orange">Proses</span>
                            <span class="legend-gray">Diambil</span>
                            <span class="legend-red">Menunggu Diambil</span>
                        </div>
                        <div class="line-chart" aria-label="Grafik order 7 hari">
                            <svg viewBox="0 0 760 220" role="img">
                                <g class="chart-grid">
                                    <line x1="0" x2="760" y1="30" y2="30"></line>
                                    <line x1="0" x2="760" y1="80" y2="80"></line>
                                    <line x1="0" x2="760" y1="130" y2="130"></line>
                                    <line x1="0" x2="760" y1="180" y2="180"></line>
                                </g>
                                <polyline class="line line--lime" points="{{ $chart['points']['total'] }}"></polyline>
                                <polyline class="line line--green" points="{{ $chart['points']['selesai'] }}"></polyline>
                                <polyline class="line line--orange" points="{{ $chart['points']['proses'] }}"></polyline>
                                <polyline class="line line--gray" points="{{ $chart['points']['diambil'] }}"></polyline>
                                <polyline class="line line--red" points="{{ $chart['points']['menunggu_diambil'] }}"></polyline>
                            </svg>
                            <div class="chart-days">
                                @foreach ($chart['days'] as $day)
                                    <span>{{ $day }}</span>
                                @endforeach
                            </div>
                        </div>
                    </article>

                    <article class="overview-panel revenue-panel">
                        <div class="panel-head">
                            <h2>Ringkasan Pendapatan</h2>
                            <button type="button">7 Hari Terakhir <i data-lucide="chevron-down"></i></button>
                        </div>
                        <div class="revenue-layout">
                            <div>
                                <span>Omzet Order</span>
                                <strong>Rp {{ number_format($stats['revenue'], 0, ',', '.') }}</strong>
                                <small class="{{ $trends['revenue']['class'] }}">{{ $trends['revenue']['text'] }}</small>
                            </div>
                            <div class="donut-chart" aria-hidden="true"></div>
                            <ul class="revenue-list">
                                <li><span class="dot green"></span>Terbayar <strong>Rp {{ number_format($stats['paid_revenue'], 0, ',', '.') }}</strong></li>
                                <li><span class="dot orange"></span>Piutang <strong>Rp {{ number_format($stats['outstanding_revenue'], 0, ',', '.') }}</strong></li>
                                <li><span class="dot lime"></span>Selesai <strong>Rp {{ number_format($revenueByStatus['selesai'], 0, ',', '.') }}</strong></li>
                                <li><span class="dot red"></span>Proses <strong>Rp {{ number_format($revenueByStatus['proses'], 0, ',', '.') }}</strong></li>
                                <li><span class="dot red"></span>Menunggu Diambil <strong>Rp {{ number_format($revenueByStatus['menunggu_diambil'], 0, ',', '.') }}</strong></li>
                                <li><span class="dot danger"></span>Dibatalkan <strong>Rp {{ number_format($revenueByStatus['dibatalkan'], 0, ',', '.') }}</strong></li>
                            </ul>
                        </div>
                    </article>
                </section>

                <section class="overview-lower-grid">
                    <article class="overview-panel recent-orders-panel">
                        <div class="panel-head">
                            <h2>Order Terbaru</h2>
                            <a href="{{ route('orders.index') }}">Lihat Semua</a>
                        </div>
                        <div class="mini-order-table">
                            <div class="mini-head">
                                <span>No. Nota</span><span></span><span>Pelanggan</span><span>Layanan</span><span>Status</span><span>Total</span>
                            </div>
                            <template x-for="order in orders.slice(0, 5)" :key="order.id">
                                <div class="mini-row">
                                    <div><strong x-text="order.id"></strong><small><span x-text="order.date"></span> · <span x-text="order.time"></span></small></div>
                                    <div class="dashboard-thumb mini-thumb" :class="'thumb--' + order.statusClass.replace('tag--', '')">
                                        <template x-if="order.photoUrl">
                                            <img :src="order.photoUrl" :alt="order.id">
                                        </template>
                                    </div>
                                    <div><strong x-text="order.customer"></strong><small x-text="order.phone"></small></div>
                                    <div><span x-text="order.service"></span><small x-text="order.qty"></small></div>
                                    <div>
                                        <span class="dashboard-status mini-status" :class="order.statusClass" x-text="order.status"></span>
                                        <small class="deadline-pill mini-deadline" :class="{ 'is-overdue': order.isOverdue }" x-text="order.deadlineLabel"></small>
                                    </div>
                                    <strong x-text="order.amount"></strong>
                                </div>
                            </template>
                        </div>
                    </article>

                    <article class="overview-panel popular-panel">
                        <div class="panel-head">
                            <h2>Layanan Terpopuler</h2>
                            <button type="button">7 Hari Terakhir <i data-lucide="chevron-down"></i></button>
                        </div>
                        <ol class="popular-list">
                            @forelse ($popularServices as $service)
                                <li><span>{{ $loop->iteration }}</span><i data-lucide="sparkles"></i><div><strong>{{ $service->name }}</strong><small>{{ $service->order_items_count }} Order</small></div><b>{{ $service->order_items_count }}</b></li>
                            @empty
                                <li><span>1</span><i data-lucide="sparkles"></i><div><strong>Belum ada data</strong><small>0 Order</small></div><b>0</b></li>
                            @endforelse
                        </ol>
                    </article>

                    <aside class="overview-side-stack">
                        <article class="overview-panel status-panel">
                            <h2>Status Order</h2>
                            <ul>
                                <li>Diterima <b class="info">{{ $statusCounts['diterima'] ?? 0 }}</b></li>
                                <li>Proses <b class="warning">{{ $statusCounts['proses'] ?? 0 }}</b></li>
                                <li>Selesai <b class="success">{{ $statusCounts['selesai'] ?? 0 }}</b></li>
                                <li>Diambil <b>{{ $statusCounts['diambil'] ?? 0 }}</b></li>
                                <li>Menunggu Diambil <b class="pending">{{ $statusCounts['menunggu_diambil'] ?? 0 }}</b></li>
                                <li>Dibatalkan <b class="danger">{{ $statusCounts['dibatalkan'] ?? 0 }}</b></li>
                            </ul>
                        </article>
                        <article class="overview-panel new-customer-panel">
                            <div class="panel-head">
                                <h2>Pelanggan Baru</h2>
                                <button type="button">7 Hari Terakhir</button>
                            </div>
                            <strong>{{ $stats['new_customers'] }}</strong>
                            <small class="{{ $trends['new_customers']['class'] }}">{{ $trends['new_customers']['text'] }}</small>
                            <i data-lucide="users-round"></i>
                        </article>
                    </aside>
                </section>

                <footer class="overview-footer">© 2025 Zolix Shoe Care. All rights reserved.</footer>
                </div>

                <section class="mobile-dashboard-screen">
                    <header class="mobile-dashboard-header">
                        <img src="{{ asset('assets/logo-putih.png') }}" alt="ZOLIX Shoe Care">
                        <div class="mobile-dashboard-userbar">
                            <button type="button" aria-label="Notifikasi">
                                <i data-lucide="bell"></i>
                                <b></b>
                            </button>
                            <span></span>
                            <div class="mobile-dashboard-avatar">ZA</div>
                            <div>
                                <strong>{{ auth()->user()?->name ?? 'Zolix Admin' }}</strong>
                                <small>{{ ucfirst(auth()->user()?->role ?? 'Admin') }}</small>
                            </div>
                            <i data-lucide="chevron-down"></i>
                        </div>
                    </header>

                    <div class="mobile-dashboard-greeting">
                        <div>
                            <h1>Halo, {{ auth()->user()?->name ?? 'Zolix Admin' }}! 👋</h1>
                            <p>Semangat bekerja hari ini!</p>
                        </div>
                        <button type="button">
                            <i data-lucide="calendar-days"></i>
                            <span>18 Mei 2025</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                    </div>

                    <section class="mobile-dashboard-stats">
                        <article>
                            <div class="stat-icon stat-icon--lime"><i data-lucide="clipboard-list"></i></div>
                            <div>
                                <span>Total Order</span>
                                <strong>{{ $stats['total_orders'] }}</strong>
                                <small class="{{ $trends['total_orders']['class'] }}">{{ $trends['total_orders']['text'] }}</small>
                            </div>
                        </article>
                        <article>
                            <div class="stat-icon stat-icon--orange"><i data-lucide="loader-circle"></i></div>
                            <div>
                                <span>Proses</span>
                                <strong>{{ $stats['proses'] }}</strong>
                                <small class="{{ $trends['proses']['class'] }}">{{ $trends['proses']['text'] }}</small>
                            </div>
                        </article>
                        <article>
                            <div class="stat-icon stat-icon--green"><i data-lucide="circle-check"></i></div>
                            <div>
                                <span>Selesai</span>
                                <strong>{{ $stats['selesai'] }}</strong>
                                <small class="{{ $trends['selesai']['class'] }}">{{ $trends['selesai']['text'] }}</small>
                            </div>
                        </article>
                        <article>
                            <div class="stat-icon stat-icon--orange"><i data-lucide="clock"></i></div>
                            <div>
                                <span>Menunggu Diambil</span>
                                <strong>{{ $stats['menunggu_diambil'] }}</strong>
                                <small class="{{ $trends['menunggu_diambil']['class'] }}">{{ $trends['menunggu_diambil']['text'] }}</small>
                            </div>
                        </article>
                        <article>
                            <div class="stat-icon stat-icon--gray"><i data-lucide="package-check"></i></div>
                            <div>
                                <span>Diambil</span>
                                <strong>{{ $stats['diambil'] }}</strong>
                                <small class="{{ $trends['diambil']['class'] }}">{{ $trends['diambil']['text'] }}</small>
                            </div>
                        </article>
                        <article>
                            <div class="stat-icon stat-icon--red"><i data-lucide="circle-x"></i></div>
                            <div>
                                <span>Dibatalkan</span>
                                <strong>{{ $statusCounts['dibatalkan'] ?? 0 }}</strong>
                                <small class="trend-down">Perlu evaluasi</small>
                            </div>
                        </article>
                    </section>

                    <article class="mobile-dashboard-panel mobile-chart-panel">
                        <div class="mobile-panel-head">
                            <h2>Grafik Order</h2>
                            <button type="button">7 Hari Terakhir <i data-lucide="chevron-down"></i></button>
                        </div>
                        <div class="line-legend">
                            <span class="legend-lime">Total</span>
                            <span class="legend-green">Selesai</span>
                            <span class="legend-orange">Proses</span>
                            <span class="legend-gray">Diambil</span>
                            <span class="legend-red">Dibatalkan</span>
                        </div>
                        <div class="line-chart" aria-label="Grafik order 7 hari">
                            <svg viewBox="0 0 760 220" role="img">
                                <g class="chart-grid">
                                    <line x1="0" x2="760" y1="30" y2="30"></line>
                                    <line x1="0" x2="760" y1="80" y2="80"></line>
                                    <line x1="0" x2="760" y1="130" y2="130"></line>
                                    <line x1="0" x2="760" y1="180" y2="180"></line>
                                </g>
                                <polyline class="line line--lime" points="{{ $chart['points']['total'] }}"></polyline>
                                <polyline class="line line--green" points="{{ $chart['points']['selesai'] }}"></polyline>
                                <polyline class="line line--orange" points="{{ $chart['points']['proses'] }}"></polyline>
                                <polyline class="line line--gray" points="{{ $chart['points']['diambil'] }}"></polyline>
                                <polyline class="line line--red" points="{{ $chart['points']['menunggu_diambil'] }}"></polyline>
                            </svg>
                            <div class="chart-days">
                                @foreach ($chart['days'] as $day)
                                    <span>{{ $day }}</span>
                                @endforeach
                            </div>
                        </div>
                    </article>

                    <article class="mobile-dashboard-panel mobile-revenue-panel">
                        <div class="mobile-panel-head">
                            <h2>Ringkasan Pendapatan</h2>
                            <button type="button">7 Hari Terakhir <i data-lucide="chevron-down"></i></button>
                        </div>
                        <div class="mobile-revenue-layout">
                            <div>
                                <span>Total Pendapatan</span>
                                <strong>Rp {{ number_format($stats['revenue'], 0, ',', '.') }}</strong>
                                <small class="{{ $trends['revenue']['class'] }}">{{ $trends['revenue']['text'] }}</small>
                            </div>
                            <div class="donut-chart" aria-hidden="true"></div>
                            <ul class="revenue-list">
                                <li><span class="dot lime"></span>Selesai <strong>Rp {{ number_format($revenueByStatus['selesai'], 0, ',', '.') }}</strong></li>
                                <li><span class="dot green"></span>Diambil <strong>Rp {{ number_format($revenueByStatus['diambil'], 0, ',', '.') }}</strong></li>
                                <li><span class="dot orange"></span>Proses <strong>Rp {{ number_format($revenueByStatus['proses'], 0, ',', '.') }}</strong></li>
                                <li><span class="dot red"></span>Menunggu Diambil <strong>Rp {{ number_format($revenueByStatus['menunggu_diambil'], 0, ',', '.') }}</strong></li>
                                <li><span class="dot danger"></span>Dibatalkan <strong>Rp {{ number_format($revenueByStatus['dibatalkan'], 0, ',', '.') }}</strong></li>
                            </ul>
                        </div>
                    </article>

                    <article class="mobile-dashboard-panel mobile-latest-panel">
                        <div class="mobile-panel-head">
                            <h2>Order Terbaru</h2>
                            <a href="{{ route('orders.index') }}">Lihat Semua</a>
                        </div>
                        <div class="mobile-latest-list">
                            <template x-for="order in orders.slice(0, 3)" :key="'dashboard-mobile-' + order.id">
                                <a class="mobile-latest-row" :href="order.showUrl">
                                    <div class="dashboard-thumb mini-thumb" :class="'thumb--' + order.statusClass.replace('tag--', '')">
                                        <template x-if="order.photoUrl">
                                            <img :src="order.photoUrl" :alt="order.itemName">
                                        </template>
                                    </div>
                                    <div>
                                        <small x-text="order.id"></small>
                                        <strong x-text="order.itemName"></strong>
                                        <span><i data-lucide="message-circle"></i><b x-text="order.customer"></b></span>
                                    </div>
                                    <span class="dashboard-status mini-status" :class="order.statusClass" x-text="order.status"></span>
                                    <time><span x-text="order.date"></span><br><span x-text="order.time"></span></time>
                                    <b x-text="order.amount"></b>
                                    <i data-lucide="chevron-right"></i>
                                </a>
                            </template>
                        </div>
                    </article>
                </section>
            </main>
        </div>
    </body>
</html>

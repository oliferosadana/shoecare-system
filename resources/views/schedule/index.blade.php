<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Jadwal Pengerjaan - ZOLIX Shoe Care</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="dashboard-body" x-data="shoeCareApp()" x-init="$nextTick(refreshIcons)">
        <div class="dashboard-shell">
            @include('partials.admin-sidebar', ['active' => 'schedule'])

            <main class="dashboard-main">
                <header class="orders-page-header">
                    <div>
                        <h1>Jadwal Pengerjaan</h1>
                        <p>Pantau estimasi selesai dan order yang sudah melewati deadline.</p>
                    </div>
                    <a class="dashboard-add" href="{{ route('orders.create') }}"><i data-lucide="circle-plus"></i><span>Tambah Order</span></a>
                </header>

                <form class="report-filter-card" method="GET" action="{{ route('schedule.index') }}">
                    <label>
                        <span>Tanggal Jadwal</span>
                        <input type="date" name="date" value="{{ $date->format('Y-m-d') }}">
                    </label>
                    <button type="submit"><i data-lucide="filter"></i><span>Tampilkan</span></button>
                </form>

                <section class="customer-stat-grid">
                    <article><span>Jadwal Tanggal Ini</span><strong>{{ $orders->count() }}</strong><small>{{ $date->format('d M Y') }}</small></article>
                    <article><span>Terlambat</span><strong>{{ $overdueOrders->count() }}</strong><small>Belum diambil/batal</small></article>
                    <article><span>Akan Datang</span><strong>{{ $upcomingOrders->count() }}</strong><small>8 order terdekat</small></article>
                </section>

                <section class="schedule-grid">
                    <article class="overview-panel">
                        <div class="panel-head">
                            <h2>Deadline {{ $date->format('d M Y') }}</h2>
                            <a href="{{ route('orders.index') }}">List Order</a>
                        </div>
                        <div class="schedule-list">
                            @forelse ($orders as $order)
                                @include('schedule.partials.order-card', ['order' => $order])
                            @empty
                                <div class="orders-table__empty"><strong>Tidak ada jadwal</strong><p>Belum ada order dengan estimasi tanggal ini.</p></div>
                            @endforelse
                        </div>
                    </article>

                    <aside class="overview-side-stack">
                        <article class="overview-panel">
                            <div class="panel-head"><h2>Terlambat</h2></div>
                            <div class="schedule-list schedule-list--compact">
                                @forelse ($overdueOrders as $order)
                                    @include('schedule.partials.order-card', ['order' => $order, 'compact' => true])
                                @empty
                                    <p class="schedule-empty">Tidak ada order terlambat.</p>
                                @endforelse
                            </div>
                        </article>
                        <article class="overview-panel">
                            <div class="panel-head"><h2>Akan Datang</h2></div>
                            <div class="schedule-list schedule-list--compact">
                                @forelse ($upcomingOrders as $order)
                                    @include('schedule.partials.order-card', ['order' => $order, 'compact' => true])
                                @empty
                                    <p class="schedule-empty">Belum ada jadwal berikutnya.</p>
                                @endforelse
                            </div>
                        </article>
                    </aside>
                </section>
            </main>
        </div>
    </body>
</html>

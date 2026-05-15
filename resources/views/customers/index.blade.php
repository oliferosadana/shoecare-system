<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pelanggan - ZOLIX Shoe Care</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="dashboard-body" x-data="shoeCareApp()" x-init="$nextTick(refreshIcons)">
        <div class="dashboard-shell">
            @include('partials.admin-sidebar', ['active' => 'customers'])

            <main class="dashboard-main">
                <header class="orders-page-header">
                    <div>
                        <h1>Pelanggan</h1>
                        <p>Kelola data pelanggan dan riwayat ordernya.</p>
                    </div>
                    <a class="dashboard-add" href="{{ route('orders.create') }}">
                        <i data-lucide="circle-plus"></i>
                        <span>Tambah Order</span>
                    </a>
                </header>

                @if (session('success'))
                    <div class="dashboard-flash"><strong>Berhasil</strong><span>{{ session('success') }}</span></div>
                @endif

                <section class="customer-stat-grid">
                    <article><span>Total Pelanggan</span><strong>{{ $totalCustomers }}</strong><small>Data tersimpan</small></article>
                    <article><span>Pelanggan Repeat</span><strong>{{ $repeatCustomers }}</strong><small>Minimal 2 order</small></article>
                    <article><span>Hasil Filter</span><strong>{{ $customers->total() }}</strong><small>Pelanggan ditemukan</small></article>
                </section>

                <form class="dashboard-tools customer-tools" method="GET" action="{{ route('customers.index') }}">
                    <label class="dashboard-search">
                        <i data-lucide="search"></i>
                        <input name="q" value="{{ request('q') }}" type="search" placeholder="Cari nama, WhatsApp, email, alamat...">
                    </label>
                    <button class="dashboard-add" type="submit">
                        <i data-lucide="filter"></i>
                        <span>Cari</span>
                    </button>
                </form>

                <section class="orders-table-panel customer-list">
                    <div class="customer-list-head">
                        <span>Pelanggan</span><span>Kontak</span><span>Alamat</span><span>Order</span><span>Total Belanja</span><span>Aksi</span>
                    </div>
                    @forelse ($customers as $customer)
                        <article>
                            <div class="customer-avatar-cell">
                                <b>{{ strtoupper(substr($customer->name, 0, 1)) }}</b>
                                <div><strong>{{ $customer->name }}</strong><small>Bergabung {{ $customer->created_at->format('d M Y') }}</small></div>
                            </div>
                            <div><strong>{{ $customer->phone }}</strong><small>{{ $customer->email ?: 'Email belum diisi' }}</small></div>
                            <span>{{ $customer->address ?: '-' }}</span>
                            <b>{{ $customer->orders_count }} order</b>
                            <strong>Rp {{ number_format($customer->orders_sum_total_amount ?? 0, 0, ',', '.') }}</strong>
                            <div class="action-cell">
                                <a href="{{ route('customers.show', $customer) }}" title="Lihat detail"><i data-lucide="eye"></i></a>
                                <a href="{{ route('customers.edit', $customer) }}" title="Edit pelanggan"><i data-lucide="pencil"></i></a>
                            </div>
                        </article>
                    @empty
                        <div class="orders-table__empty">
                            <strong>Pelanggan tidak ditemukan</strong>
                            <p>Coba ubah kata kunci pencarian.</p>
                        </div>
                    @endforelse
                </section>

                <footer class="dashboard-pagination">
                    <p>Menampilkan {{ $customers->firstItem() ?? 0 }} - {{ $customers->lastItem() ?? 0 }} dari {{ $customers->total() }} pelanggan</p>
                    <div></div>
                    <div class="dashboard-pages">{{ $customers->links() }}</div>
                </footer>
            </main>
        </div>
    </body>
</html>

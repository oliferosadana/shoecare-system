<div wire:poll.30s>
    <div class="orders-desktop-content">
    <form class="dashboard-tools orders-tools" wire:submit.prevent>
        <label class="dashboard-search">
            <i data-lucide="search"></i>
            <input wire:model.live.debounce.400ms="q" type="search" placeholder="Cari no. nota, nama, no. HP...">
        </label>
        <label class="dashboard-filter">
            <i data-lucide="funnel"></i>
            <select wire:model.live="status">
                <option value="">Semua</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <a class="dashboard-add" href="{{ route('orders.create') }}">
            <i data-lucide="circle-plus"></i>
            <span>Tambah Order</span>
        </a>
    </form>

    <section class="dashboard-tabs">
        <button class="dashboard-tab {{ $status === '' && $paymentStatus === '' && $deadline === '' && $quick === '' ? 'is-active' : '' }}" type="button" wire:click="setStatus('')">
            <span>Semua</span>
            <small>{{ array_sum($statusCounts->all()) }}</small>
        </button>
        @foreach ($statusOptions as $value => $label)
            <button class="dashboard-tab {{ $status === $value ? 'is-active' : '' }}" type="button" wire:click="setStatus('{{ $value }}')">
                <span>{{ $label }}</span>
                <small>{{ $statusCounts[$label] ?? 0 }}</small>
            </button>
        @endforeach
    </section>

    <section class="quick-filter-bar">
        <button class="{{ $paymentStatus === 'unpaid' ? 'is-active' : '' }}" type="button" wire:click="setPaymentStatus('unpaid')">
            <i data-lucide="wallet"></i>
            <span>Belum Bayar</span>
            <b>{{ $quickFilterCounts['unpaid'] }}</b>
        </button>
        <button class="{{ $paymentStatus === 'partial' ? 'is-active' : '' }}" type="button" wire:click="setPaymentStatus('partial')">
            <i data-lucide="badge-dollar-sign"></i>
            <span>Bayar Sebagian</span>
            <b>{{ $quickFilterCounts['partial'] }}</b>
        </button>
        <button class="{{ $deadline === 'overdue' ? 'is-active' : '' }}" type="button" wire:click="setDeadline('overdue')">
            <i data-lucide="alarm-clock"></i>
            <span>Deadline Lewat</span>
            <b>{{ $quickFilterCounts['overdue'] }}</b>
        </button>
        <button class="{{ $quick === 'ready_pickup' ? 'is-active' : '' }}" type="button" wire:click="setQuick('ready_pickup')">
            <i data-lucide="package-check"></i>
            <span>Siap Diambil</span>
            <b>{{ $quickFilterCounts['ready_pickup'] }}</b>
        </button>
        @if ($paymentStatus !== '' || $deadline !== '' || $quick !== '')
            <button class="quick-filter-reset" type="button" wire:click="resetFilters">
                <i data-lucide="x"></i>
                <span>Reset Filter</span>
            </button>
        @endif
    </section>

    <section class="orders-table-panel">
        <div class="orders-table">
            <div class="orders-table__head">
                <span>No. Nota</span>
                <span></span>
                <span>Pelanggan</span>
                <span>Layanan</span>
                <span>Tanggal Order</span>
                <span>Estimasi Selesai</span>
                <span>Status</span>
                <span>Total</span>
                <span>Aksi</span>
            </div>

            @forelse ($ordersForView as $order)
                <article class="orders-table__row" wire:key="desktop-{{ $order['id'] }}">
                    <div class="invoice-cell">
                        <strong>{{ $order['id'] }}</strong>
                        <span>{{ $order['date'] }} &middot; {{ $order['time'] }}</span>
                    </div>
                    <div class="dashboard-thumb thumb--{{ str_replace('tag--', '', $order['statusClass']) }}" aria-hidden="true">
                        @if ($order['photoUrl'])
                            <img src="{{ $order['photoUrl'] }}" alt="{{ $order['itemName'] }}">
                        @endif
                    </div>
                    <div class="customer-cell">
                        <strong>{{ $order['customer'] }}</strong>
                        <span><i data-lucide="phone"></i><span>{{ $order['phone'] }}</span></span>
                    </div>
                    <div class="service-cell">
                        <span><i data-lucide="sparkles"></i><span>{{ $order['service'] }}</span></span>
                        <small>{{ $order['qty'] }}</small>
                    </div>
                    <div class="date-cell">
                        <strong>{{ $order['date'] }}</strong>
                        <span>{{ $order['time'] }} WIB</span>
                    </div>
                    <div class="date-cell">
                        <strong>{{ $order['estimate'] }}</strong>
                        <span>{{ $order['estimateTime'] }} WIB</span>
                        <small class="deadline-pill {{ $order['isOverdue'] ? 'is-overdue' : '' }}">{{ $order['deadlineLabel'] }}</small>
                    </div>
                    <div><span class="dashboard-status {{ $order['statusClass'] }}">{{ $order['status'] }}</span></div>
                    <strong class="dashboard-total">{{ $order['amount'] }}</strong>
                    <div class="action-cell">
                        <a href="{{ $order['showUrl'] }}" title="Lihat detail"><i data-lucide="eye"></i></a>
                    </div>
                </article>
            @empty
                <div class="orders-table__empty">
                    <strong>Order tidak ditemukan</strong>
                    <p>Coba ubah kata kunci pencarian atau pilih tab status lain.</p>
                </div>
            @endforelse
        </div>
    </section>

    <footer class="dashboard-pagination">
        <p>Menampilkan {{ $orders->firstItem() ?? 0 }} - {{ $orders->lastItem() ?? 0 }} dari {{ $orders->total() }} order</p>
        <div class="page-size"><span>{{ $orders->perPage() }} / halaman</span><i data-lucide="chevron-down"></i></div>
        <div class="dashboard-pages">{{ $orders->links() }}</div>
    </footer>
    </div>

    <section class="mobile-order-screen">
        <header class="mobile-order-top">
            <img src="{{ asset('assets/logo-putih.png') }}" alt="ZOLIX Shoe Care">
            <div class="mobile-top-actions">
                <a href="{{ route('orders.create') }}" aria-label="Tambah Order">
                    <i data-lucide="plus"></i>
                    <span>Tambah Order</span>
                </a>
            </div>
        </header>

        <div class="mobile-order-title">
            <h1>List Order</h1>
            <p>Kelola semua order dengan mudah.</p>
        </div>

        <div class="mobile-order-search">
            <label>
                <i data-lucide="search"></i>
                <input wire:model.live.debounce.400ms="q" type="search" placeholder="Cari nama, no. nota, atau no. hp...">
            </label>
            <button type="button">
                <i data-lucide="list-filter"></i>
                <span>Filter</span>
            </button>
        </div>

        <nav class="mobile-order-tabs" aria-label="Filter status order">
            <button class="{{ $status === '' && $paymentStatus === '' && $deadline === '' && $quick === '' ? 'is-active' : '' }}" type="button" wire:click="setStatus('')">Semua</button>
            @foreach ($statusOptions as $value => $label)
                <button class="{{ $status === $value ? 'is-active' : '' }}" type="button" wire:click="setStatus('{{ $value }}')">{{ $label }}</button>
            @endforeach
        </nav>

        <div class="mobile-order-list">
            @forelse ($ordersForView as $order)
                <a class="mobile-reference-card" href="{{ $order['showUrl'] }}" wire:key="mobile-{{ $order['id'] }}">
                    <div class="mobile-reference-thumb thumb--{{ str_replace('tag--', '', $order['statusClass']) }}">
                        @if ($order['photoUrl'])
                            <img src="{{ $order['photoUrl'] }}" alt="{{ $order['itemName'] }}">
                        @endif
                    </div>
                    <div class="mobile-reference-main">
                        <div class="mobile-reference-heading">
                            <h2>{{ $order['id'] }}</h2>
                            <i data-lucide="copy"></i>
                        </div>
                        <strong>{{ $order['customer'] }}</strong>
                        <p><i data-lucide="phone"></i><span>{{ $order['phone'] }}</span></p>
                        <div class="mobile-reference-meta">
                            <span><i data-lucide="washing-machine"></i><b>{{ $order['service'] }}</b></span>
                            <span><i data-lucide="calendar-days"></i><b>{{ $order['date'] }} &middot; {{ $order['time'] }}</b></span>
                        </div>
                    </div>
                    <div class="mobile-reference-side">
                        <div>
                            <span class="dashboard-status {{ $order['statusClass'] }}">{{ $order['status'] }}</span>
                            <strong>{{ $order['amount'] }}</strong>
                        </div>
                        <i data-lucide="chevron-right"></i>
                    </div>
                </a>
            @empty
                <div class="mobile-empty">
                    <strong>Order tidak ditemukan</strong>
                    <p>Coba ubah pencarian atau filter status.</p>
                </div>
            @endforelse
        </div>

        <footer class="mobile-order-pagination">
            <p>Menampilkan {{ $orders->firstItem() ?? 0 }} - {{ $orders->lastItem() ?? 0 }} dari {{ $orders->total() }} order</p>
            <div class="mobile-order-pages">{{ $orders->links() }}</div>
        </footer>
    </section>
</div>

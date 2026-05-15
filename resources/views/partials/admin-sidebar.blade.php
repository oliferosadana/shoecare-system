@php
    $sidebarUser = auth()->user();
    $sidebarName = $sidebarUser?->name ?? 'Zolix Admin';
    $sidebarRole = $sidebarUser?->role ?? 'administrator';
@endphp

<button
    class="admin-mobile-menu-button"
    type="button"
    aria-label="Buka menu navigasi"
    x-data
    @click="$store.adminNav.toggle()"
>
    <i data-lucide="menu"></i>
</button>

<aside
    class="dashboard-sidebar"
    x-data
    :class="{ 'is-mobile-open': $store.adminNav.open }"
>
    <button
        class="admin-sidebar-close"
        type="button"
        aria-label="Tutup menu navigasi"
        @click="$store.adminNav.close()"
    >
        <i data-lucide="x"></i>
    </button>

    <a class="sidebar-logo sidebar-logo--dark" href="{{ route('dashboard') }}">
        <img src="{{ asset('assets/logo-putih.png') }}" alt="ZOLIX Shoe Care">
    </a>

    <nav class="sidebar-nav" aria-label="Navigasi dashboard" @click="$store.adminNav.close()">
        <a href="{{ route('dashboard') }}" class="sidebar-link @if ($active === 'dashboard') is-active @endif">
            <i data-lucide="home"></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('orders.index') }}" class="sidebar-link @if ($active === 'orders') is-active @endif">
            <i data-lucide="receipt-text"></i>
            <span>List Order</span>
        </a>
        <a href="{{ route('orders.create') }}" class="sidebar-link @if ($active === 'orders-create') is-active @endif">
            <i data-lucide="circle-plus"></i>
            <span>Tambah Order</span>
        </a>
        <a href="{{ route('payments.pending') }}" class="sidebar-link @if ($active === 'payments') is-active @endif">
            <i data-lucide="wallet-cards"></i>
            <span>Pembayaran</span>
        </a>
        <a href="{{ route('schedule.index') }}" class="sidebar-link @if ($active === 'schedule') is-active @endif">
            <i data-lucide="calendar-days"></i>
            <span>Jadwal</span>
        </a>
        <a href="{{ route('customers.index') }}" class="sidebar-link @if ($active === 'customers') is-active @endif">
            <i data-lucide="users"></i>
            <span>Pelanggan</span>
        </a>
        @if (in_array($sidebarRole, ['admin', 'kasir'], true))
            <a href="{{ route('services.index') }}" class="sidebar-link @if ($active === 'services') is-active @endif">
                <i data-lucide="tag"></i>
                <span>Layanan</span>
            </a>
            <a href="{{ route('reports.revenue') }}" class="sidebar-link @if ($active === 'reports') is-active @endif">
                <i data-lucide="chart-column"></i>
                <span>Laporan</span>
            </a>
        @endif
        <a href="#" class="sidebar-link">
            <i data-lucide="settings"></i>
            <span>Pengaturan</span>
        </a>
    </nav>
<!-- 
    <div class="sidebar-promo">
        <strong>Bersih Maksimal,</strong>
        <span>Tampil Optimal!</span>
        <p>Percayakan sepatu Anda pada ahlinya.</p>
        <div class="promo-shoe" aria-hidden="true"></div>
    </div> -->

    <div class="sidebar-profile">
        <div class="profile-avatar">
            <span>{{ strtoupper(substr($sidebarName, 0, 1)) }}</span>
        </div>
        <div>
            <strong>{{ $sidebarName }}</strong>
            <span>{{ ucfirst($sidebarRole) }}</span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="profile-menu" type="submit" title="Logout">
                <i data-lucide="chevron-down"></i>
            </button>
        </form>
    </div>
</aside>

<button
    class="admin-mobile-sidebar-overlay"
    x-data
    :class="{ 'is-visible': $store.adminNav.open }"
    type="button"
    aria-label="Tutup menu navigasi"
    @click="$store.adminNav.close()"
></button>

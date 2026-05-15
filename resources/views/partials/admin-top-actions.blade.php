<div class="topbar-actions">
    <label class="topbar-search">
        <i data-lucide="search"></i>
        <input x-model="search" @input="refreshIcons()" type="search" placeholder="Cari no. nota, pelanggan, atau layanan...">
    </label>

    <button class="notification-button" type="button" title="Notifikasi">
        <i data-lucide="bell"></i>
        <span></span>
    </button>

    <button class="date-picker-button" type="button">
        <i data-lucide="calendar-days"></i>
        <span>
            <strong>18 Mei 2025 - 24 Mei 2025</strong>
            <small>7 Hari Terakhir</small>
        </span>
        <i data-lucide="chevron-down"></i>
    </button>
</div>

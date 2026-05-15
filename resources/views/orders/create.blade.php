<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Tambah Order - ZOLIX Shoe Care</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="dashboard-body create-order-page" x-data="shoeCareApp()" x-init="$nextTick(refreshIcons)">
        <div class="dashboard-shell">
            @include('partials.admin-sidebar', ['active' => 'orders-create', 'logoVariant' => 'dark'])

            <main class="dashboard-main create-order-main-screen">
                <header class="create-order-topbar">
                    <div>
                        <h1>Tambah Order</h1>
                        <nav class="create-breadcrumb" aria-label="Breadcrumb">
                            <a href="{{ route('dashboard') }}">Home</a>
                            <i data-lucide="chevron-right"></i>
                            <a href="{{ route('orders.index') }}">Order</a>
                            <i data-lucide="chevron-right"></i>
                            <span>Tambah Order</span>
                        </nav>
                    </div>

                    <div class="create-top-actions">
                        <button class="create-whatsapp-button" type="button">
                            <i data-lucide="message-circle"></i>
                            <span>Kirim via WhatsApp</span>
                        </button>
                        <button class="notification-button" type="button" title="Notifikasi">
                            <i data-lucide="bell"></i>
                            <span></span>
                        </button>
                        <button class="date-picker-button date-picker-button--compact" type="button">
                            <i data-lucide="calendar-days"></i>
                            <span>
                                <strong>Zolix Shoe Care</strong>
                                <small>Balikpapan</small>
                            </span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                    </div>
                </header>

                <livewire:orders.create-order-form />
            </main>
        </div>

        @livewireScripts
    </body>
</html>

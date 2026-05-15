<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>List Order - ZOLIX Shoe Care</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="dashboard-body">
        <div class="dashboard-shell">
            @include('partials.admin-sidebar', ['active' => 'orders'])

            <main class="dashboard-main">
                <div class="orders-desktop-content">
                    <header class="orders-page-header">
                        <div>
                            <h1>List Order</h1>
                            <p>Kelola semua order dengan mudah dan cepat.</p>
                        </div>

                        <div class="orders-header-actions">
                            <button class="notification-button" type="button" title="Notifikasi">
                                <i data-lucide="bell"></i>
                                <span></span>
                            </button>
                            <button class="date-picker-button date-picker-button--compact" type="button">
                                <i data-lucide="calendar-days"></i>
                                <span>
                                    <strong>{{ now()->translatedFormat('d M Y') }}</strong>
                                    <small>{{ now()->format('H:i') }} WITA</small>
                                </span>
                                <i data-lucide="chevron-down"></i>
                            </button>
                        </div>
                    </header>

                    @if (session('success'))
                        <div class="dashboard-flash">
                            <strong>Berhasil</strong>
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif
                </div>

                <livewire:orders.order-list />
            </main>
        </div>

        @livewireScripts
    </body>
</html>

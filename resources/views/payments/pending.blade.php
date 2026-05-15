<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pembayaran Pending - ZOLIX Shoe Care</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="dashboard-body" x-data="shoeCareApp()" x-init="$nextTick(refreshIcons)">
        <div class="dashboard-shell">
            @include('partials.admin-sidebar', ['active' => 'payments'])

            <main class="dashboard-main">
                <header class="orders-page-header">
                    <div>
                        <h1>Pembayaran Pending</h1>
                        <p>Pantau QRIS, cash outlet, dan transfer manual yang menunggu penyelesaian.</p>
                    </div>

                    <div class="orders-header-actions">
                        <a class="dashboard-add" href="{{ route('orders.index') }}">
                            <i data-lucide="receipt-text"></i>
                            <span>List Order</span>
                        </a>
                    </div>
                </header>

                @if (session('success'))
                    <div class="dashboard-flash">
                        <strong>Berhasil</strong>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if (isset($errors) && $errors->any())
                    <div class="dashboard-flash dashboard-flash--error">
                        <strong>Gagal</strong>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <livewire:payments.pending-payments />
            </main>
        </div>

        @livewireScripts
    </body>
</html>

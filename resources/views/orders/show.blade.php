<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $order->invoice_number }} - ZOLIX Shoe Care</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="dashboard-body" x-data="shoeCareApp()" x-init="$nextTick(refreshIcons)">
        <div class="dashboard-shell">
            @include('partials.admin-sidebar', ['active' => 'orders'])

            <main class="dashboard-main">
                <header class="orders-page-header">
                    <div>
                        <h1>Detail Order</h1>
                        <p>{{ $order->invoice_number }} · {{ $order->customer->name }}</p>
                    </div>

                    <div class="orders-header-actions">
                        <a class="create-back-link" href="{{ route('orders.index') }}">
                            <i data-lucide="arrow-left"></i>
                            <span>Kembali</span>
                        </a>
                        <a class="dashboard-add" href="{{ route('orders.create') }}">
                            <i data-lucide="circle-plus"></i>
                            <span>Tambah Order</span>
                        </a>
                        <a class="create-back-link" href="{{ route('orders.edit', $order) }}">
                            <i data-lucide="pencil"></i>
                            <span>Edit</span>
                        </a>
                    </div>
                </header>

                @if (session('success'))
                    <div class="dashboard-flash">
                        <strong>Berhasil</strong>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                <livewire:orders.order-detail :order="$order" />
            </main>
        </div>

        @livewireScripts
    </body>
</html>

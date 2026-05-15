<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Layanan - ZOLIX</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="dashboard-body" x-data="shoeCareApp()" x-init="$nextTick(refreshIcons)">
        <div class="dashboard-shell">
            @include('partials.admin-sidebar', ['active' => 'services'])
            <main class="dashboard-main">
                <header class="orders-page-header">
                    <div><h1>Layanan</h1><p>Kelola harga dan estimasi layanan.</p></div>
                    <a class="dashboard-add" href="{{ route('services.create') }}"><i data-lucide="circle-plus"></i><span>Tambah Layanan</span></a>
                </header>
                @if (session('success'))<div class="dashboard-flash"><strong>Berhasil</strong><span>{{ session('success') }}</span></div>@endif
                <section class="orders-table-panel service-admin-list">
                    @foreach ($services as $service)
                        <article>
                            <div><strong>{{ $service->name }}</strong><span>{{ $service->description }}</span></div>
                            <b>Rp {{ number_format($service->price, 0, ',', '.') }}</b>
                            <span>{{ $service->estimated_hours }} jam</span>
                            <span class="dashboard-status {{ $service->is_active ? 'tag--selesai' : 'tag--dibatalkan' }}">{{ $service->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            <a href="{{ route('services.edit', $service) }}">Edit</a>
                            <form method="POST" action="{{ route('services.destroy', $service) }}">@csrf @method('DELETE')<button type="submit">Nonaktifkan</button></form>
                        </article>
                    @endforeach
                </section>
            </main>
        </div>
    </body>
</html>

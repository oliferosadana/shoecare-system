<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $service->exists ? 'Edit' : 'Tambah' }} Layanan</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="dashboard-body" x-data="shoeCareApp()" x-init="$nextTick(refreshIcons)">
        <div class="dashboard-shell">
            @include('partials.admin-sidebar', ['active' => 'services'])
            <main class="dashboard-main">
                <header class="orders-page-header">
                    <div><h1>{{ $service->exists ? 'Edit' : 'Tambah' }} Layanan</h1><p>Atur nama, harga, dan estimasi.</p></div>
                    <a class="create-back-link" href="{{ route('services.index') }}"><i data-lucide="arrow-left"></i><span>Kembali</span></a>
                </header>
                @if (isset($errors) && $errors->any())<div class="dashboard-flash dashboard-flash--error"><strong>Gagal</strong><span>{{ $errors->first() }}</span></div>@endif
                <form class="order-detail-card edit-order-form" method="POST" action="{{ $service->exists ? route('services.update', $service) : route('services.store') }}">
                    @csrf
                    @if ($service->exists) @method('PUT') @endif
                    <label><span>Nama</span><input name="name" value="{{ old('name', $service->name) }}" required></label>
                    <label><span>Harga</span><input type="number" name="price" value="{{ old('price', $service->price) }}" required></label>
                    <label><span>Estimasi Jam</span><input type="number" name="estimated_hours" value="{{ old('estimated_hours', $service->estimated_hours ?: 24) }}" required></label>
                    <label><span>Aktif</span><select name="is_active"><option value="1" @selected($service->is_active)>Aktif</option><option value="0" @selected(!$service->is_active)>Nonaktif</option></select></label>
                    <label class="full"><span>Deskripsi</span><textarea name="description">{{ old('description', $service->description) }}</textarea></label>
                    <button class="create-order-button" type="submit">Simpan Layanan</button>
                </form>
            </main>
        </div>
    </body>
</html>

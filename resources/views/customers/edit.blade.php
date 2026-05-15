<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Edit {{ $customer->name }} - ZOLIX Shoe Care</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="dashboard-body" x-data="shoeCareApp()" x-init="$nextTick(refreshIcons)">
        <div class="dashboard-shell">
            @include('partials.admin-sidebar', ['active' => 'customers'])

            <main class="dashboard-main">
                <header class="orders-page-header">
                    <div><h1>Edit Pelanggan</h1><p>{{ $customer->name }}</p></div>
                    <a class="create-back-link" href="{{ route('customers.show', $customer) }}"><i data-lucide="arrow-left"></i><span>Kembali</span></a>
                </header>

                @if (isset($errors) && $errors->any())
                    <div class="dashboard-flash dashboard-flash--error"><strong>Gagal</strong><span>{{ $errors->first() }}</span></div>
                @endif

                <form class="order-detail-card edit-order-form" method="POST" action="{{ route('customers.update', $customer) }}">
                    @csrf
                    @method('PUT')
                    <label><span>Nama Pelanggan</span><input name="name" value="{{ old('name', $customer->name) }}" required></label>
                    <label><span>No. WhatsApp</span><input name="phone" value="{{ old('phone', $customer->phone) }}" required></label>
                    <label><span>Email</span><input name="email" type="email" value="{{ old('email', $customer->email) }}"></label>
                    <label class="full"><span>Alamat</span><input name="address" value="{{ old('address', $customer->address) }}"></label>
                    <label class="full"><span>Catatan</span><textarea name="notes">{{ old('notes', $customer->notes) }}</textarea></label>
                    <button class="create-order-button" type="submit">Simpan Pelanggan</button>
                </form>
            </main>
        </div>
    </body>
</html>

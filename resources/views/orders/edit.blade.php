<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Edit {{ $order->invoice_number }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="dashboard-body" x-data="shoeCareApp()" x-init="$nextTick(refreshIcons)">
        <div class="dashboard-shell">
            @include('partials.admin-sidebar', ['active' => 'orders'])
            <main class="dashboard-main">
                <header class="orders-page-header">
                    <div><h1>Edit Order</h1><p>{{ $order->invoice_number }}</p></div>
                    <a class="create-back-link" href="{{ route('orders.show', $order) }}"><i data-lucide="arrow-left"></i><span>Kembali</span></a>
                </header>

                @if (isset($errors) && $errors->any())
                    <div class="dashboard-flash dashboard-flash--error"><strong>Gagal</strong><span>{{ $errors->first() }}</span></div>
                @endif

                <form class="order-detail-card edit-order-form" method="POST" action="{{ route('orders.update', $order) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <label><span>Nama Pelanggan</span><input name="customer_name" value="{{ old('customer_name', $order->customer->name) }}" required></label>
                    <label><span>No. WhatsApp</span><input name="phone" value="{{ old('phone', $order->customer->phone) }}" required></label>
                    <label class="full"><span>Alamat</span><input name="address" value="{{ old('address', $order->customer->address) }}"></label>
                    <label><span>Diskon</span><input name="discount_amount" value="{{ old('discount_amount', $order->discount_amount) }}"></label>
                    <label>
                        <span>Jenis Pickup / Delivery</span>
                        <select name="pickup_delivery_type">
                            <option value="none" @selected(old('pickup_delivery_type', $order->pickup_delivery_type) === 'none')>Tanpa Pickup/Delivery</option>
                            <option value="pickup" @selected(old('pickup_delivery_type', $order->pickup_delivery_type) === 'pickup')>Pickup</option>
                            <option value="delivery" @selected(old('pickup_delivery_type', $order->pickup_delivery_type) === 'delivery')>Delivery</option>
                            <option value="pickup_delivery" @selected(old('pickup_delivery_type', $order->pickup_delivery_type) === 'pickup_delivery')>Pickup + Delivery</option>
                        </select>
                    </label>
                    <label><span>Ongkos Pickup / Delivery</span><input name="pickup_delivery_fee" value="{{ old('pickup_delivery_fee', $order->pickup_delivery_fee) }}"></label>
                    <label><span>Estimasi Tanggal Selesai</span><input name="estimated_date" type="date" value="{{ old('estimated_date', $order->estimated_finished_at?->format('Y-m-d')) }}"></label>
                    <label><span>Estimasi Jam Selesai</span><input name="estimated_time" type="time" value="{{ old('estimated_time', $order->estimated_finished_at?->format('H:i')) }}"></label>
                    <label class="full"><span>Catatan</span><textarea name="notes">{{ old('notes', $order->notes) }}</textarea></label>

                    <section class="full edit-items-panel">
                        <div class="panel-head">
                            <h2>Item Sepatu</h2>
                            <span>Ubah layanan, qty, harga, dan foto before.</span>
                        </div>

                        <div class="create-item-table">
                            <div class="create-item-head">
                                <span>No</span>
                                <span>Foto</span>
                                <span>Item</span>
                                <span>Layanan</span>
                                <span>Qty</span>
                                <span>Harga</span>
                                <span>Total</span>
                            </div>
                            @foreach ($order->items as $index => $item)
                                @php
                                    $oldItem = old("items.$index", []);
                                    $unitPrice = $oldItem['unit_price'] ?? $item->unit_price;
                                    $quantity = $oldItem['quantity'] ?? $item->quantity;
                                @endphp
                                <div class="create-item-row">
                                    <div class="drag-handle">
                                        <i data-lucide="grip-vertical"></i>
                                        <span>{{ $loop->iteration }}</span>
                                        <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                    </div>
                                    <label class="item-photo-upload">
                                        @if ($item->before_photo_path)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($item->before_photo_path) }}" alt="{{ $item->item_name }}">
                                        @else
                                            <i data-lucide="camera"></i>
                                        @endif
                                        <span>Ganti Foto</span>
                                                    <input name="items[{{ $index }}][before_photo]" type="file" accept="image/*">
                                    </label>
                                    <div class="item-name-cell">
                                        <input class="item-name-input" name="items[{{ $index }}][item_name]" value="{{ $oldItem['item_name'] ?? $item->item_name }}" required>
                                        <div class="item-inline-fields">
                                            <input name="items[{{ $index }}][notes]" value="{{ $oldItem['notes'] ?? $item->notes }}" placeholder="Catatan item">
                                            <input name="items[{{ $index }}][size]" value="{{ $oldItem['size'] ?? $item->size }}" placeholder="Size">
                                        </div>
                                    </div>
                                    <div class="item-inline-fields">
                                        <select name="items[{{ $index }}][service_slug]" required>
                                            @foreach ($services as $service)
                                                <option value="{{ $service->slug }}" @selected(($oldItem['service_slug'] ?? $item->service?->slug) === $service->slug)>{{ $service->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="quantity-cell">
                                        <input name="items[{{ $index }}][quantity]" type="number" min="1" max="99" value="{{ $quantity }}" required>
                                    </div>
                                    <label class="item-price-input">
                                        <input name="items[{{ $index }}][unit_price]" type="number" min="0" value="{{ $unitPrice }}" required>
                                    </label>
                                    <strong class="green-price">Rp {{ number_format(((int) $quantity) * ((int) $unitPrice), 0, ',', '.') }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <button class="create-order-button" type="submit">Simpan Perubahan</button>
                </form>
            </main>
        </div>
    </body>
</html>

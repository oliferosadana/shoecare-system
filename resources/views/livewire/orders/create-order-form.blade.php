<form class="create-order-layout create-order-layout--reference" wire:submit.prevent="save">
    <section class="create-order-form-stack">
        @if ($errors->any())
            <div class="dashboard-flash dashboard-flash--error">
                <strong>Order belum bisa disimpan.</strong>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <article class="reference-section-card">
            <div class="reference-section-head">
                <span class="reference-section-icon"><i data-lucide="user-round"></i></span>
                <h2>1. Informasi Pelanggan</h2>
            </div>

            <div class="reference-form-grid">
                <label class="reference-field reference-field--full customer-select-field">
                    <span>Pakai Data Pelanggan Lama</span>
                    <i data-lucide="users"></i>
                    <select wire:model="selectedCustomerId" wire:change="selectCustomer">
                        <option value="">Pelanggan baru / input manual</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} - {{ $customer->phone }} ({{ $customer->orders_count }} order)</option>
                        @endforeach
                    </select>
                    <i data-lucide="chevron-down"></i>
                </label>

                <label class="reference-field">
                    <span>Nama Pelanggan <b>*</b></span>
                    <i data-lucide="user-round"></i>
                    <input type="text" wire:model.live.debounce.400ms="customerName" placeholder="Masukkan nama pelanggan">
                </label>

                <label class="reference-field">
                    <span>No. WhatsApp <b>*</b></span>
                    <i data-lucide="message-circle"></i>
                    <input type="tel" wire:model.live.debounce.400ms="phone" placeholder="08xx-xxxx-xxxx">
                </label>

                <label class="reference-field reference-field--full">
                    <span>Lokasi / Alamat <b>*</b></span>
                    <i data-lucide="map-pin"></i>
                    <input type="text" wire:model.live.debounce.400ms="address" placeholder="Pilih lokasi atau masukkan alamat lengkap">
                    <i data-lucide="chevron-down"></i>
                </label>

                <label class="reference-field reference-field--full">
                    <span>Catatan (Opsional)</span>
                    <i data-lucide="notebook-text"></i>
                    <input type="text" wire:model.live.debounce.400ms="note" placeholder="Tambahkan catatan untuk order ini">
                </label>

                @if ($selectedCustomerId !== '')
                    <div class="selected-customer-card reference-field--full">
                        <i data-lucide="badge-check"></i>
                        <div>
                            <strong>Pelanggan lama dipilih: {{ $customerName }}</strong>
                            <small>{{ $phone }}</small>
                        </div>
                        <button type="button" wire:click="clearCustomer">Input Manual</button>
                    </div>
                @endif
            </div>
        </article>

        <article class="reference-section-card">
            <div class="reference-section-head reference-section-head--between">
                <div>
                    <span class="reference-section-icon"><i data-lucide="sparkles"></i></span>
                    <h2>2. Detail Item</h2>
                </div>
            </div>

            <div class="item-table-block">
                <p>Item Sepatu <b>*</b></p>
                <div class="create-item-table">
                    <div class="create-item-head">
                        <span>#</span>
                        <span>Foto</span>
                        <span>Nama Item / Sepatu</span>
                        <span>Jumlah</span>
                        <span>Harga</span>
                        <span>Subtotal</span>
                        <span>Aksi</span>
                    </div>

                    @foreach ($items as $index => $item)
                        <div class="create-item-row" wire:key="create-item-{{ $item['key'] }}">
                            <span class="drag-handle"><i data-lucide="grip-vertical"></i><span>Item</span><span>{{ $index + 1 }}</span></span>

                            <label class="item-photo-upload">
                                <input type="file" wire:model="beforePhotos.{{ $item['key'] }}" accept="image/*">
                                @if (isset($beforePhotos[$item['key']]) && method_exists($beforePhotos[$item['key']], 'temporaryUrl'))
                                    <img src="{{ $beforePhotos[$item['key']]->temporaryUrl() }}" alt="Preview foto sepatu">
                                @else
                                    <i data-lucide="camera"></i>
                                @endif
                                <span>{{ isset($beforePhotos[$item['key']]) ? 'Ganti Foto' : 'Foto Real' }}</span>
                            </label>

                            <div class="item-name-cell">
                                <label class="item-field item-field--name">
                                    <span>Nama Sepatu</span>
                                    <input class="item-name-input" type="text" wire:model.live.debounce.400ms="items.{{ $index }}.item_name" placeholder="Contoh: Nike Air Force Putih">
                                </label>
                                <div class="item-inline-fields">
                                    <label class="item-field">
                                        <span>Layanan</span>
                                        <select wire:model.live="items.{{ $index }}.service_slug" wire:change="syncItemPrice({{ $index }})">
                                            @foreach ($services as $service)
                                                <option value="{{ $service->slug }}">{{ $service->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="item-field">
                                        <span>Size</span>
                                        <input type="text" wire:model.live.debounce.400ms="items.{{ $index }}.size" placeholder="Contoh: 42">
                                    </label>
                                </div>
                            </div>

                            <div class="quantity-cell">
                                <span class="quantity-title">Jumlah</span>
                                <button type="button" wire:click="decrementItem({{ $index }})">-</button>
                                <input type="number" min="1" max="99" wire:model.live="items.{{ $index }}.quantity">
                                <button type="button" wire:click="incrementItem({{ $index }})">+</button>
                                <span>Pasang</span>
                            </div>

                            <label class="item-price-input">
                                <span>Harga</span>
                                <input type="number" min="0" wire:model.live.debounce.300ms="items.{{ $index }}.unit_price">
                            </label>

                            <div class="item-subtotal-mobile">
                                <span>Subtotal</span>
                                <strong class="green-price">Rp {{ number_format(((int) $item['quantity']) * ((int) $item['unit_price']), 0, ',', '.') }}</strong>
                            </div>

                            <div class="row-actions">
                                <button type="button" title="Hapus item" wire:click="removeItem({{ $index }})" @disabled(count($items) === 1)><i data-lucide="trash-2"></i></button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <button class="add-item-button" type="button" wire:click="addItem"><i data-lucide="plus"></i><span>Tambah Item Sepatu</span></button>
                <p class="photo-upload-hint">Gunakan foto asli sepatu customer. Di HP, Anda bisa mengambil dari kamera atau memilih dari galeri.</p>
            </div>

            <label class="reference-field reference-field--full">
                <span>Catatan Item (Opsional)</span>
                <i data-lucide="notebook-text"></i>
                <input type="text" wire:model.live.debounce.400ms="itemNote" placeholder="Tambahkan catatan khusus untuk item di atas">
            </label>
        </article>

        <article class="reference-section-card">
            <div class="reference-section-head">
                <span class="reference-section-icon"><i data-lucide="calendar-days"></i></span>
                <h2>3. Jadwal Pengerjaan</h2>
            </div>

            <div class="reference-form-grid">
                <label class="reference-field">
                    <span>Estimasi Tanggal Selesai</span>
                    <i data-lucide="calendar-check"></i>
                    <input type="date" wire:model.live="estimatedDate">
                </label>
                <label class="reference-field">
                    <span>Estimasi Jam Selesai</span>
                    <i data-lucide="clock"></i>
                    <input type="time" wire:model.live="estimatedTime">
                </label>
            </div>
        </article>

        <article class="reference-section-card">
            <div class="reference-section-head">
                <span class="reference-section-icon"><i data-lucide="truck"></i></span>
                <h2>4. Pickup / Delivery</h2>
            </div>

            <div class="reference-form-grid">
                <label class="reference-field">
                    <span>Jenis Pickup / Delivery</span>
                    <i data-lucide="route"></i>
                    <select wire:model.live="pickupDeliveryType">
                        <option value="none">Tanpa Pickup/Delivery</option>
                        <option value="pickup">Pickup</option>
                        <option value="delivery">Delivery</option>
                        <option value="pickup_delivery">Pickup + Delivery</option>
                    </select>
                    <i data-lucide="chevron-down"></i>
                </label>
                <label class="reference-field">
                    <span>Ongkos Pickup / Delivery</span>
                    <i data-lucide="banknote"></i>
                    <input type="text" wire:model.live.debounce.300ms="pickupDeliveryFee" placeholder="0">
                </label>
            </div>
        </article>
    </section>

    <aside class="reference-summary-panel">
        <article class="reference-summary-card">
            <h2>Ringkasan Order</h2>

            @foreach ($items as $item)
                <div class="summary-product" wire:key="summary-{{ $item['key'] }}">
                    <div class="summary-shoe summary-shoe--white">
                        @if (isset($beforePhotos[$item['key']]) && method_exists($beforePhotos[$item['key']], 'temporaryUrl'))
                            <img src="{{ $beforePhotos[$item['key']]->temporaryUrl() }}" alt="Preview foto sepatu">
                        @endif
                    </div>
                    <div>
                        <span class="service-badge">{{ $services->firstWhere('slug', $item['service_slug'])?->name ?? '-' }}</span>
                        <strong>{{ $item['item_name'] ?: 'Nama sepatu belum diisi' }}</strong>
                        <small>{{ $item['quantity'] }} Pasang @if($item['size']) · Size: {{ $item['size'] }} @endif</small>
                        <b>Rp {{ number_format(((int) $item['quantity']) * ((int) $item['unit_price']), 0, ',', '.') }}</b>
                    </div>
                </div>
            @endforeach

            <div class="reference-price-lines">
                <div><span>Subtotal</span><strong>Rp {{ number_format($subtotal, 0, ',', '.') }}</strong></div>
                <label><span>Diskon</span><input type="text" wire:model.live.debounce.300ms="discountAmount"><b>Rp</b></label>
                <div><span>Pickup / Delivery</span><strong>Rp {{ number_format((int) preg_replace('/[^\d]/', '', $pickupDeliveryFee), 0, ',', '.') }}</strong></div>
                <div><span>Total</span><strong>Rp {{ number_format($total, 0, ',', '.') }}</strong></div>
            </div>

            <div class="total-pay-card">
                <span>Total Bayar</span>
                <strong>Rp {{ number_format($total, 0, ',', '.') }}</strong>
            </div>

            <div class="payment-note">
                <i data-lucide="shield-check"></i>
                <span>Pembayaran akan dikonfirmasi saat order selesai.</span>
            </div>

            <div class="summary-bottom-actions">
                <button class="reset-order-button" type="button" wire:click="resetForm"><i data-lucide="refresh-cw"></i><span>Reset</span></button>
                <button class="create-order-button" type="submit" wire:loading.attr="disabled" wire:target="save">
                    <i data-lucide="circle-plus"></i>
                    <span wire:loading.remove wire:target="save">Buat Order</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </button>
            </div>
        </article>
    </aside>
</form>

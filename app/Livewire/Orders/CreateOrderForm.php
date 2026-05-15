<?php

namespace App\Livewire\Orders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class CreateOrderForm extends Component
{
    use WithFileUploads;

    public string $selectedCustomerId = '';

    public string $customerName = '';

    public string $phone = '';

    public string $address = '';

    public string $note = '';

    public string $itemNote = '';

    public string $estimatedDate = '';

    public string $estimatedTime = '';

    public string $pickupDeliveryType = 'none';

    public string $pickupDeliveryFee = '0';

    public string $discountAmount = '0';

    public array $items = [];

    public array $beforePhotos = [];

    public ?string $success = null;

    public function mount(): void
    {
        $defaultEstimate = now()->addDays(2)->setTime(17, 0);

        $this->estimatedDate = $defaultEstimate->format('Y-m-d');
        $this->estimatedTime = $defaultEstimate->format('H:i');
        $this->addItem();
    }

    public function selectCustomer(): void
    {
        if ($this->selectedCustomerId === '') {
            return;
        }

        $customer = Customer::find($this->selectedCustomerId);

        if (! $customer) {
            return;
        }

        $this->customerName = $customer->name;
        $this->phone = $customer->phone;
        $this->address = (string) $customer->address;
        $this->note = (string) $customer->notes;
    }

    public function clearCustomer(): void
    {
        $this->selectedCustomerId = '';
        $this->customerName = '';
        $this->phone = '';
        $this->address = '';
        $this->note = '';
    }

    public function addItem(): void
    {
        $previousServiceSlug = $this->items[array_key_last($this->items)]['service_slug'] ?? null;
        $service = $this->serviceBySlug($previousServiceSlug) ?? $this->defaultService();

        $this->items[] = [
            'key' => (string) Str::uuid(),
            'service_slug' => $service?->slug ?? 'deep-clean',
            'item_name' => '',
            'size' => '',
            'quantity' => 1,
            'unit_price' => $service?->price ?? 0,
        ];

        $this->dispatch('refresh-icons');
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) <= 1) {
            return;
        }

        $key = $this->items[$index]['key'] ?? null;

        array_splice($this->items, $index, 1);

        if ($key) {
            unset($this->beforePhotos[$key]);
        }

        $this->dispatch('refresh-icons');
    }

    public function incrementItem(int $index): void
    {
        $this->items[$index]['quantity'] = min(((int) ($this->items[$index]['quantity'] ?? 1)) + 1, 99);
    }

    public function decrementItem(int $index): void
    {
        $this->items[$index]['quantity'] = max(((int) ($this->items[$index]['quantity'] ?? 1)) - 1, 1);
    }

    public function syncItemPrice(int $index): void
    {
        $service = $this->serviceBySlug($this->items[$index]['service_slug'] ?? null);

        if ($service) {
            $this->items[$index]['unit_price'] = $service->price;
        }
    }

    public function updatedBeforePhotos($value, string $key): void
    {
        $this->validateOnly("beforePhotos.{$key}", [
            "beforePhotos.{$key}" => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ], [
            "beforePhotos.{$key}.image" => 'Foto sepatu harus berupa file gambar.',
            "beforePhotos.{$key}.mimes" => 'Foto sepatu harus berformat JPG, JPEG, PNG, atau WEBP.',
            "beforePhotos.{$key}.max" => 'Ukuran foto sepatu maksimal 5MB.',
            "beforePhotos.{$key}.dimensions" => 'Resolusi foto sepatu maksimal 6000x6000 piksel.',
        ]);

        $this->dispatch('refresh-icons');
    }

    public function removeBeforePhoto(string $key): void
    {
        unset($this->beforePhotos[$key]);
        $this->resetValidation("beforePhotos.{$key}");
        $this->dispatch('refresh-icons');
    }

    public function resetForm(): void
    {
        $this->reset([
            'selectedCustomerId',
            'customerName',
            'phone',
            'address',
            'note',
            'itemNote',
            'pickupDeliveryType',
            'pickupDeliveryFee',
            'discountAmount',
            'items',
            'beforePhotos',
            'success',
        ]);

        $this->pickupDeliveryType = 'none';
        $this->pickupDeliveryFee = '0';
        $this->discountAmount = '0';
        $this->mount();
    }

    public function save()
    {
        $validated = $this->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'itemNote' => ['nullable', 'string', 'max:2000'],
            'discountAmount' => ['nullable', 'string', 'max:50'],
            'pickupDeliveryType' => ['nullable', 'string', 'in:none,pickup,delivery,pickup_delivery'],
            'pickupDeliveryFee' => ['nullable', 'string', 'max:50'],
            'estimatedDate' => ['nullable', 'date'],
            'estimatedTime' => ['nullable', 'date_format:H:i'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.key' => ['required', 'string'],
            'items.*.service_slug' => ['required', 'string', 'exists:services,slug'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.size' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'beforePhotos.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ], [
            'customerName.required' => 'Nama pelanggan wajib diisi.',
            'phone.required' => 'No. WhatsApp wajib diisi.',
            'address.required' => 'Lokasi / alamat wajib diisi.',
            'items.*.service_slug.required' => 'Layanan item wajib dipilih.',
            'items.*.item_name.required' => 'Nama item sepatu wajib diisi.',
            'items.*.quantity.required' => 'Jumlah item wajib diisi.',
            'items.*.unit_price.required' => 'Harga item wajib diisi.',
            'beforePhotos.*.image' => 'Foto sepatu harus berupa file gambar.',
            'beforePhotos.*.mimes' => 'Foto sepatu harus berformat JPG, JPEG, PNG, atau WEBP.',
            'beforePhotos.*.max' => 'Ukuran foto sepatu maksimal 5MB.',
            'beforePhotos.*.dimensions' => 'Resolusi foto sepatu maksimal 6000x6000 piksel.',
        ]);

        $order = DB::transaction(function () use ($validated): Order {
            $customer = Customer::updateOrCreate([
                'phone' => $validated['phone'],
            ], [
                'name' => $validated['customerName'],
                'address' => $validated['address'],
                'notes' => $validated['note'] ?? null,
            ]);

            $items = collect($validated['items'])->map(function (array $item) {
                $quantity = (int) $item['quantity'];
                $unitPrice = (int) $item['unit_price'];
                $service = Service::where('slug', $item['service_slug'])->firstOrFail();

                return [
                    'key' => $item['key'],
                    'service' => $service,
                    'item_name' => $item['item_name'],
                    'size' => $item['size'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $quantity * $unitPrice,
                ];
            });

            $subtotal = $items->sum('line_total');
            $discount = min($this->moneyToInteger($validated['discountAmount'] ?? 0), $subtotal);
            $pickupDeliveryFee = $this->moneyToInteger($validated['pickupDeliveryFee'] ?? 0);
            $receivedAt = now();

            $order = Order::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'customer_id' => $customer->id,
                'received_by' => auth()->id(),
                'status' => 'diterima',
                'received_at' => $receivedAt,
                'estimated_finished_at' => $this->estimatedFinishedAt($validated['estimatedDate'] ?? null, $validated['estimatedTime'] ?? null, $receivedAt->copy()->addDays(2)),
                'payment_status' => 'unpaid',
                'payment_method' => 'cash',
                'pickup_delivery_type' => $validated['pickupDeliveryType'] ?? 'none',
                'pickup_delivery_fee' => $pickupDeliveryFee,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total_amount' => $subtotal - $discount + $pickupDeliveryFee,
                'notes' => $validated['note'] ?? null,
            ]);

            foreach ($items as $item) {
                $photo = $this->beforePhotos[$item['key']] ?? null;

                $order->items()->create([
                    'service_id' => $item['service']->id,
                    'item_name' => $item['item_name'],
                    'size' => $item['size'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'before_photo_path' => $photo ? $photo->store('order-photos/before', 'public') : null,
                    'notes' => $validated['itemNote'] ?? null,
                ]);
            }

            $order->timelines()->create([
                'status' => 'diterima',
                'label' => 'Order diterima',
                'description' => 'Order baru berhasil dibuat oleh admin.',
                'logged_at' => now(),
                'created_by' => auth()->id(),
            ]);

            return $order;
        });

        session()->flash('success', "Order {$order->invoice_number} berhasil dibuat.");

        return redirect()->route('orders.show', $order);
    }

    public function render(): View
    {
        return view('livewire.orders.create-order-form', [
            'services' => Service::where('is_active', true)->orderBy('id')->get(),
            'customers' => Customer::withCount('orders')->orderBy('name')->get(),
            'subtotal' => $this->subtotal(),
            'total' => $this->total(),
        ]);
    }

    private function defaultService(): ?Service
    {
        return Service::where('slug', 'deep-clean')->first() ?? Service::where('is_active', true)->orderBy('id')->first();
    }

    private function serviceBySlug(?string $slug): ?Service
    {
        if (! $slug) {
            return null;
        }

        return Service::where('slug', $slug)->first();
    }

    private function subtotal(): int
    {
        return collect($this->items)->sum(fn (array $item): int => ((int) ($item['quantity'] ?? 0)) * ((int) ($item['unit_price'] ?? 0)));
    }

    private function total(): int
    {
        return max($this->subtotal() - $this->moneyToInteger($this->discountAmount) + $this->moneyToInteger($this->pickupDeliveryFee), 0);
    }

    private function moneyToInteger(string|int|null $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $numeric = preg_replace('/[^\d]/', '', (string) $value);

        return $numeric === '' ? 0 : (int) $numeric;
    }

    private function estimatedFinishedAt(?string $date, ?string $time, Carbon $fallback): Carbon
    {
        if (! $date) {
            return $fallback;
        }

        return Carbon::parse($date . ' ' . ($time ?: $fallback->format('H:i')));
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . now()->format('ymd') . '-';
        $lastOrder = Order::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('invoice_number')
            ->first();

        $nextNumber = $lastOrder
            ? ((int) substr($lastOrder->invoice_number, -4)) + 1
            : 1;

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function formatRupiah(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

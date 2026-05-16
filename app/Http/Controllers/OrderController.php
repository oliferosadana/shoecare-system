<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        $query = Order::with(['customer', 'items.service'])->latest('received_at');

        if ($search = request('q')) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        if ($status = request('status')) {
            $query->where('status', $status);
        }

        if ($paymentStatus = request('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        if (request('deadline') === 'overdue') {
            $query->where('estimated_finished_at', '<', now())
                ->whereNotIn('status', ['diambil', 'dibatalkan']);
        }

        if (request('quick') === 'ready_pickup') {
            $query->where('status', 'menunggu_diambil');
        }

        $paginator = $query->paginate(10)->withQueryString();
        $orders = $paginator->getCollection();
        $allStatusCounts = Order::query()->get()->countBy(fn (Order $order): string => $this->statusLabel($order->status));

        return view('orders.index', [
            'ordersForView' => $orders->map(fn (Order $order): array => $this->orderForFrontend($order))->values(),
            'ordersTotal' => $paginator->total(),
            'ordersPaginator' => $paginator,
            'statusCounts' => $allStatusCounts,
            'statusOptions' => $this->statusOptions(),
            'quickFilterCounts' => $this->quickFilterCounts(),
        ]);
    }

    public function create(): View
    {
        return view('orders.create', [
            'services' => Service::where('is_active', true)->orderBy('id')->get(),
            'customers' => Customer::withCount('orders')->orderBy('name')->get(),
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['customer', 'items.service', 'payment', 'payments', 'timelines']);

        return view('orders.show', [
            'order' => $order,
            'statuses' => $this->statusOptions(),
            'whatsappUrl' => $this->whatsappUrl($order),
            'whatsappReminderUrl' => $this->whatsappReminderUrl($order),
            'whatsappReadyPickupUrl' => $this->whatsappReadyPickupUrl($order),
            'whatsappBillingUrl' => $this->whatsappBillingUrl($order),
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    public function invoice(Order $order): View
    {
        $order->load(['customer', 'items.service', 'payment', 'payments', 'timelines']);

        return view('orders.invoice', [
            'order' => $order,
            'whatsappUrl' => $this->whatsappUrl($order),
        ]);
    }

    public function track(string $invoiceNumber): View
    {
        $order = Order::with(['customer', 'items.service', 'payment', 'payments', 'timelines'])
            ->where('invoice_number', $invoiceNumber)
            ->firstOrFail();

        return view('orders.track', [
            'order' => $order,
            'whatsappUrl' => $this->whatsappUrl($order),
        ]);
    }

    public function edit(Order $order): View
    {
        $order->load(['customer', 'items.service']);

        return view('orders.edit', [
            'order' => $order,
            'services' => Service::where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'discount_amount' => ['nullable', 'string', 'max:50'],
            'pickup_delivery_type' => ['nullable', 'string', 'in:none,pickup,delivery,pickup_delivery'],
            'pickup_delivery_fee' => ['nullable', 'string', 'max:50'],
            'estimated_date' => ['nullable', 'date'],
            'estimated_time' => ['nullable', 'date_format:H:i'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.service_slug' => ['required', 'string', 'exists:services,slug'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.size' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
            'items.*.before_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480', 'dimensions:max_width=6000,max_height=6000'],
        ], [
            'required' => ':attribute wajib diisi.',
            'items.required' => 'Minimal satu item sepatu wajib tersedia.',
            'items.*.before_photo.image' => 'Foto sepatu harus berupa file gambar.',
            'items.*.before_photo.mimes' => 'Foto sepatu harus berformat JPG, JPEG, PNG, atau WEBP.',
            'items.*.before_photo.max' => 'Ukuran foto sepatu maksimal 20MB.',
            'items.*.before_photo.dimensions' => 'Resolusi foto sepatu maksimal 6000x6000 piksel.',
        ], [
            'customer_name' => 'Nama pelanggan',
            'phone' => 'No. WhatsApp',
            'items.*.service_slug' => 'Layanan item',
            'items.*.item_name' => 'Nama item sepatu',
            'items.*.quantity' => 'Jumlah item',
            'items.*.unit_price' => 'Harga item',
        ]);

        DB::transaction(function () use ($validated, $request, $order): void {
            $order->customer->update([
                'name' => $validated['customer_name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'] ?? null,
            ]);

            $subtotal = 0;

            foreach ($validated['items'] as $index => $itemData) {
                $item = $order->items()->whereKey($itemData['id'])->firstOrFail();
                $service = Service::where('slug', $itemData['service_slug'])->firstOrFail();
                $quantity = (int) $itemData['quantity'];
                $unitPrice = (int) $itemData['unit_price'];
                $lineTotal = $quantity * $unitPrice;
                $beforePhoto = $request->file("items.{$index}.before_photo");

                if ($beforePhoto && $item->before_photo_path) {
                    Storage::disk('public')->delete($item->before_photo_path);
                }

                $item->update([
                    'service_id' => $service->id,
                    'item_name' => $itemData['item_name'],
                    'size' => $itemData['size'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'before_photo_path' => $beforePhoto?->store('order-photos/before', 'public') ?? $item->before_photo_path,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                $subtotal += $lineTotal;
            }

            $discount = min($this->moneyToInteger($validated['discount_amount'] ?? 0), $subtotal);
            $pickupDeliveryFee = $this->moneyToInteger($validated['pickup_delivery_fee'] ?? 0);
            $order->update([
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'pickup_delivery_type' => $validated['pickup_delivery_type'] ?? 'none',
                'pickup_delivery_fee' => $pickupDeliveryFee,
                'total_amount' => $subtotal - $discount + $pickupDeliveryFee,
                'estimated_finished_at' => $this->estimatedFinishedAt($validated['estimated_date'] ?? null, $validated['estimated_time'] ?? null, $order->estimated_finished_at ?? now()->addDays(2)),
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->syncPaymentStatus($order);
        });

        return redirect()->route('orders.show', $order)->with('success', 'Order berhasil diperbarui.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'item_note' => ['nullable', 'string', 'max:2000'],
            'discount_amount' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'payment_status' => ['nullable', 'string', 'max:30'],
            'pickup_delivery_type' => ['nullable', 'string', 'in:none,pickup,delivery,pickup_delivery'],
            'pickup_delivery_fee' => ['nullable', 'string', 'max:50'],
            'estimated_date' => ['nullable', 'date'],
            'estimated_time' => ['nullable', 'date_format:H:i'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_slug' => ['required', 'string', 'max:80'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.size' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.before_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480', 'dimensions:max_width=6000,max_height=6000'],
        ], [
            'required' => ':attribute wajib diisi.',
            'items.required' => 'Minimal satu item sepatu wajib ditambahkan.',
            'items.min' => 'Minimal satu item sepatu wajib ditambahkan.',
            'items.*.before_photo.image' => 'Foto sepatu harus berupa file gambar.',
            'items.*.before_photo.mimes' => 'Foto sepatu harus berformat JPG, JPEG, PNG, atau WEBP.',
            'items.*.before_photo.max' => 'Ukuran foto sepatu maksimal 20MB.',
            'items.*.before_photo.dimensions' => 'Resolusi foto sepatu maksimal 6000x6000 piksel.',
        ], [
            'customer_name' => 'Nama pelanggan',
            'phone' => 'No. WhatsApp',
            'address' => 'Lokasi / alamat',
            'items.*.service_slug' => 'Layanan item',
            'items.*.item_name' => 'Nama item sepatu',
            'items.*.quantity' => 'Jumlah item',
            'items.*.unit_price' => 'Harga item',
            'items.*.before_photo' => 'Foto sepatu',
        ]);

        $order = DB::transaction(function () use ($validated, $request): Order {
            $customer = Customer::updateOrCreate([
                'phone' => $validated['phone'],
            ], [
                'name' => $validated['customer_name'],
                'address' => $validated['address'],
                'notes' => $validated['note'] ?? null,
            ]);

            $items = collect($validated['items'])->map(function (array $item, int $index) {
                $quantity = (int) $item['quantity'];
                $unitPrice = (int) $item['unit_price'];
                $service = Service::where('slug', $item['service_slug'])->first();

                return [
                    'index' => $index,
                    'service' => $service,
                    'item_name' => $item['item_name'],
                    'size' => $item['size'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $quantity * $unitPrice,
                ];
            });

            $subtotal = $items->sum('line_total');
            $discount = min($this->moneyToInteger($validated['discount_amount'] ?? 0), $subtotal);
            $pickupDeliveryFee = $this->moneyToInteger($validated['pickup_delivery_fee'] ?? 0);
            $total = $subtotal - $discount + $pickupDeliveryFee;
            $receivedAt = now();
            $estimatedFinishedAt = $this->estimatedFinishedAt($validated['estimated_date'] ?? null, $validated['estimated_time'] ?? null, $receivedAt->copy()->addDays(2));

            $order = Order::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'customer_id' => $customer->id,
                'received_by' => $request->user()?->id,
                'status' => 'diterima',
                'received_at' => $receivedAt,
                'estimated_finished_at' => $estimatedFinishedAt,
                'payment_status' => $validated['payment_status'] ?? 'unpaid',
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'pickup_delivery_type' => $validated['pickup_delivery_type'] ?? 'none',
                'pickup_delivery_fee' => $pickupDeliveryFee,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total_amount' => $total,
                'notes' => $validated['note'] ?? null,
            ]);

            foreach ($items as $item) {
                $beforePhoto = $request->file("items.{$item['index']}.before_photo");

                $order->items()->create([
                    'service_id' => $item['service']?->id,
                    'item_name' => $item['item_name'],
                    'size' => $item['size'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'before_photo_path' => $beforePhoto?->store('order-photos/before', 'public'),
                    'notes' => $validated['item_note'] ?? null,
                ]);
            }

            if (($validated['payment_status'] ?? 'unpaid') === 'paid') {
                $order->payment()->create([
                    'amount_paid' => $total,
                    'method' => $validated['payment_method'] ?? 'cash',
                    'status' => 'paid',
                    'paid_at' => now(),
                    'recorded_by' => $request->user()?->id,
                ]);
            }

            $order->timelines()->create([
                'status' => 'diterima',
                'label' => 'Order diterima',
                'description' => 'Order baru berhasil dibuat oleh admin.',
                'logged_at' => now(),
                'created_by' => $request->user()?->id,
            ]);

            return $order;
        });

        return redirect()
            ->route('orders.index')
            ->with('success', "Order {$order->invoice_number} berhasil dibuat.");
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:diterima,proses,selesai,menunggu_diambil,diambil,dibatalkan'],
        ], [
            'status.required' => 'Status order wajib dipilih.',
            'status.in' => 'Status order tidak valid.',
        ]);

        $status = $validated['status'];
        $updates = ['status' => $status];

        if ($status === 'selesai') {
            $updates['finished_at'] = now();
            $updates['status'] = 'menunggu_diambil';
        }

        if ($status === 'diambil') {
            $updates['picked_up_at'] = now();
        }

        $order->update($updates);
        $timelineStatus = $status === 'selesai' ? 'menunggu_diambil' : $status;
        $order->timelines()->create([
            'status' => $timelineStatus,
            'label' => $timelineStatus === 'menunggu_diambil' ? $order->readyOrderLabel() : $this->statusLabel($timelineStatus),
            'description' => $timelineStatus === 'menunggu_diambil' ? $order->readyOrderDescription() : $this->timelineDescription($status),
            'logged_at' => now(),
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Status order berhasil diperbarui.');
    }

    public function uploadAfterPhoto(Request $request, Order $order, OrderItem $item): RedirectResponse
    {
        abort_unless($item->order_id === $order->id, 404);

        $validated = $request->validate([
            'after_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480', 'dimensions:max_width=6000,max_height=6000'],
        ], [
            'after_photo.required' => 'Foto after wajib dipilih.',
            'after_photo.image' => 'Foto after harus berupa gambar.',
            'after_photo.mimes' => 'Foto after harus berformat JPG, JPEG, PNG, atau WEBP.',
            'after_photo.max' => 'Ukuran foto after maksimal 20MB.',
            'after_photo.dimensions' => 'Resolusi foto after maksimal 6000x6000 piksel.',
        ]);

        if ($item->after_photo_path) {
            Storage::disk('public')->delete($item->after_photo_path);
        }

        $item->update([
            'after_photo_path' => $request->file('after_photo')->store('order-photos/after', 'public'),
        ]);

        $order->timelines()->create([
            'status' => 'foto_after',
            'label' => 'Foto after diupload',
            'description' => "Foto hasil pengerjaan untuk {$item->item_name} berhasil ditambahkan.",
            'logged_at' => now(),
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Foto after berhasil diupload.');
    }

    public function updatePayment(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'amount_paid' => ['required', 'string', 'max:50'],
            'method' => ['required', 'string', 'in:cash,transfer,qris,card,other'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'amount_paid.required' => 'Nominal pembayaran wajib diisi.',
            'method.required' => 'Metode pembayaran wajib dipilih.',
            'method.in' => 'Metode pembayaran tidak valid.',
        ]);

        $currentPaid = (int) $order->payments()->where('status', 'paid')->sum('amount_paid');
        $remaining = max($order->total_amount - $currentPaid, 0);
        $amountPaid = min($this->moneyToInteger($validated['amount_paid']), $remaining);

        if ($amountPaid <= 0) {
            return back()->withErrors(['amount_paid' => 'Nominal pembayaran harus lebih dari Rp 0 dan order belum boleh lunas.'])->withInput();
        }

        $totalPaid = min($currentPaid + $amountPaid, $order->total_amount);
        $paymentStatus = $this->paymentStatusFromAmount($totalPaid, $order->total_amount);

        DB::transaction(function () use ($validated, $request, $order, $amountPaid, $totalPaid, $paymentStatus): void {
            $order->payments()->create([
                'amount_paid' => $amountPaid,
                'method' => $validated['method'],
                'status' => 'paid',
                'reference_number' => $validated['reference_number'] ?? null,
                'paid_at' => now(),
                'recorded_by' => $request->user()?->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            $order->update([
                'payment_status' => $paymentStatus,
                'payment_method' => $validated['method'],
            ]);

            $order->timelines()->create([
                'status' => 'pembayaran',
                'label' => 'Pembayaran diperbarui',
                'description' => 'Pembayaran masuk ' . $this->formatRupiah($amountPaid) . '. Total terbayar ' . $this->formatRupiah($totalPaid) . ' (' . $this->paymentStatusLabel($paymentStatus) . ').',
                'logged_at' => now(),
                'created_by' => $request->user()?->id,
            ]);
        });

        return back()->with('success', 'Pembayaran berhasil diperbarui.');
    }

    public function destroyPayment(Order $order, Payment $payment): RedirectResponse
    {
        abort_unless($payment->order_id === $order->id, 404);

        DB::transaction(function () use ($order, $payment): void {
            $amount = $payment->amount_paid;
            $payment->delete();

            $this->syncPaymentStatus($order);

            $order->timelines()->create([
                'status' => 'pembayaran',
                'label' => 'Pembayaran dikoreksi',
                'description' => 'Transaksi pembayaran ' . $this->formatRupiah($amount) . ' dihapus dari riwayat.',
                'logged_at' => now(),
                'created_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Transaksi pembayaran berhasil dihapus.');
    }

    public function verifyPaymentRequest(Order $order, Payment $payment): RedirectResponse
    {
        abort_unless($payment->order_id === $order->id && $payment->status === 'pending' && $payment->provider === null, 404);

        if ($payment->method === 'transfer' && ! $payment->proof_photo_path) {
            return back()->withErrors(['payment' => 'Bukti transfer belum diupload customer.']);
        }

        $currentPaid = (int) $order->payments()->where('status', 'paid')->sum('amount_paid');
        $remaining = max($order->total_amount - $currentPaid, 0);
        $amountPaid = min($payment->requested_amount ?: $remaining, $remaining);

        if ($amountPaid <= 0) {
            return back()->withErrors(['payment' => 'Order sudah lunas atau nominal verifikasi tidak valid.']);
        }

        DB::transaction(function () use ($order, $payment, $amountPaid): void {
            $payment->update([
                'amount_paid' => $amountPaid,
                'requested_amount' => $payment->requested_amount ?: $amountPaid,
                'status' => 'paid',
                'paid_at' => now(),
                'recorded_by' => auth()->id(),
                'notes' => trim(($payment->notes ? $payment->notes . ' ' : '') . 'Diverifikasi admin.'),
            ]);

            $this->syncPaymentStatus($order);

            $order->timelines()->create([
                'status' => 'pembayaran',
                'label' => 'Pembayaran diverifikasi',
                'description' => 'Pembayaran ' . $this->formatRupiah($amountPaid) . ' via ' . $this->paymentMethods()[$payment->method] . ' diverifikasi admin.',
                'logged_at' => now(),
                'created_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Pembayaran customer berhasil diverifikasi.');
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

    private function orderForFrontend(Order $order): array
    {
        $firstItem = $order->items->first();
        $services = $order->items
            ->map(fn ($item) => $item->service?->name)
            ->filter()
            ->unique()
            ->values();

        return [
            'id' => $order->invoice_number,
            'customer' => $order->customer?->name ?? '-',
            'phone' => $order->customer?->phone ?? '-',
            'service' => $services->isNotEmpty() ? $services->join(' + ') : '-',
            'date' => $order->received_at->translatedFormat('d M Y'),
            'time' => $order->received_at->format('H:i'),
            'status' => $order->displayStatusLabel(),
            'statusClass' => 'tag--' . $this->statusClass($order->status),
            'amount' => $this->formatRupiah($order->total_amount),
            'itemName' => $firstItem?->item_name ?? '-',
            'qty' => $order->items->sum('quantity') . ' Pasang',
            'subtotal' => $this->formatRupiah($order->subtotal),
            'discount' => $order->discount_amount > 0 ? '- ' . $this->formatRupiah($order->discount_amount) : '-',
            'pickupDeliveryType' => $this->pickupDeliveryTypeLabel($order->pickup_delivery_type ?? 'none'),
            'pickupDeliveryFee' => $this->formatRupiah($order->pickup_delivery_fee ?? 0),
            'total' => $this->formatRupiah($order->total_amount),
            'paymentMethod' => ucfirst($order->payment_method ?? '-'),
            'location' => $order->customer?->address ?? '-',
            'estimate' => $order->estimated_finished_at?->translatedFormat('d M Y') ?? '-',
            'estimateTime' => $order->estimated_finished_at?->format('H:i') ?? '-',
            'isOverdue' => $this->isOverdue($order),
            'deadlineLabel' => $this->deadlineLabel($order),
            'completedAt' => $order->finished_at?->translatedFormat('d M Y') ?? '-',
            'completedTime' => $order->finished_at?->format('H:i') ?? '-',
            'summaryText' => 'Order tersimpan di sistem ZOLIX Shoe Care.',
            'photoUrl' => $firstItem?->before_photo_path ? Storage::url($firstItem->before_photo_path) : null,
            'showUrl' => route('orders.show', $order),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'proses' => 'Proses',
            'selesai' => 'Selesai',
            'diambil' => 'Diambil',
            'menunggu_diambil' => 'Menunggu Diambil',
            'dibatalkan' => 'Dibatalkan',
            default => 'Diterima',
        };
    }

    private function statusOptions(): array
    {
        return [
            'diterima' => 'Diterima',
            'proses' => 'Proses',
            'selesai' => 'Selesai',
            'menunggu_diambil' => 'Menunggu Diambil',
            'diambil' => 'Diambil',
            'dibatalkan' => 'Dibatalkan',
        ];
    }

    private function paymentMethods(): array
    {
        return [
            'cash' => 'Cash',
            'transfer' => 'Transfer',
            'qris' => 'QRIS',
            'card' => 'Kartu Debit/Kredit',
            'other' => 'Lainnya',
        ];
    }

    private function quickFilterCounts(): array
    {
        return [
            'unpaid' => Order::where('payment_status', 'unpaid')->count(),
            'partial' => Order::where('payment_status', 'partial')->count(),
            'overdue' => Order::where('estimated_finished_at', '<', now())
                ->whereNotIn('status', ['diambil', 'dibatalkan'])
                ->count(),
            'ready_pickup' => Order::where('status', 'menunggu_diambil')->count(),
        ];
    }

    private function pickupDeliveryTypeLabel(string $type): string
    {
        return match ($type) {
            'pickup' => 'Pickup',
            'delivery' => 'Delivery',
            'pickup_delivery' => 'Pickup + Delivery',
            default => 'Tanpa Pickup/Delivery',
        };
    }

    private function paymentStatusFromAmount(int $amountPaid, int $totalAmount): string
    {
        if ($amountPaid <= 0) {
            return 'unpaid';
        }

        return $amountPaid >= $totalAmount ? 'paid' : 'partial';
    }

    private function paymentStatusLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'Lunas',
            'partial' => 'Bayar Sebagian',
            default => 'Belum Bayar',
        };
    }

    private function syncPaymentStatus(Order $order): void
    {
        $order->loadMissing('payments');

        $totalPaid = min((int) $order->payments()->sum('amount_paid'), $order->total_amount);
        $latestPayment = $order->payments()->latest('paid_at')->first();

        $order->update([
            'payment_status' => $this->paymentStatusFromAmount($totalPaid, $order->total_amount),
            'payment_method' => $latestPayment?->method ?? $order->payment_method,
        ]);
    }

    private function timelineDescription(string $status): string
    {
        return match ($status) {
            'proses' => 'Order masuk proses pengerjaan.',
            'selesai' => 'Order selesai dikerjakan dan siap dikonfirmasi ke pelanggan.',
            'menunggu_diambil' => 'Order siap diambil oleh pelanggan.',
            'diambil' => 'Order sudah diambil pelanggan.',
            'dibatalkan' => 'Order dibatalkan.',
            default => 'Order diterima oleh outlet.',
        };
    }

    private function whatsappUrl(Order $order): string
    {
        $message = implode("\n", [
            "Halo Kak {$order->customer?->name},",
            '',
            '*Update Order ZOLIX Shoe Care*',
            "No. Nota: {$order->invoice_number}",
            'Status: ' . $order->displayStatusLabel(),
            'Total: ' . $this->formatRupiah($order->total_amount),
            '',
            'Cek detail dan tracking order melalui link berikut:',
            route('orders.track', $order->invoice_number),
            '',
            'Terima kasih telah menggunakan ZOLIX Shoe Care.',
        ]);

        return $this->buildWhatsappUrl($order, $message);
    }

    private function whatsappReminderUrl(Order $order): string
    {
        $estimate = $order->estimated_finished_at?->format('d M Y H:i') . ' WITA';
        $message = implode("\n", [
            "Halo Kak {$order->customer?->name},",
            '',
            '*Informasi Order ZOLIX Shoe Care*',
            "No. Nota: {$order->invoice_number}",
            'Status: Masih dalam penanganan',
            "Estimasi sebelumnya: {$estimate}",
            '',
            'Order Kakak sedang kami prioritaskan. Mohon ditunggu, kami akan kabari kembali setelah selesai.',
            '',
            'Tracking order:',
            route('orders.track', $order->invoice_number),
        ]);

        return $this->buildWhatsappUrl($order, $message);
    }

    private function whatsappReadyPickupUrl(Order $order): string
    {
        $message = implode("\n", [
            "Halo Kak {$order->customer?->name},",
            '',
            '*' . $order->readyOrderLabel() . '*',
            "No. Nota: {$order->invoice_number}",
            'Status: ' . ($order->usesDelivery() ? 'Siap Diantar' : 'Selesai'),
            'Total tagihan: ' . $this->formatRupiah($order->total_amount),
            '',
            $order->readyWhatsappDescription(),
            '',
            'Tracking order:',
            route('orders.track', $order->invoice_number),
            '',
            'Terima kasih.',
        ]);

        return $this->buildWhatsappUrl($order, $message);
    }

    private function whatsappBillingUrl(Order $order): string
    {
        $paid = min((int) $order->payments()->where('status', 'paid')->sum('amount_paid'), $order->total_amount);
        $remaining = max($order->total_amount - $paid, 0);
        $billingLines = [
            'Subtotal: ' . $this->formatRupiah($order->subtotal),
            'Diskon: ' . $this->formatRupiah($order->discount_amount),
        ];

        if ($order->pickup_delivery_fee > 0) {
            $billingLines[] = 'Pickup/Delivery: ' . $this->formatRupiah($order->pickup_delivery_fee);
        }

        $billingLines = array_merge($billingLines, [
            'Total: ' . $this->formatRupiah($order->total_amount),
            'Sudah dibayar: ' . $this->formatRupiah($paid),
            'Sisa tagihan: ' . $this->formatRupiah($remaining),
        ]);

        $message = implode("\n", array_merge([
            "Halo Kak {$order->customer?->name},",
            '',
            '*Rincian Tagihan ZOLIX Shoe Care*',
            "No. Nota: {$order->invoice_number}",
            '',
        ], $billingLines, [
            '',
            'Silakan cek detail order dan pilihan pembayaran melalui link berikut:',
            route('orders.track', $order->invoice_number),
        ]));

        return $this->buildWhatsappUrl($order, $message);
    }

    private function buildWhatsappUrl(Order $order, string $message): string
    {
        $phone = preg_replace('/\D+/', '', $order->customer?->phone ?? '');

        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }

    private function statusClass(string $status): string
    {
        return match ($status) {
            'proses' => 'proses',
            'selesai' => 'selesai',
            'diambil' => 'diambil',
            'menunggu_diambil' => 'menunggu',
            'dibatalkan' => 'dibatalkan',
            default => 'diterima',
        };
    }

    private function isOverdue(Order $order): bool
    {
        return $order->estimated_finished_at?->isPast()
            && ! in_array($order->status, ['diambil', 'dibatalkan'], true);
    }

    private function deadlineLabel(Order $order): string
    {
        if (! $order->estimated_finished_at) {
            return 'Belum dijadwalkan';
        }

        if ($this->isOverdue($order)) {
            return 'Terlambat';
        }

        if ($order->estimated_finished_at->isToday()) {
            return 'Deadline hari ini';
        }

        return 'Terjadwal';
    }

    private function formatRupiah(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

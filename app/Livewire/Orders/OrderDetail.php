<?php

namespace App\Livewire\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\AutoGopayClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Throwable;

class OrderDetail extends Component
{
    use WithFileUploads;

    public Order $order;

    public string $selectedStatus = '';

    public string $qrisAmount = '';

    public string $amountPaid = '';

    public string $method = 'cash';

    public string $referenceNumber = '';

    public string $paymentNotes = '';

    public array $afterPhotos = [];

    public ?string $success = null;

    public function mount(Order $order): void
    {
        $this->order = $order;
        $this->refreshOrder();
        $this->selectedStatus = $this->order->status;
        $this->method = $this->order->payment?->method ?? $this->order->payment_method ?? 'cash';
        $this->qrisAmount = (string) $this->remainingPayment();
        $this->amountPaid = (string) $this->remainingPayment();
    }

    public function updateStatus(): void
    {
        $validated = $this->validate([
            'selectedStatus' => ['required', 'string', 'in:diterima,proses,selesai,menunggu_diambil,diambil,dibatalkan'],
        ], [
            'selectedStatus.required' => 'Status order wajib dipilih.',
            'selectedStatus.in' => 'Status order tidak valid.',
        ]);

        $status = $validated['selectedStatus'];
        $updates = ['status' => $status];

        if ($status === 'selesai') {
            $updates['finished_at'] = now();
            $updates['status'] = 'menunggu_diambil';
        }

        if ($status === 'diambil') {
            $updates['picked_up_at'] = now();
        }

        $this->order->update($updates);

        $timelineStatus = $status === 'selesai' ? 'menunggu_diambil' : $status;
        $this->order->timelines()->create([
            'status' => $timelineStatus,
            'label' => $this->statusLabel($timelineStatus),
            'description' => $status === 'selesai' ? 'Order selesai dan siap diambil pelanggan.' : $this->timelineDescription($status),
            'logged_at' => now(),
            'created_by' => auth()->id(),
        ]);

        $this->success = 'Status order berhasil diperbarui.';
        $this->refreshOrder();
        $this->selectedStatus = $this->order->status;
        $this->dispatch('refresh-icons');
    }

    public function uploadAfterPhoto(int $itemId): void
    {
        $item = $this->order->items()->whereKey($itemId)->firstOrFail();

        $this->validate([
            "afterPhotos.{$itemId}" => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ], [
            "afterPhotos.{$itemId}.required" => 'Foto after wajib dipilih.',
            "afterPhotos.{$itemId}.image" => 'Foto after harus berupa gambar.',
            "afterPhotos.{$itemId}.mimes" => 'Foto after harus berformat JPG, JPEG, PNG, atau WEBP.',
            "afterPhotos.{$itemId}.max" => 'Ukuran foto after maksimal 5MB.',
            "afterPhotos.{$itemId}.dimensions" => 'Resolusi foto after maksimal 6000x6000 piksel.',
        ]);

        if ($item->after_photo_path) {
            Storage::disk('public')->delete($item->after_photo_path);
        }

        $item->update([
            'after_photo_path' => $this->afterPhotos[$itemId]->store('order-photos/after', 'public'),
        ]);

        $this->order->timelines()->create([
            'status' => 'foto_after',
            'label' => 'Foto after diupload',
            'description' => "Foto hasil pengerjaan untuk {$item->item_name} berhasil ditambahkan.",
            'logged_at' => now(),
            'created_by' => auth()->id(),
        ]);

        unset($this->afterPhotos[$itemId]);
        $this->success = 'Foto after berhasil diupload.';
        $this->refreshOrder();
        $this->dispatch('refresh-icons');
    }

    public function recordPayment(): void
    {
        $validated = $this->validate([
            'amountPaid' => ['required', 'string', 'max:50'],
            'method' => ['required', 'string', 'in:cash,transfer,qris,card,other'],
            'referenceNumber' => ['nullable', 'string', 'max:120'],
            'paymentNotes' => ['nullable', 'string', 'max:1000'],
        ], [
            'amountPaid.required' => 'Nominal pembayaran wajib diisi.',
            'method.required' => 'Metode pembayaran wajib dipilih.',
            'method.in' => 'Metode pembayaran tidak valid.',
        ]);

        $currentPaid = (int) $this->order->payments()->where('status', 'paid')->sum('amount_paid');
        $remaining = max($this->order->total_amount - $currentPaid, 0);
        $amountPaid = min($this->moneyToInteger($validated['amountPaid']), $remaining);

        if ($amountPaid <= 0) {
            $this->addError('amountPaid', 'Nominal pembayaran harus lebih dari Rp 0 dan order belum boleh lunas.');
            return;
        }

        $totalPaid = min($currentPaid + $amountPaid, $this->order->total_amount);
        $paymentStatus = $this->paymentStatusFromAmount($totalPaid, $this->order->total_amount);

        DB::transaction(function () use ($validated, $amountPaid, $totalPaid, $paymentStatus): void {
            $this->order->payments()->create([
                'amount_paid' => $amountPaid,
                'method' => $validated['method'],
                'status' => 'paid',
                'reference_number' => $validated['referenceNumber'] ?? null,
                'paid_at' => now(),
                'recorded_by' => auth()->id(),
                'notes' => $validated['paymentNotes'] ?? null,
            ]);

            $this->order->update([
                'payment_status' => $paymentStatus,
                'payment_method' => $validated['method'],
            ]);

            $this->order->timelines()->create([
                'status' => 'pembayaran',
                'label' => 'Pembayaran diperbarui',
                'description' => 'Pembayaran masuk ' . $this->formatRupiah($amountPaid) . '. Total terbayar ' . $this->formatRupiah($totalPaid) . ' (' . $this->paymentStatusLabel($paymentStatus) . ').',
                'logged_at' => now(),
                'created_by' => auth()->id(),
            ]);
        });

        $this->referenceNumber = '';
        $this->paymentNotes = '';
        $this->success = 'Pembayaran berhasil diperbarui.';
        $this->refreshOrder();
        $this->qrisAmount = (string) $this->remainingPayment();
        $this->amountPaid = (string) $this->remainingPayment();
        $this->dispatch('refresh-icons');
    }

    public function deletePayment(int $paymentId): void
    {
        $payment = $this->order->payments()->whereKey($paymentId)->firstOrFail();

        DB::transaction(function () use ($payment): void {
            $amount = $payment->amount_paid;
            $payment->delete();
            $this->syncPaymentStatus();

            $this->order->timelines()->create([
                'status' => 'pembayaran',
                'label' => 'Pembayaran dikoreksi',
                'description' => 'Transaksi pembayaran ' . $this->formatRupiah($amount) . ' dihapus dari riwayat.',
                'logged_at' => now(),
                'created_by' => auth()->id(),
            ]);
        });

        $this->success = 'Transaksi pembayaran berhasil dihapus.';
        $this->refreshOrder();
        $this->amountPaid = (string) $this->remainingPayment();
        $this->dispatch('refresh-icons');
    }

    public function verifyPaymentRequest(int $paymentId): void
    {
        $payment = $this->order->payments()
            ->whereKey($paymentId)
            ->where('status', 'pending')
            ->whereNull('provider')
            ->firstOrFail();

        if ($payment->method === 'transfer' && ! $payment->proof_photo_path) {
            $this->addError('payment', 'Bukti transfer belum diupload customer.');
            return;
        }

        $currentPaid = (int) $this->order->payments()->where('status', 'paid')->sum('amount_paid');
        $remaining = max($this->order->total_amount - $currentPaid, 0);
        $amountPaid = min($payment->requested_amount ?: $remaining, $remaining);

        if ($amountPaid <= 0) {
            $this->addError('payment', 'Order sudah lunas atau nominal verifikasi tidak valid.');
            return;
        }

        DB::transaction(function () use ($payment, $amountPaid): void {
            $payment->update([
                'amount_paid' => $amountPaid,
                'requested_amount' => $payment->requested_amount ?: $amountPaid,
                'status' => 'paid',
                'paid_at' => now(),
                'recorded_by' => auth()->id(),
                'notes' => trim(($payment->notes ? $payment->notes . ' ' : '') . 'Diverifikasi admin.'),
            ]);

            $this->syncPaymentStatus();

            $this->order->timelines()->create([
                'status' => 'pembayaran',
                'label' => 'Pembayaran diverifikasi',
                'description' => 'Pembayaran ' . $this->formatRupiah($amountPaid) . ' via ' . $this->paymentMethods()[$payment->method] . ' diverifikasi admin.',
                'logged_at' => now(),
                'created_by' => auth()->id(),
            ]);
        });

        $this->success = 'Pembayaran customer berhasil diverifikasi.';
        $this->refreshOrder();
        $this->dispatch('refresh-icons');
    }

    public function generateQris(AutoGopayClient $client): void
    {
        $this->expireStaleQris();

        $remaining = $this->remainingPayment();

        if ($remaining <= 0) {
            $this->addError('qris', 'Order sudah lunas.');
            return;
        }

        $activePendingQris = $this->order->payments()
            ->where('provider', 'autogopay')
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();

        if ($activePendingQris) {
            $this->success = 'QRIS aktif sudah tersedia.';
            $this->refreshOrder();
            return;
        }

        $validated = $this->validate([
            'qrisAmount' => ['nullable', 'string', 'max:50'],
        ]);

        $amount = min(max($this->moneyToInteger($validated['qrisAmount'] ?? $remaining), 1), $remaining);
        $orderId = $this->order->invoice_number . '-QRIS-' . now()->format('YmdHis');

        try {
            $response = $client->generateQris(['amount' => $amount]);
        } catch (RequestException $exception) {
            report($exception);
            $message = $exception->response?->json('message')
                ?? $exception->response?->body()
                ?? $exception->getMessage();
            $this->addError('qris', 'Gagal membuat QRIS AutoGopay: ' . str($message)->limit(180));
            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('qris', 'Gagal membuat QRIS AutoGopay: ' . str($exception->getMessage())->limit(180));
            return;
        }

        $data = $this->responseData($response);
        $transactionId = $data['transaction_id'] ?? $data['trx_id'] ?? $data['id'] ?? null;

        if (! $transactionId) {
            $this->addError('qris', 'Response AutoGopay tidak memiliki transaction_id.');
            return;
        }

        $payment = $this->order->payments()->create([
            'amount_paid' => 0,
            'requested_amount' => $amount,
            'method' => 'qris',
            'provider' => 'autogopay',
            'status' => 'pending',
            'reference_number' => $transactionId,
            'provider_transaction_id' => $transactionId,
            'provider_order_id' => $data['order_id'] ?? $orderId,
            'qr_string' => $this->qrStringFromData($data),
            'qr_url' => $this->qrUrlFromData($data),
            'expires_at' => isset($data['expiry_time'])
                ? now()->parse($data['expiry_time'])
                : (isset($data['expires_at']) ? now()->parse($data['expires_at']) : now()->addMinutes(15)),
            'recorded_by' => auth()->id(),
            'notes' => 'QRIS AutoGopay dibuat untuk ' . $this->formatRupiah($amount),
        ]);

        $this->order->timelines()->create([
            'status' => 'qris',
            'label' => 'QRIS dibuat',
            'description' => 'QRIS AutoGopay dibuat untuk pembayaran ' . $this->formatRupiah($amount) . '.',
            'logged_at' => now(),
            'created_by' => auth()->id(),
        ]);

        $this->success = "QRIS berhasil dibuat: {$payment->provider_transaction_id}";
        $this->refreshOrder();
        $this->dispatch('refresh-icons');
    }

    public function checkQris(int $paymentId, AutoGopayClient $client): void
    {
        $payment = $this->order->payments()
            ->whereKey($paymentId)
            ->where('provider', 'autogopay')
            ->firstOrFail();

        if ($this->isExpiredPendingQris($payment)) {
            $payment->update(['status' => 'expired']);
            $this->addError('qris', 'QRIS sudah expired. Buat QRIS baru untuk melanjutkan pembayaran.');
            $this->refreshOrder();
            return;
        }

        try {
            $response = $client->checkStatus((string) $payment->provider_transaction_id);
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('qris', 'Gagal mengecek status QRIS.');
            return;
        }

        $this->applyProviderStatus($payment, $this->responseData($response), auth()->id());

        $this->success = 'Status QRIS berhasil diperbarui.';
        $this->refreshOrder();
        $this->dispatch('refresh-icons');
    }

    public function cancelQris(int $paymentId, AutoGopayClient $client): void
    {
        $payment = $this->order->payments()
            ->whereKey($paymentId)
            ->where('provider', 'autogopay')
            ->firstOrFail();

        if ($payment->status === 'paid') {
            $this->addError('qris', 'QRIS yang sudah dibayar tidak bisa dibatalkan.');
            return;
        }

        try {
            $client->cancel((string) $payment->provider_transaction_id);
        } catch (Throwable $exception) {
            report($exception);
        }

        $payment->update(['status' => 'cancelled']);

        $this->success = 'QRIS berhasil dibatalkan.';
        $this->refreshOrder();
        $this->dispatch('refresh-icons');
    }

    public function render(): View
    {
        $this->expireStaleQris();
        $this->refreshOrder();

        return view('livewire.orders.order-detail', [
            'statuses' => $this->statusOptions(),
            'paymentMethods' => $this->paymentMethods(),
            'amountPaid' => min($this->order->payments->where('status', 'paid')->sum('amount_paid'), $this->order->total_amount),
            'remainingPayment' => $this->remainingPayment(),
            'pendingQrisPayments' => $this->pendingQrisPayments(),
            'expiredQrisPayments' => $this->expiredQrisPayments(),
            'pendingManualPayments' => $this->pendingManualPayments(),
            'whatsappUrl' => $this->whatsappUrl(),
            'whatsappReminderUrl' => $this->whatsappReminderUrl(),
            'whatsappReadyPickupUrl' => $this->whatsappReadyPickupUrl(),
            'whatsappBillingUrl' => $this->whatsappBillingUrl(),
        ]);
    }

    private function refreshOrder(): void
    {
        $this->order = $this->order->fresh(['customer', 'items.service', 'payment', 'payments', 'timelines']);
    }

    private function pendingQrisPayments()
    {
        return $this->order->payments
            ->where('provider', 'autogopay')
            ->where('status', 'pending')
            ->filter(fn (Payment $payment): bool => ! $payment->expires_at || $payment->expires_at->isFuture())
            ->sortByDesc('created_at');
    }

    private function expiredQrisPayments()
    {
        return $this->order->payments
            ->where('provider', 'autogopay')
            ->filter(fn (Payment $payment): bool => $payment->status === 'expired' || ($payment->status === 'pending' && $payment->expires_at && $payment->expires_at->isPast()))
            ->sortByDesc('created_at');
    }

    private function pendingManualPayments()
    {
        return $this->order->payments
            ->whereNull('provider')
            ->where('status', 'pending')
            ->sortByDesc('created_at');
    }

    private function remainingPayment(): int
    {
        $paid = min((int) $this->order->payments()->where('status', 'paid')->sum('amount_paid'), $this->order->total_amount);

        return max($this->order->total_amount - $paid, 0);
    }

    private function applyProviderStatus(Payment $payment, array $data, ?int $userId): void
    {
        $status = strtolower((string) ($data['status'] ?? $data['transaction_status'] ?? 'pending'));
        $isPaid = in_array($status, ['paid', 'success', 'settlement', 'settled', 'completed'], true);
        $isCancelled = in_array($status, ['cancelled', 'canceled', 'cancel', 'expired', 'expire', 'failed'], true);

        DB::transaction(function () use ($payment, $data, $userId, $isPaid, $isCancelled): void {
            $order = $payment->order()->lockForUpdate()->firstOrFail();
            $amount = (int) ($data['amount'] ?? $data['paid_amount'] ?? $payment->requested_amount);

            if ($isPaid) {
                $payment->update([
                    'amount_paid' => min($amount, $payment->requested_amount ?: $amount),
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                $this->syncPaymentStatus();

                $order->timelines()->create([
                    'status' => 'pembayaran',
                    'label' => 'QRIS dibayar',
                    'description' => 'Pembayaran QRIS AutoGopay diterima sebesar ' . $this->formatRupiah($payment->fresh()->amount_paid) . '.',
                    'logged_at' => now(),
                    'created_by' => $userId,
                ]);

                return;
            }

            if ($isCancelled) {
                $payment->update(['status' => 'cancelled']);
                return;
            }

            $payment->update(['status' => 'pending']);
        });
    }

    private function syncPaymentStatus(): void
    {
        $paid = min((int) $this->order->payments()->where('status', 'paid')->sum('amount_paid'), $this->order->total_amount);
        $latestPayment = $this->order->payments()->where('status', 'paid')->latest('paid_at')->first();

        $this->order->update([
            'payment_status' => $this->paymentStatusFromAmount($paid, $this->order->total_amount),
            'payment_method' => $latestPayment?->method ?? $this->order->payment_method,
        ]);
    }

    private function expireStaleQris(): void
    {
        $this->order->payments()
            ->where('provider', 'autogopay')
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    private function isExpiredPendingQris(Payment $payment): bool
    {
        return $payment->status === 'pending'
            && $payment->expires_at
            && $payment->expires_at->isPast();
    }

    private function responseData(array $response): array
    {
        return $response['data'] ?? $response;
    }

    private function qrStringFromData(array $data): ?string
    {
        $value = $this->firstStringValue($data, [
            'qr_string',
            'qris_string',
            'qr_code',
            'qrcode',
            'qr',
            'qris',
            'payload',
            'qr_payload',
        ]);

        if (! $value || str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:image')) {
            return null;
        }

        return $value;
    }

    private function qrUrlFromData(array $data): ?string
    {
        $value = $this->firstStringValue($data, [
            'qr_url',
            'qris_url',
            'image_url',
            'qr_image',
            'qris_image',
            'image',
            'url',
            'payment_url',
        ]);

        if (! $value) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:image')) {
            return $value;
        }

        return null;
    }

    private function firstStringValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                return trim($data[$key]);
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $nestedValue = $this->firstStringValue($value, $keys);

                if ($nestedValue) {
                    return $nestedValue;
                }
            }
        }

        return null;
    }

    private function moneyToInteger(string|int|null $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $numeric = preg_replace('/[^\d]/', '', (string) $value);

        return $numeric === '' ? 0 : (int) $numeric;
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

    private function pickupDeliveryTypeLabel(): string
    {
        return match ($this->order->pickup_delivery_type ?? 'none') {
            'pickup' => 'Ongkos Pickup',
            'delivery' => 'Ongkos Delivery',
            'pickup_delivery' => 'Ongkos Pickup + Delivery',
            default => 'Pickup / Delivery',
        };
    }

    private function whatsappUrl(): string
    {
        return $this->buildWhatsappUrl("Halo Kak {$this->order->customer?->name}, order sepatu {$this->order->invoice_number} statusnya {$this->statusLabel($this->order->status)}. Total: {$this->formatRupiah($this->order->total_amount)}. Tracking: " . route('orders.track', $this->order->invoice_number) . '. Terima kasih telah menggunakan ZOLIX Shoe Care.');
    }

    private function whatsappReminderUrl(): string
    {
        $estimate = $this->order->estimated_finished_at?->format('d M Y H:i') . ' WITA';

        return $this->buildWhatsappUrl("Halo Kak {$this->order->customer?->name}, kami informasikan order {$this->order->invoice_number} masih dalam penanganan dan melewati estimasi {$estimate}. Tim ZOLIX sedang memprioritaskan order Kakak. Tracking: " . route('orders.track', $this->order->invoice_number));
    }

    private function whatsappReadyPickupUrl(): string
    {
        return $this->buildWhatsappUrl("Halo Kak {$this->order->customer?->name}, order sepatu {$this->order->invoice_number} sudah selesai dan siap diambil. Total tagihan: {$this->formatRupiah($this->order->total_amount)}. Tracking: " . route('orders.track', $this->order->invoice_number) . '. Terima kasih.');
    }

    private function whatsappBillingUrl(): string
    {
        $paid = min((int) $this->order->payments()->where('status', 'paid')->sum('amount_paid'), $this->order->total_amount);
        $remaining = max($this->order->total_amount - $paid, 0);
        $pickupDelivery = $this->order->pickup_delivery_fee > 0
            ? ' Ongkos pickup/delivery: ' . $this->formatRupiah($this->order->pickup_delivery_fee) . '.'
            : '';

        return $this->buildWhatsappUrl("Halo Kak {$this->order->customer?->name}, tagihan order {$this->order->invoice_number}: subtotal {$this->formatRupiah($this->order->subtotal)}, diskon {$this->formatRupiah($this->order->discount_amount)}.{$pickupDelivery} Total {$this->formatRupiah($this->order->total_amount)}, sudah dibayar {$this->formatRupiah($paid)}, sisa {$this->formatRupiah($remaining)}. Tracking: " . route('orders.track', $this->order->invoice_number));
    }

    private function buildWhatsappUrl(string $message): string
    {
        $phone = preg_replace('/\D+/', '', $this->order->customer?->phone ?? '');

        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }

    private function formatRupiah(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

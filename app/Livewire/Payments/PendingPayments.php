<?php

namespace App\Livewire\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Services\AutoGopayClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class PendingPayments extends Component
{
    use WithPagination;

    public string $method = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->method = (string) request('method', '');
    }

    public function setMethod(string $method = ''): void
    {
        $this->method = $method;
        $this->resetPage();
        $this->clearMessages();
    }

    public function verify(int $paymentId): void
    {
        $this->clearMessages();

        $payment = Payment::with('order')->findOrFail($paymentId);
        $order = $payment->order;

        if (! $order || $payment->status !== 'pending' || $payment->provider !== null || ! in_array($payment->method, ['transfer', 'cash'], true)) {
            $this->error = 'Pembayaran tidak valid untuk diverifikasi.';
            return;
        }

        if ($payment->method === 'transfer' && ! $payment->proof_photo_path) {
            $this->error = 'Bukti transfer belum diupload customer.';
            return;
        }

        $currentPaid = (int) $order->payments()->where('status', 'paid')->sum('amount_paid');
        $remaining = max($order->total_amount - $currentPaid, 0);
        $amountPaid = min($payment->requested_amount ?: $remaining, $remaining);

        if ($amountPaid <= 0) {
            $this->error = 'Order sudah lunas atau nominal verifikasi tidak valid.';
            return;
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

            $this->syncOrderPayment($order);

            $order->timelines()->create([
                'status' => 'pembayaran',
                'label' => 'Pembayaran diverifikasi',
                'description' => 'Pembayaran ' . $this->formatRupiah($amountPaid) . ' via ' . $this->paymentMethodLabel($payment->method) . ' diverifikasi admin.',
                'logged_at' => now(),
                'created_by' => auth()->id(),
            ]);
        });

        $this->success = 'Pembayaran customer berhasil diverifikasi.';
    }

    public function checkQris(int $paymentId): void
    {
        $this->clearMessages();

        $payment = Payment::with('order')->findOrFail($paymentId);

        if ($payment->provider !== 'autogopay') {
            $this->error = 'Pembayaran ini bukan QRIS AutoGopay.';
            return;
        }

        if ($this->isExpiredPendingQris($payment)) {
            $payment->update(['status' => 'expired']);
            $this->error = 'QRIS sudah expired. Buat QRIS baru untuk melanjutkan pembayaran.';
            return;
        }

        try {
            $response = app(AutoGopayClient::class)->checkStatus((string) $payment->provider_transaction_id);
        } catch (Throwable $exception) {
            report($exception);
            $this->error = 'Gagal mengecek status QRIS.';
            return;
        }

        $this->applyProviderStatus($payment, $response['data'] ?? $response);
        $this->success = $payment->fresh()->status === 'paid'
            ? 'Pembayaran QRIS sudah diterima.'
            : 'Status QRIS berhasil diperbarui.';
    }

    public function regenerateQris(int $paymentId): void
    {
        $this->clearMessages();

        $payment = Payment::with('order')->findOrFail($paymentId);
        $order = $payment->order;

        if (! $order || $payment->method !== 'qris' || $payment->status !== 'expired') {
            $this->error = 'QRIS tidak valid untuk dibuat ulang.';
            return;
        }

        $paid = min((int) $order->payments()->where('status', 'paid')->sum('amount_paid'), $order->total_amount);
        $remaining = max($order->total_amount - $paid, 0);

        if ($remaining <= 0) {
            $this->error = 'Order sudah lunas.';
            return;
        }

        $activeQris = $order->payments()
            ->where('provider', 'autogopay')
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($activeQris) {
            $this->success = 'QRIS aktif sudah tersedia.';
            return;
        }

        try {
            $response = app(AutoGopayClient::class)->generateQris(['amount' => $remaining]);
        } catch (Throwable $exception) {
            report($exception);
            $this->error = 'Gagal membuat QRIS AutoGopay: ' . str($exception->getMessage())->limit(160);
            return;
        }

        $data = $response['data'] ?? $response;
        $transactionId = $data['transaction_id'] ?? $data['trx_id'] ?? $data['id'] ?? null;

        if (! $transactionId) {
            $this->error = 'Response AutoGopay tidak memiliki transaction_id.';
            return;
        }

        $order->payments()->create([
            'amount_paid' => 0,
            'requested_amount' => $remaining,
            'method' => 'qris',
            'provider' => 'autogopay',
            'status' => 'pending',
            'reference_number' => $transactionId,
            'provider_transaction_id' => $transactionId,
            'provider_order_id' => $data['order_id'] ?? ($order->invoice_number . '-QRIS-' . now()->format('YmdHis')),
            'qr_string' => $this->qrStringFromData($data),
            'qr_url' => $this->qrUrlFromData($data),
            'expires_at' => isset($data['expiry_time'])
                ? Carbon::parse($data['expiry_time'])
                : (isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : now()->addMinutes(15)),
            'recorded_by' => auth()->id(),
            'notes' => 'QRIS AutoGopay dibuat ulang untuk ' . $this->formatRupiah($remaining),
        ]);

        $order->timelines()->create([
            'status' => 'qris',
            'label' => 'QRIS dibuat ulang',
            'description' => 'QRIS AutoGopay dibuat ulang untuk pembayaran ' . $this->formatRupiah($remaining) . '.',
            'logged_at' => now(),
            'created_by' => auth()->id(),
        ]);

        $this->success = 'QRIS berhasil dibuat ulang.';
    }

    public function render(): View
    {
        $query = Payment::with(['order.customer'])
            ->whereHas('order')
            ->latest();

        if ($this->method === 'expired') {
            $query->where('status', 'expired')->where('method', 'qris');
        } else {
            $query->where('status', 'pending');
        }

        if (in_array($this->method, ['qris', 'transfer', 'cash'], true)) {
            $query->where('method', $this->method);
        }

        return view('livewire.payments.pending-payments', [
            'payments' => $query->paginate(15),
            'summary' => $this->summary(),
        ]);
    }

    private function clearMessages(): void
    {
        $this->success = null;
        $this->error = null;
    }

    private function summary(): array
    {
        return [
            'all' => Payment::where('status', 'pending')->count(),
            'qris' => Payment::where('status', 'pending')->where('method', 'qris')->count(),
            'transfer' => Payment::where('status', 'pending')->where('method', 'transfer')->count(),
            'transfer_with_proof' => Payment::where('status', 'pending')->where('method', 'transfer')->whereNotNull('proof_photo_path')->count(),
            'cash' => Payment::where('status', 'pending')->where('method', 'cash')->count(),
            'expired_qris' => Payment::where('status', 'expired')->where('method', 'qris')->count(),
        ];
    }

    private function applyProviderStatus(Payment $payment, array $data): void
    {
        $status = strtolower((string) ($data['status'] ?? $data['transaction_status'] ?? 'pending'));
        $isPaid = in_array($status, ['paid', 'success', 'settlement', 'settled', 'completed'], true);
        $isCancelled = in_array($status, ['cancelled', 'canceled', 'cancel', 'expired', 'expire', 'failed'], true);

        DB::transaction(function () use ($payment, $data, $isPaid, $isCancelled): void {
            $order = $payment->order()->lockForUpdate()->firstOrFail();
            $amount = (int) ($data['amount'] ?? $data['paid_amount'] ?? $payment->requested_amount);

            if ($isPaid) {
                $payment->update([
                    'amount_paid' => min($amount, $payment->requested_amount ?: $amount),
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                $this->syncOrderPayment($order);

                $order->timelines()->create([
                    'status' => 'pembayaran',
                    'label' => 'QRIS dibayar',
                    'description' => 'Pembayaran QRIS AutoGopay diterima sebesar ' . $this->formatRupiah($payment->fresh()->amount_paid) . '.',
                    'logged_at' => now(),
                    'created_by' => auth()->id(),
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

    private function syncOrderPayment(Order $order): void
    {
        $paid = min((int) $order->payments()->where('status', 'paid')->sum('amount_paid'), $order->total_amount);
        $latestPayment = $order->payments()->where('status', 'paid')->latest('paid_at')->first();

        $order->update([
            'payment_status' => $paid <= 0 ? 'unpaid' : ($paid >= $order->total_amount ? 'paid' : 'partial'),
            'payment_method' => $latestPayment?->method ?? $order->payment_method,
        ]);
    }

    private function isExpiredPendingQris(Payment $payment): bool
    {
        return $payment->status === 'pending'
            && $payment->expires_at
            && $payment->expires_at->isPast();
    }

    private function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'transfer' => 'Transfer Manual',
            'cash' => 'Cash di Outlet',
            'qris' => 'QRIS AutoGopay',
            default => ucfirst($method),
        };
    }

    private function qrStringFromData(array $data): ?string
    {
        return $this->firstStringValue($data, ['qr_string', 'qris_string', 'qr_code', 'qrcode', 'qr', 'qris', 'payload', 'qr_payload'], false);
    }

    private function qrUrlFromData(array $data): ?string
    {
        return $this->firstStringValue($data, ['qr_url', 'qris_url', 'image_url', 'qr_image', 'qris_image', 'image', 'url', 'payment_url'], true);
    }

    private function firstStringValue(array $data, array $keys, bool $urlOnly): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                $value = trim($data[$key]);
                $isUrl = str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:image');

                if ($urlOnly === $isUrl) {
                    return $value;
                }
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $nestedValue = $this->firstStringValue($value, $keys, $urlOnly);

                if ($nestedValue) {
                    return $nestedValue;
                }
            }
        }

        return null;
    }

    private function formatRupiah(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

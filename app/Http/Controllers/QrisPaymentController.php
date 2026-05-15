<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\AutoGopayClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class QrisPaymentController extends Controller
{
    public function generate(Request $request, Order $order, AutoGopayClient $client): RedirectResponse
    {
        return $this->createQris($request, $order, $client, true);
    }

    public function publicGenerate(Request $request, string $invoiceNumber, AutoGopayClient $client): RedirectResponse
    {
        $order = Order::where('invoice_number', $invoiceNumber)->firstOrFail();

        return $this->createQris($request, $order, $client, false);
    }

    private function createQris(Request $request, Order $order, AutoGopayClient $client, bool $allowCustomAmount): RedirectResponse
    {
        $this->expireStaleQris($order);

        $paid = $this->paidAmount($order);
        $remaining = max($order->total_amount - $paid, 0);

        if ($remaining <= 0) {
            return back()->withErrors(['qris' => 'Order sudah lunas.']);
        }

        $activePendingQris = $order->payments()
            ->where('provider', 'autogopay')
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();

        if ($activePendingQris) {
            return back()
                ->with('success', 'QRIS aktif sudah tersedia.')
                ->withFragment('tracking-qris-section');
        }

        $validated = $request->validate([
            'amount' => ['nullable', 'string', 'max:50'],
        ]);

        $amount = $allowCustomAmount ? $this->moneyToInteger($validated['amount'] ?? $remaining) : $remaining;
        $amount = min(max($amount, 1), $remaining);
        $orderId = $order->invoice_number . '-QRIS-' . now()->format('YmdHis');

        try {
            $response = $client->generateQris([
                'amount' => $amount,
            ]);
        } catch (RequestException $exception) {
            report($exception);

            $message = $exception->response?->json('message')
                ?? $exception->response?->body()
                ?? $exception->getMessage();

            return back()->withErrors(['qris' => 'Gagal membuat QRIS AutoGopay: ' . str($message)->limit(180)]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['qris' => 'Gagal membuat QRIS AutoGopay: ' . str($exception->getMessage())->limit(180)]);
        }

        $data = $this->responseData($response);
        $transactionId = $data['transaction_id'] ?? $data['trx_id'] ?? $data['id'] ?? null;

        if (! $transactionId) {
            return back()->withErrors(['qris' => 'Response AutoGopay tidak memiliki transaction_id.']);
        }

        $payment = $order->payments()->create([
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
                ? Carbon::parse($data['expiry_time'])
                : (isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : now()->addMinutes(15)),
            'recorded_by' => $request->user()?->id,
            'notes' => 'QRIS AutoGopay dibuat untuk ' . $this->formatRupiah($amount),
        ]);

        $order->timelines()->create([
            'status' => 'qris',
            'label' => 'QRIS dibuat',
            'description' => 'QRIS AutoGopay dibuat untuk pembayaran ' . $this->formatRupiah($amount) . '.',
            'logged_at' => now(),
            'created_by' => $request->user()?->id,
        ]);

        return back()
            ->with('success', "QRIS berhasil dibuat: {$payment->provider_transaction_id}")
            ->withFragment('tracking-qris-section');
    }

    public function check(Order $order, Payment $payment, AutoGopayClient $client): RedirectResponse
    {
        abort_unless($payment->order_id === $order->id && $payment->provider === 'autogopay', 404);

        if ($this->isExpiredPendingQris($payment)) {
            $payment->update(['status' => 'expired']);

            return back()->withErrors(['qris' => 'QRIS sudah expired. Buat QRIS baru untuk melanjutkan pembayaran.']);
        }

        try {
            $response = $client->checkStatus((string) $payment->provider_transaction_id);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['qris' => 'Gagal mengecek status QRIS.']);
        }

        $this->applyProviderStatus($payment, $this->responseData($response), auth()->id());

        return back()->with('success', 'Status QRIS berhasil diperbarui.');
    }

    public function publicCheck(string $invoiceNumber, Payment $payment, AutoGopayClient $client): RedirectResponse
    {
        $order = Order::where('invoice_number', $invoiceNumber)->firstOrFail();

        abort_unless($payment->order_id === $order->id && $payment->provider === 'autogopay', 404);

        if ($this->isExpiredPendingQris($payment)) {
            $payment->update(['status' => 'expired']);

            return back()
                ->withErrors(['qris' => 'QRIS sudah expired. Silakan buat QRIS baru.'])
                ->withFragment('tracking-qris-section');
        }

        try {
            $response = $client->checkStatus((string) $payment->provider_transaction_id);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['qris' => 'Gagal mengecek status QRIS. Coba lagi beberapa saat.']);
        }

        $this->applyProviderStatus($payment, $this->responseData($response), null);

        $message = $payment->fresh()->status === 'paid'
            ? 'Pembayaran QRIS sudah diterima.'
            : 'Pembayaran belum diterima. Silakan cek kembali setelah pembayaran berhasil.';

        return back()
            ->with('success', $message)
            ->withFragment('tracking-qris-section');
    }

    public function cancel(Order $order, Payment $payment, AutoGopayClient $client): RedirectResponse
    {
        abort_unless($payment->order_id === $order->id && $payment->provider === 'autogopay', 404);
        abort_if($payment->status === 'paid', 422, 'QRIS yang sudah dibayar tidak bisa dibatalkan.');

        try {
            $client->cancel((string) $payment->provider_transaction_id);
        } catch (Throwable $exception) {
            report($exception);
        }

        $payment->update(['status' => 'cancelled']);

        return back()->with('success', 'QRIS berhasil dibatalkan.');
    }

    public function webhook(Request $request, AutoGopayClient $client): JsonResponse
    {
        if (! $client->isValidSignature($request->getContent(), $request->header('X-Signature'))) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $data = $request->json()->all();

        if (($data['event'] ?? null) === 'verification.challenge' || ($data['type'] ?? null) === 'verification') {
            return response()->json(['success' => true]);
        }

        $transaction = $data['transaction'] ?? $data;
        $transactionId = $transaction['transaction_id'] ?? $transaction['trx_id'] ?? $transaction['id'] ?? null;

        if (! $transactionId) {
            return response()->json(['message' => 'Missing transaction_id'], 422);
        }

        $payment = Payment::where('provider', 'autogopay')
            ->where('provider_transaction_id', $transactionId)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $this->applyProviderStatus($payment, $transaction, null);

        return response()->json(['message' => 'OK']);
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

                $this->syncOrderPayment($order);

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

    private function syncOrderPayment(Order $order): void
    {
        $paid = $this->paidAmount($order);
        $latestPayment = $order->payments()->where('status', 'paid')->latest('paid_at')->first();

        $order->update([
            'payment_status' => $paid <= 0 ? 'unpaid' : ($paid >= $order->total_amount ? 'paid' : 'partial'),
            'payment_method' => $latestPayment?->method ?? $order->payment_method,
        ]);
    }

    private function expireStaleQris(Order $order): void
    {
        $expiredPayments = $order->payments()
            ->where('provider', 'autogopay')
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredPayments as $payment) {
            $payment->update(['status' => 'expired']);
        }
    }

    private function isExpiredPendingQris(Payment $payment): bool
    {
        return $payment->status === 'pending'
            && $payment->expires_at
            && $payment->expires_at->isPast();
    }

    private function paidAmount(Order $order): int
    {
        return min((int) $order->payments()->where('status', 'paid')->sum('amount_paid'), $order->total_amount);
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

    private function formatRupiah(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

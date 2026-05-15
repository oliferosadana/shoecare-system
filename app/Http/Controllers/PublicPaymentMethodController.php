<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\AutoGopayClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PublicPaymentMethodController extends Controller
{
    public function store(Request $request, string $invoiceNumber, AutoGopayClient $client): RedirectResponse
    {
        $order = Order::where('invoice_number', $invoiceNumber)->firstOrFail();

        $validated = $request->validate([
            'method' => ['required', 'string', 'in:cash,transfer'],
        ]);

        $paid = min((int) $order->payments()->where('status', 'paid')->sum('amount_paid'), $order->total_amount);
        $remaining = max($order->total_amount - $paid, 0);

        if ($remaining <= 0) {
            return back()->withErrors(['payment' => 'Order sudah lunas.']);
        }

        $method = $validated['method'];
        $label = $method === 'cash' ? 'Cash di Outlet' : 'Transfer Manual';
        $cancelledCount = $this->cancelOtherPendingMethods($order, $method, $client);

        $existingRequest = $order->payments()
            ->whereNull('provider')
            ->where('method', $method)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $existingRequest) {
            $order->payments()->create([
                'amount_paid' => 0,
                'requested_amount' => $remaining,
                'method' => $method,
                'status' => 'pending',
                'reference_number' => 'CUSTOMER-' . strtoupper($method) . '-' . now()->format('YmdHis'),
                'notes' => 'Customer memilih metode pembayaran ' . $label . ' untuk sisa tagihan ' . $this->formatRupiah($remaining) . '.',
            ]);

            $order->timelines()->create([
                'status' => 'payment_method',
                'label' => 'Metode pembayaran dipilih',
                'description' => 'Customer memilih metode pembayaran ' . $label . ' untuk sisa tagihan ' . $this->formatRupiah($remaining) . '.',
                'logged_at' => now(),
            ]);
        } else {
            $existingRequest->update([
                'requested_amount' => $remaining,
                'notes' => 'Customer tetap memilih metode pembayaran ' . $label . ' untuk sisa tagihan ' . $this->formatRupiah($remaining) . '.',
            ]);
        }

        if ($cancelledCount > 0) {
            $order->timelines()->create([
                'status' => 'payment_method_changed',
                'label' => 'Metode pembayaran diganti',
                'description' => 'Metode pembayaran sebelumnya otomatis dibatalkan karena customer memilih ' . $label . '.',
                'logged_at' => now(),
            ]);
        }

        return back()->with('success', 'Metode pembayaran dipilih: ' . $label . '.');
    }

    public function uploadProof(Request $request, string $invoiceNumber, Payment $payment): RedirectResponse
    {
        $order = Order::where('invoice_number', $invoiceNumber)->firstOrFail();

        abort_unless($payment->order_id === $order->id && $payment->method === 'transfer' && $payment->status === 'pending', 404);

        $validated = $request->validate([
            'proof_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'proof_photo.required' => 'Bukti transfer wajib diupload.',
            'proof_photo.uploaded' => 'Bukti transfer gagal diupload. Pastikan ukuran file maksimal 8 MB dan koneksi stabil.',
            'proof_photo.image' => 'Bukti transfer harus berupa gambar.',
            'proof_photo.mimes' => 'Format bukti transfer harus JPG, JPEG, PNG, atau WEBP.',
            'proof_photo.max' => 'Ukuran bukti transfer maksimal 8 MB.',
        ]);

        if ($payment->proof_photo_path) {
            Storage::disk('public')->delete($payment->proof_photo_path);
        }

        $path = $validated['proof_photo']->store('payment-proofs', 'public');

        $payment->update([
            'proof_photo_path' => $path,
            'notes' => trim(($payment->notes ? $payment->notes . ' ' : '') . 'Bukti transfer sudah diupload customer.'),
        ]);

        $order->timelines()->create([
            'status' => 'payment_proof',
            'label' => 'Bukti transfer diupload',
            'description' => 'Customer mengupload bukti transfer untuk verifikasi admin.',
            'logged_at' => now(),
        ]);

        return back()->with('success', 'Bukti transfer berhasil diupload. Admin akan memverifikasi pembayaran.');
    }

    private function formatRupiah(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    private function cancelOtherPendingMethods(Order $order, string $selectedMethod, AutoGopayClient $client): int
    {
        $payments = $order->payments()
            ->where('status', 'pending')
            ->where(function ($query) use ($selectedMethod) {
                $query
                    ->where('provider', 'autogopay')
                    ->orWhere(function ($manualQuery) use ($selectedMethod) {
                        $manualQuery
                            ->whereNull('provider')
                            ->where('method', '!=', $selectedMethod);
                    });
            })
            ->get();

        foreach ($payments as $payment) {
            if ($payment->provider === 'autogopay' && $payment->provider_transaction_id) {
                try {
                    $client->cancel((string) $payment->provider_transaction_id);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            $payment->update(['status' => 'cancelled']);
        }

        return $payments->count();
    }
}

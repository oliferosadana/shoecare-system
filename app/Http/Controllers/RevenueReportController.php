<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class RevenueReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $startDate = $request->date('start_date')?->startOfDay() ?? now()->subDays(29)->startOfDay();
        $endDate = $request->date('end_date')?->endOfDay() ?? now()->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        $orders = Order::with(['customer', 'payments'])
            ->whereBetween('received_at', [$startDate, $endDate])
            ->latest('received_at')
            ->get();

        $activeOrders = $orders->where('status', '!=', 'dibatalkan');
        $paidAmount = (int) $orders->sum(fn (Order $order): int => min($order->payments->where('status', 'paid')->sum('amount_paid'), $order->total_amount));
        $grossRevenue = (int) $activeOrders->sum('total_amount');
        $outstanding = max($grossRevenue - $paidAmount, 0);

        $methodSummary = $orders
            ->flatMap(fn (Order $order) => $order->payments->where('status', 'paid'))
            ->groupBy(fn ($payment): string => $payment->method ?? 'other')
            ->map(fn ($payments, string $method): array => [
                'method' => $this->paymentMethodLabel($method),
                'count' => $payments->count(),
                'amount' => (int) $payments->sum('amount_paid'),
            ])
            ->values();

        return view('reports.revenue', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'orders' => $orders,
            'methodSummary' => $methodSummary,
            'summary' => [
                'gross_revenue' => $grossRevenue,
                'paid_amount' => $paidAmount,
                'outstanding' => $outstanding,
                'discount' => (int) $orders->sum('discount_amount'),
                'cancelled' => (int) $orders->where('status', 'dibatalkan')->sum('total_amount'),
                'orders_count' => $orders->count(),
                'paid_count' => $orders->where('payment_status', 'paid')->count(),
                'partial_count' => $orders->where('payment_status', 'partial')->count(),
                'unpaid_count' => $orders->where('payment_status', 'unpaid')->count(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $startDate = $request->date('start_date')?->startOfDay() ?? now()->subDays(29)->startOfDay();
        $endDate = $request->date('end_date')?->endOfDay() ?? now()->endOfDay();
        $orders = Order::with(['customer', 'payments'])->whereBetween('received_at', [$startDate, $endDate])->latest('received_at')->get();
        $filename = 'laporan-pendapatan-' . $startDate->format('Ymd') . '-' . $endDate->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($orders): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nota', 'Tanggal', 'Pelanggan', 'Total', 'Dibayar', 'Sisa', 'Status Pembayaran', 'Status Order']);

            foreach ($orders as $order) {
                $paid = min($order->payments->where('status', 'paid')->sum('amount_paid'), $order->total_amount);
                fputcsv($handle, [
                    $order->invoice_number,
                    $order->received_at->format('Y-m-d H:i'),
                    $order->customer?->name,
                    $order->total_amount,
                    $paid,
                    max($order->total_amount - $paid, 0),
                    $order->payment_status,
                    $order->status,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Cash',
            'transfer' => 'Transfer',
            'qris' => 'QRIS',
            'card' => 'Kartu',
            default => 'Lainnya',
        };
    }
}

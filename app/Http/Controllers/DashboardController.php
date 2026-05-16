<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $orders = Order::with(['customer', 'items.service', 'payments'])->latest('received_at')->get();
        $statusCounts = $orders->countBy('status');
        $currentPeriodStart = now()->subDays(6)->startOfDay();
        $previousPeriodStart = now()->subDays(13)->startOfDay();
        $previousPeriodEnd = now()->subDays(7)->endOfDay();
        $currentOrders = $orders->filter(fn (Order $order): bool => $order->received_at->greaterThanOrEqualTo($currentPeriodStart));
        $previousOrders = $orders->filter(fn (Order $order): bool => $order->received_at->between($previousPeriodStart, $previousPeriodEnd));
        $recentOrders = $orders->take(5)->map(fn (Order $order): array => $this->orderForFrontend($order))->values();
        $popularServices = Service::withCount('orderItems')
            ->orderByDesc('order_items_count')
            ->take(5)
            ->get();
        $chart = $this->chartData($orders);
        $revenueByStatus = $this->revenueByStatus($orders);
        $paidRevenue = (int) $orders->sum(fn (Order $order): int => min($order->payments->where('status', 'paid')->sum('amount_paid'), $order->total_amount));
        $bookedRevenue = (int) $orders->where('status', '!=', 'dibatalkan')->sum('total_amount');

        return view('dashboard', [
            'stats' => [
                'total_orders' => $orders->count(),
                'proses' => (int) ($statusCounts['proses'] ?? 0),
                'selesai' => (int) ($statusCounts['selesai'] ?? 0),
                'diambil' => (int) ($statusCounts['diambil'] ?? 0),
                'menunggu_diambil' => (int) ($statusCounts['menunggu_diambil'] ?? 0),
                'overdue' => $orders->filter(fn (Order $order): bool => $this->isOverdue($order))->count(),
                'revenue' => $bookedRevenue,
                'paid_revenue' => $paidRevenue,
                'outstanding_revenue' => max($bookedRevenue - $paidRevenue, 0),
                'new_customers' => (int) $orders->pluck('customer_id')->unique()->count(),
            ],
            'trends' => [
                'total_orders' => $this->trendText($currentOrders->count(), $previousOrders->count()),
                'proses' => $this->trendText($currentOrders->where('status', 'proses')->count(), $previousOrders->where('status', 'proses')->count()),
                'selesai' => $this->trendText($currentOrders->whereIn('status', ['selesai', 'menunggu_diambil'])->count(), $previousOrders->whereIn('status', ['selesai', 'menunggu_diambil'])->count()),
                'diambil' => $this->trendText($currentOrders->where('status', 'diambil')->count(), $previousOrders->where('status', 'diambil')->count()),
                'menunggu_diambil' => $this->trendText($currentOrders->where('status', 'menunggu_diambil')->count(), $previousOrders->where('status', 'menunggu_diambil')->count()),
                'revenue' => $this->trendText($currentOrders->where('status', '!=', 'dibatalkan')->sum('total_amount'), $previousOrders->where('status', '!=', 'dibatalkan')->sum('total_amount')),
                'new_customers' => $this->trendText($currentOrders->pluck('customer_id')->unique()->count(), $previousOrders->pluck('customer_id')->unique()->count()),
            ],
            'chart' => $chart,
            'revenueByStatus' => $revenueByStatus,
            'recentOrdersForView' => $recentOrders,
            'popularServices' => $popularServices,
            'statusCounts' => $statusCounts,
        ]);
    }

    private function chartData($orders): array
    {
        $days = collect(range(6, 0))->map(fn (int $offset) => now()->subDays($offset)->startOfDay());
        $series = [
            'total' => [],
            'selesai' => [],
            'proses' => [],
            'diambil' => [],
            'menunggu_diambil' => [],
        ];

        foreach ($days as $day) {
            $dayOrders = $orders->filter(fn (Order $order): bool => $order->received_at->isSameDay($day));

            $series['total'][] = $dayOrders->count();
            $series['selesai'][] = $dayOrders->whereIn('status', ['selesai', 'menunggu_diambil'])->count();
            $series['proses'][] = $dayOrders->where('status', 'proses')->count();
            $series['diambil'][] = $dayOrders->where('status', 'diambil')->count();
            $series['menunggu_diambil'][] = $dayOrders->where('status', 'menunggu_diambil')->count();
        }

        $max = max(1, collect($series)->flatten()->max());

        return [
            'days' => $days->map(fn (Carbon $day): string => $day->translatedFormat('d M'))->all(),
            'points' => collect($series)->map(fn (array $values): string => $this->polylinePoints($values, $max))->all(),
        ];
    }

    private function polylinePoints(array $values, int $max): string
    {
        $xStep = 720 / max(count($values) - 1, 1);

        return collect($values)
            ->map(fn (int $value, int $index): string => round(20 + ($index * $xStep)) . ',' . round(186 - (($value / $max) * 132)))
            ->implode(' ');
    }

    private function revenueByStatus($orders): array
    {
        return collect(['selesai', 'diambil', 'proses', 'menunggu_diambil', 'dibatalkan'])
            ->mapWithKeys(fn (string $status): array => [$status => (int) $orders->where('status', $status)->sum('total_amount')])
            ->all();
    }

    private function trendText(int|float $current, int|float $previous): array
    {
        if ($previous <= 0) {
            return [
                'class' => $current > 0 ? 'trend-up' : 'trend-warning',
                'text' => $current > 0 ? 'Naik dari periode sebelumnya' : 'Belum ada data periode lalu',
            ];
        }

        $percent = (($current - $previous) / $previous) * 100;

        return [
            'class' => $percent >= 0 ? 'trend-up' : 'trend-down',
            'text' => ($percent >= 0 ? 'Naik ' : 'Turun ') . number_format(abs($percent), 1, ',', '.') . '% dari 7 hari lalu',
        ];
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
            'amount' => 'Rp ' . number_format($order->total_amount, 0, ',', '.'),
            'qty' => $order->items->sum('quantity') . ' Pasang',
            'isOverdue' => $this->isOverdue($order),
            'deadlineLabel' => $this->deadlineLabel($order),
            'photoUrl' => $firstItem?->before_photo_path ? Storage::url($firstItem->before_photo_path) : null,
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
}

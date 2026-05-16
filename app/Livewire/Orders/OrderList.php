<?php

namespace App\Livewire\Orders;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class OrderList extends Component
{
    use WithPagination;

    public string $q = '';

    public string $status = '';

    public string $paymentStatus = '';

    public string $deadline = '';

    public string $quick = '';

    public function mount(): void
    {
        $this->q = (string) request('q', '');
        $this->status = (string) request('status', '');
        $this->paymentStatus = (string) request('payment_status', '');
        $this->deadline = (string) request('deadline', '');
        $this->quick = (string) request('quick', '');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['q', 'status', 'paymentStatus', 'deadline', 'quick'], true)) {
            $this->resetPage();
        }
    }

    public function setStatus(string $status = ''): void
    {
        $this->status = $status;
        $this->paymentStatus = '';
        $this->deadline = '';
        $this->quick = '';
        $this->resetPage();
    }

    public function setPaymentStatus(string $status): void
    {
        $this->paymentStatus = $status;
        $this->deadline = '';
        $this->quick = '';
        $this->resetPage();
    }

    public function setDeadline(string $deadline): void
    {
        $this->deadline = $deadline;
        $this->paymentStatus = '';
        $this->quick = '';
        $this->resetPage();
    }

    public function setQuick(string $quick): void
    {
        $this->quick = $quick;
        $this->paymentStatus = '';
        $this->deadline = '';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->status = '';
        $this->paymentStatus = '';
        $this->deadline = '';
        $this->quick = '';
        $this->resetPage();
    }

    public function render(): View
    {
        $query = Order::with(['customer', 'items.service'])->latest('received_at');

        if ($this->q !== '') {
            $search = $this->q;

            $query->where(function ($builder) use ($search): void {
                $builder->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->paymentStatus !== '') {
            $query->where('payment_status', $this->paymentStatus);
        }

        if ($this->deadline === 'overdue') {
            $query->where('estimated_finished_at', '<', now())
                ->whereNotIn('status', ['diambil', 'dibatalkan']);
        }

        if ($this->quick === 'ready_pickup') {
            $query->where('status', 'menunggu_diambil');
        }

        $orders = $query->paginate(10);
        $statusCounts = Order::query()->get()->countBy(fn (Order $order): string => $this->statusLabel($order->status));

        return view('livewire.orders.order-list', [
            'orders' => $orders,
            'ordersForView' => $orders->getCollection()->map(fn (Order $order): array => $this->orderForFrontend($order))->values(),
            'statusCounts' => $statusCounts,
            'statusOptions' => $this->statusOptions(),
            'quickFilterCounts' => $this->quickFilterCounts(),
        ]);
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
            'estimate' => $order->estimated_finished_at?->translatedFormat('d M Y') ?? '-',
            'estimateTime' => $order->estimated_finished_at?->format('H:i') ?? '-',
            'isOverdue' => $this->isOverdue($order),
            'deadlineLabel' => $this->deadlineLabel($order),
            'photoUrl' => $firstItem?->before_photo_path ? Storage::url($firstItem->before_photo_path) : null,
            'showUrl' => route('orders.show', $order),
        ];
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

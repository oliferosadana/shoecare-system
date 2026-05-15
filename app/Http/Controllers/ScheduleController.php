<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __invoke(Request $request): View
    {
        $date = $request->date('date') ?? today();
        $orders = Order::with(['customer', 'items.service'])
            ->whereNotIn('status', ['diambil', 'dibatalkan'])
            ->whereDate('estimated_finished_at', $date)
            ->orderBy('estimated_finished_at')
            ->get();

        $overdueOrders = Order::with(['customer', 'items.service'])
            ->whereNotIn('status', ['diambil', 'dibatalkan'])
            ->where('estimated_finished_at', '<', now())
            ->orderBy('estimated_finished_at')
            ->get();

        $upcomingOrders = Order::with(['customer', 'items.service'])
            ->whereNotIn('status', ['diambil', 'dibatalkan'])
            ->whereDate('estimated_finished_at', '>', $date)
            ->orderBy('estimated_finished_at')
            ->take(8)
            ->get();

        return view('schedule.index', [
            'date' => $date,
            'orders' => $orders,
            'overdueOrders' => $overdueOrders,
            'upcomingOrders' => $upcomingOrders,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PendingPaymentController extends Controller
{
    public function __invoke(Request $request): View
    {
        $method = $request->string('method')->toString();

        $paymentsQuery = Payment::with(['order.customer'])
            ->whereHas('order')
            ->latest();

        if ($method === 'expired') {
            $paymentsQuery->where('status', 'expired')->where('method', 'qris');
        } else {
            $paymentsQuery->where('status', 'pending');
        }

        if (in_array($method, ['qris', 'transfer', 'cash'], true)) {
            $paymentsQuery->where('method', $method);
        }

        $payments = $paymentsQuery->paginate(15)->withQueryString();

        $summary = [
            'all' => Payment::where('status', 'pending')->count(),
            'qris' => Payment::where('status', 'pending')->where('method', 'qris')->count(),
            'transfer' => Payment::where('status', 'pending')->where('method', 'transfer')->count(),
            'transfer_with_proof' => Payment::where('status', 'pending')->where('method', 'transfer')->whereNotNull('proof_photo_path')->count(),
            'cash' => Payment::where('status', 'pending')->where('method', 'cash')->count(),
            'expired_qris' => Payment::where('status', 'expired')->where('method', 'qris')->count(),
        ];

        return view('payments.pending', [
            'payments' => $payments,
            'summary' => $summary,
            'activeMethod' => $method,
        ]);
    }
}

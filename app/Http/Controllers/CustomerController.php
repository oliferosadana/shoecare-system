<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $query = Customer::withCount('orders')
            ->withSum('orders', 'total_amount')
            ->latest();

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        return view('customers.index', [
            'customers' => $query->paginate(12)->withQueryString(),
            'totalCustomers' => Customer::count(),
            'repeatCustomers' => Customer::has('orders', '>=', 2)->count(),
        ]);
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'orders' => fn ($query) => $query->with(['items.service', 'payments'])->latest('received_at'),
        ]);

        return view('customers.show', [
            'customer' => $customer,
            'totalSpent' => (int) $customer->orders->where('status', '!=', 'dibatalkan')->sum('total_amount'),
            'totalPaid' => (int) $customer->orders->sum(fn ($order): int => min($order->payments->where('status', 'paid')->sum('amount_paid'), $order->total_amount)),
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', [
            'customer' => $customer,
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'unique:customers,phone,' . $customer->id],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'required' => ':attribute wajib diisi.',
            'email' => 'Format email tidak valid.',
            'unique' => ':attribute sudah digunakan pelanggan lain.',
        ], [
            'name' => 'Nama pelanggan',
            'phone' => 'No. WhatsApp',
            'email' => 'Email',
        ]);

        $customer->update($validated);

        return redirect()->route('customers.show', $customer)->with('success', 'Data pelanggan berhasil diperbarui.');
    }
}

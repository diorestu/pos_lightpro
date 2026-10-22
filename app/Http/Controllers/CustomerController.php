<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Customer::withCount('orders');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('customer_type')) {
            $query->where('customer_type', $type);
        }

        $perPage = min(max($request->integer('per_page', 15), 5), 100);
        $customers = $query->orderBy('name')->paginate($perPage)->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search', 'customer_type', 'per_page']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'customer_type' => 'required|string|in:regular,member,reseller,corporate',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        Customer::create($validated);

        return back()->with('success', "Pelanggan {$validated['name']} berhasil ditambahkan!");
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'customer_type' => 'required|string|in:regular,member,reseller,corporate',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $customer->update($validated);

        return back()->with('success', "Pelanggan {$customer->name} berhasil diperbarui!");
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->orders()->count() > 0) {
            return back()->with('error', "Pelanggan tidak dapat dihapus karena telah memiliki riwayat pesanan.");
        }

        $name = $customer->name;
        $customer->delete();

        return back()->with('success', "Pelanggan {$name} berhasil dihapus!");
    }
}

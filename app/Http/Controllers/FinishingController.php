<?php

namespace App\Http\Controllers;

use App\Models\Finishing;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinishingController extends Controller
{
    public function index(): Response
    {
        $finishings = Finishing::orderBy('name')->get()->map(function ($item) {
            $ordersCount = OrderItem::where('finishing_option', $item->name)->count();

            return [
                'id' => $item->id,
                'name' => $item->name,
                'price' => (float) $item->price,
                'description' => $item->description,
                'is_active' => (bool) $item->is_active,
                'orders_count' => $ordersCount,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        return Inertia::render('Finishings/Index', [
            'finishings' => $finishings,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Finishing::create($validated);

        return back()->with('success', "Finishing cetak {$validated['name']} berhasil ditambahkan!");
    }

    public function update(Request $request, Finishing $finishing): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $finishing->update($validated);

        return back()->with('success', "Finishing cetak {$finishing->name} berhasil diperbarui!");
    }

    public function destroy(Finishing $finishing): RedirectResponse
    {
        $ordersCount = OrderItem::where('finishing_option', $finishing->name)->count();

        if ($ordersCount > 0) {
            return back()->with('error', "Finishing {$finishing->name} tidak dapat dihapus karena pernah digunakan dalam {$ordersCount} transaksi cetak. Anda dapat menonaktifkan statusnya.");
        }

        $name = $finishing->name;
        $finishing->delete();

        return back()->with('success', "Finishing cetak {$name} berhasil dihapus!");
    }
}

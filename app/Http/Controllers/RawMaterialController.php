<?php

namespace App\Http\Controllers;

use App\Models\RawMaterial;
use App\Models\StockMutation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RawMaterialController extends Controller
{
    public function index(Request $request): Response
    {
        $query = RawMaterial::withCount('products');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('low_stock_only')) {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        $perPage = min(max($request->integer('per_page', 15), 5), 100);
        $rawMaterials = $query->orderBy('name')->paginate($perPage)->withQueryString();

        return Inertia::render('RawMaterials/Index', [
            'rawMaterials' => $rawMaterials,
            'filters' => $request->only(['search', 'low_stock_only', 'per_page']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:raw_materials,code',
            'unit' => 'required|string|max:50',
            'stock' => 'required|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $rawMaterial = RawMaterial::create($validated);

        if ($validated['stock'] > 0) {
            StockMutation::create([
                'item_type' => 'raw_material',
                'item_id' => $rawMaterial->id,
                'item_name' => $rawMaterial->name,
                'type' => 'in',
                'quantity' => $validated['stock'],
                'previous_stock' => 0,
                'current_stock' => $validated['stock'],
                'reference_type' => 'initial_stock',
                'reason' => 'Stok Awal Input Bahan Baku',
                'user_id' => $request->user()->id,
            ]);
        }

        return back()->with('success', "Bahan baku {$validated['name']} berhasil ditambahkan!");
    }

    public function update(Request $request, RawMaterial $rawMaterial): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:raw_materials,code,' . $rawMaterial->id,
            'unit' => 'required|string|max:50',
            'min_stock' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $rawMaterial->update($validated);

        return back()->with('success', "Bahan baku {$rawMaterial->name} berhasil diperbarui!");
    }

    public function destroy(RawMaterial $rawMaterial): RedirectResponse
    {
        if ($rawMaterial->products()->count() > 0) {
            return back()->with('error', "Bahan baku ini masih terhubung dengan {$rawMaterial->products()->count()} produk.");
        }

        $name = $rawMaterial->name;
        $rawMaterial->delete();

        return back()->with('success', "Bahan baku {$name} berhasil dihapus!");
    }
}

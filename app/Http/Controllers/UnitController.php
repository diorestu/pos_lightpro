<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function index(): Response
    {
        $units = Unit::orderBy('name')->get()->map(function ($unit) {
            $productCount = Product::where('unit', $unit->symbol)->count();
            $rawMaterialCount = RawMaterial::where('unit', $unit->symbol)->count();

            return [
                'id' => $unit->id,
                'name' => $unit->name,
                'symbol' => $unit->symbol,
                'description' => $unit->description,
                'is_active' => (bool) $unit->is_active,
                'products_count' => $productCount,
                'raw_materials_count' => $rawMaterialCount,
                'created_at' => $unit->created_at,
                'updated_at' => $unit->updated_at,
            ];
        });

        return Inertia::render('Settings/Units/Index', [
            'units' => $units,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:50|unique:units,symbol',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Unit::create($validated);

        return back()->with('success', "Satuan barang {$validated['name']} ({$validated['symbol']}) berhasil ditambahkan!");
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'symbol' => "required|string|max:50|unique:units,symbol,{$unit->id}",
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $unit->update($validated);

        return back()->with('success', "Satuan barang {$unit->name} berhasil diperbarui!");
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $productCount = Product::where('unit', $unit->symbol)->count();
        $rawMaterialCount = RawMaterial::where('unit', $unit->symbol)->count();

        if ($productCount > 0 || $rawMaterialCount > 0) {
            $usage = [];
            if ($productCount > 0) {
                $usage[] = "{$productCount} produk";
            }
            if ($rawMaterialCount > 0) {
                $usage[] = "{$rawMaterialCount} bahan baku";
            }
            return back()->with('error', "Satuan {$unit->name} ({$unit->symbol}) tidak dapat dihapus karena masih digunakan oleh " . implode(' dan ', $usage) . ".");
        }

        $name = $unit->name;
        $unit->delete();

        return back()->with('success', "Satuan barang {$name} berhasil dihapus!");
    }
}

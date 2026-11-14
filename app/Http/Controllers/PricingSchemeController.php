<?php

namespace App\Http\Controllers;

use App\Models\PricingScheme;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PricingSchemeController extends Controller
{
    public function index(): Response
    {
        $schemes = PricingScheme::orderBy('name')->get()->map(function ($scheme) {
            $productCount = Product::where('pricing_type', $scheme->code)->count();

            return [
                'id' => $scheme->id,
                'code' => $scheme->code,
                'name' => $scheme->name,
                'calculation_type' => $scheme->calculation_type,
                'default_unit' => $scheme->default_unit,
                'description' => $scheme->description,
                'is_active' => (bool) $scheme->is_active,
                'products_count' => $productCount,
                'created_at' => $scheme->created_at,
                'updated_at' => $scheme->updated_at,
            ];
        });

        $units = Unit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'symbol']);

        return Inertia::render('PricingSchemes/Index', [
            'schemes' => $schemes,
            'units' => $units,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|alpha_dash|unique:pricing_schemes,code',
            'name' => 'required|string|max:255',
            'calculation_type' => 'required|string|in:fixed,dimension_sqm',
            'default_unit' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ], [
            'code.unique' => 'Kode skema sudah digunakan.',
            'code.alpha_dash' => 'Kode skema hanya boleh berisi huruf, angka, garis bawah (_), dan strip (-).',
            'name.required' => 'Nama skema perhitungan wajib diisi.',
            'calculation_type.required' => 'Tipe kalkulasi wajib dipilih.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        PricingScheme::create($validated);

        return back()->with('success', "Skema perhitungan {$validated['name']} berhasil ditambahkan!");
    }

    public function update(Request $request, PricingScheme $pricingScheme): RedirectResponse
    {
        $validated = $request->validate([
            'code' => "required|string|max:50|alpha_dash|unique:pricing_schemes,code,{$pricingScheme->id}",
            'name' => 'required|string|max:255',
            'calculation_type' => 'required|string|in:fixed,dimension_sqm',
            'default_unit' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ], [
            'code.unique' => 'Kode skema sudah digunakan.',
            'code.alpha_dash' => 'Kode skema hanya boleh berisi huruf, angka, garis bawah (_), dan strip (-).',
            'name.required' => 'Nama skema perhitungan wajib diisi.',
            'calculation_type.required' => 'Tipe kalkulasi wajib dipilih.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        // If code changed, update existing products with old code
        if ($pricingScheme->code !== $validated['code']) {
            Product::where('pricing_type', $pricingScheme->code)->update(['pricing_type' => $validated['code']]);
        }

        $pricingScheme->update($validated);

        return back()->with('success', "Skema perhitungan {$pricingScheme->name} berhasil diperbarui!");
    }

    public function toggle(PricingScheme $pricingScheme): RedirectResponse
    {
        $pricingScheme->update([
            'is_active' => ! $pricingScheme->is_active,
        ]);

        $statusText = $pricingScheme->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Skema perhitungan {$pricingScheme->name} berhasil {$statusText}!");
    }

    public function destroy(PricingScheme $pricingScheme): RedirectResponse
    {
        $productsCount = Product::where('pricing_type', $pricingScheme->code)->count();

        if ($productsCount > 0) {
            return back()->with('error', "Skema {$pricingScheme->name} tidak dapat dihapus karena sedang digunakan oleh {$productsCount} produk. Anda dapat menonaktifkan statusnya.");
        }

        if (in_array($pricingScheme->code, ['fixed', 'dimension_sqm'])) {
            return back()->with('error', "Skema bawaan '{$pricingScheme->name}' tidak dapat dihapus, namun Anda dapat menonaktifkan statusnya.");
        }

        $name = $pricingScheme->name;
        $pricingScheme->delete();

        return back()->with('success', "Skema perhitungan {$name} berhasil dihapus!");
    }
}

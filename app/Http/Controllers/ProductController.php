<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Product::with(['category', 'rawMaterial']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($pricingType = $request->input('pricing_type')) {
            $query->where('pricing_type', $pricingType);
        }

        $perPage = min(max($request->integer('per_page', 15), 5), 100);
        $products = $query->orderBy('name')->paginate($perPage)->withQueryString();
        $categories = Category::orderBy('name')->get();
        $rawMaterials = RawMaterial::orderBy('name')->get();
        $units = Unit::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'rawMaterials' => $rawMaterials,
            'units' => $units,
            'filters' => $request->only(['search', 'category_id', 'pricing_type', 'per_page']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'raw_material_id' => 'nullable|exists:raw_materials,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:products,code',
            'pricing_type' => 'required|string|in:fixed,dimension_sqm',
            'unit' => 'required|string|max:50',
            'base_price' => 'required|numeric|min:0',
            'min_order' => 'required|numeric|min:0.01',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|numeric|min:0',
            'is_stock_managed' => 'boolean',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        Product::create($validated);

        return back()->with('success', "Produk {$validated['name']} berhasil ditambahkan!");
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'raw_material_id' => 'nullable|exists:raw_materials,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:products,code,' . $product->id,
            'pricing_type' => 'required|string|in:fixed,dimension_sqm',
            'unit' => 'required|string|max:50',
            'base_price' => 'required|numeric|min:0',
            'min_order' => 'required|numeric|min:0.01',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|numeric|min:0',
            'is_stock_managed' => 'boolean',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $product->update($validated);

        return back()->with('success', "Produk {$product->name} berhasil diperbarui!");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $product->delete();

        return back()->with('success', "Produk {$name} berhasil dihapus!");
    }
}

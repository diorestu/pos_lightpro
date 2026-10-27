<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockMutation;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $allProducts = Product::orderBy('name')->get(['id', 'name', 'code', 'stock', 'unit', 'pricing_type', 'is_stock_managed']);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'allProducts' => $allProducts,
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

    public function bulkStock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.type' => 'required|string|in:in,out,adjustment',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.reason' => 'nullable|string|max:255',
            'default_reason' => 'nullable|string|max:255',
        ], [
            'items.required' => 'Pilih minimal 1 produk untuk disesuaikan.',
            'items.min' => 'Pilih minimal 1 produk untuk disesuaikan.',
            'items.*.product_id.required' => 'Produk tidak valid.',
            'items.*.quantity.required' => 'Jumlah kuantitas wajib diisi.',
            'items.*.quantity.min' => 'Jumlah kuantitas minimal 0.01.',
        ]);

        $user = $request->user();
        $defaultReason = trim($validated['default_reason'] ?? '') ?: 'Penyesuaian Stok Massal';

        $updatedCount = DB::transaction(function () use ($validated, $user, $defaultReason) {
            $count = 0;

            foreach ($validated['items'] as $itemData) {
                $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);
                $oldStock = (float) $product->stock;
                $qty = (float) $itemData['quantity'];
                $type = $itemData['type'];
                $reason = trim($itemData['reason'] ?? '') ?: $defaultReason;

                if ($type === 'in') {
                    $newStock = $oldStock + $qty;
                } elseif ($type === 'out') {
                    $newStock = max(0, $oldStock - $qty);
                } else { // opname / adjustment
                    $newStock = max(0, $qty);
                }

                $product->update([
                    'stock' => $newStock,
                    'is_stock_managed' => true,
                ]);

                StockMutation::create([
                    'item_type' => 'product',
                    'item_id' => $product->id,
                    'item_name' => $product->name,
                    'type' => $type,
                    'quantity' => $qty,
                    'previous_stock' => $oldStock,
                    'current_stock' => $newStock,
                    'reference_type' => 'bulk_stock_adjustment',
                    'reason' => $reason,
                    'user_id' => $user->id,
                ]);

                $count++;
            }

            return $count;
        });

        return back()->with('success', "Stok untuk {$updatedCount} produk berhasil diperbarui!");
    }
}

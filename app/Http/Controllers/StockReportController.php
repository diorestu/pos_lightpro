<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockMutation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockReportController extends Controller
{
    public function index(Request $request): Response
    {
        $query = StockMutation::with('user');

        if ($itemType = $request->input('item_type')) {
            $query->where('item_type', $itemType);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $mutations = $query->latest()->paginate(15)->withQueryString();

        // Summaries
        $totalIn = StockMutation::where('type', 'in')->sum('quantity');
        $totalOut = StockMutation::where('type', 'out')->sum('quantity');

        $rawMaterials = RawMaterial::orderBy('name')->get();
        $products = Product::where('is_stock_managed', true)->orderBy('name')->get();

        return Inertia::render('StockReports/Index', [
            'mutations' => $mutations,
            'summary' => [
                'total_in' => (float) $totalIn,
                'total_out' => (float) $totalOut,
            ],
            'rawMaterials' => $rawMaterials,
            'products' => $products,
            'filters' => $request->only(['item_type', 'type', 'search', 'date_from', 'date_to']),
        ]);
    }

    public function adjust(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_type' => 'required|string|in:raw_material,product',
            'item_id' => 'required|integer',
            'type' => 'required|string|in:in,out,adjustment',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $item = null;
        $itemName = '';
        $oldStock = 0;
        $newStock = 0;
        $qty = (float) $validated['quantity'];

        if ($validated['item_type'] === 'raw_material') {
            $item = RawMaterial::findOrFail($validated['item_id']);
            $itemName = $item->name;
            $oldStock = (float) $item->stock;
        } else {
            $item = Product::findOrFail($validated['item_id']);
            $itemName = $item->name;
            $oldStock = (float) $item->stock;
        }

        if ($validated['type'] === 'in') {
            $newStock = $oldStock + $qty;
        } elseif ($validated['type'] === 'out') {
            $newStock = max(0, $oldStock - $qty);
        } else {
            // Opname adjustment (set directly to quantity)
            $newStock = $qty;
        }

        $item->update(['stock' => $newStock]);

        StockMutation::create([
            'item_type' => $validated['item_type'],
            'item_id' => $item->id,
            'item_name' => $itemName,
            'type' => $validated['type'],
            'quantity' => $qty,
            'previous_stock' => $oldStock,
            'current_stock' => $newStock,
            'reference_type' => 'manual_adjustment',
            'reason' => $validated['reason'],
            'user_id' => $user->id,
        ]);

        return back()->with('success', "Penyesuaian stok untuk {$itemName} berhasil disimpan! (Stok: {$oldStock} -> {$newStock})");
    }
}

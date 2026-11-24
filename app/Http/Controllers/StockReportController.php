<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockMutation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $perPage = min(max($request->integer('per_page', 15), 5), 100);
        $mutations = $query->latest()->paginate($perPage)->withQueryString();

        // Summaries
        $totalIn = StockMutation::where('type', 'in')->sum('quantity');
        $totalOut = StockMutation::where('type', 'out')->sum('quantity');

        $rawMaterials = RawMaterial::orderBy('name')->get();
        $products = Product::orderBy('name')->get(['id', 'name', 'code', 'stock', 'unit', 'pricing_type', 'is_stock_managed']);

        return Inertia::render('StockReports/Index', [
            'mutations' => $mutations,
            'summary' => [
                'total_in' => (float) $totalIn,
                'total_out' => (float) $totalOut,
            ],
            'rawMaterials' => $rawMaterials,
            'products' => $products,
            'filters' => $request->only(['item_type', 'type', 'search', 'date_from', 'date_to', 'per_page']),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
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

        $mutations = $query->latest()->get();

        $filename = 'laporan_mutasi_stok_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($mutations) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

            fputcsv($handle, [
                'No',
                'Tanggal & Waktu',
                'Tipe Barang',
                'Nama Barang',
                'Jenis Mutasi',
                'Jumlah Mutasi',
                'Stok Sebelum',
                'Stok Sesudah',
                'Referensi',
                'Keterangan / Alasan',
                'Petugas',
            ]);

            foreach ($mutations as $index => $item) {
                $itemTypeLabel = $item->item_type === 'raw_material' ? 'Bahan Baku' : 'Produk Jadi';
                $mutationType = $item->type === 'in' ? 'Masuk (+)' : 'Keluar (-)';

                fputcsv($handle, [
                    $index + 1,
                    $item->created_at ? $item->created_at->format('d/m/Y H:i') : '-',
                    $itemTypeLabel,
                    $item->item_name,
                    $mutationType,
                    $item->quantity,
                    $item->previous_stock,
                    $item->current_stock,
                    $item->reference_type ? "{$item->reference_type} #{$item->reference_id}" : '-',
                    $item->reason ?: '-',
                    $item->user?->name ?? 'Sistem',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
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

    public function bulkAdjust(Request $request): RedirectResponse
    {
        return app(ProductController::class)->bulkStock($request);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $deadlineFilter = $request->input('deadline', 'all'); // 'all', 'today', 'overdue', 'upcoming'
        $today = Carbon::today();

        $query = Order::with(['user', 'customer', 'items.product'])
            ->where('order_status', '!=', 'cancelled');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('items', function ($itemQ) use ($search) {
                        $itemQ->where('product_name', 'like', "%{$search}%")
                            ->orWhere('design_note', 'like', "%{$search}%")
                            ->orWhere('finishing_option', 'like', "%{$search}%");
                    });
            });
        }

        if ($deadlineFilter === 'today') {
            $query->whereDate('due_date', $today);
        } elseif ($deadlineFilter === 'overdue') {
            $query->whereDate('due_date', '<', $today)
                ->where('order_status', '!=', 'completed');
        } elseif ($deadlineFilter === 'upcoming') {
            $query->whereDate('due_date', '>', $today);
        }

        // Fetch all non-completed active orders + recent completed orders (last 24 hours)
        $orders = (clone $query)
            ->where(function ($q) use ($today) {
                $q->whereIn('order_status', ['pending', 'processing', 'finishing', 'ready'])
                    ->orWhere(function ($subQ) use ($today) {
                        $subQ->where('order_status', 'completed')
                            ->whereDate('updated_at', '>=', $today->copy()->subDays(1));
                    });
            })
            ->orderByRaw("
                CASE 
                    WHEN due_date IS NOT NULL AND due_date < '{$today->toDateString()}' AND order_status != 'completed' THEN 1
                    WHEN due_date IS NOT NULL AND due_date = '{$today->toDateString()}' AND order_status != 'completed' THEN 2
                    ELSE 3
                END ASC
            ")
            ->orderBy('created_at', 'asc')
            ->get();

        // Calculate statistics
        $stats = [
            'pending' => Order::where('order_status', 'pending')->count(),
            'processing' => Order::where('order_status', 'processing')->count(),
            'finishing' => Order::where('order_status', 'finishing')->count(),
            'ready' => Order::where('order_status', 'ready')->count(),
            'completed_today' => Order::where('order_status', 'completed')->whereDate('updated_at', $today)->count(),
            'overdue' => Order::where('order_status', '!=', 'completed')
                ->where('order_status', '!=', 'cancelled')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->count(),
        ];

        return Inertia::render('Production/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'deadline' => $deadlineFilter,
            ],
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'order_status' => 'required|string|in:pending,processing,finishing,ready,completed,cancelled',
        ]);

        $statusLabels = [
            'pending' => 'Menunggu Antrean',
            'processing' => 'Proses Cetak',
            'finishing' => 'Finishing',
            'ready' => 'Siap Diambil/Kirim',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
        ];

        $order->update([
            'order_status' => $validated['order_status'],
        ]);

        $label = $statusLabels[$validated['order_status']] ?? $validated['order_status'];

        return back()->with('success', "Status pengerjaan nota {$order->invoice_number} berhasil diubah ke: {$label}.");
    }
}

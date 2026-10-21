<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RawMaterial;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();

        // Key stats
        $todayRevenue = Order::whereDate('created_at', $today)
            ->where('order_status', '!=', 'cancelled')
            ->sum('grand_total');

        $todayOrdersCount = Order::whereDate('created_at', $today)
            ->where('order_status', '!=', 'cancelled')
            ->count();

        $monthRevenue = Order::where('created_at', '>=', $startOfMonth)
            ->where('order_status', '!=', 'cancelled')
            ->sum('grand_total');

        $monthOrdersCount = Order::where('created_at', '>=', $startOfMonth)
            ->where('order_status', '!=', 'cancelled')
            ->count();

        $pendingProductionCount = Order::whereIn('order_status', ['pending', 'processing'])->count();

        // Low stock raw materials
        $lowStockMaterials = RawMaterial::whereColumn('stock', '<=', 'min_stock')->get();
        $lowStockCount = $lowStockMaterials->count();

        // 7 Days sales chart
        $salesChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayRevenue = Order::whereDate('created_at', $date)
                ->where('order_status', '!=', 'cancelled')
                ->sum('grand_total');
            $salesChart[] = [
                'date' => $date->format('d M'),
                'revenue' => (float) $dayRevenue,
            ];
        }

        // Recent orders
        $recentOrders = Order::with(['user', 'customer', 'items'])
            ->latest()
            ->take(6)
            ->get();

        // Top products by quantity
        $topProducts = OrderItem::selectRaw('product_name, SUM(quantity) as total_qty, SUM(subtotal) as total_amount')
            ->groupBy('product_name')
            ->orderByDesc('total_amount')
            ->take(5)
            ->get();

        // Active shift
        $activeShift = CashierShift::with('user')
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        return Inertia::render('Dashboard', [
            'metrics' => [
                'today_revenue' => (float) $todayRevenue,
                'today_orders' => $todayOrdersCount,
                'month_revenue' => (float) $monthRevenue,
                'month_orders' => $monthOrdersCount,
                'pending_production' => $pendingProductionCount,
                'low_stock_count' => $lowStockCount,
            ],
            'lowStockMaterials' => $lowStockMaterials,
            'salesChart' => $salesChart,
            'recentOrders' => $recentOrders,
            'topProducts' => $topProducts,
            'activeShift' => $activeShift,
        ]);
    }
}

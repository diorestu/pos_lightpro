<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $period = $request->input('period', 'today'); // today, this_week, this_month, custom
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        [$from, $to] = $this->resolveDateRange($period, $startDate, $endDate);

        $ordersQuery = Order::whereBetween('created_at', [$from, $to])
            ->where('order_status', '!=', 'cancelled');

        $totalRevenue = (float) $ordersQuery->sum('grand_total');
        $totalOrders = $ordersQuery->count();
        $totalDiscounts = (float) $ordersQuery->sum('discount_amount');

        // Payment breakdown
        $paymentBreakdown = (clone $ordersQuery)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(grand_total) as total')
            ->groupBy('payment_method')
            ->get();

        // Payment status breakdown
        $paymentStatusBreakdown = (clone $ordersQuery)
            ->selectRaw('payment_status, COUNT(*) as count, SUM(grand_total) as total')
            ->groupBy('payment_status')
            ->get();

        // Estimated Cost & Profit
        $orderIds = (clone $ordersQuery)->pluck('id');
        $totalCost = (float) OrderItem::whereIn('order_id', $orderIds)->sum('cost_price');
        $grossProfit = $totalRevenue - $totalCost;
        $profitMargin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;

        // Daily trend
        $dailyTrend = (clone $ordersQuery)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders_count, SUM(grand_total) as revenue')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Top products
        $topProducts = OrderItem::whereIn('order_id', $orderIds)
            ->selectRaw('product_name, unit, SUM(quantity) as total_qty, SUM(subtotal) as total_revenue, SUM(cost_price) as total_cost')
            ->groupBy('product_name', 'unit')
            ->orderByDesc('total_revenue')
            ->take(10)
            ->get()
            ->map(function ($item) {
                $rev = (float) $item->total_revenue;
                $cost = (float) $item->total_cost;
                $item->profit = $rev - $cost;
                return $item;
            });

        return Inertia::render('Reports/Index', [
            'period' => $period,
            'startDate' => $from->toDateString(),
            'endDate' => $to->toDateString(),
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'total_cost' => $totalCost,
                'gross_profit' => $grossProfit,
                'profit_margin' => round($profitMargin, 1),
                'total_discounts' => $totalDiscounts,
                'avg_order_value' => $totalOrders > 0 ? round($totalRevenue / $totalOrders) : 0,
            ],
            'paymentBreakdown' => $paymentBreakdown,
            'paymentStatusBreakdown' => $paymentStatusBreakdown,
            'dailyTrend' => $dailyTrend,
            'topProducts' => $topProducts,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $period = $request->input('period', 'today');
        [$from, $to] = $this->resolveDateRange($period, $request->input('start_date'), $request->input('end_date'));

        $orders = Order::with(['user', 'customer', 'items'])
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"laporan_penjualan_{$from->format('Ymd')}_{$to->format('Ymd')}.csv\"",
        ];

        return response()->stream(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['No Invoice', 'Tanggal', 'Kasir', 'Pelanggan', 'Metode Bayar', 'Status Bayar', 'Status Pesanan', 'Subtotal', 'Diskon', 'Total']);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->invoice_number,
                    $order->created_at->format('d/m/Y H:i'),
                    $order->user->name ?? '-',
                    $order->customer_name ?? 'Umum',
                    strtoupper($order->payment_method),
                    strtoupper($order->payment_status),
                    strtoupper($order->order_status),
                    $order->subtotal,
                    $order->discount_amount,
                    $order->grand_total,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function resolveDateRange(string $period, ?string $start, ?string $end): array
    {
        switch ($period) {
            case 'this_week':
                return [Carbon::now()->startOfWeek()->startOfDay(), Carbon::now()->endOfWeek()->endOfDay()];
            case 'this_month':
                return [Carbon::now()->startOfMonth()->startOfDay(), Carbon::now()->endOfMonth()->endOfDay()];
            case 'custom':
                $from = $start ? Carbon::parse($start)->startOfDay() : Carbon::today()->startOfDay();
                $to = $end ? Carbon::parse($end)->endOfDay() : Carbon::today()->endOfDay();
                return [$from, $to];
            case 'today':
            default:
                return [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()];
        }
    }
}

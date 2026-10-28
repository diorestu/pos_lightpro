<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
        ],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AlertTriangle from '@lucide/svelte/icons/alert-triangle';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import BarChart3 from '@lucide/svelte/icons/bar-chart-3';
    import Boxes from '@lucide/svelte/icons/boxes';
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import Clock from '@lucide/svelte/icons/clock';
    import DollarSign from '@lucide/svelte/icons/dollar-sign';
    import Package from '@lucide/svelte/icons/package';
    import Printer from '@lucide/svelte/icons/printer';
    import ShoppingCart from '@lucide/svelte/icons/shopping-cart';
    import TrendingUp from '@lucide/svelte/icons/trending-up';
    import AppHead from '@/components/AppHead.svelte';
    import { formatStock } from '@/lib/utils';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
    import { createSortable } from '@/lib/sortable.svelte';

    interface MetricProps {
        today_revenue: number;
        today_orders: number;
        month_revenue: number;
        month_orders: number;
        pending_production: number;
        low_stock_count: number;
    }

    interface OrderItem {
        id: number;
        invoice_number: string;
        customer_name: string;
        grand_total: number;
        payment_method: string;
        payment_status: string;
        order_status: string;
        created_at: string;
        items?: any[];
    }

    let {
        metrics = {
            today_revenue: 0,
            today_orders: 0,
            month_revenue: 0,
            month_orders: 0,
            pending_production: 0,
            low_stock_count: 0,
        },
        lowStockMaterials = [],
        salesChart = [],
        recentOrders = [],
        topProducts = [],
        activeShift = null,
    }: {
        metrics: MetricProps;
        lowStockMaterials: any[];
        salesChart: { date: string; revenue: number }[];
        recentOrders: OrderItem[];
        topProducts: any[];
        activeShift: any;
    } = $props();

    const recentOrdersSortable = createSortable(() => recentOrders, 'id', 'desc');

    function formatRupiah(amount: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);
    }

    const maxChartRevenue = $derived(
        Math.max(...salesChart.map((s) => s.revenue), 100000)
    );
</script>

<AppHead title="Dashboard Percetakan" />

<div class="flex flex-col gap-6 p-4 md:p-6 max-w-7xl mx-auto w-full">
    <!-- Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b pb-5">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <Printer class="size-6 text-primary" />
                LightPrint POS Dashboard
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Sistem Point of Sale & Manajemen Produksi Percetakan Digital
            </p>
        </div>
        <div class="flex items-center gap-2.5">
            {#if activeShift}
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 text-xs font-medium">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    Shift Aktif: {activeShift.user?.name || 'Kasir'} (Kas: {formatRupiah(activeShift.expected_cash)})
                </div>
            {:else}
                <Link href="/shifts">
                    <Button variant="outline" size="sm" class="border-amber-400 text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/30">
                        <Clock class="size-3.5 mr-1.5" />
                        Shift Belum Dibuka
                    </Button>
                </Link>
            {/if}

            <Link href="/pos">
                <Button class="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold shadow-sm">
                    <ShoppingCart class="size-4 mr-1.5" />
                    Buka Kasir (POS)
                </Button>
            </Link>
        </div>
    </div>

    <!-- Alert Low Stock Notice -->
    {#if metrics.low_stock_count > 0}
        <div class="rounded-xl border border-amber-300 bg-amber-50/80 dark:border-amber-800 dark:bg-amber-950/30 p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-amber-900 dark:text-amber-200">
            <div class="flex items-center gap-3">
                <AlertTriangle class="size-5 text-amber-600 dark:text-amber-400 shrink-0" />
                <div>
                    <span class="font-semibold text-sm">Peringatan Stok Bahan Baku!</span>
                    <span class="text-xs text-amber-800 dark:text-amber-300 ml-1">
                        Ada {metrics.low_stock_count} jenis bahan baku yang mencapai batas minimum stok.
                    </span>
                </div>
            </div>
            <Link href="/raw-materials?low_stock_only=true">
                <Button size="sm" variant="outline" class="border-amber-300 text-amber-800 hover:bg-amber-100 bg-white dark:bg-amber-900/40 text-xs">
                    Periksa Bahan Baku
                    <ArrowRight class="size-3.5 ml-1" />
                </Button>
            </Link>
        </div>
    {/if}

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Hari Ini Omset -->
        <Card class="border shadow-xs">
            <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Omset Hari Ini</CardTitle>
                <div class="p-2 bg-emerald-100 dark:bg-emerald-950 text-emerald-600 rounded-lg">
                    <TrendingUp class="size-4" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-foreground">{formatRupiah(metrics.today_revenue)}</div>
                <p class="text-xs text-muted-foreground mt-1 flex items-center gap-1">
                    <CheckCircle2 class="size-3 text-emerald-500" />
                    {metrics.today_orders} transaksi selesai hari ini
                </p>
            </CardContent>
        </Card>

        <!-- Omset Bulan Ini -->
        <Card class="border shadow-xs">
            <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Omset Bulan Ini</CardTitle>
                <div class="p-2 bg-blue-100 dark:bg-blue-950 text-blue-600 rounded-lg">
                    <DollarSign class="size-4" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-foreground">{formatRupiah(metrics.month_revenue)}</div>
                <p class="text-xs text-muted-foreground mt-1">
                    {metrics.month_orders} total transaksi bulan berjalan
                </p>
            </CardContent>
        </Card>

        <!-- Antrian Produksi Cetak -->
        <Card class="border shadow-xs">
            <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Antrian Cetak</CardTitle>
                <div class="p-2 bg-amber-100 dark:bg-amber-950 text-amber-600 rounded-lg">
                    <Clock class="size-4" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-foreground">{metrics.pending_production} Pesanan</div>
                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1 font-medium">
                    Perlu diproses / sedang dicetak
                </p>
            </CardContent>
        </Card>

        <!-- Bahan Baku Peringatan -->
        <Card class="border shadow-xs">
            <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Bahan Baku Kritis</CardTitle>
                <div class="p-2 bg-purple-100 dark:bg-purple-950 text-purple-600 rounded-lg">
                    <Boxes class="size-4" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold {metrics.low_stock_count > 0 ? 'text-destructive' : 'text-foreground'}">
                    {metrics.low_stock_count} Item
                </div>
                <p class="text-xs text-muted-foreground mt-1">
                    {metrics.low_stock_count === 0 ? 'Semua stok dalam kondisi aman' : 'Perlu restock segera'}
                </p>
            </CardContent>
        </Card>
    </div>

    <!-- Chart & Top Products Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- 7-Day Sales Trend -->
        <Card class="lg:col-span-2 border shadow-xs">
            <CardHeader class="pb-3">
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="text-base font-semibold">Tren Penjualan 7 Hari Terakhir</CardTitle>
                        <CardDescription class="text-xs">Grafik pendapatan omset harian percetakan</CardDescription>
                    </div>
                    <Link href="/reports">
                        <Button variant="ghost" size="sm" class="text-xs">
                            Laporan Lengkap
                            <ArrowRight class="size-3.5 ml-1" />
                        </Button>
                    </Link>
                </div>
            </CardHeader>
            <CardContent>
                <div class="h-56 flex items-end gap-3 pt-6 pb-2 px-2">
                    {#each salesChart as bar}
                        {@const heightPercent = Math.max(8, Math.round((bar.revenue / maxChartRevenue) * 100))}
                        <div class="flex-1 flex flex-col items-center gap-2 group h-full justify-end">
                            <div class="text-[10px] font-semibold text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity">
                                {formatRupiah(bar.revenue)}
                            </div>
                            <div
                                class="w-full rounded-t-md bg-primary/85 group-hover:bg-primary transition-all duration-200"
                                style="height: {heightPercent}%;"
                            ></div>
                            <span class="text-[11px] text-muted-foreground font-medium truncate w-full text-center">
                                {bar.date}
                            </span>
                        </div>
                    {/each}
                </div>
            </CardContent>
        </Card>

        <!-- Top Selling Products -->
        <Card class="border shadow-xs flex flex-col">
            <CardHeader class="pb-3">
                <CardTitle class="text-base font-semibold">Produk Terlaris</CardTitle>
                <CardDescription class="text-xs">Berdasarkan total nominal penjualan</CardDescription>
            </CardHeader>
            <CardContent class="flex-1">
                {#if topProducts.length === 0}
                    <div class="h-44 flex items-center justify-center text-xs text-muted-foreground">
                        Belum ada data penjualan tercatat.
                    </div>
                {:else}
                    <div class="space-y-3.5">
                        {#each topProducts as item, i}
                            <div class="flex items-center justify-between text-xs pb-2 border-b last:border-0 last:pb-0">
                                <div class="flex items-center gap-2.5 truncate max-w-[190px]">
                                    <span class="size-5 rounded-full bg-muted flex items-center justify-center font-bold text-[10px] text-muted-foreground">
                                        {i + 1}
                                    </span>
                                    <span class="font-medium text-foreground truncate">{item.product_name}</span>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-foreground">{formatRupiah(item.total_amount)}</div>
                                    <div class="text-[10px] text-muted-foreground">{formatStock(item.total_qty)} terjual</div>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </CardContent>
        </Card>
    </div>

    <!-- Recent Orders Table -->
    <Card class="border shadow-xs">
        <CardHeader class="pb-3 flex flex-row items-center justify-between">
            <div>
                <CardTitle class="text-base font-semibold">Transaksi Terbaru</CardTitle>
                <CardDescription class="text-xs">Daftar transaksi nota POS terakhir yang masuk ke sistem</CardDescription>
            </div>
            <Link href="/orders">
                <Button variant="outline" size="sm" class="text-xs">
                    Lihat Semua Riwayat
                    <ArrowRight class="size-3.5 ml-1" />
                </Button>
            </Link>
        </CardHeader>
        <CardContent class="p-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="text-xs" sortable sortDirection={recentOrdersSortable.getDirection('invoice_number')} onSort={() => recentOrdersSortable.toggleSort('invoice_number')}>
                            No. Invoice
                        </TableHead>
                        <TableHead class="text-xs" sortable sortDirection={recentOrdersSortable.getDirection('customer_name')} onSort={() => recentOrdersSortable.toggleSort('customer_name')}>
                            Pelanggan
                        </TableHead>
                        <TableHead class="text-xs" sortable sortDirection={recentOrdersSortable.getDirection('payment_method')} onSort={() => recentOrdersSortable.toggleSort('payment_method')}>
                            Metode
                        </TableHead>
                        <TableHead class="text-xs" sortable sortDirection={recentOrdersSortable.getDirection('order_status')} onSort={() => recentOrdersSortable.toggleSort('order_status')}>
                            Status Pesanan
                        </TableHead>
                        <TableHead class="text-xs" sortable sortDirection={recentOrdersSortable.getDirection('payment_status')} onSort={() => recentOrdersSortable.toggleSort('payment_status')}>
                            Status Bayar
                        </TableHead>
                        <TableHead class="text-xs text-right" sortable sortDirection={recentOrdersSortable.getDirection('grand_total')} onSort={() => recentOrdersSortable.toggleSort('grand_total')}>
                            Total
                        </TableHead>
                        <TableHead class="text-xs text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {#if recentOrdersSortable.sorted.length === 0}
                        <TableRow>
                            <TableCell colspan={7} class="text-center py-6 text-muted-foreground text-xs">
                                Belum ada transaksi penjualan hari ini.
                            </TableCell>
                        </TableRow>
                    {:else}
                        {#each recentOrdersSortable.sorted as order}
                            <TableRow>
                                <TableCell class="font-medium text-xs">
                                    <Link href="/orders/{order.id}" class="hover:underline text-primary">
                                        {order.invoice_number}
                                    </Link>
                                </TableCell>
                                <TableCell class="text-xs">{order.customer_name || 'Pelanggan Umum'}</TableCell>
                                <TableCell class="text-xs">
                                    <span class="uppercase font-semibold text-[11px]">{order.payment_method}</span>
                                </TableCell>
                                <TableCell class="text-xs">
                                    {#if order.order_status === 'completed'}
                                        <Badge variant="outline" class="bg-emerald-50 text-emerald-700 border-emerald-200">Selesai</Badge>
                                    {:else if order.order_status === 'processing'}
                                        <Badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">Proses Cetak</Badge>
                                    {:else if order.order_status === 'ready'}
                                        <Badge variant="outline" class="bg-purple-50 text-purple-700 border-purple-200">Siap Ambil</Badge>
                                    {:else if order.order_status === 'pending'}
                                        <Badge variant="outline" class="bg-amber-50 text-amber-700 border-amber-200">Antrian</Badge>
                                    {:else if order.order_status === 'cancelled'}
                                        <Badge variant="destructive">Batal</Badge>
                                    {/if}
                                </TableCell>
                                <TableCell class="text-xs">
                                    {#if order.payment_status === 'paid'}
                                        <Badge class="bg-emerald-600 hover:bg-emerald-600 text-white">Lunas</Badge>
                                    {:else if order.payment_status === 'partial'}
                                        <Badge variant="secondary" class="bg-amber-100 text-amber-800">DP (Parsial)</Badge>
                                    {:else}
                                        <Badge variant="destructive">Belum Bayar</Badge>
                                    {/if}
                                </TableCell>
                                <TableCell class="text-xs text-right font-semibold">
                                    {formatRupiah(order.grand_total)}
                                </TableCell>
                                <TableCell class="text-xs text-right">
                                    <Link href="/orders/{order.id}">
                                        <Button size="sm" variant="ghost" class="h-7 px-2 text-xs">
                                            Detail & Cetak
                                        </Button>
                                    </Link>
                                </TableCell>
                            </TableRow>
                        {/each}
                    {/if}
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</div>

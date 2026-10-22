<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Laporan',
                href: '#',
            },
            {
                title: 'Laporan Penjualan',
                href: '/reports',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import BarChart3 from '@lucide/svelte/icons/bar-chart-3';
    import Calendar from '@lucide/svelte/icons/calendar';
    import CreditCard from '@lucide/svelte/icons/credit-card';
    import DollarSign from '@lucide/svelte/icons/dollar-sign';
    import Download from '@lucide/svelte/icons/download';
    import Package from '@lucide/svelte/icons/package';
    import Printer from '@lucide/svelte/icons/printer';
    import ShoppingBag from '@lucide/svelte/icons/shopping-bag';
    import TrendingUp from '@lucide/svelte/icons/trending-up';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
    import { createSortable } from '@/lib/sortable.svelte';

    interface ReportSummary {
        total_revenue: number;
        total_orders: number;
        total_cost: number;
        gross_profit: number;
        profit_margin: number;
        total_discounts: number;
        avg_order_value: number;
    }

    let {
        period = 'today',
        startDate = '',
        endDate = '',
        summary = {
            total_revenue: 0,
            total_orders: 0,
            total_cost: 0,
            gross_profit: 0,
            profit_margin: 0,
            total_discounts: 0,
            avg_order_value: 0,
        },
        paymentBreakdown = [],
        paymentStatusBreakdown = [],
        dailyTrend = [],
        topProducts = [],
    }: {
        period: string;
        startDate: string;
        endDate: string;
        summary: ReportSummary;
        paymentBreakdown: any[];
        paymentStatusBreakdown: any[];
        dailyTrend: any[];
        topProducts: any[];
    } = $props();

    // svelte-ignore state_referenced_locally
    let customStart = $state(startDate);
    // svelte-ignore state_referenced_locally
    let customEnd = $state(endDate);

    $effect(() => {
        customStart = startDate;
        customEnd = endDate;
    });

    const dailyTrendSortable = createSortable(() => dailyTrend, 'date', 'desc');
    const topProductsSortable = createSortable(() => topProducts, 'total_revenue', 'desc');

    function changePeriod(p: string) {
        router.get('/reports', { period: p });
    }

    function applyCustomDate() {
        router.get('/reports', {
            period: 'custom',
            start_date: customStart,
            end_date: customEnd,
        });
    }

    function formatRupiah(amount: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);
    }

    function exportCsv() {
        window.location.href = `/reports/export-csv?period=${period}&start_date=${customStart}&end_date=${customEnd}`;
    }

    function printReport() {
        window.print();
    }
</script>

<AppHead title="Laporan Penjualan & Keuntungan" />

<div class="flex flex-col gap-6 p-4 md:p-6 max-w-7xl mx-auto w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4 print:hidden">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <BarChart3 class="size-6 text-primary" />
                Laporan Penjualan & Keuntungan
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Analisis omset percetakan, estimasi HPP modal, laba kotor, dan metode pembayaran.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" class="text-xs" onclick={printReport}>
                <Printer class="size-3.5 mr-1.5" />
                Cetak Laporan
            </Button>
            <Button size="sm" class="bg-primary text-xs font-semibold" onclick={exportCsv}>
                <Download class="size-3.5 mr-1.5" />
                Export CSV
            </Button>
        </div>
    </div>

    <!-- Period Filter Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-muted/30 p-2.5 rounded-xl border print:hidden">
        <div class="flex items-center gap-1 overflow-x-auto">
            <button
                onclick={() => changePeriod('today')}
                class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition-colors {period === 'today' ? 'bg-primary text-primary-foreground shadow-xs' : 'bg-background hover:bg-muted text-muted-foreground'}"
            >
                Hari Ini (Harian)
            </button>
            <button
                onclick={() => changePeriod('this_week')}
                class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition-colors {period === 'this_week' ? 'bg-primary text-primary-foreground shadow-xs' : 'bg-background hover:bg-muted text-muted-foreground'}"
            >
                Minggu Ini (Mingguan)
            </button>
            <button
                onclick={() => changePeriod('this_month')}
                class="px-3.5 py-1.5 text-xs font-medium rounded-lg transition-colors {period === 'this_month' ? 'bg-primary text-primary-foreground shadow-xs' : 'bg-background hover:bg-muted text-muted-foreground'}"
            >
                Bulan Ini (Bulanan)
            </button>
        </div>

        <!-- Custom Range -->
        <div class="flex items-center gap-2 text-xs">
            <span class="text-muted-foreground hidden md:inline">Rentang:</span>
            <Input type="date" bind:value={customStart} class="h-8 text-xs w-36" />
            <span class="text-muted-foreground">-</span>
            <Input type="date" bind:value={customEnd} class="h-8 text-xs w-36" />
            <Button size="sm" variant="outline" class="h-8 text-xs" onclick={applyCustomDate}>
                Filter
            </Button>
        </div>
    </div>

    <!-- Period Label Banner -->
    <div class="text-xs text-muted-foreground font-medium flex items-center gap-1.5">
        <Calendar class="size-3.5 text-primary" />
        Periode Laporan: <strong>{new Date(startDate).toLocaleDateString('id-ID', { dateStyle: 'long' })}</strong> s/d <strong>{new Date(endDate).toLocaleDateString('id-ID', { dateStyle: 'long' })}</strong>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Omset -->
        <Card class="border shadow-xs">
            <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total Omset Penjualan</CardTitle>
                <div class="p-2 bg-emerald-100 dark:bg-emerald-950 text-emerald-600 rounded-lg">
                    <TrendingUp class="size-4" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-foreground">{formatRupiah(summary.total_revenue)}</div>
                <p class="text-xs text-muted-foreground mt-1">
                    {summary.total_orders} transaksi berhasil
                </p>
            </CardContent>
        </Card>

        <!-- Laba Kotor -->
        <Card class="border shadow-xs">
            <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Laba Kotor (Gross Profit)</CardTitle>
                <div class="p-2 bg-blue-100 dark:bg-blue-950 text-blue-600 rounded-lg">
                    <DollarSign class="size-4" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-emerald-600">{formatRupiah(summary.gross_profit)}</div>
                <p class="text-xs text-muted-foreground mt-1 font-semibold">
                    Margin Profit: {summary.profit_margin}%
                </p>
            </CardContent>
        </Card>

        <!-- Total HPP Modal -->
        <Card class="border shadow-xs">
            <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Estimasi HPP Modal</CardTitle>
                <div class="p-2 bg-amber-100 dark:bg-amber-950 text-amber-600 rounded-lg">
                    <Package class="size-4" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-foreground">{formatRupiah(summary.total_cost)}</div>
                <p class="text-xs text-muted-foreground mt-1">
                    Biaya bahan baku & produksi
                </p>
            </CardContent>
        </Card>

        <!-- Rata-rata Order -->
        <Card class="border shadow-xs">
            <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                <CardTitle class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Rata-Rata Order (AOV)</CardTitle>
                <div class="p-2 bg-purple-100 dark:bg-purple-950 text-purple-600 rounded-lg">
                    <ShoppingBag class="size-4" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-foreground">{formatRupiah(summary.avg_order_value)}</div>
                <p class="text-xs text-muted-foreground mt-1">
                    Nilai rata-rata per nota
                </p>
            </CardContent>
        </Card>
    </div>

    <!-- Payment Breakdown Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
        {#each paymentBreakdown as pay}
            <div class="p-3 bg-card rounded-xl border flex items-center justify-between shadow-2xs">
                <div>
                    <span class="text-muted-foreground block text-[11px] uppercase font-semibold">
                        Metode: {pay.payment_method}
                    </span>
                    <span class="text-base font-bold text-foreground">{formatRupiah(pay.total)}</span>
                </div>
                <Badge variant="outline" class="font-bold">{pay.count} Nota</Badge>
            </div>
        {/each}
    </div>

    <!-- Daily Trend & Top Products Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Daily Trend Table (5 cols) -->
        <Card class="lg:col-span-5 border shadow-xs">
            <CardHeader class="pb-3">
                <CardTitle class="text-sm font-semibold">Rincian Penjualan per Tanggal</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-xs" sortable sortDirection={dailyTrendSortable.getDirection('date')} onSort={() => dailyTrendSortable.toggleSort('date')}>
                                Tanggal
                            </TableHead>
                            <TableHead class="text-xs text-center" sortable sortDirection={dailyTrendSortable.getDirection('orders_count')} onSort={() => dailyTrendSortable.toggleSort('orders_count')}>
                                Jumlah Transaksi
                            </TableHead>
                            <TableHead class="text-xs text-right" sortable sortDirection={dailyTrendSortable.getDirection('revenue')} onSort={() => dailyTrendSortable.toggleSort('revenue')}>
                                Pendapatan
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {#if dailyTrendSortable.sorted.length === 0}
                            <TableRow>
                                <TableCell colspan={3} class="text-center py-6 text-muted-foreground text-xs">
                                    Tidak ada data penjualan pada periode ini.
                                </TableCell>
                            </TableRow>
                        {:else}
                            {#each dailyTrendSortable.sorted as day}
                                <TableRow>
                                    <TableCell class="text-xs font-medium">
                                        {new Date(day.date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}
                                    </TableCell>
                                    <TableCell class="text-xs text-center font-semibold">
                                        {day.orders_count} Nota
                                    </TableCell>
                                    <TableCell class="text-xs text-right font-bold text-foreground">
                                        {formatRupiah(day.revenue)}
                                    </TableCell>
                                </TableRow>
                            {/each}
                        {/if}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <!-- Top 10 Best Selling Products with Profit (7 cols) -->
        <Card class="lg:col-span-7 border shadow-xs">
            <CardHeader class="pb-3">
                <CardTitle class="text-sm font-semibold">Produk Terlaris & Kontribusi Laba</CardTitle>
                <CardDescription class="text-xs">Diurutkan berdasarkan total omset tertinggi pada periode ini</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-xs" sortable sortDirection={topProductsSortable.getDirection('product_name')} onSort={() => topProductsSortable.toggleSort('product_name')}>
                                Produk Cetak
                            </TableHead>
                            <TableHead class="text-xs text-center" sortable sortDirection={topProductsSortable.getDirection('total_qty')} onSort={() => topProductsSortable.toggleSort('total_qty')}>
                                Terjual
                            </TableHead>
                            <TableHead class="text-xs text-right" sortable sortDirection={topProductsSortable.getDirection('total_revenue')} onSort={() => topProductsSortable.toggleSort('total_revenue')}>
                                Total Omset
                            </TableHead>
                            <TableHead class="text-xs text-right" sortable sortDirection={topProductsSortable.getDirection('profit')} onSort={() => topProductsSortable.toggleSort('profit')}>
                                Estimasi Laba
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {#if topProductsSortable.sorted.length === 0}
                            <TableRow>
                                <TableCell colspan={4} class="text-center py-6 text-muted-foreground text-xs">
                                    Belum ada data produk terjual pada periode ini.
                                </TableCell>
                            </TableRow>
                        {:else}
                            {#each topProductsSortable.sorted as item}
                                <TableRow>
                                    <TableCell class="text-xs font-semibold text-foreground">
                                        {item.product_name}
                                    </TableCell>
                                    <TableCell class="text-xs text-center font-medium">
                                        {item.total_qty} {item.unit}
                                    </TableCell>
                                    <TableCell class="text-xs text-right font-bold">
                                        {formatRupiah(item.total_revenue)}
                                    </TableCell>
                                    <TableCell class="text-xs text-right font-bold text-emerald-600">
                                        {formatRupiah(item.profit)}
                                    </TableCell>
                                </TableRow>
                            {/each}
                        {/if}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</div>

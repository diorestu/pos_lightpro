<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Produksi',
                href: '#',
            },
            {
                title: 'Antrean Produksi',
                href: '/production',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AlertTriangle from '@lucide/svelte/icons/alert-triangle';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import Clock from '@lucide/svelte/icons/clock';
    import Eye from '@lucide/svelte/icons/eye';
    import FileText from '@lucide/svelte/icons/file-text';
    import Filter from '@lucide/svelte/icons/filter';
    import Kanban from '@lucide/svelte/icons/kanban';
    import PackageCheck from '@lucide/svelte/icons/package-check';
    import Printer from '@lucide/svelte/icons/printer';
    import RefreshCw from '@lucide/svelte/icons/refresh-cw';
    import Scissors from '@lucide/svelte/icons/scissors';
    import Search from '@lucide/svelte/icons/search';
    import Store from '@lucide/svelte/icons/store';
    import Truck from '@lucide/svelte/icons/truck';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';

    interface OrderItemDetail {
        id: number;
        product_name: string;
        pricing_type: string;
        unit: string;
        length?: number | null;
        width?: number | null;
        area?: number | null;
        quantity: number;
        unit_price: number;
        finishing_option?: string | null;
        finishing_price?: number;
        subtotal: number;
        design_note?: string | null;
    }

    interface OrderRecord {
        id: number;
        invoice_number: string;
        customer_name?: string | null;
        customer_phone?: string | null;
        order_status: 'pending' | 'processing' | 'finishing' | 'ready' | 'completed' | 'cancelled';
        payment_status: 'paid' | 'partial' | 'unpaid';
        payment_method: string;
        grand_total: number;
        paid_amount: number;
        due_date?: string | null;
        delivery_type?: string | null;
        courier_name?: string | null;
        notes?: string | null;
        created_at: string;
        updated_at: string;
        items: OrderItemDetail[];
        customer?: { id: number; name: string; phone?: string | null };
        user?: { id: number; name: string };
    }

    interface Stats {
        pending: number;
        processing: number;
        finishing: number;
        ready: number;
        completed_today: number;
        overdue: number;
    }

    let {
        orders = [],
        stats = {
            pending: 0,
            processing: 0,
            finishing: 0,
            ready: 0,
            completed_today: 0,
            overdue: 0,
        },
        filters = {
            search: '',
            deadline: 'all',
        },
    }: {
        orders: OrderRecord[];
        stats: Stats;
        filters: { search?: string; deadline?: string };
    } = $props();

    let searchInput = $state(filters.search || '');
    let selectedDeadline = $state(filters.deadline || 'all');
    let isUpdating = $state<number | null>(null);
    let showCompletedColumn = $state(false);

    $effect(() => {
        searchInput = filters.search || '';
        selectedDeadline = filters.deadline || 'all';
    });

    function applyFilters() {
        router.get(
            '/production',
            {
                search: searchInput || undefined,
                deadline: selectedDeadline !== 'all' ? selectedDeadline : undefined,
            },
            {
                preserveState: true,
                replace: true,
            }
        );
    }

    function resetFilters() {
        searchInput = '';
        selectedDeadline = 'all';
        applyFilters();
    }

    function updateOrderStatus(order: OrderRecord, newStatus: string) {
        if (isUpdating !== null) return;
        isUpdating = order.id;

        router.patch(
            `/production/${order.id}/status`,
            { order_status: newStatus },
            {
                preserveScroll: true,
                onFinish: () => {
                    isUpdating = null;
                },
            }
        );
    }

    function formatTime(dateStr: string): string {
        const d = new Date(dateStr);
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    function formatDate(dateStr: string): string {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function isOverdue(dueDateStr?: string | null, status?: string): boolean {
        if (!dueDateStr || status === 'completed' || status === 'cancelled') return false;
        const due = new Date(dueDateStr);
        due.setHours(23, 59, 59, 999);
        return due < new Date();
    }

    function isDueToday(dueDateStr?: string | null): boolean {
        if (!dueDateStr) return false;
        const due = new Date(dueDateStr);
        const today = new Date();
        return (
            due.getFullYear() === today.getFullYear() &&
            due.getMonth() === today.getMonth() &&
            due.getDate() === today.getDate()
        );
    }

    const pendingOrders = $derived(orders.filter((o) => o.order_status === 'pending'));
    const processingOrders = $derived(orders.filter((o) => o.order_status === 'processing'));
    const finishingOrders = $derived(orders.filter((o) => o.order_status === 'finishing'));
    const readyOrders = $derived(orders.filter((o) => o.order_status === 'ready'));
    const completedOrders = $derived(orders.filter((o) => o.order_status === 'completed'));
</script>

<AppHead title="Antrean & Pipeline Produksi Cetak" />

<div class="flex flex-col gap-6 p-4 md:p-6 w-full">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <Kanban class="size-6 text-primary" />
                Antrean & Pipeline Produksi Cetak
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Pantau antrean pekerjaan percetakan, proses cetak mesin, tahapan finishing, hingga pesanan siap diambil atau dikirim.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                size="sm"
                class="text-xs gap-1.5"
                onclick={() => router.reload({ only: ['orders', 'stats'] })}
            >
                <RefreshCw class="size-3.5" />
                Refresh Data
            </Button>
            <Button
                variant={showCompletedColumn ? 'default' : 'outline'}
                size="sm"
                class="text-xs gap-1.5"
                onclick={() => (showCompletedColumn = !showCompletedColumn)}
            >
                <PackageCheck class="size-3.5" />
                {showCompletedColumn ? 'Sembunyikan Selesai' : 'Tampilkan Selesai'}
            </Button>
        </div>
    </div>

    <!-- Stats KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Antrean -->
        <Card class="border shadow-2xs bg-amber-50/40 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900/50">
            <CardHeader class="p-3 pb-1 flex flex-row items-center justify-between">
                <CardTitle class="text-[11px] font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">
                    Antrean Baru
                </CardTitle>
                <Clock class="size-4 text-amber-600 dark:text-amber-400" />
            </CardHeader>
            <CardContent class="p-3 pt-0">
                <div class="text-2xl font-bold text-amber-800 dark:text-amber-300">
                    {stats.pending}
                </div>
                <p class="text-[10px] text-muted-foreground">Menunggu giliran cetak</p>
            </CardContent>
        </Card>

        <!-- Sedang Cetak -->
        <Card class="border shadow-2xs bg-blue-50/40 dark:bg-blue-950/20 border-blue-200 dark:border-blue-900/50">
            <CardHeader class="p-3 pb-1 flex flex-row items-center justify-between">
                <CardTitle class="text-[11px] font-semibold uppercase tracking-wider text-blue-700 dark:text-blue-400">
                    Sedang Cetak
                </CardTitle>
                <Printer class="size-4 text-blue-600 dark:text-blue-400" />
            </CardHeader>
            <CardContent class="p-3 pt-0">
                <div class="text-2xl font-bold text-blue-800 dark:text-blue-300">
                    {stats.processing}
                </div>
                <p class="text-[10px] text-muted-foreground">Proses mesin cetak</p>
            </CardContent>
        </Card>

        <!-- Finishing -->
        <Card class="border shadow-2xs bg-purple-50/40 dark:bg-purple-950/20 border-purple-200 dark:border-purple-900/50">
            <CardHeader class="p-3 pb-1 flex flex-row items-center justify-between">
                <CardTitle class="text-[11px] font-semibold uppercase tracking-wider text-purple-700 dark:text-purple-400">
                    Finishing
                </CardTitle>
                <Scissors class="size-4 text-purple-600 dark:text-purple-400" />
            </CardHeader>
            <CardContent class="p-3 pt-0">
                <div class="text-2xl font-bold text-purple-800 dark:text-purple-300">
                    {stats.finishing}
                </div>
                <p class="text-[10px] text-muted-foreground">Laminasi, mata ayam, potong</p>
            </CardContent>
        </Card>

        <!-- Siap Ambil / Kirim -->
        <Card class="border shadow-2xs bg-emerald-50/40 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900/50">
            <CardHeader class="p-3 pb-1 flex flex-row items-center justify-between">
                <CardTitle class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                    Siap Ambil
                </CardTitle>
                <CheckCircle2 class="size-4 text-emerald-600 dark:text-emerald-400" />
            </CardHeader>
            <CardContent class="p-3 pt-0">
                <div class="text-2xl font-bold text-emerald-800 dark:text-emerald-300">
                    {stats.ready}
                </div>
                <p class="text-[10px] text-muted-foreground">Menunggu diambil / kurir</p>
            </CardContent>
        </Card>

        <!-- Selesai Hari Ini -->
        <Card class="border shadow-2xs bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-800">
            <CardHeader class="p-3 pb-1 flex flex-row items-center justify-between">
                <CardTitle class="text-[11px] font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    Selesai Hari Ini
                </CardTitle>
                <PackageCheck class="size-4 text-slate-600 dark:text-slate-400" />
            </CardHeader>
            <CardContent class="p-3 pt-0">
                <div class="text-2xl font-bold text-slate-800 dark:text-slate-200">
                    {stats.completed_today}
                </div>
                <p class="text-[10px] text-muted-foreground">Tuntas dikerjakan</p>
            </CardContent>
        </Card>

        <!-- Lewat Deadline / Overdue -->
        <Card class="border shadow-2xs {stats.overdue > 0 ? 'bg-red-50/60 dark:bg-red-950/30 border-red-300 dark:border-red-900 animate-pulse' : 'bg-muted/30 border-border'}">
            <CardHeader class="p-3 pb-1 flex flex-row items-center justify-between">
                <CardTitle class="text-[11px] font-semibold uppercase tracking-wider {stats.overdue > 0 ? 'text-red-700 dark:text-red-400' : 'text-muted-foreground'}">
                    Lewat Deadline
                </CardTitle>
                <AlertTriangle class="size-4 {stats.overdue > 0 ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'}" />
            </CardHeader>
            <CardContent class="p-3 pt-0">
                <div class="text-2xl font-bold {stats.overdue > 0 ? 'text-red-700 dark:text-red-300' : 'text-foreground'}">
                    {stats.overdue}
                </div>
                <p class="text-[10px] {stats.overdue > 0 ? 'text-red-600 font-semibold' : 'text-muted-foreground'}">
                    {stats.overdue > 0 ? 'Harus segera diproses!' : 'Semua tepat waktu'}
                </p>
            </CardContent>
        </Card>
    </div>

    <!-- Filter & Search Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-card p-3 rounded-xl border">
        <div class="flex items-center gap-2 flex-1 max-w-md">
            <div class="relative w-full">
                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <Input
                    bind:value={searchInput}
                    onkeydown={(e) => e.key === 'Enter' && applyFilters()}
                    placeholder="Cari no nota, pelanggan, atau nama file..."
                    class="pl-9 h-9 text-xs"
                />
            </div>
            <Button size="sm" class="h-9 text-xs gap-1" onclick={applyFilters}>
                <Filter class="size-3" />
                Cari
            </Button>
            {#if searchInput || selectedDeadline !== 'all'}
                <Button variant="ghost" size="sm" class="h-9 text-xs text-muted-foreground" onclick={resetFilters}>
                    Reset
                </Button>
            {/if}
        </div>

        <!-- Deadline Filter Tabs -->
        <div class="flex items-center gap-1 overflow-x-auto text-xs">
            <button
                type="button"
                onclick={() => {
                    selectedDeadline = 'all';
                    applyFilters();
                }}
                class="px-3 py-1.5 rounded-lg font-medium transition-colors {selectedDeadline === 'all' ? 'bg-primary text-primary-foreground shadow-xs' : 'bg-muted/40 hover:bg-muted text-muted-foreground'}"
            >
                Semua Deadline
            </button>
            <button
                type="button"
                onclick={() => {
                    selectedDeadline = 'today';
                    applyFilters();
                }}
                class="px-3 py-1.5 rounded-lg font-medium transition-colors {selectedDeadline === 'today' ? 'bg-amber-600 text-white shadow-xs' : 'bg-muted/40 hover:bg-muted text-muted-foreground'}"
            >
                Hari Ini
            </button>
            <button
                type="button"
                onclick={() => {
                    selectedDeadline = 'overdue';
                    applyFilters();
                }}
                class="px-3 py-1.5 rounded-lg font-medium transition-colors {selectedDeadline === 'overdue' ? 'bg-red-600 text-white shadow-xs' : 'bg-muted/40 hover:bg-muted text-muted-foreground'}"
            >
                Terlambat
            </button>
        </div>
    </div>

    <!-- Kanban Board Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 {showCompletedColumn ? 'xl:grid-cols-5' : 'xl:grid-cols-4'} gap-4 items-start">
        
        <!-- ==================== KOLOM 1: ANTREAN BARU (PENDING) ==================== -->
        <div class="flex flex-col gap-3 bg-muted/20 border border-amber-200/60 dark:border-amber-900/30 rounded-xl p-3 min-h-[500px]">
            <div class="flex items-center justify-between pb-2 border-b border-amber-200/50 dark:border-amber-900/30">
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full bg-amber-500"></span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-amber-900 dark:text-amber-300">
                        Antrean Cetak
                    </h2>
                </div>
                <Badge variant="outline" class="bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 font-bold text-xs">
                    {pendingOrders.length}
                </Badge>
            </div>

            {#if pendingOrders.length === 0}
                <div class="py-12 text-center text-xs text-muted-foreground flex flex-col items-center justify-center gap-2">
                    <Clock class="size-8 text-muted-foreground/40" />
                    <span>Tidak ada antrean baru</span>
                </div>
            {:else}
                <div class="space-y-3">
                    {#each pendingOrders as order (order.id)}
                        {@render orderCard(order, 'pending')}
                    {/each}
                </div>
            {/if}
        </div>

        <!-- ==================== KOLOM 2: PROSES CETAK (PROCESSING) ==================== -->
        <div class="flex flex-col gap-3 bg-muted/20 border border-blue-200/60 dark:border-blue-900/30 rounded-xl p-3 min-h-[500px]">
            <div class="flex items-center justify-between pb-2 border-b border-blue-200/50 dark:border-blue-900/30">
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-blue-900 dark:text-blue-300">
                        Sedang Dicetak
                    </h2>
                </div>
                <Badge variant="outline" class="bg-blue-100 dark:bg-blue-950 text-blue-800 dark:text-blue-300 font-bold text-xs">
                    {processingOrders.length}
                </Badge>
            </div>

            {#if processingOrders.length === 0}
                <div class="py-12 text-center text-xs text-muted-foreground flex flex-col items-center justify-center gap-2">
                    <Printer class="size-8 text-muted-foreground/40" />
                    <span>Tidak ada yang sedang dicetak</span>
                </div>
            {:else}
                <div class="space-y-3">
                    {#each processingOrders as order (order.id)}
                        {@render orderCard(order, 'processing')}
                    {/each}
                </div>
            {/if}
        </div>

        <!-- ==================== KOLOM 3: FINISHING ==================== -->
        <div class="flex flex-col gap-3 bg-muted/20 border border-purple-200/60 dark:border-purple-900/30 rounded-xl p-3 min-h-[500px]">
            <div class="flex items-center justify-between pb-2 border-b border-purple-200/50 dark:border-purple-900/30">
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full bg-purple-500"></span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-purple-900 dark:text-purple-300">
                        Finishing
                    </h2>
                </div>
                <Badge variant="outline" class="bg-purple-100 dark:bg-purple-950 text-purple-800 dark:text-purple-300 font-bold text-xs">
                    {finishingOrders.length}
                </Badge>
            </div>

            {#if finishingOrders.length === 0}
                <div class="py-12 text-center text-xs text-muted-foreground flex flex-col items-center justify-center gap-2">
                    <Scissors class="size-8 text-muted-foreground/40" />
                    <span>Tidak ada dalam finishing</span>
                </div>
            {:else}
                <div class="space-y-3">
                    {#each finishingOrders as order (order.id)}
                        {@render orderCard(order, 'finishing')}
                    {/each}
                </div>
            {/if}
        </div>

        <!-- ==================== KOLOM 4: SIAP AMBIL / KIRIM (READY) ==================== -->
        <div class="flex flex-col gap-3 bg-muted/20 border border-emerald-200/60 dark:border-emerald-900/30 rounded-xl p-3 min-h-[500px]">
            <div class="flex items-center justify-between pb-2 border-b border-emerald-200/50 dark:border-emerald-900/30">
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full bg-emerald-500"></span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-300">
                        Siap Ambil / Kirim
                    </h2>
                </div>
                <Badge variant="outline" class="bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 font-bold text-xs">
                    {readyOrders.length}
                </Badge>
            </div>

            {#if readyOrders.length === 0}
                <div class="py-12 text-center text-xs text-muted-foreground flex flex-col items-center justify-center gap-2">
                    <CheckCircle2 class="size-8 text-muted-foreground/40" />
                    <span>Belum ada pesanan siap</span>
                </div>
            {:else}
                <div class="space-y-3">
                    {#each readyOrders as order (order.id)}
                        {@render orderCard(order, 'ready')}
                    {/each}
                </div>
            {/if}
        </div>

        <!-- ==================== KOLOM 5: SELESAI (COMPLETED) - OPTIONAL ==================== -->
        {#if showCompletedColumn}
            <div class="flex flex-col gap-3 bg-muted/20 border border-slate-200/60 dark:border-slate-800 rounded-xl p-3 min-h-[500px]">
                <div class="flex items-center justify-between pb-2 border-b border-slate-200/50 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="size-2.5 rounded-full bg-slate-500"></span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-300">
                            Selesai (24 Jam)
                        </h2>
                    </div>
                    <Badge variant="outline" class="bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 font-bold text-xs">
                        {completedOrders.length}
                    </Badge>
                </div>

                {#if completedOrders.length === 0}
                    <div class="py-12 text-center text-xs text-muted-foreground flex flex-col items-center justify-center gap-2">
                        <PackageCheck class="size-8 text-muted-foreground/40" />
                        <span>Belum ada pesanan tuntas</span>
                    </div>
                {:else}
                    <div class="space-y-3">
                        {#each completedOrders as order (order.id)}
                            {@render orderCard(order, 'completed')}
                        {/each}
                    </div>
                {/if}
            </div>
        {/if}
    </div>
</div>

<!-- ==================== SNIPPET: KARTU PESANAN (ORDER CARD) ==================== -->
{#snippet orderCard(order: OrderRecord, stage: string)}
    {@const overdue = isOverdue(order.due_date, order.order_status)}
    {@const dueToday = isDueToday(order.due_date)}
    {@const isDelivery = order.delivery_type === 'courier'}

    <div class="bg-card rounded-lg border shadow-2xs hover:shadow-md transition-all p-3 space-y-2.5 text-xs relative {overdue ? 'border-red-400 bg-red-50/20 dark:border-red-800' : ''}">
        <!-- Top Row: Invoice + Time + Deadline Badge -->
        <div class="flex items-start justify-between gap-1.5">
            <div>
                <a
                    href={`/orders/${order.id}`}
                    class="font-mono font-bold text-foreground hover:text-primary transition-colors hover:underline text-xs"
                    title="Buka detail order"
                >
                    {order.invoice_number}
                </a>
                <div class="text-[10px] text-muted-foreground mt-0.5">
                    {formatDate(order.created_at)} • {formatTime(order.created_at)}
                </div>
            </div>

            <!-- Deadline Badge -->
            {#if order.due_date}
                {#if overdue}
                    <Badge class="bg-red-600 text-white font-bold text-[9px] uppercase tracking-wider px-1.5 py-0.5">
                        Lewat Deadline!
                    </Badge>
                {:else if dueToday}
                    <Badge class="bg-amber-600 text-white font-bold text-[9px] uppercase tracking-wider px-1.5 py-0.5">
                        Hari Ini
                    </Badge>
                {:else}
                    <Badge variant="outline" class="text-[9px] px-1.5 py-0.5 font-medium border-muted-foreground/30">
                        {formatDate(order.due_date)}
                    </Badge>
                {/if}
            {/if}
        </div>

        <!-- Customer & Delivery Info -->
        <div class="flex items-center justify-between text-xs pt-1 border-t border-border/60">
            <span class="font-semibold text-foreground truncate max-w-[140px]" title={order.customer_name || 'Pelanggan Umum'}>
                {order.customer_name || 'Pelanggan Umum'}
            </span>

            {#if isDelivery}
                <Badge variant="outline" class="text-[9px] px-1.5 py-0 text-blue-600 border-blue-300 dark:border-blue-800 bg-blue-50/50 gap-1 flex items-center">
                    <Truck class="size-2.5" />
                    {order.courier_name || 'Kurir'}
                </Badge>
            {:else}
                <Badge variant="outline" class="text-[9px] px-1.5 py-0 text-neutral-600 border-neutral-300 dark:border-neutral-700 bg-neutral-50/50 gap-1 flex items-center">
                    <Store class="size-2.5" />
                    Ambil di Toko
                </Badge>
            {/if}
        </div>

        <!-- Items Breakdown -->
        <div class="bg-muted/40 rounded-md p-2 space-y-2 text-[11px] border border-border/50">
            {#each order.items as item}
                <div class="border-b border-border/40 last:border-0 pb-1.5 last:pb-0">
                    <div class="flex items-center justify-between gap-1 font-semibold text-foreground">
                        <span class="truncate">{item.product_name}</span>
                        <span class="shrink-0 bg-primary/10 text-primary px-1.5 py-0.2 rounded font-bold text-[10px]">
                            {item.quantity} {item.unit}
                        </span>
                    </div>

                    <!-- Dimension Info if available -->
                    {#if Number(item.length) > 0 && Number(item.width) > 0}
                        <div class="text-[10px] text-muted-foreground mt-0.5 flex items-center gap-1 font-mono">
                            <span>📐 {item.length} × {item.width} cm</span>
                            <span>• Luas: {item.area} {item.pricing_type === 'dimension_sqcm' || item.unit === 'cm²' ? 'cm²' : 'm²'}</span>
                        </div>
                    {/if}

                    <!-- Finishing Info -->
                    {#if item.finishing_option && item.finishing_option !== 'Tanpa Finishing' && item.finishing_option !== 'Standar'}
                        <div class="text-[10px] text-purple-700 dark:text-purple-400 font-medium mt-0.5 flex items-center gap-1">
                            <span>✂️ {item.finishing_option}</span>
                        </div>
                    {/if}

                    <!-- Design File Note -->
                    {#if item.design_note}
                        <div class="text-[10px] text-blue-700 dark:text-blue-400 font-mono mt-1 p-1 bg-blue-50 dark:bg-blue-950/60 rounded border border-blue-200 dark:border-blue-900 flex items-center gap-1 break-all">
                            <FileText class="size-3 shrink-0" />
                            <span>{item.design_note}</span>
                        </div>
                    {/if}
                </div>
            {/each}
        </div>

        <!-- Notes / Special Instructions -->
        {#if order.notes}
            <div class="text-[10px] text-amber-800 dark:text-amber-300 bg-amber-50/60 dark:bg-amber-950/30 p-1.5 rounded border border-amber-200/50">
                <span class="font-bold">Catatan:</span> {order.notes}
            </div>
        {/if}

        <!-- Bottom Action Buttons by Stage -->
        <div class="pt-2 border-t flex items-center justify-between gap-1.5">
            <a
                href={`/orders/${order.id}`}
                class="size-7 inline-flex items-center justify-center rounded border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors"
                title="Lihat Detail Nota"
            >
                <Eye class="size-3.5" />
            </a>

            <!-- State Machine Advancement Buttons -->
            <div class="flex items-center gap-1">
                {#if stage === 'pending'}
                    <Button
                        size="sm"
                        class="h-7 text-[11px] px-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold gap-1 shadow-xs"
                        onclick={() => updateOrderStatus(order, 'processing')}
                        disabled={isUpdating === order.id}
                    >
                        <Printer class="size-3" />
                        Mulai Cetak
                    </Button>
                {:else if stage === 'processing'}
                    <!-- Check if order items have finishing -->
                    <Button
                        size="sm"
                        class="h-7 text-[11px] px-2 bg-purple-600 hover:bg-purple-700 text-white font-semibold gap-1 shadow-xs"
                        onclick={() => updateOrderStatus(order, 'finishing')}
                        disabled={isUpdating === order.id}
                        title="Lanjut ke proses finishing"
                    >
                        <Scissors class="size-3" />
                        Finishing
                    </Button>
                    <Button
                        size="sm"
                        class="h-7 text-[11px] px-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold gap-1 shadow-xs"
                        onclick={() => updateOrderStatus(order, 'ready')}
                        disabled={isUpdating === order.id}
                        title="Lewati finishing dan langsung tandai siap ambil"
                    >
                        <CheckCircle2 class="size-3" />
                        Siap
                    </Button>
                {:else if stage === 'finishing'}
                    <Button
                        size="sm"
                        class="h-7 text-[11px] px-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold gap-1 shadow-xs"
                        onclick={() => updateOrderStatus(order, 'ready')}
                        disabled={isUpdating === order.id}
                    >
                        <CheckCircle2 class="size-3" />
                        Tandai Siap
                    </Button>
                {:else if stage === 'ready'}
                    <Button
                        size="sm"
                        class="h-7 text-[11px] px-2.5 bg-slate-800 hover:bg-slate-900 text-white font-semibold gap-1 shadow-xs dark:bg-slate-700"
                        onclick={() => updateOrderStatus(order, 'completed')}
                        disabled={isUpdating === order.id}
                    >
                        <PackageCheck class="size-3" />
                        Diambil / Selesai
                    </Button>
                {:else if stage === 'completed'}
                    <span class="text-[10px] text-muted-foreground font-semibold flex items-center gap-1">
                        <CheckCircle2 class="size-3 text-emerald-600" />
                        Tuntas
                    </span>
                {/if}
            </div>
        </div>
    </div>
{/snippet}

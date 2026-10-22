<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Riwayat Penjualan',
                href: '/orders',
            },
        ],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import AlertTriangle from '@lucide/svelte/icons/alert-triangle';
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import Clock from '@lucide/svelte/icons/clock';
    import Eye from '@lucide/svelte/icons/eye';
    import Filter from '@lucide/svelte/icons/filter';
    import Printer from '@lucide/svelte/icons/printer';
    import RotateCcw from '@lucide/svelte/icons/rotate-ccw';
    import Search from '@lucide/svelte/icons/search';
    import XCircle from '@lucide/svelte/icons/x-circle';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';
    import { createSortable } from '@/lib/sortable.svelte';

    interface OrderRecord {
        id: number;
        invoice_number: string;
        customer_name: string;
        customer_phone: string;
        grand_total: number;
        paid_amount: number;
        change_amount: number;
        payment_method: string;
        payment_status: 'paid' | 'partial' | 'unpaid';
        order_status: 'pending' | 'processing' | 'ready' | 'completed' | 'cancelled';
        cancellation_reason?: string;
        created_at: string;
        user?: { name: string };
        items?: any[];
        return?: any;
    }

    let {
        orders = { data: [], links: [] },
        filters = {},
    }: {
        orders: { data: OrderRecord[]; links: any[]; current_page: number; last_page: number };
        filters: Record<string, string>;
    } = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let dateFrom = $state(filters.date_from || '');
    // svelte-ignore state_referenced_locally
    let dateTo = $state(filters.date_to || '');
    // svelte-ignore state_referenced_locally
    let orderStatus = $state(filters.order_status || '');
    // svelte-ignore state_referenced_locally
    let paymentStatus = $state(filters.payment_status || '');
    // svelte-ignore state_referenced_locally
    let paymentMethod = $state(filters.payment_method || '');

    $effect(() => {
        search = filters.search || '';
        dateFrom = filters.date_from || '';
        dateTo = filters.date_to || '';
        orderStatus = filters.order_status || '';
        paymentStatus = filters.payment_status || '';
        paymentMethod = filters.payment_method || '';
    });

    // Sorter
    const sorter = createSortable(() => orders.data, 'created_at', 'desc');

    // Modals
    let showCancelModal = $state(false);
    let orderToCancel = $state<OrderRecord | null>(null);
    let cancelReason = $state('');
    let restoreStockOnCancel = $state(true);

    let showReturnModal = $state(false);
    let orderToReturn = $state<OrderRecord | null>(null);
    let returnReason = $state('');
    let refundAmount = $state('');
    let restoreStockOnReturn = $state(true);

    function applyFilters() {
        router.get(
            '/orders',
            {
                search: search || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                order_status: orderStatus || undefined,
                payment_status: paymentStatus || undefined,
                payment_method: paymentMethod || undefined,
            },
            { preserveState: true }
        );
    }

    function resetFilters() {
        search = '';
        dateFrom = '';
        dateTo = '';
        orderStatus = '';
        paymentStatus = '';
        paymentMethod = '';
        router.get('/orders');
    }

    function formatRupiah(amount: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);
    }

    function openCancelDialog(order: OrderRecord) {
        orderToCancel = order;
        cancelReason = '';
        restoreStockOnCancel = true;
        showCancelModal = true;
    }

    function submitCancel() {
        if (!orderToCancel) return;
        router.post(
            `/orders/${orderToCancel.id}/cancel`,
            {
                reason: cancelReason,
                restore_stock: restoreStockOnCancel,
            },
            {
                onSuccess: () => {
                    showCancelModal = false;
                    orderToCancel = null;
                },
            }
        );
    }

    function openReturnDialog(order: OrderRecord) {
        orderToReturn = order;
        returnReason = '';
        refundAmount = order.paid_amount.toString();
        restoreStockOnReturn = true;
        showReturnModal = true;
    }

    function submitReturn() {
        if (!orderToReturn) return;
        router.post(
            `/orders/${orderToReturn.id}/return`,
            {
                reason: returnReason,
                refund_amount: parseFloat(refundAmount) || 0,
                restore_stock: restoreStockOnReturn,
            },
            {
                onSuccess: () => {
                    showReturnModal = false;
                    orderToReturn = null;
                },
            }
        );
    }
</script>

<AppHead title="Riwayat Penjualan Nota" />

<div class="flex flex-col gap-6 p-4 md:p-6 max-w-7xl mx-auto w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Riwayat Penjualan & Nota</h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Kelola transaksi cetak, pelunasan sisa tempo, pembatalan pesanan (cancel order), dan retur pelanggan. Klik judul kolom untuk mengurutkan data (sortable).
            </p>
        </div>
        <Link href="/pos">
            <Button class="bg-primary font-semibold">
                + Buat Transaksi Baru
            </Button>
        </Link>
    </div>

    <!-- Filters Bar -->
    <Card class="border shadow-2xs">
        <CardContent class="p-4 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2.5">
                <!-- Search -->
                <div class="space-y-1">
                    <Label class="text-[11px] text-muted-foreground">Cari Nota / Pelanggan</Label>
                    <div class="relative">
                        <Search class="size-3.5 absolute left-2.5 top-2.5 text-muted-foreground" />
                        <Input bind:value={search} placeholder="INV-2026..." class="h-8 pl-8 text-xs" />
                    </div>
                </div>

                <!-- Date From -->
                <div class="space-y-1">
                    <Label class="text-[11px] text-muted-foreground">Dari Tanggal</Label>
                    <Input type="date" bind:value={dateFrom} class="h-8 text-xs" />
                </div>

                <!-- Date To -->
                <div class="space-y-1">
                    <Label class="text-[11px] text-muted-foreground">Sampai Tanggal</Label>
                    <Input type="date" bind:value={dateTo} class="h-8 text-xs" />
                </div>

                <!-- Order Status -->
                <div class="space-y-1">
                    <Label class="text-[11px] text-muted-foreground">Status Cetak</Label>
                    <select
                        bind:value={orderStatus}
                        class="h-8 text-xs rounded-md border border-input bg-background px-2 pr-8 w-full"
                    >
                        <option value="">Semua Status</option>
                        <option value="pending">Antrian (Pending)</option>
                        <option value="processing">Sedang Dicetak</option>
                        <option value="ready">Siap Diambil</option>
                        <option value="completed">Selesai</option>
                        <option value="cancelled">Dibatalkan</option>
                    </select>
                </div>

                <!-- Payment Status -->
                <div class="space-y-1">
                    <Label class="text-[11px] text-muted-foreground">Status Pembayaran</Label>
                    <select
                        bind:value={paymentStatus}
                        class="h-8 text-xs rounded-md border border-input bg-background px-2 pr-8 w-full"
                    >
                        <option value="">Semua Bayar</option>
                        <option value="paid">Lunas</option>
                        <option value="partial">DP (Parsial)</option>
                        <option value="unpaid">Belum Bayar</option>
                    </select>
                </div>

                <!-- Payment Method -->
                <div class="space-y-1">
                    <Label class="text-[11px] text-muted-foreground">Metode Bayar</Label>
                    <select
                        bind:value={paymentMethod}
                        class="h-8 text-xs rounded-md border border-input bg-background px-2 pr-8 w-full"
                    >
                        <option value="">Semua Metode</option>
                        <option value="cash">Tunai</option>
                        <option value="qris">QRIS</option>
                        <option value="transfer">Transfer Bank</option>
                        <option value="tempo">Tempo / DP</option>
                        <option value="split">Split / Termin</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-1 border-t">
                <Button variant="ghost" size="sm" class="h-7 text-xs text-muted-foreground" onclick={resetFilters}>
                    Reset Filter
                </Button>
                <Button size="sm" class="h-7 text-xs" onclick={applyFilters}>
                    <Filter class="size-3 mr-1" />
                    Terapkan Filter
                </Button>
            </div>
        </CardContent>
    </Card>

    <!-- Orders Table with Sortable Columns -->
    <Card class="border shadow-xs">
        <CardContent class="p-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'invoice_number' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('invoice_number')}
                        >
                            No. Invoice
                        </TableHead>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'created_at' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('created_at')}
                        >
                            Tanggal
                        </TableHead>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'customer_name' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('customer_name')}
                        >
                            Pelanggan
                        </TableHead>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'user_id' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('user_id')}
                        >
                            Kasir
                        </TableHead>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'order_status' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('order_status')}
                        >
                            Status Cetak
                        </TableHead>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'payment_status' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('payment_status')}
                        >
                            Pembayaran
                        </TableHead>
                        <TableHead
                            class="text-xs text-right"
                            sortable
                            sortDirection={sorter.sortKey === 'grand_total' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('grand_total')}
                        >
                            Total Nota
                        </TableHead>
                        <TableHead class="text-xs text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {#if sorter.sortedData.length === 0}
                        <TableRow>
                            <TableCell colspan={8} class="text-center py-10 text-muted-foreground text-xs">
                                Tidak ada data penjualan sesuai filter yang dipilih.
                            </TableCell>
                        </TableRow>
                    {:else}
                        {#each sorter.sortedData as order (order.id)}
                            <TableRow>
                                <TableCell class="font-medium text-xs">
                                    <Link href="/orders/{order.id}" class="hover:underline text-primary font-bold">
                                        {order.invoice_number}
                                    </Link>
                                    {#if order.return}
                                        <span class="block text-[10px] text-destructive font-semibold">
                                            (Diretur: {order.return.return_number})
                                        </span>
                                    {/if}
                                </TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    {new Date(order.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                </TableCell>
                                <TableCell class="text-xs">
                                    <span class="font-medium block text-foreground">{order.customer_name || 'Pelanggan Umum'}</span>
                                    {#if order.customer_phone && order.customer_phone !== '-'}
                                        <span class="text-[11px] text-muted-foreground">{order.customer_phone}</span>
                                    {/if}
                                </TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    {order.user?.name || '-'}
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
                                    <div class="flex items-center gap-1.5">
                                        {#if order.payment_status === 'paid'}
                                            <Badge class="bg-emerald-600 hover:bg-emerald-600 text-white text-[10px]">Lunas</Badge>
                                        {:else if order.payment_status === 'partial'}
                                            <Badge variant="secondary" class="bg-amber-100 text-amber-800 text-[10px]">DP / Tempo</Badge>
                                        {:else}
                                            <Badge variant="destructive" class="text-[10px]">Belum Bayar</Badge>
                                        {/if}
                                        <span class="text-[11px] uppercase text-muted-foreground font-semibold">({order.payment_method})</span>
                                    </div>
                                    {#if order.payment_status !== 'paid' && order.order_status !== 'cancelled'}
                                        <div class="text-[10px] text-amber-700 dark:text-amber-300 mt-0.5 font-medium">
                                            Sisa: {formatRupiah(order.grand_total - order.paid_amount)}
                                        </div>
                                    {/if}
                                </TableCell>
                                <TableCell class="text-xs text-right font-bold text-foreground">
                                    {formatRupiah(order.grand_total)}
                                </TableCell>
                                <TableCell class="text-xs text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link href="/orders/{order.id}">
                                            <Button size="sm" variant="ghost" class="h-7 px-2 text-xs" title="Lihat Detail & Nota">
                                                <Eye class="size-3.5 mr-1" />
                                                Detail
                                            </Button>
                                        </Link>

                                        {#if order.order_status !== 'cancelled'}
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="h-7 px-2 text-xs text-destructive hover:bg-destructive/10 border-destructive/30"
                                                title="Batalkan Pesanan (Cancel Order)"
                                                onclick={() => openCancelDialog(order)}
                                            >
                                                <XCircle class="size-3.5 mr-1" />
                                                Batal
                                            </Button>

                                            {#if !order.return}
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    class="h-7 px-2 text-xs text-amber-700 hover:bg-amber-100 border-amber-300"
                                                    title="Retur Hasil Cetak"
                                                    onclick={() => openReturnDialog(order)}
                                                >
                                                    <RotateCcw class="size-3.5 mr-1" />
                                                    Retur
                                                </Button>
                                            {/if}
                                        {/if}
                                    </div>
                                </TableCell>
                            </TableRow>
                        {/each}
                    {/if}
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</div>

<!-- Modal: Cancel Order -->
<Dialog open={showCancelModal} onOpenChange={(open) => (showCancelModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2 text-destructive">
                <AlertTriangle class="size-5" />
                Batalkan Pesanan #{orderToCancel?.invoice_number}
            </DialogTitle>
            <DialogDescription class="text-xs">
                Pembatalan pesanan akan mencatat riwayat audit dan dapat mengembalikan stok bahan baku / produk secara otomatis.
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
            <div class="space-y-1">
                <Label for="cancel_reason">Alasan Pembatalan *</Label>
                <Textarea
                    id="cancel_reason"
                    bind:value={cancelReason}
                    placeholder="Contoh: Pelanggan membatalkan acara / salah spesifikasi file cetak..."
                    rows={3}
                />
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input
                    type="checkbox"
                    id="restore_stock"
                    bind:checked={restoreStockOnCancel}
                    class="rounded border-input size-4 text-primary"
                />
                <Label for="restore_stock" class="cursor-pointer text-xs font-medium">
                    Kembalikan stok bahan baku / produk yang terpakai ke gudang
                </Label>
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (showCancelModal = false)}>Tutup</Button>
            <Button variant="destructive" onclick={submitCancel} disabled={!cancelReason}>
                Konfirmasi Batal Pesanan
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Modal: Retur Penjualan -->
<Dialog open={showReturnModal} onOpenChange={(open) => (showReturnModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2 text-amber-700">
                <RotateCcw class="size-5" />
                Proses Retur #{orderToReturn?.invoice_number}
            </DialogTitle>
            <DialogDescription class="text-xs">
                Catat pengembalian pesanan cacat atau komplain pelanggan dan jumlah pengembalian dana (refund).
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
            <div class="space-y-1">
                <Label for="ret_reason">Alasan Retur / Komplain *</Label>
                <Textarea
                    id="ret_reason"
                    bind:value={returnReason}
                    placeholder="Contoh: Warna luntur, banner terlipat saat finishing, cacat bahan..."
                    rows={3}
                />
            </div>

            <div class="space-y-1">
                <Label for="refund_amt">Nominal Refund / Pengembalian Dana (Rp)</Label>
                <Input
                    id="refund_amt"
                    type="number"
                    bind:value={refundAmount}
                    class="font-bold"
                />
                <span class="text-[10px] text-muted-foreground">
                    Maksimal refund: {formatRupiah(orderToReturn?.paid_amount || 0)}
                </span>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input
                    type="checkbox"
                    id="ret_restore_stock"
                    bind:checked={restoreStockOnReturn}
                    class="rounded border-input size-4 text-primary"
                />
                <Label for="ret_restore_stock" class="cursor-pointer text-xs font-medium">
                    Kembalikan produk ke stok jika barang masih layak
                </Label>
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (showReturnModal = false)}>Tutup</Button>
            <Button class="bg-amber-600 hover:bg-amber-700 text-white" onclick={submitReturn} disabled={!returnReason}>
                Simpan Data Retur
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

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
    import Plus from '@lucide/svelte/icons/plus';
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
    import { DataTable, type ColumnDef, TableCell, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';

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
        orders: any;
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
    // svelte-ignore state_referenced_locally
    let currentPerPage = $state(filters.per_page ? parseInt(filters.per_page, 10) : 15);

    $effect(() => {
        search = filters.search || '';
        dateFrom = filters.date_from || '';
        dateTo = filters.date_to || '';
        orderStatus = filters.order_status || '';
        paymentStatus = filters.payment_status || '';
        paymentMethod = filters.payment_method || '';
        currentPerPage = filters.per_page ? parseInt(filters.per_page, 10) : 15;
    });

    const columns: ColumnDef[] = [
        { key: 'invoice_number', label: 'No. Invoice', sortable: true },
        { key: 'created_at', label: 'Tanggal', sortable: true },
        { key: 'customer_name', label: 'Pelanggan', sortable: true },
        { key: 'user_id', label: 'Kasir', sortable: true },
        { key: 'order_status', label: 'Status Cetak', sortable: false },
        { key: 'payment_status', label: 'Status Bayar', sortable: false },
        { key: 'grand_total', label: 'Total Tagihan', align: 'right', sortable: true },
        { key: 'actions', label: 'Aksi', align: 'right', sortable: false, hideable: false },
    ];

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

    function applyFilters(newSearch?: string) {
        if (newSearch !== undefined) {
            search = newSearch;
        }
        router.get(
            '/orders',
            {
                search: search || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                order_status: orderStatus || undefined,
                payment_status: paymentStatus || undefined,
                payment_method: paymentMethod || undefined,
                per_page: currentPerPage,
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
            <Button class="bg-primary font-semibold whitespace-nowrap inline-flex items-center">
                <Plus class="size-4 mr-1.5" />
                Transaksi POS
            </Button>
        </Link>
    </div>

    <!-- Filters Bar -->
    <Card class="border shadow-2xs">
        <CardContent class="p-3.5 space-y-2.5">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2.5">
                <!-- Date From -->
                <div class="space-y-1">
                    <Label class="text-[11px] text-muted-foreground">Dari Tanggal</Label>
                    <Input type="date" bind:value={dateFrom} class="h-8 text-xs bg-background" />
                </div>

                <!-- Date To -->
                <div class="space-y-1">
                    <Label class="text-[11px] text-muted-foreground">Sampai Tanggal</Label>
                    <Input type="date" bind:value={dateTo} class="h-8 text-xs bg-background" />
                </div>

                <!-- Payment Method -->
                <div class="space-y-1">
                    <Label class="text-[11px] text-muted-foreground">Metode Bayar</Label>
                    <select
                        bind:value={paymentMethod}
                        class="h-8 text-xs rounded-md border border-input bg-background px-2 pr-8 w-full cursor-pointer font-medium"
                    >
                        <option value="">Semua Metode</option>
                        <option value="cash">Tunai</option>
                        <option value="qris">QRIS</option>
                        <option value="transfer">Transfer Bank</option>
                        <option value="tempo">Tempo / DP</option>
                        <option value="split">Split / Termin</option>
                    </select>
                </div>

                <!-- Actions -->
                <div class="flex items-end gap-2">
                    <Button size="sm" class="h-8 text-xs flex-1" onclick={() => applyFilters()}>
                        <Filter class="size-3 mr-1" />
                        Terapkan Filter
                    </Button>
                    <Button variant="outline" size="sm" class="h-8 text-xs text-muted-foreground" onclick={resetFilters}>
                        Reset
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Reusable DataTable -->
    <DataTable
        {columns}
        data={orders}
        pagination={orders}
        search={search}
        searchPlaceholder="Cari no invoice, pelanggan, kasir..."
        onSearch={(val) => applyFilters(val)}
        perPage={currentPerPage}
        onPerPageChange={(val) => {
            currentPerPage = val;
            applyFilters();
        }}
        onPageChange={(_, url) => {
            if (url) router.get(url, {}, { preserveState: true });
        }}
        defaultSortKey="created_at"
        defaultSortDir="desc"
        emptyMessage="Tidak ada riwayat transaksi penjualan ditemukan."
    >
        {#snippet filtersSnippet()}
            <select
                bind:value={orderStatus}
                onchange={() => applyFilters()}
                class="h-8 text-xs rounded-md border border-input bg-background px-2 pr-8 w-full sm:w-36 cursor-pointer font-medium"
            >
                <option value="">Semua Status Cetak</option>
                <option value="pending">Antrian (Pending)</option>
                <option value="processing">Sedang Dicetak</option>
                <option value="ready">Siap Diambil</option>
                <option value="completed">Selesai</option>
                <option value="cancelled">Dibatalkan</option>
            </select>
            <select
                bind:value={paymentStatus}
                onchange={() => applyFilters()}
                class="h-8 text-xs rounded-md border border-input bg-background px-2 pr-8 w-full sm:w-36 cursor-pointer font-medium"
            >
                <option value="">Semua Status Bayar</option>
                <option value="paid">Lunas</option>
                <option value="partial">DP (Parsial)</option>
                <option value="unpaid">Belum Bayar</option>
            </select>
        {/snippet}

        {#snippet rowSnippet({ item: order, isVisible })}
            <TableRow class="hover:bg-muted/30 transition-colors">
                {#if isVisible('invoice_number')}
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
                {/if}
                {#if isVisible('created_at')}
                    <TableCell class="text-xs text-muted-foreground whitespace-nowrap">
                        {new Date(order.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                    </TableCell>
                {/if}
                {#if isVisible('customer_name')}
                    <TableCell class="text-xs">
                        <span class="font-medium block text-foreground">{order.customer_name || 'Pelanggan Umum'}</span>
                        {#if order.customer_phone && order.customer_phone !== '-'}
                            <span class="text-[11px] text-muted-foreground">{order.customer_phone}</span>
                        {/if}
                    </TableCell>
                {/if}
                {#if isVisible('user_id')}
                    <TableCell class="text-xs text-muted-foreground">
                        {order.user?.name || '-'}
                    </TableCell>
                {/if}
                {#if isVisible('order_status')}
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
                {/if}
                {#if isVisible('payment_status')}
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
                {/if}
                {#if isVisible('grand_total')}
                    <TableCell class="text-xs text-right font-bold text-foreground">
                        {formatRupiah(order.grand_total)}
                    </TableCell>
                {/if}
                {#if isVisible('actions')}
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
                {/if}
            </TableRow>
        {/snippet}
    </DataTable>
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

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
    import CreditCard from '@lucide/svelte/icons/credit-card';
    import Eye from '@lucide/svelte/icons/eye';
    import Filter from '@lucide/svelte/icons/filter';
    import Motorbike from '@lucide/svelte/icons/motorbike';
    import Plus from '@lucide/svelte/icons/plus';
    import Printer from '@lucide/svelte/icons/printer';
    import RotateCcw from '@lucide/svelte/icons/rotate-ccw';
    import Search from '@lucide/svelte/icons/search';
    import XCircle from '@lucide/svelte/icons/x-circle';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import LoaderCircle from '@lucide/svelte/icons/loader-circle';
    import AppHead from '@/components/AppHead.svelte';
    import DriverPickupModal from '@/components/DriverPickupModal.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { DataTable, type ColumnDef, TableCell, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';
    import { printDirectWebUSB } from '@/lib/thermal-printer';
    import { toast } from 'svelte-sonner';

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
        delivery_type?: 'pickup' | 'courier';
        courier_name?: string | null;
        booking_code?: string | null;
        driver_name?: string | null;
        delivery_status?: 'pending' | 'picked_up' | 'delivered' | null;
        created_at: string;
        user?: { name: string };
        items?: any[];
        return?: any;
    }

    let {
        orders = { data: [], links: [] },
        filters = {},
        courierWaitingCount = 0,
        receiptSettings = {},
    }: {
        orders: any;
        filters: Record<string, string>;
        courierWaitingCount?: number;
        receiptSettings?: any;
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
    let deliveryType = $state(filters.delivery_type || '');
    // svelte-ignore state_referenced_locally
    let currentPerPage = $state(filters.per_page ? parseInt(filters.per_page, 10) : 15);

    let showDriverPickupModal = $state(false);

    $effect(() => {
        search = filters.search || '';
        dateFrom = filters.date_from || '';
        dateTo = filters.date_to || '';
        orderStatus = filters.order_status || '';
        paymentStatus = filters.payment_status || '';
        paymentMethod = filters.payment_method || '';
        deliveryType = filters.delivery_type || '';
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

    // Receipt Preview Modal State
    let showReceiptPreviewModal = $state(false);
    let selectedOrderForPreview = $state<any>(null);
    let isPrintingUSB = $state(false);

    function formatNumber(num: number | null | undefined): string {
        if (!num && num !== 0) return '0';
        return Number(num).toLocaleString('id-ID');
    }

    function formatReceiptDate(dateString: string | null | undefined, includeTime = true): string {
        if (!dateString) return '-';
        const d = new Date(dateString);
        if (isNaN(d.getTime())) return String(dateString);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        if (!includeTime) {
            return `${day}-${month}-${year}`;
        }
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        return `${day}-${month}-${year} ${hours}:${minutes}`;
    }

    function formatDim(val: number | null | undefined): string {
        if (!val) return '0 cm';
        const num = Number(val);
        if (num < 1) {
            return `${Math.round(num * 100)} cm`;
        }
        return `${num} cm`;
    }

    function openReceiptPreview(order: any) {
        selectedOrderForPreview = order;
        showReceiptPreviewModal = true;
    }

    async function handlePrintUSB() {
        if (!selectedOrderForPreview) return;
        isPrintingUSB = true;
        try {
            const res = await printDirectWebUSB(selectedOrderForPreview, receiptSettings);
            if (res.success) {
                toast.success(res.message);
            } else {
                toast.error(res.message);
            }
        } catch (e: any) {
            toast.error(e.message || 'Gagal mengirim perintah cetak ke printer USB.');
        } finally {
            isPrintingUSB = false;
        }
    }

    function printReceipt() {
        const receiptEl = document.getElementById('history-thermal-receipt');
        if (!receiptEl) {
            window.print();
            return;
        }

        const printWindow = window.open('', '_blank', 'width=380,height=600');
        if (!printWindow) {
            window.print();
            return;
        }

        const invoice = selectedOrderForPreview?.invoice_number || 'Nota';

        printWindow.document.open();
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Cetak Nota - ${invoice}</title>
                <style>
                    @page {
                        size: 80mm auto;
                        margin: 0;
                    }
                    html, body {
                        width: 80mm;
                        margin: 0;
                        padding: 0;
                        background: #ffffff;
                        color: #000000;
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    }
                    .thermal-receipt-80mm {
                        display: block !important;
                        width: 100% !important;
                        max-width: 68mm !important;
                        box-sizing: border-box !important;
                        padding: 2mm 3mm 4mm 2mm !important;
                        margin-left: auto !important;
                        margin-right: auto !important;
                        background: #ffffff !important;
                        color: #000000 !important;
                        font-size: 9.5px !important;
                        line-height: 1.25 !important;
                        border: none !important;
                        outline: none !important;
                    }
                    .thermal-receipt-80mm * {
                        box-sizing: border-box !important;
                        border-left: none !important;
                        border-right: none !important;
                    }
                    .header-store {
                        text-align: center !important;
                        padding-bottom: 5px !important;
                        border-bottom: 0.5px solid #000000 !important;
                    }
                    .header-store h2, .header-store h3 {
                        font-size: 11.5px !important;
                        font-weight: 700 !important;
                        text-transform: uppercase !important;
                        margin: 0 0 2px 0 !important;
                        line-height: 1.25 !important;
                    }
                    .header-store p {
                        font-size: 8.5px !important;
                        color: #000000 !important;
                        margin: 1px 0 0 0 !important;
                        line-height: 1.2 !important;
                    }
                    .receipt-logo {
                        display: block !important;
                        max-height: 48px !important;
                        max-width: 150px !important;
                        margin: 0 auto 4px auto !important;
                        object-fit: contain !important;
                        filter: grayscale(100%) contrast(160%) !important;
                    }
                    .receipt-meta {
                        padding: 5px 0 !important;
                        border-bottom: 0.5px solid #000000 !important;
                        font-size: 9px !important;
                    }
                    .receipt-meta-grid {
                        display: grid !important;
                        grid-template-columns: 72px 8px 1fr !important;
                        row-gap: 1.5px !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-table-header {
                        display: grid !important;
                        grid-template-columns: 1fr 56px 24px 62px !important;
                        padding: 5px 0 3px 0 !important;
                        border-bottom: 0.5px solid #000000 !important;
                        font-size: 9px !important;
                        font-weight: 700 !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-item-row {
                        padding: 4px 0 !important;
                        border-bottom: 0.5px solid #000000 !important;
                        font-size: 9px !important;
                    }
                    .receipt-item-grid {
                        display: grid !important;
                        grid-template-columns: 1fr 56px 24px 62px !important;
                        align-items: start !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-col-product {
                        font-weight: 700 !important;
                        font-size: 9.5px !important;
                        padding-right: 4px !important;
                        word-break: break-word !important;
                    }
                    .receipt-col-price {
                        text-align: right !important;
                        white-space: nowrap !important;
                    }
                    .receipt-col-qty {
                        text-align: center !important;
                        white-space: nowrap !important;
                    }
                    .receipt-col-total {
                        text-align: right !important;
                        font-weight: 600 !important;
                        white-space: nowrap !important;
                        padding-right: 2px !important;
                    }
                    .receipt-item-bullets {
                        margin-top: 2.5px !important;
                        font-size: 8.5px !important;
                        color: #000000 !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-item-bullets div {
                        margin: 1px 0 !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-order-notes {
                        margin-top: 4px !important;
                        padding: 3px 0 !important;
                        border-top: 0.5px dashed #000000 !important;
                        font-size: 8.5px !important;
                        color: #000000 !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-totals {
                        padding-top: 5px !important;
                        font-size: 9px !important;
                    }
                    .receipt-totals-box {
                        margin-left: auto !important;
                        width: 165px !important;
                        margin-right: 2px !important;
                    }
                    .receipt-totals-row {
                        display: flex !important;
                        justify-content: space-between !important;
                        margin-bottom: 2px !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-totals-row.grand-total {
                        font-weight: 700 !important;
                        font-size: 10.5px !important;
                    }
                    .receipt-status {
                        margin-top: 7px !important;
                        display: flex !important;
                        align-items: baseline !important;
                        gap: 4px !important;
                        font-size: 9.5px !important;
                    }
                    .receipt-status-badge {
                        font-weight: 700 !important;
                        font-size: 12px !important;
                        letter-spacing: 0.03em !important;
                    }
                    .receipt-footer {
                        padding-top: 7px !important;
                        padding-bottom: 7px !important;
                        text-align: center !important;
                        font-size: 8.5px !important;
                        color: #000000 !important;
                        white-space: pre-line !important;
                        line-height: 1.25 !important;
                    }
                    .border-b { border-bottom: 0.5px solid #000000 !important; }
                    .border-t { border-top: 0.5px solid #000000 !important; }
                    .border-black { border-color: #000000 !important; }
                    .text-center { text-align: center; }
                    .text-right { text-align: right; }
                    .font-bold { font-weight: 700; }
                    .uppercase { text-transform: uppercase; }
                    .whitespace-pre-line { white-space: pre-line; }
                </style>
            </head>
            <body>
                <div class="thermal-receipt-80mm">
                    ${receiptEl.innerHTML}
                </div>
                <script>
                    window.addEventListener('load', () => {
                        window.focus();
                        window.print();
                        setTimeout(() => window.close(), 500);
                    });
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

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
                delivery_type: deliveryType || undefined,
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
        deliveryType = '';
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
        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                class="font-semibold whitespace-nowrap inline-flex items-center border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40"
                onclick={() => (showDriverPickupModal = true)}
                title="Pencocokan kode booking driver Gojek / Grab yang pick-up barang"
            >
                <Motorbike class="size-4 mr-1.5 text-emerald-600 dark:text-emerald-400" />
                Verifikasi Pick-up Driver
                {#if courierWaitingCount > 0}
                    <Badge variant="secondary" class="ml-1.5 h-4 px-1.5 text-[10px] bg-emerald-200 text-emerald-900 dark:bg-emerald-900 dark:text-emerald-200 font-bold">
                        {courierWaitingCount}
                    </Badge>
                {/if}
            </Button>
            <Link href="/pos">
                <Button class="bg-primary font-semibold whitespace-nowrap inline-flex items-center">
                    <Plus class="size-4 mr-1.5" />
                    Transaksi POS
                </Button>
            </Link>
        </div>
    </div>

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
            <div class="flex flex-wrap items-center gap-2 text-xs w-full">
                <!-- Date Range -->
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] text-muted-foreground whitespace-nowrap">Tanggal:</span>
                    <Input type="date" bind:value={dateFrom} onchange={() => applyFilters()} class="h-8 text-xs bg-background w-32" />
                    <span class="text-muted-foreground">-</span>
                    <Input type="date" bind:value={dateTo} onchange={() => applyFilters()} class="h-8 text-xs bg-background w-32" />
                </div>

                <!-- Status Cetak -->
                <select
                    bind:value={orderStatus}
                    onchange={() => applyFilters()}
                    class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-8 cursor-pointer font-medium"
                >
                    <option value="">Semua Status Cetak</option>
                    <option value="pending">Antrian (Pending)</option>
                    <option value="processing">Sedang Dicetak</option>
                    <option value="ready">Siap Diambil</option>
                    <option value="completed">Selesai</option>
                    <option value="cancelled">Dibatalkan</option>
                </select>

                <!-- Status Bayar -->
                <select
                    bind:value={paymentStatus}
                    onchange={() => applyFilters()}
                    class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-8 cursor-pointer font-medium"
                >
                    <option value="">Semua Status Bayar</option>
                    <option value="paid">Lunas</option>
                    <option value="partial">DP (Parsial)</option>
                    <option value="unpaid">Belum Bayar</option>
                </select>

                <!-- Metode Bayar -->
                <select
                    bind:value={paymentMethod}
                    onchange={() => applyFilters()}
                    class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-8 cursor-pointer font-medium"
                >
                    <option value="">Semua Metode Bayar</option>
                    <option value="cash">Tunai</option>
                    <option value="qris">QRIS</option>
                    <option value="transfer">Transfer Bank</option>
                    <option value="tempo">Tempo / DP</option>
                    <option value="split">Split / Termin</option>
                    <option value="pending">Bayar Saat Ambil</option>
                </select>

                <!-- Delivery Type -->
                <select
                    bind:value={deliveryType}
                    onchange={() => applyFilters()}
                    class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-8 cursor-pointer font-medium"
                >
                    <option value="">Semua Pengambilan</option>
                    <option value="pickup">Ambil di Toko</option>
                    <option value="courier">Kurir / Ojol</option>
                </select>

                <!-- Actions -->
                <div class="flex items-center gap-1.5 ml-auto">
                    <Button size="sm" class="h-8 text-xs" onclick={() => applyFilters()}>
                        <Filter class="size-3 mr-1" />
                        Terapkan
                    </Button>
                    <Button variant="outline" size="sm" class="h-8 text-xs text-muted-foreground" onclick={resetFilters}>
                        Reset
                    </Button>
                </div>
            </div>
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
                        <div class="space-y-1">
                            <div>
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
                            </div>
                            {#if order.delivery_type === 'courier'}
                                <div class="flex items-center gap-1 flex-wrap">
                                    <Badge variant="outline" class="text-[10px] bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 border-indigo-300 font-mono">
                                        <Motorbike class="size-3 mr-0.5 inline" />
                                        {order.courier_name || 'Ojol'}{order.booking_code ? `: ${order.booking_code}` : ''}
                                    </Badge>
                                    {#if order.delivery_status === 'picked_up'}
                                        <span class="text-[10px] text-emerald-600 font-semibold" title="Barang sudah diserahkan ke driver">✓ Diambil</span>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    </TableCell>
                {/if}
                {#if isVisible('payment_status')}
                    <TableCell class="text-xs">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            {#if order.payment_status === 'paid'}
                                <Badge class="bg-emerald-600 hover:bg-emerald-600 text-white text-[10px]">Lunas</Badge>
                            {:else if order.payment_status === 'partial'}
                                <Badge variant="secondary" class="bg-amber-100 text-amber-800 text-[10px]">DP / Tempo</Badge>
                            {:else if order.payment_method === 'pending'}
                                <Badge variant="outline" class="border-indigo-400 text-indigo-700 bg-indigo-50 dark:bg-indigo-950/40 text-[10px] font-semibold">
                                    Bayar Saat Ambil
                                </Badge>
                            {:else}
                                <Badge variant="destructive" class="text-[10px]">Belum Bayar</Badge>
                            {/if}
                            {#if order.payment_method !== 'pending'}
                                <span class="text-[11px] uppercase text-muted-foreground font-semibold">({order.payment_method})</span>
                            {/if}
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

                            <Button
                                size="sm"
                                variant="outline"
                                class="h-7 px-2 text-xs text-primary border-primary/30 hover:bg-primary/10"
                                title="Lihat Preview & Cetak Nota Thermal"
                                onclick={() => openReceiptPreview(order)}
                            >
                                <Printer class="size-3.5 mr-1" />
                                Nota
                            </Button>

                            {#if order.payment_status !== 'paid' && order.order_status !== 'cancelled'}
                                <Link href="/orders/{order.id}">
                                    <Button size="sm" class="h-7 px-2 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-medium" title="Catat Pelunasan Pembayaran">
                                        <CreditCard class="size-3.5 mr-1" />
                                        Bayar
                                    </Button>
                                </Link>
                            {/if}

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

<!-- Modal: Verifikasi Driver Pick-up (Gojek / Grab) -->
<DriverPickupModal bind:open={showDriverPickupModal} />

<!-- Modal: Preview & Cetak Nota Thermal 80mm -->
<Dialog open={showReceiptPreviewModal} onOpenChange={(open) => (showReceiptPreviewModal = open)}>
    <DialogContent class="sm:max-w-md max-h-[90vh] overflow-y-auto">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Printer class="size-5 text-primary" />
                Preview Nota #{selectedOrderForPreview?.invoice_number}
            </DialogTitle>
            <DialogDescription class="text-xs">
                Format nota thermal standar 80mm. Anda dapat langsung mencetak via browser atau printer direct USB.
            </DialogDescription>
        </DialogHeader>

        {#if selectedOrderForPreview}
            <div class="my-2 bg-neutral-100 dark:bg-neutral-900 p-3 rounded-lg flex justify-center">
                <div id="history-thermal-receipt" class="thermal-receipt-80mm shadow-sm border border-neutral-200 dark:border-neutral-800 rounded-sm">
                    <!-- Store Header -->
                    {#if receiptSettings.show_logo && receiptSettings.logo_path}
                        <img
                            src={receiptSettings.logo_path}
                            alt="Logo"
                            class="receipt-logo"
                        />
                    {/if}
                    <div class="header-store">
                        <h2>{receiptSettings.store_name || 'LIGHTPROJECT POS'}</h2>
                        {#if receiptSettings.tagline}
                            <p>{receiptSettings.tagline}</p>
                        {/if}
                        {#if receiptSettings.address}
                            <p>{receiptSettings.address}</p>
                        {/if}
                        {#if receiptSettings.phone}
                            <p>Telp/WA: {receiptSettings.phone}</p>
                        {/if}
                        {#if receiptSettings.website}
                            <p>{receiptSettings.website}</p>
                        {/if}
                    </div>

                    <!-- Order Metadata -->
                    <div class="receipt-meta">
                        <div class="receipt-meta-grid">
                            <span>Kode Order</span>
                            <span>:</span>
                            <span class="font-medium">{selectedOrderForPreview.invoice_number}</span>

                            <span>Tanggal</span>
                            <span>:</span>
                            <span>{formatReceiptDate(selectedOrderForPreview.created_at)}</span>

                            {#if selectedOrderForPreview.due_date}
                                <span>Deadline</span>
                                <span>:</span>
                                <span>{formatReceiptDate(selectedOrderForPreview.due_date, false)}</span>
                            {/if}

                            {#if receiptSettings.show_customer_info !== false}
                                <span>Kepada</span>
                                <span>:</span>
                                <span class="font-bold">{selectedOrderForPreview.customer_name || 'Pelanggan Umum'}{selectedOrderForPreview.customer_phone ? ` (${selectedOrderForPreview.customer_phone})` : ''}</span>
                            {/if}

                            {#if receiptSettings.show_cashier_name !== false}
                                <span>Kasir Online</span>
                                <span>:</span>
                                <span class="uppercase">{selectedOrderForPreview.user?.name || '-'}</span>
                            {/if}

                            {#if selectedOrderForPreview.delivery_type === 'courier'}
                                <span>Kurir</span>
                                <span>:</span>
                                <span class="font-bold uppercase">{selectedOrderForPreview.courier_name || 'KURIR'}{selectedOrderForPreview.booking_code ? ` (${selectedOrderForPreview.booking_code})` : ''}</span>
                            {/if}
                        </div>
                    </div>

                    <!-- Items Table Header -->
                    <div>
                        <div class="receipt-table-header">
                            <span>Produk</span>
                            <span class="receipt-col-price">Harga</span>
                            <span class="receipt-col-qty">Qty</span>
                            <span class="receipt-col-total">Total</span>
                        </div>

                        <!-- Items Rows -->
                        {#if selectedOrderForPreview.items}
                            {#each selectedOrderForPreview.items as item}
                                <div class="receipt-item-row">
                                    <div class="receipt-item-grid">
                                        <span class="receipt-col-product">{item.product_name}</span>
                                        <span class="receipt-col-price">{formatNumber(item.unit_price)}</span>
                                        <span class="receipt-col-qty">{formatNumber(item.quantity)}</span>
                                        <span class="receipt-col-total">{formatNumber(item.subtotal)}</span>
                                    </div>

                                    <div class="receipt-item-bullets">
                                        {#if Number(item.length) > 0 && Number(item.width) > 0}
                                            <div>- Ukuran P: {formatDim(item.length)} x L: {formatDim(item.width)} {item.area ? `(${item.area} m²)` : ''}</div>
                                        {/if}
                                        {#if item.product?.raw_material?.name}
                                            <div>- Bahan: {item.product.raw_material.name}</div>
                                        {/if}
                                        {#if item.finishing_option && item.finishing_option !== 'Standar' && item.finishing_option !== 'Tanpa Finishing'}
                                            <div>- {item.finishing_option}{Number(item.finishing_price) > 0 ? ` (+${formatNumber(item.finishing_price)})` : ''}</div>
                                        {/if}
                                        {#if item.design_note}
                                            <div>- Nama File: {item.design_note}</div>
                                        {/if}
                                        {#if item.product?.description}
                                            <div>- {item.product.description}</div>
                                        {/if}
                                        {#if selectedOrderForPreview.due_date}
                                            <div>- Deadline: {formatReceiptDate(selectedOrderForPreview.due_date)}</div>
                                        {/if}
                                    </div>
                                </div>
                            {/each}
                        {/if}

                        {#if selectedOrderForPreview.notes}
                            <div class="receipt-order-notes">
                                <div>- Catatan: {selectedOrderForPreview.notes}</div>
                            </div>
                        {/if}
                    </div>

                    <!-- Totals Summary -->
                    <div class="receipt-totals">
                        <div class="receipt-totals-box">
                            {#if selectedOrderForPreview.discount_amount > 0}
                                <div class="receipt-totals-row">
                                    <span>Diskon :</span>
                                    <span>-{formatNumber(selectedOrderForPreview.discount_amount)}</span>
                                </div>
                            {/if}
                            {#if selectedOrderForPreview.tax_amount && selectedOrderForPreview.tax_amount > 0}
                                <div class="receipt-totals-row">
                                    <span>PPN :</span>
                                    <span>{formatNumber(selectedOrderForPreview.tax_amount)}</span>
                                </div>
                            {/if}
                            <div class="receipt-totals-row grand-total">
                                <span>Total :</span>
                                <span>{formatNumber(selectedOrderForPreview.grand_total)}</span>
                            </div>
                            <div class="receipt-totals-row">
                                <span>Bayar :</span>
                                <span>{formatNumber(selectedOrderForPreview.paid_amount)}</span>
                            </div>
                            {#if selectedOrderForPreview.change_amount > 0}
                                <div class="receipt-totals-row">
                                    <span>Kembali :</span>
                                    <span>{formatNumber(selectedOrderForPreview.change_amount)}</span>
                                </div>
                            {/if}
                            {#if selectedOrderForPreview.grand_total > selectedOrderForPreview.paid_amount}
                                <div class="receipt-totals-row font-bold text-red-600">
                                    <span>Sisa :</span>
                                    <span>{formatNumber(selectedOrderForPreview.grand_total - selectedOrderForPreview.paid_amount)}</span>
                                </div>
                            {/if}
                        </div>

                        <!-- Status Bayar -->
                        <div class="receipt-status">
                            <span>Keterangan : </span>
                            <span class="receipt-status-badge">
                                {#if selectedOrderForPreview.payment_status === 'paid'}
                                    LUNAS
                                {:else if selectedOrderForPreview.payment_method === 'pending'}
                                    BAYAR SAAT AMBIL
                                {:else if selectedOrderForPreview.payment_status === 'partial'}
                                    DP (PARSIAL)
                                {:else}
                                    BELUM LUNAS
                                {/if}
                            </span>
                        </div>

                        {#if selectedOrderForPreview.payments && selectedOrderForPreview.payments.length > 0}
                            <div class="mt-2 pt-1 border-t border-dotted border-black text-[9px] space-y-0.5">
                                <div class="font-bold">Rincian Termin:</div>
                                {#each selectedOrderForPreview.payments as p}
                                    <div class="flex justify-between">
                                        <span>{p.term_name}:</span>
                                        <span>{formatNumber(p.status === 'paid' ? p.paid_amount : p.amount)} ({p.status === 'paid' ? 'LUNAS' : 'TEMPO'})</span>
                                    </div>
                                {/each}
                            </div>
                        {/if}
                    </div>

                    <!-- Footer -->
                    <div class="receipt-footer">
                        {receiptSettings.footer_text || 'Terima kasih atas pesanan cetak Anda!'}
                    </div>
                </div>
            </div>
        {/if}

        <DialogFooter class="flex flex-col sm:flex-row gap-2 justify-between items-center w-full">
            {#if selectedOrderForPreview}
                <Link href="/orders/{selectedOrderForPreview.id}">
                    <Button variant="ghost" size="sm" class="text-xs">
                        <ExternalLink class="size-3.5 mr-1" />
                        Buka Halaman Detail
                    </Button>
                </Link>
            {/if}
            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" onclick={() => (showReceiptPreviewModal = false)}>
                    Tutup
                </Button>
                <Button variant="outline" size="sm" onclick={printReceipt}>
                    <Printer class="size-4 mr-1.5" />
                    Cetak Browser
                </Button>
                <Button size="sm" class="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold" onclick={handlePrintUSB} disabled={isPrintingUSB}>
                    {#if isPrintingUSB}
                        <LoaderCircle class="size-4 mr-1.5 animate-spin" />
                        Mencetak...
                    {:else}
                        <Printer class="size-4 mr-1.5" />
                        Cetak USB
                    {/if}
                </Button>
            </div>
        </DialogFooter>
    </DialogContent>
</Dialog>

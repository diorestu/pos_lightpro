<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Riwayat Penjualan',
                href: '/orders',
            },
            {
                title: 'Detail Nota',
                href: '#',
            },
        ],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import AlertTriangle from '@lucide/svelte/icons/alert-triangle';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import CheckCircle from '@lucide/svelte/icons/check-circle';
    import Clock from '@lucide/svelte/icons/clock';
    import CreditCard from '@lucide/svelte/icons/credit-card';
    import DollarSign from '@lucide/svelte/icons/dollar-sign';
    import FileText from '@lucide/svelte/icons/file-text';
    import Printer from '@lucide/svelte/icons/printer';
    import RotateCcw from '@lucide/svelte/icons/rotate-ccw';
    import User from '@lucide/svelte/icons/user';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

    interface OrderDetail {
        id: number;
        invoice_number: string;
        customer_name: string;
        customer_phone: string;
        subtotal: number;
        discount_amount: number;
        tax_amount: number;
        grand_total: number;
        paid_amount: number;
        change_amount: number;
        payment_method: string;
        payment_status: 'paid' | 'partial' | 'unpaid';
        order_status: 'pending' | 'processing' | 'ready' | 'completed' | 'cancelled';
        cancellation_reason?: string;
        cancelled_at?: string;
        due_date?: string;
        notes?: string;
        created_at: string;
        user?: { name: string; email: string };
        customer?: { name: string; phone: string; address: string; customer_type: string };
        cancelled_by_user?: { name: string };
        items: any[];
        return?: any;
    }

    let {
        order,
        receiptSettings = {},
    }: {
        order: OrderDetail;
        receiptSettings: any;
    } = $props();

    // Settle payment modal
    let showPayModal = $state(false);
    let payAmount = $state('');
    let payMethod = $state<'cash' | 'transfer' | 'qris'>('cash');
    let payNotes = $state('');

    const remainingDebt = $derived(
        Math.max(0, order.grand_total - order.paid_amount)
    );

    function formatRupiah(amount: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);
    }

    function printReceipt() {
        window.print();
    }

    function updateStatus(newStatus: string) {
        router.patch(`/orders/${order.id}/status`, { order_status: newStatus });
    }

    function submitPayment() {
        router.post(
            `/orders/${order.id}/payment`,
            {
                amount: parseFloat(payAmount) || 0,
                payment_method: payMethod,
                notes: payNotes,
            },
            {
                onSuccess: () => {
                    showPayModal = false;
                },
            }
        );
    }
</script>

<AppHead title="Detail Nota #{order.invoice_number}" />

<!-- Print-Only Receipt Thermal View (visible only on @media print) -->
<div class="hidden print:block print:w-[320px] print:mx-auto print:p-2 print:text-black print:text-xs">
    <div class="text-center pb-2 border-b border-dashed border-black">
        <h2 class="font-bold text-sm uppercase">{receiptSettings.store_name || 'LightPrint Percetakan'}</h2>
        <p class="text-[10px]">{receiptSettings.tagline || 'Solusi Cetak Cepat & Berkualitas'}</p>
        <p class="text-[10px]">{receiptSettings.address || 'Jakarta'}</p>
        <p class="text-[10px]">Telp/WA: {receiptSettings.phone || '-'}</p>
    </div>

    <div class="py-2 text-[10px] border-b border-dashed border-black space-y-0.5">
        <div class="flex justify-between">
            <span>No: {order.invoice_number}</span>
            <span>{new Date(order.created_at).toLocaleDateString('id-ID')}</span>
        </div>
        {#if receiptSettings.show_cashier_name}
            <div>Kasir: {order.user?.name || '-'}</div>
        {/if}
        {#if receiptSettings.show_customer_info}
            <div>Pelanggan: {order.customer_name || 'Umum'}</div>
        {/if}
    </div>

    <div class="py-2 border-b border-dashed border-black space-y-1.5 text-[10px]">
        {#each order.items as item}
            <div>
                <div class="font-bold">{item.product_name}</div>
                {#if item.pricing_type === 'dimension_sqm'}
                    <div class="text-[9px]">
                        Ukuran: {item.length}m × {item.width}m ({item.area} m²) × {item.quantity} pcs
                    </div>
                {:else}
                    <div class="text-[9px]">
                        Qty: {item.quantity} {item.unit} @ {formatRupiah(item.unit_price)}
                    </div>
                {/if}
                {#if item.finishing_option && item.finishing_option !== 'Standar'}
                    <div class="text-[9px]">Finishing: {item.finishing_option}</div>
                {/if}
                <div class="text-right font-bold">{formatRupiah(item.subtotal)}</div>
            </div>
        {/each}
    </div>

    <div class="py-2 border-b border-dashed border-black space-y-0.5 text-[10px]">
        <div class="flex justify-between">
            <span>Subtotal:</span>
            <span>{formatRupiah(order.subtotal)}</span>
        </div>
        {#if order.discount_amount > 0}
            <div class="flex justify-between">
                <span>Diskon:</span>
                <span>-{formatRupiah(order.discount_amount)}</span>
            </div>
        {/if}
        <div class="flex justify-between font-bold text-xs pt-1 border-t border-black">
            <span>TOTAL:</span>
            <span>{formatRupiah(order.grand_total)}</span>
        </div>
        <div class="flex justify-between">
            <span>Bayar ({order.payment_method.toUpperCase()}):</span>
            <span>{formatRupiah(order.paid_amount)}</span>
        </div>
        <div class="flex justify-between">
            <span>Kembalian:</span>
            <span>{formatRupiah(order.change_amount)}</span>
        </div>
        {#if order.payment_status !== 'paid'}
            <div class="flex justify-between font-bold text-red-600">
                <span>Sisa Tagihan:</span>
                <span>{formatRupiah(order.grand_total - order.paid_amount)}</span>
            </div>
        {/if}
    </div>

    <div class="pt-3 text-center text-[9px] whitespace-pre-line">
        {receiptSettings.footer_text || 'Terima kasih telah mencetak di tempat kami!'}
    </div>
</div>

<!-- Interactive Web Management View -->
<div class="flex flex-col gap-6 p-4 md:p-6 max-w-5xl mx-auto w-full print:hidden">
    <!-- Back & Actions Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
        <div class="flex items-center gap-3">
            <Link href="/orders">
                <Button variant="outline" size="sm" class="h-8">
                    <ArrowLeft class="size-4 mr-1" />
                    Kembali
                </Button>
            </Link>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
                    Nota #{order.invoice_number}
                    {#if order.order_status === 'completed'}
                        <Badge class="bg-emerald-600 text-white">Selesai</Badge>
                    {:else if order.order_status === 'processing'}
                        <Badge class="bg-blue-600 text-white">Sedang Dicetak</Badge>
                    {:else if order.order_status === 'ready'}
                        <Badge class="bg-purple-600 text-white">Siap Diambil</Badge>
                    {:else if order.order_status === 'pending'}
                        <Badge class="bg-amber-600 text-white">Antrian</Badge>
                    {:else if order.order_status === 'cancelled'}
                        <Badge variant="destructive">Dibatalkan</Badge>
                    {/if}
                </h1>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Dibuat pada {new Date(order.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            {#if remainingDebt > 0 && order.order_status !== 'cancelled'}
                <Button
                    size="sm"
                    class="bg-amber-600 hover:bg-amber-700 text-white font-semibold"
                    onclick={() => {
                        payAmount = remainingDebt.toString();
                        showPayModal = true;
                    }}
                >
                    <DollarSign class="size-3.5 mr-1" />
                    Lunasi Sisa Tagihan ({formatRupiah(remainingDebt)})
                </Button>
            {/if}

            <Button size="sm" variant="outline" onclick={printReceipt}>
                <Printer class="size-4 mr-1.5" />
                Cetak Nota Fisik
            </Button>
        </div>
    </div>

    <!-- Cancellation Notice Banner if Cancelled -->
    {#if order.order_status === 'cancelled'}
        <div class="rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-xs text-destructive space-y-1">
            <div class="font-bold text-sm flex items-center gap-1.5">
                <AlertTriangle class="size-4" />
                Pesanan Ini Telah Dibatalkan
            </div>
            <div>
                <strong>Alasan:</strong> {order.cancellation_reason || 'Tidak ada alasan dicatat.'}
            </div>
            {#if order.cancelled_by_user}
                <div>Dibatalkan oleh: {order.cancelled_by_user.name} ({order.cancelled_at})</div>
            {/if}
        </div>
    {/if}

    <!-- Return Notice Banner if Returned -->
    {#if order.return}
        <div class="rounded-xl border border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30 p-4 text-xs text-amber-900 dark:text-amber-200 space-y-1">
            <div class="font-bold text-sm flex items-center gap-1.5 text-amber-800 dark:text-amber-300">
                <RotateCcw class="size-4" />
                Pesanan Ini Memiliki Riwayat Retur (#{order.return.return_number})
            </div>
            <div><strong>Alasan Retur:</strong> {order.return.reason}</div>
            <div><strong>Nominal Refund:</strong> {formatRupiah(order.return.refund_amount)}</div>
        </div>
    {/if}

    <!-- Status Production Pipeline Changer -->
    {#if order.order_status !== 'cancelled'}
        <Card class="border shadow-2xs">
            <CardHeader class="pb-2.5">
                <CardTitle class="text-xs uppercase tracking-wider text-muted-foreground font-semibold">
                    Alur Produksi Cetak (Update Status)
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <Button
                        size="sm"
                        variant={order.order_status === 'pending' ? 'default' : 'outline'}
                        class="text-xs justify-start"
                        onclick={() => updateStatus('pending')}
                    >
                        <Clock class="size-3.5 mr-1.5" />
                        1. Antrian Desain/File
                    </Button>
                    <Button
                        size="sm"
                        variant={order.order_status === 'processing' ? 'default' : 'outline'}
                        class="text-xs justify-start"
                        onclick={() => updateStatus('processing')}
                    >
                        <Printer class="size-3.5 mr-1.5" />
                        2. Sedang Dicetak Mesin
                    </Button>
                    <Button
                        size="sm"
                        variant={order.order_status === 'ready' ? 'default' : 'outline'}
                        class="text-xs justify-start"
                        onclick={() => updateStatus('ready')}
                    >
                        <CheckCircle class="size-3.5 mr-1.5" />
                        3. Siap Diambil Pelanggan
                    </Button>
                    <Button
                        size="sm"
                        variant={order.order_status === 'completed' ? 'default' : 'outline'}
                        class="text-xs justify-start"
                        onclick={() => updateStatus('completed')}
                    >
                        <CheckCircle class="size-3.5 mr-1.5" />
                        4. Selesai / Diserahkan
                    </Button>
                </div>
            </CardContent>
        </Card>
    {/if}

    <!-- Order Summary Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
        <!-- Customer Info -->
        <Card class="border shadow-2xs">
            <CardHeader class="pb-2">
                <CardTitle class="text-xs uppercase tracking-wider text-muted-foreground font-semibold flex items-center gap-1.5">
                    <User class="size-3.5 text-primary" />
                    Data Pelanggan
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-1">
                <div class="font-bold text-sm text-foreground">{order.customer_name || 'Pelanggan Umum'}</div>
                <div class="text-muted-foreground">Telp/WA: {order.customer_phone || '-'}</div>
                {#if order.customer}
                    <div class="text-muted-foreground capitalize">Tipe: {order.customer.customer_type}</div>
                    {#if order.customer.address}
                        <div class="text-muted-foreground text-[11px] mt-1">{order.customer.address}</div>
                    {/if}
                {/if}
            </CardContent>
        </Card>

        <!-- Payment Info -->
        <Card class="border shadow-2xs">
            <CardHeader class="pb-2">
                <CardTitle class="text-xs uppercase tracking-wider text-muted-foreground font-semibold flex items-center gap-1.5">
                    <CreditCard class="size-3.5 text-primary" />
                    Informasi Pembayaran
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-1">
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Metode Bayar:</span>
                    <span class="font-bold uppercase text-foreground">{order.payment_method}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Status Bayar:</span>
                    <span>
                        {#if order.payment_status === 'paid'}
                            <Badge class="bg-emerald-600 text-white text-[10px]">Lunas</Badge>
                        {:else if order.payment_status === 'partial'}
                            <Badge variant="secondary" class="bg-amber-100 text-amber-800 text-[10px]">DP (Parsial)</Badge>
                        {:else}
                            <Badge variant="destructive" class="text-[10px]">Belum Bayar</Badge>
                        {/if}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Telah Dibayar:</span>
                    <span class="font-semibold">{formatRupiah(order.paid_amount)}</span>
                </div>
                {#if remainingDebt > 0}
                    <div class="flex justify-between text-amber-700 dark:text-amber-400 font-semibold pt-1 border-t">
                        <span>Sisa Tagihan:</span>
                        <span>{formatRupiah(remainingDebt)}</span>
                    </div>
                {/if}
            </CardContent>
        </Card>

        <!-- Cashier & Notes -->
        <Card class="border shadow-2xs">
            <CardHeader class="pb-2">
                <CardTitle class="text-xs uppercase tracking-wider text-muted-foreground font-semibold flex items-center gap-1.5">
                    <FileText class="size-3.5 text-primary" />
                    Kasir & Catatan
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-1">
                <div>Kasir: <strong>{order.user?.name || '-'}</strong></div>
                {#if order.due_date}
                    <div>Jatuh Tempo: <strong>{new Date(order.due_date).toLocaleDateString('id-ID')}</strong></div>
                {/if}
                {#if order.notes}
                    <div class="mt-2 text-[11px] bg-muted/40 p-2 rounded border whitespace-pre-line text-muted-foreground">
                        {order.notes}
                    </div>
                {/if}
            </CardContent>
        </Card>
    </div>

    <!-- Items Table -->
    <Card class="border shadow-xs">
        <CardHeader class="pb-3">
            <CardTitle class="text-sm font-semibold">Rincian Barang Cetakan</CardTitle>
        </CardHeader>
        <CardContent class="p-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="text-xs">Produk / Layanan</TableHead>
                        <TableHead class="text-xs">Dimensi / Spesifikasi</TableHead>
                        <TableHead class="text-xs">Finishing</TableHead>
                        <TableHead class="text-xs">Qty</TableHead>
                        <TableHead class="text-xs text-right">Harga Satuan</TableHead>
                        <TableHead class="text-xs text-right">Subtotal</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {#each order.items as item}
                        <TableRow>
                            <TableCell class="text-xs">
                                <span class="font-semibold block text-foreground">{item.product_name}</span>
                                {#if item.design_note}
                                    <span class="text-[11px] text-muted-foreground flex items-center gap-1 mt-0.5">
                                        File/Ket: {item.design_note}
                                    </span>
                                {/if}
                            </TableCell>
                            <TableCell class="text-xs">
                                {#if item.pricing_type === 'dimension_sqm'}
                                    <span class="text-xs font-medium">
                                        {item.length}m × {item.width}m ({item.area} m²)
                                    </span>
                                {:else}
                                    <span class="text-muted-foreground">
                                        Standar ({item.unit})
                                    </span>
                                {/if}
                            </TableCell>
                            <TableCell class="text-xs">
                                <Badge variant="outline" class="text-[11px]">
                                    {item.finishing_option || 'Standar'}
                                </Badge>
                                {#if item.finishing_price > 0}
                                    <span class="text-[10px] text-muted-foreground block mt-0.5">
                                        +{formatRupiah(item.finishing_price)}
                                    </span>
                                {/if}
                            </TableCell>
                            <TableCell class="text-xs font-semibold">
                                {item.quantity} {item.unit}
                            </TableCell>
                            <TableCell class="text-xs text-right">
                                {formatRupiah(item.unit_price)}
                            </TableCell>
                            <TableCell class="text-xs text-right font-bold">
                                {formatRupiah(item.subtotal)}
                            </TableCell>
                        </TableRow>
                    {/each}
                </TableBody>
            </Table>

            <!-- Totals Footer -->
            <div class="p-4 bg-muted/20 border-t flex flex-col items-end gap-1 text-xs">
                <div class="flex justify-between w-64 text-muted-foreground">
                    <span>Subtotal Item:</span>
                    <span>{formatRupiah(order.subtotal)}</span>
                </div>
                {#if order.discount_amount > 0}
                    <div class="flex justify-between w-64 text-emerald-600 font-medium">
                        <span>Diskon Khusus:</span>
                        <span>-{formatRupiah(order.discount_amount)}</span>
                    </div>
                {/if}
                <div class="flex justify-between w-64 font-bold text-sm text-foreground pt-1.5 border-t">
                    <span>Grand Total:</span>
                    <span class="text-base text-primary">{formatRupiah(order.grand_total)}</span>
                </div>
                <div class="flex justify-between w-64 text-muted-foreground">
                    <span>Jumlah Dibayar:</span>
                    <span>{formatRupiah(order.paid_amount)}</span>
                </div>
                <div class="flex justify-between w-64 text-muted-foreground">
                    <span>Kembalian:</span>
                    <span>{formatRupiah(order.change_amount)}</span>
                </div>
            </div>
        </CardContent>
    </Card>
</div>

<!-- Modal: Pelunasan Sisa Tagihan (Tempo / DP) -->
<Dialog open={showPayModal} onOpenChange={(open) => (showPayModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <DollarSign class="size-5 text-primary" />
                Pelunasan Sisa Tagihan #{order.invoice_number}
            </DialogTitle>
            <DialogDescription class="text-xs">
                Catat pelunasan sisa biaya cetak saat pelanggan mengambil barang.
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
            <div class="space-y-1">
                <Label for="p_amt">Nominal Pelunasan (Rp) *</Label>
                <Input
                    id="p_amt"
                    type="number"
                    bind:value={payAmount}
                    class="font-bold text-base"
                />
                <span class="text-[10px] text-muted-foreground">
                    Total sisa tagihan: {formatRupiah(remainingDebt)}
                </span>
            </div>

            <div class="space-y-1">
                <Label for="p_met">Metode Pembayaran</Label>
                <select id="p_met" bind:value={payMethod} class="w-full h-9 rounded-md border border-input bg-background pl-3 pr-8">
                    <option value="cash">Tunai (Cash)</option>
                    <option value="qris">QRIS</option>
                    <option value="transfer">Transfer Bank</option>
                </select>
            </div>

            <div class="space-y-1">
                <Label for="p_notes">Catatan Pelunasan</Label>
                <Input id="p_notes" bind:value={payNotes} placeholder="Pelunasan saat pengambilan spanduk..." />
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (showPayModal = false)}>Batal</Button>
            <Button class="bg-primary" onclick={submitPayment} disabled={!payAmount}>
                Simpan Pelunasan
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

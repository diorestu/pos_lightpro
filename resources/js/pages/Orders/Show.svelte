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
    import { Link, page, router } from '@inertiajs/svelte';
    import AlertTriangle from '@lucide/svelte/icons/alert-triangle';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import Calendar from '@lucide/svelte/icons/calendar';
    import CheckCircle from '@lucide/svelte/icons/check-circle';
    import Clock from '@lucide/svelte/icons/clock';
    import CreditCard from '@lucide/svelte/icons/credit-card';
    import DollarSign from '@lucide/svelte/icons/dollar-sign';
    import Eye from '@lucide/svelte/icons/eye';
    import FileText from '@lucide/svelte/icons/file-text';
    import Plus from '@lucide/svelte/icons/plus';
    import Printer from '@lucide/svelte/icons/printer';
    import RotateCcw from '@lucide/svelte/icons/rotate-ccw';
    import Split from '@lucide/svelte/icons/split';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import Usb from '@lucide/svelte/icons/usb';
    import User from '@lucide/svelte/icons/user';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
    import { toast } from 'svelte-sonner';
    import { printDirectWebUSB, isWebUSBSupported } from '@/lib/thermal-printer';

    interface OrderPaymentItem {
        id: number;
        order_id: number;
        cashier_shift_id?: number | null;
        user_id?: number | null;
        term_index: number;
        term_name: string;
        amount: number;
        paid_amount: number;
        payment_method: string;
        status: 'paid' | 'pending' | 'partial';
        due_date?: string | null;
        paid_at?: string | null;
        notes?: string | null;
        user?: { name: string };
        shift?: { id: number };
    }

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
        payments?: OrderPaymentItem[];
        return?: any;
    }

    let {
        order,
        receiptSettings = {},
    }: {
        order: OrderDetail;
        receiptSettings: any;
    } = $props();

    const isKasir = $derived(page.props.auth?.user?.role === 'kasir');

    // Settle payment modal
    let showPayModal = $state(false);
    let selectedTerm = $state<OrderPaymentItem | null>(null);
    let payAmount = $state('');
    let payMethod = $state<'cash' | 'transfer' | 'qris'>('cash');
    let payNotes = $state('');

    // Add new term modal
    let showAddTermModal = $state(false);
    let newTermName = $state('');
    let newTermAmount = $state('');
    let newTermDueDate = $state('');
    let newTermNotes = $state('');

    // Thermal Printer USB & Preview State
    let isPrintingUSB = $state(false);
    let showReceiptPreviewModal = $state(false);

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

    async function handlePrintUSB() {
        isPrintingUSB = true;
        try {
            const res = await printDirectWebUSB(order, receiptSettings);
            if (res.success) {
                toast.success(res.message);
            } else {
                toast.error(res.message);
            }
        } catch (err: any) {
            toast.error(err?.message || 'Gagal mengirim data ke printer USB.');
        } finally {
            isPrintingUSB = false;
        }
    }

    function printReceipt() {
        window.print();
    }

    function updateStatus(newStatus: string) {
        router.patch(`/orders/${order.id}/status`, { order_status: newStatus });
    }

    function openPayTermModal(term: OrderPaymentItem) {
        selectedTerm = term;
        const owed = Math.max(0, Number(term.amount) - Number(term.paid_amount));
        payAmount = Math.round(owed > 0 ? owed : Number(term.amount) || 0).toString();
        payMethod = (term.payment_method as any) || 'cash';
        payNotes = `Pembayaran ${term.term_name}`;
        showPayModal = true;
    }

    function openGeneralPayModal() {
        selectedTerm = null;
        payAmount = Math.round(remainingDebt).toString();
        payMethod = 'cash';
        payNotes = 'Pelunasan sisa tagihan';
        showPayModal = true;
    }

    function submitPayment() {
        router.post(
            `/orders/${order.id}/payment`,
            {
                order_payment_id: selectedTerm ? selectedTerm.id : null,
                amount: parseFloat(payAmount) || 0,
                payment_method: payMethod,
                notes: payNotes,
            },
            {
                onSuccess: () => {
                    showPayModal = false;
                    payAmount = '';
                    payNotes = '';
                    selectedTerm = null;
                },
            }
        );
    }

    function openAddTermModal() {
        const nextIdx = (order.payments?.length || 0) + 1;
        newTermName = `Termin ${nextIdx} (Pelunasan Akhir)`;
        newTermAmount = remainingDebt > 0 ? remainingDebt.toString() : '';
        const d = new Date();
        d.setDate(d.getDate() + 7);
        newTermDueDate = d.toISOString().split('T')[0];
        newTermNotes = '';
        showAddTermModal = true;
    }

    function submitNewTerm() {
        router.post(
            `/orders/${order.id}/terms`,
            {
                term_name: newTermName,
                amount: parseFloat(newTermAmount) || 0,
                due_date: newTermDueDate || null,
                notes: newTermNotes || null,
            },
            {
                onSuccess: () => {
                    showAddTermModal = false;
                },
            }
        );
    }

    function deleteTerm(paymentId: number) {
        if (confirm('Hapus jadwal termin pembayaran ini?')) {
            router.delete(`/orders/${order.id}/terms/${paymentId}`);
        }
    }
</script>

<AppHead title="Detail Nota #{order.invoice_number}" />

<!-- Print-Only Receipt Thermal 80mm View (Standard Font & 80mm Paper) -->
<div class="hidden print:block thermal-receipt-80mm text-black font-sans text-xs">
    <!-- Store Header -->
    <div class="text-center pb-2 border-b border-dashed border-black">
        {#if receiptSettings.show_logo !== false && receiptSettings.logo_url}
            <div class="flex justify-center mb-1.5">
                <img
                    src={receiptSettings.logo_url}
                    alt="Logo {receiptSettings.store_name || 'Toko'}"
                    class="receipt-logo max-h-14 max-w-[160px] object-contain filter grayscale contrast-125 mx-auto block"
                />
            </div>
        {/if}
        <h2 class="font-bold text-sm tracking-tight uppercase">{receiptSettings.store_name || 'LightPrint Percetakan'}</h2>
        {#if receiptSettings.tagline}
            <p class="text-[10px] text-neutral-800">{receiptSettings.tagline}</p>
        {/if}
        {#if receiptSettings.address}
            <p class="text-[10px] text-neutral-800">{receiptSettings.address}</p>
        {/if}
        {#if receiptSettings.phone}
            <p class="text-[10px] font-medium text-neutral-800">Telp/WA: {receiptSettings.phone}</p>
        {/if}
        {#if receiptSettings.website}
            <p class="text-[9px] text-neutral-700">{receiptSettings.website}</p>
        {/if}
    </div>

    <!-- Order Metadata -->
    <div class="py-2 text-[10px] border-b border-dashed border-black space-y-0.5">
        <div class="flex justify-between font-bold">
            <span>No. Nota:</span>
            <span>{order.invoice_number}</span>
        </div>
        <div class="flex justify-between">
            <span>Tanggal:</span>
            <span>{new Date(order.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
        </div>
        {#if receiptSettings.show_cashier_name !== false}
            <div class="flex justify-between">
                <span>Kasir:</span>
                <span>{order.user?.name || '-'}</span>
            </div>
        {/if}
        {#if receiptSettings.show_customer_info !== false}
            <div class="flex justify-between">
                <span>Pelanggan:</span>
                <span class="font-semibold">{order.customer_name || 'Pelanggan Umum'}{order.customer_phone ? ` (${order.customer_phone})` : ''}</span>
            </div>
        {/if}
        <div class="flex justify-between">
            <span>Metode / Status:</span>
            <span class="font-bold uppercase">
                {order.payment_method} / {order.payment_status === 'paid' ? 'LUNAS' : order.payment_status === 'partial' ? 'DP (PARSIAL)' : 'BELUM BAYAR'}
            </span>
        </div>
    </div>

    <!-- Items List (80mm Width) -->
    <div class="py-2 border-b border-dashed border-black space-y-2 text-[10px]">
        {#each order.items as item, idx}
            <div>
                <div class="font-bold flex justify-between">
                    <span>{idx + 1}. {item.product_name}</span>
                    <span class="font-bold">{formatRupiah(item.subtotal)}</span>
                </div>
                {#if item.pricing_type === 'dimension_sqm'}
                    <div class="text-[9px] text-neutral-700 pl-2">
                        Ukuran: {item.length}m × {item.width}m ({item.area} m²) × {item.quantity} pcs @ {formatRupiah(item.unit_price)}
                    </div>
                {:else}
                    <div class="text-[9px] text-neutral-700 pl-2">
                        Qty: {item.quantity} {item.unit || 'pcs'} × @ {formatRupiah(item.unit_price)}
                    </div>
                {/if}
                {#if item.finishing_option && item.finishing_option !== 'Standar' && item.finishing_option !== 'Tanpa Finishing'}
                    <div class="text-[9px] text-neutral-700 pl-2 font-medium">
                        Finishing: {item.finishing_option}
                    </div>
                {/if}
                {#if item.design_note}
                    <div class="text-[9px] italic text-neutral-600 pl-2">
                        Catatan: {item.design_note}
                    </div>
                {/if}
            </div>
        {/each}
    </div>

    <!-- Totals -->
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
        {#if order.tax_amount && order.tax_amount > 0}
            <div class="flex justify-between">
                <span>PPN / Pajak:</span>
                <span>{formatRupiah(order.tax_amount)}</span>
            </div>
        {/if}
        <div class="flex justify-between font-bold text-xs pt-1 border-t border-black">
            <span>TOTAL:</span>
            <span>{formatRupiah(order.grand_total)}</span>
        </div>
        <div class="flex justify-between">
            <span>Total Bayar ({order.payment_method.toUpperCase()}):</span>
            <span class="font-bold">{formatRupiah(order.paid_amount)}</span>
        </div>
        <div class="flex justify-between">
            <span>Kembalian:</span>
            <span>{formatRupiah(order.change_amount)}</span>
        </div>
        {#if remainingDebt > 0}
            <div class="flex justify-between font-bold text-red-600 pt-0.5 border-t border-dotted border-black">
                <span>Sisa Tagihan / Tempo:</span>
                <span>{formatRupiah(remainingDebt)}</span>
            </div>
        {/if}

        {#if order.payments && order.payments.length > 0}
            <div class="pt-1.5 mt-1 border-t border-dotted border-black space-y-0.5">
                <div class="font-bold text-[9px]">Rincian Termin / Tahapan Pembayaran:</div>
                {#each order.payments as p}
                    <div class="flex justify-between text-[9px]">
                        <span>{p.term_name}:</span>
                        <span>{formatRupiah(p.status === 'paid' ? p.paid_amount : p.amount)} ({p.status === 'paid' ? 'Lunas ' + p.payment_method.toUpperCase() : 'Tempo'})</span>
                    </div>
                {/each}
            </div>
        {/if}
    </div>

    <!-- Footer Terms & Cut Area -->
    <div class="pt-3 pb-6 text-center text-[9px] text-neutral-800 whitespace-pre-line leading-relaxed">
        {receiptSettings.footer_text || 'Terima kasih telah mencetak di tempat kami!\nPeriksa kembali hasil cetakan Anda.'}
        <div class="mt-2 text-[8px] text-neutral-500 font-semibold uppercase tracking-wider">
            *** LIGHTPRINT POS 80MM ***
        </div>
    </div>
</div>

<!-- Interactive Web Management View -->
<div class="flex flex-col gap-6 p-4 md:p-6 max-w-5xl mx-auto w-full print:hidden">
    <!-- Back & Actions Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
        <div class="flex items-center gap-3">
            <Link href={isKasir ? '/pos' : '/orders'}>
                <Button variant="outline" size="sm" class="h-8">
                    <ArrowLeft class="size-4 mr-1" />
                    {isKasir ? 'Kembali ke Kasir (POS)' : 'Kembali'}
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

        <div class="flex flex-wrap items-center gap-2">
            {#if remainingDebt > 0 && order.order_status !== 'cancelled'}
                <Button
                    size="sm"
                    class="bg-amber-600 hover:bg-amber-700 text-white font-semibold"
                    onclick={openGeneralPayModal}
                >
                    <DollarSign class="size-3.5 mr-1" />
                    Lunasi Sisa Tagihan ({formatRupiah(remainingDebt)})
                </Button>
            {/if}

            <Button
                size="sm"
                variant="outline"
                class="border-neutral-300 hover:bg-neutral-100 dark:border-neutral-700 font-medium"
                onclick={() => (showReceiptPreviewModal = true)}
            >
                <Eye class="size-4 mr-1.5" />
                Pratinjau Nota (80mm)
            </Button>

            <Button
                size="sm"
                class="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold shadow-xs"
                disabled={isPrintingUSB}
                onclick={handlePrintUSB}
            >
                <Usb class="size-4 mr-1.5" />
                {isPrintingUSB ? 'Mengirim ke USB...' : 'Cetak USB Thermal (80mm)'}
            </Button>

            <Button size="sm" variant="outline" onclick={printReceipt}>
                <Printer class="size-4 mr-1.5" />
                Dialog Cetak
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

    <!-- Split Payment & Termin Schedule Card -->
    <Card class="border shadow-xs">
        <CardHeader class="pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b">
            <div>
                <CardTitle class="text-sm font-semibold flex items-center gap-2">
                    <Split class="size-4 text-amber-600" />
                    Jadwal & Riwayat Termin Pembayaran (Split Payment)
                    {#if order.payments && order.payments.length > 0}
                        <Badge variant="outline" class="text-[10px] font-bold">
                            {order.payments.length} Termin
                        </Badge>
                    {/if}
                </CardTitle>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Pelacakan pembayaran uang muka (DP), cicilan bertahap, dan pelunasan nota
                </p>
            </div>
            {#if remainingDebt > 0 && order.order_status !== 'cancelled'}
                <div class="flex items-center gap-2">
                    <Button size="sm" variant="outline" class="h-8 text-xs font-semibold" onclick={openAddTermModal}>
                        <Plus class="size-3.5 mr-1" />
                        Tambah Termin
                    </Button>
                    <Button size="sm" class="h-8 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white" onclick={openGeneralPayModal}>
                        <DollarSign class="size-3.5 mr-1" />
                        Pelunasan Langsung
                    </Button>
                </div>
            {/if}
        </CardHeader>
        <CardContent class="p-0">
            {#if !order.payments || order.payments.length === 0}
                <div class="py-6 text-center text-xs text-muted-foreground">
                    Tidak ada rincian termin terpisah untuk transaksi ini.
                </div>
            {:else}
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-xs">Termin</TableHead>
                            <TableHead class="text-xs text-right">Target Nominal</TableHead>
                            <TableHead class="text-xs text-right">Telah Dibayar</TableHead>
                            <TableHead class="text-xs text-right">Sisa</TableHead>
                            <TableHead class="text-xs">Metode</TableHead>
                            <TableHead class="text-xs">Jatuh Tempo / Tgl Bayar</TableHead>
                            <TableHead class="text-xs text-center">Status</TableHead>
                            <TableHead class="text-xs text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {#each order.payments as p}
                            {@const termOwed = Math.max(0, Number(p.amount) - Number(p.paid_amount))}
                            <TableRow>
                                <TableCell class="text-xs">
                                    <div class="font-bold text-foreground">{p.term_name}</div>
                                    {#if p.notes}
                                        <div class="text-[11px] text-muted-foreground mt-0.5">{p.notes}</div>
                                    {/if}
                                </TableCell>
                                <TableCell class="text-xs text-right font-medium">
                                    {formatRupiah(p.amount)}
                                </TableCell>
                                <TableCell class="text-xs text-right font-bold text-emerald-600">
                                    {formatRupiah(p.paid_amount)}
                                </TableCell>
                                <TableCell class="text-xs text-right font-bold {termOwed > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-muted-foreground'}">
                                    {formatRupiah(termOwed)}
                                </TableCell>
                                <TableCell class="text-xs">
                                    <Badge variant="outline" class="uppercase text-[10px] font-semibold">
                                        {p.payment_method || '-'}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    {#if p.status === 'paid' && p.paid_at}
                                        <div class="text-foreground font-medium">
                                            {new Date(p.paid_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                        </div>
                                        {#if p.user}
                                            <div class="text-[10px] text-muted-foreground">Kasir: {p.user.name}</div>
                                        {/if}
                                    {:else if p.due_date}
                                        <div class="text-amber-800 dark:text-amber-300 font-medium flex items-center gap-1">
                                            <Calendar class="size-3" />
                                            Tempo: {new Date(p.due_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}
                                        </div>
                                    {:else}
                                        -
                                    {/if}
                                </TableCell>
                                <TableCell class="text-xs text-center">
                                    {#if p.status === 'paid'}
                                        <Badge class="bg-emerald-600 text-white text-[10px]">
                                            ✓ Lunas
                                        </Badge>
                                    {:else if p.status === 'partial'}
                                        <Badge variant="secondary" class="bg-amber-100 text-amber-800 text-[10px]">
                                            DP / Parsial
                                        </Badge>
                                    {:else}
                                        <Badge variant="outline" class="border-amber-400 text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/30 text-[10px]">
                                            Menunggu
                                        </Badge>
                                    {/if}
                                </TableCell>
                                <TableCell class="text-xs text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        {#if p.status !== 'paid' && order.order_status !== 'cancelled'}
                                            <Button
                                                size="sm"
                                                class="h-7 px-2.5 text-xs bg-primary hover:bg-primary/90 font-semibold"
                                                onclick={() => openPayTermModal(p)}
                                            >
                                                Bayar Termin
                                            </Button>
                                        {/if}
                                        {#if p.paid_amount == 0 && order.order_status !== 'cancelled' && (order.payments?.length || 0) > 1}
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                class="h-7 w-7 p-0 text-destructive hover:bg-destructive/10"
                                                onclick={() => deleteTerm(p.id)}
                                                title="Hapus jadwal termin ini"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </Button>
                                        {/if}
                                    </div>
                                </TableCell>
                            </TableRow>
                        {/each}
                    </TableBody>
                </Table>
            {/if}
        </CardContent>
    </Card>

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

<!-- Modal: Pelunasan Sisa Tagihan / Bayar Termin -->
<Dialog open={showPayModal} onOpenChange={(open) => (showPayModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <DollarSign class="size-5 text-primary" />
                {selectedTerm ? `Bayar ${selectedTerm.term_name}` : `Pelunasan Tagihan #${order.invoice_number}`}
            </DialogTitle>
            <DialogDescription class="text-xs">
                {selectedTerm
                    ? `Catat setoran pembayaran untuk termin ${selectedTerm.term_name}.`
                    : 'Catat pembayaran pelunasan sisa biaya cetak pesanan.'}
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
            <div class="space-y-1">
                <Label for="p_amt">Nominal Pembayaran (Rp) *</Label>
                <Input
                    id="p_amt"
                    type="number"
                    step="1"
                    min="0"
                    bind:value={payAmount}
                    class="font-bold text-base"
                />
                <span class="text-[10px] text-muted-foreground">
                    {selectedTerm
                        ? `Target termin: ${formatRupiah(selectedTerm.amount)} (Sisa: ${formatRupiah(Math.max(0, Number(selectedTerm.amount) - Number(selectedTerm.paid_amount)))})`
                        : `Total sisa tagihan: ${formatRupiah(remainingDebt)}`}
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
                <Label for="p_notes">Catatan Pembayaran</Label>
                <Input id="p_notes" bind:value={payNotes} placeholder="Catatan pembayaran / no referensi transfer..." />
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (showPayModal = false)}>Batal</Button>
            <Button class="bg-primary" onclick={submitPayment} disabled={!payAmount}>
                Simpan Pembayaran
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Modal: Tambah Jadwal Termin Baru -->
<Dialog open={showAddTermModal} onOpenChange={(open) => (showAddTermModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Split class="size-5 text-amber-600" />
                Tambah Jadwal Termin Pembayaran
            </DialogTitle>
            <DialogDescription class="text-xs">
                Jadwalkan termin pembayaran tambahan atau pecah sisa tagihan menjadi beberapa cicilan.
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
            <div class="space-y-1">
                <Label for="term_n">Nama Termin *</Label>
                <Input
                    id="term_n"
                    bind:value={newTermName}
                    placeholder="Contoh: Termin 3 (Pelunasan Akhir)"
                />
            </div>

            <div class="space-y-1">
                <Label for="term_a">Nominal Termin (Rp) *</Label>
                <Input
                    id="term_a"
                    type="number"
                    bind:value={newTermAmount}
                    placeholder="0"
                    class="font-bold text-base"
                />
                {#if remainingDebt > 0}
                    <div class="flex items-center justify-between text-[10px] text-muted-foreground pt-0.5">
                        <span>Sisa belum lunas: {formatRupiah(remainingDebt)}</span>
                        <button
                            type="button"
                            onclick={() => (newTermAmount = remainingDebt.toString())}
                            class="text-primary hover:underline font-semibold"
                        >
                            Set Seluruh Sisa
                        </button>
                    </div>
                {/if}
            </div>

            <div class="space-y-1">
                <Label for="term_d">Tanggal Jatuh Tempo</Label>
                <Input
                    id="term_d"
                    type="date"
                    bind:value={newTermDueDate}
                />
            </div>

            <div class="space-y-1">
                <Label for="term_notes">Catatan Termin (Opsional)</Label>
                <Input
                    id="term_notes"
                    bind:value={newTermNotes}
                    placeholder="Keterangan tahapan / syarat pembayaran..."
                />
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (showAddTermModal = false)}>Batal</Button>
            <Button class="bg-primary" onclick={submitNewTerm} disabled={!newTermName || !newTermAmount}>
                Simpan Jadwal Termin
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Modal: Pratinjau Nota Thermal USB 80mm -->
<Dialog open={showReceiptPreviewModal} onOpenChange={(open) => (showReceiptPreviewModal = open)}>
    <DialogContent class="sm:max-w-md max-h-[90vh] overflow-y-auto">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2 text-base">
                <Printer class="size-5 text-primary" />
                Pratinjau Nota Thermal (Kertas 80mm)
            </DialogTitle>
            <DialogDescription class="text-xs">
                Format standar untuk printer thermal USB kasir dengan lebar kertas 80mm.
            </DialogDescription>
        </DialogHeader>

        <!-- Kertas Thermal 80mm Simulasi -->
        <div class="my-2 p-4 bg-white text-black rounded-lg border border-neutral-300 shadow-inner font-sans text-xs max-w-[340px] mx-auto select-none">
            <div class="text-center pb-2 border-b border-dashed border-black">
                {#if receiptSettings.show_logo !== false && receiptSettings.logo_url}
                    <div class="flex justify-center mb-1.5">
                        <img
                            src={receiptSettings.logo_url}
                            alt="Logo {receiptSettings.store_name || 'Toko'}"
                            class="max-h-12 max-w-[150px] object-contain filter grayscale contrast-125 mx-auto block"
                        />
                    </div>
                {/if}
                <h3 class="font-bold text-sm uppercase">{receiptSettings.store_name || 'LightPrint Percetakan'}</h3>
                {#if receiptSettings.tagline}
                    <p class="text-[10px] text-neutral-700">{receiptSettings.tagline}</p>
                {/if}
                {#if receiptSettings.address}
                    <p class="text-[10px] text-neutral-700">{receiptSettings.address}</p>
                {/if}
                {#if receiptSettings.phone}
                    <p class="text-[10px] font-semibold">Telp/WA: {receiptSettings.phone}</p>
                {/if}
                {#if receiptSettings.website}
                    <p class="text-[9px] text-neutral-600">{receiptSettings.website}</p>
                {/if}
            </div>

            <div class="py-2 text-[10px] border-b border-dashed border-black space-y-0.5">
                <div class="flex justify-between font-bold">
                    <span>No. Nota:</span>
                    <span>{order.invoice_number}</span>
                </div>
                <div class="flex justify-between">
                    <span>Tanggal:</span>
                    <span>{new Date(order.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                </div>
                {#if receiptSettings.show_cashier_name !== false}
                    <div class="flex justify-between">
                        <span>Kasir:</span>
                        <span>{order.user?.name || '-'}</span>
                    </div>
                {/if}
                {#if receiptSettings.show_customer_info !== false}
                    <div class="flex justify-between">
                        <span>Pelanggan:</span>
                        <span class="font-semibold">{order.customer_name || 'Pelanggan Umum'}</span>
                    </div>
                {/if}
                <div class="flex justify-between">
                    <span>Metode / Status:</span>
                    <span class="font-bold uppercase">
                        {order.payment_method} / {order.payment_status === 'paid' ? 'LUNAS' : order.payment_status === 'partial' ? 'DP' : 'BELUM'}
                    </span>
                </div>
            </div>

            <div class="py-2 border-b border-dashed border-black space-y-1.5 text-[10px]">
                {#each order.items as item, idx}
                    <div>
                        <div class="font-bold flex justify-between">
                            <span>{idx + 1}. {item.product_name}</span>
                            <span>{formatRupiah(item.subtotal)}</span>
                        </div>
                        {#if item.pricing_type === 'dimension_sqm'}
                            <div class="text-[9px] text-neutral-600 pl-2">
                                {item.length}m × {item.width}m ({item.area} m²) × {item.quantity} @ {formatRupiah(item.unit_price)}
                            </div>
                        {:else}
                            <div class="text-[9px] text-neutral-600 pl-2">
                                {item.quantity} {item.unit || 'pcs'} × @ {formatRupiah(item.unit_price)}
                            </div>
                        {/if}
                        {#if item.finishing_option && item.finishing_option !== 'Standar' && item.finishing_option !== 'Tanpa Finishing'}
                            <div class="text-[9px] text-neutral-700 pl-2">Finishing: {item.finishing_option}</div>
                        {/if}
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
                    <span>Total Bayar:</span>
                    <span class="font-bold">{formatRupiah(order.paid_amount)}</span>
                </div>
                <div class="flex justify-between">
                    <span>Kembalian:</span>
                    <span>{formatRupiah(order.change_amount)}</span>
                </div>
                {#if remainingDebt > 0}
                    <div class="flex justify-between font-bold text-red-600 pt-0.5 border-t border-dotted border-black">
                        <span>Sisa Tagihan / Tempo:</span>
                        <span>{formatRupiah(remainingDebt)}</span>
                    </div>
                {/if}

                {#if order.payments && order.payments.length > 0}
                    <div class="pt-1 mt-1 border-t border-dotted border-black space-y-0.5">
                        <div class="font-bold text-[9px]">Rincian Termin:</div>
                        {#each order.payments as p}
                            <div class="flex justify-between text-[9px]">
                                <span>{p.term_name}:</span>
                                <span>{formatRupiah(p.status === 'paid' ? p.paid_amount : p.amount)}</span>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>

            <div class="pt-2 text-center text-[9px] text-neutral-600 whitespace-pre-line leading-relaxed">
                {receiptSettings.footer_text || 'Terima kasih atas pesanan cetak Anda!'}
                <div class="mt-1 text-[8px] text-neutral-400 font-semibold tracking-wider">
                    *** LIGHTPRINT POS 80MM ***
                </div>
            </div>
        </div>

        <DialogFooter class="flex-col sm:flex-row gap-2">
            <Button variant="outline" size="sm" onclick={() => (showReceiptPreviewModal = false)}>
                Tutup
            </Button>
            <Button variant="outline" size="sm" onclick={printReceipt}>
                <Printer class="size-4 mr-1.5" />
                Dialog Browser
            </Button>
            <Button
                size="sm"
                class="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold"
                disabled={isPrintingUSB}
                onclick={handlePrintUSB}
            >
                <Usb class="size-4 mr-1.5" />
                {isPrintingUSB ? 'Mengirim ke USB...' : 'Cetak USB Thermal (80mm)'}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

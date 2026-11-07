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
    import Motorbike from '@lucide/svelte/icons/motorbike';
    import PackageCheck from '@lucide/svelte/icons/package-check';
    import Plus from '@lucide/svelte/icons/plus';
    import Printer from '@lucide/svelte/icons/printer';
    import RotateCcw from '@lucide/svelte/icons/rotate-ccw';
    import Split from '@lucide/svelte/icons/split';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import Usb from '@lucide/svelte/icons/usb';
    import User from '@lucide/svelte/icons/user';
    import DriverPickupModal from '@/components/DriverPickupModal.svelte';
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
        delivery_type?: 'pickup' | 'courier';
        courier_name?: string | null;
        booking_code?: string | null;
        driver_name?: string | null;
        driver_phone?: string | null;
        delivery_status?: 'pending' | 'picked_up' | 'delivered' | null;
        picked_up_at?: string | null;
        delivery_notes?: string | null;
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
    let markAsCompleted = $state(true);

    // Add new term modal
    let showAddTermModal = $state(false);
    let newTermName = $state('');
    let newTermAmount = $state('');
    let newTermDueDate = $state('');
    let newTermNotes = $state('');

    // Delivery & Courier Modal
    let showDeliveryModal = $state(false);
    let deliveryType = $state<'pickup' | 'courier'>('pickup');
    let courierName = $state('Gojek');
    let bookingCode = $state('');
    let deliveryNotes = $state('');
    let deliveryStatus = $state<'pending' | 'picked_up' | 'delivered'>('pending');
    let isSavingDelivery = $state(false);

    // Driver Pickup Matching Modal
    let showDriverPickupModal = $state(false);

    function openDeliveryModal() {
        deliveryType = order.delivery_type || 'pickup';
        courierName = order.courier_name || 'Gojek';
        bookingCode = order.booking_code || '';
        deliveryNotes = order.delivery_notes || '';
        deliveryStatus = (order.delivery_status as any) || 'pending';
        showDeliveryModal = true;
    }

    function submitDelivery() {
        isSavingDelivery = true;
        router.patch(
            `/orders/${order.id}/delivery`,
            {
                delivery_type: deliveryType,
                courier_name: courierName,
                booking_code: bookingCode,
                delivery_notes: deliveryNotes,
                delivery_status: deliveryStatus,
            },
            {
                onSuccess: () => {
                    toast.success('Informasi kurir/pengiriman berhasil disimpan!');
                    showDeliveryModal = false;
                },
                onError: () => {
                    toast.error('Gagal menyimpan informasi kurir.');
                },
                onFinish: () => {
                    isSavingDelivery = false;
                },
            }
        );
    }

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

    function formatNumber(amount: number | string | null | undefined): string {
        return Number(amount || 0).toLocaleString('id-ID');
    }

    function formatReceiptDate(dateStr: string | null | undefined, includeTime = true): string {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return String(dateStr);
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
        return `${num} m`;
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
        const receiptEl = document.getElementById('order-thermal-receipt');
        if (!receiptEl) {
            window.print();
            return;
        }

        const popup = window.open('', '_blank', 'width=380,height=600,top=100,left=100');
        if (!popup) {
            window.print();
            return;
        }

        popup.document.open();
        popup.document.write(`
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="utf-8">
                <title>Nota #${order.invoice_number}</title>
                <style>
                    @page { size: 80mm auto; margin: 0; }
                    * {
                        box-sizing: border-box;
                        border-width: 0;
                        border-style: solid;
                        border-color: transparent;
                    }
                    html, body {
                        margin: 0;
                        padding: 0;
                        background: #ffffff;
                        color: #000000;
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    }
                    p, h1, h2, h3, h4, h5, h6 {
                        margin: 0 !important;
                        padding: 0 !important;
                        line-height: 1.25 !important;
                    }
                    .header-store p {
                        margin-top: 1.5px !important;
                        margin-bottom: 0 !important;
                        line-height: 1.2 !important;
                    }
                    .header-store h2, .header-store h3 {
                        margin: 0 0 2px 0 !important;
                        line-height: 1.25 !important;
                    }
                    .thermal-receipt-80mm {
                        display: block !important;
                        width: 68mm !important;
                        max-width: 68mm !important;
                        min-width: 68mm !important;
                        margin: 0 auto !important;
                        padding: 2mm 3mm 4mm 2mm !important;
                        font-size: 9.5px !important;
                        line-height: 1.25 !important;
                        color: #000000 !important;
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
                        border-bottom: 1px solid #000000 !important;
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
                        border-bottom: 1px solid #000000 !important;
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
                        border-bottom: 1px solid #000000 !important;
                        font-size: 9px !important;
                        font-weight: 700 !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-item-row {
                        padding: 4px 0 !important;
                        border-bottom: 1px solid #000000 !important;
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
                        border-top: 1px dashed #000000 !important;
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
                    .border-b { border-bottom: 1px solid #000000 !important; }
                    .border-t { border-top: 1px solid #000000 !important; }
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
        popup.document.close();
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
        markAsCompleted = true;
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
                mark_as_completed: markAsCompleted,
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

<!-- Print-Only Receipt Thermal 80mm View (Matching WhatsApp Image Format - Scaled Cleanly) -->
<div id="order-thermal-receipt" class="thermal-receipt-80mm text-black font-sans">
    <!-- Store Header (Logo & Identitas Toko di Bawah Logo) -->
    {#if (receiptSettings.show_logo !== false && receiptSettings.logo_url) || receiptSettings.store_name}
        <div class="header-store">
            {#if receiptSettings.show_logo !== false && receiptSettings.logo_url}
                <img
                    src={receiptSettings.logo_url}
                    alt="Logo {receiptSettings.store_name || 'Toko'}"
                    class="receipt-logo"
                />
            {/if}
            <h2>{receiptSettings.store_name || 'LightPrint Percetakan'}</h2>
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
    {/if}

    <!-- Order Metadata (Format persis gambar WhatsApp) -->
    <div class="receipt-meta">
        <div class="receipt-meta-grid">
            <span>Kode Order</span>
            <span>:</span>
            <span class="font-medium">{order.invoice_number}</span>

            <span>Tanggal</span>
            <span>:</span>
            <span>{formatReceiptDate(order.created_at)}</span>

            {#if order.due_date}
                <span>Deadline</span>
                <span>:</span>
                <span>{formatReceiptDate(order.due_date, false)}</span>
            {/if}

            {#if receiptSettings.show_customer_info !== false}
                <span>Kepada</span>
                <span>:</span>
                <span class="font-bold">{order.customer_name || 'Pelanggan Umum'}{order.customer_phone ? ` (${order.customer_phone})` : ''}</span>
            {/if}

            {#if receiptSettings.show_cashier_name !== false}
                <span>Kasir Online</span>
                <span>:</span>
                <span class="uppercase">{order.user?.name || '-'}</span>
            {/if}

            {#if order.delivery_type === 'courier'}
                <span>Kurir</span>
                <span>:</span>
                <span class="font-bold uppercase">{order.courier_name || 'KURIR'}{order.booking_code ? ` (${order.booking_code})` : ''}</span>
            {/if}
        </div>
    </div>

    <!-- Items Table Header (Produk, Harga, Qty, Total) -->
    <div>
        <div class="receipt-table-header">
            <span>Produk</span>
            <span class="receipt-col-price">Harga</span>
            <span class="receipt-col-qty">Qty</span>
            <span class="receipt-col-total">Total</span>
        </div>

        <!-- Items Rows with details and horizontal dividers -->
        {#each order.items as item}
            <div class="receipt-item-row">
                <div class="receipt-item-grid">
                    <span class="receipt-col-product">{item.product_name}</span>
                    <span class="receipt-col-price">{formatNumber(item.unit_price)}</span>
                    <span class="receipt-col-qty">{formatNumber(item.quantity)}</span>
                    <span class="receipt-col-total">{formatNumber(item.subtotal)}</span>
                </div>

                <!-- Bullets Detail Produk (Ukuran, Bahan, Finishing, File, Deskripsi, Deadline) -->
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
                    {#if order.due_date}
                        <div>- Deadline: {formatReceiptDate(order.due_date)}</div>
                    {/if}
                </div>
            </div>
        {/each}

        <!-- Catatan Pesanan Keseluruhan jika ada -->
        {#if order.notes}
            <div class="receipt-order-notes">
                <div>- Catatan: {order.notes}</div>
            </div>
        {/if}
    </div>

    <!-- Totals Summary Section -->
    <div class="receipt-totals">
        <div class="receipt-totals-box">
            {#if order.discount_amount > 0}
                <div class="receipt-totals-row">
                    <span>Diskon :</span>
                    <span>-{formatNumber(order.discount_amount)}</span>
                </div>
            {/if}
            {#if order.tax_amount && order.tax_amount > 0}
                <div class="receipt-totals-row">
                    <span>PPN :</span>
                    <span>{formatNumber(order.tax_amount)}</span>
                </div>
            {/if}
            <div class="receipt-totals-row grand-total">
                <span>Total :</span>
                <span>{formatNumber(order.grand_total)}</span>
            </div>
            <div class="receipt-totals-row">
                <span>Bayar :</span>
                <span>{formatNumber(order.paid_amount)}</span>
            </div>
            {#if order.change_amount > 0}
                <div class="receipt-totals-row">
                    <span>Kembali :</span>
                    <span>{formatNumber(order.change_amount)}</span>
                </div>
            {/if}
            {#if remainingDebt > 0}
                <div class="receipt-totals-row font-bold text-red-600">
                    <span>Sisa :</span>
                    <span>{formatNumber(remainingDebt)}</span>
                </div>
            {/if}
        </div>

        <!-- Status LUNAS / DP / PENDING di kiri bawah persis seperti gambar -->
        <div class="receipt-status">
            <span>Keterangan : </span>
            <span class="receipt-status-badge">
                {#if order.payment_status === 'paid'}
                    LUNAS
                {:else if order.payment_method === 'pending'}
                    BAYAR SAAT AMBIL
                {:else if order.payment_status === 'partial'}
                    DP (PARSIAL)
                {:else}
                    BELUM LUNAS
                {/if}
            </span>
        </div>

        {#if order.payments && order.payments.length > 0}
            <div class="mt-2 pt-1 border-t border-dotted border-black text-[9px] space-y-0.5">
                <div class="font-bold">Rincian Termin Pembayaran:</div>
                {#each order.payments as p}
                    <div class="flex justify-between">
                        <span>{p.term_name}:</span>
                        <span>{formatNumber(p.status === 'paid' ? p.paid_amount : p.amount)} ({p.status === 'paid' ? 'LUNAS' : 'TEMPO'})</span>
                    </div>
                {/each}
            </div>
        {/if}
    </div>

    <!-- Footer Terms & Cut Area -->
    <div class="receipt-footer">
        {receiptSettings.footer_text || 'Terima kasih telah mencetak di tempat kami!\nPeriksa kembali hasil cetakan Anda.'}
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
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
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
                    <span class="font-bold uppercase text-foreground">
                        {order.payment_method === 'pending' ? 'Bayar Saat Ambil (Pending)' : order.payment_method}
                    </span>
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

        <!-- Delivery / Courier Info -->
        <Card class="border shadow-2xs {order.delivery_type === 'courier' && order.delivery_status !== 'picked_up' ? 'border-amber-400/80 bg-amber-50/20 dark:bg-amber-950/10' : ''}">
            <CardHeader class="pb-2 flex flex-row items-center justify-between space-y-0">
                <CardTitle class="text-xs uppercase tracking-wider text-muted-foreground font-semibold flex items-center gap-1.5">
                    <Motorbike class="size-3.5 text-primary" />
                    Pengiriman & Kurir
                </CardTitle>
                <Button variant="ghost" size="sm" class="h-6 px-1.5 text-[11px] text-primary hover:text-primary/80" onclick={openDeliveryModal}>
                    {order.delivery_type === 'courier' ? 'Ubah Kode' : 'Set Kurir'}
                </Button>
            </CardHeader>
            <CardContent class="space-y-1.5">
                {#if order.delivery_type === 'courier'}
                    <div class="flex justify-between items-center">
                        <span class="text-muted-foreground">Kurir / Ojol:</span>
                        <Badge class="bg-indigo-600 text-white text-[10px] uppercase font-semibold">
                            {order.courier_name || 'Gojek / Grab'}
                        </Badge>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-muted-foreground">Kode Booking:</span>
                        {#if order.booking_code}
                            <span class="font-mono font-bold text-xs bg-muted px-2 py-0.5 rounded text-primary border">
                                {order.booking_code}
                            </span>
                        {:else}
                            <span class="text-amber-700 dark:text-amber-400 italic text-[11px] font-medium">(Belum diinput)</span>
                        {/if}
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-muted-foreground">Status Pick-up:</span>
                        {#if order.delivery_status === 'picked_up'}
                            <Badge class="bg-emerald-600 text-white text-[10px]">Sudah Diambil</Badge>
                        {:else}
                            <Badge variant="outline" class="border-amber-400 bg-amber-50 text-amber-800 text-[10px] font-semibold">
                                Menunggu Driver
                            </Badge>
                        {/if}
                    </div>

                    {#if order.delivery_status === 'picked_up'}
                        <div class="text-[10px] text-muted-foreground pt-1 border-t space-y-0.5">
                            <div>Driver: <strong>{order.driver_name || '-'}</strong> {order.driver_phone ? `(${order.driver_phone})` : ''}</div>
                            {#if order.picked_up_at}
                                <div>Waktu: {new Date(order.picked_up_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}</div>
                            {/if}
                        </div>
                    {:else}
                        <Button
                            size="sm"
                            class="w-full h-7 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-semibold mt-1"
                            onclick={() => (showDriverPickupModal = true)}
                        >
                            <CheckCircle class="size-3.5 mr-1" />
                            Serahkan ke Driver
                        </Button>
                    {/if}
                {:else}
                    <div class="text-muted-foreground">
                        Metode: <strong class="text-foreground">Ambil di Toko</strong>
                    </div>
                    <p class="text-[11px] text-muted-foreground">
                        Customer mengambil pesanan langsung ke kasir toko.
                    </p>
                    <Button variant="outline" size="sm" class="w-full h-7 text-[11px] mt-1 text-primary border-primary/30" onclick={openDeliveryModal}>
                        Kirim via Gojek / Grab
                    </Button>
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
                                    {item.finishing_option || 'Tanpa Finishing'}
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

            {#if order.order_status !== 'completed'}
                <label class="flex items-center gap-2 pt-1 text-xs cursor-pointer select-none">
                    <input type="checkbox" bind:checked={markAsCompleted} class="size-4 rounded border-input text-primary focus:ring-primary" />
                    <span class="font-medium text-foreground">Sekaligus tandai pesanan sebagai <strong>Selesai (Sudah Diambil)</strong></span>
                </label>
            {/if}
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
        <div class="my-2 p-3 bg-white text-black rounded-lg border border-neutral-300 shadow-inner font-sans thermal-receipt-80mm max-w-[310px] mx-auto select-none">
            <!-- Store Header (Logo & Identitas Toko) -->
            {#if (receiptSettings.show_logo !== false && receiptSettings.logo_url) || receiptSettings.store_name}
                <div class="header-store">
                    {#if receiptSettings.show_logo !== false && receiptSettings.logo_url}
                        <img
                            src={receiptSettings.logo_url}
                            alt="Logo {receiptSettings.store_name || 'Toko'}"
                            class="receipt-logo"
                        />
                    {/if}
                    <h3>{receiptSettings.store_name || 'LightPrint Percetakan'}</h3>
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
            {/if}

            <!-- Order Metadata -->
            <div class="receipt-meta">
                <div class="receipt-meta-grid">
                    <span>Kode Order</span>
                    <span>:</span>
                    <span class="font-medium">{order.invoice_number}</span>

                    <span>Tanggal</span>
                    <span>:</span>
                    <span>{formatReceiptDate(order.created_at)}</span>

                    {#if order.due_date}
                        <span>Deadline</span>
                        <span>:</span>
                        <span>{formatReceiptDate(order.due_date, false)}</span>
                    {/if}

                    {#if receiptSettings.show_customer_info !== false}
                        <span>Kepada</span>
                        <span>:</span>
                        <span class="font-bold">{order.customer_name || 'Pelanggan Umum'}{order.customer_phone ? ` (${order.customer_phone})` : ''}</span>
                    {/if}

                    {#if receiptSettings.show_cashier_name !== false}
                        <span>Kasir Online</span>
                        <span>:</span>
                        <span class="uppercase">{order.user?.name || '-'}</span>
                    {/if}

                    {#if order.delivery_type === 'courier'}
                        <span>Kurir</span>
                        <span>:</span>
                        <span class="font-bold uppercase">{order.courier_name || 'KURIR'}{order.booking_code ? ` (${order.booking_code})` : ''}</span>
                    {/if}
                </div>
            </div>

            <!-- Items Table Header (Produk, Harga, Qty, Total) -->
            <div>
                <div class="receipt-table-header">
                    <span>Produk</span>
                    <span class="receipt-col-price">Harga</span>
                    <span class="receipt-col-qty">Qty</span>
                    <span class="receipt-col-total">Total</span>
                </div>

                <!-- Items Rows with details and horizontal dividers -->
                {#each order.items as item}
                    <div class="receipt-item-row">
                        <div class="receipt-item-grid">
                            <span class="receipt-col-product">{item.product_name}</span>
                            <span class="receipt-col-price">{formatNumber(item.unit_price)}</span>
                            <span class="receipt-col-qty">{formatNumber(item.quantity)}</span>
                            <span class="receipt-col-total">{formatNumber(item.subtotal)}</span>
                        </div>

                        <!-- Bullets Detail Produk (Ukuran, Bahan, Finishing, File, Deskripsi, Deadline) -->
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
                            {#if order.due_date}
                                <div>- Deadline: {formatReceiptDate(order.due_date)}</div>
                            {/if}
                        </div>
                    </div>
                {/each}

                <!-- Catatan Pesanan Keseluruhan jika ada -->
                {#if order.notes}
                    <div class="receipt-order-notes">
                        <div>- Catatan: {order.notes}</div>
                    </div>
                {/if}
            </div>

            <!-- Totals Summary Section -->
            <div class="receipt-totals">
                <div class="receipt-totals-box">
                    {#if order.discount_amount > 0}
                        <div class="receipt-totals-row">
                            <span>Diskon :</span>
                            <span>-{formatNumber(order.discount_amount)}</span>
                        </div>
                    {/if}
                    {#if order.tax_amount && order.tax_amount > 0}
                        <div class="receipt-totals-row">
                            <span>PPN :</span>
                            <span>{formatNumber(order.tax_amount)}</span>
                        </div>
                    {/if}
                    <div class="receipt-totals-row grand-total">
                        <span>Total :</span>
                        <span>{formatNumber(order.grand_total)}</span>
                    </div>
                    <div class="receipt-totals-row">
                        <span>Bayar :</span>
                        <span>{formatNumber(order.paid_amount)}</span>
                    </div>
                    {#if order.change_amount > 0}
                        <div class="receipt-totals-row">
                            <span>Kembali :</span>
                            <span>{formatNumber(order.change_amount)}</span>
                        </div>
                    {/if}
                    {#if remainingDebt > 0}
                        <div class="receipt-totals-row font-bold text-red-600">
                            <span>Sisa :</span>
                            <span>{formatNumber(remainingDebt)}</span>
                        </div>
                    {/if}
                </div>

                <!-- Status LUNAS / DP / PENDING di kiri bawah persis seperti gambar -->
                <div class="receipt-status">
                    <span>Keterangan : </span>
                    <span class="receipt-status-badge">
                        {#if order.payment_status === 'paid'}
                            LUNAS
                        {:else if order.payment_method === 'pending'}
                            BAYAR SAAT AMBIL
                        {:else if order.payment_status === 'partial'}
                            DP (PARSIAL)
                        {:else}
                            BELUM LUNAS
                        {/if}
                    </span>
                </div>

                {#if order.payments && order.payments.length > 0}
                    <div class="mt-2 pt-1 border-t border-dotted border-black text-[9px] space-y-0.5">
                        <div class="font-bold">Rincian Termin:</div>
                        {#each order.payments as p}
                            <div class="flex justify-between">
                                <span>{p.term_name}:</span>
                                <span>{formatNumber(p.status === 'paid' ? p.paid_amount : p.amount)} ({p.status === 'paid' ? 'LUNAS' : 'TEMPO'})</span>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>

            <!-- Footer Terms & Cut Area -->
            <div class="receipt-footer">
                {receiptSettings.footer_text || 'Terima kasih atas pesanan cetak Anda!'}
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

<!-- Delivery / Courier Modal -->
<Dialog bind:open={showDeliveryModal}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="text-base font-bold flex items-center gap-2">
                <Motorbike class="size-5 text-primary" />
                Pengaturan Pengiriman & Kurir (Gojek / Grab)
            </DialogTitle>
            <DialogDescription class="text-xs">
                Pilih apakah barang diambil sendiri oleh pelanggan atau dikirim menggunakan kurir ojol.
            </DialogDescription>
        </DialogHeader>

        <form
            onsubmit={(e) => {
                e.preventDefault();
                submitDelivery();
            }}
            class="space-y-4 py-2"
        >
            <div class="space-y-2">
                <Label class="text-xs font-semibold">Tipe Pengambilan / Pengiriman</Label>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        class="p-2.5 rounded-lg border text-xs font-medium text-left flex items-center gap-2 cursor-pointer transition-all {deliveryType === 'pickup' ? 'border-primary bg-primary/10 text-primary font-bold' : 'hover:bg-muted/50'}"
                        onclick={() => (deliveryType = 'pickup')}
                    >
                        <User class="size-4 shrink-0" />
                        <div>
                            <div>Ambil di Toko</div>
                            <div class="text-[10px] text-muted-foreground font-normal">Customer ambil sendiri</div>
                        </div>
                    </button>

                    <button
                        type="button"
                        class="p-2.5 rounded-lg border text-xs font-medium text-left flex items-center gap-2 cursor-pointer transition-all {deliveryType === 'courier' ? 'border-primary bg-primary/10 text-primary font-bold' : 'hover:bg-muted/50'}"
                        onclick={() => (deliveryType = 'courier')}
                    >
                        <Motorbike class="size-4 shrink-0" />
                        <div>
                            <div>Kurir / Ojol</div>
                            <div class="text-[10px] text-muted-foreground font-normal">Gojek, Grab, dll</div>
                        </div>
                    </button>
                </div>
            </div>

            {#if deliveryType === 'courier'}
                <div class="space-y-3 pt-2 border-t">
                    <div class="space-y-1">
                        <Label for="courier-name" class="text-xs">Layanan Kurir</Label>
                        <select
                            id="courier-name"
                            bind:value={courierName}
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-xs"
                        >
                            <option value="Gojek (GoSend)">Gojek (GoSend)</option>
                            <option value="Grab (GrabExpress)">Grab (GrabExpress)</option>
                            <option value="Maxim">Maxim</option>
                            <option value="Lalamove">Lalamove</option>
                            <option value="Kurir Toko / Internal">Kurir Toko / Internal</option>
                            <option value="Ekspedisi (JNE/J&T/SiCepat)">Ekspedisi (JNE/J&T/SiCepat)</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <Label for="booking-code" class="text-xs font-semibold text-foreground">
                            Kode Booking / Nomor Order Ojol / Resi
                        </Label>
                        <Input
                            id="booking-code"
                            bind:value={bookingCode}
                            placeholder="Contoh: GK-882310 atau GRAB-10294"
                            class="h-9 text-xs font-mono uppercase tracking-wider"
                        />
                        <p class="text-[10px] text-muted-foreground">
                            Kode ini akan dicocokkan saat driver datang ke toko untuk mengambil barang.
                        </p>
                    </div>

                    <div class="space-y-1">
                        <Label for="delivery-status" class="text-xs">Status Penyerahan Kurir</Label>
                        <select
                            id="delivery-status"
                            bind:value={deliveryStatus}
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-xs"
                        >
                            <option value="pending">Menunggu Driver Pick-up</option>
                            <option value="picked_up">Sudah Diambil Driver</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <Label for="delivery-notes" class="text-xs">Catatan Pengiriman (Opsional)</Label>
                        <Input
                            id="delivery-notes"
                            bind:value={deliveryNotes}
                            placeholder="Contoh: Titipkan di pos satpam / barang jangan ditumpuk"
                            class="h-9 text-xs"
                        />
                    </div>
                </div>
            {/if}

            <DialogFooter class="pt-2">
                <Button type="button" variant="outline" size="sm" onclick={() => (showDeliveryModal = false)}>
                    Batal
                </Button>
                <Button type="submit" size="sm" class="bg-primary text-primary-foreground font-semibold" disabled={isSavingDelivery}>
                    {isSavingDelivery ? 'Menyimpan...' : 'Simpan Info Pengiriman'}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>

<!-- Driver Pickup Matching Modal -->
<DriverPickupModal bind:open={showDriverPickupModal} />

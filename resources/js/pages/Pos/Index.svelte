<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Kasir (POS)',
                href: '/pos',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AlertCircle from '@lucide/svelte/icons/alert-circle';
    import Calculator from '@lucide/svelte/icons/calculator';
    import Calendar from '@lucide/svelte/icons/calendar';
    import Check from '@lucide/svelte/icons/check';
    import Clock from '@lucide/svelte/icons/clock';
    import CreditCard from '@lucide/svelte/icons/credit-card';
    import DollarSign from '@lucide/svelte/icons/dollar-sign';
    import Eye from '@lucide/svelte/icons/eye';
    import FileText from '@lucide/svelte/icons/file-text';
    import LoaderCircle from '@lucide/svelte/icons/loader-circle';
    import Minus from '@lucide/svelte/icons/minus';
    import Motorbike from '@lucide/svelte/icons/motorbike';
    import Plus from '@lucide/svelte/icons/plus';
    import Printer from '@lucide/svelte/icons/printer';
    import QrCode from '@lucide/svelte/icons/qr-code';
    import Search from '@lucide/svelte/icons/search';
    import ShoppingBag from '@lucide/svelte/icons/shopping-bag';
    import Split from '@lucide/svelte/icons/split';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import UserPlus from '@lucide/svelte/icons/user-plus';
    import Users from '@lucide/svelte/icons/users';
    import AppHead from '@/components/AppHead.svelte';
    import DriverPickupModal from '@/components/DriverPickupModal.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Textarea } from '@/components/ui/textarea';

    interface ProductItem {
        id: number;
        category_id: number;
        raw_material_id?: number | null;
        name: string;
        code: string;
        pricing_type: 'fixed' | 'dimension_sqm';
        unit: string;
        base_price: number;
        min_order: number;
        cost_price: number;
        stock: number;
        is_stock_managed: boolean;
        category?: { id: number; name: string };
        raw_material?: { id: number; name: string; stock: number; unit: string };
    }

    interface CustomerItem {
        id: number;
        name: string;
        phone: string | null;
        customer_type: 'regular' | 'member' | 'reseller' | 'corporate';
        discount_percent: number;
    }

    interface CartItem {
        temp_id: string;
        product_id: number;
        product_name: string;
        pricing_type: 'fixed' | 'dimension_sqm';
        unit: string;
        base_price: number;
        cost_price: number;
        length: number; // in meters
        width: number;  // in meters
        area: number;   // length x width
        quantity: number;
        finishing_option: string;
        finishing_price: number;
        subtotal: number;
        design_note: string;
    }

    let {
        activeShift = null,
        categories = [],
        products = [],
        customers = [],
        receiptSettings = {},
        nextInvoice = '',
        pendingOrders = [],
        courierWaitingCount = 0,
    }: {
        activeShift: any;
        categories: any[];
        products: ProductItem[];
        customers: CustomerItem[];
        receiptSettings: any;
        nextInvoice: string;
        pendingOrders?: any[];
        courierWaitingCount?: number;
    } = $props();

    // State
    let selectedCategory = $state<number | 'all'>('all');
    let searchQuery = $state('');
    let cart = $state<CartItem[]>([]);
    let selectedCustomerId = $state<number | null>(null);
    let paymentMethod = $state<'cash' | 'transfer' | 'qris' | 'split' | 'pending'>('cash');
    let cashPaidInput = $state('');
    let orderNotes = $state('');
    let dueDate = $state('');
    let isSubmitting = $state(false);
    let showPendingOrdersModal = $state(false);
    let pendingSearchQuery = $state('');
    let showDriverPickupModal = $state(false);

    // Delivery & Courier for Current Cart
    let deliveryType = $state<'pickup' | 'courier'>('pickup');
    let courierName = $state('Gojek (GoSend)');
    let bookingCode = $state('');
    let deliveryNotes = $state('');

    const filteredPendingOrders = $derived(
        pendingOrders.filter((o: any) => {
            const q = pendingSearchQuery.toLowerCase().trim();
            if (!q) return true;
            return (
                (o.invoice_number && o.invoice_number.toLowerCase().includes(q)) ||
                (o.customer_name && o.customer_name.toLowerCase().includes(q)) ||
                (o.customer_phone && o.customer_phone.toLowerCase().includes(q))
            );
        })
    );

    interface PaymentTermItem {
        id: string;
        term_name: string;
        amount: number;
        payment_method: 'cash' | 'transfer' | 'qris';
        is_paid: boolean;
        due_date: string;
        notes: string;
    }

    let paymentTerms = $state<PaymentTermItem[]>([
        {
            id: '1',
            term_name: 'Termin 1 (DP Uang Muka)',
            amount: 0,
            payment_method: 'cash',
            is_paid: true,
            due_date: '',
            notes: 'Uang Muka (DP)',
        },
        {
            id: '2',
            term_name: 'Termin 2 (Pelunasan Akhir)',
            amount: 0,
            payment_method: 'cash',
            is_paid: false,
            due_date: '',
            notes: 'Pelunasan saat pengambilan barang',
        },
    ]);

    // Modals
    // svelte-ignore state_referenced_locally
    let showOpenShiftModal = $state(!activeShift);
    let startingCashInput = $state('200000');
    let shiftNotes = $state('');

    $effect(() => {
        showOpenShiftModal = !activeShift;
    });

    let showNewCustomerModal = $state(false);
    let newCustName = $state('');
    let newCustPhone = $state('');
    let newCustType = $state<'regular' | 'member' | 'reseller' | 'corporate'>('regular');
    let newCustDiscount = $state('0');

    // Filtered products
    const filteredProducts = $derived(
        products.filter((p) => {
            const matchesCat = selectedCategory === 'all' || p.category_id === selectedCategory;
            const matchesSearch =
                p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                p.code.toLowerCase().includes(searchQuery.toLowerCase());
            return matchesCat && matchesSearch;
        })
    );

    // Selected customer
    const selectedCustomer = $derived(
        customers.find((c) => c.id === selectedCustomerId) || null
    );

    // Calculations
    const cartSubtotal = $derived(
        Math.round(cart.reduce((sum, item) => sum + (Number(item.subtotal) || 0), 0))
    );

    const discountPercent = $derived(
        selectedCustomer ? Number(selectedCustomer.discount_percent || 0) : 0
    );

    const discountAmount = $derived(
        Math.round((cartSubtotal * discountPercent) / 100)
    );

    const grandTotal = $derived(Math.round(Math.max(0, cartSubtotal - discountAmount)));

    const totalAllocatedTerms = $derived(
        Math.round(paymentTerms.reduce((sum, t) => sum + (Number(t.amount) || 0), 0))
    );

    const totalPaidTermsNow = $derived(
        Math.round(paymentTerms.filter((t) => t.is_paid).reduce((sum, t) => sum + (Number(t.amount) || 0), 0))
    );

    const paidAmount = $derived(
        paymentMethod === 'cash'
            ? (cashPaidInput ? Math.round(parseFloat(cashPaidInput) || 0) : 0)
            : (paymentMethod === 'split'
                ? totalPaidTermsNow
                : (paymentMethod === 'pending' ? 0 : grandTotal))
    );

    const changeAmount = $derived(
        paymentMethod === 'cash' && paidAmount > grandTotal ? Math.round(paidAmount - grandTotal) : 0
    );

    const remainingDebt = $derived(
        grandTotal > paidAmount ? Math.round(grandTotal - paidAmount) : 0
    );

    const termAllocationDiff = $derived(
        grandTotal - totalAllocatedTerms
    );

    function formatRupiah(amount: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);
    }

    function initSplitTerms(percent = 50) {
        paymentMethod = 'split';
        const dp = Math.round((grandTotal * percent) / 100);
        const rem = Math.max(0, grandTotal - dp);
        const d = new Date();
        d.setDate(d.getDate() + 3);
        const defaultDue = d.toISOString().split('T')[0];

        paymentTerms = [
            {
                id: Math.random().toString(36).substring(2, 9),
                term_name: `Termin 1 (DP ${percent}%)`,
                amount: dp,
                payment_method: 'cash',
                is_paid: true,
                due_date: '',
                notes: 'Uang Muka (DP)',
            },
            {
                id: Math.random().toString(36).substring(2, 9),
                term_name: 'Termin 2 (Pelunasan Akhir)',
                amount: rem,
                payment_method: 'cash',
                is_paid: false,
                due_date: defaultDue,
                notes: 'Pelunasan saat ambil barang',
            },
        ];
    }

    function addPaymentTerm() {
        const nextIdx = paymentTerms.length + 1;
        const d = new Date();
        d.setDate(d.getDate() + (nextIdx * 3));
        const defaultDue = d.toISOString().split('T')[0];
        const unallocated = Math.max(0, grandTotal - totalAllocatedTerms);

        paymentTerms = [
            ...paymentTerms,
            {
                id: Math.random().toString(36).substring(2, 9),
                term_name: `Termin ${nextIdx} (Cicilan / Progress)`,
                amount: unallocated,
                payment_method: 'cash',
                is_paid: false,
                due_date: defaultDue,
                notes: '',
            },
        ];
    }

    function removePaymentTerm(id: string) {
        if (paymentTerms.length <= 2) {
            alert('Minimal 2 termin untuk Split Payment.');
            return;
        }
        paymentTerms = paymentTerms.filter((t) => t.id !== id);
    }

    function autoBalanceTerms() {
        if (paymentTerms.length === 0) return;
        const sumExceptLast = paymentTerms.slice(0, -1).reduce((sum, t) => sum + (Number(t.amount) || 0), 0);
        paymentTerms[paymentTerms.length - 1].amount = Math.max(0, grandTotal - sumExceptLast);
    }

    function splitEqually(count: number) {
        if (count < 2) count = 2;
        const base = Math.floor(grandTotal / count);
        const remainder = grandTotal - (base * count);

        const newTerms: PaymentTermItem[] = [];
        for (let i = 0; i < count; i++) {
            const isFirst = i === 0;
            const amt = isFirst ? (base + remainder) : base;
            const d = new Date();
            d.setDate(d.getDate() + ((i + 1) * 3));
            newTerms.push({
                id: Math.random().toString(36).substring(2, 9),
                term_name: isFirst ? 'Termin 1 (DP Uang Muka)' : (i === count - 1 ? `Termin ${i + 1} (Pelunasan Akhir)` : `Termin ${i + 1} (Progress Cetak)`),
                amount: amt,
                payment_method: 'cash',
                is_paid: isFirst,
                due_date: isFirst ? '' : d.toISOString().split('T')[0],
                notes: isFirst ? 'Uang Muka' : '',
            });
        }
        paymentTerms = newTerms;
    }

    // Add product to cart
    function addToCart(product: ProductItem) {
        if (!activeShift) {
            showOpenShiftModal = true;
            return;
        }

        const isDimension = product.pricing_type === 'dimension_sqm';
        const length = isDimension ? 1 : 1;
        const width = isDimension ? 1 : 1;
        const area = isDimension ? Math.max(product.min_order || 1, length * width) : 1;
        const unitPrice = Number(product.base_price);
        const subtotal = Math.round(isDimension ? unitPrice * area : unitPrice);

        const newItem: CartItem = {
            temp_id: Math.random().toString(36).substring(2, 9),
            product_id: product.id,
            product_name: product.name,
            pricing_type: product.pricing_type,
            unit: product.unit,
            base_price: unitPrice,
            cost_price: Number(product.cost_price || 0),
            length: length,
            width: width,
            area: area,
            quantity: 1,
            finishing_option: isDimension ? 'Mata Ayam 4 Sudut' : 'Standar',
            finishing_price: 0,
            subtotal: subtotal,
            design_note: '',
        };

        cart = [...cart, newItem];
    }

    // Recalculate item subtotal
    function updateItemCalculation(item: CartItem) {
        if (item.pricing_type === 'dimension_sqm') {
            const rawArea = (Number(item.length) || 0) * (Number(item.width) || 0);
            item.area = Math.round(rawArea * 100) / 100;
            // Area calculated with min order 1 if applicable
            const billedArea = Math.max(1, item.area);
            item.subtotal = Math.round((billedArea * item.base_price + (Number(item.finishing_price) || 0)) * (Number(item.quantity) || 1));
        } else {
            item.subtotal = Math.round((item.base_price + (Number(item.finishing_price) || 0)) * (Number(item.quantity) || 1));
        }
        cart = [...cart];
    }

    function removeItem(index: number) {
        cart = cart.filter((_, i) => i !== index);
    }

    function clearCart() {
        cart = [];
        cashPaidInput = '';
        orderNotes = '';
        dueDate = '';
        paymentMethod = 'cash';
        deliveryType = 'pickup';
        courierName = 'Gojek (GoSend)';
        bookingCode = '';
        deliveryNotes = '';
    }

    function setQuickCash(amount: number) {
        cashPaidInput = Math.round(Number(amount) || 0).toString();
    }

    function submitOrder() {
        if (isSubmitting) return;

        if (!activeShift) {
            showOpenShiftModal = true;
            return;
        }

        if (cart.length === 0) {
            alert('Keranjang pesanan masih kosong.');
            return;
        }

        if (paymentMethod === 'cash' && paidAmount < grandTotal) {
            alert('Jumlah pembayaran tunai kurang dari total pesanan!');
            return;
        }

        if (paymentMethod === 'split') {
            if (Math.abs(termAllocationDiff) > 1) {
                alert(`Total alokasi termin (${formatRupiah(totalAllocatedTerms)}) harus sama dengan Grand Total (${formatRupiah(grandTotal)}). Klik tombol 'Sesuaikan Sisa' untuk menyeimbangkan.`);
                return;
            }
        }

        isSubmitting = true;
        const clientToken = (typeof crypto !== 'undefined' && crypto.randomUUID)
            ? crypto.randomUUID()
            : (Date.now() + '-' + Math.random().toString(36).substring(2));

        const isSplit = paymentMethod === 'split';

        router.post(
            '/pos',
            {
                client_token: clientToken,
                customer_id: selectedCustomerId,
                customer_name: selectedCustomer?.name || 'Pelanggan Umum',
                customer_phone: selectedCustomer?.phone || '-',
                items: cart.map((item) => ({
                    product_id: item.product_id,
                    product_name: item.product_name,
                    pricing_type: item.pricing_type,
                    unit: item.unit,
                    length: item.length,
                    width: item.width,
                    area: item.area,
                    quantity: item.quantity,
                    unit_price: item.base_price,
                    finishing_option: item.finishing_option,
                    finishing_price: item.finishing_price,
                    cost_price: item.cost_price,
                    subtotal: item.subtotal,
                    design_note: item.design_note,
                })),
                subtotal: Math.round(cartSubtotal),
                discount_amount: Math.round(discountAmount),
                tax_amount: 0,
                grand_total: Math.round(grandTotal),
                paid_amount: Math.round(paymentMethod === 'pending' ? 0 : (isSplit ? totalPaidTermsNow : paidAmount)),
                change_amount: Math.round(paymentMethod === 'cash' ? changeAmount : 0),
                payment_method: isSplit ? 'split' : paymentMethod,
                is_split_payment: isSplit,
                terms: isSplit
                    ? paymentTerms.map((t, idx) => ({
                          term_name: t.term_name || `Termin ${idx + 1}`,
                          amount: Number(t.amount) || 0,
                          payment_method: t.payment_method,
                          is_paid: t.is_paid,
                          due_date: t.due_date || null,
                          notes: t.notes || null,
                      }))
                    : null,
                order_status: paymentMethod === 'pending' ? 'processing' : 'completed',
                due_date: isSplit
                    ? (paymentTerms.find((t) => !t.is_paid && t.due_date)?.due_date || null)
                    : (dueDate || null),
                notes: orderNotes,
                delivery_type: deliveryType,
                courier_name: deliveryType === 'courier' ? courierName : null,
                booking_code: deliveryType === 'courier' ? bookingCode : null,
                delivery_notes: deliveryType === 'courier' ? deliveryNotes : null,
            },
            {
                onFinish: () => {
                    isSubmitting = false;
                },
            }
        );
    }

    function openShiftSubmit() {
        router.post(
            '/shifts/start',
            {
                starting_cash: parseFloat(startingCashInput) || 0,
                notes: shiftNotes,
            },
            {
                onSuccess: () => {
                    showOpenShiftModal = false;
                },
            }
        );
    }

    function quickCustomerSubmit() {
        router.post(
            '/pos/quick-customer',
            {
                name: newCustName,
                phone: newCustPhone,
                customer_type: newCustType,
                discount_percent: parseFloat(newCustDiscount) || 0,
            },
            {
                onSuccess: () => {
                    showNewCustomerModal = false;
                    newCustName = '';
                    newCustPhone = '';
                },
            }
        );
    }
</script>

<AppHead title="Kasir Percetakan (POS)" />

<div class="flex flex-col h-[calc(100vh-4.2rem)] overflow-hidden bg-background">
    <!-- Top Bar: Shift & Quick Info -->
    <div class="flex items-center justify-between px-4 py-2.5 border-b bg-card shrink-0">
        <div class="flex items-center gap-3">
            <span class="font-bold text-sm tracking-tight flex items-center gap-1.5 text-foreground">
                <Printer class="size-4 text-primary" />
                POS Kasir
            </span>
            <span class="text-xs text-muted-foreground font-medium bg-muted/60 px-2 py-0.5 rounded">
                Invoice: {nextInvoice}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                size="sm"
                class="h-8 text-xs font-semibold border-indigo-300 dark:border-indigo-800 text-indigo-900 dark:text-indigo-200 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40"
                onclick={() => (showPendingOrdersModal = true)}
                title="Lihat daftar pesanan pending / yang akan diambil customer"
            >
                <Clock class="size-3.5 mr-1 text-indigo-600 dark:text-indigo-400" />
                Ambil Pesanan / Pending
                {#if pendingOrders && pendingOrders.length > 0}
                    <Badge variant="secondary" class="ml-1.5 h-4 px-1.5 text-[10px] bg-indigo-200 text-indigo-900 dark:bg-indigo-900 dark:text-indigo-200 font-bold">
                        {pendingOrders.length}
                    </Badge>
                {/if}
            </Button>

            <Button
                variant="outline"
                size="sm"
                class="h-8 text-xs font-semibold border-emerald-300 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40"
                onclick={() => (showDriverPickupModal = true)}
                title="Pencocokan kode booking driver Gojek / Grab yang pick-up barang"
            >
                <Motorbike class="size-3.5 mr-1 text-emerald-600 dark:text-emerald-400" />
                Pick-up Kurir (Ojol)
                {#if courierWaitingCount > 0}
                    <Badge variant="secondary" class="ml-1.5 h-4 px-1.5 text-[10px] bg-emerald-200 text-emerald-900 dark:bg-emerald-900 dark:text-emerald-200 font-bold">
                        {courierWaitingCount}
                    </Badge>
                {/if}
            </Button>

            {#if activeShift}
                <div class="flex items-center gap-2 text-xs bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 px-3 py-1 rounded-md">
                    <Clock class="size-3.5 text-emerald-600" />
                    <span>Shift Aktif: <strong>{activeShift.user?.name}</strong></span>
                    <span class="text-emerald-500">•</span>
                    <span>Kas: {formatRupiah(activeShift.expected_cash)}</span>
                </div>
            {:else}
                <Button size="sm" variant="destructive" class="h-8 text-xs font-semibold" onclick={() => (showOpenShiftModal = true)}>
                    <AlertCircle class="size-3.5 mr-1" />
                    Buka Shift Kasir Sekarang
                </Button>
            {/if}
        </div>
    </div>

    <!-- Main POS Grid: Left (Products) | Right (Cart & Checkout) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 flex-1 overflow-hidden">
        <!-- LEFT: Product Catalog (7 cols) -->
        <div class="lg:col-span-7 flex flex-col h-full border-r overflow-hidden bg-muted/10">
            <!-- Search & Categories Bar -->
            <div class="p-3 border-b bg-card space-y-2.5">
                <div class="relative">
                    <Search class="size-4 absolute left-3 top-2.5 text-muted-foreground" />
                    <Input
                        bind:value={searchQuery}
                        placeholder="Cari produk cetak (nama, kode banner, stiker)..."
                        class="pl-9 h-9 text-xs"
                    />
                </div>

                <!-- Category Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none">
                    <button
                        onclick={() => (selectedCategory = 'all')}
                        class="px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap transition-colors {selectedCategory === 'all' ? 'bg-primary text-primary-foreground shadow-xs' : 'bg-muted text-muted-foreground hover:bg-muted/80'}"
                    >
                        Semua Kategori
                    </button>
                    {#each categories as cat}
                        <button
                            onclick={() => (selectedCategory = cat.id)}
                            class="px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap transition-colors {selectedCategory === cat.id ? 'bg-primary text-primary-foreground shadow-xs' : 'bg-muted text-muted-foreground hover:bg-muted/80'}"
                        >
                            {cat.name} ({cat.products_count})
                        </button>
                    {/each}
                </div>
            </div>

            <!-- Product Grid -->
            <div class="flex-1 overflow-y-auto p-3">
                {#if filteredProducts.length === 0}
                    <div class="h-64 flex flex-col items-center justify-center text-muted-foreground gap-2">
                        <ShoppingBag class="size-8 opacity-40" />
                        <span class="text-xs">Tidak ada produk ditemukan.</span>
                    </div>
                {:else}
                    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2.5">
                        {#each filteredProducts as p}
                            <button
                                onclick={() => addToCart(p)}
                                class="flex flex-col text-left p-3 rounded-xl border bg-card hover:border-primary/60 hover:shadow-sm transition-all group relative overflow-hidden"
                            >
                                <span class="text-xs font-semibold text-foreground line-clamp-2 group-hover:text-primary transition-colors">
                                    {p.name}
                                </span>
                                <span class="text-[11px] text-muted-foreground mt-0.5">
                                    {p.code}
                                </span>

                                <div class="mt-3 pt-2 border-t flex items-center justify-between w-full">
                                    <div>
                                        <div class="text-xs font-bold text-primary">
                                            {formatRupiah(p.base_price)}
                                        </div>
                                        <div class="text-[10px] text-muted-foreground">
                                            /{p.unit}
                                        </div>
                                    </div>
                                    <div class="size-6 rounded-full bg-primary/10 text-primary flex items-center justify-center group-hover:bg-primary group-hover:text-primary-foreground transition-colors">
                                        <Plus class="size-3.5" />
                                    </div>
                                </div>
                            </button>
                        {/each}
                    </div>
                {/if}
            </div>
        </div>

        <!-- RIGHT: Order Cart & Checkout (5 cols) -->
        <div class="lg:col-span-5 flex flex-col h-full bg-card overflow-hidden">
            <!-- Customer Selection Header -->
            <div class="p-3 border-b flex items-center justify-between gap-2 bg-muted/20">
                <div class="flex items-center gap-2 flex-1">
                    <Users class="size-4 text-muted-foreground shrink-0" />
                    <select
                        bind:value={selectedCustomerId}
                        class="h-8 text-xs rounded-md border border-input bg-background pl-2 pr-8 py-1 w-full focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option value={null}>Pelanggan Umum (Walk-in - 0% Diskon)</option>
                        {#each customers as c}
                            <option value={c.id}>
                                {c.name} ({c.customer_type.toUpperCase()} - Diskon {c.discount_percent}%)
                            </option>
                        {/each}
                    </select>
                </div>
                <Button size="sm" variant="outline" class="h-8 px-2 text-xs shrink-0 whitespace-nowrap inline-flex items-center" onclick={() => (showNewCustomerModal = true)}>
                    <UserPlus class="size-3.5 mr-1" />
                    Tambah Pelanggan
                </Button>
            </div>

            <!-- Cart Items List -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2.5">
                {#if cart.length === 0}
                    <div class="h-full flex flex-col items-center justify-center text-muted-foreground gap-2 p-6 text-center">
                        <ShoppingBag class="size-10 opacity-30" />
                        <span class="text-sm font-medium">Keranjang Cetak Kosong</span>
                        <span class="text-xs text-muted-foreground/80 max-w-xs">
                            Pilih produk cetak di sebelah kiri untuk mulai membuat pesanan nota.
                        </span>
                    </div>
                {:else}
                    {#each cart as item, index (item.temp_id)}
                        <div class="p-3 rounded-lg border bg-background/80 shadow-2xs space-y-2">
                            <!-- Top Row: Item Title & Quantity + Delete button -->
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-xs text-foreground truncate flex-1" title={item.product_name}>
                                    {item.product_name}
                                </span>

                                <!-- Input quantity di sebelah tombol delete item -->
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <div class="flex items-center border border-input rounded bg-background h-7">
                                        <button
                                            type="button"
                                            onclick={() => {
                                                if (item.quantity > 1) {
                                                    item.quantity -= 1;
                                                    updateItemCalculation(item);
                                                }
                                            }}
                                            class="px-1.5 h-full flex items-center justify-center text-muted-foreground hover:bg-muted rounded-l"
                                            title="Kurangi"
                                        >
                                            <Minus class="size-3" />
                                        </button>
                                        <input
                                            type="number"
                                            min="1"
                                            bind:value={item.quantity}
                                            oninput={() => updateItemCalculation(item)}
                                            class="w-10 text-center text-xs font-semibold focus:outline-none bg-transparent"
                                        />
                                        <button
                                            type="button"
                                            onclick={() => {
                                                item.quantity += 1;
                                                updateItemCalculation(item);
                                            }}
                                            class="px-1.5 h-full flex items-center justify-center text-muted-foreground hover:bg-muted rounded-r"
                                            title="Tambah"
                                        >
                                            <Plus class="size-3" />
                                        </button>
                                    </div>

                                    <button
                                        type="button"
                                        onclick={() => removeItem(index)}
                                        class="size-7 inline-flex items-center justify-center text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded transition-colors"
                                        title="Hapus Item"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Dimension Inputs for Meteran (Spanduk/Banner) -->
                            {#if item.pricing_type === 'dimension_sqm'}
                                <div class="bg-muted/40 p-2 rounded-md space-y-1.5 text-xs">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1">
                                            <span class="text-[10px] text-muted-foreground block">Panjang (m)</span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0.1"
                                                bind:value={item.length}
                                                oninput={() => updateItemCalculation(item)}
                                                class="w-full h-7 px-2 text-xs rounded border bg-background"
                                                placeholder="3.00"
                                            />
                                        </div>
                                        <span class="text-muted-foreground font-bold mt-3">×</span>
                                        <div class="flex-1">
                                            <span class="text-[10px] text-muted-foreground block">Lebar (m)</span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0.1"
                                                bind:value={item.width}
                                                oninput={() => updateItemCalculation(item)}
                                                class="w-full h-7 px-2 text-xs rounded border bg-background"
                                                placeholder="1.00"
                                            />
                                        </div>
                                        <div class="flex-1">
                                            <span class="text-[10px] text-muted-foreground block">Luas Total</span>
                                            <div class="h-7 px-2 text-xs font-semibold flex items-center bg-background rounded border">
                                                {item.area} m²
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Finishing Dropdown -->
                                    <div class="flex items-center gap-2 pt-1">
                                        <div class="flex-1">
                                            <span class="text-[10px] text-muted-foreground block">Finishing Cetak</span>
                                            <select
                                                bind:value={item.finishing_option}
                                                onchange={() => {
                                                    if (item.finishing_option === 'Mata Ayam Keliling') item.finishing_price = 10000;
                                                    else if (item.finishing_option === 'Selongsong') item.finishing_price = 10000;
                                                    else if (item.finishing_option === 'Laminasi Glossy/Doff') item.finishing_price = 15000;
                                                    else item.finishing_price = 0;
                                                    updateItemCalculation(item);
                                                }}
                                                class="w-full h-7 pl-2 pr-8 text-xs rounded border bg-background"
                                            >
                                                <option value="Mata Ayam 4 Sudut">Mata Ayam 4 Sudut (+Rp 0)</option>
                                                <option value="Mata Ayam Keliling">Mata Ayam Keliling (+Rp 10.000)</option>
                                                <option value="Selongsong">Selongsong Atas Bawah (+Rp 10.000)</option>
                                                <option value="Lebih Bahan 2.5cm">Lebih Bahan (+Rp 0)</option>
                                                <option value="Potong Pas">Potong Pas Gambar (+Rp 0)</option>
                                                <option value="Laminasi Glossy/Doff">Laminasi (+Rp 15.000)</option>
                                                <option value="Tanpa Finishing">Tanpa Finishing (+Rp 0)</option>
                                            </select>
                                        </div>
                                        <div class="w-24">
                                            <span class="text-[10px] text-muted-foreground block">Biaya Fin</span>
                                            <input
                                                type="number"
                                                bind:value={item.finishing_price}
                                                oninput={() => updateItemCalculation(item)}
                                                class="w-full h-7 px-2 text-xs rounded border bg-background"
                                                placeholder="0"
                                            />
                                        </div>
                                    </div>
                                </div>
                            {/if}

                            <!-- Catatan Desain & Subtotal -->
                            <div class="flex items-center gap-2 pt-0.5">
                                <input
                                    type="text"
                                    bind:value={item.design_note}
                                    placeholder="Nama file / catatan desain..."
                                    class="flex-1 h-7 px-2 text-xs rounded border bg-background"
                                />

                                <div class="text-right font-bold text-xs text-foreground min-w-[80px]">
                                    {formatRupiah(item.subtotal)}
                                </div>
                            </div>
                        </div>
                    {/each}
                {/if}
            </div>

            <!-- Footer: Totals & Checkout Form -->
            <div class="p-3 border-t bg-muted/15 space-y-3 shrink-0">
                <!-- Subtotal & Discount rows -->
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between text-muted-foreground">
                        <span>Subtotal Item</span>
                        <span class="font-semibold">{formatRupiah(cartSubtotal)}</span>
                    </div>
                    {#if discountAmount > 0}
                        <div class="flex justify-between text-emerald-600 font-medium">
                            <span>Diskon Pelanggan ({discountPercent}%)</span>
                            <span class="font-semibold">-{formatRupiah(discountAmount)}</span>
                        </div>
                    {/if}
                    <div class="flex justify-between items-center text-sm font-bold text-foreground pt-1 border-t">
                        <span>Grand Total</span>
                        <span class="text-base text-primary font-bold">{formatRupiah(grandTotal)}</span>
                    </div>
                </div>

                <!-- Pengambilan: Ambil Toko vs Kurir Ojol -->
                <div class="space-y-1.5 bg-background p-2 rounded-md border text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold text-muted-foreground">Pengambilan:</span>
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                class="px-2 py-0.5 rounded text-[11px] font-medium transition-colors {deliveryType === 'pickup' ? 'bg-primary text-primary-foreground font-bold shadow-2xs' : 'text-muted-foreground hover:bg-muted'}"
                                onclick={() => (deliveryType = 'pickup')}
                            >
                                Ambil di Toko
                            </button>
                            <button
                                type="button"
                                class="px-2 py-0.5 rounded text-[11px] font-medium transition-colors {deliveryType === 'courier' ? 'bg-indigo-600 text-white font-bold shadow-2xs' : 'text-muted-foreground hover:bg-muted'}"
                                onclick={() => (deliveryType = 'courier')}
                            >
                                <Motorbike class="size-3 inline-block mr-0.5" />
                                Kirim Ojol
                            </button>
                        </div>
                    </div>
                    {#if deliveryType === 'courier'}
                        <div class="pt-1.5 border-t space-y-1.5 text-xs">
                            <div class="grid grid-cols-2 gap-1.5">
                                <select
                                    bind:value={courierName}
                                    class="h-7 text-xs rounded border border-input bg-background px-2"
                                >
                                    <option value="Gojek (GoSend)">Gojek (GoSend)</option>
                                    <option value="Grab (GrabExpress)">Grab (GrabExpress)</option>
                                    <option value="Maxim">Maxim</option>
                                    <option value="Lalamove">Lalamove</option>
                                    <option value="Kurir Toko">Kurir Toko</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                                <Input
                                    bind:value={bookingCode}
                                    placeholder="Kode Booking (opsional)"
                                    class="h-7 text-xs font-mono uppercase"
                                />
                            </div>
                        </div>
                    {/if}
                </div>

                <!-- Payment Method Tabs -->
                <div class="grid grid-cols-5 gap-1">
                    <button
                        onclick={() => (paymentMethod = 'cash')}
                        class="py-1.5 text-xs font-medium rounded border transition-colors flex flex-col items-center gap-1 {paymentMethod === 'cash' ? 'bg-primary text-primary-foreground border-primary font-bold' : 'bg-background hover:bg-muted'}"
                        title="Pembayaran Tunai"
                    >
                        <DollarSign class="size-3.5" />
                        Tunai
                    </button>
                    <button
                        onclick={() => (paymentMethod = 'qris')}
                        class="py-1.5 text-xs font-medium rounded border transition-colors flex flex-col items-center gap-1 {paymentMethod === 'qris' ? 'bg-primary text-primary-foreground border-primary font-bold' : 'bg-background hover:bg-muted'}"
                        title="Pembayaran QRIS"
                    >
                        <QrCode class="size-3.5" />
                        QRIS
                    </button>
                    <button
                        onclick={() => (paymentMethod = 'transfer')}
                        class="py-1.5 text-xs font-medium rounded border transition-colors flex flex-col items-center gap-1 {paymentMethod === 'transfer' ? 'bg-primary text-primary-foreground border-primary font-bold' : 'bg-background hover:bg-muted'}"
                        title="Pembayaran Transfer Bank"
                    >
                        <CreditCard class="size-3.5" />
                        Transfer
                    </button>
                    <button
                        onclick={() => initSplitTerms(50)}
                        class="py-1.5 text-xs font-medium rounded border transition-colors flex flex-col items-center gap-1 {paymentMethod === 'split' ? 'bg-amber-600 text-white border-amber-600 font-bold' : 'bg-background hover:bg-muted text-amber-700'}"
                        title="Uang Muka / Bertahap"
                    >
                        <Split class="size-3.5" />
                        Termin
                    </button>
                    <button
                        onclick={() => (paymentMethod = 'pending')}
                        class="py-1.5 text-xs font-medium rounded border transition-colors flex flex-col items-center gap-1 {paymentMethod === 'pending' ? 'bg-indigo-600 text-white border-indigo-600 font-bold shadow-xs' : 'bg-background hover:bg-muted text-indigo-700 dark:text-indigo-400'}"
                        title="Pesan Sekarang, Bayar Saat Pengambilan Barang di Toko"
                    >
                        <Clock class="size-3.5" />
                        Saat Ambil
                    </button>
                </div>

                <!-- Cash input & quick buttons (if cash) -->
                {#if paymentMethod === 'cash'}
                    <div class="space-y-1.5 bg-background p-2 rounded-md border">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground whitespace-nowrap">Uang Diterima:</span>
                            <Input
                                type="number"
                                step="1"
                                min="0"
                                bind:value={cashPaidInput}
                                placeholder="Nominal uang..."
                                class="h-8 text-xs font-bold"
                            />
                        </div>
                        <div class="flex items-center gap-1 overflow-x-auto">
                            <button onclick={() => setQuickCash(grandTotal)} class="px-2 py-0.5 text-[11px] rounded bg-muted hover:bg-muted/80 font-medium">Uang Pas</button>
                            <button onclick={() => setQuickCash(50000)} class="px-2 py-0.5 text-[11px] rounded bg-muted hover:bg-muted/80 font-medium">50k</button>
                            <button onclick={() => setQuickCash(100000)} class="px-2 py-0.5 text-[11px] rounded bg-muted hover:bg-muted/80 font-medium">100k</button>
                            <button onclick={() => setQuickCash(200000)} class="px-2 py-0.5 text-[11px] rounded bg-muted hover:bg-muted/80 font-medium">200k</button>
                            <button onclick={() => setQuickCash(500000)} class="px-2 py-0.5 text-[11px] rounded bg-muted hover:bg-muted/80 font-medium">500k</button>
                        </div>
                        <div class="flex justify-between items-center text-xs font-semibold pt-1 border-t">
                            <span class="text-muted-foreground">Kembalian:</span>
                            <span class="text-emerald-600 text-sm font-bold">{formatRupiah(changeAmount)}</span>
                        </div>
                    </div>
                {:else if paymentMethod === 'split'}
                    <div class="space-y-2 bg-amber-50/50 dark:bg-amber-950/20 p-2.5 rounded-lg border border-amber-200 text-xs">
                        <!-- Header with Presets & Add -->
                        <div class="flex items-center justify-between pb-1 border-b border-amber-200/70">
                            <span class="font-bold text-amber-900 dark:text-amber-200 flex items-center gap-1 text-xs">
                                <Split class="size-3.5 text-amber-600 shrink-0" />
                                Termin ({paymentTerms.length})
                            </span>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    onclick={() => initSplitTerms(30)}
                                    class="px-1.5 py-0.5 text-[10px] rounded bg-amber-100 hover:bg-amber-200 dark:bg-amber-900 text-amber-900 dark:text-amber-100 font-semibold transition-colors"
                                    title="Set DP 30%"
                                >
                                    DP 30%
                                </button>
                                <button
                                    type="button"
                                    onclick={() => initSplitTerms(50)}
                                    class="px-1.5 py-0.5 text-[10px] rounded bg-amber-100 hover:bg-amber-200 dark:bg-amber-900 text-amber-900 dark:text-amber-100 font-semibold transition-colors"
                                    title="Set DP 50%"
                                >
                                    DP 50%
                                </button>
                                <button
                                    type="button"
                                    onclick={() => splitEqually(3)}
                                    class="px-1.5 py-0.5 text-[10px] rounded bg-amber-100 hover:bg-amber-200 dark:bg-amber-900 text-amber-900 dark:text-amber-100 font-semibold transition-colors"
                                    title="Bagi rata 3 termin"
                                >
                                    3 Termin
                                </button>
                                <button
                                    type="button"
                                    onclick={addPaymentTerm}
                                    class="px-2 py-0.5 text-[10px] rounded bg-amber-600 hover:bg-amber-700 text-white font-semibold transition-colors flex items-center gap-0.5"
                                >
                                    <Plus class="size-3" />
                                    Termin
                                </button>
                            </div>
                        </div>

                        <!-- Termin List (Scrollable) -->
                        <div class="space-y-2 max-h-48 overflow-y-auto pr-0.5">
                            {#each paymentTerms as term, idx (term.id)}
                                <div class="p-2 rounded-md bg-background border shadow-2xs space-y-1.5">
                                    <div class="flex items-center justify-between gap-1">
                                        <Input
                                            bind:value={term.term_name}
                                            placeholder="Nama Termin..."
                                            class="h-6 text-[11px] font-semibold flex-1 px-1.5 py-0"
                                        />
                                        {#if paymentTerms.length > 2}
                                            <button
                                                type="button"
                                                onclick={() => removePaymentTerm(term.id)}
                                                class="text-destructive hover:bg-destructive/10 p-1 rounded"
                                                title="Hapus Termin Ini"
                                            >
                                                <Trash2 class="size-3" />
                                            </button>
                                        {/if}
                                    </div>

                                    <div class="grid grid-cols-12 gap-1.5 items-center">
                                        <!-- Amount Input -->
                                        <div class="col-span-5">
                                            <Input
                                                type="number"
                                                bind:value={term.amount}
                                                placeholder="0"
                                                class="h-7 text-xs font-bold px-2"
                                            />
                                        </div>

                                        <!-- Payment Method -->
                                        <div class="col-span-4">
                                            <select
                                                bind:value={term.payment_method}
                                                class="w-full h-7 text-[11px] font-medium rounded border border-input bg-background pl-1.5 pr-6 py-0"
                                            >
                                                <option value="cash">Tunai</option>
                                                <option value="qris">QRIS</option>
                                                <option value="transfer">Transfer</option>
                                            </select>
                                        </div>

                                        <!-- Paid Now Toggle Button -->
                                        <div class="col-span-3 flex justify-end">
                                            <button
                                                type="button"
                                                onclick={() => (term.is_paid = !term.is_paid)}
                                                class="h-7 px-1.5 text-[10px] font-bold rounded border transition-colors flex items-center justify-center w-full {term.is_paid ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-muted text-muted-foreground hover:bg-muted/80'}"
                                                title={term.is_paid ? 'Sudah dibayar saat ini (DP)' : 'Jatuh tempo (belum dibayar)'}
                                            >
                                                {term.is_paid ? '✓ Lunas' : 'Tempo'}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Due Date (if not paid) -->
                                    {#if !term.is_paid}
                                        <div class="flex items-center gap-1.5 pt-0.5 text-[11px] text-muted-foreground">
                                            <Calendar class="size-3 shrink-0 text-amber-600" />
                                            <span class="text-[10px] whitespace-nowrap">Jatuh Tempo:</span>
                                            <Input
                                                type="date"
                                                bind:value={term.due_date}
                                                class="h-6 text-[10px] py-0 px-1.5 bg-background flex-1"
                                            />
                                        </div>
                                    {/if}
                                </div>
                            {/each}
                        </div>

                        <!-- Summary & Balance Check -->
                        <div class="pt-1 border-t border-amber-200/70 space-y-1">
                            <div class="flex justify-between items-center text-[11px]">
                                <span class="text-amber-800 dark:text-amber-300">Dibayar Sekarang (DP):</span>
                                <span class="font-bold text-emerald-600">{formatRupiah(totalPaidTermsNow)}</span>
                            </div>
                            <div class="flex justify-between items-center text-[11px]">
                                <span class="text-amber-800 dark:text-amber-300">Sisa Belum Dibayar:</span>
                                <span class="font-bold text-amber-700 dark:text-amber-400">
                                    {formatRupiah(Math.max(0, grandTotal - totalPaidTermsNow))}
                                </span>
                            </div>

                            {#if Math.abs(termAllocationDiff) > 1}
                                <div class="flex items-center justify-between bg-destructive/10 text-destructive p-1.5 rounded text-[11px] mt-1">
                                    <span class="font-semibold text-[10px]">
                                        {termAllocationDiff > 0 ? `Kurang: ${formatRupiah(termAllocationDiff)}` : `Lebih: ${formatRupiah(-termAllocationDiff)}`}
                                    </span>
                                    <button
                                        type="button"
                                        onclick={autoBalanceTerms}
                                        class="px-2 py-0.5 rounded bg-destructive text-white text-[10px] font-bold hover:bg-destructive/90"
                                    >
                                        Sesuaikan Sisa
                                    </button>
                                </div>
                            {:else}
                                <div class="text-[10px] text-emerald-700 dark:text-emerald-400 font-semibold text-center py-0.5 bg-emerald-50 dark:bg-emerald-950/40 rounded border border-emerald-200">
                                    ✓ Alokasi {paymentTerms.length} termin pas 100% ({formatRupiah(totalAllocatedTerms)})
                                </div>
                            {/if}
                        </div>
                    </div>
                {:else if paymentMethod === 'pending'}
                    <div class="space-y-2 bg-indigo-50/70 dark:bg-indigo-950/30 p-2.5 rounded-lg border border-indigo-200 dark:border-indigo-800 text-xs">
                        <div class="flex items-center justify-between pb-1 border-b border-indigo-200/70 dark:border-indigo-800/70">
                            <span class="font-bold text-indigo-950 dark:text-indigo-200 flex items-center gap-1.5 text-xs">
                                <Clock class="size-3.5 text-indigo-600 dark:text-indigo-400 shrink-0" />
                                Bayar Saat Ambil (Pending)
                            </span>
                            <Badge variant="outline" class="bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200 border-indigo-300 text-[10px] font-semibold">
                                Belum Bayar
                            </Badge>
                        </div>
                        <p class="text-[11px] text-muted-foreground leading-relaxed">
                            Customer pesan cetak dulu sekarang dan akan membayar penuh sebesar <strong class="text-foreground">{formatRupiah(grandTotal)}</strong> saat pengambilan pesanan di toko.
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 border-t border-indigo-200/50 dark:border-indigo-800/50">
                            <div class="space-y-1">
                                <Label class="text-[10px] text-muted-foreground font-medium">Estimasi Tgl Ambil:</Label>
                                <Input type="date" bind:value={dueDate} class="h-7 text-xs bg-background" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-[10px] text-muted-foreground font-medium">Catatan Pengambilan:</Label>
                                <Input bind:value={orderNotes} placeholder="Contoh: Diambil jam 4 sore..." class="h-7 text-xs bg-background" />
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Action Buttons: Clear & Checkout -->
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" class="h-9 px-3 text-xs text-muted-foreground" onclick={clearCart} disabled={cart.length === 0}>
                        Reset
                    </Button>
                    <Button
                        class="flex-1 h-9 {paymentMethod === 'pending' ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-primary hover:bg-primary/90 text-primary-foreground'} font-bold text-xs shadow-xs"
                        onclick={submitOrder}
                        disabled={cart.length === 0 || isSubmitting}
                    >
                        {#if isSubmitting}
                            <LoaderCircle class="size-4 mr-1.5 animate-spin" />
                            Memproses Transaksi...
                        {:else if paymentMethod === 'pending'}
                            <Clock class="size-4 mr-1.5" />
                            Simpan Pesanan (Bayar Saat Ambil)
                        {:else}
                            <Check class="size-4 mr-1.5" />
                            Bayar & Cetak Nota ({formatRupiah(grandTotal)})
                        {/if}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Buka Shift Kasir -->
<Dialog open={showOpenShiftModal} onOpenChange={(open) => (showOpenShiftModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Clock class="size-5 text-primary" />
                Buka Shift Kasir
            </DialogTitle>
            <DialogDescription class="text-xs">
                Masukkan modal kas awal di laci kasir sebelum memulai sesi transaksi POS hari ini.
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-4 py-2">
            <div class="space-y-1.5">
                <Label for="modal_kas" class="text-xs">Modal Awal Kas (Rp)</Label>
                <Input
                    id="modal_kas"
                    type="number"
                    bind:value={startingCashInput}
                    placeholder="200000"
                    class="text-sm font-bold"
                />
            </div>
            <div class="space-y-1.5">
                <Label for="shift_notes" class="text-xs">Catatan Shift (Opsional)</Label>
                <Textarea
                    id="shift_notes"
                    bind:value={shiftNotes}
                    placeholder="Shift Pagi / Siang, kondisi laci kasir..."
                    rows={2}
                    class="text-xs"
                />
            </div>
        </div>

        <DialogFooter>
            <Button class="w-full" onclick={openShiftSubmit}>
                Buka Shift & Masuk Kasir
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Modal: Tambah Pelanggan Cepat -->
<Dialog open={showNewCustomerModal} onOpenChange={(open) => (showNewCustomerModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <UserPlus class="size-5 text-primary" />
                Tambah Pelanggan
            </DialogTitle>
            <DialogDescription class="text-xs">
                Data pelanggan tersimpan otomatis untuk diskon khusus dan riwayat order.
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
            <div class="space-y-1">
                <Label for="c_name">Nama Pelanggan / Usaha *</Label>
                <Input id="c_name" bind:value={newCustName} placeholder="Contoh: Andi Advertising" />
            </div>
            <div class="space-y-1">
                <Label for="c_phone">No. WhatsApp / HP</Label>
                <Input id="c_phone" bind:value={newCustPhone} placeholder="08123456789" />
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <Label for="c_type">Tipe Pelanggan</Label>
                    <select
                        id="c_type"
                        bind:value={newCustType}
                        onchange={() => {
                            if (newCustType === 'reseller') newCustDiscount = '15';
                            else if (newCustType === 'corporate') newCustDiscount = '10';
                            else if (newCustType === 'member') newCustDiscount = '5';
                            else newCustDiscount = '0';
                        }}
                        class="w-full h-9 rounded-md border border-input bg-background pl-3 pr-8 py-1"
                    >
                        <option value="regular">Regular (Umum)</option>
                        <option value="member">Member Setia</option>
                        <option value="reseller">Reseller Percetakan</option>
                        <option value="corporate">Perusahaan / Instansi</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <Label for="c_disc">Diskon Khusus (%)</Label>
                    <Input id="c_disc" type="number" bind:value={newCustDiscount} placeholder="0" />
                </div>
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (showNewCustomerModal = false)}>Batal</Button>
            <Button onclick={quickCustomerSubmit} disabled={!newCustName}>Simpan Pelanggan</Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Modal: Pesanan Pending / Ambil di Toko -->
<Dialog open={showPendingOrdersModal} onOpenChange={(open) => (showPendingOrdersModal = open)}>
    <DialogContent class="sm:max-w-3xl max-h-[85vh] flex flex-col p-0">
        <DialogHeader class="px-6 pt-5 pb-3 border-b">
            <div class="flex items-center justify-between">
                <div>
                    <DialogTitle class="flex items-center gap-2 text-base font-bold">
                        <Clock class="size-5 text-indigo-600" />
                        Daftar Pesanan Pending & Siap Ambil
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                        Pesanan yang belum lunas atau menunggu pelunasan saat customer datang mengambil hasil cetak ke toko.
                    </DialogDescription>
                </div>
                <Badge variant="outline" class="font-bold text-xs bg-indigo-50 text-indigo-700 border-indigo-200">
                    {pendingOrders.length} Pesanan
                </Badge>
            </div>
            <!-- Search bar -->
            <div class="pt-3">
                <div class="relative">
                    <Search class="size-3.5 absolute left-3 top-2.5 text-muted-foreground" />
                    <Input
                        bind:value={pendingSearchQuery}
                        placeholder="Cari nomor invoice, nama pelanggan, atau no telepon..."
                        class="pl-8 h-8 text-xs bg-background"
                    />
                </div>
            </div>
        </DialogHeader>

        <!-- Body: List of orders -->
        <div class="flex-1 overflow-y-auto p-6 space-y-2.5">
            {#if filteredPendingOrders.length === 0}
                <div class="py-12 text-center text-muted-foreground space-y-2">
                    <Clock class="size-10 mx-auto text-muted-foreground/40" />
                    <p class="text-xs font-medium">Tidak ada pesanan pending yang menunggu pembayaran.</p>
                </div>
            {:else}
                <div class="space-y-2">
                    {#each filteredPendingOrders as order (order.id)}
                        {@const remaining = Math.max(0, Number(order.grand_total) - Number(order.paid_amount))}
                        <div class="p-3 rounded-lg border bg-card hover:border-indigo-300 dark:hover:border-indigo-800 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-foreground text-sm">{order.invoice_number}</span>
                                    {#if order.payment_method === 'pending'}
                                        <Badge class="bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 text-[10px] font-semibold border-indigo-200">
                                            Bayar Saat Ambil
                                        </Badge>
                                    {:else}
                                        <Badge variant="outline" class="uppercase text-[10px] font-semibold">
                                            {order.payment_method} ({order.payment_status === 'partial' ? 'DP' : 'Belum Lunas'})
                                        </Badge>
                                    {/if}

                                    {#if order.order_status === 'ready'}
                                        <Badge variant="outline" class="bg-purple-50 text-purple-700 border-purple-200 text-[10px]">
                                            Siap Ambil
                                        </Badge>
                                    {:else if order.order_status === 'processing'}
                                        <Badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200 text-[10px]">
                                            Proses Cetak
                                        </Badge>
                                    {:else}
                                        <Badge variant="outline" class="bg-amber-50 text-amber-700 border-amber-200 text-[10px]">
                                            Antrian
                                        </Badge>
                                    {/if}
                                </div>

                                <div class="text-muted-foreground flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-foreground">{order.customer_name || 'Pelanggan Umum'}</span>
                                    {#if order.customer_phone && order.customer_phone !== '-'}
                                        <span>• {order.customer_phone}</span>
                                    {/if}
                                    <span>• {new Date(order.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}</span>
                                </div>

                                {#if order.items && order.items.length > 0}
                                    <div class="text-[11px] text-muted-foreground line-clamp-1">
                                        Item: {order.items.map((it) => `${it.product_name} (${it.quantity} ${it.unit})`).join(', ')}
                                    </div>
                                {/if}

                                {#if order.notes}
                                    <div class="text-[10px] italic text-amber-700 dark:text-amber-400">
                                        Catatan: {order.notes}
                                    </div>
                                {/if}
                            </div>

                            <div class="flex items-center sm:flex-col sm:items-end justify-between gap-2 shrink-0 border-t sm:border-t-0 pt-2 sm:pt-0">
                                <div class="text-left sm:text-right">
                                    <div class="text-muted-foreground text-[10px]">Sisa Tagihan:</div>
                                    <div class="font-bold text-sm text-destructive">{formatRupiah(remaining)}</div>
                                    <div class="text-[10px] text-muted-foreground">Total: {formatRupiah(order.grand_total)}</div>
                                </div>

                                <a href="/orders/{order.id}">
                                    <Button size="sm" class="h-8 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-bold">
                                        <DollarSign class="size-3.5 mr-1" />
                                        Pelunasan & Nota
                                    </Button>
                                </a>
                            </div>
                        </div>
                    {/each}
                </div>
            {/if}
        </div>

        <DialogFooter class="px-6 py-3 border-t bg-muted/20">
            <Button variant="outline" class="text-xs" onclick={() => (showPendingOrdersModal = false)}>
                Tutup
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Modal: Verifikasi & Pencocokan Kode Pick-up Driver (Gojek / Grab) -->
<DriverPickupModal bind:open={showDriverPickupModal} />

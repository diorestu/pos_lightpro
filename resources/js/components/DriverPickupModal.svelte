<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AlertCircle from '@lucide/svelte/icons/alert-circle';
    import AlertTriangle from '@lucide/svelte/icons/alert-triangle';
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import Clock from '@lucide/svelte/icons/clock';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import Motorbike from '@lucide/svelte/icons/motorbike';
    import PackageCheck from '@lucide/svelte/icons/package-check';
    import Phone from '@lucide/svelte/icons/phone';
    import Search from '@lucide/svelte/icons/search';
    import User from '@lucide/svelte/icons/user';
    import X from '@lucide/svelte/icons/x';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { toast } from 'svelte-sonner';

    let {
        open = $bindable(false),
        onPickupConfirmed = () => {},
    }: {
        open: boolean;
        onPickupConfirmed?: () => void;
    } = $props();

    let searchCode = $state('');
    let isSearching = $state(false);
    let hasSearched = $state(false);
    let exactMatch = $state(false);
    let matchedOrders = $state<any[]>([]);
    let selectedOrder = $state<any | null>(null);

    // Form inputs for confirming driver pickup
    let driverName = $state('');
    let driverPhone = $state('');
    let pickupNotes = $state('');
    let markAsCompleted = $state(true);
    let isSubmitting = $state(false);

    // Waiting list
    let waitingOrders = $state<any[]>([]);
    let isLoadingWaiting = $state(false);

    function formatRupiah(amount: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount || 0);
    }

    async function fetchWaitingOrders() {
        isLoadingWaiting = true;
        try {
            const res = await fetch('/orders/lookup-booking', {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                waitingOrders = data.orders || [];
            }
        } catch (err) {
            console.error('Error fetching waiting courier orders:', err);
        } finally {
            isLoadingWaiting = false;
        }
    }

    async function handleSearch(manualQuery?: string) {
        const query = (manualQuery !== undefined ? manualQuery : searchCode).trim();
        if (!query) {
            hasSearched = false;
            matchedOrders = [];
            selectedOrder = null;
            return;
        }

        isSearching = true;
        hasSearched = true;
        try {
            const res = await fetch(`/orders/lookup-booking?code=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                matchedOrders = data.orders || [];
                exactMatch = !!data.exact_match;

                if (matchedOrders.length === 1) {
                    selectOrder(matchedOrders[0]);
                } else if (matchedOrders.length === 0) {
                    selectedOrder = null;
                }
            }
        } catch (err) {
            console.error('Lookup booking code error:', err);
            toast.error('Gagal mencocokkan kode booking.');
        } finally {
            isSearching = false;
        }
    }

    function selectOrder(order: any) {
        selectedOrder = order;
        driverName = order.driver_name || '';
        driverPhone = order.driver_phone || '';
        pickupNotes = '';
        markAsCompleted = true;
    }

    function clearSelection() {
        selectedOrder = null;
    }

    function resetModal() {
        searchCode = '';
        isSearching = false;
        hasSearched = false;
        exactMatch = false;
        matchedOrders = [];
        selectedOrder = null;
        driverName = '';
        driverPhone = '';
        pickupNotes = '';
        markAsCompleted = true;
        isSubmitting = false;
    }

    function confirmPickup() {
        if (!selectedOrder) return;

        isSubmitting = true;
        router.post(
            `/orders/${selectedOrder.id}/confirm-driver-pickup`,
            {
                driver_name: driverName,
                driver_phone: driverPhone,
                notes: pickupNotes,
                mark_as_completed: markAsCompleted,
            },
            {
                onSuccess: () => {
                    toast.success(`Barang untuk nota ${selectedOrder.invoice_number} berhasil diserahkan ke driver!`);
                    open = false;
                    resetModal();
                    onPickupConfirmed();
                },
                onError: () => {
                    toast.error('Gagal mengonfirmasi penyerahan ke driver.');
                },
                onFinish: () => {
                    isSubmitting = false;
                },
            }
        );
    }

    $effect(() => {
        if (open) {
            fetchWaitingOrders();
        } else {
            resetModal();
        }
    });
</script>

<Dialog bind:open>
    <DialogContent class="sm:max-w-2xl max-h-[90vh] overflow-y-auto p-0 gap-0">
        <!-- Header -->
        <DialogHeader class="p-6 pb-4 border-b bg-gradient-to-r from-amber-500/10 via-emerald-500/10 to-primary/10">
            <div class="flex items-center gap-2">
                <div class="p-2 rounded-lg bg-emerald-600 text-white shadow-xs">
                    <Motorbike class="size-5" />
                </div>
                <div>
                    <DialogTitle class="text-base font-bold flex items-center gap-2">
                        Pencocokan Kode Pick-up Driver (Gojek / Grab / Kurir)
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                        Cocokkan kode booking dari driver untuk verifikasi barang cetakan sebelum diserahkan.
                    </DialogDescription>
                </div>
            </div>
        </DialogHeader>

        <div class="p-6 space-y-4">
            <!-- Search & Code Matching Box -->
            <div class="space-y-1.5">
                <Label for="booking-code-search" class="text-xs font-semibold">
                    Kode Booking / PIN / No. Resi dari Driver
                </Label>
                <form
                    onsubmit={(e) => {
                        e.preventDefault();
                        handleSearch();
                    }}
                    class="flex gap-2"
                >
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                        <Input
                            id="booking-code-search"
                            bind:value={searchCode}
                            placeholder="Contoh: GK-882310, GRAB-1029, atau nama pelanggan..."
                            class="pl-9 h-10 text-sm font-mono uppercase tracking-wide border-2 focus-visible:border-primary"
                            autocomplete="off"
                        />
                        {#if searchCode}
                            <button
                                type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                onclick={() => {
                                    searchCode = '';
                                    handleSearch('');
                                }}
                            >
                                <X class="size-4" />
                            </button>
                        {/if}
                    </div>
                    <Button type="submit" class="h-10 px-4 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white" disabled={isSearching}>
                        {#if isSearching}
                            Mencocokkan...
                        {:else}
                            <CheckCircle2 class="size-3.5 mr-1" />
                            Cocokkan Kode
                        {/if}
                    </Button>
                </form>
            </div>

            <!-- RESULT 1: Order Selected / Code Matched -->
            {#if selectedOrder}
                <div class="rounded-xl border-2 border-emerald-500/50 bg-emerald-50/40 dark:bg-emerald-950/20 p-4 space-y-4">
                    <!-- Match Status Banner -->
                    <div class="flex items-center justify-between pb-3 border-b border-emerald-200 dark:border-emerald-800">
                        <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-bold text-sm">
                            <CheckCircle2 class="size-5 text-emerald-600 dark:text-emerald-400" />
                            <span>
                                {exactMatch || (selectedOrder.booking_code && searchCode && selectedOrder.booking_code.toUpperCase().includes(searchCode.toUpperCase()))
                                    ? 'KODE COCOK! Pesanan Terverifikasi'
                                    : 'Pesanan Dipilih'}
                            </span>
                        </div>
                        <Button variant="ghost" size="sm" class="h-7 text-xs text-muted-foreground hover:text-foreground" onclick={clearSelection}>
                            Cari Kode Lain
                        </Button>
                    </div>

                    <!-- Order Overview -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-background/80 p-3 rounded-lg border">
                        <div>
                            <div class="text-muted-foreground text-[11px]">No. Invoice:</div>
                            <div class="font-bold text-sm text-foreground">{selectedOrder.invoice_number}</div>
                            <div class="text-muted-foreground mt-1 text-[11px]">Pelanggan:</div>
                            <div class="font-semibold text-foreground flex items-center gap-1">
                                <User class="size-3 text-muted-foreground" />
                                {selectedOrder.customer_name || 'Pelanggan Umum'}
                            </div>
                            {#if selectedOrder.customer_phone && selectedOrder.customer_phone !== '-'}
                                <div class="text-muted-foreground text-[11px] flex items-center gap-1 mt-0.5">
                                    <Phone class="size-3 text-muted-foreground" />
                                    {selectedOrder.customer_phone}
                                </div>
                            {/if}
                        </div>

                        <div>
                            <div class="text-muted-foreground text-[11px]">Layanan Kurir:</div>
                            <div class="font-bold text-foreground flex items-center gap-1.5">
                                <Badge variant="outline" class="bg-primary/10 text-primary border-primary/20 uppercase font-semibold">
                                    {selectedOrder.courier_name || 'Gojek / Grab'}
                                </Badge>
                            </div>
                            <div class="text-muted-foreground mt-1 text-[11px]">Kode Booking:</div>
                            <div class="font-mono font-bold text-sm text-emerald-700 dark:text-emerald-400">
                                {selectedOrder.booking_code || '-'}
                            </div>

                            <div class="text-muted-foreground mt-1 text-[11px]">Status Kurir Saat Ini:</div>
                            <div>
                                {#if selectedOrder.delivery_status === 'picked_up'}
                                    <Badge class="bg-emerald-600 text-white text-[10px]">Sudah Diambil Driver</Badge>
                                {:else}
                                    <Badge variant="outline" class="border-amber-400 bg-amber-50 text-amber-800 text-[10px] font-semibold">
                                        Menunggu Pick-up Driver
                                    </Badge>
                                {/if}
                            </div>
                        </div>
                    </div>

                    <!-- Payment Warning / Status -->
                    {#if selectedOrder.payment_status === 'paid'}
                        <div class="flex items-center gap-2 p-2.5 rounded-lg bg-emerald-100/70 dark:bg-emerald-950/60 border border-emerald-300 dark:border-emerald-800 text-xs text-emerald-900 dark:text-emerald-200">
                            <CheckCircle2 class="size-4 text-emerald-600 shrink-0" />
                            <span>
                                <strong>PEMBAYARAN LUNAS ({formatRupiah(selectedOrder.grand_total)}).</strong>
                                Barang aman diserahkan ke driver ojol.
                            </span>
                        </div>
                    {:else}
                        <div class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 dark:bg-amber-950/50 border border-amber-300 dark:border-amber-700 text-xs text-amber-900 dark:text-amber-200">
                            <AlertTriangle class="size-4 text-amber-600 shrink-0 mt-0.5" />
                            <div class="space-y-1">
                                <div class="font-bold">
                                    PERHATIAN: Tagihan Belum Lunas! (Sisa: {formatRupiah(selectedOrder.grand_total - selectedOrder.paid_amount)})
                                </div>
                                <p class="text-[11px]">
                                    Pastikan pelanggan sudah melunasi via transfer atau driver membayarkan tunai sebelum barang diserahkan.
                                </p>
                                <a
                                    href="/orders/{selectedOrder.id}"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 text-[11px] font-bold text-primary underline hover:text-primary/80"
                                >
                                    Buka Halaman Nota & Catat Pelunasan
                                    <ExternalLink class="size-3" />
                                </a>
                            </div>
                        </div>
                    {/if}

                    <!-- Items Checklist to Hand Over -->
                    <div class="space-y-1.5">
                        <div class="font-semibold text-xs flex items-center justify-between">
                            <span>📦 Rincian Barang yang Harus Diserahkan ({selectedOrder.items?.length || 0} item):</span>
                            <span class="text-[10px] text-muted-foreground font-normal">Periksa fisik barang sebelum menyerahkan</span>
                        </div>
                        <div class="max-h-36 overflow-y-auto rounded-lg border bg-background divide-y">
                            {#each selectedOrder.items || [] as item}
                                <div class="p-2.5 text-xs flex items-start justify-between gap-2">
                                    <div class="space-y-0.5">
                                        <div class="font-semibold text-foreground">{item.product_name}</div>
                                        {#if item.length && item.width}
                                            <div class="text-[11px] text-muted-foreground">
                                                Ukuran: {item.length}m × {item.width}m ({item.area} m²) × {item.quantity} {item.unit}
                                            </div>
                                        {:else}
                                            <div class="text-[11px] text-muted-foreground">
                                                Jumlah: {item.quantity} {item.unit}
                                            </div>
                                        {/if}
                                        {#if item.finishing_option}
                                            <div class="text-[10px] text-primary font-medium">
                                                Finishing: {item.finishing_option}
                                            </div>
                                        {/if}
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-xs font-bold text-foreground">{formatRupiah(item.subtotal)}</span>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </div>

                    {#if selectedOrder.delivery_notes}
                        <div class="p-2.5 rounded-lg bg-muted/50 border text-xs">
                            <span class="font-semibold text-foreground">Catatan Pengiriman:</span>
                            <span class="text-muted-foreground ml-1">{selectedOrder.delivery_notes}</span>
                        </div>
                    {/if}

                    <!-- Driver Confirmation Form -->
                    <div class="pt-3 border-t space-y-3">
                        <div class="text-xs font-bold text-foreground flex items-center gap-1.5">
                            <PackageCheck class="size-4 text-emerald-600" />
                            Konfirmasi Serah Terima Barang ke Driver:
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <Label for="driver-name" class="text-xs">Nama Driver</Label>
                                <Input
                                    id="driver-name"
                                    bind:value={driverName}
                                    placeholder="Contoh: Pak Joko (Gojek)"
                                    class="h-8 text-xs"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label for="driver-phone" class="text-xs">No. HP / Plat Nomor (Opsional)</Label>
                                <Input
                                    id="driver-phone"
                                    bind:value={driverPhone}
                                    placeholder="Contoh: 08123... / DK 1234 AB"
                                    class="h-8 text-xs"
                                />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <Label for="driver-notes" class="text-xs">Catatan Tambahan (Opsional)</Label>
                            <Input
                                id="driver-notes"
                                bind:value={pickupNotes}
                                placeholder="Contoh: Barang sudah dipacking rapi dan diperiksa bersama driver"
                                class="h-8 text-xs"
                            />
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <input
                                type="checkbox"
                                id="mark-completed"
                                bind:checked={markAsCompleted}
                                class="rounded border-input text-primary focus:ring-primary size-4"
                            />
                            <Label for="mark-completed" class="text-xs cursor-pointer font-medium">
                                Tandai status pesanan langsung menjadi <strong>"Selesai (Completed)"</strong>
                            </Label>
                        </div>

                        <Button
                            class="w-full h-10 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
                            disabled={isSubmitting}
                            onclick={confirmPickup}
                        >
                            {#if isSubmitting}
                                Memproses Penyerahan...
                            {:else}
                                <CheckCircle2 class="size-4 mr-1.5" />
                                Konfirmasi Barang Diserahkan ke Driver
                            {/if}
                        </Button>
                    </div>
                </div>

            <!-- RESULT 2: Code not found after search -->
            {:else if hasSearched && matchedOrders.length === 0}
                <div class="rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-xs text-destructive space-y-2">
                    <div class="flex items-center gap-2 font-bold text-sm">
                        <AlertCircle class="size-4" />
                        Kode Booking "{searchCode}" Tidak Ditemukan!
                    </div>
                    <p>
                        Tidak ada pesanan aktif dengan kode booking tersebut. Pastikan driver menyebutkan kode booking dengan benar, atau periksa apakah admin sudah menginputkan kode booking pada pesanan.
                    </p>
                </div>

            <!-- RESULT 3: Multiple results found -->
            {:else if hasSearched && matchedOrders.length > 1}
                <div class="space-y-2">
                    <div class="text-xs font-semibold text-muted-foreground">
                        Ditemukan {matchedOrders.length} pesanan yang cocok. Pilih pesanan yang sesuai:
                    </div>
                    <div class="space-y-2 max-h-52 overflow-y-auto">
                        {#each matchedOrders as ord}
                            <button
                                type="button"
                                class="w-full text-left p-3 rounded-lg border bg-card hover:bg-muted/50 transition-colors flex items-center justify-between text-xs cursor-pointer"
                                onclick={() => selectOrder(ord)}
                            >
                                <div>
                                    <div class="font-bold text-foreground">{ord.invoice_number} - {ord.customer_name || 'Pelanggan'}</div>
                                    <div class="text-muted-foreground text-[11px]">
                                        Kurir: {ord.courier_name || 'Gojek/Grab'} • Kode: <span class="font-mono font-semibold text-foreground">{ord.booking_code || '-'}</span>
                                    </div>
                                </div>
                                <Button size="sm" variant="outline" class="h-7 text-xs">Pilih</Button>
                            </button>
                        {/each}
                    </div>
                </div>
            {/if}

            <!-- Waiting Orders List (Shown if no order is selected) -->
            {#if !selectedOrder}
                <div class="pt-2 border-t space-y-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-semibold text-foreground flex items-center gap-1.5">
                            <Clock class="size-3.5 text-amber-500" />
                            Pesanan Menunggu Pick-up Driver ({waitingOrders.length})
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-6 text-[11px] text-muted-foreground hover:text-foreground"
                            onclick={fetchWaitingOrders}
                        >
                            Segarkan
                        </Button>
                    </div>

                    {#if isLoadingWaiting}
                        <div class="text-xs text-muted-foreground text-center py-4">Memuat daftar kurir...</div>
                    {:else if waitingOrders.length === 0}
                        <div class="text-xs text-muted-foreground text-center py-4 bg-muted/20 rounded-lg border">
                            Tidak ada pesanan kurir yang sedang menunggu pick-up driver.
                        </div>
                    {:else}
                        <div class="max-h-48 overflow-y-auto divide-y rounded-lg border bg-card">
                            {#each waitingOrders as ord}
                                <div class="p-2.5 text-xs flex items-center justify-between gap-2 hover:bg-muted/30">
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-foreground">{ord.invoice_number}</span>
                                            {#if ord.booking_code}
                                                <Badge class="bg-primary/10 text-primary border-primary/20 text-[10px] font-mono font-semibold">
                                                    {ord.booking_code}
                                                </Badge>
                                            {:else}
                                                <Badge variant="outline" class="text-[10px] text-amber-700 bg-amber-50 border-amber-300">
                                                    Belum Ada Kode
                                                </Badge>
                                            {/if}
                                            <span class="text-[10px] uppercase font-semibold text-muted-foreground">({ord.courier_name || 'Ojol'})</span>
                                        </div>
                                        <div class="text-muted-foreground text-[11px]">
                                            {ord.customer_name || 'Pelanggan'} • {ord.items?.length || 0} item • {formatRupiah(ord.grand_total)}
                                            {#if ord.payment_status === 'paid'}
                                                <span class="text-emerald-600 font-semibold ml-1">(Lunas)</span>
                                            {:else}
                                                <span class="text-amber-700 font-semibold ml-1">(Belum Lunas)</span>
                                            {/if}
                                        </div>
                                    </div>
                                    <Button
                                        size="sm"
                                        class="h-7 px-2.5 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shrink-0"
                                        onclick={() => selectOrder(ord)}
                                    >
                                        Cocokkan
                                    </Button>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>
            {/if}
        </div>

        <DialogFooter class="px-6 py-3 border-t bg-muted/20">
            <Button variant="outline" class="text-xs" onclick={() => (open = false)}>
                Tutup
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

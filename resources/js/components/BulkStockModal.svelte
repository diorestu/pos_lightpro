<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ArrowDownRight from '@lucide/svelte/icons/arrow-down-right';
    import ArrowUpRight from '@lucide/svelte/icons/arrow-up-right';
    import Boxes from '@lucide/svelte/icons/boxes';
    import Equal from '@lucide/svelte/icons/equal';
    import LoaderCircle from '@lucide/svelte/icons/loader-circle';
    import PackagePlus from '@lucide/svelte/icons/package-plus';
    import Plus from '@lucide/svelte/icons/plus';
    import RotateCcw from '@lucide/svelte/icons/rotate-ccw';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import { toast } from 'svelte-sonner';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    export interface ProductOption {
        id: number;
        name: string;
        code: string;
        stock: number | string;
        unit: string;
        pricing_type?: string;
        is_stock_managed?: boolean;
    }

    interface BulkStockRow {
        rowId: string;
        product_id: number | '';
        type: 'in' | 'out' | 'adjustment';
        quantity: string;
        reason: string;
    }

    let {
        open = $bindable(false),
        products = [],
        endpoint = '/products/bulk-stock',
    }: {
        open?: boolean;
        products?: ProductOption[];
        endpoint?: string;
    } = $props();

    let rows = $state<BulkStockRow[]>([]);
    let defaultType = $state<'in' | 'out' | 'adjustment'>('in');
    let defaultReason = $state('Restock Pembelian Supplier');
    let isSubmitting = $state(false);

    // Buka modal dengan minimal 1 baris jika kosong
    $effect(() => {
        if (open && rows.length === 0 && products.length > 0) {
            addRow();
        }
    });

    function createRow(productId: number | '' = '', type: 'in' | 'out' | 'adjustment' = defaultType): BulkStockRow {
        return {
            rowId: Math.random().toString(36).substring(2, 9),
            product_id: productId,
            type,
            quantity: '',
            reason: '',
        };
    }

    function addRow() {
        // Cari produk pertama yang belum dipilih
        const selectedIds = new Set(rows.map((r) => r.product_id).filter(Boolean));
        const available = products.find((p) => !selectedIds.has(p.id));
        rows.push(createRow(available ? available.id : ''));
    }

    function removeRow(index: number) {
        rows.splice(index, 1);
    }

    function clearAllRows() {
        rows = [];
    }

    function addAllProducts() {
        const selectedIds = new Set(rows.map((r) => r.product_id).filter(Boolean));
        products.forEach((p) => {
            if (!selectedIds.has(p.id)) {
                rows.push(createRow(p.id));
            }
        });
        toast.info(`${products.length} produk siap disesuaikan.`);
    }

    function addLowStockProducts() {
        const selectedIds = new Set(rows.map((r) => r.product_id).filter(Boolean));
        let addedCount = 0;
        products.forEach((p) => {
            const current = parseFloat(p.stock.toString()) || 0;
            if (current <= 10 && !selectedIds.has(p.id)) {
                rows.push(createRow(p.id));
                addedCount++;
            }
        });
        if (addedCount > 0) {
            toast.info(`${addedCount} produk dengan stok menipis (<= 10) ditambahkan.`);
        } else {
            toast.info('Tidak ada produk dengan stok menipis (<= 10).');
        }
    }

    function applyDefaultToAll() {
        rows.forEach((r) => {
            r.type = defaultType;
            if (!r.reason && defaultReason) {
                r.reason = defaultReason;
            }
        });
        toast.success(`Tipe ${defaultType.toUpperCase()} diterapkan ke semua baris.`);
    }

    function getProduct(productId: number | ''): ProductOption | undefined {
        return products.find((p) => p.id === productId);
    }

    // Perhitungan baris
    function calculateStockDelta(row: BulkStockRow): {
        oldStock: number;
        newStock: number;
        deltaText: string;
        isNegative: boolean;
    } {
        const prod = getProduct(row.product_id);
        const oldStock = prod ? parseFloat(prod.stock.toString()) || 0 : 0;
        const qty = parseFloat(row.quantity) || 0;

        let newStock = oldStock;
        let deltaText = `${oldStock}`;
        let isNegative = false;

        if (row.type === 'in') {
            newStock = oldStock + qty;
            deltaText = `+${qty}`;
        } else if (row.type === 'out') {
            newStock = Math.max(0, oldStock - qty);
            deltaText = `-${qty}`;
            if (qty > oldStock) isNegative = true;
        } else {
            // Opname
            newStock = qty;
            const diff = qty - oldStock;
            deltaText = diff >= 0 ? `+${diff}` : `${diff}`;
        }

        return { oldStock, newStock, deltaText, isNegative };
    }

    // Ringkasan
    const summary = $derived.by(() => {
        let totalItems = 0;
        let totalQtyIn = 0;
        let totalQtyOut = 0;

        rows.forEach((r) => {
            const qty = parseFloat(r.quantity) || 0;
            if (r.product_id && qty > 0) {
                totalItems++;
                if (r.type === 'in') totalQtyIn += qty;
                else if (r.type === 'out') totalQtyOut += qty;
            }
        });

        return { totalItems, totalQtyIn, totalQtyOut };
    });

    function handleSubmit() {
        const validRows = rows.filter((r) => r.product_id && parseFloat(r.quantity) > 0);

        if (validRows.length === 0) {
            toast.error('Masukkan minimal 1 produk dengan jumlah mutasi lebih dari 0.');
            return;
        }

        // Cek duplikat produk
        const prodIds = validRows.map((r) => r.product_id);
        const hasDuplicate = new Set(prodIds).size !== prodIds.length;
        if (hasDuplicate) {
            toast.error('Terdapat produk yang dipilih lebih dari satu kali. Mohon gabungkan baris tersebut.');
            return;
        }

        isSubmitting = true;
        router.post(
            endpoint,
            {
                items: validRows.map((r) => ({
                    product_id: r.product_id,
                    type: r.type,
                    quantity: parseFloat(r.quantity),
                    reason: r.reason || defaultReason,
                })),
                default_reason: defaultReason,
            },
            {
                onSuccess: () => {
                    open = false;
                    rows = [];
                },
                onFinish: () => {
                    isSubmitting = false;
                },
            }
        );
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-5xl lg:max-w-6xl w-[96vw] max-h-[92vh] flex flex-col p-0 overflow-hidden shadow-2xl">
        <!-- Header Dialog -->
        <DialogHeader class="px-6 sm:px-8 py-5 pb-4 border-b bg-muted/20">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-lg bg-primary/10 text-primary">
                        <Boxes class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-lg font-bold flex items-center gap-2">
                            Kelola Stok Multi Produk
                            <Badge variant="outline" class="text-[10px] bg-primary/5 text-primary border-primary/20">
                                Batch Mode
                            </Badge>
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Input stok masuk (restock), pengurangan pemakaian/rusak, atau opname fisik untuk banyak produk sekaligus.
                        </DialogDescription>
                    </div>
                </div>
            </div>
        </DialogHeader>

        <!-- Batch Controls & Presets Bar -->
        <div class="px-6 sm:px-8 py-3.5 bg-muted/40 border-b flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-1.5">
                    <Label class="text-xs text-muted-foreground whitespace-nowrap font-medium">Tipe Default:</Label>
                    <select
                        bind:value={defaultType}
                        class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-7 font-semibold cursor-pointer"
                    >
                        <option value="in">Stok Masuk (+) / Restock</option>
                        <option value="out">Stok Keluar (-) / Rusak</option>
                        <option value="adjustment">Stock Opname (=) / Fisik</option>
                    </select>
                </div>

                <div class="flex items-center gap-1.5">
                    <Label class="text-xs text-muted-foreground whitespace-nowrap font-medium">Alasan Default:</Label>
                    <Input
                        bind:value={defaultReason}
                        placeholder="Contoh: Restock Supplier, Opname Bulanan..."
                        class="h-8 text-xs w-44 sm:w-60 bg-background"
                    />
                </div>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs font-medium"
                    onclick={applyDefaultToAll}
                    disabled={rows.length === 0}
                >
                    <RotateCcw class="size-3.5 mr-1 text-muted-foreground" />
                    Terapkan ke Semua
                </Button>
            </div>

            <div class="flex items-center gap-1.5">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs font-semibold text-primary border-primary/30 hover:bg-primary/5"
                    onclick={addRow}
                >
                    <Plus class="size-3.5 mr-1" />
                    Tambah Baris
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs font-medium"
                    onclick={addAllProducts}
                    title="Tambahkan seluruh katalog produk ke tabel"
                >
                    <PackagePlus class="size-3.5 mr-1 text-muted-foreground" />
                    Semua Produk ({products.length})
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs font-medium text-amber-700 dark:text-amber-400 border-amber-300 dark:border-amber-800"
                    onclick={addLowStockProducts}
                    title="Tambahkan produk yang stoknya <= 10"
                >
                    Stok Menipis (≤10)
                </Button>

                {#if rows.length > 0}
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-8 text-xs text-destructive hover:bg-destructive/10"
                        onclick={clearAllRows}
                    >
                        Kosongkan
                    </Button>
                {/if}
            </div>
        </div>

        <!-- Table Body (Scrollable Container) -->
        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-5 max-h-[52vh]">
            {#if rows.length === 0}
                <div class="py-12 flex flex-col items-center justify-center text-center text-muted-foreground border-2 border-dashed rounded-lg bg-muted/10">
                    <Boxes class="size-12 opacity-30 mb-2" />
                    <p class="font-semibold text-foreground text-sm">Belum ada produk yang dipilih</p>
                    <p class="text-xs max-w-sm mt-1 mb-4">
                        Tambahkan produk yang ingin Anda sesuaikan stoknya baik untuk restock barang, pencatatan pemakaian, atau opname fisik.
                    </p>
                    <div class="flex items-center gap-2">
                        <Button size="sm" class="bg-primary text-xs font-semibold" onclick={addRow}>
                            <Plus class="size-3.5 mr-1" />
                            Tambah Baris Pertama
                        </Button>
                        <Button size="sm" variant="outline" class="text-xs" onclick={addAllProducts}>
                            <PackagePlus class="size-3.5 mr-1" />
                            Masukkan Semua Produk ({products.length})
                        </Button>
                    </div>
                </div>
            {:else}
                <div class="rounded-lg border overflow-hidden">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead class="bg-muted/70 text-muted-foreground uppercase text-[10px] tracking-wider font-semibold border-b">
                            <tr>
                                <th class="py-2.5 px-3 text-center w-10">#</th>
                                <th class="py-2.5 px-3 min-w-[240px]">Pilih Produk Percetakan</th>
                                <th class="py-2.5 px-3 w-36">Tipe Aksi</th>
                                <th class="py-2.5 px-3 w-28 text-center">Stok Saat Ini</th>
                                <th class="py-2.5 px-3 w-32 text-right">Jumlah (Qty)</th>
                                <th class="py-2.5 px-3 w-40 text-center">Estimasi Stok Baru</th>
                                <th class="py-2.5 px-3 min-w-[180px]">Alasan / Keterangan</th>
                                <th class="py-2.5 px-2 text-center w-12">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            {#each rows as row, idx (row.rowId)}
                                {@const prod = getProduct(row.product_id)}
                                {@const calc = calculateStockDelta(row)}
                                <tr class="hover:bg-muted/20 transition-colors">
                                    <td class="py-2 px-3 text-center font-medium text-muted-foreground">
                                        {idx + 1}
                                    </td>

                                    <!-- Produk Selector -->
                                    <td class="py-2 px-3">
                                        <select
                                            bind:value={row.product_id}
                                            class="w-full h-8 text-xs rounded border border-input bg-background px-2 pr-7 font-medium cursor-pointer"
                                        >
                                            <option value="">-- Pilih Produk --</option>
                                            {#each products as p}
                                                {@const isTaken = rows.some((r) => r.product_id === p.id && r.rowId !== row.rowId)}
                                                <option value={p.id} disabled={isTaken}>
                                                    [{p.code}] {p.name} {isTaken ? '(Sudah dipilih)' : `(Stok: ${p.stock} ${p.unit})`}
                                                </option>
                                            {/each}
                                        </select>
                                    </td>

                                    <!-- Tipe Aksi -->
                                    <td class="py-2 px-3">
                                        <select
                                            bind:value={row.type}
                                            class="w-full h-8 text-xs rounded border border-input bg-background px-2 pr-6 font-semibold cursor-pointer {row.type === 'in' ? 'text-emerald-700 dark:text-emerald-400' : row.type === 'out' ? 'text-destructive' : 'text-blue-600 dark:text-blue-400'}"
                                        >
                                            <option value="in">+ Stok Masuk</option>
                                            <option value="out">- Stok Keluar</option>
                                            <option value="adjustment">= Opname Fisik</option>
                                        </select>
                                    </td>

                                    <!-- Stok Saat Ini -->
                                    <td class="py-2 px-3 text-center">
                                        {#if prod}
                                            <span class="inline-block px-2 py-0.5 rounded font-mono font-semibold text-[11px] bg-muted/60 text-foreground">
                                                {prod.stock} {prod.unit}
                                            </span>
                                        {:else}
                                            <span class="text-muted-foreground">-</span>
                                        {/if}
                                    </td>

                                    <!-- Jumlah Input -->
                                    <td class="py-2 px-3 text-right">
                                        <div class="relative">
                                            <Input
                                                type="number"
                                                step="any"
                                                min="0.01"
                                                placeholder="0"
                                                bind:value={row.quantity}
                                                class="h-8 text-xs text-right font-mono font-bold pr-7"
                                            />
                                            {#if prod}
                                                <span class="absolute right-2 top-1.5 text-[10px] text-muted-foreground pointer-events-none">
                                                    {prod.unit}
                                                </span>
                                            {/if}
                                        </div>
                                    </td>

                                    <!-- Estimasi Stok Baru -->
                                    <td class="py-2 px-3 text-center font-mono">
                                        {#if prod && parseFloat(row.quantity) > 0}
                                            <div class="flex items-center justify-center gap-1">
                                                <span class="text-muted-foreground line-through text-[10px]">
                                                    {calc.oldStock}
                                                </span>
                                                <span class="text-muted-foreground">→</span>
                                                <span class="font-bold text-xs {row.type === 'in' ? 'text-emerald-600 dark:text-emerald-400' : row.type === 'out' ? 'text-destructive' : 'text-blue-600 dark:text-blue-400'}">
                                                    {calc.newStock} {prod.unit}
                                                </span>
                                                <span class="text-[9px] px-1 rounded {row.type === 'in' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : row.type === 'out' ? 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300'}">
                                                    {calc.deltaText}
                                                </span>
                                            </div>
                                            {#if calc.isNegative}
                                                <div class="text-[9px] text-destructive mt-0.5 font-sans font-medium">
                                                    Pengurangan melebihi stok!
                                                </div>
                                            {/if}
                                        {:else if prod}
                                            <span class="text-muted-foreground text-[11px]">{prod.stock} {prod.unit}</span>
                                        {:else}
                                            <span class="text-muted-foreground text-[11px]">-</span>
                                        {/if}
                                    </td>

                                    <!-- Alasan / Catatan -->
                                    <td class="py-2 px-3">
                                        <Input
                                            type="text"
                                            placeholder={defaultReason || 'Keterangan khusus baris ini...'}
                                            bind:value={row.reason}
                                            class="h-8 text-xs"
                                        />
                                    </td>

                                    <!-- Delete Row Action -->
                                    <td class="py-2 px-2 text-center">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="size-7 p-0 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                                            onclick={() => removeRow(idx)}
                                            title="Hapus baris ini"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>

                <!-- Bottom quick row adder button -->
                <div class="mt-3 flex items-center justify-between">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-xs font-semibold text-primary border-dashed border-primary/40 hover:bg-primary/5"
                        onclick={addRow}
                    >
                        <Plus class="size-3.5 mr-1" />
                        Tambah Baris Produk Lainnya
                    </Button>
                    <span class="text-[11px] text-muted-foreground">
                        Total {rows.length} baris ({summary.totalItems} siap disimpan)
                    </span>
                </div>
            {/if}
        </div>

        <!-- Footer Dialog: Summary & Action Buttons -->
        <DialogFooter class="px-6 sm:px-8 py-4 border-t bg-muted/20 flex-col sm:flex-row items-center justify-between gap-4">
            <!-- Summary Badges (Kiri) -->
            <div class="flex flex-wrap items-center gap-2.5 text-xs w-full sm:w-auto">
                <span class="text-muted-foreground font-medium">Ringkasan:</span>
                <Badge variant="outline" class="font-semibold bg-background">
                    {summary.totalItems} Produk Siap
                </Badge>
                {#if summary.totalQtyIn > 0}
                    <Badge variant="outline" class="bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800 font-semibold flex items-center gap-1">
                        <ArrowUpRight class="size-3" />
                        +{summary.totalQtyIn.toLocaleString('id-ID')} Masuk
                    </Badge>
                {/if}
                {#if summary.totalQtyOut > 0}
                    <Badge variant="outline" class="bg-red-50 text-destructive border-red-300 dark:bg-red-950/40 dark:text-red-300 dark:border-red-800 font-semibold flex items-center gap-1">
                        <ArrowDownRight class="size-3" />
                        -{summary.totalQtyOut.toLocaleString('id-ID')} Keluar
                    </Badge>
                {/if}
            </div>

            <!-- Action Buttons (Kanan) -->
            <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end">
                <Button
                    type="button"
                    variant="outline"
                    class="font-medium text-xs h-9 px-4"
                    onclick={() => (open = false)}
                    disabled={isSubmitting}
                >
                    Batal
                </Button>
                <Button
                    type="button"
                    class="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold text-xs h-9 px-5 shadow-xs min-w-[180px]"
                    onclick={handleSubmit}
                    disabled={isSubmitting || summary.totalItems === 0}
                >
                    {#if isSubmitting}
                        <LoaderCircle class="size-4 mr-2 animate-spin" />
                        Menyimpan Stok...
                    {:else}
                        <Boxes class="size-4 mr-1.5" />
                        Simpan Penyesuaian ({summary.totalItems})
                    {/if}
                </Button>
            </div>
        </DialogFooter>
    </DialogContent>
</Dialog>

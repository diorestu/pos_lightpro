<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Laporan',
                href: '#',
            },
            {
                title: 'Laporan Stok (In/Out)',
                href: '/stock-reports',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ArrowDownRight from '@lucide/svelte/icons/arrow-down-right';
    import ArrowUpDown from '@lucide/svelte/icons/arrow-up-down';
    import ArrowUpRight from '@lucide/svelte/icons/arrow-up-right';
    import Boxes from '@lucide/svelte/icons/boxes';
    import Filter from '@lucide/svelte/icons/filter';
    import Plus from '@lucide/svelte/icons/plus';
    import Search from '@lucide/svelte/icons/search';
    import Sliders from '@lucide/svelte/icons/sliders';
    import AppHead from '@/components/AppHead.svelte';
    import BulkStockModal from '@/components/BulkStockModal.svelte';
    import { formatStock } from '@/lib/utils';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { DataTable, type ColumnDef, TableCell, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';

    interface MutationItem {
        id: number;
        item_type: 'raw_material' | 'product';
        item_id: number;
        item_name: string;
        type: 'in' | 'out' | 'adjustment';
        quantity: number;
        previous_stock: number;
        current_stock: number;
        reference_type: string | null;
        reason: string;
        created_at: string;
        user?: { name: string };
    }

    let {
        mutations = { data: [], links: [] },
        summary = { total_in: 0, total_out: 0 },
        rawMaterials = [],
        products = [],
        filters = {},
    }: {
        mutations: any;
        summary: { total_in: number; total_out: number };
        rawMaterials: any[];
        products: any[];
        filters: Record<string, string>;
    } = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let itemType = $state(filters.item_type || '');
    // svelte-ignore state_referenced_locally
    let mutationType = $state(filters.type || '');
    // svelte-ignore state_referenced_locally
    let dateFrom = $state(filters.date_from || '');
    // svelte-ignore state_referenced_locally
    let dateTo = $state(filters.date_to || '');
    // svelte-ignore state_referenced_locally
    let currentPerPage = $state(filters.per_page ? parseInt(filters.per_page, 10) : 15);

    $effect(() => {
        search = filters.search || '';
        itemType = filters.item_type || '';
        mutationType = filters.type || '';
        dateFrom = filters.date_from || '';
        dateTo = filters.date_to || '';
        currentPerPage = filters.per_page ? parseInt(filters.per_page, 10) : 15;
    });

    const columns: ColumnDef[] = [
        { key: 'created_at', label: 'Tanggal & Waktu', sortable: true },
        { key: 'item_name', label: 'Nama Barang / Material', sortable: true },
        { key: 'item_type', label: 'Jenis Barang', sortable: true },
        { key: 'type', label: 'Tipe Mutasi', sortable: true },
        { key: 'quantity', label: 'Jumlah Mutasi', align: 'right', sortable: true },
        { key: 'current_stock', label: 'Perubahan Stok', align: 'center', sortable: true },
        { key: 'reason', label: 'Keterangan / Alasan', sortable: false },
        { key: 'user_id', label: 'Petugas', sortable: false },
    ];

    // Modal state
    let showAdjustModal = $state(false);
    let showBulkStockModal = $state(false);
    let adjustItemType = $state<'raw_material' | 'product'>('raw_material');
    let adjustItemId = $state<number | string>('');
    let adjustType = $state<'in' | 'out' | 'adjustment'>('in');
    let adjustQty = $state('');
    let adjustReason = $state('');

    function applyFilter(newSearch?: string) {
        if (newSearch !== undefined) {
            search = newSearch;
        }
        router.get(
            '/stock-reports',
            {
                search: search || undefined,
                item_type: itemType || undefined,
                type: mutationType || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                per_page: currentPerPage,
            },
            { preserveState: true }
        );
    }

    function openAdjust() {
        adjustItemType = 'raw_material';
        adjustItemId = rawMaterials.length > 0 ? rawMaterials[0].id : '';
        adjustType = 'in';
        adjustQty = '';
        adjustReason = '';
        showAdjustModal = true;
    }

    function submitAdjust() {
        router.post(
            '/stock-reports/adjust',
            {
                item_type: adjustItemType,
                item_id: adjustItemId,
                type: adjustType,
                quantity: parseFloat(adjustQty) || 0,
                reason: adjustReason,
            },
            {
                onSuccess: () => (showAdjustModal = false),
            }
        );
    }
</script>

<AppHead title="Laporan Mutasi Stok (Tambah / Kurang)" />

<div class="flex flex-col gap-6 p-4 md:p-6 max-w-7xl mx-auto w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <ArrowUpDown class="size-6 text-primary" />
                Laporan Mutasi Stok (Tambah / Kurang)
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Pencatatan riwayat stok masuk (restock), pengurangan (penjualan kasir & cacat cetak), dan penyesuaian stock opname manual. Klik judul kolom untuk mengurutkan (sortable).
            </p>
        </div>

        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                class="font-semibold whitespace-nowrap inline-flex items-center border-primary/30 text-primary hover:bg-primary/10 shadow-2xs"
                onclick={() => (showBulkStockModal = true)}
            >
                <Boxes class="size-4 mr-1.5" />
                Kelola Stok Multi Produk
            </Button>
            <Button class="bg-primary font-semibold whitespace-nowrap inline-flex items-center" onclick={openAdjust}>
                <Sliders class="size-4 mr-1.5" />
                Penyesuaian Satuan
            </Button>
        </div>
    </div>

    <!-- KPI Summary Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Card class="border shadow-2xs bg-emerald-50/30 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <CardTitle class="text-xs font-semibold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">
                    Total Stok Masuk (Penambahan)
                </CardTitle>
                <div class="p-2 bg-emerald-100 dark:bg-emerald-950 text-emerald-600 rounded-lg">
                    <ArrowUpRight class="size-4" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                    +{formatStock(summary.total_in)} Unit
                </div>
                <p class="text-xs text-muted-foreground mt-1">Dari pembelian supplier & stock in</p>
            </CardContent>
        </Card>

        <Card class="border shadow-2xs bg-red-50/30 dark:bg-red-950/20 border-red-200 dark:border-red-900">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <CardTitle class="text-xs font-semibold uppercase tracking-wider text-red-800 dark:text-red-300">
                    Total Stok Keluar (Pemakaian & Rusak)
                </CardTitle>
                <div class="p-2 bg-red-100 dark:bg-red-950 text-red-600 rounded-lg">
                    <ArrowDownRight class="size-4" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-destructive">
                    -{formatStock(summary.total_out)} Unit
                </div>
                <p class="text-xs text-muted-foreground mt-1">Dari cetak pesanan POS & sisa bahan</p>
            </CardContent>
        </Card>
    </div>

    <!-- Reusable DataTable with Search, Column Filter, Records Per Page & Pagination in Head Table Card -->
    <DataTable
        {columns}
        data={mutations}
        pagination={mutations}
        search={search}
        searchPlaceholder="Cari nama barang atau alasan mutasi..."
        onSearch={(val) => applyFilter(val)}
        perPage={currentPerPage}
        onPerPageChange={(val) => {
            currentPerPage = val;
            applyFilter();
        }}
        onPageChange={(_, url) => {
            if (url) router.get(url, {}, { preserveState: true });
        }}
        defaultSortKey="created_at"
        defaultSortDir="desc"
        emptyMessage="Tidak ada log mutasi stok sesuai filter yang dipilih."
    >
        {#snippet filtersSnippet()}
            <select
                bind:value={itemType}
                onchange={() => applyFilter()}
                class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-8 w-full sm:w-44 cursor-pointer font-medium"
            >
                <option value="">Semua Jenis Item</option>
                <option value="raw_material">Bahan Baku Percetakan</option>
                <option value="product">Produk Siap Jual</option>
            </select>

            <select
                bind:value={mutationType}
                onchange={() => applyFilter()}
                class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-8 w-full sm:w-44 cursor-pointer font-medium"
            >
                <option value="">Semua Tipe Mutasi</option>
                <option value="in">Stok Masuk (+)</option>
                <option value="out">Stok Keluar (-)</option>
                <option value="adjustment">Stock Opname (=)</option>
            </select>
        {/snippet}

        {#snippet rowSnippet({ item: mut, isVisible })}
            <TableRow class="hover:bg-muted/30 transition-colors">
                {#if isVisible('created_at')}
                    <TableCell class="text-xs text-muted-foreground whitespace-nowrap">
                        {new Date(mut.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                    </TableCell>
                {/if}
                {#if isVisible('item_name')}
                    <TableCell class="text-xs font-semibold text-foreground">
                        {mut.item_name}
                    </TableCell>
                {/if}
                {#if isVisible('item_type')}
                    <TableCell class="text-xs">
                        {#if mut.item_type === 'raw_material'}
                            <Badge variant="outline" class="text-[10px]">Bahan Baku</Badge>
                        {:else}
                            <Badge variant="secondary" class="text-[10px]">Produk</Badge>
                        {/if}
                    </TableCell>
                {/if}
                {#if isVisible('type')}
                    <TableCell class="text-xs">
                        {#if mut.type === 'in'}
                            <Badge class="bg-emerald-600 hover:bg-emerald-600 text-white text-[10px] gap-1">
                                <ArrowUpRight class="size-3" />
                                Masuk
                            </Badge>
                        {:else if mut.type === 'out'}
                            <Badge variant="destructive" class="text-[10px] gap-1">
                                <ArrowDownRight class="size-3" />
                                Keluar
                            </Badge>
                        {:else}
                            <Badge class="bg-blue-600 hover:bg-blue-600 text-white text-[10px] gap-1">
                                <Sliders class="size-3" />
                                Opname
                            </Badge>
                        {/if}
                    </TableCell>
                {/if}
                {#if isVisible('quantity')}
                    <TableCell class="text-xs text-right font-bold {mut.type === 'in' ? 'text-emerald-600' : mut.type === 'out' ? 'text-destructive' : 'text-blue-600'}">
                        {mut.type === 'in' ? '+' : mut.type === 'out' ? '-' : ''}{formatStock(mut.quantity)}
                    </TableCell>
                {/if}
                {#if isVisible('current_stock')}
                    <TableCell class="text-xs text-center">
                        <span class="text-muted-foreground">{formatStock(mut.previous_stock)}</span>
                        <span class="text-muted-foreground/60 mx-1">&rarr;</span>
                        <span class="font-bold text-foreground">{formatStock(mut.current_stock)}</span>
                    </TableCell>
                {/if}
                {#if isVisible('reason')}
                    <TableCell class="text-xs text-muted-foreground">
                        {mut.reason}
                    </TableCell>
                {/if}
                {#if isVisible('user_id')}
                    <TableCell class="text-xs font-medium">
                        {mut.user?.name || '-'}
                    </TableCell>
                {/if}
            </TableRow>
        {/snippet}
    </DataTable>
</div>

<!-- Modal: Penyesuaian Stok Manual -->
<Dialog open={showAdjustModal} onOpenChange={(open) => (showAdjustModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Sliders class="size-5 text-primary" />
                Penyesuaian Stok (Tambah / Kurang / Opname)
            </DialogTitle>
            <DialogDescription class="text-xs">
                Catat penerimaan barang baru, barang rusak (waste percetakan), atau hasil audit fisik gudang.
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <Label for="adj_type">Jenis Item *</Label>
                    <select
                        id="adj_type"
                        bind:value={adjustItemType}
                        onchange={() => {
                            if (adjustItemType === 'raw_material') {
                                adjustItemId = rawMaterials.length > 0 ? rawMaterials[0].id : '';
                            } else {
                                adjustItemId = products.length > 0 ? products[0].id : '';
                            }
                        }}
                        class="w-full h-9 rounded-md border border-input bg-background px-3 pr-8"
                    >
                        <option value="raw_material">Bahan Baku Percetakan</option>
                        <option value="product">Produk Jadi</option>
                    </select>
                </div>

                <div class="space-y-1">
                    <Label for="adj_mut">Tindakan Mutasi *</Label>
                    <select id="adj_mut" bind:value={adjustType} class="w-full h-9 rounded-md border border-input bg-background px-3 pr-8">
                        <option value="in">Tambah Stok (+) [Restock]</option>
                        <option value="out">Kurang Stok (-) [Rusak/Reject]</option>
                        <option value="adjustment">Stock Opname (=) [Set Aktual]</option>
                    </select>
                </div>
            </div>

            <div class="space-y-1">
                <Label for="adj_item">Pilih Barang / Material *</Label>
                <select id="adj_item" bind:value={adjustItemId} class="w-full h-9 rounded-md border border-input bg-background px-3 pr-8">
                    {#if adjustItemType === 'raw_material'}
                        {#each rawMaterials as raw}
                            <option value={raw.id}>{raw.name} (Stok Saat Ini: {formatStock(raw.stock)} {raw.unit})</option>
                        {/each}
                    {:else}
                        {#each products as prd}
                            <option value={prd.id}>{prd.name} (Stok Saat Ini: {formatStock(prd.stock)} {prd.unit})</option>
                        {/each}
                    {/if}
                </select>
            </div>

            <div class="space-y-1">
                <Label for="adj_qty">
                    {adjustType === 'adjustment' ? 'Jumlah Stok Fisik Aktual Saat Ini *' : 'Jumlah Unit Mutasi *'}
                </Label>
                <Input
                    id="adj_qty"
                    type="number"
                    step="0.01"
                    bind:value={adjustQty}
                    placeholder="Contoh: 50"
                    class="font-bold"
                />
            </div>

            <div class="space-y-1">
                <Label for="adj_reason">Alasan Penyesuaian *</Label>
                <Textarea
                    id="adj_reason"
                    bind:value={adjustReason}
                    placeholder="Contoh: Pembelian roll dari supplier / Roll flexi robek / Stock opname bulanan..."
                    rows={2}
                />
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (showAdjustModal = false)}>Batal</Button>
            <Button class="bg-primary" onclick={submitAdjust} disabled={!adjustQty || !adjustReason}>
                Simpan Penyesuaian
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Extra Width Popup: Kelola Stok Multi Produk -->
<BulkStockModal
    bind:open={showBulkStockModal}
    products={products || []}
    endpoint="/stock-reports/bulk-adjust"
/>

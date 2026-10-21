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
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';
    import { createSortable } from '@/lib/sortable.svelte';

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
        mutations: { data: MutationItem[]; links: any[] };
        summary: { total_in: number; total_out: number };
        rawMaterials: any[];
        products: any[];
        filters: Record<string, string>;
    } = $props();

    let search = $state(filters.search || '');
    let itemType = $state(filters.item_type || '');
    let mutationType = $state(filters.type || '');
    let dateFrom = $state(filters.date_from || '');
    let dateTo = $state(filters.date_to || '');

    // Sorter
    const sorter = createSortable(() => mutations.data, 'created_at', 'desc');

    // Modal state
    let showAdjustModal = $state(false);
    let adjustItemType = $state<'raw_material' | 'product'>('raw_material');
    let adjustItemId = $state<number | string>('');
    let adjustType = $state<'in' | 'out' | 'adjustment'>('in');
    let adjustQty = $state('');
    let adjustReason = $state('');

    function applyFilter() {
        router.get(
            '/stock-reports',
            {
                search: search || undefined,
                item_type: itemType || undefined,
                type: mutationType || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
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

        <Button class="bg-primary font-semibold" onclick={openAdjust}>
            <Sliders class="size-4 mr-1.5" />
            + Penyesuaian Stok Manual
        </Button>
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
                    +{summary.total_in.toLocaleString('id-ID')} Unit
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
                    -{summary.total_out.toLocaleString('id-ID')} Unit
                </div>
                <p class="text-xs text-muted-foreground mt-1">Dari cetak pesanan POS & sisa bahan</p>
            </CardContent>
        </Card>
    </div>

    <!-- Filters Bar -->
    <Card class="border shadow-2xs">
        <CardContent class="p-4 flex flex-col md:flex-row items-center gap-3">
            <div class="relative flex-1 w-full">
                <Search class="size-3.5 absolute left-3 top-2.5 text-muted-foreground" />
                <Input
                    bind:value={search}
                    onchange={applyFilter}
                    placeholder="Cari nama barang atau alasan mutasi..."
                    class="h-8 pl-8 text-xs"
                />
            </div>

            <select
                bind:value={itemType}
                onchange={applyFilter}
                class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-8 w-full md:w-44"
            >
                <option value="">Semua Jenis Item</option>
                <option value="raw_material">Bahan Baku Percetakan</option>
                <option value="product">Produk Siap Jual</option>
            </select>

            <select
                bind:value={mutationType}
                onchange={applyFilter}
                class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-8 w-full md:w-44"
            >
                <option value="">Semua Tipe Mutasi</option>
                <option value="in">Stok Masuk (+)</option>
                <option value="out">Stok Keluar (-)</option>
                <option value="adjustment">Stock Opname (=)</option>
            </select>
        </CardContent>
    </Card>

    <!-- Mutation Log Table with Sortable Headers -->
    <Card class="border shadow-xs">
        <CardContent class="p-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'created_at' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('created_at')}
                        >
                            Tanggal & Waktu
                        </TableHead>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'item_name' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('item_name')}
                        >
                            Nama Barang / Material
                        </TableHead>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'item_type' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('item_type')}
                        >
                            Jenis Barang
                        </TableHead>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'type' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('type')}
                        >
                            Tipe Mutasi
                        </TableHead>
                        <TableHead
                            class="text-xs text-right"
                            sortable
                            sortDirection={sorter.sortKey === 'quantity' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('quantity')}
                        >
                            Jumlah Mutasi
                        </TableHead>
                        <TableHead
                            class="text-xs text-center"
                            sortable
                            sortDirection={sorter.sortKey === 'current_stock' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('current_stock')}
                        >
                            Perubahan Stok
                        </TableHead>
                        <TableHead class="text-xs">Keterangan / Alasan</TableHead>
                        <TableHead class="text-xs">Petugas</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {#if sorter.sortedData.length === 0}
                        <TableRow>
                            <TableCell colspan={8} class="text-center py-10 text-muted-foreground text-xs">
                                Tidak ada log mutasi stok sesuai filter yang dipilih.
                            </TableCell>
                        </TableRow>
                    {:else}
                        {#each sorter.sortedData as mut (mut.id)}
                            <TableRow>
                                <TableCell class="text-xs text-muted-foreground">
                                    {new Date(mut.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                </TableCell>
                                <TableCell class="text-xs font-semibold text-foreground">
                                    {mut.item_name}
                                </TableCell>
                                <TableCell class="text-xs">
                                    {#if mut.item_type === 'raw_material'}
                                        <Badge variant="outline" class="text-[10px]">Bahan Baku</Badge>
                                    {:else}
                                        <Badge variant="secondary" class="text-[10px]">Produk</Badge>
                                    {/if}
                                </TableCell>
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
                                <TableCell class="text-xs text-right font-bold {mut.type === 'in' ? 'text-emerald-600' : mut.type === 'out' ? 'text-destructive' : 'text-blue-600'}">
                                    {mut.type === 'in' ? '+' : mut.type === 'out' ? '-' : ''}{mut.quantity}
                                </TableCell>
                                <TableCell class="text-xs text-center">
                                    <span class="text-muted-foreground">{mut.previous_stock}</span>
                                    <span class="text-muted-foreground/60 mx-1">&rarr;</span>
                                    <span class="font-bold text-foreground">{mut.current_stock}</span>
                                </TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    {mut.reason}
                                </TableCell>
                                <TableCell class="text-xs font-medium">
                                    {mut.user?.name || '-'}
                                </TableCell>
                            </TableRow>
                        {/each}
                    {/if}
                </TableBody>
            </Table>
        </CardContent>
    </Card>
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
                            <option value={raw.id}>{raw.name} (Stok Saat Ini: {raw.stock} {raw.unit})</option>
                        {/each}
                    {:else}
                        {#each products as prd}
                            <option value={prd.id}>{prd.name} (Stok Saat Ini: {prd.stock} {prd.unit})</option>
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

<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Master Percetakan',
                href: '#',
            },
            {
                title: 'Bahan Baku',
                href: '/raw-materials',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AlertTriangle from '@lucide/svelte/icons/alert-triangle';
    import Boxes from '@lucide/svelte/icons/boxes';
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import Edit2 from '@lucide/svelte/icons/edit-2';
    import Plus from '@lucide/svelte/icons/plus';
    import Search from '@lucide/svelte/icons/search';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { DataTable, type ColumnDef, TableCell, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';

    interface RawMaterialItem {
        id: number;
        name: string;
        code: string;
        unit: string;
        stock: number;
        min_stock: number;
        cost_price: number;
        description: string | null;
        products_count: number;
    }

    let {
        rawMaterials = { data: [], links: [] },
        units = [],
        filters = {},
    }: {
        rawMaterials: any;
        units?: Array<{ id: number; name: string; symbol: string }>;
        filters: Record<string, any>;
    } = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let lowStockOnly = $state(filters.low_stock_only === 'true' || filters.low_stock_only === true);
    // svelte-ignore state_referenced_locally
    let currentPerPage = $state(filters.per_page ? parseInt(filters.per_page, 10) : 15);

    $effect(() => {
        search = filters.search || '';
        lowStockOnly = filters.low_stock_only === 'true' || filters.low_stock_only === true;
        currentPerPage = filters.per_page ? parseInt(filters.per_page, 10) : 15;
    });

    const columns: ColumnDef[] = [
        { key: 'code', label: 'Kode', sortable: true },
        { key: 'name', label: 'Nama Bahan Baku', sortable: true },
        { key: 'unit', label: 'Satuan', sortable: true },
        { key: 'stock', label: 'Stok Gudang', align: 'right', sortable: true },
        { key: 'min_stock', label: 'Min. Alert', align: 'right', sortable: true },
        { key: 'status', label: 'Status Stok', sortable: false },
        { key: 'cost_price', label: 'Harga Beli Modal', align: 'right', sortable: true },
        { key: 'actions', label: 'Aksi', align: 'right', sortable: false, hideable: false },
    ];

    // Modal state
    let showModal = $state(false);
    let editingMaterial = $state<RawMaterialItem | null>(null);
    let formName = $state('');
    let formCode = $state('');
    let formUnit = $state('meter');
    let formStock = $state('100');
    let formMinStock = $state('20');
    let formCostPrice = $state('15000');
    let formDescription = $state('');

    function applyFilter(newSearch?: string) {
        if (newSearch !== undefined) {
            search = newSearch;
        }
        router.get(
            '/raw-materials',
            {
                search: search || undefined,
                low_stock_only: lowStockOnly ? 'true' : undefined,
                per_page: currentPerPage,
            },
            { preserveState: true }
        );
    }

    function formatRupiah(amount: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);
    }

    function openCreate() {
        editingMaterial = null;
        formName = '';
        formCode = 'RAW-' + Math.floor(100 + Math.random() * 900);
        formUnit = 'meter';
        formStock = '100';
        formMinStock = '20';
        formCostPrice = '15000';
        formDescription = '';
        showModal = true;
    }

    function openEdit(item: RawMaterialItem) {
        editingMaterial = item;
        formName = item.name;
        formCode = item.code;
        formUnit = item.unit;
        formStock = item.stock.toString();
        formMinStock = item.min_stock.toString();
        formCostPrice = item.cost_price.toString();
        formDescription = item.description || '';
        showModal = true;
    }

    function saveMaterial() {
        const payload = {
            name: formName,
            code: formCode,
            unit: formUnit,
            stock: parseFloat(formStock) || 0,
            min_stock: parseFloat(formMinStock) || 0,
            cost_price: parseFloat(formCostPrice) || 0,
            description: formDescription,
        };

        if (editingMaterial) {
            router.put(`/raw-materials/${editingMaterial.id}`, payload, {
                onSuccess: () => (showModal = false),
            });
        } else {
            router.post('/raw-materials', payload, {
                onSuccess: () => (showModal = false),
            });
        }
    }

    function deleteMaterial(item: RawMaterialItem) {
        if (item.products_count > 0) {
            alert(`Bahan baku "${item.name}" masih terhubung dengan ${item.products_count} produk cetak.`);
            return;
        }

        if (confirm(`Yakin ingin menghapus bahan baku "${item.name}"?`)) {
            router.delete(`/raw-materials/${item.id}`);
        }
    }
</script>

<AppHead title="Bahan Baku Percetakan" />

<div class="flex flex-col gap-6 p-4 md:p-6 max-w-7xl mx-auto w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <Boxes class="size-6 text-primary" />
                Bahan Baku Percetakan
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Stok material roll, lembaran, tinta solvent, albatros, art carton, dan ring mata ayam untuk produksi cetak. Klik judul kolom untuk mengurutkan (sortable).
            </p>
        </div>
        <Button class="bg-primary font-semibold whitespace-nowrap inline-flex items-center" onclick={openCreate}>
            <Plus class="size-4 mr-1.5" />
            Tambah Bahan Baku
        </Button>
    </div>

    <!-- Reusable DataTable with Search, Column Filter, Records Per Page & Pagination in Head Table Card -->
    <DataTable
        {columns}
        data={rawMaterials}
        pagination={rawMaterials}
        search={search}
        searchPlaceholder="Cari kode atau nama bahan baku..."
        onSearch={(val) => applyFilter(val)}
        perPage={currentPerPage}
        onPerPageChange={(val) => {
            currentPerPage = val;
            applyFilter();
        }}
        onPageChange={(_, url) => {
            if (url) router.get(url, {}, { preserveState: true });
        }}
        defaultSortKey="name"
        defaultSortDir="asc"
        emptyMessage="Tidak ada data bahan baku ditemukan."
    >
        {#snippet filtersSnippet()}
            <label class="flex items-center gap-2 px-2 py-1 text-xs border rounded-md bg-background cursor-pointer hover:bg-muted/40 transition-colors">
                <input
                    type="checkbox"
                    bind:checked={lowStockOnly}
                    onchange={() => applyFilter()}
                    class="size-3.5 rounded border-input text-primary focus:ring-primary"
                />
                <span class="font-medium text-muted-foreground whitespace-nowrap">Hanya Stok Menipis (&le; Min Stok)</span>
            </label>
        {/snippet}

        {#snippet rowSnippet({ item: raw, isVisible })}
            <TableRow class="hover:bg-muted/30 transition-colors">
                {#if isVisible('code')}
                    <TableCell class="text-xs font-bold text-muted-foreground">{raw.code}</TableCell>
                {/if}
                {#if isVisible('name')}
                    <TableCell class="text-xs font-semibold text-foreground">
                        {raw.name}
                        {#if raw.description}
                            <span class="block text-[11px] text-muted-foreground font-normal line-clamp-1">{raw.description}</span>
                        {/if}
                    </TableCell>
                {/if}
                {#if isVisible('unit')}
                    <TableCell class="text-xs capitalize text-muted-foreground">{raw.unit}</TableCell>
                {/if}
                {#if isVisible('stock')}
                    <TableCell class="text-xs text-right font-bold text-foreground">
                        {raw.stock} {raw.unit}
                    </TableCell>
                {/if}
                {#if isVisible('min_stock')}
                    <TableCell class="text-xs text-right text-muted-foreground">
                        {raw.min_stock} {raw.unit}
                    </TableCell>
                {/if}
                {#if isVisible('status')}
                    <TableCell class="text-xs">
                        {#if raw.stock <= raw.min_stock}
                            <Badge variant="destructive" class="text-[10px] gap-1">
                                <AlertTriangle class="size-3" />
                                Menipis
                            </Badge>
                        {:else}
                            <Badge class="bg-emerald-600 hover:bg-emerald-600 text-white text-[10px] gap-1">
                                <CheckCircle2 class="size-3" />
                                Aman
                            </Badge>
                        {/if}
                    </TableCell>
                {/if}
                {#if isVisible('cost_price')}
                    <TableCell class="text-xs text-right text-foreground font-medium">
                        {formatRupiah(raw.cost_price)}
                    </TableCell>
                {/if}
                {#if isVisible('actions')}
                    <TableCell class="text-xs text-right">
                        <div class="flex items-center justify-end gap-1">
                            <Button size="sm" variant="ghost" class="h-7 w-7 p-0" onclick={() => openEdit(raw)} title="Ubah Data">
                                <Edit2 class="size-3.5 text-muted-foreground" />
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="h-7 w-7 p-0 text-destructive hover:bg-destructive/10"
                                disabled={raw.products_count > 0}
                                onclick={() => deleteMaterial(raw)}
                                title={raw.products_count > 0 ? 'Bahan baku terhubung dengan produk' : 'Hapus Bahan Baku'}
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </TableCell>
                {/if}
            </TableRow>
        {/snippet}
    </DataTable>
</div>

<!-- Modal: Tambah/Edit Bahan Baku -->
<Dialog open={showModal} onOpenChange={(open) => (showModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Boxes class="size-5 text-primary" />
                {editingMaterial ? 'Edit Bahan Baku' : 'Tambah Bahan Baku'}
            </DialogTitle>
            <DialogDescription class="text-xs">
                Data bahan baku digunakan untuk menghitung sisa stok fisik dan estimasi biaya HPP cetak.
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <Label for="r_code">Kode Bahan *</Label>
                    <Input id="r_code" bind:value={formCode} placeholder="RAW-FLX280" />
                </div>
                <div class="space-y-1">
                    <Label for="r_unit">Satuan Ukuran *</Label>
                    <select id="r_unit" bind:value={formUnit} class="w-full h-9 rounded-md border border-input bg-background px-3 pr-8">
                        {#if units && units.length > 0}
                            {#each units as u}
                                <option value={u.symbol}>{u.name} ({u.symbol})</option>
                            {/each}
                            {#if !units.some(u => u.symbol === formUnit) && formUnit}
                                <option value={formUnit}>{formUnit}</option>
                            {/if}
                        {:else}
                            <option value="meter">Meter (Roll Spanduk)</option>
                            <option value="m²">Meter Persegi (m²)</option>
                            <option value="lembar">Lembar (Plano/A3+)</option>
                            <option value="roll">Roll Utuh</option>
                            <option value="pcs">Pcs (Ring Mata Ayam/Stand)</option>
                            <option value="liter">Liter (Tinta Cetak)</option>
                            <option value="rim">Rim (Kertas)</option>
                        {/if}
                    </select>
                </div>
            </div>

            <div class="space-y-1">
                <Label for="r_name">Nama Bahan Baku *</Label>
                <Input id="r_name" bind:value={formName} placeholder="Contoh: Flexi China 280g (Roll 3.2m)" />
            </div>

            <div class="grid grid-cols-3 gap-2">
                {#if !editingMaterial}
                    <div class="space-y-1">
                        <Label for="r_stk">Stok Awal</Label>
                        <Input id="r_stk" type="number" bind:value={formStock} />
                    </div>
                {/if}
                <div class="space-y-1">
                    <Label for="r_min">Min. Alert</Label>
                    <Input id="r_min" type="number" bind:value={formMinStock} />
                </div>
                <div class="space-y-1">
                    <Label for="r_cost">Harga Beli (Rp)</Label>
                    <Input id="r_cost" type="number" bind:value={formCostPrice} class="font-bold" />
                </div>
            </div>

            <div class="space-y-1">
                <Label for="r_desc">Keterangan / Supplier</Label>
                <Textarea id="r_desc" bind:value={formDescription} placeholder="Supplier roll, ketebalan gramatur..." rows={2} />
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (showModal = false)}>Batal</Button>
            <Button class="bg-primary" onclick={saveMaterial} disabled={!formName || !formCode}>
                Simpan Bahan Baku
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

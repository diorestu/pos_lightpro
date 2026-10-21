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
    import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';
    import { createSortable } from '@/lib/sortable.svelte';

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
        filters = {},
    }: {
        rawMaterials: { data: RawMaterialItem[]; links: any[] };
        filters: Record<string, any>;
    } = $props();

    let search = $state(filters.search || '');
    let lowStockOnly = $state(filters.low_stock_only === 'true' || filters.low_stock_only === true);

    // Sorter
    const sorter = createSortable(() => rawMaterials.data, 'name', 'asc');

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

    function applyFilter() {
        router.get(
            '/raw-materials',
            {
                search: search || undefined,
                low_stock_only: lowStockOnly ? 'true' : undefined,
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
        <Button class="bg-primary font-semibold" onclick={openCreate}>
            <Plus class="size-4 mr-1.5" />
            + Tambah Bahan Baku
        </Button>
    </div>

    <!-- Filters -->
    <Card class="border shadow-2xs">
        <CardContent class="p-4 flex flex-col sm:flex-row items-center gap-3">
            <div class="relative flex-1 w-full">
                <Search class="size-3.5 absolute left-3 top-2.5 text-muted-foreground" />
                <Input
                    bind:value={search}
                    onchange={applyFilter}
                    placeholder="Cari kode atau nama bahan baku..."
                    class="h-8 pl-8 text-xs"
                />
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <input
                    type="checkbox"
                    id="low_stock_chk"
                    bind:checked={lowStockOnly}
                    onchange={applyFilter}
                    class="size-4 rounded border-input"
                />
                <Label for="low_stock_chk" class="text-xs font-medium cursor-pointer">
                    Hanya Stok Menipis (&le; Min Stok)
                </Label>
            </div>
        </CardContent>
    </Card>

    <!-- Table -->
    <Card class="border shadow-xs">
        <CardContent class="p-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'code' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('code')}
                        >
                            Kode
                        </TableHead>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'name' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('name')}
                        >
                            Nama Bahan Baku
                        </TableHead>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'unit' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('unit')}
                        >
                            Satuan
                        </TableHead>
                        <TableHead
                            class="text-xs text-right"
                            sortable
                            sortDirection={sorter.sortKey === 'stock' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('stock')}
                        >
                            Stok Gudang
                        </TableHead>
                        <TableHead
                            class="text-xs text-right"
                            sortable
                            sortDirection={sorter.sortKey === 'min_stock' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('min_stock')}
                        >
                            Min. Alert
                        </TableHead>
                        <TableHead class="text-xs">Status Stok</TableHead>
                        <TableHead
                            class="text-xs text-right"
                            sortable
                            sortDirection={sorter.sortKey === 'cost_price' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('cost_price')}
                        >
                            Harga Beli Modal
                        </TableHead>
                        <TableHead class="text-xs text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {#if sorter.sortedData.length === 0}
                        <TableRow>
                            <TableCell colspan={8} class="text-center py-10 text-muted-foreground text-xs">
                                Tidak ada data bahan baku ditemukan.
                            </TableCell>
                        </TableRow>
                    {:else}
                        {#each sorter.sortedData as raw (raw.id)}
                            <TableRow>
                                <TableCell class="text-xs font-bold text-muted-foreground">{raw.code}</TableCell>
                                <TableCell class="text-xs font-semibold text-foreground">
                                    {raw.name}
                                    {#if raw.description}
                                        <span class="block text-[11px] text-muted-foreground font-normal line-clamp-1">{raw.description}</span>
                                    {/if}
                                </TableCell>
                                <TableCell class="text-xs capitalize text-muted-foreground">{raw.unit}</TableCell>
                                <TableCell class="text-xs text-right font-bold text-foreground">
                                    {raw.stock} {raw.unit}
                                </TableCell>
                                <TableCell class="text-xs text-right text-muted-foreground">
                                    {raw.min_stock} {raw.unit}
                                </TableCell>
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
                                <TableCell class="text-xs text-right text-foreground font-medium">
                                    {formatRupiah(raw.cost_price)}
                                </TableCell>
                                <TableCell class="text-xs text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button size="sm" variant="ghost" class="h-7 w-7 p-0" onclick={() => openEdit(raw)}>
                                            <Edit2 class="size-3.5 text-muted-foreground" />
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="h-7 w-7 p-0 text-destructive hover:bg-destructive/10"
                                            disabled={raw.products_count > 0}
                                            onclick={() => deleteMaterial(raw)}
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        {/each}
                    {/if}
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</div>

<!-- Modal: Tambah/Edit Bahan Baku -->
<Dialog open={showModal} onOpenChange={(open) => (showModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Boxes class="size-5 text-primary" />
                {editingMaterial ? 'Edit Bahan Baku' : 'Tambah Bahan Baku Baru'}
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
                        <option value="meter">Meter (Roll Spanduk)</option>
                        <option value="lembar">Lembar (Plano/A3+)</option>
                        <option value="roll">Roll Utuh</option>
                        <option value="pcs">Pcs (Ring Mata Ayam/Stand)</option>
                        <option value="liter">Liter (Tinta Cetak)</option>
                        <option value="rim">Rim (Kertas)</option>
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

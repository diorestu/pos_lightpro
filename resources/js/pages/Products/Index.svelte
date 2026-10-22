<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Master Percetakan',
                href: '#',
            },
            {
                title: 'Produk',
                href: '/products',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Edit2 from '@lucide/svelte/icons/edit-2';
    import Filter from '@lucide/svelte/icons/filter';
    import Package from '@lucide/svelte/icons/package';
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

    interface ProductItem {
        id: number;
        category_id: number;
        raw_material_id: number | null;
        name: string;
        code: string;
        pricing_type: 'fixed' | 'dimension_sqm';
        unit: string;
        base_price: number;
        min_order: number;
        cost_price: number;
        stock: number;
        is_stock_managed: boolean;
        is_active: boolean;
        description: string | null;
        category?: { id: number; name: string };
        raw_material?: { id: number; name: string };
    }

    let {
        products = { data: [], links: [] },
        categories = [],
        rawMaterials = [],
        filters = {},
    }: {
        products: { data: ProductItem[]; links: any[] };
        categories: any[];
        rawMaterials: any[];
        filters: Record<string, string>;
    } = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let categoryFilter = $state(filters.category_id || '');
    // svelte-ignore state_referenced_locally
    let pricingTypeFilter = $state(filters.pricing_type || '');

    $effect(() => {
        search = filters.search || '';
        categoryFilter = filters.category_id || '';
        pricingTypeFilter = filters.pricing_type || '';
    });

    // Sorter
    const sorter = createSortable(() => products.data, 'name', 'asc');

    // Modal state
    let showModal = $state(false);
    let editingProduct = $state<ProductItem | null>(null);

    // Form inputs
    let formCategoryId = $state<number | string>('');
    let formRawMaterialId = $state<number | string>('');
    let formName = $state('');
    let formCode = $state('');
    let formPricingType = $state<'fixed' | 'dimension_sqm'>('dimension_sqm');
    let formUnit = $state('m²');
    let formBasePrice = $state('');
    let formMinOrder = $state('1');
    let formCostPrice = $state('0');
    let formStock = $state('0');
    let formIsStockManaged = $state(false);
    let formDescription = $state('');

    function applyFilter() {
        router.get(
            '/products',
            {
                search: search || undefined,
                category_id: categoryFilter || undefined,
                pricing_type: pricingTypeFilter || undefined,
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
        editingProduct = null;
        formCategoryId = categories.length > 0 ? categories[0].id : '';
        formRawMaterialId = '';
        formName = '';
        formCode = 'PRD-' + Math.floor(1000 + Math.random() * 9000);
        formPricingType = 'dimension_sqm';
        formUnit = 'm²';
        formBasePrice = '25000';
        formMinOrder = '1';
        formCostPrice = '12000';
        formStock = '0';
        formIsStockManaged = false;
        formDescription = '';
        showModal = true;
    }

    function openEdit(p: ProductItem) {
        editingProduct = p;
        formCategoryId = p.category_id;
        formRawMaterialId = p.raw_material_id || '';
        formName = p.name;
        formCode = p.code;
        formPricingType = p.pricing_type;
        formUnit = p.unit;
        formBasePrice = p.base_price.toString();
        formMinOrder = p.min_order.toString();
        formCostPrice = p.cost_price.toString();
        formStock = p.stock.toString();
        formIsStockManaged = p.is_stock_managed;
        formDescription = p.description || '';
        showModal = true;
    }

    function saveProduct() {
        const payload = {
            category_id: formCategoryId,
            raw_material_id: formRawMaterialId || null,
            name: formName,
            code: formCode,
            pricing_type: formPricingType,
            unit: formUnit,
            base_price: parseFloat(formBasePrice) || 0,
            min_order: parseFloat(formMinOrder) || 1,
            cost_price: parseFloat(formCostPrice) || 0,
            stock: parseFloat(formStock) || 0,
            is_stock_managed: formIsStockManaged,
            description: formDescription,
            is_active: true,
        };

        if (editingProduct) {
            router.put(`/products/${editingProduct.id}`, payload, {
                onSuccess: () => (showModal = false),
            });
        } else {
            router.post('/products', payload, {
                onSuccess: () => (showModal = false),
            });
        }
    }

    function deleteProduct(p: ProductItem) {
        if (confirm(`Yakin ingin menghapus produk "${p.name}"?`)) {
            router.delete(`/products/${p.id}`);
        }
    }
</script>

<AppHead title="Master Produk Percetakan" />

<div class="flex flex-col gap-6 p-4 md:p-6 max-w-7xl mx-auto w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <Package class="size-6 text-primary" />
                Produk Percetakan
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Katalog master produk: Spanduk meteran (m²), Stiker A3+, Kartu Nama, Merchandise, dan Banner Display. Klik judul kolom untuk mengurutkan (sortable).
            </p>
        </div>
        <Button class="bg-primary font-semibold" onclick={openCreate}>
            <Plus class="size-4 mr-1.5" />
            + Tambah Produk Baru
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
                    placeholder="Cari nama atau kode produk..."
                    class="h-8 pl-8 text-xs"
                />
            </div>
            <select
                bind:value={categoryFilter}
                onchange={applyFilter}
                class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-8 w-full sm:w-48"
            >
                <option value="">Semua Kategori</option>
                {#each categories as cat}
                    <option value={cat.id}>{cat.name}</option>
                {/each}
            </select>
            <select
                bind:value={pricingTypeFilter}
                onchange={applyFilter}
                class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-8 w-full sm:w-48"
            >
                <option value="">Semua Skema Harga</option>
                <option value="dimension_sqm">Meteran (Panjang x Lebar / m²)</option>
                <option value="fixed">Fixed (Pcs, Box, Rim, Lembar)</option>
            </select>
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
                            Nama Produk
                        </TableHead>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'category_id' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('category_id')}
                        >
                            Kategori
                        </TableHead>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'pricing_type' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('pricing_type')}
                        >
                            Skema Harga
                        </TableHead>
                        <TableHead class="text-xs">Bahan Baku Terkait</TableHead>
                        <TableHead
                            class="text-xs text-right"
                            sortable
                            sortDirection={sorter.sortKey === 'base_price' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('base_price')}
                        >
                            Harga Jual
                        </TableHead>
                        <TableHead
                            class="text-xs text-right"
                            sortable
                            sortDirection={sorter.sortKey === 'cost_price' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('cost_price')}
                        >
                            HPP Modal
                        </TableHead>
                        <TableHead class="text-xs text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {#if sorter.sortedData.length === 0}
                        <TableRow>
                            <TableCell colspan={8} class="text-center py-10 text-muted-foreground text-xs">
                                Tidak ada produk ditemukan.
                            </TableCell>
                        </TableRow>
                    {:else}
                        {#each sorter.sortedData as p (p.id)}
                            <TableRow>
                                <TableCell class="text-xs font-bold text-muted-foreground">{p.code}</TableCell>
                                <TableCell class="text-xs font-semibold text-foreground">
                                    {p.name}
                                    {#if p.description}
                                        <span class="block text-[11px] text-muted-foreground font-normal line-clamp-1">{p.description}</span>
                                    {/if}
                                </TableCell>
                                <TableCell class="text-xs">
                                    <Badge variant="outline" class="text-[10px]">{p.category?.name || '-'}</Badge>
                                </TableCell>
                                <TableCell class="text-xs">
                                    {#if p.pricing_type === 'dimension_sqm'}
                                        <Badge class="bg-blue-600 hover:bg-blue-600 text-white text-[10px]">
                                            Meteran (m²)
                                        </Badge>
                                    {:else}
                                        <Badge variant="secondary" class="text-[10px]">
                                            Fixed ({p.unit})
                                        </Badge>
                                    {/if}
                                </TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    {p.raw_material?.name || '-'}
                                </TableCell>
                                <TableCell class="text-xs text-right font-bold text-primary">
                                    {formatRupiah(p.base_price)}
                                    <span class="text-[10px] text-muted-foreground font-normal">/{p.unit}</span>
                                </TableCell>
                                <TableCell class="text-xs text-right text-muted-foreground font-medium">
                                    {formatRupiah(p.cost_price)}
                                </TableCell>
                                <TableCell class="text-xs text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button size="sm" variant="ghost" class="h-7 w-7 p-0" onclick={() => openEdit(p)}>
                                            <Edit2 class="size-3.5 text-muted-foreground" />
                                        </Button>
                                        <Button size="sm" variant="ghost" class="h-7 w-7 p-0 text-destructive hover:bg-destructive/10" onclick={() => deleteProduct(p)}>
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

<!-- Modal: Tambah / Edit Produk -->
<Dialog open={showModal} onOpenChange={(open) => (showModal = open)}>
    <DialogContent class="sm:max-w-lg">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Package class="size-5 text-primary" />
                {editingProduct ? 'Edit Produk Cetak' : 'Tambah Produk Cetak Baru'}
            </DialogTitle>
            <DialogDescription class="text-xs">
                Konfigurasi harga per meter (m²) atau fixed per pcs, serta hubungkan bahan baku terkait.
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <Label for="p_code">Kode / SKU *</Label>
                    <Input id="p_code" bind:value={formCode} placeholder="PRD-BAN280" />
                </div>
                <div class="space-y-1">
                    <Label for="p_cat">Kategori *</Label>
                    <select id="p_cat" bind:value={formCategoryId} class="w-full h-9 rounded-md border border-input bg-background px-3 pr-8">
                        {#each categories as cat}
                            <option value={cat.id}>{cat.name}</option>
                        {/each}
                    </select>
                </div>
            </div>

            <div class="space-y-1">
                <Label for="p_name">Nama Produk Cetak *</Label>
                <Input id="p_name" bind:value={formName} placeholder="Contoh: Banner Spanduk Flexi 280g" />
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <Label for="p_type">Skema Perhitungan Harga *</Label>
                    <select
                        id="p_type"
                        bind:value={formPricingType}
                        onchange={() => {
                            if (formPricingType === 'dimension_sqm') formUnit = 'm²';
                            else formUnit = 'pcs';
                        }}
                        class="w-full h-9 rounded-md border border-input bg-background px-3 pr-8"
                    >
                        <option value="dimension_sqm">Meteran (Panjang × Lebar / m²)</option>
                        <option value="fixed">Fixed (Pcs / Box / Rim / Lembar)</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <Label for="p_unit">Satuan Penjualan</Label>
                    <Input id="p_unit" bind:value={formUnit} placeholder="m², pcs, box, rim" />
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <div class="space-y-1">
                    <Label for="p_base">Harga Jual (Rp) *</Label>
                    <Input id="p_base" type="number" bind:value={formBasePrice} class="font-bold" />
                </div>
                <div class="space-y-1">
                    <Label for="p_cost">HPP Modal (Rp)</Label>
                    <Input id="p_cost" type="number" bind:value={formCostPrice} />
                </div>
                <div class="space-y-1">
                    <Label for="p_min">Min. Hitung Order</Label>
                    <Input id="p_min" type="number" step="0.1" bind:value={formMinOrder} />
                </div>
            </div>

            <div class="space-y-1">
                <Label for="p_raw">Bahan Baku Terkait (Pengurangan Stok Otomatis)</Label>
                <select id="p_raw" bind:value={formRawMaterialId} class="w-full h-9 rounded-md border border-input bg-background px-3 pr-8">
                    <option value="">-- Tidak Terhubung Bahan Baku --</option>
                    {#each rawMaterials as raw}
                        <option value={raw.id}>{raw.name} ({raw.code} - Sisa: {raw.stock} {raw.unit})</option>
                    {/each}
                </select>
            </div>

            <div class="space-y-1">
                <Label for="p_desc">Keterangan / Spesifikasi</Label>
                <Textarea id="p_desc" bind:value={formDescription} placeholder="Spesifikasi bahan, resolusi mesin..." rows={2} />
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (showModal = false)}>Batal</Button>
            <Button class="bg-primary" onclick={saveProduct} disabled={!formName || !formCode}>
                Simpan Produk
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

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
    import Boxes from '@lucide/svelte/icons/boxes';
    import Package from '@lucide/svelte/icons/package';
    import Plus from '@lucide/svelte/icons/plus';
    import Search from '@lucide/svelte/icons/search';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import BulkStockModal from '@/components/BulkStockModal.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { DataTable, type ColumnDef, TableCell, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';

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
        allProducts = [],
        categories = [],
        rawMaterials = [],
        units = [],
        filters = {},
    }: {
        products: any;
        allProducts?: any[];
        categories: any[];
        rawMaterials: any[];
        units?: Array<{ id: number; name: string; symbol: string }>;
        filters: Record<string, string>;
    } = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let categoryFilter = $state(filters.category_id || '');
    // svelte-ignore state_referenced_locally
    let pricingTypeFilter = $state(filters.pricing_type || '');
    // svelte-ignore state_referenced_locally
    let currentPerPage = $state(filters.per_page ? parseInt(filters.per_page, 10) : 15);

    $effect(() => {
        search = filters.search || '';
        categoryFilter = filters.category_id || '';
        pricingTypeFilter = filters.pricing_type || '';
        currentPerPage = filters.per_page ? parseInt(filters.per_page, 10) : 15;
    });

    const columns: ColumnDef[] = [
        { key: 'code', label: 'Kode', sortable: true },
        { key: 'name', label: 'Nama Produk', sortable: true },
        { key: 'category_id', label: 'Kategori', sortable: true },
        { key: 'pricing_type', label: 'Skema Harga', sortable: true },
        { key: 'raw_material', label: 'Bahan Baku Terkait', sortable: false },
        { key: 'base_price', label: 'Harga Jual', align: 'right', sortable: true },
        { key: 'cost_price', label: 'HPP Modal', align: 'right', sortable: true },
        { key: 'actions', label: 'Aksi', align: 'right', sortable: false, hideable: false },
    ];

    // Modal state
    let showModal = $state(false);
    let showBulkStockModal = $state(false);
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

    function applyFilter(newSearch?: string) {
        if (newSearch !== undefined) {
            search = newSearch;
        }
        router.get(
            '/products',
            {
                search: search || undefined,
                category_id: categoryFilter || undefined,
                pricing_type: pricingTypeFilter || undefined,
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
        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                class="font-semibold whitespace-nowrap inline-flex items-center border-primary/30 text-primary hover:bg-primary/10 shadow-2xs"
                onclick={() => (showBulkStockModal = true)}
            >
                <Boxes class="size-4 mr-1.5" />
                Kelola Stok Multi Produk
            </Button>
            <Button class="bg-primary font-semibold whitespace-nowrap inline-flex items-center" onclick={openCreate}>
                <Plus class="size-4 mr-1.5" />
                Tambah Produk
            </Button>
        </div>
    </div>

    <!-- Reusable DataTable with Search, Column Filter, Records Per Page & Pagination in Head Table Card -->
    <DataTable
        {columns}
        data={products}
        pagination={products}
        search={search}
        searchPlaceholder="Cari nama atau kode produk..."
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
        emptyMessage="Tidak ada produk ditemukan."
    >
        {#snippet filtersSnippet()}
            <select
                bind:value={categoryFilter}
                onchange={() => applyFilter()}
                class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-8 w-full sm:w-44 cursor-pointer font-medium"
            >
                <option value="">Semua Kategori</option>
                {#each categories as cat}
                    <option value={cat.id}>{cat.name}</option>
                {/each}
            </select>
            <select
                bind:value={pricingTypeFilter}
                onchange={() => applyFilter()}
                class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-8 w-full sm:w-44 cursor-pointer font-medium"
            >
                <option value="">Semua Skema Harga</option>
                <option value="dimension_sqm">Meteran (m²)</option>
                <option value="fixed">Fixed (Pcs/Box)</option>
            </select>
        {/snippet}

        {#snippet rowSnippet({ item: p, isVisible })}
            <TableRow class="hover:bg-muted/30 transition-colors">
                {#if isVisible('code')}
                    <TableCell class="text-xs font-bold text-muted-foreground">{p.code}</TableCell>
                {/if}
                {#if isVisible('name')}
                    <TableCell class="text-xs font-semibold text-foreground">
                        {p.name}
                        {#if p.description}
                            <span class="block text-[11px] text-muted-foreground font-normal line-clamp-1">{p.description}</span>
                        {/if}
                    </TableCell>
                {/if}
                {#if isVisible('category_id')}
                    <TableCell class="text-xs">
                        <Badge variant="outline" class="text-[10px]">{p.category?.name || '-'}</Badge>
                    </TableCell>
                {/if}
                {#if isVisible('pricing_type')}
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
                {/if}
                {#if isVisible('raw_material')}
                    <TableCell class="text-xs text-muted-foreground">
                        {p.raw_material?.name || '-'}
                    </TableCell>
                {/if}
                {#if isVisible('base_price')}
                    <TableCell class="text-xs text-right font-bold text-primary">
                        {formatRupiah(p.base_price)}
                        <span class="text-[10px] text-muted-foreground font-normal">/{p.unit}</span>
                    </TableCell>
                {/if}
                {#if isVisible('cost_price')}
                    <TableCell class="text-xs text-right text-muted-foreground font-medium">
                        {formatRupiah(p.cost_price)}
                    </TableCell>
                {/if}
                {#if isVisible('actions')}
                    <TableCell class="text-xs text-right">
                        <div class="flex items-center justify-end gap-1">
                            <Button size="sm" variant="ghost" class="h-7 w-7 p-0" onclick={() => openEdit(p)} title="Ubah Produk">
                                <Edit2 class="size-3.5 text-muted-foreground" />
                            </Button>
                            <Button size="sm" variant="ghost" class="h-7 w-7 p-0 text-destructive hover:bg-destructive/10" onclick={() => deleteProduct(p)} title="Hapus Produk">
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </TableCell>
                {/if}
            </TableRow>
        {/snippet}
    </DataTable>
</div>

<!-- Modal: Tambah / Edit Produk -->
<Dialog open={showModal} onOpenChange={(open) => (showModal = open)}>
    <DialogContent class="sm:max-w-lg">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Package class="size-5 text-primary" />
                {editingProduct ? 'Edit Produk Cetak' : 'Tambah Produk Cetak'}
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
                    {#if units && units.length > 0}
                        <select id="p_unit" bind:value={formUnit} class="w-full h-9 rounded-md border border-input bg-background px-3 pr-8">
                            {#each units as u}
                                <option value={u.symbol}>{u.name} ({u.symbol})</option>
                            {/each}
                            {#if !units.some(u => u.symbol === formUnit) && formUnit}
                                <option value={formUnit}>{formUnit}</option>
                            {/if}
                        </select>
                    {:else}
                        <Input id="p_unit" bind:value={formUnit} placeholder="m², pcs, box, rim" />
                    {/if}
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

<!-- Extra Width Popup: Kelola Stok Multi Produk -->
<BulkStockModal
    bind:open={showBulkStockModal}
    products={allProducts.length > 0 ? allProducts : (products?.data || [])}
    endpoint="/products/bulk-stock"
/>

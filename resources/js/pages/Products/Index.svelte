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
    import { formatStock, formatStockInput } from '@/lib/utils';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { DataTable, type ColumnDef, TableCell, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';

    interface PriceTier {
        id?: number;
        product_id?: number;
        min_quantity: number;
        max_quantity: number | null;
        price: number;
    }

    interface PricingSchemeItem {
        id: number;
        code: string;
        name: string;
        calculation_type: string;
        default_unit: string | null;
        is_active: boolean;
        description: string | null;
    }

    interface ProductItem {
        id: number;
        category_id: number;
        raw_material_id: number | null;
        name: string;
        code: string;
        pricing_type: string | null;
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
        price_tiers?: PriceTier[];
    }

    let {
        products = { data: [], links: [] },
        allProducts = [],
        categories = [],
        rawMaterials = [],
        units = [],
        pricingSchemes = [],
        filters = {},
    }: {
        products: any;
        allProducts?: any[];
        categories: any[];
        rawMaterials: any[];
        units?: Array<{ id: number; name: string; symbol: string }>;
        pricingSchemes?: PricingSchemeItem[];
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
    // By default nonaktif keduanya (tanpa skema)
    let formPricingType = $state<string>('none');
    let formUnit = $state('pcs');
    let formBasePrice = $state('');
    let formMinOrder = $state('1');
    let formCostPrice = $state('0');
    let formStock = $state('0');
    let formIsStockManaged = $state(false);
    let formDescription = $state('');
    let formPriceTiers = $state<Array<{ min_quantity: string; max_quantity: string; price: string }>>([]);

    function addPriceTier() {
        let nextMin = '10';
        if (formPriceTiers.length > 0) {
            const last = formPriceTiers[formPriceTiers.length - 1];
            if (last.max_quantity && Number(last.max_quantity) > 0) {
                nextMin = String(Number(last.max_quantity) + 1);
            } else if (last.min_quantity && Number(last.min_quantity) > 0) {
                nextMin = String(Number(last.min_quantity) + 10);
            }
        }
        formPriceTiers = [
            ...formPriceTiers,
            { min_quantity: nextMin, max_quantity: '', price: '' },
        ];
    }

    function removePriceTier(index: number) {
        formPriceTiers = formPriceTiers.filter((_, i) => i !== index);
    }

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

    let selectableSchemes = $derived(
        pricingSchemes.filter((s) => s.is_active || s.code === formPricingType)
    );

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
        // By default nonaktif keduanya (Tanpa Skema)
        formPricingType = 'none';
        formUnit = 'pcs';
        formBasePrice = '0';
        formMinOrder = '1';
        formCostPrice = '0';
        formStock = '0';
        formIsStockManaged = false;
        formDescription = '';
        formPriceTiers = [];
        showModal = true;
    }

    function openEdit(p: ProductItem) {
        editingProduct = p;
        formCategoryId = p.category_id;
        formRawMaterialId = p.raw_material_id || '';
        formName = p.name;
        formCode = p.code;
        formPricingType = p.pricing_type || 'none';
        formUnit = p.unit;
        formBasePrice = formatStockInput(p.base_price);
        formMinOrder = formatStockInput(p.min_order);
        formCostPrice = formatStockInput(p.cost_price);
        formStock = formatStockInput(p.stock);
        formIsStockManaged = p.is_stock_managed;
        formDescription = p.description || '';
        formPriceTiers = (p.price_tiers || []).map((t) => ({
            min_quantity: String(t.min_quantity),
            max_quantity: t.max_quantity != null ? String(t.max_quantity) : '',
            price: String(t.price),
        }));
        showModal = true;
    }

    function saveProduct() {
        const cleanTiers = formPriceTiers
            .filter((t) => t.min_quantity !== '' && t.price !== '')
            .map((t) => ({
                min_quantity: parseFloat(t.min_quantity),
                max_quantity: t.max_quantity !== '' && !isNaN(parseFloat(t.max_quantity)) ? parseFloat(t.max_quantity) : null,
                price: parseFloat(t.price),
            }));

        const payload = {
            category_id: formCategoryId,
            raw_material_id: formRawMaterialId || null,
            name: formName,
            code: formCode,
            pricing_type: formPricingType || 'none',
            unit: formUnit,
            base_price: parseFloat(formBasePrice) || 0,
            min_order: parseFloat(formMinOrder) || 1,
            cost_price: parseFloat(formCostPrice) || 0,
            stock: parseFloat(formStock) || 0,
            is_stock_managed: formIsStockManaged,
            description: formDescription,
            is_active: true,
            price_tiers: cleanTiers,
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
            <div class="flex flex-wrap items-center gap-2 text-xs w-full">
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
                    <option value="none">Tanpa Skema (Nonaktif)</option>
                    {#each pricingSchemes as s}
                        <option value={s.code}>{s.name}</option>
                    {/each}
                </select>

                {#if categoryFilter || pricingTypeFilter}
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 text-xs text-muted-foreground hover:text-foreground ml-auto"
                        onclick={() => {
                            categoryFilter = '';
                            pricingTypeFilter = '';
                            applyFilter();
                        }}
                    >
                        Reset Filter
                    </Button>
                {/if}
            </div>
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
                            <Badge class="bg-emerald-600 hover:bg-emerald-600 text-white text-[10px]">
                                Per Meter Luas (m²)
                            </Badge>
                        {:else if p.pricing_type === 'fixed'}
                            <Badge class="bg-blue-600 hover:bg-blue-600 text-white text-[10px]">
                                Per Pcs ({p.unit})
                            </Badge>
                        {:else if p.pricing_type && p.pricing_type !== 'none'}
                            <Badge variant="secondary" class="text-[10px]">
                                {pricingSchemes.find((s) => s.code === p.pricing_type)?.name || p.pricing_type}
                            </Badge>
                        {:else}
                            <Badge variant="outline" class="text-[10px] text-muted-foreground border-dashed bg-muted/20">
                                Tanpa Skema (Nonaktif)
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
                    <TableCell class="text-xs text-right">
                        <div class="font-bold text-primary">
                            {formatRupiah(p.base_price)}
                            <span class="text-[10px] text-muted-foreground font-normal">/{p.unit}</span>
                        </div>
                        {#if p.price_tiers && p.price_tiers.length > 0}
                            <div class="flex justify-end gap-1 mt-0.5" title="Tersedia harga grosir berjenjang">
                                <Badge variant="outline" class="text-[9px] px-1.5 py-0 bg-emerald-50 text-emerald-700 border-emerald-300 font-semibold">
                                    Grosir ({p.price_tiers.length} Level)
                                </Badge>
                            </div>
                        {/if}
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
    <DialogContent class="sm:max-w-xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Package class="size-5 text-primary" />
                {editingProduct ? 'Edit Produk Cetak' : 'Tambah Produk Cetak'}
            </DialogTitle>
            <DialogDescription class="text-xs">
                Konfigurasi harga per meter (m²) atau fixed per pcs, harga grosir bertingkat, serta hubungkan bahan baku terkait.
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

            <!-- Skema Perhitungan Harga (Maks 1 atau Nonaktif keduanya) -->
            <div class="rounded-lg border p-3 bg-muted/20 space-y-2.5">
                <div class="flex items-center justify-between">
                    <div>
                        <Label class="font-bold text-xs flex items-center gap-1.5 text-foreground">
                            <span>Skema Perhitungan Harga</span>
                            {#if formPricingType === 'none' || !formPricingType}
                                <Badge variant="outline" class="text-[10px] text-muted-foreground border-dashed">
                                    Nonaktif / Tanpa Skema
                                </Badge>
                            {:else}
                                <Badge class="text-[10px] bg-primary text-primary-foreground">
                                    {pricingSchemes.find((s) => s.code === formPricingType)?.name || formPricingType}
                                </Badge>
                            {/if}
                        </Label>
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            Pilih salah satu skema perhitungan aktif, atau nonaktifkan keduanya (default: nonaktif).
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <!-- Opsi 1: Nonaktif / Tanpa Skema (Default) -->
                    <button
                        type="button"
                        class="flex flex-col text-left p-2.5 rounded-md border transition-all text-xs {formPricingType === 'none' || !formPricingType ? 'border-primary bg-primary/10 ring-1 ring-primary font-medium text-foreground' : 'border-border/80 bg-background hover:bg-muted/50 text-muted-foreground'}"
                        onclick={() => {
                            formPricingType = 'none';
                            if (!formUnit || formUnit === 'm²') formUnit = 'pcs';
                        }}
                    >
                        <div class="flex items-center gap-1.5 font-semibold text-foreground">
                            <span class="inline-block size-2 rounded-full {formPricingType === 'none' || !formPricingType ? 'bg-primary' : 'bg-muted-foreground/40'}"></span>
                            Nonaktif (Tanpa Skema)
                        </div>
                        <span class="text-[10px] text-muted-foreground mt-1 leading-tight">
                            Perhitungan standar qty biasa tanpa rumus/luas (Default).
                        </span>
                    </button>

                    <!-- Opsi Skema dari Master -->
                    {#each selectableSchemes as scheme}
                        <button
                            type="button"
                            class="flex flex-col text-left p-2.5 rounded-md border transition-all text-xs {formPricingType === scheme.code ? 'border-primary bg-primary/10 ring-1 ring-primary font-medium text-foreground' : 'border-border/80 bg-background hover:bg-muted/50 text-muted-foreground'}"
                            onclick={() => {
                                formPricingType = scheme.code;
                                if (scheme.default_unit) {
                                    formUnit = scheme.default_unit;
                                } else if (scheme.code === 'dimension_sqm') {
                                    formUnit = 'm²';
                                } else if (scheme.code === 'fixed') {
                                    formUnit = 'pcs';
                                }
                            }}
                        >
                            <div class="flex items-center gap-1.5 font-semibold text-foreground">
                                <span class="inline-block size-2 rounded-full {formPricingType === scheme.code ? 'bg-primary' : 'bg-muted-foreground/40'}"></span>
                                {scheme.name}
                            </div>
                            <span class="text-[10px] text-muted-foreground mt-1 leading-tight">
                                {scheme.description || (scheme.code === 'dimension_sqm' ? 'Panjang × Lebar × Qty' : 'Satuan tetap per item')}
                            </span>
                        </button>
                    {/each}
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <div class="space-y-1">
                    <Label for="p_unit">Satuan Penjualan *</Label>
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
                <div class="space-y-1">
                    <Label for="p_base">Harga Jual (Rp) *</Label>
                    <Input id="p_base" type="number" bind:value={formBasePrice} class="font-bold" />
                </div>
                <div class="space-y-1">
                    <Label for="p_cost">HPP Modal (Rp)</Label>
                    <Input id="p_cost" type="number" bind:value={formCostPrice} />
                </div>
                <div class="space-y-1">
                    <Label for="p_min">
                        {formPricingType === 'dimension_sqm' ? 'Min. Luas (m²)' : 'Min. Order'}
                    </Label>
                    <Input id="p_min" type="number" step="0.1" bind:value={formMinOrder} />
                </div>
            </div>

            <!-- Harga Grosir / Berjenjang (Tiered Quantity Pricing) -->
            <div class="rounded-lg border bg-muted/20 p-3 space-y-2.5">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <Label class="font-bold text-xs flex items-center gap-1.5 text-foreground">
                            <span>Harga Grosir / Berjenjang (Berdasarkan Qty)</span>
                            {#if formPriceTiers.length > 0}
                                <Badge variant="secondary" class="text-[10px] px-1.5 py-0 bg-primary/10 text-primary">
                                    {formPriceTiers.length} Level
                                </Badge>
                            {/if}
                        </Label>
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            Sistem kasir otomatis mendeteksi dan mengubah harga satuan ketika kuantitas belanja mencapai rentang ini.
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        class="h-7 text-xs font-semibold px-2.5 shrink-0 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-200 dark:border-emerald-800"
                        onclick={addPriceTier}
                    >
                        <Plus class="size-3 mr-1" />
                        Tambah
                    </Button>
                </div>

                {#if formPriceTiers.length === 0}
                    <div class="py-2.5 px-3 text-center text-[11px] text-muted-foreground bg-background rounded border border-dashed">
                        Belum ada harga grosir untuk produk ini. Klik <strong class="text-foreground">+ Tambah</strong> untuk menetapkan harga berjenjang (contoh: 10 - 50 pcs, &gt; 50 pcs).
                    </div>
                {:else}
                    <div class="space-y-1.5">
                        <div class="grid grid-cols-[1fr_1fr_1.3fr_32px] gap-2 text-[10px] font-semibold text-muted-foreground px-1">
                            <span>Min. Qty *</span>
                            <span>Max. Qty (opsional)</span>
                            <span>Harga Satuan (Rp) *</span>
                            <span></span>
                        </div>
                        {#each formPriceTiers as tier, idx}
                            <div class="grid grid-cols-[1fr_1fr_1.3fr_32px] gap-2 items-center bg-background p-1.5 rounded border">
                                <div>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="Min (cth: 10)"
                                        bind:value={tier.min_quantity}
                                        class="h-7 text-xs"
                                    />
                                </div>
                                <div>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="Max (cth: 50)"
                                        bind:value={tier.max_quantity}
                                        class="h-7 text-xs"
                                        title="Kosongkan jika berlaku tanpa batas maksimal (misal: kuantitas 50 ke atas)"
                                    />
                                </div>
                                <div>
                                    <Input
                                        type="number"
                                        min="0"
                                        placeholder="Harga khusus (Rp)"
                                        bind:value={tier.price}
                                        class="h-7 text-xs font-bold text-primary"
                                    />
                                </div>
                                <div class="flex justify-center">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                                        onclick={() => removePriceTier(idx)}
                                        title="Hapus rentang grosir"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>

            <div class="space-y-1">
                <Label for="p_raw">Bahan Baku Terkait (Pengurangan Stok Otomatis)</Label>
                <select id="p_raw" bind:value={formRawMaterialId} class="w-full h-9 rounded-md border border-input bg-background px-3 pr-8">
                    <option value="">-- Tidak Terhubung Bahan Baku --</option>
                    {#each rawMaterials as raw}
                        <option value={raw.id}>{raw.name} ({raw.code} - Sisa: {formatStock(raw.stock)} {raw.unit})</option>
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

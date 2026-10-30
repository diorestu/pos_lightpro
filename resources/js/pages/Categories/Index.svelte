<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Master Percetakan',
                href: '#',
            },
            {
                title: 'Kategori Produk',
                href: '/categories',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Edit2 from '@lucide/svelte/icons/edit-2';
    import Plus from '@lucide/svelte/icons/plus';
    import Tags from '@lucide/svelte/icons/tags';
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

    interface CategoryItem {
        id: number;
        name: string;
        color?: string | null;
        slug: string;
        description: string | null;
        products_count: number;
    }

    let {
        categories = [],
    }: {
        categories: CategoryItem[];
    } = $props();

    const columns: ColumnDef[] = [
        { key: 'name', label: 'Nama Kategori', sortable: true },
        { key: 'slug', label: 'Slug URL', sortable: true },
        { key: 'description', label: 'Keterangan', sortable: true },
        { key: 'products_count', label: 'Jumlah Produk', align: 'center', sortable: true },
        { key: 'actions', label: 'Aksi', align: 'right', sortable: false, hideable: false },
    ];

    const PALETTE = [
        '#2563EB', '#059669', '#D97706', '#7C3AED', '#DC2626',
        '#DB2777', '#0891B2', '#4F46E5', '#EA580C', '#0D9488',
        '#9333EA', '#16A34A',
    ];

    function getRandomPaletteColor() {
        return PALETTE[Math.floor(Math.random() * PALETTE.length)];
    }

    let showModal = $state(false);
    let editingCategory = $state<CategoryItem | null>(null);
    let formName = $state('');
    let formColor = $state('#2563EB');
    let formDescription = $state('');

    function openCreate() {
        editingCategory = null;
        formName = '';
        formColor = getRandomPaletteColor();
        formDescription = '';
        showModal = true;
    }

    function openEdit(cat: CategoryItem) {
        editingCategory = cat;
        formName = cat.name;
        formColor = cat.color || getRandomPaletteColor();
        formDescription = cat.description || '';
        showModal = true;
    }

    function saveCategory() {
        const payload = {
            name: formName,
            color: formColor,
            description: formDescription,
        };

        if (editingCategory) {
            router.put(`/categories/${editingCategory.id}`, payload, {
                onSuccess: () => (showModal = false),
            });
        } else {
            router.post('/categories', payload, {
                onSuccess: () => (showModal = false),
            });
        }
    }

    function deleteCategory(cat: CategoryItem) {
        if (cat.products_count > 0) {
            alert(`Kategori "${cat.name}" masih memiliki ${cat.products_count} produk. Hapus atau pindahkan produk terlebih dahulu.`);
            return;
        }

        if (confirm(`Hapus kategori "${cat.name}"?`)) {
            router.delete(`/categories/${cat.id}`);
        }
    }
</script>

<AppHead title="Kategori Produk Percetakan" />

<div class="flex flex-col gap-6 p-4 md:p-6 max-w-5xl mx-auto w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <Tags class="size-6 text-primary" />
                Kategori Produk
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Pengelompokan divisi percetakan (Outdoor Printing, Indoor & Display, Digital Offset A3+, Merchandise, Finishing).
            </p>
        </div>
        <Button class="bg-primary font-semibold whitespace-nowrap inline-flex items-center" onclick={openCreate}>
            <Plus class="size-4 mr-1.5" />
            Tambah Kategori
        </Button>
    </div>

    <!-- Reusable DataTable -->
    <DataTable
        {columns}
        data={categories}
        defaultSortKey="name"
        defaultSortDir="asc"
        searchPlaceholder="Cari kategori percetakan..."
        emptyMessage="Belum ada kategori yang dibuat."
    >
        {#snippet rowSnippet({ item: cat, isVisible })}
            <TableRow class="hover:bg-muted/30 transition-colors">
                {#if isVisible('name')}
                    <TableCell class="font-bold text-xs text-foreground">
                        <div class="flex items-center gap-2">
                            <span class="size-3.5 rounded-full shrink-0 shadow-2xs border border-white/20" style="background-color: {cat.color || '#2563EB'};"></span>
                            <span style="color: {cat.color || 'inherit'};">{cat.name}</span>
                        </div>
                    </TableCell>
                {/if}
                {#if isVisible('slug')}
                    <TableCell class="text-xs text-muted-foreground">
                        {cat.slug}
                    </TableCell>
                {/if}
                {#if isVisible('description')}
                    <TableCell class="text-xs text-muted-foreground">
                        {cat.description || '-'}
                    </TableCell>
                {/if}
                {#if isVisible('products_count')}
                    <TableCell class="text-xs text-center">
                        <Badge variant="secondary" class="font-bold">
                            {cat.products_count} Produk
                        </Badge>
                    </TableCell>
                {/if}
                {#if isVisible('actions')}
                    <TableCell class="text-xs text-right">
                        <div class="flex items-center justify-end gap-1">
                            <Button size="sm" variant="ghost" class="h-7 w-7 p-0" onclick={() => openEdit(cat)} title="Ubah Kategori">
                                <Edit2 class="size-3.5 text-muted-foreground" />
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="h-7 w-7 p-0 text-destructive hover:bg-destructive/10"
                                disabled={cat.products_count > 0}
                                onclick={() => deleteCategory(cat)}
                                title={cat.products_count > 0 ? 'Kategori memiliki produk aktif' : 'Hapus Kategori'}
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

<!-- Modal: Tambah/Edit Kategori -->
<Dialog open={showModal} onOpenChange={(open) => (showModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Tags class="size-5 text-primary" />
                {editingCategory ? 'Edit Kategori' : 'Tambah Kategori Baru'}
            </DialogTitle>
            <DialogDescription class="text-xs">
                Kategori digunakan untuk memfilter daftar produk pada halaman POS dan laporan omset.
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
            <div class="space-y-1">
                <Label for="cat_name">Nama Kategori *</Label>
                <Input id="cat_name" bind:value={formName} placeholder="Contoh: Digital Offset A3+" />
            </div>
            <div class="space-y-1">
                <Label for="cat_color">Warna Kategori (POS)</Label>
                <div class="flex items-center gap-2">
                    <input
                        type="color"
                        id="cat_color"
                        bind:value={formColor}
                        class="size-8 rounded border p-0.5 cursor-pointer bg-background shrink-0"
                    />
                    <Input bind:value={formColor} placeholder="#2563EB" class="font-mono text-xs uppercase h-8" />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 text-xs shrink-0"
                        onclick={() => (formColor = getRandomPaletteColor())}
                    >
                        Acak Warna
                    </Button>
                </div>
            </div>
            <div class="space-y-1">
                <Label for="cat_desc">Deskripsi</Label>
                <Textarea id="cat_desc" bind:value={formDescription} placeholder="Keterangan divisi..." rows={2} />
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (showModal = false)}>Batal</Button>
            <Button class="bg-primary" onclick={saveCategory} disabled={!formName}>
                Simpan Kategori
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

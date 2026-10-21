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
    import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';
    import { createSortable } from '@/lib/sortable.svelte';

    interface CategoryItem {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        products_count: number;
    }

    let {
        categories = [],
    }: {
        categories: CategoryItem[];
    } = $props();

    const sortable = createSortable(() => categories, 'name', 'asc');

    let showModal = $state(false);
    let editingCategory = $state<CategoryItem | null>(null);
    let formName = $state('');
    let formDescription = $state('');

    function openCreate() {
        editingCategory = null;
        formName = '';
        formDescription = '';
        showModal = true;
    }

    function openEdit(cat: CategoryItem) {
        editingCategory = cat;
        formName = cat.name;
        formDescription = cat.description || '';
        showModal = true;
    }

    function saveCategory() {
        const payload = {
            name: formName,
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
        <Button class="bg-primary font-semibold" onclick={openCreate}>
            <Plus class="size-4 mr-1.5" />
            + Tambah Kategori
        </Button>
    </div>

    <!-- Table -->
    <Card class="border shadow-xs">
        <CardContent class="p-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="text-xs" sortable sortDirection={sortable.getDirection('name')} onSort={() => sortable.toggleSort('name')}>
                            Nama Kategori
                        </TableHead>
                        <TableHead class="text-xs" sortable sortDirection={sortable.getDirection('slug')} onSort={() => sortable.toggleSort('slug')}>
                            Slug URL
                        </TableHead>
                        <TableHead class="text-xs" sortable sortDirection={sortable.getDirection('description')} onSort={() => sortable.toggleSort('description')}>
                            Keterangan
                        </TableHead>
                        <TableHead class="text-xs text-center" sortable sortDirection={sortable.getDirection('products_count')} onSort={() => sortable.toggleSort('products_count')}>
                            Jumlah Produk
                        </TableHead>
                        <TableHead class="text-xs text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {#if sortable.sorted.length === 0}
                        <TableRow>
                            <TableCell colspan={5} class="text-center py-10 text-muted-foreground text-xs">
                                Belum ada kategori yang dibuat.
                            </TableCell>
                        </TableRow>
                    {:else}
                        {#each sortable.sorted as cat}
                            <TableRow>
                                <TableCell class="font-bold text-xs text-foreground">
                                    {cat.name}
                                </TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    {cat.slug}
                                </TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    {cat.description || '-'}
                                </TableCell>
                                <TableCell class="text-xs text-center">
                                    <Badge variant="secondary" class="font-bold">
                                        {cat.products_count} Produk
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-xs text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button size="sm" variant="ghost" class="h-7 w-7 p-0" onclick={() => openEdit(cat)}>
                                            <Edit2 class="size-3.5 text-muted-foreground" />
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="h-7 w-7 p-0 text-destructive hover:bg-destructive/10"
                                            disabled={cat.products_count > 0}
                                            onclick={() => deleteCategory(cat)}
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

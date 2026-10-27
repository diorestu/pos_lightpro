<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Pengaturan',
                href: '#',
            },
            {
                title: 'Master Satuan',
                href: '/settings/units',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Edit2 from '@lucide/svelte/icons/edit-2';
    import Plus from '@lucide/svelte/icons/plus';
    import Ruler from '@lucide/svelte/icons/ruler';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { DataTable, type ColumnDef, TableCell, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';

    interface UnitItem {
        id: number;
        name: string;
        symbol: string;
        description: string | null;
        is_active: boolean;
        products_count: number;
        raw_materials_count: number;
    }

    let {
        units = [],
    }: {
        units: UnitItem[];
    } = $props();

    const columns: ColumnDef[] = [
        { key: 'name', label: 'Nama Satuan', sortable: true },
        { key: 'symbol', label: 'Simbol / Kode', sortable: true },
        { key: 'description', label: 'Keterangan', sortable: true },
        { key: 'usage', label: 'Penggunaan', align: 'center', sortable: false },
        { key: 'is_active', label: 'Status', align: 'center', sortable: true },
        { key: 'actions', label: 'Aksi', align: 'right', sortable: false, hideable: false },
    ];

    let showModal = $state(false);
    let editingUnit = $state<UnitItem | null>(null);
    let formName = $state('');
    let formSymbol = $state('');
    let formDescription = $state('');
    let formIsActive = $state(true);

    function openCreate() {
        editingUnit = null;
        formName = '';
        formSymbol = '';
        formDescription = '';
        formIsActive = true;
        showModal = true;
    }

    function openEdit(unit: UnitItem) {
        editingUnit = unit;
        formName = unit.name;
        formSymbol = unit.symbol;
        formDescription = unit.description || '';
        formIsActive = unit.is_active;
        showModal = true;
    }

    function saveUnit() {
        if (!formName.trim() || !formSymbol.trim()) {
            alert('Nama satuan dan simbol satuan wajib diisi.');
            return;
        }

        const payload = {
            name: formName.trim(),
            symbol: formSymbol.trim(),
            description: formDescription.trim(),
            is_active: formIsActive,
        };

        if (editingUnit) {
            router.put(`/settings/units/${editingUnit.id}`, payload, {
                onSuccess: () => (showModal = false),
            });
        } else {
            router.post('/settings/units', payload, {
                onSuccess: () => (showModal = false),
            });
        }
    }

    function deleteUnit(unit: UnitItem) {
        if (unit.products_count > 0 || unit.raw_materials_count > 0) {
            const usageParts: string[] = [];
            if (unit.products_count > 0) usageParts.push(`${unit.products_count} produk`);
            if (unit.raw_materials_count > 0) usageParts.push(`${unit.raw_materials_count} bahan baku`);
            alert(`Satuan "${unit.name}" (${unit.symbol}) masih digunakan oleh ${usageParts.join(' dan ')}. Hapus atau alihkan produk/bahan baku terlebih dahulu.`);
            return;
        }

        if (confirm(`Hapus satuan barang "${unit.name}" (${unit.symbol})?`)) {
            router.delete(`/settings/units/${unit.id}`);
        }
    }
</script>

<AppHead title="Master Satuan Barang - Pengaturan" />

<div class="flex flex-col gap-6 p-4 md:p-6 max-w-5xl mx-auto w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <Ruler class="size-6 text-primary" />
                Master Satuan Barang
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Kelola daftar unit satuan ukur untuk produk percetakan dan stok bahan baku (misal: pcs, m², lembar, roll, rim, box).
            </p>
        </div>
        <Button class="bg-primary font-semibold whitespace-nowrap inline-flex items-center" onclick={openCreate}>
            <Plus class="size-4 mr-1.5" />
            Tambah Satuan
        </Button>
    </div>

    <!-- Reusable DataTable -->
    <DataTable
        {columns}
        data={units}
        defaultSortKey="name"
        defaultSortDir="asc"
        searchPlaceholder="Cari nama atau simbol satuan..."
        emptyMessage="Belum ada data satuan barang."
    >
        {#snippet rowSnippet({ item: unit, isVisible })}
            <TableRow class="hover:bg-muted/30 transition-colors">
                {#if isVisible('name')}
                    <TableCell class="font-bold text-xs text-foreground">
                        {unit.name}
                    </TableCell>
                {/if}
                {#if isVisible('symbol')}
                    <TableCell class="text-xs">
                        <span class="inline-block px-2 py-0.5 rounded font-mono text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                            {unit.symbol}
                        </span>
                    </TableCell>
                {/if}
                {#if isVisible('description')}
                    <TableCell class="text-xs text-muted-foreground max-w-xs truncate">
                        {unit.description || '-'}
                    </TableCell>
                {/if}
                {#if isVisible('usage')}
                    <TableCell class="text-xs text-center">
                        <div class="flex items-center justify-center gap-1.5 flex-wrap">
                            {#if unit.products_count > 0}
                                <Badge variant="secondary" class="text-[11px] font-semibold">
                                    {unit.products_count} Produk
                                </Badge>
                            {/if}
                            {#if unit.raw_materials_count > 0}
                                <Badge variant="outline" class="text-[11px] font-semibold">
                                    {unit.raw_materials_count} Bahan
                                </Badge>
                            {/if}
                            {#if unit.products_count === 0 && unit.raw_materials_count === 0}
                                <span class="text-xs text-muted-foreground italic">Belum terpakai</span>
                            {/if}
                        </div>
                    </TableCell>
                {/if}
                {#if isVisible('is_active')}
                    <TableCell class="text-xs text-center">
                        {#if unit.is_active}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                Aktif
                            </span>
                        {:else}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-muted text-muted-foreground">
                                Nonaktif
                            </span>
                        {/if}
                    </TableCell>
                {/if}
                {#if isVisible('actions')}
                    <TableCell class="text-xs text-right">
                        <div class="flex items-center justify-end gap-1">
                            <Button size="sm" variant="ghost" class="h-7 w-7 p-0" onclick={() => openEdit(unit)} title="Ubah Satuan">
                                <Edit2 class="size-3.5 text-muted-foreground" />
                            </Button>
                            <Button size="sm" variant="ghost" class="h-7 w-7 p-0 text-destructive hover:bg-destructive/10" onclick={() => deleteUnit(unit)} title="Hapus Satuan">
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </TableCell>
                {/if}
            </TableRow>
        {/snippet}
    </DataTable>
</div>

<!-- Modal Tambah/Edit Satuan -->
<Dialog bind:open={showModal}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle>
                {editingUnit ? 'Ubah Satuan Barang' : 'Tambah Satuan Barang'}
            </DialogTitle>
            <DialogDescription>
                Tentukan nama dan simbol satuan yang digunakan pada produk cetak atau bahan baku.
            </DialogDescription>
        </DialogHeader>

        <form onsubmit={(e) => { e.preventDefault(); saveUnit(); }} class="space-y-4 py-2">
            <div class="space-y-1.5">
                <Label for="unit_name" class="text-xs font-semibold">Nama Satuan <span class="text-destructive">*</span></Label>
                <Input
                    id="unit_name"
                    bind:value={formName}
                    placeholder="Contoh: Meter Persegi, Pieces, Lembar"
                    required
                />
            </div>

            <div class="space-y-1.5">
                <Label for="unit_symbol" class="text-xs font-semibold">Simbol / Kode Satuan <span class="text-destructive">*</span></Label>
                <Input
                    id="unit_symbol"
                    bind:value={formSymbol}
                    placeholder="Contoh: m², pcs, lbr, roll, rim"
                    required
                />
                <p class="text-[11px] text-muted-foreground">
                    Simbol ini akan ditampilkan di katalog, keranjang POS, dan nota cetak.
                </p>
            </div>

            <div class="space-y-1.5">
                <Label for="unit_desc" class="text-xs font-semibold">Keterangan (Opsional)</Label>
                <Textarea
                    id="unit_desc"
                    bind:value={formDescription}
                    rows={2}
                    placeholder="Penjelasan penggunaan satuan ini..."
                />
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input
                    type="checkbox"
                    id="unit_active"
                    bind:checked={formIsActive}
                    class="h-4 w-4 rounded border-input text-primary focus:ring-primary accent-primary"
                />
                <Label for="unit_active" class="text-xs font-medium cursor-pointer">
                    Satuan Aktif (Dapat dipilih di form produk & bahan)
                </Label>
            </div>

            <DialogFooter class="pt-4">
                <Button type="button" variant="outline" onclick={() => (showModal = false)}>
                    Batal
                </Button>
                <Button type="submit" class="bg-primary font-semibold">
                    Simpan Satuan
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>

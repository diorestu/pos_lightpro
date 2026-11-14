<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Katalog & Bahan',
                href: '#',
            },
            {
                title: 'Skema Perhitungan',
                href: '/pricing-schemes',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Calculator from '@lucide/svelte/icons/calculator';
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import Edit2 from '@lucide/svelte/icons/edit-2';
    import Plus from '@lucide/svelte/icons/plus';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import XCircle from '@lucide/svelte/icons/x-circle';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { DataTable, type ColumnDef, TableCell, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';

    interface SchemeItem {
        id: number;
        code: string;
        name: string;
        calculation_type: 'fixed' | 'dimension_sqm';
        default_unit?: string | null;
        description?: string | null;
        is_active: boolean;
        products_count: number;
        created_at?: string;
        updated_at?: string;
    }

    interface UnitItem {
        id: number;
        name: string;
        symbol: string;
    }

    let {
        schemes = [],
        units = [],
    }: {
        schemes: SchemeItem[];
        units: UnitItem[];
    } = $props();

    const columns: ColumnDef[] = [
        { key: 'name', label: 'Nama Skema & Kode', sortable: true },
        { key: 'calculation_type', label: 'Tipe Kalkulasi', sortable: true },
        { key: 'default_unit', label: 'Satuan Bawaan', align: 'center', sortable: true },
        { key: 'products_count', label: 'Digunakan Produk', align: 'center', sortable: true },
        { key: 'is_active', label: 'Status Keaktifan', align: 'center', sortable: true },
        { key: 'actions', label: 'Aksi', align: 'right', sortable: false, hideable: false },
    ];

    let showModal = $state(false);
    let editingScheme = $state<SchemeItem | null>(null);

    let formName = $state('');
    let formCode = $state('');
    let formCalcType = $state<'fixed' | 'dimension_sqm'>('fixed');
    let formDefaultUnit = $state('pcs');
    let formDescription = $state('');
    let formIsActive = $state(true);

    function openCreate() {
        editingScheme = null;
        formName = '';
        formCode = '';
        formCalcType = 'fixed';
        formDefaultUnit = 'pcs';
        formDescription = '';
        formIsActive = true;
        showModal = true;
    }

    function openEdit(item: SchemeItem) {
        editingScheme = item;
        formName = item.name;
        formCode = item.code;
        formCalcType = item.calculation_type;
        formDefaultUnit = item.default_unit || 'pcs';
        formDescription = item.description || '';
        formIsActive = item.is_active;
        showModal = true;
    }

    function saveScheme() {
        if (!formName.trim()) {
            alert('Nama skema perhitungan wajib diisi.');
            return;
        }

        if (!formCode.trim()) {
            alert('Kode skema wajib diisi.');
            return;
        }

        const payload = {
            name: formName.trim(),
            code: formCode.trim().toLowerCase(),
            calculation_type: formCalcType,
            default_unit: formDefaultUnit,
            description: formDescription.trim(),
            is_active: formIsActive,
        };

        if (editingScheme) {
            router.put(`/pricing-schemes/${editingScheme.id}`, payload, {
                onSuccess: () => (showModal = false),
            });
        } else {
            router.post('/pricing-schemes', payload, {
                onSuccess: () => (showModal = false),
            });
        }
    }

    function toggleStatus(item: SchemeItem) {
        router.patch(`/pricing-schemes/${item.id}/toggle`, {}, {
            preserveScroll: true,
        });
    }

    function deleteScheme(item: SchemeItem) {
        if (['fixed', 'dimension_sqm'].includes(item.code)) {
            alert(`Skema bawaan sistem "${item.name}" tidak dapat dihapus, namun Anda dapat menonaktifkan status keaktifannya.`);
            return;
        }

        if (item.products_count > 0) {
            alert(`Skema "${item.name}" sedang digunakan oleh ${item.products_count} produk. Anda tidak dapat menghapusnya, namun dapat menonaktifkan status keaktifannya.`);
            return;
        }

        if (confirm(`Yakin ingin menghapus skema perhitungan "${item.name}"?`)) {
            router.delete(`/pricing-schemes/${item.id}`);
        }
    }
</script>

<AppHead title="Master Skema Perhitungan" />

<div class="flex flex-col gap-6 p-4 md:p-6">
    <!-- Header -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl md:text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <Calculator class="size-6 text-primary" />
                Master Skema Perhitungan
            </h1>
            <p class="text-xs md:text-sm text-muted-foreground mt-0.5">
                Kelola opsi skema kalkulasi harga produk cetak (Per Pcs, Per Meter Luas bahan, dll) dan atur keaktifan pilihannya.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <Button size="sm" onclick={openCreate} class="gap-1.5 bg-primary text-primary-foreground">
                <Plus class="size-4" />
                Tambah Skema
            </Button>
        </div>
    </div>

    <!-- DataTable Component -->
    <DataTable data={schemes} {columns} searchPlaceholder="Cari nama skema, kode, atau deskripsi...">
        {#snippet row({ item, isVisible })}
            <TableRow class="hover:bg-muted/50 transition-colors">
                {#if isVisible('name')}
                    <TableCell class="font-medium">
                        <div class="flex flex-col gap-0.5">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-foreground text-sm">{item.name}</span>
                                <span class="font-mono text-[10px] bg-muted px-1.5 py-0.5 rounded text-muted-foreground border">
                                    {item.code}
                                </span>
                            </div>
                            {#if item.description}
                                <span class="text-xs text-muted-foreground line-clamp-1">{item.description}</span>
                            {/if}
                        </div>
                    </TableCell>
                {/if}

                {#if isVisible('calculation_type')}
                    <TableCell>
                        {#if item.calculation_type === 'dimension_sqm'}
                            <Badge class="bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300">
                                Per Meter Luas (m²)
                            </Badge>
                        {:else}
                            <Badge class="bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-300">
                                Per Pcs / Fixed
                            </Badge>
                        {/if}
                    </TableCell>
                {/if}

                {#if isVisible('default_unit')}
                    <TableCell class="text-center font-mono text-xs">
                        {item.default_unit || '-'}
                    </TableCell>
                {/if}

                {#if isVisible('products_count')}
                    <TableCell class="text-center">
                        <Badge variant="outline" class="font-mono text-xs">
                            {item.products_count} Produk
                        </Badge>
                    </TableCell>
                {/if}

                {#if isVisible('is_active')}
                    <TableCell class="text-center">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold cursor-pointer transition-all border {item.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800' : 'bg-neutral-100 text-neutral-600 border-neutral-300 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-400 dark:border-neutral-700'}"
                            onclick={() => toggleStatus(item)}
                            title="Klik untuk mengubah status aktif/nonaktif"
                        >
                            {#if item.is_active}
                                <CheckCircle2 class="size-3.5 text-emerald-600" />
                                Aktif
                            {:else}
                                <XCircle class="size-3.5 text-neutral-400" />
                                Nonaktif
                            {/if}
                        </button>
                    </TableCell>
                {/if}

                {#if isVisible('actions')}
                    <TableCell class="text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-8 px-2.5 text-xs border-input hover:bg-accent"
                                onclick={() => openEdit(item)}
                                title="Edit Skema"
                            >
                                <Edit2 class="size-3.5 mr-1" />
                                Edit
                            </Button>
                            {#if !['fixed', 'dimension_sqm'].includes(item.code)}
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-8 px-2 text-xs text-destructive hover:bg-destructive/10 border-destructive/30"
                                    onclick={() => deleteScheme(item)}
                                    title="Hapus Skema"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            {/if}
                        </div>
                    </TableCell>
                {/if}
            </TableRow>
        {/snippet}
    </DataTable>
</div>

<!-- Modal: Tambah / Edit Skema Perhitungan -->
<Dialog open={showModal} onOpenChange={(open) => (showModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Calculator class="size-5 text-primary" />
                {editingScheme ? 'Edit Skema Perhitungan' : 'Tambah Skema Perhitungan'}
            </DialogTitle>
            <DialogDescription class="text-xs">
                {editingScheme
                    ? 'Perbarui rincian tipe kalkulasi dan status keaktifan skema perhitungan ini.'
                    : 'Daftarkan opsi skema perhitungan harga baru untuk produk percetakan.'}
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3.5 py-2 text-xs">
            <div class="space-y-1">
                <Label for="scheme_name">Nama Skema *</Label>
                <Input
                    id="scheme_name"
                    bind:value={formName}
                    placeholder="Contoh: Per Meter Luas Bahan Tercetak"
                    class="h-9 text-xs"
                />
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <Label for="scheme_code">Kode Unik *</Label>
                    <Input
                        id="scheme_code"
                        bind:value={formCode}
                        placeholder="Contoh: dimension_sqm"
                        class="h-9 text-xs font-mono lowercase"
                        disabled={editingScheme && ['fixed', 'dimension_sqm'].includes(editingScheme.code)}
                    />
                    {#if editingScheme && ['fixed', 'dimension_sqm'].includes(editingScheme.code)}
                        <span class="text-[10px] text-muted-foreground">Kode bawaan sistem dikunci</span>
                    {/if}
                </div>

                <div class="space-y-1">
                    <Label for="scheme_calc_type">Tipe Kalkulasi Dasar *</Label>
                    <select
                        id="scheme_calc_type"
                        bind:value={formCalcType}
                        onchange={() => {
                            if (formCalcType === 'dimension_sqm') formDefaultUnit = 'm²';
                            else formDefaultUnit = 'pcs';
                        }}
                        class="w-full h-9 rounded-md border border-input bg-background px-3 text-xs"
                    >
                        <option value="fixed">Per Pcs / Fixed (Harga Satuan)</option>
                        <option value="dimension_sqm">Per Meter Luas (Panjang × Lebar / m²)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <Label for="scheme_unit">Satuan Bawaan (Default)</Label>
                    {#if units && units.length > 0}
                        <select
                            id="scheme_unit"
                            bind:value={formDefaultUnit}
                            class="w-full h-9 rounded-md border border-input bg-background px-3 text-xs"
                        >
                            {#each units as u}
                                <option value={u.symbol}>{u.name} ({u.symbol})</option>
                            {/each}
                        </select>
                    {:else}
                        <Input
                            id="scheme_unit"
                            bind:value={formDefaultUnit}
                            placeholder="m² atau pcs"
                            class="h-9 text-xs"
                        />
                    {/if}
                </div>

                <div class="flex items-center pt-5">
                    <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-medium">
                        <input
                            type="checkbox"
                            bind:checked={formIsActive}
                            class="rounded border-input text-primary focus:ring-primary size-4"
                        />
                        <span>Aktifkan Skema Ini</span>
                    </label>
                </div>
            </div>

            <div class="space-y-1">
                <Label for="scheme_desc">Deskripsi / Keterangan</Label>
                <Textarea
                    id="scheme_desc"
                    bind:value={formDescription}
                    placeholder="Penjelasan bagaimana skema perhitungan ini digunakan pada transaksi..."
                    rows={2}
                    class="text-xs resize-none"
                />
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" class="text-xs" onclick={() => (showModal = false)}>
                Batal
            </Button>
            <Button class="text-xs bg-primary text-primary-foreground font-semibold" onclick={saveScheme}>
                {editingScheme ? 'Simpan Perubahan' : 'Tambah Skema'}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

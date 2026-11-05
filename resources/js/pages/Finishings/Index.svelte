<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Katalog & Bahan',
                href: '#',
            },
            {
                title: 'Finishing Cetak',
                href: '/finishings',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Edit2 from '@lucide/svelte/icons/edit-2';
    import Plus from '@lucide/svelte/icons/plus';
    import Scissors from '@lucide/svelte/icons/scissors';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { DataTable, type ColumnDef, TableCell, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';

    interface FinishingItem {
        id: number;
        name: string;
        price: number;
        description: string | null;
        is_active: boolean;
        orders_count: number;
        created_at?: string;
        updated_at?: string;
    }

    let {
        finishings = [],
    }: {
        finishings: FinishingItem[];
    } = $props();

    const columns: ColumnDef[] = [
        { key: 'name', label: 'Nama Finishing', sortable: true },
        { key: 'price', label: 'Biaya / Harga Tambahan', align: 'right', sortable: true },
        { key: 'description', label: 'Keterangan', sortable: true },
        { key: 'usage', label: 'Penggunaan Transaksi', align: 'center', sortable: false },
        { key: 'is_active', label: 'Status', align: 'center', sortable: true },
        { key: 'actions', label: 'Aksi', align: 'right', sortable: false, hideable: false },
    ];

    let showModal = $state(false);
    let editingFinishing = $state<FinishingItem | null>(null);
    let formName = $state('');
    let formPrice = $state<number | string>(0);
    let formDescription = $state('');
    let formIsActive = $state(true);

    function formatRupiah(amount: number): string {
        return 'Rp ' + Number(amount || 0).toLocaleString('id-ID');
    }

    function openCreate() {
        editingFinishing = null;
        formName = '';
        formPrice = 0;
        formDescription = '';
        formIsActive = true;
        showModal = true;
    }

    function openEdit(item: FinishingItem) {
        editingFinishing = item;
        formName = item.name;
        formPrice = item.price;
        formDescription = item.description || '';
        formIsActive = item.is_active;
        showModal = true;
    }

    function saveFinishing() {
        if (!formName.trim()) {
            alert('Nama finishing wajib diisi.');
            return;
        }

        const priceNum = Number(formPrice);
        if (isNaN(priceNum) || priceNum < 0) {
            alert('Harga / biaya finishing tidak boleh bernilai negatif.');
            return;
        }

        const payload = {
            name: formName.trim(),
            price: priceNum,
            description: formDescription.trim(),
            is_active: formIsActive,
        };

        if (editingFinishing) {
            router.put(`/finishings/${editingFinishing.id}`, payload, {
                onSuccess: () => (showModal = false),
            });
        } else {
            router.post('/finishings', payload, {
                onSuccess: () => (showModal = false),
            });
        }
    }

    function deleteFinishing(item: FinishingItem) {
        if (item.orders_count > 0) {
            alert(`Finishing "${item.name}" pernah digunakan pada ${item.orders_count} transaksi cetak. Untuk menjaga integritas data riwayat nota, Anda disarankan menonaktifkan statusnya daripada menghapus.`);
            return;
        }

        if (confirm(`Hapus opsi finishing "${item.name}"?`)) {
            router.delete(`/finishings/${item.id}`);
        }
    }
</script>

<AppHead title="Kelola Finishing Cetak - Light Project POS" />

<div class="flex flex-col gap-6 p-4 md:p-6 max-w-5xl mx-auto w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <Scissors class="size-6 text-primary" />
                Finishing Cetak
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Kelola daftar opsi finishing cetak beserta tarif/harga tambahannya (Mata Ayam, Selongsong, Laminasi Glossy/Doff, Potong Pas).
            </p>
        </div>
        <Button class="bg-primary font-semibold whitespace-nowrap inline-flex items-center" onclick={openCreate}>
            <Plus class="size-4 mr-1.5" />
            Tambah Finishing
        </Button>
    </div>

    <!-- Reusable DataTable -->
    <DataTable
        {columns}
        data={finishings}
        defaultSortKey="price"
        defaultSortDir="asc"
        searchPlaceholder="Cari nama finishing cetak..."
        emptyMessage="Belum ada data opsi finishing cetak."
    >
        {#snippet rowSnippet({ item, isVisible })}
            <TableRow class="hover:bg-muted/30 transition-colors">
                {#if isVisible('name')}
                    <TableCell class="font-bold text-xs text-foreground">
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full {item.price > 0 ? 'bg-primary' : 'bg-muted-foreground/50'}"></span>
                            <span>{item.name}</span>
                        </div>
                    </TableCell>
                {/if}
                {#if isVisible('price')}
                    <TableCell class="text-xs text-right font-semibold">
                        {#if item.price > 0}
                            <span class="text-primary font-bold">+{formatRupiah(item.price)}</span>
                        {:else}
                            <span class="text-muted-foreground font-medium">Rp 0 (Gratis)</span>
                        {/if}
                    </TableCell>
                {/if}
                {#if isVisible('description')}
                    <TableCell class="text-xs text-muted-foreground max-w-xs truncate">
                        {item.description || '-'}
                    </TableCell>
                {/if}
                {#if isVisible('usage')}
                    <TableCell class="text-xs text-center">
                        {#if item.orders_count > 0}
                            <Badge variant="secondary" class="text-[11px] font-semibold">
                                {item.orders_count} Transaksi
                            </Badge>
                        {:else}
                            <span class="text-xs text-muted-foreground italic">Belum terpakai</span>
                        {/if}
                    </TableCell>
                {/if}
                {#if isVisible('is_active')}
                    <TableCell class="text-xs text-center">
                        {#if item.is_active}
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
                            <Button size="sm" variant="ghost" class="h-7 w-7 p-0" onclick={() => openEdit(item)} title="Ubah Finishing">
                                <Edit2 class="size-3.5 text-muted-foreground" />
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="h-7 w-7 p-0 text-destructive hover:bg-destructive/10"
                                onclick={() => deleteFinishing(item)}
                                title={item.orders_count > 0 ? 'Finishing pernah digunakan dalam transaksi' : 'Hapus Finishing'}
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

<!-- Modal Tambah/Edit Finishing -->
<Dialog bind:open={showModal}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Scissors class="size-5 text-primary" />
                {editingFinishing ? 'Ubah Finishing Cetak' : 'Tambah Finishing Cetak'}
            </DialogTitle>
            <DialogDescription>
                Tentukan nama finishing dan biaya tambahannya yang otomatis dihitung saat kasir memilih finishing ini di POS.
            </DialogDescription>
        </DialogHeader>

        <form onsubmit={(e) => { e.preventDefault(); saveFinishing(); }} class="space-y-4 py-2 text-xs">
            <div class="space-y-1.5">
                <Label for="fin_name" class="font-semibold">Nama Finishing <span class="text-destructive">*</span></Label>
                <Input
                    id="fin_name"
                    bind:value={formName}
                    placeholder="Contoh: Mata Ayam 4 Sudut, Selongsong, Laminasi Glossy"
                    required
                />
            </div>

            <div class="space-y-1.5">
                <Label for="fin_price" class="font-semibold">Biaya Tambahan (Rp) <span class="text-destructive">*</span></Label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-xs text-muted-foreground font-semibold">Rp</span>
                    <Input
                        id="fin_price"
                        type="number"
                        min="0"
                        step="500"
                        bind:value={formPrice}
                        class="pl-9 font-semibold"
                        placeholder="0"
                        required
                    />
                </div>
                <p class="text-[11px] text-muted-foreground">
                    Biaya tambahan akan langsung dijumlahkan ke subtotal item pesanan saat dipilih. Masukkan 0 jika gratis.
                </p>
            </div>

            <div class="space-y-1.5">
                <Label for="fin_desc" class="font-semibold">Keterangan (Opsional)</Label>
                <Textarea
                    id="fin_desc"
                    bind:value={formDescription}
                    rows={2}
                    placeholder="Keterangan spesifikasi finishing..."
                />
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input
                    type="checkbox"
                    id="fin_active"
                    bind:checked={formIsActive}
                    class="h-4 w-4 rounded border-input text-primary focus:ring-primary accent-primary"
                />
                <Label for="fin_active" class="font-medium cursor-pointer">
                    Finishing Aktif (Dapat dipilih di kasir POS)
                </Label>
            </div>

            <DialogFooter class="pt-4">
                <Button type="button" variant="outline" onclick={() => (showModal = false)}>
                    Batal
                </Button>
                <Button type="submit" class="bg-primary font-semibold">
                    Simpan Finishing
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>

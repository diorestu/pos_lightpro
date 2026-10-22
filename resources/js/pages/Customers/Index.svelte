<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Master Percetakan',
                href: '#',
            },
            {
                title: 'Pelanggan',
                href: '/customers',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Edit2 from '@lucide/svelte/icons/edit-2';
    import Plus from '@lucide/svelte/icons/plus';
    import Search from '@lucide/svelte/icons/search';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import Users from '@lucide/svelte/icons/users';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { DataTable, type ColumnDef, TableCell, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';

    interface CustomerItem {
        id: number;
        name: string;
        phone: string | null;
        email: string | null;
        address: string | null;
        customer_type: 'regular' | 'member' | 'reseller' | 'corporate';
        discount_percent: number;
        total_spent: number;
        orders_count: number;
    }

    let {
        customers = { data: [], links: [] },
        filters = {},
    }: {
        customers: any;
        filters: Record<string, string>;
    } = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let typeFilter = $state(filters.customer_type || '');
    // svelte-ignore state_referenced_locally
    let currentPerPage = $state(filters.per_page ? parseInt(filters.per_page, 10) : 15);

    $effect(() => {
        search = filters.search || '';
        typeFilter = filters.customer_type || '';
        currentPerPage = filters.per_page ? parseInt(filters.per_page, 10) : 15;
    });

    const columns: ColumnDef[] = [
        { key: 'name', label: 'Nama Pelanggan', sortable: true },
        { key: 'phone', label: 'Telepon / WhatsApp', sortable: true },
        { key: 'customer_type', label: 'Tipe Pelanggan', sortable: true },
        { key: 'discount_percent', label: 'Diskon Otomatis', align: 'center', sortable: true },
        { key: 'total_spent', label: 'Total Transaksi', align: 'right', sortable: true },
        { key: 'orders_count', label: 'Jumlah Order', align: 'center', sortable: true },
        { key: 'actions', label: 'Aksi', align: 'right', sortable: false, hideable: false },
    ];

    // Modal state
    let showModal = $state(false);
    let editingCustomer = $state<CustomerItem | null>(null);
    let formName = $state('');
    let formPhone = $state('');
    let formEmail = $state('');
    let formAddress = $state('');
    let formType = $state<'regular' | 'member' | 'reseller' | 'corporate'>('regular');
    let formDiscount = $state('0');

    function applyFilter(newSearch?: string) {
        if (newSearch !== undefined) {
            search = newSearch;
        }
        router.get(
            '/customers',
            {
                search: search || undefined,
                customer_type: typeFilter || undefined,
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
        editingCustomer = null;
        formName = '';
        formPhone = '';
        formEmail = '';
        formAddress = '';
        formType = 'regular';
        formDiscount = '0';
        showModal = true;
    }

    function openEdit(c: CustomerItem) {
        editingCustomer = c;
        formName = c.name;
        formPhone = c.phone || '';
        formEmail = c.email || '';
        formAddress = c.address || '';
        formType = c.customer_type;
        formDiscount = c.discount_percent.toString();
        showModal = true;
    }

    function saveCustomer() {
        const payload = {
            name: formName,
            phone: formPhone,
            email: formEmail,
            address: formAddress,
            customer_type: formType,
            discount_percent: parseFloat(formDiscount) || 0,
        };

        if (editingCustomer) {
            router.put(`/customers/${editingCustomer.id}`, payload, {
                onSuccess: () => (showModal = false),
            });
        } else {
            router.post('/customers', payload, {
                onSuccess: () => (showModal = false),
            });
        }
    }

    function deleteCustomer(c: CustomerItem) {
        if (c.orders_count > 0) {
            alert(`Pelanggan "${c.name}" telah memiliki ${c.orders_count} riwayat transaksi.`);
            return;
        }

        if (confirm(`Hapus pelanggan "${c.name}"?`)) {
            router.delete(`/customers/${c.id}`);
        }
    }
</script>

<AppHead title="Master Data Pelanggan" />

<div class="flex flex-col gap-6 p-4 md:p-6 max-w-7xl mx-auto w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <Users class="size-6 text-primary" />
                Data Pelanggan (Customer Master)
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Kelola pelanggan Regular, Member, Reseller Percetakan, dan Instansi/Corporate dengan persentase diskon khusus otomatis. Klik judul kolom untuk mengurutkan (sortable).
            </p>
        </div>
        <Button class="bg-primary font-semibold whitespace-nowrap inline-flex items-center" onclick={openCreate}>
            <Plus class="size-4 mr-1.5" />
            Tambah Pelanggan
        </Button>
    </div>

    <!-- Reusable DataTable with Search, Column Filter, Records Per Page & Pagination in Head Table Card -->
    <DataTable
        {columns}
        data={customers}
        pagination={customers}
        search={search}
        searchPlaceholder="Cari nama, telepon/WA, atau email..."
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
        emptyMessage="Tidak ada data pelanggan ditemukan."
    >
        {#snippet filtersSnippet()}
            <select
                bind:value={typeFilter}
                onchange={() => applyFilter()}
                class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-8 w-full sm:w-48 cursor-pointer font-medium"
            >
                <option value="">Semua Tipe Pelanggan</option>
                <option value="regular">Regular (Umum)</option>
                <option value="member">Member Setia</option>
                <option value="reseller">Reseller Percetakan</option>
                <option value="corporate">Corporate / Instansi</option>
            </select>
        {/snippet}

        {#snippet rowSnippet({ item: c, isVisible })}
            <TableRow class="hover:bg-muted/30 transition-colors">
                {#if isVisible('name')}
                    <TableCell class="text-xs font-semibold text-foreground">
                        {c.name}
                        {#if c.address}
                            <span class="block text-[11px] text-muted-foreground font-normal line-clamp-1">{c.address}</span>
                        {/if}
                    </TableCell>
                {/if}
                {#if isVisible('phone')}
                    <TableCell class="text-xs text-muted-foreground">
                        {c.phone || '-'}
                    </TableCell>
                {/if}
                {#if isVisible('customer_type')}
                    <TableCell class="text-xs">
                        {#if c.customer_type === 'reseller'}
                            <Badge class="bg-indigo-600 hover:bg-indigo-600 text-white text-[10px]">Reseller</Badge>
                        {:else if c.customer_type === 'corporate'}
                            <Badge class="bg-purple-600 hover:bg-purple-600 text-white text-[10px]">Corporate</Badge>
                        {:else if c.customer_type === 'member'}
                            <Badge class="bg-emerald-600 hover:bg-emerald-600 text-white text-[10px]">Member</Badge>
                        {:else}
                            <Badge variant="outline" class="text-[10px]">Regular</Badge>
                        {/if}
                    </TableCell>
                {/if}
                {#if isVisible('discount_percent')}
                    <TableCell class="text-xs text-center font-bold text-emerald-600">
                        {c.discount_percent > 0 ? `${c.discount_percent}%` : '-'}
                    </TableCell>
                {/if}
                {#if isVisible('total_spent')}
                    <TableCell class="text-xs text-right font-semibold text-foreground">
                        {formatRupiah(c.total_spent)}
                    </TableCell>
                {/if}
                {#if isVisible('orders_count')}
                    <TableCell class="text-xs text-center">
                        <Badge variant="secondary" class="font-bold">
                            {c.orders_count} Transaksi
                        </Badge>
                    </TableCell>
                {/if}
                {#if isVisible('actions')}
                    <TableCell class="text-xs text-right">
                        <div class="flex items-center justify-end gap-1">
                            <Button size="sm" variant="ghost" class="h-7 w-7 p-0" onclick={() => openEdit(c)} title="Ubah Data">
                                <Edit2 class="size-3.5 text-muted-foreground" />
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="h-7 w-7 p-0 text-destructive hover:bg-destructive/10"
                                disabled={c.orders_count > 0}
                                onclick={() => deleteCustomer(c)}
                                title={c.orders_count > 0 ? 'Pelanggan memiliki transaksi aktif' : 'Hapus Pelanggan'}
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

<!-- Modal: Tambah/Edit Pelanggan -->
<Dialog open={showModal} onOpenChange={(open) => (showModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Users class="size-5 text-primary" />
                {editingCustomer ? 'Edit Pelanggan' : 'Tambah Pelanggan'}
            </DialogTitle>
            <DialogDescription class="text-xs">
                Tentukan tipe pelanggan dan persentase diskon yang langsung teraplikasikan saat kasir memilih nama pelanggan.
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
            <div class="space-y-1">
                <Label for="cust_name">Nama Pelanggan / Badan Usaha *</Label>
                <Input id="cust_name" bind:value={formName} placeholder="Contoh: Bintang Advertising" />
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <Label for="cust_phone">No. Telepon / WhatsApp</Label>
                    <Input id="cust_phone" bind:value={formPhone} placeholder="08123456789" />
                </div>
                <div class="space-y-1">
                    <Label for="cust_email">Email (Opsional)</Label>
                    <Input id="cust_email" type="email" bind:value={formEmail} placeholder="cs@bintang.com" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <Label for="cust_type">Tipe Pelanggan *</Label>
                    <select
                        id="cust_type"
                        bind:value={formType}
                        onchange={() => {
                            if (formType === 'reseller') formDiscount = '15';
                            else if (formType === 'corporate') formDiscount = '10';
                            else if (formType === 'member') formDiscount = '5';
                            else formDiscount = '0';
                        }}
                        class="w-full h-9 rounded-md border border-input bg-background px-3 pr-8"
                    >
                        <option value="regular">Regular (Umum)</option>
                        <option value="member">Member Setia</option>
                        <option value="reseller">Reseller Percetakan</option>
                        <option value="corporate">Corporate / Instansi</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <Label for="cust_disc">Diskon Khusus (%)</Label>
                    <Input id="cust_disc" type="number" bind:value={formDiscount} placeholder="0" />
                </div>
            </div>

            <div class="space-y-1">
                <Label for="cust_addr">Alamat Lengkap</Label>
                <Textarea id="cust_addr" bind:value={formAddress} placeholder="Alamat pengiriman / workshop..." rows={2} />
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (showModal = false)}>Batal</Button>
            <Button class="bg-primary" onclick={saveCustomer} disabled={!formName}>
                Simpan Pelanggan
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

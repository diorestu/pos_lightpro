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
    import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';
    import { createSortable } from '@/lib/sortable.svelte';

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
        customers: { data: CustomerItem[]; links: any[] };
        filters: Record<string, string>;
    } = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let typeFilter = $state(filters.customer_type || '');

    $effect(() => {
        search = filters.search || '';
        typeFilter = filters.customer_type || '';
    });

    // Sorter
    const sorter = createSortable(() => customers.data, 'name', 'asc');

    // Modal state
    let showModal = $state(false);
    let editingCustomer = $state<CustomerItem | null>(null);
    let formName = $state('');
    let formPhone = $state('');
    let formEmail = $state('');
    let formAddress = $state('');
    let formType = $state<'regular' | 'member' | 'reseller' | 'corporate'>('regular');
    let formDiscount = $state('0');

    function applyFilter() {
        router.get(
            '/customers',
            {
                search: search || undefined,
                customer_type: typeFilter || undefined,
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
        <Button class="bg-primary font-semibold" onclick={openCreate}>
            <Plus class="size-4 mr-1.5" />
            + Tambah Pelanggan
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
                    placeholder="Cari nama, no. telepon/WA, atau email..."
                    class="h-8 pl-8 text-xs"
                />
            </div>
            <select
                bind:value={typeFilter}
                onchange={applyFilter}
                class="h-8 text-xs rounded-md border border-input bg-background px-2.5 pr-8 w-full sm:w-56"
            >
                <option value="">Semua Tipe Pelanggan</option>
                <option value="regular">Regular (Umum)</option>
                <option value="member">Member Setia</option>
                <option value="reseller">Reseller Percetakan</option>
                <option value="corporate">Corporate / Instansi</option>
            </select>
        </CardContent>
    </Card>

    <!-- Table with Sortable Columns -->
    <Card class="border shadow-xs">
        <CardContent class="p-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'name' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('name')}
                        >
                            Nama Pelanggan
                        </TableHead>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'phone' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('phone')}
                        >
                            Telepon / WhatsApp
                        </TableHead>
                        <TableHead
                            class="text-xs"
                            sortable
                            sortDirection={sorter.sortKey === 'customer_type' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('customer_type')}
                        >
                            Tipe Pelanggan
                        </TableHead>
                        <TableHead
                            class="text-xs text-center"
                            sortable
                            sortDirection={sorter.sortKey === 'discount_percent' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('discount_percent')}
                        >
                            Diskon Otomatis
                        </TableHead>
                        <TableHead
                            class="text-xs text-right"
                            sortable
                            sortDirection={sorter.sortKey === 'total_spent' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('total_spent')}
                        >
                            Total Transaksi
                        </TableHead>
                        <TableHead
                            class="text-xs text-center"
                            sortable
                            sortDirection={sorter.sortKey === 'orders_count' ? sorter.sortDirection : null}
                            onSort={() => sorter.toggleSort('orders_count')}
                        >
                            Jumlah Order
                        </TableHead>
                        <TableHead class="text-xs text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {#if sorter.sortedData.length === 0}
                        <TableRow>
                            <TableCell colspan={7} class="text-center py-10 text-muted-foreground text-xs">
                                Tidak ada data pelanggan ditemukan.
                            </TableCell>
                        </TableRow>
                    {:else}
                        {#each sorter.sortedData as c (c.id)}
                            <TableRow>
                                <TableCell class="text-xs font-semibold text-foreground">
                                    {c.name}
                                    {#if c.address}
                                        <span class="block text-[11px] text-muted-foreground font-normal line-clamp-1">{c.address}</span>
                                    {/if}
                                </TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    {c.phone || '-'}
                                </TableCell>
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
                                <TableCell class="text-xs text-center font-bold text-emerald-600">
                                    {c.discount_percent > 0 ? `${c.discount_percent}%` : '-'}
                                </TableCell>
                                <TableCell class="text-xs text-right font-semibold text-foreground">
                                    {formatRupiah(c.total_spent)}
                                </TableCell>
                                <TableCell class="text-xs text-center">
                                    <Badge variant="secondary" class="font-bold">
                                        {c.orders_count} Transaksi
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-xs text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button size="sm" variant="ghost" class="h-7 w-7 p-0" onclick={() => openEdit(c)}>
                                            <Edit2 class="size-3.5 text-muted-foreground" />
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="h-7 w-7 p-0 text-destructive hover:bg-destructive/10"
                                            disabled={c.orders_count > 0}
                                            onclick={() => deleteCustomer(c)}
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

<!-- Modal: Tambah/Edit Pelanggan -->
<Dialog open={showModal} onOpenChange={(open) => (showModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Users class="size-5 text-primary" />
                {editingCustomer ? 'Edit Pelanggan' : 'Tambah Pelanggan Baru'}
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

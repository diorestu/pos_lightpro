<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Operasional',
                href: '#',
            },
            {
                title: 'Shift Kasir',
                href: '/shifts',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AlertCircle from '@lucide/svelte/icons/alert-circle';
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import Clock from '@lucide/svelte/icons/clock';
    import DollarSign from '@lucide/svelte/icons/dollar-sign';
    import Lock from '@lucide/svelte/icons/lock';
    import Plus from '@lucide/svelte/icons/plus';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import UserCheck from '@lucide/svelte/icons/user-check';
    import Users from '@lucide/svelte/icons/users';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { DataTable, type ColumnDef, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
    import { Textarea } from '@/components/ui/textarea';

    interface ShiftItem {
        id: number;
        user_id: number;
        opened_at: string;
        closed_at: string | null;
        starting_cash: number;
        cash_sales: number;
        non_cash_sales: number;
        expected_cash: number;
        closing_cash: number | null;
        difference: number;
        status: 'open' | 'closed';
        notes: string | null;
        user?: { id: number; name: string; role: string };
    }

    interface UserItem {
        id: number;
        name: string;
        email: string;
        role: string;
        phone: string | null;
        is_active: boolean;
    }

    let {
        activeShift = null,
        shifts = { data: [], links: [] },
        users = [],
        filters = {},
    }: {
        activeShift: ShiftItem | null;
        shifts: any;
        users: UserItem[];
        filters?: Record<string, string>;
    } = $props();

    // svelte-ignore state_referenced_locally
    let currentPerPage = $state(filters?.per_page ? parseInt(filters.per_page, 10) : 15);
    $effect(() => {
        currentPerPage = filters?.per_page ? parseInt(filters.per_page, 10) : 15;
    });

    const shiftColumns: ColumnDef[] = [
        { key: 'user_id', label: 'Kasir Bertugas', sortable: false },
        { key: 'opened_at', label: 'Waktu Buka', sortable: true },
        { key: 'closed_at', label: 'Waktu Tutup', sortable: true },
        { key: 'starting_cash', label: 'Modal Awal', align: 'right', sortable: true },
        { key: 'cash_sales', label: 'Penjualan Tunai', align: 'right', sortable: true },
        { key: 'closing_cash', label: 'Uang Fisik Aktual', align: 'right', sortable: true },
        { key: 'difference', label: 'Selisih Kas', align: 'right', sortable: true },
        { key: 'status', label: 'Status', align: 'center', sortable: false },
    ];

    // Modals
    let showOpenModal = $state(false);
    let openStartingCash = $state('200000');
    let openNotes = $state('');

    let showCloseModal = $state(false);
    let closePhysicalCash = $state('');
    let closeNotes = $state('');

    let showAddUserModal = $state(false);
    let newUserName = $state('');
    let newUserEmail = $state('');
    let newUserPassword = $state('');
    let newUserRole = $state<'admin' | 'kasir' | 'manager' | 'desainer'>('kasir');
    let newUserPhone = $state('');

    function formatRupiah(amount: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);
    }

    const calculatedDifference = $derived(
        activeShift && closePhysicalCash !== ''
            ? (parseFloat(closePhysicalCash) || 0) - Number(activeShift.expected_cash)
            : 0
    );

    function submitOpenShift() {
        router.post('/shifts/start', {
            starting_cash: parseFloat(openStartingCash) || 0,
            notes: openNotes,
        }, {
            onSuccess: () => (showOpenModal = false),
        });
    }

    function submitCloseShift() {
        if (!activeShift) return;
        router.post(`/shifts/${activeShift.id}/close`, {
            closing_cash: parseFloat(closePhysicalCash) || 0,
            notes: closeNotes,
        }, {
            onSuccess: () => (showCloseModal = false),
        });
    }

    function submitAddUser() {
        router.post('/shifts/users', {
            name: newUserName,
            email: newUserEmail,
            password: newUserPassword,
            role: newUserRole,
            phone: newUserPhone,
        }, {
            onSuccess: () => {
                showAddUserModal = false;
                newUserName = '';
                newUserEmail = '';
                newUserPassword = '';
            },
        });
    }

    function quickSwitchUser(userId: number) {
        router.post('/shifts/switch-user', { user_id: userId });
    }
</script>

<AppHead title="Manajemen Shift Kasir & Role Karyawan" />

<div class="flex flex-col gap-6 p-4 md:p-6 max-w-7xl mx-auto w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <Clock class="size-6 text-primary" />
                Shift Kasir & Multi-Person Role
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Pengelolaan pergantian shift kasir, rekonsiliasi kas fisik di laci (selisih), dan pembagian hak akses peran karyawan.
            </p>
        </div>

        <div class="flex items-center gap-2">
            {#if !activeShift}
                <Button class="bg-primary font-semibold whitespace-nowrap inline-flex items-center" onclick={() => (showOpenModal = true)}>
                    <Clock class="size-4 mr-1.5" />
                    Buka Shift Kasir
                </Button>
            {:else}
                <Button variant="destructive" class="font-semibold whitespace-nowrap inline-flex items-center" onclick={() => {
                    closePhysicalCash = activeShift ? activeShift.expected_cash.toString() : '';
                    showCloseModal = true;
                }}>
                    <Lock class="size-4 mr-1.5" />
                    Tutup Shift Kasir Ini
                </Button>
            {/if}
        </div>
    </div>

    <!-- Active Shift Status Card -->
    {#if activeShift}
        <Card class="border-2 border-emerald-500/30 bg-emerald-50/20 dark:bg-emerald-950/10 shadow-xs">
            <CardHeader class="pb-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                        <div>
                            <CardTitle class="text-base font-bold text-foreground">
                                Shift Aktif Berjalan: {activeShift.user?.name}
                            </CardTitle>
                            <CardDescription class="text-xs">
                                Dibuka pada {new Date(activeShift.opened_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                            </CardDescription>
                        </div>
                    </div>
                    <Badge class="bg-emerald-600 hover:bg-emerald-600 text-white text-xs w-fit">
                        Status: SEDANG AKTIF
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div class="p-3 bg-background rounded-lg border">
                        <span class="text-muted-foreground block text-[11px]">Modal Kas Awal</span>
                        <span class="text-base font-bold text-foreground">{formatRupiah(activeShift.starting_cash)}</span>
                    </div>
                    <div class="p-3 bg-background rounded-lg border">
                        <span class="text-muted-foreground block text-[11px]">Penerimaan Tunai (Cash)</span>
                        <span class="text-base font-bold text-emerald-600">{formatRupiah(activeShift.cash_sales)}</span>
                    </div>
                    <div class="p-3 bg-background rounded-lg border">
                        <span class="text-muted-foreground block text-[11px]">Non-Tunai (QRIS / Transfer)</span>
                        <span class="text-base font-bold text-blue-600">{formatRupiah(activeShift.non_cash_sales)}</span>
                    </div>
                    <div class="p-3 bg-background rounded-lg border border-primary/40">
                        <span class="text-muted-foreground block text-[11px] font-semibold text-primary">Kas Sistem (Harus Ada di Laci)</span>
                        <span class="text-base font-bold text-primary">{formatRupiah(activeShift.expected_cash)}</span>
                    </div>
                </div>

                {#if activeShift.notes}
                    <div class="mt-3 text-xs text-muted-foreground bg-background p-2.5 rounded-lg border">
                        <strong>Catatan Buka Shift:</strong> {activeShift.notes}
                    </div>
                {/if}
            </CardContent>
        </Card>
    {:else}
        <Card class="border border-dashed border-amber-300 bg-amber-50/40 dark:border-amber-800 dark:bg-amber-950/20">
            <CardContent class="p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <AlertCircle class="size-8 text-amber-600 dark:text-amber-400 shrink-0" />
                    <div>
                        <h3 class="font-bold text-sm text-foreground">Tidak Ada Shift Kasir yang Sedang Terbuka</h3>
                        <p class="text-xs text-muted-foreground">
                            Anda harus membuka shift kasir terlebih dahulu untuk mencatat modal awal kas dan mengaktifkan fitur transaksi di menu Kasir POS.
                        </p>
                    </div>
                </div>
                <Button class="bg-primary font-semibold shrink-0 whitespace-nowrap inline-flex items-center" onclick={() => (showOpenModal = true)}>
                    <Clock class="size-4 mr-1.5" />
                    Buka Shift Sekarang
                </Button>
            </CardContent>
        </Card>
    {/if}

    <!-- Cashier Switcher & Role Permission List -->
    <Card class="border shadow-xs">
        <CardHeader class="pb-3 flex flex-row items-center justify-between">
            <div>
                <CardTitle class="text-sm font-semibold flex items-center gap-2">
                    <Users class="size-4 text-primary" />
                    Daftar Karyawan & Cepat Beralih Akun (Multi-Person Kasir)
                </CardTitle>
                <CardDescription class="text-xs">
                    Pilih akun petugas kasir yang sedang bertugas di meja kasir.
                </CardDescription>
            </div>
            <Button size="sm" variant="outline" class="text-xs whitespace-nowrap inline-flex items-center" onclick={() => (showAddUserModal = true)}>
                <Plus class="size-3.5 mr-1" />
                Tambah Karyawan
            </Button>
        </CardHeader>
        <CardContent class="p-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="text-xs">Nama Petugas</TableHead>
                        <TableHead class="text-xs">Email Login</TableHead>
                        <TableHead class="text-xs">Peran / Role</TableHead>
                        <TableHead class="text-xs">Hak Akses Menu</TableHead>
                        <TableHead class="text-xs">Status</TableHead>
                        <TableHead class="text-xs text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {#each users as u}
                        <TableRow>
                            <TableCell class="text-xs font-semibold text-foreground flex items-center gap-2">
                                <span class="size-7 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center text-[10px]">
                                    {u.name.substring(0, 2).toUpperCase()}
                                </span>
                                {u.name}
                            </TableCell>
                            <TableCell class="text-xs text-muted-foreground">{u.email}</TableCell>
                            <TableCell class="text-xs">
                                {#if u.role === 'admin'}
                                    <Badge class="bg-red-600 hover:bg-red-600 text-white text-[10px]">Administrator</Badge>
                                {:else if u.role === 'manager'}
                                    <Badge class="bg-purple-600 hover:bg-purple-600 text-white text-[10px]">Manager</Badge>
                                {:else if u.role === 'kasir'}
                                    <Badge class="bg-emerald-600 hover:bg-emerald-600 text-white text-[10px]">Kasir / POS</Badge>
                                {:else}
                                    <Badge variant="outline" class="text-[10px]">Desainer / Operator</Badge>
                                {/if}
                            </TableCell>
                            <TableCell class="text-xs text-muted-foreground">
                                {#if u.role === 'admin'}
                                    Semua Menu & Konfigurasi Sistem
                                {:else if u.role === 'manager'}
                                    Laporan Keuangan, Shift, & Audit Stok
                                {:else if u.role === 'kasir'}
                                    POS Kasir, Pelanggan, & Riwayat Nota
                                {:else}
                                    Antrian Cetak & Status Produksi
                                {/if}
                            </TableCell>
                            <TableCell class="text-xs">
                                <Badge variant="outline" class="bg-emerald-50 text-emerald-700 text-[10px]">Aktif</Badge>
                            </TableCell>
                            <TableCell class="text-xs text-right">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-7 px-2.5 text-xs font-semibold"
                                    onclick={() => quickSwitchUser(u.id)}
                                >
                                    <UserCheck class="size-3 mr-1 text-primary" />
                                    Ganti Petugas Ini
                                </Button>
                            </TableCell>
                        </TableRow>
                    {/each}
                </TableBody>
            </Table>
        </CardContent>
    </Card>

    <!-- Shift History Reusable DataTable -->
    <div class="space-y-2">
        <div class="px-1">
            <h3 class="text-sm font-semibold text-foreground">Riwayat Sesi Shift Kasir</h3>
            <p class="text-xs text-muted-foreground">Catatan rekonsiliasi kas masuk, kas keluar, dan selisih uang fisik per shift kasir.</p>
        </div>

        <DataTable
            columns={shiftColumns}
            data={shifts}
            pagination={shifts}
            searchPlaceholder="Cari riwayat shift..."
            perPage={currentPerPage}
            onPerPageChange={(val) => {
                currentPerPage = val;
                router.get('/shifts', { per_page: val }, { preserveState: true });
            }}
            onPageChange={(_, url) => {
                if (url) router.get(url, {}, { preserveState: true });
            }}
            defaultSortKey="opened_at"
            defaultSortDir="desc"
            emptyMessage="Belum ada riwayat shift kasir tercatat."
        >
            {#snippet rowSnippet({ item: s, isVisible })}
                <TableRow class="hover:bg-muted/30 transition-colors">
                    {#if isVisible('user_id')}
                        <TableCell class="text-xs font-semibold">{s.user?.name || '-'}</TableCell>
                    {/if}
                    {#if isVisible('opened_at')}
                        <TableCell class="text-xs text-muted-foreground whitespace-nowrap">
                            {new Date(s.opened_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}
                        </TableCell>
                    {/if}
                    {#if isVisible('closed_at')}
                        <TableCell class="text-xs text-muted-foreground whitespace-nowrap">
                            {s.closed_at ? new Date(s.closed_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '-'}
                        </TableCell>
                    {/if}
                    {#if isVisible('starting_cash')}
                        <TableCell class="text-xs text-right">{formatRupiah(s.starting_cash)}</TableCell>
                    {/if}
                    {#if isVisible('cash_sales')}
                        <TableCell class="text-xs text-right text-emerald-600 font-semibold">{formatRupiah(s.cash_sales)}</TableCell>
                    {/if}
                    {#if isVisible('closing_cash')}
                        <TableCell class="text-xs text-right font-bold">
                            {s.closing_cash !== null ? formatRupiah(s.closing_cash) : '-'}
                        </TableCell>
                    {/if}
                    {#if isVisible('difference')}
                        <TableCell class="text-xs text-right font-semibold">
                            {#if s.status === 'open'}
                                <span class="text-muted-foreground">-</span>
                            {:else if s.difference == 0}
                                <span class="text-emerald-600">Pas (0)</span>
                            {:else if s.difference > 0}
                                <span class="text-blue-600 font-bold">+{formatRupiah(s.difference)}</span>
                            {:else}
                                <span class="text-destructive font-bold">{formatRupiah(s.difference)}</span>
                            {/if}
                        </TableCell>
                    {/if}
                    {#if isVisible('status')}
                        <TableCell class="text-xs text-center">
                            {#if s.status === 'open'}
                                <Badge class="bg-emerald-600 hover:bg-emerald-600 text-white text-[10px]">Terbuka</Badge>
                            {:else}
                                <Badge variant="outline" class="text-[10px]">Ditutup</Badge>
                            {/if}
                        </TableCell>
                    {/if}
                </TableRow>
            {/snippet}
        </DataTable>
    </div>
</div>

<!-- Modal: Buka Shift Kasir -->
<Dialog open={showOpenModal} onOpenChange={(open) => (showOpenModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <Clock class="size-5 text-primary" />
                Buka Shift Kasir
            </DialogTitle>
            <DialogDescription class="text-xs">
                Masukkan uang kas kecil awal di laci kasir untuk uang kembalian.
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
            <div class="space-y-1">
                <Label for="sc_cash">Modal Awal Kas (Rp) *</Label>
                <Input id="sc_cash" type="number" bind:value={openStartingCash} class="text-base font-bold" />
            </div>
            <div class="space-y-1">
                <Label for="sc_note">Catatan Awal Shift</Label>
                <Textarea id="sc_note" bind:value={openNotes} placeholder="Pecahan uang kembalian, kondisi laci..." rows={2} />
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (showOpenModal = false)}>Batal</Button>
            <Button class="bg-primary" onclick={submitOpenShift} disabled={!openStartingCash}>
                Buka Shift Sekarang
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Modal: Tutup Shift Kasir -->
<Dialog open={showCloseModal} onOpenChange={(open) => (showCloseModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2 text-destructive">
                <Lock class="size-5" />
                Tutup Shift Kasir & Rekonsiliasi Kas
            </DialogTitle>
            <DialogDescription class="text-xs">
                Hitung uang fisik yang ada di laci kasir secara aktual untuk mengecek selisih penerimaan.
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
            <div class="bg-muted/40 p-3 rounded-lg border space-y-1 text-xs">
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Modal Awal:</span>
                    <span>{formatRupiah(activeShift?.starting_cash || 0)}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Penjualan Tunai:</span>
                    <span class="text-emerald-600">+{formatRupiah(activeShift?.cash_sales || 0)}</span>
                </div>
                <div class="flex justify-between font-bold border-t pt-1">
                    <span>Kas Sistem (Harus Ada):</span>
                    <span class="text-primary">{formatRupiah(activeShift?.expected_cash || 0)}</span>
                </div>
            </div>

            <div class="space-y-1">
                <Label for="phys_cash">Uang Fisik Aktual di Laci Kasir (Rp) *</Label>
                <Input
                    id="phys_cash"
                    type="number"
                    bind:value={closePhysicalCash}
                    class="text-base font-bold"
                />
            </div>

            <div class="p-2.5 rounded-lg border {calculatedDifference === 0 ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : calculatedDifference > 0 ? 'bg-blue-50 text-blue-800 border-blue-200' : 'bg-red-50 text-red-800 border-red-200'}">
                <div class="flex justify-between items-center text-xs font-bold">
                    <span>Selisih Kas:</span>
                    <span class="text-sm">
                        {calculatedDifference === 0 ? 'PAS (Tidak Ada Selisih)' : calculatedDifference > 0 ? `LEBIH +${formatRupiah(calculatedDifference)}` : `KURANG -${formatRupiah(Math.abs(calculatedDifference))}`}
                    </span>
                </div>
            </div>

            <div class="space-y-1">
                <Label for="c_notes">Catatan Serah Terima / Penjelasan Selisih</Label>
                <Textarea id="c_notes" bind:value={closeNotes} placeholder="Catatan untuk shift berikutnya..." rows={2} />
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (showCloseModal = false)}>Batal</Button>
            <Button variant="destructive" onclick={submitCloseShift} disabled={!closePhysicalCash}>
                Tutup Shift & Simpan
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Modal: Tambah Karyawan Baru -->
<Dialog open={showAddUserModal} onOpenChange={(open) => (showAddUserModal = open)}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
                <ShieldCheck class="size-5 text-primary" />
                Tambah Karyawan & Role
            </DialogTitle>
            <DialogDescription class="text-xs">
                Buat akun petugas kasir, operator cetak, atau manajer baru.
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-xs">
            <div class="space-y-1">
                <Label for="u_name">Nama Lengkap *</Label>
                <Input id="u_name" bind:value={newUserName} placeholder="Nama karyawan..." />
            </div>
            <div class="space-y-1">
                <Label for="u_email">Email Login *</Label>
                <Input id="u_email" type="email" bind:value={newUserEmail} placeholder="kasir@lightprint.id" />
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <Label for="u_pass">Password *</Label>
                    <Input id="u_pass" type="password" bind:value={newUserPassword} placeholder="Minimal 6 karakter" />
                </div>
                <div class="space-y-1">
                    <Label for="u_role">Peran / Role *</Label>
                    <select id="u_role" bind:value={newUserRole} class="w-full h-9 rounded-md border border-input bg-background px-2.5 pr-8">
                        <option value="kasir">Kasir (POS & Transaksi)</option>
                        <option value="manager">Manager (Laporan & Audit)</option>
                        <option value="desainer">Operator / Desainer</option>
                        <option value="admin">Administrator Penuh</option>
                    </select>
                </div>
            </div>
            <div class="space-y-1">
                <Label for="u_phone">No. Telepon / WA</Label>
                <Input id="u_phone" bind:value={newUserPhone} placeholder="08123456789" />
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (showAddUserModal = false)}>Batal</Button>
            <Button class="bg-primary" onclick={submitAddUser} disabled={!newUserName || !newUserEmail || !newUserPassword}>
                Simpan Karyawan
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

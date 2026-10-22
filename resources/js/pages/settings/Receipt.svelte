<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Pengaturan',
                href: '#',
            },
            {
                title: 'Pengaturan Nota',
                href: '/settings/receipt',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Check from '@lucide/svelte/icons/check';
    import Eye from '@lucide/svelte/icons/eye';
    import Printer from '@lucide/svelte/icons/printer';
    import Save from '@lucide/svelte/icons/save';
    import Usb from '@lucide/svelte/icons/usb';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Textarea } from '@/components/ui/textarea';
    import { toast } from 'svelte-sonner';
    import { printDirectWebUSB } from '@/lib/thermal-printer';

    interface ReceiptConfig {
        id?: number;
        store_name: string;
        tagline: string | null;
        address: string | null;
        phone: string | null;
        email: string | null;
        website: string | null;
        footer_text: string | null;
        paper_size: '58mm' | '80mm' | 'A5';
        show_logo: boolean;
        show_cashier_name: boolean;
        show_customer_info: boolean;
    }

    let {
        settings = {
            store_name: 'LightPrint Digital Printing',
            tagline: 'Solusi Cetak Cepat, Tajam & Terpercaya',
            address: 'Jl. Percetakan Raya No. 88, Jakarta Pusat',
            phone: '0812-3456-7890',
            email: 'sales@lightprint.id',
            website: 'www.lightprint.id',
            footer_text: "1. Harap cek kembali hasil cetakan sebelum meninggalkan counter.\n2. Komplain dilayani maksimal 1x24 jam dengan membawa nota fisik.\n3. File desain disimpan selama 14 hari kerja.",
            paper_size: '80mm',
            show_logo: true,
            show_cashier_name: true,
            show_customer_info: true,
        },
    }: {
        settings: ReceiptConfig;
    } = $props();

    // svelte-ignore state_referenced_locally
    let storeName = $state(settings.store_name || '');
    // svelte-ignore state_referenced_locally
    let tagline = $state(settings.tagline || '');
    // svelte-ignore state_referenced_locally
    let address = $state(settings.address || '');
    // svelte-ignore state_referenced_locally
    let phone = $state(settings.phone || '');
    // svelte-ignore state_referenced_locally
    let email = $state(settings.email || '');
    // svelte-ignore state_referenced_locally
    let website = $state(settings.website || '');
    // svelte-ignore state_referenced_locally
    let footerText = $state(settings.footer_text || '');
    // svelte-ignore state_referenced_locally
    let paperSize = $state<'58mm' | '80mm' | 'A5'>(settings.paper_size || '80mm');
    // svelte-ignore state_referenced_locally
    let showLogo = $state(settings.show_logo ?? true);
    // svelte-ignore state_referenced_locally
    let showCashierName = $state(settings.show_cashier_name ?? true);
    // svelte-ignore state_referenced_locally
    let showCustomerInfo = $state(settings.show_customer_info ?? true);
    let isSaving = $state(false);
    let isTestingUSB = $state(false);

    $effect(() => {
        storeName = settings.store_name || '';
        tagline = settings.tagline || '';
        address = settings.address || '';
        phone = settings.phone || '';
        email = settings.email || '';
        website = settings.website || '';
        footerText = settings.footer_text || '';
        paperSize = settings.paper_size || '80mm';
        showLogo = settings.show_logo ?? true;
        showCashierName = settings.show_cashier_name ?? true;
        showCustomerInfo = settings.show_customer_info ?? true;
    });

    function saveSettings() {
        isSaving = true;
        router.put(
            '/settings/receipt',
            {
                store_name: storeName,
                tagline: tagline,
                address: address,
                phone: phone,
                email: email,
                website: website,
                footer_text: footerText,
                paper_size: paperSize,
                show_logo: showLogo,
                show_cashier_name: showCashierName,
                show_customer_info: showCustomerInfo,
            },
            {
                onFinish: () => (isSaving = false),
            }
        );
    }

    const sampleTestOrder = {
        invoice_number: 'TEST-80MM-001',
        created_at: new Date().toISOString(),
        user: { name: 'Siti Kasir' },
        customer_name: 'Berkah Advertising (VIP)',
        customer_phone: '081234567890',
        order_status: 'completed',
        payment_status: 'paid',
        payment_method: 'cash',
        subtotal: 150000,
        discount_amount: 15000,
        tax_amount: 0,
        grand_total: 135000,
        paid_amount: 150000,
        change_amount: 15000,
        items: [
            {
                product_name: 'Spanduk Banner Flexi 280g',
                pricing_type: 'dimension_sqm',
                length: 3,
                width: 1,
                area: 3,
                quantity: 1,
                unit_price: 25000,
                finishing_option: 'Mata Ayam 4 Sudut',
                subtotal: 75000,
            },
            {
                product_name: 'Stiker Vinyl A3+ Kiss Cut',
                pricing_type: 'fixed',
                unit: 'lembar',
                quantity: 5,
                unit_price: 15000,
                finishing_option: 'Kiss Cut',
                subtotal: 75000,
            },
        ],
        payments: [
            {
                term_name: 'Pembayaran Lunas',
                amount: 135000,
                paid_amount: 135000,
                status: 'paid',
                payment_method: 'cash',
            },
        ],
    };

    async function handleTestUSB() {
        isTestingUSB = true;
        try {
            const currentSettings = {
                store_name: storeName,
                tagline: tagline,
                address: address,
                phone: phone,
                email: email,
                website: website,
                footer_text: footerText,
                paper_size: paperSize,
                show_logo: showLogo,
                show_cashier_name: showCashierName,
                show_customer_info: showCustomerInfo,
            };
            const res = await printDirectWebUSB(sampleTestOrder, currentSettings);
            if (res.success) {
                toast.success(res.message);
            } else {
                toast.error(res.message);
            }
        } catch (err: any) {
            toast.error(err?.message || 'Gagal mengirim data tes ke printer USB.');
        } finally {
            isTestingUSB = false;
        }
    }
</script>

<AppHead title="Pengaturan Nota Percetakan" />

<div class="flex flex-col gap-6 p-4 md:p-6 max-w-6xl mx-auto w-full">
    <!-- Header -->
    <div class="border-b pb-4">
        <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
            <Printer class="size-6 text-primary" />
            Pengaturan Nota Percetakan
        </h1>
        <p class="text-sm text-muted-foreground mt-0.5">
            Sesuaikan identitas percetakan, ukuran kertas printer thermal (58mm/80mm), dan syarat ketentuan cetak pada nota kasir.
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Settings Form (7 cols) -->
        <Card class="lg:col-span-7 border shadow-xs">
            <CardHeader class="pb-3">
                <CardTitle class="text-base font-semibold">Identitas & Format Nota</CardTitle>
                <CardDescription class="text-xs">Data ini akan tercetak langsung pada kertas struk belanja pelanggan</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <Label for="s_name">Nama Toko Percetakan *</Label>
                        <Input id="s_name" bind:value={storeName} placeholder="LightPrint Digital Printing" />
                    </div>
                    <div class="space-y-1">
                        <Label for="s_tag">Slogan / Tagline</Label>
                        <Input id="s_tag" bind:value={tagline} placeholder="Cepat, Tajam, & Terpercaya" />
                    </div>
                </div>

                <div class="space-y-1">
                    <Label for="s_addr">Alamat Lengkap Workshop</Label>
                    <Textarea id="s_addr" bind:value={address} placeholder="Jl. Percetakan Raya No. 88, Jakarta Pusat" rows={2} />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="space-y-1">
                        <Label for="s_phone">No. Telp / WhatsApp</Label>
                        <Input id="s_phone" bind:value={phone} placeholder="0812-3456-7890" />
                    </div>
                    <div class="space-y-1">
                        <Label for="s_mail">Email Toko</Label>
                        <Input id="s_mail" bind:value={email} placeholder="order@lightprint.id" />
                    </div>
                    <div class="space-y-1">
                        <Label for="s_web">Website Toko</Label>
                        <Input id="s_web" bind:value={website} placeholder="www.lightprint.id" />
                    </div>
                </div>

                <!-- Paper Size Selector -->
                <div class="space-y-1.5 pt-2 border-t">
                    <div class="flex items-center justify-between">
                        <Label class="text-xs font-semibold">Format Ukuran Kertas Nota</Label>
                        <Badge variant="outline" class="text-[10px] bg-emerald-50 text-emerald-700 border-emerald-200">
                            Standar USB: 80mm
                        </Badge>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            type="button"
                            onclick={() => (paperSize = '80mm')}
                            class="p-2.5 rounded-lg border text-left transition-colors relative {paperSize === '80mm' ? 'border-primary bg-primary/5 text-primary font-bold ring-1 ring-primary' : 'bg-background hover:bg-muted text-muted-foreground'}"
                        >
                            <span class="block text-xs font-bold text-foreground">80mm Thermal USB</span>
                            <span class="text-[10px] block opacity-80 mt-0.5">Printer Kasir POS Standar (Rekomendasi)</span>
                        </button>
                        <button
                            type="button"
                            onclick={() => (paperSize = '58mm')}
                            class="p-2.5 rounded-lg border text-left transition-colors {paperSize === '58mm' ? 'border-primary bg-primary/5 text-primary font-bold ring-1 ring-primary' : 'bg-background hover:bg-muted text-muted-foreground'}"
                        >
                            <span class="block text-xs font-bold text-foreground">58mm Thermal</span>
                            <span class="text-[10px] block opacity-80 mt-0.5">Printer Mini Portable / Bluetooth</span>
                        </button>
                        <button
                            type="button"
                            onclick={() => (paperSize = 'A5')}
                            class="p-2.5 rounded-lg border text-left transition-colors {paperSize === 'A5' ? 'border-primary bg-primary/5 text-primary font-bold ring-1 ring-primary' : 'bg-background hover:bg-muted text-muted-foreground'}"
                        >
                            <span class="block text-xs font-bold text-foreground">Format A5 / Invoice</span>
                            <span class="text-[10px] block opacity-80 mt-0.5">Faktur Cetak Surat Jalan</span>
                        </button>
                    </div>
                </div>

                <!-- Toggles -->
                <div class="space-y-2 pt-2 border-t">
                    <Label class="text-xs font-semibold">Opsi Tampilan Informasi</Label>
                    <div class="space-y-1.5">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" bind:checked={showLogo} class="size-4 rounded border-input" />
                            <span>Tampilkan Header Judul Percetakan</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" bind:checked={showCashierName} class="size-4 rounded border-input" />
                            <span>Tampilkan Nama Kasir yang Bertugas</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" bind:checked={showCustomerInfo} class="size-4 rounded border-input" />
                            <span>Tampilkan Rincian Data Pelanggan</span>
                        </label>
                    </div>
                </div>

                <!-- Terms and Footer -->
                <div class="space-y-1 pt-2 border-t">
                    <Label for="s_foot">Catatan Kaki & Syarat Ketentuan Nota (Footer Terms)</Label>
                    <Textarea
                        id="s_foot"
                        bind:value={footerText}
                        placeholder="1. Cek hasil cetak sebelum meninggalkan counter..."
                        rows={4}
                    />
                    <span class="text-[10px] text-muted-foreground">
                        Teks ini akan tercetak di bagian bawah setiap nota transaksi.
                    </span>
                </div>

                <div class="pt-3 border-t flex justify-end">
                    <Button class="bg-primary font-semibold" onclick={saveSettings} disabled={isSaving || !storeName}>
                        <Save class="size-4 mr-1.5" />
                        {isSaving ? 'Menyimpan...' : 'Simpan Pengaturan Nota'}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Live Receipt Preview (5 cols) -->
        <Card class="lg:col-span-5 border shadow-xs bg-muted/20">
            <CardHeader class="pb-2">
                <CardTitle class="text-sm font-semibold flex items-center gap-1.5">
                    <Eye class="size-4 text-primary" />
                    Preview Live Struk Nota ({paperSize})
                </CardTitle>
                <CardDescription class="text-xs">
                    Simulasi tampilan hasil cetak printer thermal fisik
                </CardDescription>
            </CardHeader>
            <CardContent>
                <!-- Thermal paper simulation -->
                <div class="mx-auto bg-white text-black p-4 rounded shadow-md border border-neutral-300 text-[11px] {paperSize === '58mm' ? 'max-w-[270px]' : paperSize === '80mm' ? 'max-w-[340px]' : 'max-w-full'}">
                    {#if showLogo}
                        <div class="text-center pb-2 border-b border-dashed border-neutral-400">
                            <h3 class="font-bold text-sm uppercase tracking-tight">{storeName || 'Nama Toko'}</h3>
                            {#if tagline}
                                <p class="text-[9px] text-neutral-600">{tagline}</p>
                            {/if}
                            {#if address}
                                <p class="text-[9px] text-neutral-600">{address}</p>
                            {/if}
                            {#if phone}
                                <p class="text-[9px] text-neutral-600">WA: {phone}</p>
                            {/if}
                        </div>
                    {/if}

                    <div class="py-2 border-b border-dashed border-neutral-400 space-y-0.5 text-[10px]">
                        <div class="flex justify-between">
                            <span>No: INV-20261019-0001</span>
                            <span>19/10/2026 14:30</span>
                        </div>
                        {#if showCashierName}
                            <div>Kasir: Siti Kasir (Shift Pagi)</div>
                        {/if}
                        {#if showCustomerInfo}
                            <div>Pelanggan: Berkah Adv (Reseller)</div>
                        {/if}
                    </div>

                    <!-- Items -->
                    <div class="py-2 border-b border-dashed border-neutral-400 space-y-1.5 text-[10px]">
                        <div>
                            <div class="font-bold">Spanduk Flexi 280g</div>
                            <div class="text-[9px] text-neutral-600">3.00m × 1.00m (3 m²) × 1 pcs</div>
                            <div class="text-[9px] text-neutral-600">Finishing: Mata Ayam 4 Sudut</div>
                            <div class="text-right font-bold">Rp 75.000</div>
                        </div>
                        <div>
                            <div class="font-bold">Stiker Vinyl A3+ Kiss Cut</div>
                            <div class="text-[9px] text-neutral-600">5 lembar @ Rp 15.000</div>
                            <div class="text-right font-bold">Rp 75.000</div>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="py-2 border-b border-dashed border-neutral-400 space-y-0.5 text-[10px]">
                        <div class="flex justify-between">
                            <span>Subtotal:</span>
                            <span>Rp 150.000</span>
                        </div>
                        <div class="flex justify-between text-neutral-600">
                            <span>Diskon Reseller (15%):</span>
                            <span>-Rp 22.500</span>
                        </div>
                        <div class="flex justify-between font-bold text-xs pt-1 border-t border-black">
                            <span>TOTAL:</span>
                            <span>Rp 127.500</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Bayar (TUNAI):</span>
                            <span>Rp 150.000</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Kembalian:</span>
                            <span>Rp 22.500</span>
                        </div>
                    </div>

                    <!-- Footer note -->
                    {#if footerText}
                        <div class="pt-2 text-center text-[8px] whitespace-pre-line text-neutral-600 leading-tight">
                            {footerText}
                        </div>
                    {/if}
                </div>

                <!-- Test Print Section -->
                <div class="mt-4 pt-3 border-t flex flex-col sm:flex-row items-center justify-between gap-2.5">
                    <div class="text-[11px] text-muted-foreground">
                        <span class="font-semibold text-foreground">Tes Perangkat:</span>
                        Uji coba printer thermal USB dengan format kertas 80mm
                    </div>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <Button
                            size="sm"
                            class="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold text-xs flex-1 sm:flex-none shadow-xs"
                            disabled={isTestingUSB}
                            onclick={handleTestUSB}
                        >
                            <Usb class="size-3.5 mr-1.5" />
                            {isTestingUSB ? 'Mengirim...' : 'Tes Cetak USB (80mm)'}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</div>

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
    import ImageIcon from '@lucide/svelte/icons/image';
    import Printer from '@lucide/svelte/icons/printer';
    import Save from '@lucide/svelte/icons/save';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import Upload from '@lucide/svelte/icons/upload';
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
        logo_path?: string | null;
        logo_url?: string | null;
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
            logo_path: null,
            logo_url: null,
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
    
    // Logo management states
    // svelte-ignore state_referenced_locally
    let currentLogoUrl = $state(settings.logo_url || null);
    let logoFile = $state<File | null>(null);
    let logoPreviewUrl = $state<string | null>(null);
    let removeLogo = $state(false);

    const activeLogoDisplay = $derived(removeLogo ? null : (logoPreviewUrl || currentLogoUrl));

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
        currentLogoUrl = settings.logo_url || null;
        logoFile = null;
        logoPreviewUrl = null;
        removeLogo = false;
    });

    function handleFileSelect(e: Event) {
        const input = e.target as HTMLInputElement;
        if (input.files && input.files[0]) {
            const file = input.files[0];
            if (file.size > 2 * 1024 * 1024) {
                toast.error('Ukuran logo maksimal 2MB.');
                return;
            }
            logoFile = file;
            logoPreviewUrl = URL.createObjectURL(file);
            removeLogo = false;
        }
    }

    function handleRemoveLogo() {
        logoFile = null;
        logoPreviewUrl = null;
        currentLogoUrl = null;
        removeLogo = true;
    }

    function saveSettings() {
        isSaving = true;

        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('store_name', storeName);
        formData.append('tagline', tagline || '');
        formData.append('address', address || '');
        formData.append('phone', phone || '');
        formData.append('email', email || '');
        formData.append('website', website || '');
        formData.append('footer_text', footerText || '');
        formData.append('paper_size', paperSize);
        formData.append('show_logo', showLogo ? '1' : '0');
        formData.append('show_cashier_name', showCashierName ? '1' : '0');
        formData.append('show_customer_info', showCustomerInfo ? '1' : '0');

        if (removeLogo) {
            formData.append('remove_logo', '1');
        } else if (logoFile) {
            formData.append('logo', logoFile);
        }

        router.post('/settings/receipt', formData, {
            onFinish: () => (isSaving = false),
        });
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
                logo_url: activeLogoDisplay,
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

    function handleTestBrowserPrint() {
        const receiptEl = document.getElementById('test-thermal-receipt');
        if (!receiptEl) {
            window.print();
            return;
        }

        // Buka popup window bersih terisolasi khusus nota 80mm
        const popup = window.open('', '_blank', 'width=380,height=600,top=100,left=100');
        if (!popup) {
            // Jika popup diblokir oleh browser, fallback ke window.print()
            window.print();
            return;
        }

        popup.document.open();
        popup.document.write(`
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="utf-8">
                <title>Tes Cetak Nota - ${storeName || 'LightPrint'}</title>
                <style>
                    @page { size: 80mm auto; margin: 0; }
                    * {
                        box-sizing: border-box;
                        border-width: 0;
                        border-style: solid;
                        border-color: transparent;
                    }
                    html, body {
                        margin: 0;
                        padding: 0;
                        background: #ffffff;
                        color: #000000;
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    }
                    p, h1, h2, h3, h4, h5, h6 {
                        margin: 0 !important;
                        padding: 0 !important;
                        line-height: 1.25 !important;
                    }
                    .header-store p {
                        margin-top: 1.5px !important;
                        margin-bottom: 0 !important;
                        line-height: 1.2 !important;
                    }
                    .header-store h2, .header-store h3 {
                        margin: 0 0 2px 0 !important;
                        line-height: 1.25 !important;
                    }
                    .thermal-receipt-80mm {
                        display: block !important;
                        width: 72mm !important;
                        max-width: 72mm !important;
                        min-width: 72mm !important;
                        margin: 0 auto !important;
                        padding: 2mm 1mm !important;
                        font-size: 11px !important;
                        color: #000000 !important;
                        border: none !important;
                        outline: none !important;
                    }
                    .thermal-receipt-80mm * {
                        box-sizing: border-box;
                    }
                    .receipt-logo {
                        display: block;
                        max-height: 54px;
                        max-width: 180px;
                        margin: 0 auto 6px auto;
                        object-fit: contain;
                        filter: grayscale(100%) contrast(160%);
                    }
                    .border-b {
                        border-bottom: 1px solid #000000 !important;
                    }
                    .border-t {
                        border-top: 1px solid #000000 !important;
                    }
                    .border-solid {
                        border-style: solid !important;
                    }
                    .border-dashed {
                        border-style: dashed !important;
                    }
                    .border-dotted {
                        border-style: dotted !important;
                    }
                    .border-black {
                        border-color: #000000 !important;
                    }
                    .text-center { text-align: center; }
                    .text-right { text-align: right; }
                    .text-left { text-align: left; }
                    .font-bold { font-weight: 700; }
                    .font-semibold { font-weight: 600; }
                    .font-medium { font-weight: 500; }
                    .font-normal { font-weight: 400; }
                    .uppercase { text-transform: uppercase; }
                    .flex { display: flex; }
                    .justify-between { justify-content: space-between; }
                    .justify-end { justify-content: flex-end; }
                    .items-baseline { align-items: baseline; }
                    .gap-1 { gap: 4px; }
                    .grid { display: grid; }
                    .grid-cols-\[75px_10px_1fr\] { grid-template-columns: 75px 10px 1fr; }
                    .grid-cols-\[1fr_60px_28px_65px\] { grid-template-columns: 1fr 60px 28px 65px; }
                    .gap-y-0\.5 { row-gap: 2px; }
                    .space-y-0\.5 > * + * { margin-top: 2px; }
                    .space-y-1 > * + * { margin-top: 4px; }
                    .py-1 { padding-top: 4px; padding-bottom: 4px; }
                    .py-1\.5 { padding-top: 6px; padding-bottom: 6px; }
                    .py-2 { padding-top: 8px; padding-bottom: 8px; }
                    .pb-1 { padding-bottom: 4px; }
                    .pb-2 { padding-bottom: 8px; }
                    .pt-1 { padding-top: 4px; }
                    .pt-2 { padding-top: 8px; }
                    .pt-3 { padding-top: 12px; }
                    .pb-4 { padding-bottom: 16px; }
                    .pb-6 { padding-bottom: 24px; }
                    .mt-1 { margin-top: 4px; }
                    .mt-2 { margin-top: 8px; }
                    .mt-3 { margin-top: 12px; }
                    .mb-1\.5 { margin-bottom: 6px; }
                    .pl-2 { padding-left: 8px; }
                    .pr-1 { padding-right: 4px; }
                    .w-\[170px\] { width: 170px; }
                    .text-\[9px\] { font-size: 9px; }
                    .text-\[10px\] { font-size: 10px; }
                    .text-\[11px\] { font-size: 11px; }
                    .text-xs { font-size: 12px; }
                    .text-sm { font-size: 14px; }
                    .tracking-wide { letter-spacing: 0.025em; }
                    .tracking-tight { letter-spacing: -0.025em; }
                    .whitespace-pre-line { white-space: pre-line; }
                    .leading-tight { line-height: 1.25; }
                </style>
            </head>
            <body>
                <div class="thermal-receipt-80mm">
                    ${receiptEl.innerHTML}
                </div>
                <script>
                    window.addEventListener('load', () => {
                        window.focus();
                        window.print();
                        setTimeout(() => window.close(), 500);
                    });
                <\/script>
            </body>
            </html>
        `);
        popup.document.close();
    }
</script>

<AppHead title="Pengaturan Nota Percetakan" />

<!-- Print-Only Test Receipt Thermal 80mm View (Matching WhatsApp Image Format) -->
<div id="test-thermal-receipt" class="thermal-receipt-80mm text-black font-sans text-xs">
    <!-- Store Header (Logo & Identitas Percetakan di Bawah Logo) -->
    {#if (showLogo && activeLogoDisplay) || storeName}
        <div class="header-store text-center pb-2 border-b border-black">
            {#if showLogo && activeLogoDisplay}
                <div class="flex justify-center mb-1.5">
                    <img
                        src={activeLogoDisplay}
                        alt="Logo Toko"
                        class="receipt-logo max-h-14 max-w-[160px] object-contain filter grayscale contrast-125 mx-auto block"
                    />
                </div>
            {/if}
            <h2 class="font-bold text-sm tracking-tight uppercase leading-tight m-0">{storeName || 'LightPrint Percetakan'}</h2>
            {#if tagline}
                <p class="text-[10px] text-neutral-800 leading-tight m-0">{tagline}</p>
            {/if}
            {#if address}
                <p class="text-[10px] text-neutral-800 leading-tight m-0">{address}</p>
            {/if}
            {#if phone}
                <p class="text-[10px] font-medium text-neutral-800 leading-tight m-0">Telp/WA: {phone}</p>
            {/if}
            {#if website}
                <p class="text-[9px] text-neutral-700 leading-tight m-0">{website}</p>
            {/if}
        </div>
    {/if}

    <!-- Metadata Contoh (Format persis gambar WhatsApp) -->
    <div class="py-2 text-[11px] border-b border-black">
        <div class="grid grid-cols-[75px_10px_1fr] gap-y-0.5 leading-tight">
            <span>Kode Order</span>
            <span>:</span>
            <span class="font-medium">M261011207</span>

            <span>Tanggal</span>
            <span>:</span>
            <span>10-10-2026 15:28</span>

            <span>Deadline</span>
            <span>:</span>
            <span>12-10-2026</span>

            {#if showCustomerInfo}
                <span>Kepada</span>
                <span>:</span>
                <span class="font-bold">Farid</span>
            {/if}

            {#if showCashierName}
                <span>Kasir Online</span>
                <span>:</span>
                <span class="uppercase">REVA TRI RAHMANIA</span>
            {/if}
        </div>
    </div>

    <!-- Items Table Header -->
    <div class="pt-2">
        <div class="grid grid-cols-[1fr_60px_28px_65px] text-[11px] font-bold pb-1 border-b border-black">
            <span>Produk</span>
            <span class="text-right">Harga</span>
            <span class="text-center">Qty</span>
            <span class="text-right">Total</span>
        </div>

        <!-- Item 1 -->
        <div class="py-1.5 border-b border-black text-[10px]">
            <div class="grid grid-cols-[1fr_60px_28px_65px] leading-tight">
                <span class="font-bold pr-1">Indoor Stiker Vinyl Latex</span>
                <span class="text-right">18.000</span>
                <span class="text-center">1</span>
                <span class="text-right font-medium">18.000</span>
            </div>
            <div class="mt-1 space-y-0.5 text-[9px] text-neutral-800 leading-tight">
                <div>- Ukuran P: 90 cm x L: 30 cm</div>
                <div>- Lebar Bahan P: 100 cm x L: 30 cm</div>
                <div>- Potong Pas Gambar</div>
                <div>- Nama File: Farid</div>
                <div>- Deadline: 12-10-2026 18:00:00</div>
            </div>
        </div>

        <!-- Item 2 -->
        <div class="py-1.5 border-b border-black text-[10px]">
            <div class="grid grid-cols-[1fr_60px_28px_65px] leading-tight">
                <span class="font-bold pr-1">Laminasi Cold (120 mic)</span>
                <span class="text-right">10.800</span>
                <span class="text-center">1</span>
                <span class="text-right font-medium">10.800</span>
            </div>
            <div class="mt-1 space-y-0.5 text-[9px] text-neutral-800 leading-tight">
                <div>- Ukuran P: 90 cm x L: 30 cm</div>
                <div>- Lebar Bahan P: 120 cm x L: 30 cm</div>
                <div>- Laminasi Glossy</div>
                <div>- Deadline: 12-10-2026 18:00:00</div>
            </div>
        </div>

        <!-- Item 3 -->
        <div class="py-1.5 border-b border-black text-[10px]">
            <div class="grid grid-cols-[1fr_60px_28px_65px] leading-tight">
                <span class="font-bold pr-1">Cutting Large Format</span>
                <span class="text-right">10.800</span>
                <span class="text-center">1</span>
                <span class="text-right font-medium">10.800</span>
            </div>
            <div class="mt-1 space-y-0.5 text-[9px] text-neutral-800 leading-tight">
                <div>- Ukuran P: 90 cm x L: 30 cm</div>
                <div>- Deadline: 12-10-2026 18:00:00</div>
                <div>- CUTTING SETENGAH PUTUS</div>
            </div>
        </div>
    </div>

    <!-- Totals Summary Section -->
    <div class="pt-2 text-[11px]">
        <div class="flex justify-end">
            <div class="w-[170px] space-y-0.5 text-right">
                <div class="flex justify-between font-bold">
                    <span>Total :</span>
                    <span>39.600</span>
                </div>
                <div class="flex justify-between">
                    <span>Bayar :</span>
                    <span>0</span>
                </div>
            </div>
        </div>

        <!-- Status LUNAS -->
        <div class="mt-3 text-xs flex items-baseline gap-1">
            <span class="font-normal">Keterangan : </span>
            <span class="font-bold text-sm tracking-wide">LUNAS</span>
        </div>
    </div>

    <!-- Footer Terms -->
    {#if footerText}
        <div class="pt-3 text-center text-[9px] whitespace-pre-line text-neutral-700 leading-tight">
            {footerText}
        </div>
    {/if}

    <div class="pt-3 pb-1 text-center text-[9px] font-bold">
        *** TEST PRINTING 80MM ***
    </div>
</div>

<div class="flex flex-col gap-6 p-4 md:p-6 max-w-6xl mx-auto w-full print:hidden">
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
                <!-- Upload Logo Toko Percetakan -->
                <div class="space-y-1.5 p-3 rounded-lg border bg-muted/20">
                    <div class="flex items-center justify-between">
                        <Label class="text-xs font-semibold flex items-center gap-1.5">
                            <ImageIcon class="size-4 text-primary" />
                            Logo Percetakan (Struk Thermal)
                        </Label>
                        {#if activeLogoDisplay}
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-6 px-2 text-[11px] text-destructive hover:bg-destructive/10"
                                onclick={handleRemoveLogo}
                            >
                                <Trash2 class="size-3 mr-1" />
                                Hapus Logo
                            </Button>
                        {/if}
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-3 pt-1">
                        {#if activeLogoDisplay}
                            <div class="p-2 border rounded-md bg-white flex items-center justify-center shrink-0 w-28 h-16 shadow-xs">
                                <img
                                    src={activeLogoDisplay}
                                    alt="Preview Logo"
                                    class="max-h-12 max-w-[100px] object-contain filter grayscale contrast-125"
                                />
                            </div>
                        {:else}
                            <div class="border-2 border-dashed border-muted-foreground/30 rounded-md w-28 h-16 flex flex-col items-center justify-center shrink-0 text-muted-foreground bg-muted/10">
                                <ImageIcon class="size-5 opacity-50 mb-0.5" />
                                <span class="text-[9px]">Tanpa Logo</span>
                            </div>
                        {/if}
                        <div class="flex-1 space-y-1 w-full">
                            <input
                                type="file"
                                id="receipt_logo"
                                accept="image/png,image/jpeg,image/jpg,image/webp"
                                onchange={handleFileSelect}
                                class="block w-full text-xs text-muted-foreground file:mr-2 file:py-1 file:px-2.5 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-primary-foreground hover:file:bg-primary/90 cursor-pointer"
                            />
                            <p class="text-[10px] text-muted-foreground leading-tight">
                                Format PNG/JPG/WebP maks 2MB. Logo otomatis dikonversi ke raster monokrom 1-bit untuk printer thermal 80mm.
                            </p>
                        </div>
                    </div>
                </div>

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
                            <span>Tampilkan Logo Percetakan di Nota</span>
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
                <div class="mx-auto bg-white text-black p-4 rounded shadow-md border border-neutral-300 text-[11px] {paperSize === '58mm' ? 'max-w-[270px]' : paperSize === '80mm' ? 'max-w-[340px]' : 'max-w-full'} select-none">
                    {#if showLogo && activeLogoDisplay}
                        <div class="text-center pb-2 mb-2 border-b border-black">
                            <div class="flex justify-center">
                                <img
                                    src={activeLogoDisplay}
                                    alt="Logo Toko"
                                    class="max-h-12 max-w-[150px] object-contain filter grayscale contrast-125"
                                />
                            </div>
                        </div>
                    {/if}

                    <!-- Store Header (Nama Toko & Identitas di Bawah Logo) -->
                    <div class="header-store text-center pb-2 border-b border-black">
                        <h3 class="font-bold text-sm uppercase tracking-tight leading-tight m-0">{storeName || 'Nama Toko'}</h3>
                        {#if tagline}
                            <p class="text-[9px] text-neutral-600 leading-tight m-0 mt-0.5">{tagline}</p>
                        {/if}
                        {#if address}
                            <p class="text-[9px] text-neutral-600 leading-tight m-0 mt-0.5">{address}</p>
                        {/if}
                        {#if phone}
                            <p class="text-[9px] text-neutral-600 leading-tight m-0 mt-0.5">Telp/WA: {phone}</p>
                        {/if}
                        {#if website}
                            <p class="text-[9px] text-neutral-600 leading-tight m-0 mt-0.5">{website}</p>
                        {/if}
                    </div>

                    <!-- Metadata Contoh -->
                    <div class="py-2 text-[11px] border-b border-black">
                        <div class="grid grid-cols-[75px_10px_1fr] gap-y-0.5 leading-tight">
                            <span>Kode Order</span>
                            <span>:</span>
                            <span class="font-medium">M261011207</span>

                            <span>Tanggal</span>
                            <span>:</span>
                            <span>10-10-2026 15:28</span>

                            <span>Deadline</span>
                            <span>:</span>
                            <span>12-10-2026</span>

                            {#if showCustomerInfo}
                                <span>Kepada</span>
                                <span>:</span>
                                <span class="font-bold">Farid</span>
                            {/if}

                            {#if showCashierName}
                                <span>Kasir Online</span>
                                <span>:</span>
                                <span class="uppercase">REVA TRI RAHMANIA</span>
                            {/if}
                        </div>
                    </div>

                    <!-- Items Table Header -->
                    <div class="pt-2">
                        <div class="grid grid-cols-[1fr_60px_28px_65px] text-[11px] font-bold pb-1 border-b border-black">
                            <span>Produk</span>
                            <span class="text-right">Harga</span>
                            <span class="text-center">Qty</span>
                            <span class="text-right">Total</span>
                        </div>

                        <!-- Item 1 -->
                        <div class="py-1.5 border-b border-black text-[10px]">
                            <div class="grid grid-cols-[1fr_60px_28px_65px] leading-tight">
                                <span class="font-bold pr-1">Indoor Stiker Vinyl Latex</span>
                                <span class="text-right">18.000</span>
                                <span class="text-center">1</span>
                                <span class="text-right font-medium">18.000</span>
                            </div>
                            <div class="mt-1 space-y-0.5 text-[9px] text-neutral-700 leading-tight">
                                <div>- Ukuran P: 90 cm x L: 30 cm</div>
                                <div>- Lebar Bahan P: 100 cm x L: 30 cm</div>
                                <div>- Potong Pas Gambar</div>
                                <div>- Nama File: Farid</div>
                                <div>- Deadline: 12-10-2026 18:00:00</div>
                            </div>
                        </div>

                        <!-- Item 2 -->
                        <div class="py-1.5 border-b border-black text-[10px]">
                            <div class="grid grid-cols-[1fr_60px_28px_65px] leading-tight">
                                <span class="font-bold pr-1">Laminasi Cold (120 mic)</span>
                                <span class="text-right">10.800</span>
                                <span class="text-center">1</span>
                                <span class="text-right font-medium">10.800</span>
                            </div>
                            <div class="mt-1 space-y-0.5 text-[9px] text-neutral-700 leading-tight">
                                <div>- Ukuran P: 90 cm x L: 30 cm</div>
                                <div>- Lebar Bahan P: 120 cm x L: 30 cm</div>
                                <div>- Laminasi Glossy</div>
                                <div>- Deadline: 12-10-2026 18:00:00</div>
                            </div>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="pt-2 text-[11px]">
                        <div class="flex justify-end">
                            <div class="w-[170px] space-y-0.5 text-right">
                                <div class="flex justify-between font-bold">
                                    <span>Total :</span>
                                    <span>28.800</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Bayar :</span>
                                    <span>0</span>
                                </div>
                            </div>
                        </div>

                        <!-- Status LUNAS -->
                        <div class="mt-3 text-xs flex items-baseline gap-1">
                            <span class="font-normal">Keterangan : </span>
                            <span class="font-bold text-sm tracking-wide">LUNAS</span>
                        </div>
                    </div>

                    <!-- Footer note -->
                    {#if footerText}
                        <div class="pt-3 text-center text-[8px] whitespace-pre-line text-neutral-600 leading-tight">
                            {footerText}
                        </div>
                    {/if}
                </div>

                <!-- Test Print Section -->
                <div class="mt-4 pt-3 border-t space-y-2">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="font-semibold text-foreground">Tes Perangkat:</span>
                        <span class="text-[10px] text-muted-foreground">Format Kertas 80mm</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <Button
                            size="sm"
                            class="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold text-xs shadow-xs flex items-center justify-center"
                            disabled={isTestingUSB}
                            onclick={handleTestUSB}
                        >
                            <Usb class="size-3.5 mr-1.5 shrink-0" />
                            <span>{isTestingUSB ? 'Mengirim Data...' : 'Tes Cetak USB'}</span>
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            class="font-semibold text-xs shadow-xs flex items-center justify-center"
                            onclick={handleTestBrowserPrint}
                        >
                            <Printer class="size-3.5 mr-1.5 shrink-0" />
                            <span>Tes Cetak Browser</span>
                        </Button>
                    </div>
                    <p class="text-[10px] text-muted-foreground text-center">
                        Tes cetak nota kasir (USB atau browser dialog/popup auto-print).
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</div>

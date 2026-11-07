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
                        width: 68mm !important;
                        max-width: 68mm !important;
                        min-width: 68mm !important;
                        margin: 0 auto !important;
                        padding: 2mm 3mm 4mm 2mm !important;
                        font-size: 9.5px !important;
                        line-height: 1.25 !important;
                        color: #000000 !important;
                        border: none !important;
                        outline: none !important;
                    }
                    .thermal-receipt-80mm * {
                        box-sizing: border-box !important;
                        border-left: none !important;
                        border-right: none !important;
                    }
                    .header-store {
                        text-align: center !important;
                        padding-bottom: 5px !important;
                        border-bottom: 1px solid #000000 !important;
                    }
                    .header-store h2, .header-store h3 {
                        font-size: 11.5px !important;
                        font-weight: 700 !important;
                        text-transform: uppercase !important;
                        margin: 0 0 2px 0 !important;
                        line-height: 1.25 !important;
                    }
                    .header-store p {
                        font-size: 8.5px !important;
                        color: #000000 !important;
                        margin: 1px 0 0 0 !important;
                        line-height: 1.2 !important;
                    }
                    .receipt-logo {
                        display: block !important;
                        max-height: 48px !important;
                        max-width: 150px !important;
                        margin: 0 auto 4px auto !important;
                        object-fit: contain !important;
                        filter: grayscale(100%) contrast(160%) !important;
                    }
                    .receipt-meta {
                        padding: 5px 0 !important;
                        border-bottom: 1px solid #000000 !important;
                        font-size: 9px !important;
                    }
                    .receipt-meta-grid {
                        display: grid !important;
                        grid-template-columns: 72px 8px 1fr !important;
                        row-gap: 1.5px !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-table-header {
                        display: grid !important;
                        grid-template-columns: 1fr 56px 24px 62px !important;
                        padding: 5px 0 3px 0 !important;
                        border-bottom: 1px solid #000000 !important;
                        font-size: 9px !important;
                        font-weight: 700 !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-item-row {
                        padding: 4px 0 !important;
                        border-bottom: 1px solid #000000 !important;
                        font-size: 9px !important;
                    }
                    .receipt-item-grid {
                        display: grid !important;
                        grid-template-columns: 1fr 56px 24px 62px !important;
                        align-items: start !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-col-product {
                        font-weight: 700 !important;
                        font-size: 9.5px !important;
                        padding-right: 4px !important;
                        word-break: break-word !important;
                    }
                    .receipt-col-price {
                        text-align: right !important;
                        white-space: nowrap !important;
                    }
                    .receipt-col-qty {
                        text-align: center !important;
                        white-space: nowrap !important;
                    }
                    .receipt-col-total {
                        text-align: right !important;
                        font-weight: 600 !important;
                        white-space: nowrap !important;
                        padding-right: 2px !important;
                    }
                    .receipt-item-bullets {
                        margin-top: 2.5px !important;
                        font-size: 8.5px !important;
                        color: #000000 !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-item-bullets div {
                        margin: 1px 0 !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-order-notes {
                        margin-top: 4px !important;
                        padding: 3px 0 !important;
                        border-top: 1px dashed #000000 !important;
                        font-size: 8.5px !important;
                        color: #000000 !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-totals {
                        padding-top: 5px !important;
                        font-size: 9px !important;
                    }
                    .receipt-totals-box {
                        margin-left: auto !important;
                        width: 165px !important;
                        margin-right: 2px !important;
                    }
                    .receipt-totals-row {
                        display: flex !important;
                        justify-content: space-between !important;
                        margin-bottom: 2px !important;
                        line-height: 1.25 !important;
                    }
                    .receipt-totals-row.grand-total {
                        font-weight: 700 !important;
                        font-size: 10.5px !important;
                    }
                    .receipt-status {
                        margin-top: 7px !important;
                        display: flex !important;
                        align-items: baseline !important;
                        gap: 4px !important;
                        font-size: 9.5px !important;
                    }
                    .receipt-status-badge {
                        font-weight: 700 !important;
                        font-size: 12px !important;
                        letter-spacing: 0.03em !important;
                    }
                    .receipt-footer {
                        padding-top: 7px !important;
                        padding-bottom: 7px !important;
                        text-align: center !important;
                        font-size: 8.5px !important;
                        color: #000000 !important;
                        white-space: pre-line !important;
                        line-height: 1.25 !important;
                    }
                    .border-b { border-bottom: 1px solid #000000 !important; }
                    .border-t { border-top: 1px solid #000000 !important; }
                    .border-black { border-color: #000000 !important; }
                    .text-center { text-align: center; }
                    .text-right { text-align: right; }
                    .font-bold { font-weight: 700; }
                    .uppercase { text-transform: uppercase; }
                    .whitespace-pre-line { white-space: pre-line; }
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
<div id="test-thermal-receipt" class="thermal-receipt-80mm">
    <!-- Store Header (Logo & Identitas Percetakan di Bawah Logo) -->
    {#if (showLogo && activeLogoDisplay) || storeName}
        <div class="header-store">
            {#if showLogo && activeLogoDisplay}
                <div class="flex justify-center mb-1">
                    <img
                        src={activeLogoDisplay}
                        alt="Logo Toko"
                        class="receipt-logo max-h-12 max-w-[150px] object-contain filter grayscale contrast-125 mx-auto block"
                    />
                </div>
            {/if}
            <h2>{storeName || 'LightPrint Percetakan'}</h2>
            {#if tagline}
                <p>{tagline}</p>
            {/if}
            {#if address}
                <p>{address}</p>
            {/if}
            {#if phone}
                <p>Telp/WA: {phone}</p>
            {/if}
            {#if website}
                <p>{website}</p>
            {/if}
        </div>
    {/if}

    <!-- Metadata Contoh (Format persis gambar WhatsApp) -->
    <div class="receipt-meta">
        <div class="receipt-meta-grid">
            <span>Kode Order</span>
            <span>:</span>
            <span class="font-bold">M261011207</span>

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
                <span>REVA TRI RAHMANIA</span>
            {/if}
        </div>
    </div>

    <!-- Items Table Header -->
    <div class="receipt-table-header">
        <span>Produk</span>
        <span class="text-right">Harga</span>
        <span class="text-center">Qty</span>
        <span class="text-right pr-[2px]">Total</span>
    </div>

    <!-- Item 1 -->
    <div class="receipt-item-row">
        <div class="receipt-item-grid">
            <span class="receipt-col-product">Indoor Stiker Vinyl Latex</span>
            <span class="receipt-col-price">18.000</span>
            <span class="receipt-col-qty">1</span>
            <span class="receipt-col-total">18.000</span>
        </div>
        <div class="receipt-item-bullets">
            <div>- Ukuran P: 90 cm x L: 30 cm</div>
            <div>- Lebar Bahan P: 100 cm x L: 30 cm</div>
            <div>- Potong Pas Gambar</div>
            <div>- Nama File: Farid</div>
            <div>- Deadline: 12-10-2026 18:00:00</div>
        </div>
    </div>

    <!-- Item 2 -->
    <div class="receipt-item-row">
        <div class="receipt-item-grid">
            <span class="receipt-col-product">Laminasi Cold (120 mic)</span>
            <span class="receipt-col-price">10.800</span>
            <span class="receipt-col-qty">1</span>
            <span class="receipt-col-total">10.800</span>
        </div>
        <div class="receipt-item-bullets">
            <div>- Ukuran P: 90 cm x L: 30 cm</div>
            <div>- Lebar Bahan P: 120 cm x L: 30 cm</div>
            <div>- Laminasi Glossy</div>
            <div>- Deadline: 12-10-2026 18:00:00</div>
        </div>
    </div>

    <!-- Item 3 -->
    <div class="receipt-item-row">
        <div class="receipt-item-grid">
            <span class="receipt-col-product">Cutting Large Format</span>
            <span class="receipt-col-price">10.800</span>
            <span class="receipt-col-qty">1</span>
            <span class="receipt-col-total">10.800</span>
        </div>
        <div class="receipt-item-bullets">
            <div>- Ukuran P: 90 cm x L: 30 cm</div>
            <div>- Deadline: 12-10-2026 18:00:00</div>
            <div>- CUTTING SETENGAH PUTUS</div>
        </div>
    </div>

    <!-- Totals Summary Section -->
    <div class="receipt-totals">
        <div class="receipt-totals-box">
            <div class="receipt-totals-row receipt-totals-grand">
                <span>Total :</span>
                <span>39.600</span>
            </div>
            <div class="receipt-totals-row">
                <span>Bayar :</span>
                <span>0</span>
            </div>
        </div>

        <!-- Status LUNAS -->
        <div class="receipt-status">
            <span>Keterangan : </span>
            <span class="receipt-status-badge">LUNAS</span>
        </div>
    </div>

    <!-- Footer Terms -->
    {#if footerText}
        <div class="receipt-footer">
            {footerText}
        </div>
    {/if}

    <div class="pt-2 text-center text-[8.5px] font-bold">
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
                <div class="mx-auto bg-white text-black p-3 rounded shadow-md border border-neutral-300 thermal-receipt-80mm {paperSize === '58mm' ? 'max-w-[260px]' : 'max-w-[310px]'} select-none">
                    {#if (showLogo && activeLogoDisplay) || storeName}
                        <div class="header-store">
                            {#if showLogo && activeLogoDisplay}
                                <div class="flex justify-center mb-1">
                                    <img
                                        src={activeLogoDisplay}
                                        alt="Logo Toko"
                                        class="receipt-logo max-h-12 max-w-[150px] object-contain filter grayscale contrast-125 mx-auto block"
                                    />
                                </div>
                            {/if}
                            <h2>{storeName || 'Nama Toko'}</h2>
                            {#if tagline}
                                <p>{tagline}</p>
                            {/if}
                            {#if address}
                                <p>{address}</p>
                            {/if}
                            {#if phone}
                                <p>Telp/WA: {phone}</p>
                            {/if}
                            {#if website}
                                <p>{website}</p>
                            {/if}
                        </div>
                    {/if}

                    <!-- Metadata Contoh -->
                    <div class="receipt-meta">
                        <div class="receipt-meta-grid">
                            <span>Kode Order</span>
                            <span>:</span>
                            <span class="font-bold">M261011207</span>

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
                                <span>REVA TRI RAHMANIA</span>
                            {/if}
                        </div>
                    </div>

                    <!-- Items Table Header -->
                    <div class="receipt-table-header">
                        <span>Produk</span>
                        <span class="text-right">Harga</span>
                        <span class="text-center">Qty</span>
                        <span class="text-right pr-[2px]">Total</span>
                    </div>

                    <!-- Item 1 -->
                    <div class="receipt-item-row">
                        <div class="receipt-item-grid">
                            <span class="receipt-col-product">Indoor Stiker Vinyl Latex</span>
                            <span class="receipt-col-price">18.000</span>
                            <span class="receipt-col-qty">1</span>
                            <span class="receipt-col-total">18.000</span>
                        </div>
                        <div class="receipt-item-bullets">
                            <div>- Ukuran P: 90 cm x L: 30 cm</div>
                            <div>- Lebar Bahan P: 100 cm x L: 30 cm</div>
                            <div>- Potong Pas Gambar</div>
                            <div>- Nama File: Farid</div>
                            <div>- Deadline: 12-10-2026 18:00:00</div>
                        </div>
                    </div>

                    <!-- Item 2 -->
                    <div class="receipt-item-row">
                        <div class="receipt-item-grid">
                            <span class="receipt-col-product">Laminasi Cold (120 mic)</span>
                            <span class="receipt-col-price">10.800</span>
                            <span class="receipt-col-qty">1</span>
                            <span class="receipt-col-total">10.800</span>
                        </div>
                        <div class="receipt-item-bullets">
                            <div>- Ukuran P: 90 cm x L: 30 cm</div>
                            <div>- Lebar Bahan P: 120 cm x L: 30 cm</div>
                            <div>- Laminasi Glossy</div>
                            <div>- Deadline: 12-10-2026 18:00:00</div>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="receipt-totals">
                        <div class="receipt-totals-box">
                            <div class="receipt-totals-row receipt-totals-grand">
                                <span>Total :</span>
                                <span>28.800</span>
                            </div>
                            <div class="receipt-totals-row">
                                <span>Bayar :</span>
                                <span>0</span>
                            </div>
                        </div>

                        <!-- Status LUNAS -->
                        <div class="receipt-status">
                            <span>Keterangan : </span>
                            <span class="receipt-status-badge">LUNAS</span>
                        </div>
                    </div>

                    <!-- Footer note -->
                    {#if footerText}
                        <div class="receipt-footer">
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

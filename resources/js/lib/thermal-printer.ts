/**
 * Thermal Printer USB & ESC/POS 80mm Utility
 * Mendukung pencetakan langsung via WebUSB API dan fallback dialog cetak browser (80mm)
 * Menggunakan font standar (Inter / Sans-Serif)
 */

export interface ReceiptItemData {
    product_name: string;
    pricing_type: string;
    unit?: string;
    length?: number | null;
    width?: number | null;
    area?: number | null;
    quantity: number;
    unit_price: number;
    finishing_option?: string | null;
    subtotal: number;
}

export interface OrderPaymentTermData {
    term_name: string;
    amount: number;
    paid_amount: number;
    status: string;
    payment_method: string;
}

export interface ReceiptOrderData {
    invoice_number: string;
    created_at: string;
    user?: { name: string } | null;
    customer_name?: string | null;
    customer_phone?: string | null;
    order_status: string;
    payment_status: string;
    payment_method: string;
    subtotal: number;
    discount_amount: number;
    tax_amount?: number;
    grand_total: number;
    paid_amount: number;
    change_amount: number;
    items: ReceiptItemData[];
    payments?: OrderPaymentTermData[];
}

export interface ReceiptSettingsData {
    store_name: string;
    tagline?: string | null;
    address?: string | null;
    phone?: string | null;
    email?: string | null;
    website?: string | null;
    footer_text?: string | null;
    paper_size?: '58mm' | '80mm' | 'A5';
    show_logo?: boolean;
    show_cashier_name?: boolean;
    show_customer_info?: boolean;
}

const LINE_WIDTH_80MM = 48; // Standar 48 karakter per baris pada kertas 80mm Font A

function formatRupiah(amount: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount).replace(/\s+/g, ' ');
}

function padLine(left: string, right: string, width = LINE_WIDTH_80MM): string {
    const leftStr = left || '';
    const rightStr = right || '';
    const spaceCount = width - leftStr.length - rightStr.length;
    if (spaceCount <= 0) {
        return leftStr + ' ' + rightStr;
    }
    return leftStr + ' '.repeat(spaceCount) + rightStr;
}

function centerText(text: string, width = LINE_WIDTH_80MM): string {
    const str = text.trim();
    if (str.length >= width) return str;
    const leftPad = Math.floor((width - str.length) / 2);
    return ' '.repeat(leftPad) + str;
}

/**
 * Cek apakah browser mendukung WebUSB API
 */
export function isWebUSBSupported(): boolean {
    return typeof navigator !== 'undefined' && 'usb' in navigator;
}

/**
 * Buat payload byte ESC/POS untuk kertas 80mm
 */
export function buildEscPos80mm(
    order: ReceiptOrderData,
    settings: ReceiptSettingsData
): Uint8Array {
    const encoder = new TextEncoder();
    const bytes: number[] = [];

    // Helper untuk push byte string
    const write = (str: string) => {
        const encoded = encoder.encode(str);
        for (let i = 0; i < encoded.length; i++) {
            bytes.push(encoded[i]);
        }
    };

    // Helper push command
    const cmd = (...cmdBytes: number[]) => {
        bytes.push(...cmdBytes);
    };

    // 1. Inisialisasi printer
    cmd(0x1b, 0x40); // ESC @ (Initialize printer)
    cmd(0x1b, 0x74, 0x00); // ESC t 0 (Character code table: PC437)
    cmd(0x1b, 0x4d, 0x00); // ESC M 0 (Standard Font A - 12x24 dots, 48 cols on 80mm)

    // 2. Header Toko (Center)
    cmd(0x1b, 0x61, 0x01); // Center
    cmd(0x1b, 0x45, 0x01); // Bold ON
    cmd(0x1d, 0x21, 0x11); // Double width & height
    write((settings.store_name || 'LightPrint POS') + '\n');

    cmd(0x1d, 0x21, 0x00); // Normal size
    cmd(0x1b, 0x45, 0x00); // Bold OFF

    if (settings.tagline) {
        write(settings.tagline + '\n');
    }
    if (settings.address) {
        write(settings.address + '\n');
    }
    if (settings.phone) {
        write(`Telp/WA: ${settings.phone}\n`);
    }
    if (settings.website) {
        write(`${settings.website}\n`);
    }

    // Pembatas ganda
    write('='.repeat(LINE_WIDTH_80MM) + '\n');

    // 3. Info Transaksi (Left)
    cmd(0x1b, 0x61, 0x00); // Align Left
    const dateFormatted = new Date(order.created_at).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

    write(padLine(`No. Nota : ${order.invoice_number}`, dateFormatted) + '\n');

    if (settings.show_cashier_name !== false) {
        write(`Kasir    : ${order.user?.name || 'Kasir Percetakan'}\n`);
    }
    if (settings.show_customer_info !== false) {
        const custInfo = order.customer_name || 'Pelanggan Umum';
        const custPhone = order.customer_phone ? ` (${order.customer_phone})` : '';
        write(`Pelanggan: ${custInfo}${custPhone}\n`);
    }

    const payStatusLabel = order.payment_status === 'paid'
        ? 'LUNAS'
        : order.payment_status === 'partial'
        ? 'DP / PARSIAL'
        : 'BELUM LUNAS';
    write(padLine(`Metode   : ${order.payment_method.toUpperCase()}`, `Status: ${payStatusLabel}`) + '\n');

    // Garis pembatas
    write('-'.repeat(LINE_WIDTH_80MM) + '\n');

    // 4. Daftar Item Pesanan
    cmd(0x1b, 0x45, 0x01); // Bold ON
    write(padLine('PRODUK / RINCIAN CETAK', 'SUBTOTAL') + '\n');
    cmd(0x1b, 0x45, 0x00); // Bold OFF
    write('-'.repeat(LINE_WIDTH_80MM) + '\n');

    order.items.forEach((item, idx) => {
        cmd(0x1b, 0x45, 0x01); // Bold ON
        write(`${idx + 1}. ${item.product_name}\n`);
        cmd(0x1b, 0x45, 0x00); // Bold OFF

        let detailText = '';
        if (item.pricing_type === 'dimension_sqm') {
            detailText = `   ${item.length}m x ${item.width}m (${item.area}m2) x ${item.quantity} @ ${formatRupiah(item.unit_price)}`;
        } else {
            detailText = `   ${item.quantity} ${item.unit || 'pcs'} x @ ${formatRupiah(item.unit_price)}`;
        }

        const subtotalStr = formatRupiah(item.subtotal);
        write(padLine(detailText, subtotalStr) + '\n');

        if (item.finishing_option && item.finishing_option !== 'Standar' && item.finishing_option !== 'Tanpa Finishing') {
            write(`   Finishing: ${item.finishing_option}\n`);
        }
    });

    write('-'.repeat(LINE_WIDTH_80MM) + '\n');

    // 5. Total & Pembayaran
    write(padLine('Subtotal:', formatRupiah(order.subtotal)) + '\n');

    if (order.discount_amount > 0) {
        write(padLine('Diskon:', '-' + formatRupiah(order.discount_amount)) + '\n');
    }

    if (order.tax_amount && order.tax_amount > 0) {
        write(padLine('PPN / Pajak:', formatRupiah(order.tax_amount)) + '\n');
    }

    cmd(0x1b, 0x45, 0x01); // Bold ON
    write(padLine('TOTAL TAGIHAN:', formatRupiah(order.grand_total)) + '\n');
    cmd(0x1b, 0x45, 0x00); // Bold OFF

    write(padLine(`Total Bayar (${order.payment_method.toUpperCase()}):`, formatRupiah(order.paid_amount)) + '\n');
    write(padLine('Kembalian:', formatRupiah(order.change_amount)) + '\n');

    const remaining = Math.max(0, order.grand_total - order.paid_amount);
    if (remaining > 0) {
        cmd(0x1b, 0x45, 0x01); // Bold ON
        write(padLine('SISA KEKURANGAN / TEMPO:', formatRupiah(remaining)) + '\n');
        cmd(0x1b, 0x45, 0x00); // Bold OFF
    }

    // 6. Rincian Termin Split Payment (jika ada)
    if (order.payments && order.payments.length > 0) {
        write('-'.repeat(LINE_WIDTH_80MM) + '\n');
        cmd(0x1b, 0x45, 0x01);
        write('RINCIAN TAHAPAN TERMIN / DP:\n');
        cmd(0x1b, 0x45, 0x00);

        order.payments.forEach((p) => {
            const statusLabel = p.status === 'paid' ? `[LUNAS ${p.payment_method?.toUpperCase() || ''}]` : '[TEMPO]';
            const termAmt = p.status === 'paid' ? p.paid_amount : p.amount;
            write(padLine(`  * ${p.term_name} ${statusLabel}`, formatRupiah(termAmt)) + '\n');
        });
    }

    // 7. Footer & Catatan
    write('='.repeat(LINE_WIDTH_80MM) + '\n');
    cmd(0x1b, 0x61, 0x01); // Center

    if (settings.footer_text) {
        const lines = settings.footer_text.split('\n');
        lines.forEach((l) => {
            if (l.trim()) {
                write(l.trim() + '\n');
            }
        });
    } else {
        write('Terima kasih atas pesanan cetak Anda!\n');
        write('Periksa kembali hasil cetakan Anda.\n');
    }

    write('\n*** LIGHTPRINT POS 80MM ***\n');

    // 8. Feed baris & Cut Paper
    write('\n\n\n\n');
    cmd(0x1d, 0x56, 0x41, 0x03); // GS V A 3 (Feed 3 lines & partial cut)

    return new Uint8Array(bytes);
}

/**
 * Cetak langsung via WebUSB ke Thermal Printer 80mm
 */
export async function printDirectWebUSB(
    order: ReceiptOrderData,
    settings: ReceiptSettingsData
): Promise<{ success: boolean; message: string }> {
    if (!isWebUSBSupported()) {
        return {
            success: false,
            message: 'WebUSB API tidak didukung pada browser ini. Silakan gunakan Google Chrome / Microsoft Edge atau gunakan tombol cetak dialog browser.',
        };
    }

    let device: USBDevice | null = null;

    try {
        // Minta akses perangkat USB dari dialog browser
        device = await (navigator as any).usb.requestDevice({
            filters: [], // Tampilkan semua perangkat USB yang terhubung
        });

        if (!device) {
            return { success: false, message: 'Tidak ada printer USB yang dipilih.' };
        }

        await device.open();

        if (device.configuration === null) {
            await device.selectConfiguration(1);
        }

        // Cari interface printer yang memiliki endpoint bulk OUT
        let targetInterfaceNumber = 0;
        let targetEndpointNumber = 1;
        let foundEndpoint = false;

        for (const iface of device.configuration?.interfaces || []) {
            for (const alt of iface.alternates || []) {
                for (const ep of alt.endpoints || []) {
                    if (ep.direction === 'out' && ep.type === 'bulk') {
                        targetInterfaceNumber = iface.interfaceNumber;
                        targetEndpointNumber = ep.endpointNumber;
                        foundEndpoint = true;
                        break;
                    }
                }
                if (foundEndpoint) break;
            }
            if (foundEndpoint) break;
        }

        await device.claimInterface(targetInterfaceNumber);

        // Buat ESC/POS 80mm payload
        const rawData = buildEscPos80mm(order, settings);

        // Kirim data ke endpoint USB
        await device.transferOut(targetEndpointNumber, rawData.buffer);

        // Tutup koneksi
        try {
            await device.releaseInterface(targetInterfaceNumber);
            await device.close();
        } catch {
            // ignore release error
        }

        return {
            success: true,
            message: `Nota berhasil dikirim langsung ke printer USB (${device.productName || 'Thermal 80mm'})!`,
        };
    } catch (err: any) {
        if (device && device.opened) {
            try {
                await device.close();
            } catch {}
        }

        if (err?.name === 'NotFoundError') {
            return { success: false, message: 'Pemilihan printer USB dibatalkan.' };
        }

        if (err?.name === 'SecurityError') {
            return {
                success: false,
                message: 'Izin akses printer USB ditolak atau sistem driver OS mengunci port USB. Anda dapat mencetak melalui dialog cetak browser (80mm).',
            };
        }

        return {
            success: false,
            message: `Gagal mencetak via USB: ${err?.message || 'Periksa koneksi printer USB'}.`,
        };
    }
}

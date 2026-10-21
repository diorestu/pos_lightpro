<?php

namespace Database\Seeders;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\ReceiptSetting;
use App\Models\StockMutation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Users with Roles
        $admin = User::firstOrCreate(
            ['email' => 'admin@lightprint.id'],
            [
                'name' => 'Admin Utama',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '081234567890',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $kasir1 = User::firstOrCreate(
            ['email' => 'kasir@lightprint.id'],
            [
                'name' => 'Siti Kasir',
                'password' => Hash::make('password'),
                'role' => 'kasir',
                'phone' => '081234567891',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $kasir2 = User::firstOrCreate(
            ['email' => 'kasir2@lightprint.id'],
            [
                'name' => 'Budi Kasir',
                'password' => Hash::make('password'),
                'role' => 'kasir',
                'phone' => '081234567892',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $manager = User::firstOrCreate(
            ['email' => 'manager@lightprint.id'],
            [
                'name' => 'Hendro Manager',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'phone' => '081234567893',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $operator = User::firstOrCreate(
            ['email' => 'operator@lightprint.id'],
            [
                'name' => 'Rian Operator Desain',
                'password' => Hash::make('password'),
                'role' => 'desainer',
                'phone' => '081234567894',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // 2. Receipt Settings
        $settings = ReceiptSetting::getActive();

        // 3. Categories
        $catOutdoor = Category::firstOrCreate(['slug' => 'outdoor-printing'], ['name' => 'Outdoor Printing', 'description' => 'Banner, Baliho, Spanduk']);
        $catIndoor = Category::firstOrCreate(['slug' => 'indoor-display'], ['name' => 'Indoor & Display', 'description' => 'X-Banner, Roll Up, Poster Albatros']);
        $catA3 = Category::firstOrCreate(['slug' => 'digital-offset-a3'], ['name' => 'Digital Offset A3+', 'description' => 'Stiker Kiss Cut, Kartu Nama, Brosur']);
        $catMerchandise = Category::firstOrCreate(['slug' => 'merchandise'], ['name' => 'Merchandise & Sablon', 'description' => 'Mug, Kaos, Pin, ID Card']);
        $catFinishing = Category::firstOrCreate(['slug' => 'finishing-jasa'], ['name' => 'Finishing & Jasa', 'description' => 'Desain, Laminating, Potong, Jilid']);

        // 4. Raw Materials (Bahan Baku)
        $rawFlexi280 = RawMaterial::firstOrCreate(
            ['code' => 'RAW-FLX280'],
            [
                'name' => 'Flexi China 280g (Roll)',
                'unit' => 'meter',
                'stock' => 350.00,
                'min_stock' => 50.00,
                'cost_price' => 12500,
                'description' => 'Bahan spanduk outdoor standar ekonomis',
            ]
        );

        $rawFlexi440 = RawMaterial::firstOrCreate(
            ['code' => 'RAW-FLX440'],
            [
                'name' => 'Flexi Korea 440g (Roll)',
                'unit' => 'meter',
                'stock' => 140.00,
                'min_stock' => 30.00,
                'cost_price' => 24000,
                'description' => 'Bahan banner tebal premium outdoor tahan 1-2 tahun',
            ]
        );

        $rawAlbatros = RawMaterial::firstOrCreate(
            ['code' => 'RAW-ALB'],
            [
                'name' => 'Albatros Matte 180g (Roll)',
                'unit' => 'meter',
                'stock' => 75.00,
                'min_stock' => 20.00,
                'cost_price' => 28000,
                'description' => 'Bahan indoor halus untuk X-Banner & Roll Up',
            ]
        );

        $rawStickerVinyl = RawMaterial::firstOrCreate(
            ['code' => 'RAW-STK-VINYL'],
            [
                'name' => 'Stiker Vinyl Ritrama (Roll)',
                'unit' => 'meter',
                'stock' => 85.00,
                'min_stock' => 25.00,
                'cost_price' => 35000,
                'description' => 'Stiker outdoor tahan air dan panas',
            ]
        );

        $rawArtCarton = RawMaterial::firstOrCreate(
            ['code' => 'RAW-AC260'],
            [
                'name' => 'Art Carton 260g Plano',
                'unit' => 'lembar',
                'stock' => 450.00,
                'min_stock' => 100.00,
                'cost_price' => 2500,
                'description' => 'Kertas tebal untuk kartu nama, cover buku, sertifikat',
            ]
        );

        $rawMataAyam = RawMaterial::firstOrCreate(
            ['code' => 'RAW-EYELET'],
            [
                'name' => 'Ring Mata Ayam (Lubang Banner)',
                'unit' => 'pcs',
                'stock' => 4200.00,
                'min_stock' => 500.00,
                'cost_price' => 150,
                'description' => 'Ring besi mata itik pojok spanduk',
            ]
        );

        $rawTinta = RawMaterial::firstOrCreate(
            ['code' => 'RAW-INK-SOLVENT'],
            [
                'name' => 'Tinta Solvent Outdoor CMYK',
                'unit' => 'liter',
                'stock' => 12.00,
                'min_stock' => 5.00,
                'cost_price' => 180000,
                'description' => 'Tinta khusus mesin cetak outdoor Konica',
            ]
        );

        // 5. Products
        $pBanner280 = Product::firstOrCreate(
            ['code' => 'PRD-BAN280'],
            [
                'category_id' => $catOutdoor->id,
                'raw_material_id' => $rawFlexi280->id,
                'name' => 'Spanduk / Banner Flexi 280g',
                'pricing_type' => 'dimension_sqm',
                'unit' => 'm²',
                'base_price' => 25000,
                'min_order' => 1.00,
                'cost_price' => 13500,
                'stock' => 0,
                'is_stock_managed' => false,
                'description' => 'Cetak banner outdoor tahan cuaca, minimal hitung 1 meter persegi.',
                'is_active' => true,
            ]
        );

        $pBanner440 = Product::firstOrCreate(
            ['code' => 'PRD-BAN440'],
            [
                'category_id' => $catOutdoor->id,
                'raw_material_id' => $rawFlexi440->id,
                'name' => 'Spanduk Tebal Flexi Korea 440g',
                'pricing_type' => 'dimension_sqm',
                'unit' => 'm²',
                'base_price' => 45000,
                'min_order' => 1.00,
                'cost_price' => 26000,
                'stock' => 0,
                'is_stock_managed' => false,
                'description' => 'Banner tebal tidak tembus pandang, cocok untuk baliho & plang nama toko.',
                'is_active' => true,
            ]
        );

        $pXBanner = Product::firstOrCreate(
            ['code' => 'PRD-XBAN60'],
            [
                'category_id' => $catIndoor->id,
                'raw_material_id' => $rawAlbatros->id,
                'name' => 'X-Banner 60x160cm (Albatros + Rangka)',
                'pricing_type' => 'fixed',
                'unit' => 'set',
                'base_price' => 85000,
                'min_order' => 1.00,
                'cost_price' => 45000,
                'stock' => 40,
                'is_stock_managed' => true,
                'description' => 'Display standing x-banner rangka fiber hitam lengkap laminasi doff/glossy.',
                'is_active' => true,
            ]
        );

        $pRollUp = Product::firstOrCreate(
            ['code' => 'PRD-ROLLUP85'],
            [
                'category_id' => $catIndoor->id,
                'raw_material_id' => $rawAlbatros->id,
                'name' => 'Roll Up Banner Aluminium 85x200cm',
                'pricing_type' => 'fixed',
                'unit' => 'set',
                'base_price' => 220000,
                'min_order' => 1.00,
                'cost_price' => 125000,
                'stock' => 25,
                'is_stock_managed' => true,
                'description' => 'Stand roll up aluminium kokoh dan praktis ditarik.',
                'is_active' => true,
            ]
        );

        $pStikerA3 = Product::firstOrCreate(
            ['code' => 'PRD-STK-A3'],
            [
                'category_id' => $catA3->id,
                'raw_material_id' => $rawStickerVinyl->id,
                'name' => 'Stiker Vinyl A3+ Kiss Cut (Label Kemasan)',
                'pricing_type' => 'fixed',
                'unit' => 'lembar',
                'base_price' => 15000,
                'min_order' => 5.00,
                'cost_price' => 7000,
                'stock' => 200,
                'is_stock_managed' => true,
                'description' => 'Cetak stiker sudah dipotong sesuai pola (bulat, kotak, oval). Tinggal kelet.',
                'is_active' => true,
            ]
        );

        $pKartuNama = Product::firstOrCreate(
            ['code' => 'PRD-KN-BOX'],
            [
                'category_id' => $catA3->id,
                'raw_material_id' => $rawArtCarton->id,
                'name' => 'Kartu Nama 2 Sisi + Box Mika (100 pcs)',
                'pricing_type' => 'fixed',
                'unit' => 'box',
                'base_price' => 45000,
                'min_order' => 1.00,
                'cost_price' => 18000,
                'stock' => 50,
                'is_stock_managed' => true,
                'description' => 'Art Carton 260g full color 2 sisi termasuk box mika transparan.',
                'is_active' => true,
            ]
        );

        $pMugCustom = Product::firstOrCreate(
            ['code' => 'PRD-MUG-SUB'],
            [
                'category_id' => $catMerchandise->id,
                'name' => 'Mug Keramik Custom Sublim + Dus',
                'pricing_type' => 'fixed',
                'unit' => 'pcs',
                'base_price' => 25000,
                'min_order' => 1.00,
                'cost_price' => 13000,
                'stock' => 60,
                'is_stock_managed' => true,
                'description' => 'Mug keramik putih standar SNI full color tahan cuci.',
                'is_active' => true,
            ]
        );

        $pJasaDesain = Product::firstOrCreate(
            ['code' => 'PRD-DESAIN-STD'],
            [
                'category_id' => $catFinishing->id,
                'name' => 'Jasa Setting / Desain Banner Standar',
                'pricing_type' => 'fixed',
                'unit' => 'file',
                'base_price' => 30000,
                'min_order' => 1.00,
                'cost_price' => 0,
                'stock' => 999,
                'is_stock_managed' => false,
                'description' => 'Jasa desain layout banner, revisi minor 2x.',
                'is_active' => true,
            ]
        );

        // 6. Customers (Pelanggan)
        $custWalkIn = Customer::firstOrCreate(
            ['name' => 'Pelanggan Umum (Walk-in)'],
            [
                'phone' => '-',
                'email' => null,
                'address' => 'Customer Langsung di Toko',
                'customer_type' => 'regular',
                'discount_percent' => 0.00,
                'total_spent' => 250000,
            ]
        );

        $custReseller = Customer::firstOrCreate(
            ['name' => 'Berkah Advertising (Reseller)'],
            [
                'phone' => '085711223344',
                'email' => 'berkah.adv@gmail.com',
                'address' => 'Jl. Kebon Jeruk No. 12, Jakarta Barat',
                'customer_type' => 'reseller',
                'discount_percent' => 15.00,
                'total_spent' => 1850000,
            ]
        );

        $custCorporate = Customer::firstOrCreate(
            ['name' => 'PT Mandiri Solusindo'],
            [
                'phone' => '021-5556789',
                'email' => 'procurement@mandirisolusindo.co.id',
                'address' => 'Gedung Wisma Niaga Lt. 5, Sudirman',
                'customer_type' => 'corporate',
                'discount_percent' => 10.00,
                'total_spent' => 4500000,
            ]
        );

        $custMember = Customer::firstOrCreate(
            ['name' => 'Rina Wijaya'],
            [
                'phone' => '081399887766',
                'email' => 'rina.wijaya@outlook.com',
                'address' => 'Jl. Cempaka Putih Tengah 2 No. 5',
                'customer_type' => 'member',
                'discount_percent' => 5.00,
                'total_spent' => 620000,
            ]
        );

        // 7. Cashier Shift (Active Shift for Siti Kasir)
        $shift = CashierShift::firstOrCreate(
            ['user_id' => $kasir1->id, 'status' => 'open'],
            [
                'opened_at' => now()->startOfDay()->addHours(8),
                'starting_cash' => 250000,
                'cash_sales' => 380000,
                'non_cash_sales' => 450000,
                'expected_cash' => 630000,
                'notes' => 'Shift Pagi Kasir Utama. Uang kembalian pecahan 5rb, 10rb, 20rb lengkap.',
            ]
        );

        // 8. Sample POS Orders
        // Order 1: Spanduk Pecel Lele (Dimension meteran: 4m x 1m = 4m2) + Mata Ayam keliling
        $order1 = Order::firstOrCreate(
            ['invoice_number' => 'INV-' . date('Ymd') . '-0001'],
            [
                'cashier_shift_id' => $shift->id,
                'user_id' => $kasir1->id,
                'customer_id' => $custWalkIn->id,
                'customer_name' => 'Pak Joko Pecel Lele',
                'customer_phone' => '081299881122',
                'subtotal' => 110000,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'grand_total' => 110000,
                'paid_amount' => 110000,
                'change_amount' => 0,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'order_status' => 'completed',
                'notes' => 'File: banner_pecellele_joko.jpg, mata ayam 4 pojok',
            ]
        );

        OrderItem::firstOrCreate(
            ['order_id' => $order1->id, 'product_id' => $pBanner280->id],
            [
                'product_name' => 'Spanduk / Banner Flexi 280g',
                'pricing_type' => 'dimension_sqm',
                'unit' => 'm²',
                'length' => 4.00,
                'width' => 1.00,
                'area' => 4.00,
                'quantity' => 1.00,
                'unit_price' => 25000,
                'finishing_option' => 'Mata Ayam 4 Sudut',
                'finishing_price' => 10000,
                'cost_price' => 54000,
                'subtotal' => 110000,
                'design_note' => 'Spanduk Warung Pecel Lele Lamongan',
            ]
        );

        // Order 2: Reseller order X-Banner & Kartu nama (Transfer / QRIS)
        $order2 = Order::firstOrCreate(
            ['invoice_number' => 'INV-' . date('Ymd') . '-0002'],
            [
                'cashier_shift_id' => $shift->id,
                'user_id' => $kasir1->id,
                'customer_id' => $custReseller->id,
                'customer_name' => $custReseller->name,
                'customer_phone' => $custReseller->phone,
                'subtotal' => 215000,
                'discount_amount' => 32250, // 15% discount
                'tax_amount' => 0,
                'grand_total' => 182750,
                'paid_amount' => 182750,
                'change_amount' => 0,
                'payment_method' => 'qris',
                'payment_status' => 'paid',
                'order_status' => 'processing',
                'notes' => 'Pesanan express untuk event seminar besok pagi.',
            ]
        );

        OrderItem::firstOrCreate(
            ['order_id' => $order2->id, 'product_id' => $pXBanner->id],
            [
                'product_name' => $pXBanner->name,
                'pricing_type' => 'fixed',
                'unit' => 'set',
                'length' => null,
                'width' => null,
                'area' => null,
                'quantity' => 2.00,
                'unit_price' => 85000,
                'finishing_option' => 'Laminasi Doff',
                'finishing_price' => 0,
                'cost_price' => 90000,
                'subtotal' => 170000,
                'design_note' => 'Seminar_Digital_Marketing_2pcs.pdf',
            ]
        );

        OrderItem::firstOrCreate(
            ['order_id' => $order2->id, 'product_id' => $pKartuNama->id],
            [
                'product_name' => $pKartuNama->name,
                'pricing_type' => 'fixed',
                'unit' => 'box',
                'length' => null,
                'width' => null,
                'area' => null,
                'quantity' => 1.00,
                'unit_price' => 45000,
                'finishing_option' => 'Laminasi Glossy',
                'finishing_price' => 0,
                'cost_price' => 18000,
                'subtotal' => 45000,
                'design_note' => 'KartuNama_Dr_Hendrawan.pdf',
            ]
        );

        // Order 3: DP / Tempo order for Corporate Customer
        $order3 = Order::firstOrCreate(
            ['invoice_number' => 'INV-' . date('Ymd') . '-0003'],
            [
                'cashier_shift_id' => $shift->id,
                'user_id' => $kasir1->id,
                'customer_id' => $custCorporate->id,
                'customer_name' => $custCorporate->name,
                'customer_phone' => $custCorporate->phone,
                'subtotal' => 500000,
                'discount_amount' => 50000, // 10%
                'tax_amount' => 0,
                'grand_total' => 450000,
                'paid_amount' => 200000, // DP Rp 200.000
                'change_amount' => 0,
                'payment_method' => 'transfer',
                'payment_status' => 'partial',
                'order_status' => 'pending',
                'due_date' => now()->addDays(7),
                'notes' => 'DP Rp 200.000, sisa Rp 250.000 dilunasi saat serah terima barang.',
            ]
        );

        // 9. Stock Mutations (Laporan Stok In / Out)
        StockMutation::firstOrCreate(
            ['reason' => 'Pengadaan Awal Bahan Baku Percetakan'],
            [
                'item_type' => 'raw_material',
                'item_id' => $rawFlexi280->id,
                'item_name' => $rawFlexi280->name,
                'type' => 'in',
                'quantity' => 350.00,
                'previous_stock' => 0.00,
                'current_stock' => 350.00,
                'reference_type' => 'restock',
                'user_id' => $admin->id,
            ]
        );

        StockMutation::firstOrCreate(
            ['reason' => 'Pemakaian Cetak Order INV-' . date('Ymd') . '-0001'],
            [
                'item_type' => 'raw_material',
                'item_id' => $rawFlexi280->id,
                'item_name' => $rawFlexi280->name,
                'type' => 'out',
                'quantity' => 4.00,
                'previous_stock' => 350.00,
                'current_stock' => 346.00,
                'reference_type' => 'pos_order',
                'user_id' => $kasir1->id,
            ]
        );

        StockMutation::firstOrCreate(
            ['reason' => 'Restock Rangka X-Banner Fiber'],
            [
                'item_type' => 'product',
                'item_id' => $pXBanner->id,
                'item_name' => $pXBanner->name,
                'type' => 'in',
                'quantity' => 40.00,
                'previous_stock' => 0.00,
                'current_stock' => 40.00,
                'reference_type' => 'restock',
                'user_id' => $admin->id,
            ]
        );
    }
}

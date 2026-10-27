<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintingPosTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $kasir;
    protected Category $category;
    protected RawMaterial $rawMaterial;
    protected Product $productSqm;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com',
        ]);

        $this->kasir = User::factory()->create([
            'role' => 'kasir',
            'email' => 'kasir@test.com',
        ]);

        $this->category = Category::create([
            'name' => 'Outdoor Printing',
            'slug' => 'outdoor-printing',
        ]);

        $this->rawMaterial = RawMaterial::create([
            'name' => 'Flexi China 280g',
            'code' => 'RAW-FLX280',
            'unit' => 'meter',
            'stock' => 100,
            'min_stock' => 20,
            'cost_price' => 12000,
        ]);

        $this->productSqm = Product::create([
            'category_id' => $this->category->id,
            'raw_material_id' => $this->rawMaterial->id,
            'name' => 'Spanduk Banner Flexi 280g',
            'code' => 'PRD-BAN280',
            'pricing_type' => 'dimension_sqm',
            'unit' => 'm²',
            'base_price' => 25000,
            'min_order' => 1.0,
            'cost_price' => 12000,
            'stock' => 0,
            'is_stock_managed' => false,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'Berkah Adv',
            'phone' => '08123456789',
            'customer_type' => 'reseller',
            'discount_percent' => 10,
        ]);

        ReceiptSetting::getActive();
    }

    public function test_dashboard_accessible_when_authenticated(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_shift_can_be_opened_and_closed(): void
    {
        // Open Shift
        $response = $this->actingAs($this->kasir)->post(route('shifts.start'), [
            'starting_cash' => 200000,
            'notes' => 'Shift Pagi Kasir 1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cashier_shifts', [
            'user_id' => $this->kasir->id,
            'starting_cash' => 200000,
            'status' => 'open',
        ]);

        $shift = CashierShift::where('user_id', $this->kasir->id)->first();

        // Close Shift
        $closeResponse = $this->actingAs($this->kasir)->post(route('shifts.close', $shift->id), [
            'closing_cash' => 250000,
            'notes' => 'Shift selesai, kas fisik pas',
        ]);

        $closeResponse->assertRedirect();
        $this->assertDatabaseHas('cashier_shifts', [
            'id' => $shift->id,
            'closing_cash' => 250000,
            'status' => 'closed',
        ]);
    }

    public function test_pos_transaction_creates_order_and_reduces_raw_material_stock(): void
    {
        // 1. Open shift first
        $shift = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 200000,
            'expected_cash' => 200000,
            'status' => 'open',
        ]);

        // 2. Submit POS order (Banner 3m x 1m = 3 m² @ Rp 25.000 = Rp 75.000)
        // Customer has 10% discount -> Rp 67.500
        $response = $this->actingAs($this->kasir)->post(route('pos.store'), [
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'items' => [
                [
                    'product_id' => $this->productSqm->id,
                    'product_name' => $this->productSqm->name,
                    'pricing_type' => 'dimension_sqm',
                    'unit' => 'm²',
                    'length' => 3.0,
                    'width' => 1.0,
                    'area' => 3.0,
                    'quantity' => 1,
                    'unit_price' => 25000,
                    'finishing_option' => 'Mata Ayam 4 Sudut',
                    'finishing_price' => 0,
                    'cost_price' => 36000,
                    'subtotal' => 75000,
                    'design_note' => 'spanduk_toko.pdf',
                ],
            ],
            'subtotal' => 75000,
            'discount_amount' => 7500,
            'tax_amount' => 0,
            'grand_total' => 67500,
            'paid_amount' => 100000,
            'change_amount' => 32500,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect();

        // Verify order created
        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'grand_total' => 67500,
            'paid_amount' => 100000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        // Verify raw material stock deducted (100 - 3 = 97)
        $this->assertEquals(97, $this->rawMaterial->fresh()->stock);

        // Verify stock mutation created
        $this->assertDatabaseHas('stock_mutations', [
            'item_id' => $this->rawMaterial->id,
            'type' => 'out',
            'quantity' => 3.0,
        ]);
    }

    public function test_order_can_be_cancelled_and_stock_restored(): void
    {
        $order = Order::create([
            'invoice_number' => 'INV-TEST-0001',
            'user_id' => $this->kasir->id,
            'subtotal' => 50000,
            'grand_total' => 50000,
            'paid_amount' => 50000,
            'payment_method' => 'cash',
            'order_status' => 'completed',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productSqm->id,
            'product_name' => $this->productSqm->name,
            'pricing_type' => 'dimension_sqm',
            'unit' => 'm²',
            'length' => 2.0,
            'width' => 1.0,
            'area' => 2.0,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 50000,
        ]);

        // Pre-set stock to 98
        $this->rawMaterial->update(['stock' => 98]);

        $response = $this->actingAs($this->admin)->post(route('orders.cancel', $order->id), [
            'reason' => 'Pelanggan salah cetak ukuran',
            'restore_stock' => true,
        ]);

        $response->assertRedirect();
        $this->assertEquals('cancelled', $order->fresh()->order_status);
        $this->assertEquals(100, $this->rawMaterial->fresh()->stock); // 98 + 2 restored
    }

    public function test_add_karyawan(): void
    {
        $response = $this->actingAs($this->admin)->post(route('shifts.users.store'), [
            'name' => 'Budi Kasir',
            'email' => 'budi@test.com',
            'password' => 'secret123',
            'role' => 'kasir',
            'phone' => '08123456789',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'budi@test.com',
            'name' => 'Budi Kasir',
            'role' => 'kasir',
        ]);
    }

    public function test_stock_manual_adjustment(): void
    {
        $response = $this->actingAs($this->admin)->post(route('stock-reports.adjust'), [
            'item_type' => 'raw_material',
            'item_id' => $this->rawMaterial->id,
            'type' => 'in',
            'quantity' => 50,
            'reason' => 'Restock Roll dari Supplier',
        ]);

        $response->assertRedirect();
        $this->assertEquals(150, $this->rawMaterial->fresh()->stock);
    }

    public function test_bulk_stock_adjustment_multi_product(): void
    {
        $prod1 = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Roll Up Banner 85x200',
            'code' => 'PRD-RB85',
            'pricing_type' => 'fixed',
            'unit' => 'set',
            'base_price' => 175000,
            'min_order' => 1,
            'stock' => 10,
            'is_stock_managed' => true,
        ]);

        $prod2 = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Mug Keramik Sublim',
            'code' => 'PRD-MUG',
            'pricing_type' => 'fixed',
            'unit' => 'pcs',
            'base_price' => 25000,
            'min_order' => 1,
            'stock' => 50,
            'is_stock_managed' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('products.bulk-stock'), [
            'default_reason' => 'Restock & Koreksi Gudang',
            'items' => [
                [
                    'product_id' => $prod1->id,
                    'type' => 'in',
                    'quantity' => 25,
                    'reason' => 'Pengiriman Supplier Utama',
                ],
                [
                    'product_id' => $prod2->id,
                    'type' => 'out',
                    'quantity' => 15,
                    'reason' => 'Cacat Produksi / Pecah',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Prod 1: 10 + 25 = 35
        $this->assertEquals(35, $prod1->fresh()->stock);
        // Prod 2: 50 - 15 = 35
        $this->assertEquals(35, $prod2->fresh()->stock);

        $this->assertDatabaseHas('stock_mutations', [
            'item_type' => 'product',
            'item_id' => $prod1->id,
            'type' => 'in',
            'quantity' => 25,
            'current_stock' => 35,
            'reason' => 'Pengiriman Supplier Utama',
        ]);

        $this->assertDatabaseHas('stock_mutations', [
            'item_type' => 'product',
            'item_id' => $prod2->id,
            'type' => 'out',
            'quantity' => 15,
            'current_stock' => 35,
            'reason' => 'Cacat Produksi / Pecah',
        ]);
    }

    public function test_bulk_stock_adjustment_stock_reports_opname(): void
    {
        $prod = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Kartu Nama Box',
            'code' => 'PRD-KN-TEST',
            'pricing_type' => 'fixed',
            'unit' => 'box',
            'base_price' => 35000,
            'min_order' => 1,
            'stock' => 20,
            'is_stock_managed' => false,
        ]);

        $response = $this->actingAs($this->admin)->post(route('stock-reports.bulk-adjust'), [
            'default_reason' => 'Stock Opname Akhir Bulan',
            'items' => [
                [
                    'product_id' => $prod->id,
                    'type' => 'adjustment',
                    'quantity' => 45, // Set physical counted stock
                    'reason' => null, // will use default_reason
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertEquals(45, $prod->fresh()->stock);
        $this->assertTrue($prod->fresh()->is_stock_managed);

        $this->assertDatabaseHas('stock_mutations', [
            'item_type' => 'product',
            'item_id' => $prod->id,
            'type' => 'adjustment',
            'current_stock' => 45,
            'reason' => 'Stock Opname Akhir Bulan',
        ]);
    }

    public function test_receipt_settings_can_be_updated(): void
    {
        $response = $this->actingAs($this->admin)->put(route('settings.receipt.update'), [
            'store_name' => 'Percetakan Berkah Abadi',
            'tagline' => 'Cetak Cepat Harga Bersahabat',
            'address' => 'Jl. Percetakan No. 1',
            'phone' => '0899123456',
            'email' => 'info@berkah.id',
            'paper_size' => '80mm',
            'show_logo' => true,
            'show_cashier_name' => true,
            'show_customer_info' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('receipt_settings', [
            'store_name' => 'Percetakan Berkah Abadi',
            'paper_size' => '80mm',
        ]);
    }

    public function test_pos_split_payment_with_multiple_terms(): void
    {
        $shift = CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 200000,
            'expected_cash' => 200000,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->kasir)->post(route('pos.store'), [
            'customer_name' => 'PT Megah Printing',
            'items' => [
                [
                    'product_id' => $this->productSqm->id,
                    'product_name' => $this->productSqm->name,
                    'pricing_type' => 'dimension_sqm',
                    'unit' => 'm²',
                    'length' => 6.0,
                    'width' => 1.0,
                    'area' => 6.0,
                    'quantity' => 1,
                    'unit_price' => 25000,
                    'subtotal' => 150000,
                ],
            ],
            'subtotal' => 150000,
            'discount_amount' => 0,
            'grand_total' => 150000,
            'paid_amount' => 50000,
            'payment_method' => 'split',
            'is_split_payment' => true,
            'terms' => [
                [
                    'term_name' => 'Termin 1 (DP Uang Muka 33%)',
                    'amount' => 50000,
                    'payment_method' => 'cash',
                    'is_paid' => true,
                    'notes' => 'DP Tunai saat order',
                ],
                [
                    'term_name' => 'Termin 2 (ACC Proof Desain)',
                    'amount' => 50000,
                    'payment_method' => 'transfer',
                    'is_paid' => false,
                    'due_date' => '2026-10-25',
                    'notes' => 'Transfer setelah ACC desain',
                ],
                [
                    'term_name' => 'Termin 3 (Pelunasan Ambil Barang)',
                    'amount' => 50000,
                    'payment_method' => 'cash',
                    'is_paid' => false,
                    'due_date' => '2026-10-30',
                    'notes' => 'Pelunasan di kasir saat ambil spanduk',
                ],
            ],
        ]);

        $response->assertRedirect();

        $order = Order::where('customer_name', 'PT Megah Printing')->first();
        $this->assertNotNull($order);
        $this->assertEquals(150000, $order->grand_total);
        $this->assertEquals(50000, $order->paid_amount);
        $this->assertEquals('partial', $order->payment_status);
        $this->assertEquals('split', $order->payment_method);

        // Verify 3 terms created
        $this->assertCount(3, $order->payments);

        $term1 = $order->payments->where('term_index', 1)->first();
        $this->assertEquals('paid', $term1->status);
        $this->assertEquals(50000, $term1->amount);
        $this->assertEquals(50000, $term1->paid_amount);
        $this->assertEquals($shift->id, $term1->cashier_shift_id);

        $term2 = $order->payments->where('term_index', 2)->first();
        $this->assertEquals('pending', $term2->status);
        $this->assertEquals(50000, $term2->amount);
        $this->assertEquals(0, $term2->paid_amount);
        $this->assertEquals('2026-10-25', $term2->due_date->format('Y-m-d'));

        // Verify shift cash was incremented
        $this->assertEquals(250000, $shift->fresh()->expected_cash);

        // Pay Termin 2
        $payTerm2Response = $this->actingAs($this->kasir)->post(route('orders.payment', $order->id), [
            'order_payment_id' => $term2->id,
            'payment_method' => 'transfer',
            'amount' => 50000,
            'notes' => 'Bukti transfer BCA terlampir',
        ]);
        $payTerm2Response->assertRedirect();

        $this->assertEquals('paid', $term2->fresh()->status);
        $this->assertEquals(100000, $order->fresh()->paid_amount);
        $this->assertEquals('partial', $order->fresh()->payment_status);

        // Pay Termin 3 (Final settlement)
        $term3 = $order->payments->where('term_index', 3)->first();
        $payTerm3Response = $this->actingAs($this->kasir)->post(route('orders.payment', $order->id), [
            'order_payment_id' => $term3->id,
            'payment_method' => 'cash',
            'amount' => 50000,
            'notes' => 'Pelunasan lunas kasir',
        ]);
        $payTerm3Response->assertRedirect();

        $this->assertEquals('paid', $term3->fresh()->status);
        $this->assertEquals(150000, $order->fresh()->paid_amount);
        $this->assertEquals('paid', $order->fresh()->payment_status);

        // Add a 4th term to test adding terms
        $addTermResponse = $this->actingAs($this->admin)->post(route('orders.terms.store', $order->id), [
            'term_name' => 'Termin Tambahan (Packing Kayu Luar Kota)',
            'amount' => 25000,
            'due_date' => '2026-11-05',
            'notes' => 'Biaya tambahan pengiriman',
        ]);
        $addTermResponse->assertRedirect();
        $this->assertCount(4, $order->fresh()->payments);
    }

    public function test_admin_can_view_orders_index_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/orders');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Orders/Index')
            ->has('orders')
            ->has('filters')
        );
    }
}

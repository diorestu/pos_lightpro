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
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $operator;
    protected User $kasir;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com',
        ]);

        $this->operator = User::factory()->create([
            'role' => 'operator',
            'email' => 'operator@test.com',
        ]);

        $this->kasir = User::factory()->create([
            'role' => 'kasir',
            'email' => 'kasir@test.com',
        ]);

        $category = Category::create([
            'name' => 'Banner & Spanduk',
            'slug' => 'banner-spanduk',
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Banner Flexi 280',
            'code' => 'BAN-280',
            'pricing_type' => 'dimension_sqm',
            'unit' => 'm²',
            'base_price' => 25000,
            'min_order' => 1,
            'cost_price' => 12000,
            'stock' => 100,
            'is_stock_managed' => false,
            'is_active' => true,
        ]);
    }

    public function test_operator_can_view_production_pipeline(): void
    {
        $response = $this->actingAs($this->operator)->get('/production');
        $response->assertStatus(200);
    }

    public function test_admin_can_view_production_pipeline(): void
    {
        $response = $this->actingAs($this->admin)->get('/production');
        $response->assertStatus(200);
    }

    public function test_kasir_is_redirected_away_from_production_pipeline(): void
    {
        $response = $this->actingAs($this->kasir)->get('/production');
        $response->assertRedirect('/pos');
    }

    public function test_operator_is_redirected_to_production_pipeline_from_dashboard(): void
    {
        $response = $this->actingAs($this->operator)->get('/dashboard');
        $response->assertRedirect('/production');
    }

    public function test_operator_can_advance_order_status_through_pipeline(): void
    {
        $order = Order::create([
            'invoice_number' => 'INV-TEST-PROD-001',
            'user_id' => $this->kasir->id,
            'customer_name' => 'Budi Percetakan',
            'customer_phone' => '08123456789',
            'subtotal' => 75000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 75000,
            'paid_amount' => 75000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'order_status' => 'pending',
            'due_date' => now()->addDay()->toDateString(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'pricing_type' => 'dimension_sqm',
            'unit' => 'm²',
            'length' => 300,
            'width' => 100,
            'area' => 3.0,
            'quantity' => 1,
            'unit_price' => 25000,
            'finishing_option' => 'Mata Ayam 4 Sudut',
            'finishing_price' => 0,
            'cost_price' => 12000,
            'subtotal' => 75000,
            'design_note' => 'Banner_Ultah_Budi.pdf',
        ]);

        // 1. Pending -> Processing (Mulai Cetak)
        $response = $this->actingAs($this->operator)
            ->patch("/production/{$order->id}/status", [
                'order_status' => 'processing',
            ]);
        $response->assertRedirect();
        $this->assertEquals('processing', $order->fresh()->order_status);

        // 2. Processing -> Finishing
        $response = $this->actingAs($this->operator)
            ->patch("/production/{$order->id}/status", [
                'order_status' => 'finishing',
            ]);
        $response->assertRedirect();
        $this->assertEquals('finishing', $order->fresh()->order_status);

        // 3. Finishing -> Ready (Siap Diambil)
        $response = $this->actingAs($this->operator)
            ->patch("/production/{$order->id}/status", [
                'order_status' => 'ready',
            ]);
        $response->assertRedirect();
        $this->assertEquals('ready', $order->fresh()->order_status);

        // 4. Ready -> Completed (Selesai)
        $response = $this->actingAs($this->operator)
            ->patch("/production/{$order->id}/status", [
                'order_status' => 'completed',
            ]);
        $response->assertRedirect();
        $this->assertEquals('completed', $order->fresh()->order_status);
    }

    public function test_stock_reports_can_be_exported_to_csv(): void
    {
        $response = $this->actingAs($this->admin)->get('/stock-reports/export-csv');

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
        $this->assertTrue(str_contains($response->headers->get('content-disposition'), 'laporan_mutasi_stok_'));
    }

    public function test_sales_reports_can_be_exported_to_csv_with_utf8(): void
    {
        $response = $this->actingAs($this->admin)->get('/reports/export-csv?period=today');

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
        $this->assertTrue(str_contains($response->headers->get('content-disposition'), 'laporan_penjualan_'));
    }
}

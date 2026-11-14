<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\PricingScheme;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingSchemeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $kasir;
    protected Category $category;

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
            'name' => 'Kategori Uji',
            'slug' => 'kategori-uji',
        ]);
    }

    public function test_pricing_schemes_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->admin)->get('/pricing-schemes');
        $response->assertStatus(200);
    }

    public function test_admin_can_create_new_pricing_scheme(): void
    {
        $response = $this->actingAs($this->admin)
            ->from('/pricing-schemes')
            ->post('/pricing-schemes', [
                'name' => 'Per Rim Cetak',
                'code' => 'per_rim',
                'calculation_type' => 'fixed',
                'default_unit' => 'rim',
                'description' => 'Perhitungan khusus per rim kertas',
                'is_active' => true,
            ]);

        $response->assertRedirect('/pricing-schemes');
        $this->assertDatabaseHas('pricing_schemes', [
            'code' => 'per_rim',
            'name' => 'Per Rim Cetak',
            'default_unit' => 'rim',
        ]);
    }

    public function test_admin_can_toggle_pricing_scheme_status(): void
    {
        $scheme = PricingScheme::where('code', 'fixed')->first();
        if (! $scheme) {
            $scheme = PricingScheme::create([
                'name' => 'Per Pcs',
                'code' => 'fixed',
                'calculation_type' => 'fixed',
                'default_unit' => 'pcs',
                'is_active' => true,
            ]);
        }

        $initialStatus = $scheme->is_active;

        $response = $this->actingAs($this->admin)
            ->from('/pricing-schemes')
            ->patch("/pricing-schemes/{$scheme->id}/toggle");
        $response->assertRedirect('/pricing-schemes');

        $this->assertDatabaseHas('pricing_schemes', [
            'id' => $scheme->id,
            'is_active' => ! $initialStatus,
        ]);
    }

    public function test_admin_can_update_pricing_scheme(): void
    {
        $scheme = PricingScheme::create([
            'name' => 'Per Box 100',
            'code' => 'box_100',
            'calculation_type' => 'fixed',
            'default_unit' => 'box',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->from('/pricing-schemes')
            ->put("/pricing-schemes/{$scheme->id}", [
                'name' => 'Per Box Kartu Nama 100',
                'code' => 'box_100',
                'calculation_type' => 'fixed',
                'default_unit' => 'box',
                'description' => 'Updated description',
                'is_active' => true,
            ]);

        $response->assertRedirect('/pricing-schemes');
        $this->assertDatabaseHas('pricing_schemes', [
            'id' => $scheme->id,
            'name' => 'Per Box Kartu Nama 100',
            'description' => 'Updated description',
        ]);
    }

    public function test_cannot_delete_scheme_that_is_in_use_by_products(): void
    {
        $scheme = PricingScheme::where('code', 'fixed')->first();
        if (! $scheme) {
            $scheme = PricingScheme::create([
                'name' => 'Per Pcs',
                'code' => 'fixed',
                'calculation_type' => 'fixed',
                'default_unit' => 'pcs',
                'is_active' => true,
            ]);
        }

        Product::create([
            'category_id' => $this->category->id,
            'name' => 'Kartu Nama Bisnis',
            'code' => 'KRN-001',
            'pricing_type' => 'fixed',
            'unit' => 'box',
            'base_price' => 35000,
            'min_order' => 1,
            'cost_price' => 15000,
            'stock' => 10,
            'is_stock_managed' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->from('/pricing-schemes')
            ->delete("/pricing-schemes/{$scheme->id}");
        $response->assertSessionHas('error');
    }

    public function test_can_create_product_with_default_inactive_scheme_none(): void
    {
        $response = $this->actingAs($this->admin)
            ->from('/products')
            ->post('/products', [
                'category_id' => $this->category->id,
                'name' => 'Tote Bag Polos',
                'code' => 'TB-001',
                'pricing_type' => 'none',
                'unit' => 'pcs',
                'base_price' => 15000,
                'min_order' => 1,
                'cost_price' => 8000,
                'stock' => 20,
                'is_stock_managed' => true,
                'is_active' => true,
            ]);

        $response->assertRedirect('/products');
        $this->assertDatabaseHas('products', [
            'code' => 'TB-001',
            'pricing_type' => 'none',
        ]);
    }

    public function test_pos_transaction_with_none_pricing_type(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Pin Peniti 44mm',
            'code' => 'PIN-44',
            'pricing_type' => 'none',
            'unit' => 'pcs',
            'base_price' => 3000,
            'min_order' => 1,
            'cost_price' => 1000,
            'stock' => 50,
            'is_stock_managed' => true,
            'is_active' => true,
        ]);

        CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 100000,
            'expected_cash' => 100000,
            'status' => 'open',
        ]);

        $payload = [
            'customer_name' => 'Umum',
            'customer_phone' => '08123456789',
            'payment_method' => 'cash',
            'paid_amount' => 15000,
            'subtotal' => 15000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'grand_total' => 15000,
            'change_amount' => 0,
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'pricing_type' => 'none',
                    'unit' => 'pcs',
                    'quantity' => 5,
                    'unit_price' => 3000,
                    'cost_price' => 1000,
                    'subtotal' => 15000,
                ],
            ],
        ];

        $response = $this->actingAs($this->kasir)->post('/pos', $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'grand_total' => 15000,
            'paid_amount' => 15000,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'pricing_type' => 'none',
            'quantity' => 5,
            'unit_price' => 3000,
        ]);
    }
}

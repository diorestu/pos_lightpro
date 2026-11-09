<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPriceTierTest extends TestCase
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
            'name' => 'Stiker & Label',
            'slug' => 'stiker-label',
        ]);
    }

    public function test_admin_can_create_product_with_price_tiers(): void
    {
        $response = $this->actingAs($this->admin)->post('/products', [
            'category_id' => $this->category->id,
            'name' => 'Stiker Vinyl Glossy',
            'code' => 'STK-VNL-01',
            'pricing_type' => 'fixed',
            'unit' => 'lembar',
            'base_price' => 20000,
            'min_order' => 1,
            'cost_price' => 10000,
            'is_active' => true,
            'price_tiers' => [
                [
                    'min_quantity' => 10,
                    'max_quantity' => 50,
                    'price' => 18000,
                ],
                [
                    'min_quantity' => 51,
                    'max_quantity' => null,
                    'price' => 15000,
                ],
            ],
        ]);

        $response->assertRedirect();
        $product = Product::where('code', 'STK-VNL-01')->first();
        $this->assertNotNull($product);

        $this->assertCount(2, $product->priceTiers);
        $this->assertDatabaseHas('product_price_tiers', [
            'product_id' => $product->id,
            'min_quantity' => 10,
            'max_quantity' => 50,
            'price' => 18000,
        ]);
        $this->assertDatabaseHas('product_price_tiers', [
            'product_id' => $product->id,
            'min_quantity' => 51,
            'max_quantity' => null,
            'price' => 15000,
        ]);
    }

    public function test_admin_can_update_product_price_tiers(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Brosur A4 Art Paper',
            'code' => 'BRS-A4-01',
            'pricing_type' => 'fixed',
            'unit' => 'pcs',
            'base_price' => 5000,
            'min_order' => 1,
            'cost_price' => 2000,
            'is_active' => true,
        ]);

        $product->priceTiers()->create([
            'min_quantity' => 100,
            'max_quantity' => 500,
            'price' => 4000,
        ]);

        $response = $this->actingAs($this->admin)->put("/products/{$product->id}", [
            'category_id' => $this->category->id,
            'name' => 'Brosur A4 Art Paper (Revisi)',
            'code' => 'BRS-A4-01',
            'pricing_type' => 'fixed',
            'unit' => 'pcs',
            'base_price' => 5000,
            'min_order' => 1,
            'cost_price' => 2000,
            'is_active' => true,
            'price_tiers' => [
                [
                    'min_quantity' => 50,
                    'max_quantity' => 200,
                    'price' => 4200,
                ],
                [
                    'min_quantity' => 201,
                    'max_quantity' => 1000,
                    'price' => 3500,
                ],
                [
                    'min_quantity' => 1001,
                    'max_quantity' => null,
                    'price' => 3000,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertEquals(3, $product->fresh()->priceTiers()->count());
        $this->assertDatabaseMissing('product_price_tiers', [
            'product_id' => $product->id,
            'min_quantity' => 100,
            'price' => 4000,
        ]);
        $this->assertDatabaseHas('product_price_tiers', [
            'product_id' => $product->id,
            'min_quantity' => 1001,
            'price' => 3000,
        ]);
    }

    public function test_get_price_for_quantity_helper(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Kartu Nama Premium',
            'code' => 'KRT-01',
            'pricing_type' => 'fixed',
            'unit' => 'box',
            'base_price' => 45000,
            'min_order' => 1,
            'cost_price' => 20000,
            'is_active' => true,
        ]);

        $product->priceTiers()->createMany([
            ['min_quantity' => 5, 'max_quantity' => 19, 'price' => 40000],
            ['min_quantity' => 20, 'max_quantity' => null, 'price' => 35000],
        ]);

        // Below tier
        $this->assertEquals(45000, $product->getPriceForQuantity(1));
        $this->assertEquals(45000, $product->getPriceForQuantity(4));

        // First tier (5 - 19)
        $this->assertEquals(40000, $product->getPriceForQuantity(5));
        $this->assertEquals(40000, $product->getPriceForQuantity(15));
        $this->assertEquals(40000, $product->getPriceForQuantity(19));

        // Second tier (>= 20)
        $this->assertEquals(35000, $product->getPriceForQuantity(20));
        $this->assertEquals(35000, $product->getPriceForQuantity(100));
    }

    public function test_pos_transaction_with_tiered_price(): void
    {
        $shift = CashierShift::create([
            'user_id' => $this->kasir->id,
            'starting_cash' => 100000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Poster A3+',
            'code' => 'PST-A3-01',
            'pricing_type' => 'fixed',
            'unit' => 'lembar',
            'base_price' => 10000,
            'min_order' => 1,
            'cost_price' => 4000,
            'is_active' => true,
        ]);

        $product->priceTiers()->create([
            'min_quantity' => 20,
            'max_quantity' => null,
            'price' => 7500,
        ]);

        $response = $this->actingAs($this->kasir)->post('/pos', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '08123456789',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'pricing_type' => 'fixed',
                    'unit' => 'lembar',
                    'quantity' => 25,
                    'unit_price' => 7500, // Tiered price applied
                    'subtotal' => 187500,
                ],
            ],
            'subtotal' => 187500,
            'grand_total' => 187500,
            'paid_amount' => 200000,
            'change_amount' => 12500,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 25,
            'unit_price' => 7500,
            'subtotal' => 187500,
        ]);
    }
}

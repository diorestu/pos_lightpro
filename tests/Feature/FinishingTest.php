<?php

namespace Tests\Feature;

use App\Models\Finishing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinishingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com',
        ]);
    }

    public function test_admin_can_view_finishings_page(): void
    {
        Finishing::create([
            'name' => 'Mata Ayam 4 Sudut',
            'price' => 0,
            'description' => 'Ring mata ayam di 4 sudut',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/finishings');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Finishings/Index')
            ->has('finishings')
        );
    }

    public function test_admin_can_create_finishing(): void
    {
        $payload = [
            'name' => 'Keling Selongsong Bambu',
            'price' => 12000,
            'description' => 'Selongsong bambu khusus umbul-umbul',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)->post('/finishings', $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('finishings', [
            'name' => 'Keling Selongsong Bambu',
            'price' => 12000,
        ]);
    }

    public function test_admin_can_update_finishing(): void
    {
        $finishing = Finishing::create([
            'name' => 'Laminasi Glossy Tebal',
            'price' => 15000,
            'description' => 'Laminasi glossy 100 micron',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put("/finishings/{$finishing->id}", [
            'name' => 'Laminasi Glossy Super Tebal',
            'price' => 20000,
            'description' => 'Updated description',
            'is_active' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('finishings', [
            'id' => $finishing->id,
            'name' => 'Laminasi Glossy Super Tebal',
            'price' => 20000,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_unused_finishing(): void
    {
        $finishing = Finishing::create([
            'name' => 'Finishing Khusus Testing',
            'price' => 5000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete("/finishings/{$finishing->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('finishings', [
            'id' => $finishing->id,
        ]);
    }

    public function test_cannot_delete_finishing_used_in_orders(): void
    {
        $finishing = Finishing::create([
            'name' => 'Mata Ayam Keliling',
            'price' => 10000,
            'is_active' => true,
        ]);

        $order = Order::create([
            'invoice_number' => 'INV-TEST-001',
            'user_id' => $this->admin->id,
            'subtotal' => 50000,
            'grand_total' => 50000,
            'final_amount' => 50000,
            'paid_amount' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'order_status' => 'completed',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Spanduk Flexi',
            'pricing_type' => 'dimension_sqm',
            'unit' => 'm²',
            'unit_price' => 25000,
            'cost_price' => 15000,
            'length' => 2,
            'width' => 1,
            'area' => 2,
            'quantity' => 1,
            'finishing_option' => 'Mata Ayam Keliling',
            'finishing_price' => 10000,
            'subtotal' => 60000,
        ]);

        $response = $this->actingAs($this->admin)->delete("/finishings/{$finishing->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('finishings', [
            'id' => $finishing->id,
            'name' => 'Mata Ayam Keliling',
        ]);
    }
}

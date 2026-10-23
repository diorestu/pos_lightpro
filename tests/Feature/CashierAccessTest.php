<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $kasir;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::factory()->create([
            'role' => 'kasir',
            'email' => 'kasir@lightpos.com',
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@lightpos.com',
        ]);
    }

    public function test_cashier_can_access_pos_page(): void
    {
        $response = $this->actingAs($this->kasir)->get(route('pos.index'));
        $response->assertOk();
    }

    public function test_cashier_is_redirected_away_from_dashboard_to_pos(): void
    {
        $response = $this->actingAs($this->kasir)->get(route('dashboard'));
        $response->assertRedirect(route('pos.index'));
    }

    public function test_cashier_is_redirected_away_from_reports_to_pos(): void
    {
        $response = $this->actingAs($this->kasir)->get(route('reports.index'));
        $response->assertRedirect(route('pos.index'));
    }

    public function test_cashier_is_redirected_away_from_orders_index_to_pos(): void
    {
        $response = $this->actingAs($this->kasir)->get(route('orders.index'));
        $response->assertRedirect(route('pos.index'));
    }

    public function test_cashier_is_redirected_away_from_products_to_pos(): void
    {
        $response = $this->actingAs($this->kasir)->get(route('products.index'));
        $response->assertRedirect(route('pos.index'));
    }

    public function test_cashier_is_redirected_away_from_customers_to_pos(): void
    {
        $response = $this->actingAs($this->kasir)->get(route('customers.index'));
        $response->assertRedirect(route('pos.index'));
    }

    public function test_cashier_cannot_access_admin_api_endpoints(): void
    {
        $response = $this->actingAs($this->kasir)->json('POST', route('products.store'), [
            'name' => 'Unauthorized Product',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_access_dashboard_and_reports(): void
    {
        $dashResponse = $this->actingAs($this->admin)->get(route('dashboard'));
        $dashResponse->assertOk();

        $repResponse = $this->actingAs($this->admin)->get(route('reports.index'));
        $repResponse->assertOk();
    }

    public function test_sidebar_defaults_to_hidden_when_no_cookie(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();

        // Check Inertia shared props
        $props = $response->viewData('page')['props'];
        $this->assertFalse($props['sidebarOpen']);
    }

    public function test_double_submission_is_prevented_and_deduplicated(): void
    {
        $shift = \App\Models\CashierShift::create([
            'user_id' => $this->kasir->id,
            'opened_at' => now(),
            'starting_cash' => 100000,
            'status' => 'open',
        ]);

        $payload = [
            'client_token' => 'idempotency-token-unique-123',
            'customer_name' => 'Budi Santoso',
            'items' => [
                [
                    'product_name' => 'Spanduk Banner',
                    'pricing_type' => 'fixed',
                    'unit' => 'pcs',
                    'quantity' => 1,
                    'unit_price' => 50000,
                    'subtotal' => 50000,
                ],
            ],
            'subtotal' => 50000,
            'grand_total' => 50000,
            'paid_amount' => 50000,
            'payment_method' => 'cash',
        ];

        // 1st request succeeds
        $firstResponse = $this->actingAs($this->kasir)->post(route('pos.store'), $payload);
        $firstResponse->assertRedirect();
        $this->assertEquals(1, Order::count());

        // 2nd duplicate request with same client_token
        $secondResponse = $this->actingAs($this->kasir)->post(route('pos.store'), $payload);
        $secondResponse->assertRedirect();

        // Assert no double order was created
        $this->assertEquals(1, Order::count());
    }
}

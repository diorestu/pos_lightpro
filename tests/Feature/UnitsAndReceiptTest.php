<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ReceiptSetting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnitsAndReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $kasir;

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
    }

    public function test_admin_can_view_units_page(): void
    {
        Unit::create([
            'name' => 'Meter Persegi',
            'symbol' => 'm²',
            'description' => 'Satuan luas banner',
        ]);

        $response = $this->actingAs($this->admin)->get('/settings/units');

        $response->assertOk();
    }

    public function test_cashier_is_restricted_from_settings_units(): void
    {
        $response = $this->actingAs($this->kasir)->get('/settings/units');

        $response->assertRedirect('/pos');
    }

    public function test_admin_can_create_update_and_delete_unit(): void
    {
        // 1. Create
        $createResponse = $this->actingAs($this->admin)->post('/settings/units', [
            'name' => 'Box Kardus',
            'symbol' => 'box',
            'description' => 'Satuan per kotak',
            'is_active' => true,
        ]);

        $createResponse->assertSessionHas('success');
        $this->assertDatabaseHas('units', [
            'name' => 'Box Kardus',
            'symbol' => 'box',
        ]);

        $unit = Unit::where('symbol', 'box')->firstOrFail();

        // 2. Update
        $updateResponse = $this->actingAs($this->admin)->put("/settings/units/{$unit->id}", [
            'name' => 'Box Karton Besar',
            'symbol' => 'box-l',
            'description' => 'Karton ukuran besar',
            'is_active' => true,
        ]);

        $updateResponse->assertSessionHas('success');
        $this->assertDatabaseHas('units', [
            'name' => 'Box Karton Besar',
            'symbol' => 'box-l',
        ]);

        // 3. Delete
        $deleteResponse = $this->actingAs($this->admin)->delete("/settings/units/{$unit->id}");
        $deleteResponse->assertSessionHas('success');
        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }

    public function test_unit_cannot_be_deleted_if_used_by_product(): void
    {
        $category = Category::create([
            'name' => 'Percetakan Kertas',
            'slug' => 'percetakan-kertas',
        ]);

        $unit = Unit::create([
            'name' => 'Lembar Kertas',
            'symbol' => 'lbr',
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Kartu Nama Glossy',
            'code' => 'PRD-KN01',
            'pricing_type' => 'fixed',
            'unit' => 'lbr',
            'base_price' => 35000,
            'is_stock_managed' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete("/settings/units/{$unit->id}");

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('units', ['id' => $unit->id]);
    }

    public function test_admin_can_upload_and_remove_receipt_logo(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo_percetakan.png', 300, 100);

        // Upload logo
        $response = $this->actingAs($this->admin)->put('/settings/receipt', [
            'store_name' => 'LightPrint Studio',
            'paper_size' => '80mm',
            'show_logo' => true,
            'show_cashier_name' => true,
            'show_customer_info' => true,
            'logo' => $file,
        ]);

        $response->assertSessionHas('success');

        $settings = ReceiptSetting::getActive();
        $this->assertNotNull($settings->logo_path);
        $this->assertNotNull($settings->logo_url);
        Storage::disk('public')->assertExists($settings->logo_path);

        // Remove logo
        $removeResponse = $this->actingAs($this->admin)->put('/settings/receipt', [
            'store_name' => 'LightPrint Studio',
            'paper_size' => '80mm',
            'show_logo' => true,
            'show_cashier_name' => true,
            'show_customer_info' => true,
            'remove_logo' => true,
        ]);

        $removeResponse->assertSessionHas('success');

        $settings->refresh();
        $this->assertNull($settings->logo_path);
        $this->assertNull($settings->logo_url);
    }
}

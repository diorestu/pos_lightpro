<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_material_id')->nullable()->constrained('raw_materials')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('pricing_type')->default('fixed'); // 'fixed' or 'dimension_sqm'
            $table->string('unit')->default('pcs'); // 'm²', 'pcs', 'rim', 'box', 'lembar'
            $table->decimal('base_price', 15, 2);
            $table->decimal('min_order', 10, 2)->default(1);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('stock', 14, 2)->default(0);
            $table->boolean('is_stock_managed')->default(false);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

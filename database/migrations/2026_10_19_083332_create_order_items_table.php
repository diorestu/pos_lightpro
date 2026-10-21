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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('pricing_type')->default('fixed'); // fixed, dimension_sqm
            $table->string('unit')->default('pcs');
            $table->decimal('length', 10, 2)->nullable(); // e.g. 3.00 meter
            $table->decimal('width', 10, 2)->nullable();  // e.g. 1.50 meter
            $table->decimal('area', 10, 2)->nullable();   // e.g. 4.50 m²
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->string('finishing_option')->nullable(); // misal: "Mata ayam 4 pojok", "Laminasi glossy", "Potong pas"
            $table->decimal('finishing_price', 15, 2)->default(0);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->string('design_note')->nullable(); // nama file, spesifikasi cetak
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

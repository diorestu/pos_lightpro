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
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->string('item_type'); // 'raw_material' or 'product'
            $table->unsignedBigInteger('item_id');
            $table->string('item_name');
            $table->string('type'); // 'in', 'out', 'adjustment'
            $table->decimal('quantity', 14, 2);
            $table->decimal('previous_stock', 14, 2);
            $table->decimal('current_stock', 14, 2);
            $table->string('reference_type')->nullable(); // 'pos_order', 'restock', 'opname', 'waste_damaged', 'return'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};

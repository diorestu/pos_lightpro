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
        Schema::create('receipt_settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_name')->default('LightPrint Digital Printing');
            $table->string('tagline')->default('Solusi Cetak Cepat, Tajam, & Terpercaya');
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('paper_size')->default('58mm'); // '58mm', '80mm', 'A5'
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_cashier_name')->default(true);
            $table->boolean('show_customer_info')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_settings');
    }
};

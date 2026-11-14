<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pricing_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('calculation_type')->default('fixed'); // 'fixed' or 'dimension_sqm'
            $table->string('default_unit')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed the 2 existing calculation schemes
        $initialSchemes = [
            [
                'code' => 'fixed',
                'name' => 'Per Pcs (Satuan Tetap)',
                'calculation_type' => 'fixed',
                'default_unit' => 'pcs',
                'description' => 'Harga dihitung per pcs / unit tetap (pcs, box, lembar, rim, dsb).',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'dimension_sqm',
                'name' => 'Per Meter Luas Bahan Tercetak',
                'calculation_type' => 'dimension_sqm',
                'default_unit' => 'm²',
                'description' => 'Harga dihitung berdasarkan luas bahan tercetak (Panjang × Lebar dalam cm dikonversi ke m²).',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('pricing_schemes')->insert($initialSchemes);

        Schema::table('products', function (Blueprint $table) {
            $table->string('pricing_type')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('pricing_type')->default('fixed')->change();
        });

        Schema::dropIfExists('pricing_schemes');
    }
};

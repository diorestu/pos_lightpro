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
        // 1. Ensure Centimeter Persegi (cm²) exists in units table
        if (Schema::hasTable('units')) {
            $unitExists = DB::table('units')->where('symbol', 'cm²')->exists();
            if (! $unitExists) {
                DB::table('units')->insert([
                    'name' => 'Centimeter Persegi',
                    'symbol' => 'cm²',
                    'description' => 'Satuan luas centimeter persegi',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Ensure dimension_sqcm exists in pricing_schemes table
        if (Schema::hasTable('pricing_schemes')) {
            $schemeExists = DB::table('pricing_schemes')->where('code', 'dimension_sqcm')->exists();
            if (! $schemeExists) {
                DB::table('pricing_schemes')->insert([
                    'code' => 'dimension_sqcm',
                    'name' => 'Per Centimeter Luas (cm²)',
                    'calculation_type' => 'dimension_sqcm',
                    'default_unit' => 'cm²',
                    'description' => 'Harga dihitung berdasarkan luas bahan dalam centimeter persegi (Panjang × Lebar dalam cm = cm²).',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pricing_schemes')) {
            DB::table('pricing_schemes')->where('code', 'dimension_sqcm')->delete();
        }
        if (Schema::hasTable('units')) {
            DB::table('units')->where('symbol', 'cm²')->delete();
        }
    }
};

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
        Schema::create('finishings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed initial finishing options
        $initialFinishings = [
            [
                'name' => 'Tanpa Finishing',
                'price' => 0,
                'description' => 'Tanpa proses finishing tambahan',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mata Ayam 4 Sudut',
                'price' => 0,
                'description' => 'Ring mata ayam di 4 sudut banner',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mata Ayam Keliling',
                'price' => 10000,
                'description' => 'Ring mata ayam per 50cm / 1m keliling',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Selongsong Atas Bawah',
                'price' => 10000,
                'description' => 'Lipatan selongsong pipa/kayu atas bawah',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Lebih Bahan 2.5cm',
                'price' => 0,
                'description' => 'Bahan dilebihkan keliling 2.5cm untuk pemasangan frame',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Potong Pas Gambar',
                'price' => 0,
                'description' => 'Dipotong presisi sesuai batas desain',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Laminasi Glossy/Doff',
                'price' => 15000,
                'description' => 'Lapisan pelindung plastik mengkilap atau matte',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('finishings')->insert($initialFinishings);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finishings');
    }
};

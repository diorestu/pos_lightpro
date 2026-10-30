<?php

use App\Models\Category;
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
        Schema::table('categories', function (Blueprint $table) {
            $table->string('color', 25)->nullable()->after('name');
        });

        $palette = [
            '#2563EB', // Blue (Outdoor)
            '#059669', // Emerald (Indoor)
            '#D97706', // Amber (Digital Offset)
            '#7C3AED', // Violet (Merchandise)
            '#0891B2', // Cyan (Finishing)
            '#DB2777', // Pink
            '#EA580C', // Orange
            '#4F46E5', // Indigo
            '#0D9488', // Teal
            '#DC2626', // Red
        ];

        $categories = Category::all();
        foreach ($categories as $index => $cat) {
            $cat->color = $palette[$index % count($palette)];
            $cat->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};

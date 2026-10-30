<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'slug',
        'description',
    ];

    public const PALETTE = [
        '#2563EB', // Blue
        '#059669', // Emerald
        '#D97706', // Amber
        '#7C3AED', // Violet
        '#DC2626', // Red
        '#DB2777', // Pink
        '#0891B2', // Cyan
        '#4F46E5', // Indigo
        '#EA580C', // Orange
        '#0D9488', // Teal
        '#9333EA', // Purple
        '#16A34A', // Green
    ];

    public static function randomPaletteColor(): string
    {
        $palette = self::PALETTE;
        return $palette[array_rand($palette)];
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (empty($category->color)) {
                $category->color = static::randomPaletteColor();
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

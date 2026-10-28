<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'unit',
        'stock',
        'min_stock',
        'cost_price',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'float',
            'min_stock' => 'float',
            'cost_price' => 'decimal:2',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock <= (float) $this->min_stock;
    }
}

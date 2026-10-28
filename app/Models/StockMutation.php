<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_type',
        'item_id',
        'item_name',
        'type',
        'quantity',
        'previous_stock',
        'current_stock',
        'reference_type',
        'reference_id',
        'reason',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'previous_stock' => 'float',
            'current_stock' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

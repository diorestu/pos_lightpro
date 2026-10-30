<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'cashier_shift_id',
        'user_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'paid_amount',
        'change_amount',
        'payment_method',
        'payment_status',
        'order_status',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'due_date',
        'notes',
        'delivery_type',
        'courier_name',
        'booking_code',
        'driver_name',
        'driver_phone',
        'delivery_status',
        'picked_up_at',
        'delivery_notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'due_date' => 'date',
            'picked_up_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class)->orderBy('term_index');
    }

    public function return(): HasOne
    {
        return $this->hasOne(OrderReturn::class);
    }
}

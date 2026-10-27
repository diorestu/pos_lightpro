<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property string|null $phone
 * @property bool $is_active
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'phone', 'is_active', 'email_verified_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }

    public function isKasir(): bool
    {
        return in_array($this->role, ['admin', 'manager', 'kasir']);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(CashierShift::class);
    }

    public function activeShift()
    {
        return $this->shifts()->where('status', 'open')->latest('opened_at')->first();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function stockMutations(): HasMany
    {
        return $this->hasMany(StockMutation::class);
    }

    public function orderReturns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }
}

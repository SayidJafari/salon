<?php
// Models/customer.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    protected $table = 'customers';

    protected $fillable = [
        'customerusername',
        'full_name',
        'national_code',
        'phone',
        'email',
        // فیلدهای «ورودی کاربر» که ایمن هستند
    ];

    protected $guarded = [
        'password_hash',
        'is_suspended',
        'referral_code',
        'referred_by',
        'created_at',
        'updated_at'
    ];

    protected $hidden = ['password_hash'];

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'customer_id');
    }

    public function getWalletBalanceAttribute(): float
    {
        return (float) optional($this->wallet)->current_balance ?: 0.0;
    }
}

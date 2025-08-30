<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $table = 'admin_users';   // نام جدول
    protected $fillable = [
        'adminusername',
        'password_hash',
        'is_superadmin',
        'referral_code',
    ];
    protected $hidden = [
        'password_hash',
    ];

    public function getAuthPassword()
    {
        // چون فیلد رمز ما password_hash است باید این تابع اضافه شود
        return $this->password_hash;
    }
}

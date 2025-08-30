<?php
// Models/PackageCategory.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageCategory extends Model
{
    protected $table = 'package_categories';

    protected $fillable = [
        'name',
        'description',
        'price',
        'image',
        'is_active',
        // +++
        'referrer_enabled',
        'referrer_commission_type',
        'referrer_commission_value',
    ];

    public function services()
    {
        return $this->belongsToMany(
            ServiceType::class,
            'package_services',
            'package_category_id',
            'service_type_id'
        )->withPivot('quantity', 'staff_id')
         ->withTimestamps();
    }
}


<?php
// app/Models/PackageService.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageService extends Model
{
    /** نام جدول */
    protected $table = 'package_services';

    /**
     * چون معمولاً pivot ها کلید ترکیبی دارند (و اغلب ID ندارند)
     * ذخیره/آپدیت رکورد با Eloquent سخت می‌شود؛
     * در عمل شما بیشتر با Query Builder یا رابطه‌ی pivot کار می‌کنید.
     */
    public $timestamps  = true;
    public $incrementing = false;
    protected $primaryKey = null; // کلید ترکیبی (category_id + service_id)

    /** فیلدهای قابل مقداردهی گروهی */
    protected $fillable = [
        'package_category_id',
        'service_type_id',
        'quantity',
        'staff_id', // پرسنل پیش‌فرض
    ];

    /** کاست‌ها برای تایپ صحیح */
    protected $casts = [
        'package_category_id' => 'integer',
        'service_type_id'     => 'integer',
        'quantity'            => 'integer',
        'staff_id'            => 'integer',
    ];

    /** روابط */
    public function packageCategory(): BelongsTo
    {
        return $this->belongsTo(PackageCategory::class, 'package_category_id');
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id');
    }

    /** پرسنل پیش‌فرضِ انجام‌دهنده‌ی این خدمت داخل پکیج */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /** alias کاربردی برای سازگاری با نامی که در بعضی کوئری‌ها استفاده کرده‌اید (default_staff_id) */
    public function getDefaultStaffIdAttribute(): ?int
    {
        return $this->staff_id;
    }

    /** اگر quantity نیامد، 1 در نظر بگیر */
    public function setQuantityAttribute($value): void
    {
        $this->attributes['quantity'] = (int)($value ?? 1) > 0 ? (int)$value : 1;
    }


}

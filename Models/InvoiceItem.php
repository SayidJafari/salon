<?php
// app/Models/InvoiceItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\StaffIncome;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';
    public $timestamps = true;
    protected $fillable = [
        'invoice_id',
        'service_type_id',
        'staff_id',
        'quantity',
        'price',
        'total',
        'created_at',
        'updated_at',
        'item_status',
        'staff_commission_amount',
        'staff_commission_percent',
        'staff_commission_status',
        'staff_commission_payment_method',
        'staff_commission_paid_at'
    ];




    public function commissionAmount(): float
    {
        // اولویت با مقدار صریح
        if (!is_null($this->staff_commission_amount)) {
            return (float) $this->staff_commission_amount;
        }
        // اگر درصد ست بود
        if (!is_null($this->staff_commission_percent)) {
            return round(((float)$this->total) * ((float)$this->staff_commission_percent) / 100, 2);
        }
        // TODO: اگر سیاست پیش‌فرضی از جدول staff_skills_commission می‌خواهید، اینجا لود و محاسبه کنید.
        return 0.0;
    }


    public function staffIncome(): HasOne
    {
        return $this->hasOne(StaffIncome::class, 'invoice_item_id');
    }

    // (اختیاری) اگر ندارید، روابط مرسوم زیر هم مفیدند:
    public function invoice()      { return $this->belongsTo(Invoice::class, 'invoice_id'); }
    public function serviceType()  { return $this->belongsTo(ServiceType::class, 'service_type_id'); }
    public function staff()        { return $this->belongsTo(Staff::class, 'staff_id'); }
}

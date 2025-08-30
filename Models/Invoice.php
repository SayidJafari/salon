<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $table = 'invoices';
    public $timestamps = true;

    protected $fillable = [
        'customer_id',
        'invoice_status',
        'registration_date',
        'payment_type',
        'payment_status',
        'discount_amount',
        'discount_code_id',   // ← کلید تخفیف
        'total_amount',
        'paid_amount',
        'final_amount',
        'created_at',
        'in_progress',
    ];

    // مقدار پیش‌فرض
    protected $attributes = [
        'payment_type' => 'aggregate',
    ];

    protected $casts = [
        'registration_date' => 'datetime',
        'discount_amount'   => 'decimal:2',
        'total_amount'      => 'decimal:2',
        'paid_amount'       => 'decimal:2',
        'final_amount'      => 'decimal:2',
        'in_progress'       => 'boolean',
    ];

    /* -------------------- روابط -------------------- */

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function incomes(): HasMany
    {
        // پرداخت‌های سالن (salon_incomes)
        return $this->hasMany(SalonIncome::class, 'invoice_id');
    }

    // پرداخت‌های پرسنل (برای حالت تفکیکی)
    public function staffPayments()
    {
        return $this->hasMany(\App\Models\StaffIncome::class, 'invoice_id');
    }

    public function staffIncomes(): HasMany
    {
        // پرداخت‌های پرسنل (staff_incomes)
        return $this->hasMany(StaffIncome::class, 'invoice_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class, 'discount_code_id');
    }

    /* -------------------- محاسبات -------------------- */

    /** جمع پرداخت‌های قطعی سالن */
    public function getSumPostedIncomesAttribute(): float
    {
        return (float) $this->incomes()
            ->where('status', 'posted')
            ->sum('amount');
    }

    /** بروزرسانی وضعیت پرداخت بر اساس داده‌های فعلی */
    public function recalcPaymentStatus(): void
    {
        $sumSalon = (float) $this->incomes()
            ->where('status', 'posted')
            ->sum('amount');

        $sumStaff = 0.0;
        if ($this->payment_type === 'split') {
            $sumStaff = (float) $this->staffIncomes()
                ->where('commission_status', 'credit')
                ->sum('amount');
        }

        $legacyPaid = (float) ($this->paid_amount ?? 0);

        $paid  = $sumSalon + $sumStaff + $legacyPaid;
        $final = (float) $this->final_amount;
        $eps   = 0.001;

        if ($paid <= $eps) {
            $this->payment_status = 'unpaid';
        } elseif ($paid + $eps < $final) {
            $this->payment_status = 'partial';
        } else {
            $this->payment_status = 'paid';
        }

        $this->save();
    }
}

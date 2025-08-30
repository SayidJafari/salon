<?php
// app/Models/DiscountCode.php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCode extends Model
{
    protected $table = 'discount_codes';

    // اگر ترجیح می‌دی دقیق‌تر باشه، به‌جای guarded از fillable استفاده کن
    protected $fillable = [
        'code',
        'discount_type',
        'value',
        'usage_limit',
        'times_used',
        'valid_from',
        'valid_until',
        'is_active',
        'time_start',
        'time_end',
        'days_of_week',
    ];


    protected $casts = [
        'is_active'    => 'boolean',
        'valid_from'   => 'datetime',
        'valid_until'  => 'datetime',
        'usage_limit'  => 'integer',
        'times_used'   => 'integer',
        'value'        => 'float',
    ];

    public const TYPE_PERCENT = 'percent';
    public const TYPE_AMOUNT  = 'amount';

    /** ارتباط با فاکتورها (اختیاری اما مفید) */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'discount_code_id');
    }

    /** فقط کدهای فعال */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', 1);
    }

    /**
     * کدهایی که «به‌صورت کلی» الان قابل استفاده‌اند (بدون چکِ روز/بازه ساعتی).
     * برای فیلتر اولیه خوبه؛ چک نهایی با isUsableAt انجام بشه.
     */
    public function scopeUsableNow(Builder $q, ?Carbon $now = null): Builder
    {
        $now = $now ?: now();

        return $q->active()
            ->where(function ($qq) use ($now) {
                $qq->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($qq) use ($now) {
                $qq->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            })
            ->where(function ($qq) {
                $qq->whereNull('usage_limit')
                    ->orWhereColumn('times_used', '<', 'usage_limit');
            });
    }

    /**
     * چک کامل اعتبار در همین لحظه (مطابق منطق کنترلر)
     * شامل: بازه تاریخ، روزهای هفته، بازه زمانی، سقف دفعات
     */
    public function isUsableAt(?Carbon $now = null): bool
    {
        $now = $now ?: now();

        if ($this->valid_from && $now->lt(Carbon::parse($this->valid_from))) return false;
        if ($this->valid_until && $now->gt(Carbon::parse($this->valid_until))) return false;

        if (!empty($this->days_of_week)) {
            $allowed = array_filter(array_map('trim', explode(',', strtolower($this->days_of_week))));
            $dayCode = strtolower(substr($now->format('D'), 0, 3)); // mon,tue,...
            if (!in_array($dayCode, $allowed, true)) return false;
        }

        $ts = $this->time_start ?? null; // 'HH:MM:SS'
        $te = $this->time_end   ?? null; // 'HH:MM:SS'
        if ($ts || $te) {
            $current = $now->format('H:i:s');
            if ($ts && $te) {
                // بازه شبانه‌روزی (مثلاً 22:00 تا 03:00)
                if ($te < $ts) {
                    if (!($current >= $ts || $current <= $te)) return false;
                } else {
                    if (!($current >= $ts && $current <= $te)) return false;
                }
            } elseif ($ts) {
                if (!($current >= $ts)) return false;
            } elseif ($te) {
                if (!($current <= $te)) return false;
            }
        }

        if (!is_null($this->usage_limit) && (int) $this->times_used >= (int) $this->usage_limit) {
            return false;
        }

        return (bool) $this->is_active;
    }

    /**
     * محاسبه مبلغ تخفیف روی جمع کل
     * percent: floor(base * value / 100)
     * amount: min(base, value)
     */
    public function applyToAmount(float $baseTotal): float
    {
        $value = (float) $this->value;

        if ($this->discount_type === self::TYPE_PERCENT) {
            return max(0.0, floor($baseTotal * $value / 100));
        }

        // fixed amount
        return min($baseTotal, $value);
    }

    /** افزایش شمارنده استفاده */
    public function markUsed(): void
    {
        $this->increment('times_used');
        $this->refresh();
    }
}

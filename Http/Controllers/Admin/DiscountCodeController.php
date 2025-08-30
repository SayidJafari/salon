<?php
// app/Http/Controllers/admin/DiscountCodeController.php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Helpers\JalaliHelper;

class DiscountCodeController extends Controller
{
    /**
     * نمایش فرم ایجاد کد تخفیف و لیست فعلی کدها
     */
    public function create()
    {
        try {
            // دریافت لیست کدهای تخفیف
            $discounts = DB::table('discount_codes')->orderByDesc('id')->get();

            // استفاده از helper panelView با مسیر درست
            return panelView('admin', 'discount_codes.create', compact('discounts'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در نمایش فرم ایجاد کد تخفیف', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * دریافت لیست کدهای تخفیف (مثلاً برای Ajax)
     */
    public function list()
    {
        try {
            $discounts = DB::table('discount_codes')->orderByDesc('id')->get();

            // مسیر صحیح view برای list
            return panelView('admin', 'discount_codes.list', compact('discounts'))->render();
        } catch (\Exception $e) {
            $this->logActivity('خطا در لیست کدهای تخفیف', $e->getMessage());
            return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.']);
        }
    }

    /**
     * ثبت کد تخفیف جدید
     */
    public function store(Request $request)
    {
        // تبدیل تاریخ شمسی به میلادی
        $validFromJalali = $request->input('valid_from_jalali');
        $validUntilJalali = $request->input('valid_until_jalali');

        if ($validFromJalali) {
            $request->merge(['valid_from' => JalaliHelper::toGregorian($validFromJalali)]);
        }
        if ($validUntilJalali) {
            $request->merge(['valid_until' => JalaliHelper::toGregorian($validUntilJalali)]);
        }

        $request->validate([
            'code'          => 'required|string|max:32|unique:discount_codes,code',
            'discount_type' => 'required|in:percent,amount',
            'value'         => 'required|numeric',
            'usage_limit'   => 'required|integer|min:1',
            'valid_from'    => 'nullable|date',
            'valid_until'   => 'nullable|date|after_or_equal:valid_from',
            'is_active'     => 'required|boolean',
        ]);

        try {
            DB::table('discount_codes')->insert([
                'code'          => $request->code,
                'discount_type' => $request->discount_type,
                'value'         => $request->value,
                'usage_limit'   => $request->usage_limit,
                'times_used'    => 0,
                'valid_from'    => $request->valid_from,
                'valid_until'   => $request->valid_until,
                'is_active'     => $request->is_active,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // === ثبت لاگ ایجاد کد تخفیف ===
            $this->logActivity('ایجاد کد تخفیف', 'کد: ' . $request->code);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'کد تخفیف با موفقیت ثبت شد.']);
            }

            return redirect()->route('admin.discount-codes.create')->with('success', 'کد تخفیف ثبت شد.');
        } catch (\Exception $e) {
            $this->logActivity('خطا در ثبت کد تخفیف', $e->getMessage());
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500);
            }
            return redirect()->route('admin.discount-codes.create')->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * دریافت اطلاعات ویرایش یک کد تخفیف (خروجی json)
     */
    public function edit($id)
    {
        try {
            $discount = DB::table('discount_codes')->find($id);
            if (!$discount) {
                $this->logActivity('تلاش برای مشاهده ویرایش کد تخفیف ناموجود', 'آی‌دی: ' . $id);
                return response()->json(['success' => false, 'message' => 'کد یافت نشد!'], 404);
            }

            // === ثبت لاگ مشاهده ویرایش کد تخفیف ===
            $this->logActivity('مشاهده فرم ویرایش کد تخفیف', 'آی‌دی: ' . $id);

            return response()->json([
                'success' => true,
                'discount' => $discount,
                'valid_from_jalali'  => \App\Helpers\JalaliHelper::toJalali($discount->valid_from),
                'valid_until_jalali' => \App\Helpers\JalaliHelper::toJalali($discount->valid_until),
            ]);
        } catch (\Exception $e) {
            $this->logActivity('خطا در مشاهده فرم ویرایش کد تخفیف', $e->getMessage());
            return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500);
        }
    }

    /**
     * بروزرسانی یک کد تخفیف
     */
    public function update(Request $request, $id)
    {
        // ۱. بررسی وجود کد
        $discount = DB::table('discount_codes')->find($id);
        if (!$discount) {
            $this->logActivity('تلاش برای ویرایش کد تخفیف ناموجود', 'آی‌دی: ' . $id);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'کد تخفیف پیدا نشد!'], 404);
            }
            return redirect()->route('admin.discount-codes.create')->with('error', 'کد تخفیف پیدا نشد!');
        }

        $request->validate([
            'code'          => 'required|string|max:32|unique:discount_codes,code,' . $id,
            'discount_type' => 'required|in:percent,amount',
            'value'         => 'required|numeric',
            'usage_limit'   => 'required|integer|min:1',
            'valid_from'    => 'nullable|date',
            'valid_until'   => 'nullable|date|after_or_equal:valid_from',
            'is_active'     => 'required|boolean',
        ]);

        try {
            DB::table('discount_codes')->where('id', $id)->update([
                'code'          => $request->code,
                'discount_type' => $request->discount_type,
                'value'         => $request->value,
                'usage_limit'   => $request->usage_limit,
                'valid_from'    => $request->valid_from,
                'valid_until'   => $request->valid_until,
                'is_active'     => $request->is_active,
                'updated_at'    => now(),
            ]);

            // === ثبت لاگ ویرایش کد تخفیف ===
            $this->logActivity('ویرایش کد تخفیف', 'آی‌دی: ' . $id . ' | کد: ' . $request->code);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'کد تخفیف ویرایش شد.']);
            }

            return redirect()->route('admin.discount-codes.create')->with('success', 'کد تخفیف ویرایش شد.');
        } catch (\Exception $e) {
            $this->logActivity('خطا در ویرایش کد تخفیف', $e->getMessage());
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500);
            }
            return redirect()->route('admin.discount-codes.create')->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * حذف یک کد تخفیف
     */
    public function destroy(Request $request, $id)
    {
        try {
            $discount = DB::table('discount_codes')->find($id);
            if (!$discount) {
                $this->logActivity('تلاش برای حذف کد تخفیف ناموجود', 'آی‌دی: ' . $id);
                return response()->json(['success' => false, 'message' => 'کد پیدا نشد!'], 404);
            }

            DB::table('discount_codes')->where('id', $id)->delete();

            // === ثبت لاگ حذف کد تخفیف ===
            $this->logActivity('حذف کد تخفیف', 'آی‌دی: ' . $id . ' | کد: ' . ($discount->code ?? ''));

            return response()->json(['success' => true, 'message' => 'کد تخفیف با موفقیت حذف شد.']);
        } catch (\Exception $e) {
            $this->logActivity('خطا در حذف کد تخفیف', $e->getMessage());
            return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500);
        }
    }
    public function show($id)
    {
        abort(404); // یا هر چیزی که دوست داری
    }

    // متد ثبت لاگ با try/catch
    protected function logActivity($action, $details = null)
    {
        try {
            ActivityLog::create([
                'admin_id'   => Auth::guard('admin')->id(),
                'ip_address' => request()->ip(),
                'action'     => $action,
                'details'    => $details,
            ]);
        } catch (\Exception $e) {
            // اگر ثبت لاگ مشکل داشت، خطا نده!
        }
    }
}

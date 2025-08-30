<?php
// app/Http/Controllers/admin/CustomerController.php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator; // ⬅️ اضافه شد
use App\Http\Controllers\Controller;      // ⬅️ برای اطمینان از ارجاع صحیح
use App\Models\ActivityLog;
use App\Helpers\JalaliHelper;
use App\Models\Customer;

class CustomerController extends Controller
{
    /**
     * نمایش لیست مشتریان
     */
    public function index()
    {
        $customers = DB::table('customers as c')
            ->leftJoin('customerswallets as w', 'w.customer_id', '=', 'c.id')
            ->select('c.*', DB::raw('COALESCE(w.current_balance, 0) as wallet_balance'))
            ->orderByDesc('c.id')
            ->get();

        return panelView('admin', 'manage_customer.index', compact('customers'));
    }


    /**
     * نمایش فرم ایجاد مشتری جدید
     */
    public function create()
    {
        // استفاده از helper برای مسیر جدید view
        return panelView('admin', 'manage_customer.create');
    }

    /**
     * ثبت مشتری جدید
     */
    public function store(Request $request)
    {
        // ✅ همان قوانین قبل + آماده برای چکِ وجودِ معرف
        $validator = Validator::make($request->all(), [
            'customerusername' => 'required|string|max:100|unique:customers,customerusername',
            'full_name'        => 'required|string|max:100',
            'national_code'    => 'required|string|size:10|unique:customers,national_code',
            'phone'            => 'required|string|max:20|unique:customers,phone',
            'email'            => 'nullable|email|max:100|unique:customers,email',
            'password'         => 'required|string|min:6|confirmed',
            'referrer_type'    => 'nullable|in:admin,staff,customer',
            'referred_by'      => [
                'nullable',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    if (!empty($value)) {
                        $value = strtoupper($value);
                        if (!preg_match('/^(A|S|C|N)-[A-Z0-9]+$/', $value)) {
                            $fail('کد معرف اشتباه است. باید با A-, S-, C- یا N- شروع شده و فقط شامل حروف بزرگ و عدد باشد.');
                        }
                    }
                },
            ],
        ]);

        // ✅ چک وجود واقعیِ معرف (وقتی کاربر چیزی وارد کرده)
        $validator->after(function ($v) use ($request) {
            if ($request->filled('referrer_type') && $request->filled('referred_by')) {
                $type = $request->input('referrer_type');
                $code = strtoupper($request->input('referred_by'));

                // اگر نوع وارد شده با پیشوند کد هم‌خوانی ندارد، خطا بده
                $prefixMap = ['admin' => 'A-', 'staff' => 'S-', 'customer' => 'C-'];
                if (isset($prefixMap[$type]) && strpos($code, $prefixMap[$type]) !== 0) {
                    $v->errors()->add('referred_by', 'کد معرف با نوع انتخاب‌شده همخوانی ندارد.');
                    return;
                }

                // وجود واقعی در جدول مربوطه
                $tableMap = ['admin' => 'admin_users', 'staff' => 'staff', 'customer' => 'customers'];
                $table = $tableMap[$type] ?? null;

                if ($table) {
                    $exists = DB::table($table)->where('referral_code', $code)->exists();
                    if (!$exists) {
                        $v->errors()->add('referred_by', 'کد معرف نامعتبر است یا یافت نشد.');
                    }
                }
            }
        });

        if ($validator->fails()) {
            // برای AJAX پیام صریح برگردان
            if ($request->ajax() || $request->wantsJson()) {
                $msg = ($validator->errors()->has('referred_by') || $validator->errors()->has('referrer_type'))
                    ? 'کد یا نوع معرف نامعتبر است.'
                    : 'اعتبارسنجی ورودی‌ها ناموفق بود.';
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'errors'  => $validator->errors(),
                ], 422);
            }
            // برای درخواست معمولی، بازگشت با خطا
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $hashedPassword = Hash::make($validated['password']);

        $customerId = DB::table('customers')->insertGetId([
            'customerusername' => $validated['customerusername'],
            'full_name'        => $validated['full_name'],
            'national_code'    => $validated['national_code'],
            'phone'            => $validated['phone'],
            'email'            => $validated['email'],
            'password_hash'    => $hashedPassword,
            'is_suspended'     => 0,
            'referred_by'      => strtoupper($validated['referred_by'] ?? ''), // ✅ فقط بعد از اعتبارسنجی ذخیره می‌شود
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        DB::table('customerswallets')->insert([
            'customer_id'         => $customerId,
            'current_balance' => 0,
            'created_at'      => now(),
            'last_updated'    => now(),
        ]);
        // تولید کد ریفرال یونیک با پیشوند C-
        do {
            $referralCode = 'C-' . strtoupper(\Illuminate\Support\Str::random(6));
            $exists = DB::table('customers')->where('referral_code', $referralCode)->exists();
        } while ($exists);

        DB::table('customers')->where('id', $customerId)->update([
            'referral_code' => $referralCode,
        ]);

        // بخش ثبت ریفرال جدید طبق جدول جدید (referrals)
        if ($request->filled('referrer_type') && $request->filled('referred_by')) {
            $referrerType = $request->input('referrer_type');
            $referrerCode = strtoupper($request->input('referred_by'));
            $referrerId = null;

            if ($referrerType === 'customer') {
                $referrer = DB::table('customers')->where('referral_code', $referrerCode)->first();
                $referrerId = $referrer ? $referrer->id : null;
            } elseif ($referrerType === 'staff') {
                $referrer = DB::table('staff')->where('referral_code', $referrerCode)->first();
                $referrerId = $referrer ? $referrer->id : null;
            } elseif ($referrerType === 'admin') {
                $referrer = DB::table('admin_users')->where('referral_code', $referrerCode)->first();
                $referrerId = $referrer ? $referrer->id : null;
            }

            // ✅ به اینجا فقط وقتی می‌رسیم که معتبر بوده؛ با این حال، چک نهایی:
            if ($referrerId) {
                DB::table('referrals')->insert([
                    'referrer_id'   => $referrerId,
                    'referrer_type' => $referrerType,
                    'referrer_code' => $referrerCode,
                    'referred_id'   => $customerId,
                    'referred_type' => 'customer',
                    'referred_code' => $referralCode,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }

        // ثبت لاگ ایجاد مشتری
        $this->logActivity('ثبت مشتری جدید', 'نام کاربری: ' . $validated['customerusername'] . ' | آی‌دی: ' . $customerId);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'مشتری با موفقیت ثبت شد و کد معرف یونیک دریافت کرد.',
            ]);
        }

        return redirect('/admin/customers')->with('success', 'مشتری با موفقیت ثبت شد و کد معرف یونیک دریافت کرد.');
    }


    /**
     * نمایش فرم ویرایش مشتری
     */

    public function edit(Request $request, $id)
    {
        // اکسسور getWalletBalanceAttribute از روی relation wallet اجرا می‌شود
        $customer = Customer::with('wallet')->findOrFail($id);

        return panelView('admin', 'manage_customer.edit', compact('customer'));
    }


    /**
     * بروزرسانی اطلاعات مشتری
     */
    public function update(Request $request, $id)
    {
        // ✅ همان قوانین قبل + آماده برای چکِ وجودِ معرف
        $validator = Validator::make($request->all(), [
            'customerusername' => 'required|string|max:100|unique:customers,customerusername,' . $id,
            'full_name'        => 'required|string|max:100',
            'national_code'    => 'required|string|size:10|unique:customers,national_code,' . $id,
            'phone'            => 'required|string|max:20|unique:customers,phone,' . $id,
            'email'            => 'nullable|email|max:100|unique:customers,email,' . $id,
            'password'         => 'nullable|string|min:6|confirmed',
            'referral_code'    => 'nullable|string|max:50|unique:customers,referral_code,' . $id,
            'referred_by'      => [
                'nullable',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    if (!empty($value)) {
                        $value = strtoupper($value);
                        if (!preg_match('/^(A|S|C|N)-[A-Z0-9]+$/', $value)) {
                            $fail('کد معرف اشتباه است. باید با A-, S-, C- یا N- شروع شده و فقط شامل حروف بزرگ و عدد باشد.');
                        }
                    }
                },
            ],
            'is_suspended'     => 'required|boolean',
            'referrer_type'    => 'nullable|in:admin,staff,customer',
        ]);

        // ✅ چک وجود واقعیِ معرف (وقتی کاربر چیزی وارد کرده)
        $validator->after(function ($v) use ($request) {
            if ($request->filled('referrer_type') && $request->filled('referred_by')) {
                $type = $request->input('referrer_type');
                $code = strtoupper($request->input('referred_by'));

                $prefixMap = ['admin' => 'A-', 'staff' => 'S-', 'customer' => 'C-'];
                if (isset($prefixMap[$type]) && strpos($code, $prefixMap[$type]) !== 0) {
                    $v->errors()->add('referred_by', 'کد معرف با نوع انتخاب‌شده همخوانی ندارد.');
                    return;
                }

                $tableMap = ['admin' => 'admin_users', 'staff' => 'staff', 'customer' => 'customers'];
                $table = $tableMap[$type] ?? null;

                if ($table) {
                    $exists = DB::table($table)->where('referral_code', $code)->exists();
                    if (!$exists) {
                        $v->errors()->add('referred_by', 'کد معرف نامعتبر است یا یافت نشد.');
                    }
                }
            }
        });

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                $msg = ($validator->errors()->has('referred_by') || $validator->errors()->has('referrer_type'))
                    ? 'کد یا نوع معرف نامعتبر است.'
                    : 'اعتبارسنجی ورودی‌ها ناموفق بود.';
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'errors'  => $validator->errors(),
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        // مقدار کیف پول فعلی از دیتابیس
        $customer = DB::table('customers')->where('id', $id)->first();
        if (!$customer) {
            abort(404, 'مشتری پیدا نشد.');
        }
        $data = [
            'customerusername' => $validated['customerusername'],
            'full_name'        => $validated['full_name'],
            'national_code'    => $validated['national_code'],
            'phone'            => $validated['phone'],
            'email'            => $validated['email'],
            'referral_code'    => strtoupper($validated['referral_code'] ?? ''),
            'referred_by'      => strtoupper($validated['referred_by'] ?? ''), // ✅ فقط وقتی معتبر است به اینجا می‌رسد
            'is_suspended'     => $request->input('is_suspended', 0),
            'updated_at'       => now(),
        ];

        if (!empty($validated['password'])) {
            $data['password_hash'] = Hash::make($validated['password']);
        }

        DB::table('customers')->where('id', $id)->update($data);

        // بخش ویرایش/ثبت ریفرال در جدول referrals (اگر کد معرف یا نوع آن عوض شده باشد)
        if ($request->filled('referrer_type') && $request->filled('referred_by')) {
            $referrerType = $request->input('referrer_type');
            $referrerCode = strtoupper($request->input('referred_by'));
            $referrerId = null;

            if ($referrerType === 'customer') {
                $referrer = DB::table('customers')->where('referral_code', $referrerCode)->first();
                $referrerId = $referrer ? $referrer->id : null;
            } elseif ($referrerType === 'staff') {
                $referrer = DB::table('staff')->where('referral_code', $referrerCode)->first();
                $referrerId = $referrer ? $referrer->id : null;
            } elseif ($referrerType === 'admin') {
                $referrer = DB::table('admin_users')->where('referral_code', $referrerCode)->first();
                $referrerId = $referrer ? $referrer->id : null;
            }

            if ($referrerId) {
                // اگر قبلاً رکوردی برای این مشتری وجود دارد، به‌روزرسانی شود. اگر نه، ثبت جدید.
                $exists = DB::table('referrals')
                    ->where('referred_id', $id)
                    ->where('referred_type', 'customer')
                    ->exists();

                if ($exists) {
                    DB::table('referrals')
                        ->where('referred_id', $id)
                        ->where('referred_type', 'customer')
                        ->update([
                            'referrer_id'   => $referrerId,
                            'referrer_type' => $referrerType,
                            'referrer_code' => $referrerCode,
                            'referred_code' => strtoupper($validated['referral_code'] ?? ''),
                            'updated_at'    => now(),
                        ]);
                } else {
                    DB::table('referrals')->insert([
                        'referrer_id'   => $referrerId,
                        'referrer_type' => $referrerType,
                        'referrer_code' => $referrerCode,
                        'referred_id'   => $id,
                        'referred_type' => 'customer',
                        'referred_code' => strtoupper($validated['referral_code'] ?? ''),
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
        }

        // ثبت لاگ ویرایش مشتری
        $this->logActivity('ویرایش مشتری', 'آی‌دی: ' . $id);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'ویرایش مشتری با موفقیت انجام شد.'
            ]);
        }
        return redirect('/admin/customers')->with('success', 'ویرایش مشتری با موفقیت انجام شد.');
    }


    /**
     * حذف مشتری
     */
    public function destroy(Request $request, $id)
    {
        $customer = DB::table('customers')->where('id', $id)->first();
        if (!$customer) {
            abort(404, 'مشتری پیدا نشد.');
        }
        DB::table('customers')->where('id', $id)->delete();
        DB::table('referrals')->where('referred_id', $id)->where('referred_type', 'customer')->delete();
        DB::table('customerswallets')->where('customer_id', $id)->delete();

        // ثبت لاگ حذف مشتری
        $this->logActivity('حذف مشتری', 'آی‌دی: ' . $id);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'مشتری با موفقیت حذف شد.']);
        }
        return redirect('/admin/customers')->with('success', 'مشتری با موفقیت حذف شد.');
    }
    // کد را از روی خودش چک می‌کند (A-/S-/C- را از روی پیشوند تشخیص می‌دهد)
    public function checkReferralCode(Request $request)
    {
        $code = strtoupper((string) $request->query('code', ''));
        if (!preg_match('/^(A|S|C)-[A-Z0-9]+$/', $code)) {
            return response()->json(['exists' => false, 'message' => 'فرمت کد معرف معتبر نیست.'], 422);
        }

        $first = substr($code, 0, 1);
        $map = [
            'A' => ['table' => 'admin_users', 'type' => 'admin'],
            'S' => ['table' => 'staff',       'type' => 'staff'],
            'C' => ['table' => 'customers',   'type' => 'customer'],
        ];
        $table = $map[$first]['table'] ?? null;
        $type  = $map[$first]['type']  ?? null;

        $exists = $table ? DB::table($table)->where('referral_code', $code)->exists() : false;

        return response()->json([
            'exists' => $exists,
            'type'   => $exists ? $type : null,
        ]);
    }

    // بر اساس نوع (admin/staff/customer) و کد ملی، کد ریفرال را پیدا می‌کند
    // بر اساس نوع (admin/staff/customer) و کد ملی، کد ریفرال را برمی‌گرداند
    public function referralCodeByNational(Request $request)
    {
        // ابتدا ورودی‌ها را ساده می‌گیریم و بعداً type را نرمال می‌کنیم
        $data = $request->validate([
            'type'          => 'required|string',
            'national_code' => ['required', 'string', 'size:10', 'regex:/^\d{10}$/'],
        ]);

        // نرمال‌سازی type (قبول admin/ADMIN و staff/STAFF و …)
        $type = strtolower($data['type']);
        if (!in_array($type, ['admin', 'staff', 'customer'], true)) {
            return response()->json(['exists' => false, 'message' => 'نوع نامعتبر است.'], 422);
        }

        // مپ جدول‌ها
        $tableMap = [
            'admin'    => 'admin_users',
            'staff'    => 'staff',
            'customer' => 'customers',
        ];

        // اگر نام ستون کدملی در هر جدول متفاوت است، اینجا تنظیم کن
        $nationalColMap = [
            'admin'    => 'national_code',
            'staff'    => 'national_code',
            'customer' => 'national_code',
        ];

        // پیشوند هر نوع
        $prefixMap = [
            'admin'    => 'A-',
            'staff'    => 'S-',   // ← طبق تغییر شما
            'customer' => 'C-',
        ];

        $table = $tableMap[$type];
        $ncol  = $nationalColMap[$type];

        // جستجو بر اساس کدملی
        $row = DB::table($table)
            ->where($ncol, $data['national_code'])
            ->select('referral_code')
            ->first();

        if (!$row) {
            return response()->json(['exists' => false, 'message' => 'یافت نشد.'], 404);
        }

        $code = strtoupper((string) ($row->referral_code ?? ''));

        if ($code === '') {
            return response()->json(['exists' => false, 'message' => 'برای این شخص کد معرف ثبت نشده است.'], 404);
        }

        // صحت فرمت و هم‌خوانی با نوع
        $expectedPrefix = $prefixMap[$type];
        $formatOk = (bool) preg_match('/^(A|S|C)-[A-Z0-9]+$/', $code);
        if (strpos($code, $expectedPrefix) !== 0 || !$formatOk) {
            return response()->json(['exists' => false, 'message' => 'کد ذخیره‌شده با نوع هم‌خوانی ندارد.'], 422);
        }

        return response()->json([
            'exists' => true,
            'code'   => $code,
            'type'   => $type,
        ]);
    }



    /**
     * متد ثبت لاگ
     */
    protected function logActivity($action, $details = null)
    {
        ActivityLog::create([
            'admin_id'   => Auth::guard('admin')->id(),
            'ip_address' => request()->ip(),
            'action'     => $action,
            'details'    => $details,
        ]);
    }
}

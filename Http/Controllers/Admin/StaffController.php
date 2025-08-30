<?php
// app/Http/Controllers/Admin/StaffController.php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Helpers\JalaliHelper;
use Illuminate\Support\Facades\Log;

class StaffController extends Controller
{
    /**
     * لیست پرسنل
     */
    // app/Http/Controllers/Admin/StaffController.php

    public function index()
    {
        try {
            $staff = DB::table('staff')
                ->leftJoin('staff_payment_gateways', 'staff.id', '=', 'staff_payment_gateways.staff_id')
                ->leftJoin('bank_lists as bl', 'bl.id', '=', 'staff_payment_gateways.bank_name') // ← نام بانک
                ->select(
                    'staff.*',
                    'staff_payment_gateways.pos_terminal',
                    'staff_payment_gateways.bank_account',
                    'staff_payment_gateways.card_number',
                    'bl.name as bank_title',            // ← اضافه شد
                    'bl.short_name as bank_short_name', // ← اختیاری
                    DB::raw("(SELECT r.referrer_code 
              FROM referrals r 
              WHERE r.referred_type='staff' 
                AND r.referred_id=staff.id 
              ORDER BY r.id DESC 
              LIMIT 1) AS referrer_code_db"),
                    DB::raw("(SELECT r.referrer_type 
              FROM referrals r 
              WHERE r.referred_type='staff' 
                AND r.referred_id=staff.id 
              ORDER BY r.id DESC 
              LIMIT 1) AS referrer_type_db")
                )
                ->orderByDesc('staff.id')
                ->paginate(20);

            // مهارت‌ها (همان کد قبلی شما)
            $staffIds = $staff->pluck('id')->toArray();
            $skills = DB::table('staff_service_skills')
                ->join('service_categories', 'staff_service_skills.category_id', '=', 'service_categories.id')
                ->whereIn('staff_service_skills.staff_id', $staffIds)
                ->select('staff_service_skills.staff_id', 'service_categories.title')
                ->get()
                ->groupBy('staff_id');

            return panelView('admin', 'manage_staff.index', compact('staff', 'skills'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در مشاهده لیست پرسنل', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }


    /**
     * فرم ایجاد پرسنل
     */
    public function create()
    {
        try {
            $categories = DB::table('service_categories')->get();
            $banks = DB::table('bank_lists')
                ->orderByRaw('COALESCE(short_name, name)')
                ->get(['id', 'name', 'short_name']);
            $this->logActivity('نمایش فرم ثبت پرسنل', null);

            return panelView('admin', 'manage_staff.create', compact('categories', 'banks'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در نمایش فرم ثبت پرسنل', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }


    /**
     * ثبت پرسنل جدید
     */
    public function store(Request $request)
    {
        $request->merge(['is_active' => $request->input('is_active', 0)]);

        $validated = $request->validate([
            'staffusername' => 'required|string|max:100|unique:staff,staffusername',
            'password' => 'required|string|min:6|confirmed',
            'full_name' => 'required|string|max:100',
            'national_code' => 'required|string|size:10|unique:staff,national_code',
            'phone' => 'required|string|max:20|unique:staff,phone',
            'hire_date_jalali' => 'required|string',
            'is_active' => 'required|in:0,1',
            'categories' => 'required|array|min:1',
            'categories.*' => 'exists:service_categories,id',
            'referred_by' => [
                'nullable',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    if ($value && !preg_match('/^(A|S|C|N)-[A-Z0-9]+$/', strtoupper($value))) {
                        $fail('کد معرف اشتباه است.');
                    }
                },
            ],
            'pos_terminal' => 'required|string|max:50',
            'bank_account' => 'required|string|max:50',
            'card_number' => 'required|string|max:50',
            'bank_name'    => 'nullable|integer|exists:bank_lists,id', // ← NEW

        ]);

        // تبدیل تاریخ شمسی به میلادی
        $hireDateGregorian = JalaliHelper::toGregorian($validated['hire_date_jalali']);
        if (!$hireDateGregorian) {
            return back()->withErrors(['hire_date_jalali' => 'تاریخ استخدام نامعتبر است.'])->withInput();
        }
        $validated['hire_date'] = $hireDateGregorian;

        DB::beginTransaction();
        try {
            // ساخت کد ریفرال یونیک با پیشوند S-
            do {
                $referralCode = 'S-' . strtoupper(\Illuminate\Support\Str::random(6));
            } while (DB::table('staff')->where('referral_code', $referralCode)->exists());

            $staffId = DB::table('staff')->insertGetId([
                'staffusername' => $validated['staffusername'],
                'password_hash' => \Illuminate\Support\Facades\Hash::make($validated['password']),
                'full_name' => $validated['full_name'],
                'national_code' => $validated['national_code'],
                'phone' => $validated['phone'],
                'hire_date' => $validated['hire_date'],
                'is_active' => (int)$validated['is_active'],
                'referral_code' => $referralCode,
                'referred_by' => strtoupper($validated['referred_by'] ?? ''),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('staff_payment_gateways')->insert([
                'staff_id'     => $staffId,
                'pos_terminal' => $validated['pos_terminal'],
                'bank_account' => $validated['bank_account'],
                'card_number'  => $validated['card_number'],
                'bank_name'    => $validated['bank_name'] ?? null, // ← NEW
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);


            foreach ($validated['categories'] as $categoryId) {
                DB::table('staff_service_skills')->insert([
                    'staff_id' => $staffId,
                    'category_id' => $categoryId,
                    'can_do' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // ثبت در جدول referrals (اگر معرف وارد شده باشد)
            if ($request->filled('referred_by')) {
                $referrerCode = strtoupper($request->input('referred_by'));
                $referrerType = null;
                $referrerId = null;
                $prefix = substr($referrerCode, 0, 2);
                if ($prefix === 'C-') {
                    $referrer = DB::table('customers')->where('referral_code', $referrerCode)->first();
                    $referrerType = 'customer';
                } elseif ($prefix === 'S-') {
                    $referrer = DB::table('staff')->where('referral_code', $referrerCode)->first();
                    $referrerType = 'staff';
                } elseif ($prefix === 'A-') {
                    $referrer = DB::table('admin_users')->where('referral_code', $referrerCode)->first();
                    $referrerType = 'admin';
                }
                $referrerId = $referrer ? $referrer->id : null;

                if ($referrerId) {
                    DB::table('referrals')->insert([
                        'referrer_id'   => $referrerId,
                        'referrer_type' => $referrerType,
                        'referrer_code' => $referrerCode,
                        'referred_id'   => $staffId,
                        'referred_type' => 'staff',
                        'referred_code' => $referralCode,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }

            DB::commit();
            $this->logActivity('ثبت پرسنل جدید', 'staff_id: ' . $staffId . ' | نام: ' . $validated['full_name']);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logActivity('خطا در ثبت پرسنل جدید', $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطایی رخ داد: ' . $e->getMessage(),
            ], 500);
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'پرسنل با موفقیت ثبت شد. کد معرف: ' . $referralCode,
            ]);
        }
        return redirect('/admin/staff')->with('success', 'پرسنل با موفقیت ثبت شد. کد معرف: ' . $referralCode);
    }


    /**
     * فرم ویرایش پرسنل
     */
    public function edit($id)
    {
        try {
            $staff = DB::table('staff')->find($id);
            $categories = DB::table('service_categories')->get();
            $staffCategories = DB::table('staff_service_skills')->where('staff_id', $id)->pluck('category_id')->toArray();
            $paymentGateway = DB::table('staff_payment_gateways')->where('staff_id', $id)->first();
            $banks = DB::table('bank_lists')
                ->orderByRaw('COALESCE(short_name, name)')
                ->get(['id', 'name', 'short_name']);

            $this->logActivity('نمایش فرم ویرایش پرسنل', 'staff_id: ' . $id);

            $vars = compact('staff', 'categories', 'staffCategories', 'paymentGateway', 'banks');

            if (request()->ajax()) {
                return panelView('admin', 'manage_staff.partials.edit-form', $vars);
            }
            return panelView('admin', 'manage_staff.edit', $vars);
        } catch (\Exception $e) {
            $this->logActivity('خطا در نمایش فرم ویرایش پرسنل', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }


    /**
     * ویرایش پرسنل
     */
    public function update(Request $request, $id)
    {
        $request->merge([
            'is_active' => $request->has('is_active') ? 1 : 0
        ]);

        $validated = $request->validate([
            'full_name' => 'required|string|max:100',
            'national_code' => 'required|string|size:10|unique:staff,national_code,' . $id,
            'phone' => 'required|string|max:20|unique:staff,phone,' . $id,
            'hire_date_jalali' => 'required|string',
            'is_active' => 'required|in:0,1',
            'categories'   => 'required|array|min:1',
            'categories.*' => 'exists:service_categories,id',
            'referred_by' => [
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
            'pos_terminal' => 'required|string|max:50',
            'bank_account' => 'required|string|max:50',
            'card_number' => 'required|string|max:50',
            'bank_name'    => 'nullable|integer|exists:bank_lists,id', // ← NEW

        ]);

        // تبدیل تاریخ شمسی به میلادی
        $hireDateGregorian = JalaliHelper::toGregorian($validated['hire_date_jalali']);
        if (!$hireDateGregorian) {
            return back()->withErrors(['hire_date_jalali' => 'تاریخ استخدام نامعتبر است.'])->withInput();
        }
        $validated['hire_date'] = $hireDateGregorian;

        DB::beginTransaction();
        try {
            DB::table('staff')->where('id', $id)->update([
                'full_name'     => $validated['full_name'],
                'national_code' => $validated['national_code'],
                'phone'         => $validated['phone'],
                'hire_date'     => $validated['hire_date'],
                'is_active'     => $request->input('is_active', 0),
                'referred_by'   => strtoupper($validated['referred_by'] ?? ''),
                'updated_at'    => now(),
            ]);

            $gatewayExists = DB::table('staff_payment_gateways')->where('staff_id', $id)->exists();
            $dataGateway = [
                'pos_terminal' => $validated['pos_terminal'],
                'bank_account' => $validated['bank_account'],
                'card_number'  => $validated['card_number'],
                'bank_name'    => $validated['bank_name'] ?? null, // ← NEW
                'updated_at'   => now(),
            ];

            if ($gatewayExists) {
                DB::table('staff_payment_gateways')->where('staff_id', $id)->update($dataGateway);
            } else {
                $dataGateway['staff_id']  = $id;
                $dataGateway['created_at'] = now();
                DB::table('staff_payment_gateways')->insert($dataGateway);
            }



            DB::table('staff_service_skills')->where('staff_id', $id)->delete();
            foreach ($request->input('categories', []) as $categoryId) {
                DB::table('staff_service_skills')->insert([
                    'staff_id'    => $id,
                    'category_id' => $categoryId,
                    'can_do'      => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            // بروزرسانی یا ثبت جدول referrals (اگر معرف وارد شده باشد)
            if ($request->filled('referred_by')) {
                $referrerCode = strtoupper($request->input('referred_by'));
                $referrerType = null;
                $referrerId = null;
                $prefix = substr($referrerCode, 0, 2);
                if ($prefix === 'C-') {
                    $referrer = DB::table('customers')->where('referral_code', $referrerCode)->first();
                    $referrerType = 'customer';
                } elseif ($prefix === 'S-') {
                    $referrer = DB::table('staff')->where('referral_code', $referrerCode)->first();
                    $referrerType = 'staff';
                } elseif ($prefix === 'A-') {
                    $referrer = DB::table('admin_users')->where('referral_code', $referrerCode)->first();
                    $referrerType = 'admin';
                }
                $referrerId = $referrer ? $referrer->id : null;

                $myReferralCode = DB::table('staff')->where('id', $id)->value('referral_code');
                if ($referrerId) {
                    $exists = DB::table('referrals')
                        ->where('referred_id', $id)
                        ->where('referred_type', 'staff')
                        ->exists();

                    if ($exists) {
                        DB::table('referrals')
                            ->where('referred_id', $id)
                            ->where('referred_type', 'staff')
                            ->update([
                                'referrer_id'   => $referrerId,
                                'referrer_type' => $referrerType,
                                'referrer_code' => $referrerCode,
                                'referred_code' => $myReferralCode,
                                'updated_at'    => now(),
                            ]);
                    } else {
                        DB::table('referrals')->insert([
                            'referrer_id'   => $referrerId,
                            'referrer_type' => $referrerType,
                            'referrer_code' => $referrerCode,
                            'referred_id'   => $id,
                            'referred_type' => 'staff',
                            'referred_code' => $myReferralCode,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);
                    }
                }
            }

            DB::commit();
            $this->logActivity('ویرایش پرسنل', 'staff_id: ' . $id . ' | نام: ' . $validated['full_name']);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logActivity('خطا در ویرایش پرسنل', $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطایی رخ داد: ' . $e->getMessage(),
            ], 500);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'پرسنل با موفقیت ویرایش شد!']);
        }
        return redirect('/admin/staff')->with('success', 'پرسنل با موفقیت ویرایش شد!');
    }


    /**
     * حذف پرسنل
     */
public function destroy(Request $request, $id)
{
    DB::beginTransaction();

    try {
        // حذف وابسته‌ها
        DB::table('staff_payment_gateways')->where('staff_id', $id)->delete();
        DB::table('staff_service_skills')->where('staff_id', $id)->delete();
        DB::table('staff_skills_commission')->where('staff_id', $id)->delete();
        DB::table('staff_leaves')->where('staff_id', $id)->delete();
        DB::table('package_services')->where('staff_id', $id)->delete();

        // در آخر خودِ رکورد پرسنل
        DB::table('staff')->where('id', $id)->delete();

        // ثبت لاگ فعالیت
        $this->logActivity('حذف پرسنل', 'staff_id: ' . $id);

        DB::commit();

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'پرسنل با موفقیت حذف شد!']);
        }

        return redirect('/admin/staff')->with('success', 'پرسنل با موفقیت حذف شد!');

    } catch (\Exception $e) {
        DB::rollBack();
        $this->logActivity('خطا در حذف پرسنل', $e->getMessage());

        if ($request->ajax()) {
            return response()->json(['success' => false, 'message' => 'خطایی رخ داد: ' . $e->getMessage()], 500);
        }

        return redirect()->back()->with('error', 'خطایی رخ داد: ' . $e->getMessage());
    }
}


    /**
     * متد ثبت لاگ با try/catch
     */
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

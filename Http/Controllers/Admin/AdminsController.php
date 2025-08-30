<?php
// app/Http/Controllers/AdminsController.php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Helpers\JalaliHelper;

class AdminsController extends Controller
{
    /**
     * نمایش لیست مدیران
     */
    public function index()
    {
        $admins = DB::table('admin_users')->orderByDesc('id')->get();
        $this->logActivity('مشاهده لیست مدیران');
        // نمایش لیست مدیران از مسیر admin/manage_admins
        return panelView('admin', 'manage_admins.index', compact('admins'));
    }

    /**
     * نمایش فرم ایجاد مدیر جدید
     */
    public function create()
    {
        $this->logActivity('نمایش فرم ایجاد مدیر جدید');
        // نمایش فرم ایجاد مدیر از مسیر admin/manage_admins
        return panelView('admin', 'manage_admins.create');
    }

    /**
     * ذخیره مدیر جدید
     */
    public function store(Request $request)
    {

        $isSuper = (bool) optional(\Illuminate\Support\Facades\Auth::guard('admin')->user())->is_superadmin;

        $validated = $request->validate([
            'fullname'       => 'required|string|max:255',
            'phones'         => 'nullable|string|max:255',
            'addresses'      => 'nullable|string|max:255',
            'adminusername'   => 'required|string|max:100|unique:admin_users,adminusername',
            'password'        => 'required|string|min:6|confirmed',
            'is_superadmin'   => $isSuper ? 'nullable|boolean' : 'prohibited',

            'referred_by'     => [
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

        $hashedPassword = Hash::make($validated['password']);

        $adminId = DB::table('admin_users')->insertGetId([
            'fullname'      => $validated['fullname'],
            'phones'        => $validated['phones'] ?? null,
            'addresses'     => $validated['addresses'] ?? null,
            'adminusername' => $validated['adminusername'],
            'password_hash' => $hashedPassword,
            'is_superadmin' => $isSuper ? ($validated['is_superadmin'] ?? 0) : 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // تولید کد معرف یکتا
        do {
            $referralCode = 'A-' . strtoupper(Str::random(6));
            $exists = DB::table('admin_users')->where('referral_code', $referralCode)->exists();
        } while ($exists);

        DB::table('admin_users')->where('id', $adminId)->update([
            'referral_code' => $referralCode,
        ]);

        $this->logActivity('ایجاد مدیر جدید', 'نام کاربری: ' . $validated['adminusername'] . ' | آی‌دی: ' . $adminId);

        if ($request->ajax()) {
            // پاسخ JSON برای ایجکس
            return response()->json([
                'success'       => true,
                'message'       => 'مدیر با موفقیت ایجاد شد.',
                'data'          => [
                    'id'            => $adminId,
                    'adminusername' => $validated['adminusername'],
                    'referral_code' => $referralCode,
                ]
            ]);
        }

        return redirect()->route('admin.admins.index')->with('success', 'مدیر با موفقیت ایجاد شد.');
    }

    /**
     * نمایش فرم ویرایش مدیر
     */
    public function edit(Request $request, $id)
    {
        try {
            $admin = DB::table('admin_users')->find($id);

            if (!$admin) {
                $this->logActivity('تلاش برای ویرایش مدیر ناموجود', 'آی‌دی: ' . $id);
                if ($request->ajax()) {
                    return response()->json(['error' => 'مدیر یافت نشد.'], 404);
                }
                return redirect()->route('admin.admins.index')->with('error', 'مدیر یافت نشد.');
            }

            $this->logActivity('ورود به صفحه ویرایش مدیر', 'آی‌دی: ' . $id);

            // نمایش فرم ویرایش از مسیر admin/manage_admins
            if ($request->ajax()) {
                return panelView('admin', 'manage_admins.edit', compact('admin'));
            }
            return panelView('admin', 'manage_admins.edit', compact('admin'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در نمایش فرم ویرایش مدیر', $e->getMessage());
            if ($request->ajax()) {
                return response()->json(['error' => 'خطایی رخ داد. لطفا دوباره تلاش کنید.'], 500);
            }
            return redirect()->route('admin.admins.index')->with('error', 'خطایی رخ داد. لطفا دوباره تلاش کنید.');
        }
    }

    /**
     * بروزرسانی اطلاعات مدیر
     */
    public function update(Request $request, $id)
    {
        $admin = DB::table('admin_users')->find($id);

        if (!$admin) {
            $this->logActivity('تلاش برای ویرایش مدیر ناموجود', 'آی‌دی: ' . $id);
            if ($request->ajax()) {
                return response()->json(['error' => 'مدیر یافت نشد.'], 404);
            }
            return redirect()->route('admin.admins.index')->with('error', 'مدیر یافت نشد.');
        }
        $isSuper = (bool) optional(\Illuminate\Support\Facades\Auth::guard('admin')->user())->is_superadmin;

        $validated = $request->validate([
            'fullname'       => 'required|string|max:255',
            'phones'         => 'nullable|string|max:255',
            'addresses'      => 'nullable|string|max:255',

            'adminusername'   => 'required|string|max:100|unique:admin_users,adminusername,' . $id,
            'password'        => 'nullable|string|min:6|confirmed',
            'is_superadmin'   => $isSuper ? 'nullable|boolean' : 'prohibited',
        ]);

        $data = [
            'fullname'      => $validated['fullname'],
            'phones'        => $validated['phones'] ?? null,
            'addresses'     => $validated['addresses'] ?? null,

            'adminusername' => $validated['adminusername'],
            'is_superadmin' => $isSuper ? ($validated['is_superadmin'] ?? 0) : (int) $admin->is_superadmin,
            'updated_at'    => now(),
        ];

        if (!empty($validated['password'])) {
            $data['password_hash'] = Hash::make($validated['password']);
        }

        try {
            DB::table('admin_users')->where('id', $id)->update($data);

            $this->logActivity('ویرایش مدیر', 'آی‌دی: ' . $id . ' | نام کاربری جدید: ' . $validated['adminusername']);

            if ($request->ajax() || $request->wantsJson()) {
                // پاسخ موفقیت به صورت ایجکس
                return response()->json(['success' => true, 'message' => 'اطلاعات مدیر با موفقیت ویرایش شد.']);
            }
            return redirect()->route('admin.admins.index')->with('success', 'اطلاعات مدیر با موفقیت ویرایش شد.');
        } catch (\Exception $e) {
            $this->logActivity('خطا در ویرایش مدیر', $e->getMessage());
            if ($request->ajax()) {
                return response()->json(['error' => 'خطایی رخ داد. لطفا دوباره تلاش کنید.'], 500);
            }
            return redirect()->route('admin.admins.index')->with('error', 'خطایی رخ داد. لطفا دوباره تلاش کنید.');
        }
    }

    /**
     * حذف مدیر
     */
    public function destroy(Request $request, $id)
    {
        $admin = DB::table('admin_users')->find($id);

        if (!$admin) {
            $this->logActivity('تلاش برای حذف مدیر ناموجود', 'آی‌دی: ' . $id);
            if ($request->ajax()) {
                return response()->json(['error' => 'مدیر یافت نشد.'], 404);
            }
            return redirect()->route('admin.admins.index')->with('error', 'مدیر یافت نشد.');
        }

        // جلوگیری از حذف خود ادمین
        if (Auth::guard('admin')->id() == $id) {
            $this->logActivity('تلاش برای حذف مدیر', 'آی‌دی: ' . $id);
            return redirect()->route('admin.admins.index')->with('error', 'شما نمی‌توانید خودتان را حذف کنید.');
        }

        try {
            DB::table('admin_users')->where('id', $id)->delete();

            $this->logActivity('حذف مدیر', 'آی‌دی: ' . $id);

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => 'مدیر با موفقیت حذف شد.']);
            }

            return redirect()->route('admin.admins.index')->with('success', 'مدیر با موفقیت حذف شد.');
        } catch (\Exception $e) {
            $this->logActivity('خطا در حذف مدیر', $e->getMessage());
            if ($request->ajax()) {
                return response()->json(['error' => 'خطایی رخ داد. لطفا دوباره تلاش کنید.'], 500);
            }
            return redirect()->route('admin.admins.index')->with('error', 'خطایی رخ داد. لطفا دوباره تلاش کنید.');
        }
    }

    /**
     * متد ثبت لاگ اکشن‌های مدیر
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

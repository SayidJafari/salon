<?php
// app/Http/Controllers/CustomerAuthController.php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Helpers\JalaliHelper;

class CustomerAuthController extends Controller
{
    /**
     * نمایش فرم لاگین مشتری
     */
    public function showLoginForm()
    {
        // نمایش فرم ورود مشتری از مسیر admin/auth
        return panelView('admin', 'auth.customer_login');
    }

    /**
     * عملیات لاگین مشتری
     */
    public function login(Request $request)
    {
        $request->validate([
            'national_code' => 'required',
            'password' => 'required',
        ]);

        $customer = DB::table('customers')->where('national_code', $request->national_code)->first();

        if ($customer && Hash::check($request->password, $customer->password_hash)) {
        
            if ((int) $customer->is_suspended === 1) {
                return back()->withErrors(['account' => 'حساب کاربری شما معلق است.'])->withInput();
            }

            // ذخیره وضعیت لاگین در سشن
            session(['customer_logged_in' => true, 'customer_id' => $customer->id]);
            // ثبت لاگ ورود موفق مشتری
            $this->logActivity('ورود مشتری', 'ورود با کدملی: ' . $request->national_code);
            return redirect('/admin/customer/dashboard');
        }

        // ثبت لاگ ورود ناموفق مشتری
        $this->logActivity('ورود ناموفق مشتری', 'ورود با کدملی: ' . $request->national_code);

        return back()->withErrors(['national_code' => 'کدملی یا رمز نادرست است!'])->withInput();
    }

    /**
     * متد ثبت لاگ برای اکشن‌های مشتری
     */
    protected function logActivity($action, $details = null)
    {
        ActivityLog::create([
            // اینجا چون guard مربوط به admin نیست، می‌توانید مقدار admin_id را null بگذارید
            'admin_id'   => null,
            'ip_address' => request()->ip(),
            'action'     => $action,
            'details'    => $details,
        ]);
    }
}

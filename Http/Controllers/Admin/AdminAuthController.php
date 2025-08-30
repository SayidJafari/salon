<?php
// app/Http/Controllers/admin/AdminAuthController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Helpers\JalaliHelper;
use Illuminate\Support\Facades\Log;

class AdminAuthController extends Controller
{
    public function __construct()
    {
        // محدودیت دسترسی برای مهمان و لاگین و ریت لیمیت
        $this->middleware('guest:admin')->except('logout');
        $this->middleware('auth:admin')->only('logout');
        $this->middleware('throttle:5,1')->only('login');
    }

    /**
     * نمایش فرم ورود ادمین
     */
    public function showLoginForm()
    {
        try {
            // نمایش فرم ورود از شاخه admin/auth با استفاده از helper
            return panelView('admin', 'auth.admin_login');
        } catch (\Exception $e) {
            $this->logActivity('خطا در نمایش فرم ورود ادمین', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * عملیات لاگین ادمین
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'adminusername' => 'required|string',
            'password' => 'required|string',
        ]);
        try {
            if (Auth::guard('admin')->attempt([
                'adminusername' => $credentials['adminusername'],
                'password' => $credentials['password'],
            ])) {
                $request->session()->regenerate();
                $this->logActivity('ورود موفق', 'نام کاربری: ' . $credentials['adminusername']);
                return redirect()->intended(route('admin.dashboard'));
            }
            $this->logActivity('ورود ناموفق', 'نام کاربری: ' . $credentials['adminusername']);
            return back()->withErrors([
                'adminusername' => 'نام کاربری یا رمز عبور اشتباه است.',
            ])->onlyInput('adminusername');
        } catch (\Exception $e) {
            $this->logActivity('خطا در ورود ادمین', $e->getMessage());
            return back()->withErrors([
                'adminusername' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'
            ])->onlyInput('adminusername');
        }
    }

    /**
     * خروج ادمین
     */
    public function logout(Request $request)
    {
        try {
            $this->logActivity('خروج مدیر', 'مدیر خارج شد.');
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('admin.login');
        } catch (\Exception $e) {
            $this->logActivity('خطا در خروج ادمین', $e->getMessage());
            return redirect()->route('admin.login')->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * ثبت لاگ اکشن‌های مدیر با try/catch و user_agent در صورت نیاز
     */
    protected function logActivity($action, $details = null)
    {
        try {
            ActivityLog::create([
                'admin_id'   => Auth::guard('admin')->id(),
                'ip_address' => request()->ip(),
                'action'     => $action,
                'details'    => $details,
                // 'user_agent' => request()->header('User-Agent'), // اگر نیاز داشتی فعال کن
            ]);
        } catch (\Exception $e) {
            Log::error('Error in logActivity: ' . $e->getMessage(), ['action' => $action, 'details' => $details]);
        }
    }
}

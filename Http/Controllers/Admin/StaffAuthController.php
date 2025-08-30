<?php
// app/Http/Controllers/admin/StaffAuthController.php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash; // برای مقایسه امن رمز عبور
use Illuminate\Support\Facades\RateLimiter;
use App\Models\ActivityLog;
use App\Helpers\JalaliHelper;
use Illuminate\Support\Facades\Log;

class StaffAuthController extends Controller
{
    /**
     * نمایش فرم ورود پرسنل
     */
    public function showLoginForm()
    {
        try {
            $this->logActivity('مشاهده فرم ورود پرسنل', null);
            // استفاده از helper برای مسیر view جدید (resources/views/admin/)
            return panelView('admin', 'auth.staff_login');
        } catch (\Exception $e) {
            $this->logActivity('خطا در نمایش فرم ورود پرسنل', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * ورود پرسنل
     */
    public function login(Request $request)
    {
        // ریست محدودیت برای لاگین بعد از موفقیت
        $key = 'staff_login:' . $request->ip();
        $maxAttempts = 5;
        $decayMinutes = 1;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return back()->withErrors([
                'national_code' => 'تعداد تلاش بیش از حد مجاز! لطفاً کمی صبر کنید.'
            ])->withInput();
        }

        $request->validate([
            'national_code' => 'required',
            'password' => 'required',
        ]);

        try {
            $user = DB::table('staff')->where('national_code', $request->national_code)->first();

            // استفاده از Hash::check برای امنیت بیشتر رمز
            if ($user && Hash::check($request->password, $user->password_hash)) {

                if ((int) $user->is_active !== 1) {
                    return back()->withErrors(['account' => 'حساب پرسنل غیرفعال است.'])->withInput();
                }

                session(['staff_logged_in' => true, 'staff_id' => $user->id]);
                $this->logActivity('ورود موفق پرسنل', 'کدملی: ' . $request->national_code . ' | آی‌دی: ' . $user->id);
                RateLimiter::clear($key); // پاک‌کردن محدودیت بعد از موفقیت
                return redirect('/admin/staff/dashboard');
            }

            RateLimiter::hit($key, $decayMinutes * 60); // ثبت یک تلاش ناموفق
            $this->logActivity('ورود ناموفق پرسنل', 'کدملی: ' . $request->national_code);

            return back()->withErrors(['national_code' => 'کدملی یا رمز نادرست است!'])->withInput();
        } catch (\Exception $e) {
            $this->logActivity('خطا در ورود پرسنل', $e->getMessage());
            return back()->withErrors(['national_code' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'])->withInput();
        }
    }

    // متد ثبت لاگ با try/catch
    protected function logActivity($action, $details = null)
    {
        try {
            ActivityLog::create([
                'admin_id'   => Auth::guard('admin')->id(), // اگر گارد admin فعال نیست مقدار null می‌شود
                'ip_address' => request()->ip(),
                'action'     => $action,
                'details'    => $details,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in logActivity: ' . $e->getMessage(), ['action' => $action, 'details' => $details]);
        }
    }
}

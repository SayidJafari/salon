<?php
// app/Http/Controllers/admin/StaffCommissionController.php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Helpers\JalaliHelper;

class StaffCommissionController extends Controller
{
    /**
     * صفحه لیست کمسیون پرسنل
     */
    public function index()
    {
        try {
            $staff = DB::table('staff')->orderBy('full_name')->get();
            $this->logActivity('مشاهده صفحه کمسیون پرسنل', null);

            // نمایش view با استفاده از helper و مسیر جدید
            return panelView('admin', 'staff_commission.index', compact('staff'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در مشاهده صفحه کمسیون پرسنل', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * دریافت مهارت‌های یک پرسنل + کمسیون‌ها (AJAX)
     */
    public function getSkills($staff_id)
    {
        try {
            $skills = DB::table('staff_service_skills as sss')
                ->join('service_categories as sc', 'sss.category_id', '=', 'sc.id')
                ->where('sss.staff_id', $staff_id)
                ->where('sss.can_do', 1)
                ->select('sss.category_id', 'sc.title')
                ->get();

            $commissions = DB::table('staff_skills_commission')
                ->where('staff_id', $staff_id)
                ->get()
                ->keyBy('category_id');

            $result = [];
            foreach ($skills as $skill) {
                $commission = $commissions[$skill->category_id] ?? null;
                $result[] = [
                    'category_id'      => $skill->category_id,
                    'category_title'   => $skill->title,
                    'commission_type'  => $commission->commission_type ?? null,
                    'commission_value' => $commission->commission_value ?? null,
                ];
            }

            $this->logActivity('مشاهده مهارت‌های پرسنل', 'staff_id: ' . $staff_id);

            return response()->json(['skills' => $result]);
        } catch (\Exception $e) {
            $this->logActivity('خطا در دریافت مهارت‌های پرسنل', $e->getMessage());
            return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500);
        }
    }

    /**
     * ثبت یا بروزرسانی کمسیون‌های یک پرسنل
     */
    public function save(Request $request, $staff_id)
    {
        $data = $request->validate([
            'commissions'                   => 'required|array|min:1',
            'commissions.*.category_id'     => 'required|exists:service_categories,id',
            'commissions.*.commission_type' => 'required|in:percent,amount',
            'commissions.*.commission_value'=> 'required|numeric|min:0',
        ]);

        try {
            foreach ($data['commissions'] as $item) {
                DB::table('staff_skills_commission')->updateOrInsert(
                    [
                        'staff_id'    => $staff_id,
                        'category_id' => $item['category_id'],
                    ],
                    [
                        'commission_type'  => $item['commission_type'],
                        'commission_value' => $item['commission_value'],
                        'updated_at'       => now(),
                        'created_at'       => now(),
                    ]
                );
            }

            $this->logActivity('ذخیره کمسیون پرسنل', 'staff_id: ' . $staff_id);

            return response()->json(['success' => true, 'message' => 'کمسیون‌ها با موفقیت ذخیره شدند.']);
        } catch (\Exception $e) {
            $this->logActivity('خطا در ذخیره کمسیون پرسنل', $e->getMessage());
            return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500);
        }
    }

    /**
     * حذف کمسیون یک پرسنل برای یک دسته‌بندی
     */
    public function deleteCommission(Request $request, $staff_id, $category_id)
    {
        try {
            DB::table('staff_skills_commission')
                ->where('staff_id', $staff_id)
                ->where('category_id', $category_id)
                ->delete();

            $this->logActivity('حذف کمسیون پرسنل', 'staff_id: ' . $staff_id . ' | category_id: ' . $category_id);

            return response()->json(['success' => true, 'message' => 'کمسیون حذف شد!']);
        } catch (\Exception $e) {
            $this->logActivity('خطا در حذف کمسیون پرسنل', $e->getMessage());
            return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500);
        }
    }

    // متد ثبت لاگ با try/catch (تغییری نیاز ندارد)
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

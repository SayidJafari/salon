<?php
// app/Http/Controllers/admin/ActivityLogController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Helpers\JalaliHelper;

class ActivityLogController extends Controller
{
    public function index()
    {
        try {
            $logs = ActivityLog::with('admin')->latest()->paginate(30);
            return panelView('admin', 'activity_logs.index', compact('logs'));
        } catch (\Exception $e) {
            // اگر لازم داری همین‌جا لاگ مخصوص خطا بنویس:
            // \Log::error('خطا در مشاهده لاگ‌ها: ' . $e->getMessage());
            return redirect()->back()->with('ActivityLogController error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }
}

<?php
// app/Http/Controllers/Controller.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Helpers\JalaliHelper;

class Controller extends BaseController
{
    public function dashboard()
    {
        // === ثبت لاگ مشاهده داشبورد ===
        $this->logActivity('مشاهده داشبورد', 'مشاهده داشبورد مدیریتی');

        return view('layouts.admin.dashboard');
    }

    // متد ثبت لاگ
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

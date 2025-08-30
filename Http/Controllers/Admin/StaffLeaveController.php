<?php
// app/Http/Controllers/StaffLeaveController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StaffLeave;
use App\Models\Staff;
use Morilog\Jalali\Jalalian;
use App\Helpers\JalaliHelper;
use Illuminate\Support\Facades\Validator;

class StaffLeaveController extends Controller
{
    // تبدیل اعداد فارسی به لاتین
    public static function faToEn($number)
    {
        return strtr($number, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
        ]);
    }

    // تبدیل تاریخ شمسی (یا میلادی) به میلادی
    public static function toGregorian($date)
    {
        $date = self::faToEn($date ?? '');
        if (preg_match('/^\d{4}\/\d{2}\/\d{2}$/u', $date)) {
            // شمسی
            return Jalalian::fromFormat('Y/m/d', $date)->toCarbon()->toDateString();
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            // میلادی (از دیتابیس)
            return $date;
        } else {
            // نامعتبر
            return null;
        }
    }

    // لیست مرخصی‌ها
    public function index()
    {
        $leaves = StaffLeave::with('staff')->latest()->paginate(15);
        // اگر ajax می‌خواهی html خروجی بده
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'html'   => view('admin.staff_leaves.index', compact('leaves'))->render()
            ]);
        }
        return view('admin.staff_leaves.index', compact('leaves'));
    }

    // نمایش فرم ثبت مرخصی
    public function create()
    {
        $staff = Staff::all();
        return view('admin.staff_leaves.create', compact('staff'));
    }

    // ثبت مرخصی
    public function store(Request $request)
    {
        // مرحله ۱: فقط حضور فیلدها را چک کن
        $base = $request->validate([
            'staff_id'    => 'required|exists:staff,id',
            'leave_type'  => 'required',
            'start_date'  => 'required',
            'end_date'    => 'required',
            'start_time'  => 'nullable',
            'end_time'    => 'nullable',
            'description' => 'nullable|string',
        ]);

        // مرحله ۲: تبدیل با JalaliHelper
        $start = JalaliHelper::toGregorian($request->input('start_date'));
        $end   = JalaliHelper::toGregorian($request->input('end_date'));

        if (!$start || !$end) {
            return back()
                ->withErrors(['start_date' => 'فرمت تاریخ‌ها نامعتبر است.'])
                ->withInput();
        }

        // مرحله ۳: ولیدیشن وابسته به تاریخِ تبدیل‌شده
        $data = $base;
        $data['start_date'] = $start;
        $data['end_date']   = $end;
        $data['status']     = 'pending';

        Validator::make($data, [
            'end_date' => 'after_or_equal:start_date',
        ])->validate();

        StaffLeave::create($data);

        if ($request->ajax() || $request->wantsJson()) {
            $leaves = StaffLeave::with('staff')->latest()->paginate(15);
            return response()->json([
                'status' => 'success',
                'html'   => view('admin.staff_leaves.index', compact('leaves'))->render(),
            ]);
        }

        return redirect()->route('admin.staff_leaves.index')->with('success', 'مرخصی ثبت شد!');
    }


    // فرم ویرایش مرخصی
    public function edit($id)
    {
        $leave = StaffLeave::findOrFail($id);
        $staff = Staff::all();

        // فقط اگه میلادی معتبر بود به شمسی تبدیل کن:
        $leave->start_date_jalali = '';
        $leave->end_date_jalali   = '';
        if ($leave->start_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $leave->start_date)) {
            $leave->start_date_jalali = \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($leave->start_date))->format('Y/m/d');
        }
        if ($leave->end_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $leave->end_date)) {
            $leave->end_date_jalali = \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($leave->end_date))->format('Y/m/d');
        }

        return view('admin.staff_leaves.edit', compact('leave', 'staff'));
    }





    // به‌روزرسانی مرخصی
    // app/Http/Controllers/Admin/StaffLeaveController.php

    public function update(Request $request, $id)
    {
        $base = $request->validate([
            'staff_id'    => 'required|exists:staff,id',
            'leave_type'  => 'required',
            'start_date'  => 'required',
            'end_date'    => 'required',
            'start_time'  => 'nullable',
            'end_time'    => 'nullable',
            'description' => 'nullable|string',
        ]);

        $start = \App\Helpers\JalaliHelper::toGregorian($request->input('start_date'));
        $end   = \App\Helpers\JalaliHelper::toGregorian($request->input('end_date'));

        if (!$start || !$end) {
            return back()->withErrors(['start_date' => 'فرمت تاریخ‌ها نامعتبر است.'])->withInput();
        }

        $data = $base;
        $data['start_date'] = $start;
        $data['end_date']   = $end;

        Validator::make($data, [
            'end_date' => 'after_or_equal:start_date',
        ])->validate();

        StaffLeave::findOrFail($id)->update($data);

        // 👇 همین تکه باعث می‌شود AJAX شما موفقیت را بفهمد
        if ($request->ajax() || $request->wantsJson()) {
            $leaves = StaffLeave::with('staff')->latest()->paginate(15);
            return response()->json([
                'status' => 'success',
                'html'   => view('admin.staff_leaves.index', compact('leaves'))->render(),
            ]);
        }

        return redirect()->route('admin.staff_leaves.index')->with('success', 'مرخصی ویرایش شد!');
    }


    // تایید مرخصی
    public function approve(Request $request, $id)
    {
        $leave = StaffLeave::findOrFail($id);
        $leave->status = 'approved';
        $leave->approved_at = now();
        $leave->save();

        if ($request->ajax() || $request->wantsJson()) {
            $leaves = StaffLeave::with('staff')->latest()->paginate(15);
            return response()->json([
                'status' => 'success',
                'html'   => view('admin.staff_leaves.index', compact('leaves'))->render()
            ]);
        }
        return redirect()->route('admin.staff_leaves.index')->with('success', 'مرخصی تایید شد.');
    }

    // رد مرخصی
    public function reject(Request $request, $id)
    {
        $leave = StaffLeave::findOrFail($id);
        $leave->status = 'rejected';
        $leave->save();

        if ($request->ajax() || $request->wantsJson()) {
            $leaves = StaffLeave::with('staff')->latest()->paginate(15);
            return response()->json([
                'status' => 'success',
                'html'   => view('admin.staff_leaves.index', compact('leaves'))->render()
            ]);
        }
        return redirect()->route('admin.staff_leaves.index')->with('success', 'مرخصی رد شد.');
    }

    // حذف مرخصی
    public function destroy($id)
    {
        StaffLeave::destroy($id);
        return redirect()->route('admin.staff_leaves.index')->with('success', 'حذف شد.');
    }
}

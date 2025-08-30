<?php
// app/Http/Controllers/Admin/PackageCategoryController.php
namespace App\Http\Controllers\Admin;

use App\Models\PackageCategory;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Models\Staff; // لیست پرسنل
use App\Helpers\JalaliHelper;
use Illuminate\Support\Facades\Storage;

class PackageCategoryController extends Controller
{
    /**
     * نمایش لیست پکیج‌ها
     */
    public function index()
    {
        try {
            $packages = PackageCategory::with('services')->latest()->paginate(10);
            $services = ServiceType::orderBy('title')->get();

            // همه تخصص‌های فعال پرسنل، group by بر اساس category_id
            $skills = DB::table('staff_service_skills')
                ->where('can_do', 1)
                ->get()
                ->groupBy('category_id');

            // همه پرسنل
            $allStaffs = \App\Models\Staff::orderBy('full_name')->get()->keyBy('id');

            // برای هر خدمت، فقط پرسنل متخصص همان دسته‌بندی را پیدا کن
            foreach ($services as $srv) {
                $staff_ids = isset($skills[$srv->category_id]) ? $skills[$srv->category_id]->pluck('staff_id')->toArray() : [];
                $srv->specialist_staffs = collect($staff_ids)->map(function ($id) use ($allStaffs) {
                    return $allStaffs->get($id);
                })->filter();
            }

            $this->logActivity('مشاهده لیست پکیج‌ها', null);

            return panelView('admin', 'package-categories.index', compact('packages', 'services'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در مشاهده لیست پکیج‌ها', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * جزئیات خدمات داخل یک پکیج + پرسنل‌های مجاز هر خدمت
     */
    public function packageDetail($id)
    {
        $services = DB::table('package_services')
            ->where('package_category_id', $id)
            ->join('service_types', 'service_types.id', '=', 'package_services.service_type_id')
            ->select(
                'service_types.id as service_id',
                'service_types.title as service_title',
                'service_types.price as price',
                'package_services.staff_id as default_staff_id',
                'service_types.category_id as category_id'
            )
            ->get();

        foreach ($services as $srv) {
            $staffIds = DB::table('staff_service_skills')
                ->where('category_id', $srv->category_id)
                ->where('can_do', 1)
                ->pluck('staff_id');

            $allStaff = DB::table('staff')->whereIn('id', $staffIds)->where('is_active', 1)->get();

            // تبدیل به آرایه ساده
            $srv->all_staff = [];
            foreach ($allStaff as $s) {
                $srv->all_staff[] = [
                    'id' => $s->id,
                    'full_name' => $s->full_name,
                ];
            }
        }

        return response()->json([
            'services' => $services,
        ]);
    }

    /**
     * نمایش فرم افزودن پکیج جدید
     */
    public function create()
    {
        try {
            $services = ServiceType::orderBy('title')->get();
            $staffs = Staff::orderBy('full_name')->get(); // لیست پرسنل

            $this->logActivity('مشاهده فرم افزودن پکیج', null);

            // اگر از ویو index برای فرم استفاده می‌کنید، باید پرسنل را هم بفرستید
            return panelView('admin', 'package-categories.index', compact('services', 'staffs'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در مشاهده فرم افزودن پکیج', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * ذخیره پکیج جدید
     */
public function store(Request $request)
{
    $data = $request->validate([
        'name'        => 'required|string|max:255',
        'description' => 'nullable|string',
        'price'       => 'required|numeric|min:0',
        'image'       => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
        'is_active'   => 'boolean',
        'services'    => 'array',
        'services.*'  => 'integer|exists:service_types,id',
        'quantities'  => 'array',

        // --- کمیسیون معرف
        'ref_comm_enabled' => 'nullable|in:1',
    ]);

    // بررسی اعتبار کمیسیون معرف
    if ($request->boolean('ref_comm_enabled')) {
        $request->validate([
            'ref_comm_type'  => 'required|in:percent,amount',
            'ref_comm_value' => 'required|numeric|min:0',
        ]);
    }

    // مدیریت فایل تصویر
    if ($request->hasFile('image')) {
        $file = $request->file('image');
        $filename = uniqid() . '.' . $file->getClientOriginalExtension();
        Storage::disk('public')->putFileAs('packages', $file, $filename);
        $data['image'] = $filename;
    } else {
        $data['image'] = null;
    }

    // مقادیر امن برای ستون‌های کمیسیون
    $refEnabled = $request->boolean('ref_comm_enabled') ? 1 : 0;
    $refType    = $refEnabled ? $request->input('ref_comm_type')  : 'percent';
    $refValue   = $refEnabled ? (float) $request->input('ref_comm_value') : 0;

    try {
        DB::transaction(function () use ($data, $request, $refEnabled, $refType, $refValue) {
            $package = PackageCategory::create([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'price'       => $data['price'],
                'image'       => $data['image'] ?? null,
                'is_active'   => $data['is_active'] ?? true,

                // --- کمیسیون معرف
                'referrer_enabled'          => $refEnabled,
                'referrer_commission_type'  => $refType,
                'referrer_commission_value' => $refValue,
            ]);

            if (!empty($data['services'])) {
                $syncData = [];
                $staffs = $request->input('staffs', []);
                foreach ($data['services'] as $idx => $serviceId) {
                    $qty = $data['quantities'][$idx] ?? 1;
                    $staff_id = !empty($staffs[$serviceId]) ? $staffs[$serviceId] : null;
                    $syncData[$serviceId] = [
                        'quantity' => $qty,
                        'staff_id' => $staff_id,
                    ];
                }
                $package->services()->sync($syncData);
            }

            $this->logActivity('ایجاد پکیج', 'نام پکیج: ' . $package->name . ' | آی‌دی: ' . $package->id);
        });

        // پاسخ (هم‌راستا با فرم AJAX)
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'پک با موفقیت ساخته شد.']);
        }
        return redirect()->route('admin.package-categories.index')->with('success', 'پک با موفقیت ساخته شد.');
    } catch (\Exception $e) {
        $this->logActivity('خطا در ایجاد پکیج', $e->getMessage());
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500);
        }
        return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
    }
}


    /**
     * نمایش فرم ویرایش پکیج
     */
    public function edit(PackageCategory $packageCategory)
    {
        try {
            $services = ServiceType::orderBy('title')->get();

            // آرایه: کل پرسنل را می‌آورد
            $allStaffs = \App\Models\Staff::orderBy('full_name')->get();

            // آرایه: کل تخصص‌ها برای هر پرسنل
            $staffSkills = \App\Models\StaffServiceSkill::where('can_do', 1)->get();

            // آرایه: سرویس به پرسنل‌های متخصصش
            $serviceStaffs = [];
            foreach ($services as $service) {
                $serviceStaffs[$service->id] = $allStaffs->filter(function ($staff) use ($service, $staffSkills) {
                    return $staffSkills->where('staff_id', $staff->id)->where('category_id', $service->category_id)->count() > 0;
                });
            }

            // برای انتخاب پرسنل قبلاً انتخاب شده برای هر سرویس
            $selectedStaffs = [];
            foreach ($packageCategory->services as $srv) {
                $selectedStaffs[$srv->id] = $srv->pivot->staff_id;
            }

            return panelView('admin', 'package-categories.edit', [
                'package'        => $packageCategory,
                'services'       => $services,
                'serviceStaffs'  => $serviceStaffs,
                'selectedStaffs' => $selectedStaffs,
            ]);
        } catch (\Exception $e) {
            $this->logActivity('خطا در مشاهده فرم ویرایش پکیج', $e->getMessage());
            return redirect()->route('admin.package-categories.index')->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * بروزرسانی پکیج
     */
    public function update(Request $request, PackageCategory $packageCategory)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'image'       => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
            'is_active'   => 'boolean',
            'services'    => 'array',
            'services.*'  => 'integer|exists:service_types,id',
            'quantities'  => 'array',

            // --- کمیسیون معرف
            'ref_comm_enabled' => 'nullable|in:1',
        ]);

        if ($request->boolean('ref_comm_enabled')) {
            $request->validate([
                'ref_comm_type'  => 'required|in:percent,amount',
                'ref_comm_value' => 'required|numeric|min:0',
            ]);
        }

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('packages', 'public');
            $data['image'] = $imagePath;
        }





        // تصویر جدید؟
        if ($request->hasFile('image')) {
            if ($packageCategory->image && file_exists(public_path('uploads/packages/' . $packageCategory->image))) {
              //  @unlink(public_path('uploads/packages/' . $packageCategory->image));
                Storage::disk('public')->delete('packages/' . $packageCategory->image);

            }
            $file = $request->file('image');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
           // $file->move(public_path('uploads/packages'), $filename);
            Storage::disk('public')->putFileAs('packages', $file, $filename);

            $data['image'] = $filename;
        } else {
            $data['image'] = $packageCategory->image;
        }

        // مقادیر امن کمیسیون
        $refEnabled = $request->boolean('ref_comm_enabled') ? 1 : 0;
        $refType    = $refEnabled ? $request->input('ref_comm_type')  : 'percent';
        $refValue   = $refEnabled ? (float) $request->input('ref_comm_value') : 0;

        try {
            DB::transaction(function () use ($data, $packageCategory, $request, $refEnabled, $refType, $refValue) {
                $packageCategory->update([
                    'name'        => $data['name'],
                    'description' => $data['description'] ?? null,
                    'price'       => $data['price'],
                    'image'       => $data['image'] ?? null,
                    'is_active'   => $data['is_active'] ?? true,

                    // --- کمیسیون معرف
                    'referrer_enabled'          => $refEnabled,
                    'referrer_commission_type'  => $refType,
                    'referrer_commission_value' => $refValue,
                ]);

                $syncData = [];
                $staffs = $request->input('staffs', []);
                if (!empty($data['services'])) {
                    foreach ($data['services'] as $idx => $serviceId) {
                        $qty = $data['quantities'][$idx] ?? 1;
                        $staff_id = !empty($staffs[$serviceId]) ? $staffs[$serviceId] : null;
                        $syncData[$serviceId] = [
                            'quantity' => $qty,
                            'staff_id' => $staff_id,
                        ];
                    }
                }
                $packageCategory->services()->sync($syncData);

                $this->logActivity('ویرایش پکیج', 'نام پکیج: ' . $packageCategory->name . ' | آی‌دی: ' . $packageCategory->id);
            });

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'پک با موفقیت ویرایش شد.']);
            }
            return redirect()->route('admin.package-categories.index')->with('success', 'پک با موفقیت ویرایش شد.');
        } catch (\Exception $e) {
            $this->logActivity('خطا در ویرایش پکیج', $e->getMessage());
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500);
            }
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * حذف پکیج
     */
    public function destroy(PackageCategory $packageCategory)
    {
        try {
            // حذف فایل عکس در صورت وجود
            if ($packageCategory->image && file_exists(public_path('uploads/packages/' . $packageCategory->image))) {
               // @unlink(public_path('uploads/packages/' . $packageCategory->image));
                Storage::disk('public')->delete('packages/' . $packageCategory->image);

            }

            // ثبت لاگ حذف پکیج
            $this->logActivity('حذف پکیج', 'نام پکیج: ' . $packageCategory->name . ' | آی‌دی: ' . $packageCategory->id);

            $packageCategory->delete();

            return redirect()->route('admin.package-categories.index')->with('success', 'پک حذف شد.');
        } catch (\Exception $e) {
            $this->logActivity('خطا در حذف پکیج', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
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

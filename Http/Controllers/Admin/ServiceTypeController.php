<?php
// app/Http/Controllers/admin/ServiceTypeController.php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Helpers\JalaliHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ServiceTypeController extends Controller
{
    /**
     * نمایش فرم ثبت نوع خدمت جدید
     */
    public function create()
    {
        try {
            $categories = DB::table('service_categories')->get();
            $types = DB::table('service_types')->orderByDesc('id')->get();
            $this->logActivity('مشاهده فرم ثبت نوع خدمت', null);

            // نمایش view با helper و مسیر جدید (resources/views/admin/)
            return panelView('admin', 'service_types.create', compact('categories', 'types'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در مشاهده فرم ثبت نوع خدمت', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * ثبت نوع خدمت جدید
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:service_categories,id',
            'title' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',

            'ref_comm_enabled' => 'nullable|in:1',
        ]);
        if ($request->boolean('ref_comm_enabled')) {
            $request->validate([
                'ref_comm_type'  => 'required|in:percent,amount',
                'ref_comm_value' => 'required|numeric|min:0',
            ]);
        }

        try {
            // جلوگیری از ثبت تکراری
            $duplicate = DB::table('service_types')
                ->where('category_id', $request->category_id)
                ->where('title', $request->title)
                ->where(function ($query) use ($request) {
                    if ($request->filled('price')) {
                        $query->where('price', $request->price);
                    } else {
                        $query->whereNull('price');
                    }
                })->exists();

            if ($duplicate) {
                return back()->withErrors(['title' => 'این نوع خدمت با همین عنوان و قیمت قبلاً ثبت شده است.'])->withInput();
            }

            $category = DB::table('service_categories')->where('id', $request->category_id)->first();
            $folder = $category->folder ?? 'default';
            $folderPath = public_path('uploads/service_types/' . $folder);

            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0755, true);
            }

            $imageName = null;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '_' . \Illuminate\Support\Str::uuid() . '.' . $image->getClientOriginalExtension();
                // ذخیره در storage/app/public/service_types/{folder}
$image->move(public_path("uploads/service_types/{$folder}"), $imageName);
            }

            $refEnabled = $request->boolean('ref_comm_enabled') ? 1 : 0;
            $refType    = $refEnabled ? $request->input('ref_comm_type') : null;
            $refValue   = $refEnabled ? (float) $request->input('ref_comm_value') : null;

            $id = DB::table('service_types')->insertGetId([
                'category_id' => $request->category_id,
                'title' => $request->title,
                'price' => $request->price,
                'image' => $imageName,
                'created_at' => now(),
                'updated_at' => now(),
                'is_active' => 1,
                'referrer_enabled'   => $refEnabled,
                'referrer_commission_type'      => $refType,   // nullable وقتی غیرفعاله
                'referrer_commission_value'     => $refValue,  // nullable وقتی غیرفعاله


            ]);

            $this->logActivity('ایجاد نوع خدمت', 'عنوان: ' . $request->title . ' | آی‌دی: ' . $id);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'نوع خدمت با موفقیت ثبت شد.'
                ]);
            }
            return redirect()->route('admin.service-types.create')->with('success', 'نوع خدمت با موفقیت ثبت شد.');
        } catch (\Exception $e) {
            $this->logActivity('خطا در ثبت نوع خدمت', $e->getMessage());
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'
                ], 500);
            }
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * نمایش فرم ویرایش نوع خدمت
     */
    public function edit($id)
    {
        try {
            $editType = DB::table('service_types')->find($id);
            if (!$editType) abort(404);

            $categories = DB::table('service_categories')->get();
            $types = DB::table('service_types')->orderByDesc('id')->get();

            $this->logActivity('مشاهده فرم ویرایش نوع خدمت', 'آی‌دی: ' . $id);

            // نمایش view با helper و مسیر جدید (resources/views/admin/)
            return panelView('admin', 'service_types.create', compact('editType', 'categories', 'types'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در مشاهده فرم ویرایش نوع خدمت', $e->getMessage());
            return redirect()->route('service-types.create')->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * بروزرسانی نوع خدمت
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'category_id' => 'required|exists:service_categories,id',
            'title' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'ref_comm_enabled' => 'nullable|in:1',
        ]);
        if ($request->boolean('ref_comm_enabled')) {
            $request->validate([
                'ref_comm_type'  => 'required|in:percent,amount',
                'ref_comm_value' => 'required|numeric|min:0',
            ]);
        }
        $refEnabled = $request->boolean('ref_comm_enabled') ? 1 : 0;
        $refType    = $refEnabled ? $request->input('ref_comm_type') : null;
        $refValue   = $refEnabled ? (float) $request->input('ref_comm_value') : null;

        try {
            DB::table('service_types')->where('id', $id)->update([
                'category_id' => $request->category_id,
                'title'       => $request->title,
                'price'       => $request->price,
                'updated_at'  => now(),
                'referrer_enabled' => $refEnabled,
                'referrer_commission_type'    => $refType,
                'referrer_commission_value'   => $refValue,

            ]);

            $this->logActivity('ویرایش نوع خدمت', 'عنوان: ' . $request->title . ' | آی‌دی: ' . $id);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'نوع خدمت ویرایش شد.'
                ]);
            }
            return redirect()->route('service-types.create')->with('success', 'نوع خدمت ویرایش شد.');
        } catch (\Exception $e) {
            $this->logActivity('خطا در ویرایش نوع خدمت', $e->getMessage());
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'
                ], 500);
            }
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * حذف نوع خدمت
     */
    public function destroy(Request $request, $id)
    {
        try {
            $type = DB::table('service_types')->find($id);
            if (!$type) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'خدمت پیدا نشد!'
                    ]);
                }
                return back()->withErrors(['delete' => 'خدمت پیدا نشد!']);
            }

            $usedInPackage = DB::table('package_services')->where('service_type_id', $id)->exists();
            if ($usedInPackage) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'امکان حذف این خدمت وجود ندارد چون در یک پک خدمات استفاده شده است.'
                    ]);
                }
                return back()->withErrors(['delete' => 'امکان حذف این خدمت وجود ندارد چون در یک پک خدمات استفاده شده است.']);
            }



            $category = DB::table('service_categories')->where('id', $type->category_id)->first();
            $folder = $category->folder ?? 'default';

            if ($type->image) {
                $imagePath = public_path('uploads/service_types/' . $folder . '/' . $type->image);
                if (file_exists($imagePath)) {
                    if (!unlink($imagePath)) {
                        Log::warning('Image delete failed', ['path' => $imagePath, 'service_type_id' => $id]);
                    }
                }
            }



            DB::table('service_types')->where('id', $id)->delete();

            $this->logActivity('حذف نوع خدمت', 'عنوان: ' . ($type->title ?? '') . ' | آی‌دی: ' . $id);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'نوع خدمت و عکس با موفقیت حذف شد.'
                ]);
            }
            return redirect()->route('service-types.create')->with('success', 'نوع خدمت و عکس با موفقیت حذف شد.');
        } catch (\Exception $e) {
            $this->logActivity('خطا در حذف نوع خدمت', $e->getMessage());
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'
                ], 500);
            }
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    // متد ثبت لاگ با try/catch
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

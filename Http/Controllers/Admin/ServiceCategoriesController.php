<?php
// app/Http/Controllers/admin/ServiceCategoriesController.php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Helpers\StrHelper;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Helpers\JalaliHelper;

class ServiceCategoriesController extends Controller
{
    /**
     * نمایش لیست دسته‌بندی خدمات
     */
    public function index()
    {
        try {
            $categories = DB::table('service_categories')->get();
            $this->logActivity('مشاهده لیست دسته‌بندی خدمات', null);
            // نمایش view با helper و مسیر جدید
            return panelView('admin', 'service_categories.index', compact('categories'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در مشاهده لیست دسته‌بندی خدمات', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * نمایش فرم افزودن دسته‌بندی خدمات
     */
    public function create()
    {
        try {
            $categories = DB::table('service_categories')->get();
            $this->logActivity('مشاهده فرم افزودن دسته‌بندی خدمات', null);
            // نمایش view با helper و مسیر جدید
            return panelView('admin', 'service_categories.create', compact('categories'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در نمایش فرم افزودن دسته‌بندی', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * ثبت دسته‌بندی خدمات جدید
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $existsTitle = DB::table('service_categories')->where('title', $request->title)->exists();
            if ($existsTitle) {
                return back()->withErrors(['title' => 'دسته‌بندی با این عنوان قبلاً ثبت شده است.'])->withInput();
            }

            $slug = StrHelper::persianToEnglishSlug($validated['title']);
            $folderPath = public_path('uploads/service_types/' . $slug);
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0755, true); // یا ترجیحاً با Storage:
                // \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory("service_types/{$slug}");
            }

            $id = DB::table('service_categories')->insertGetId([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'folder' => $slug,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->logActivity('ایجاد دسته‌بندی خدمات', 'عنوان: ' . $validated['title'] . ' | آی‌دی: ' . $id);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'دسته‌بندی با موفقیت ثبت شد و فولدرش ساخته شد.'
                ]);
            }

            return redirect()->route('service-categories.index')->with('success', 'دسته‌بندی با موفقیت ثبت شد و فولدرش ساخته شد.');
        } catch (\Exception $e) {
            $this->logActivity('خطا در ثبت دسته‌بندی خدمات', $e->getMessage());
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
     * نمایش فرم ویرایش دسته‌بندی خدمات
     */
    public function edit($id)
    {
        try {
            $category = DB::table('service_categories')->find($id);
            if (!$category) return abort(404);

            $this->logActivity('مشاهده فرم ویرایش دسته‌بندی خدمات', 'آی‌دی: ' . $id);

            // نمایش view با helper و مسیر جدید
            return panelView('admin', 'service_categories.edit', compact('category'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در نمایش فرم ویرایش دسته‌بندی', $e->getMessage());
            return redirect()->route('service-categories.index')->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * بروزرسانی دسته‌بندی خدمات
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $existsTitle = DB::table('service_categories')
                ->where('title', $request->title)
                ->where('id', '!=', $id)
                ->exists();
            if ($existsTitle) {
                return back()->withErrors(['title' => 'دسته‌بندی با این عنوان قبلاً ثبت شده است.'])->withInput();
            }

            DB::table('service_categories')->where('id', $id)->update([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'updated_at' => now(),
            ]);

            $this->logActivity('ویرایش دسته‌بندی خدمات', 'عنوان: ' . $validated['title'] . ' | آی‌دی: ' . $id);

            return redirect()->route('service-categories.index')->with('success', 'دسته‌بندی ویرایش شد.');
        } catch (\Exception $e) {
            $this->logActivity('خطا در ویرایش دسته‌بندی خدمات', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * حذف دسته‌بندی خدمات
     */
    public function destroy(Request $request, $id)
    {
        try {
            $category = DB::table('service_categories')->find($id);
            $services = DB::table('service_types')->where('category_id', $id)->pluck('title')->toArray();

            if (count($services) > 0) {
                $serviceList = implode('، ', $services);
                $msg = "امکان حذف این دسته‌بندی وجود ندارد. خدمات زیر برای این دسته ثبت شده‌اند: $serviceList";
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $msg]);
                }
                return back()->withErrors(['delete' => $msg]);
            }

            DB::table('service_categories')->where('id', $id)->delete();

            $this->logActivity('حذف دسته‌بندی خدمات', 'آی‌دی: ' . $id);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'دسته‌بندی با موفقیت حذف شد.']);
            }
            return redirect()->route('service-categories.index')->with('success', 'دسته‌بندی با موفقیت حذف شد.');
        } catch (\Exception $e) {
            $this->logActivity('خطا در حذف دسته‌بندی خدمات', $e->getMessage());
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

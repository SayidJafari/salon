<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\{
    AdminAuthController,
    StaffAuthController,
    CustomerAuthController,
    Controller as AdminBaseController,
    AccountController,
    AdminsController,
    CustomerController,
    StaffController,
    ServiceCategoriesController,
    ServiceTypeController,
    PackageCategoryController,
    ReceivedCheckController,
    DiscountCodeController,
    StaffCommissionController
};

/*
|--------------------------------------------------------------------------
|  صفحه‌ی اصلی سایت
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
|  مسیرهای احراز هویت (مدیر / پرسنل / مشتری)
|--------------------------------------------------------------------------
*/

// -- مدیر --
Route::get('/admin/login',  [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->name('admin.login.submit')
    ->middleware('throttle:5,1');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

// -- پرسنل --
Route::get('/staff/login',  [StaffAuthController::class, 'showLoginForm'])->name('staff.login.form');
Route::post('/staff/login', [StaffAuthController::class, 'login'])
    ->name('staff.login')
    ->middleware('throttle:5,1');

// -- مشتری --
Route::get('/customer/login',  [CustomerAuthController::class, 'showLoginForm'])->name('customer.login.form');
Route::post('/customer/login', [CustomerAuthController::class, 'login'])
    ->name('customer.login')
    ->middleware('throttle:5,1');

/*
|--------------------------------------------------------------------------
|  مسیرهای پنل مدیریت (بعد از لاگین مدیر)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.admin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        |  داشبورد و گزارش‌ها
        |--------------------------------------------------------------------------
        */
        Route::get('/dashboard', [AdminBaseController::class, 'dashboard'])->name('dashboard');

        Route::get('activity_logs', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])
            ->name('activity_logs.index');

        /*
        |--------------------------------------------------------------------------
        |  حساب‌های سالن / سرویس‌های مرتبط با فاکتورها (Invoice)
        |--------------------------------------------------------------------------
        */

        // دریافت حساب‌های سالن (JSON)
        Route::get('/salon-accounts', [\App\Http\Controllers\Admin\InvoiceController::class, 'salonAccountsJson'])
            ->name('salon.accounts.json');

        // پرداخت تفکیکی (خلاصه + ثبت پرداخت‌های پرسنل/سالن + نهایی‌سازی)
        Route::get('/invoices/{invoice}/split-summary', [InvoiceController::class, 'splitSummary'])
            ->name('invoices.split.summary'); // توجه: مشابه روت پایین؛ عمداً حذف نشده


        Route::post('/invoices/{invoice}/split/pay-staff', [InvoiceController::class, 'paySplitStaff'])
            ->name('invoices.split.pay.staff');
        Route::post('/invoices/{invoice}/split/pay-salon', [InvoiceController::class, 'paySplitSalon'])
            ->name('invoices.split.pay.salon');
        Route::post('/invoices/{invoice}/split/finalize', [InvoiceController::class, 'finalizeSplit'])
            ->name('invoices.split.finalize');

        // بررسی کد تخفیف (AJAX)
        Route::get('discount-code/check', [\App\Http\Controllers\Admin\InvoiceController::class, 'checkDiscountCode'])
            ->name('invoices.check-discount-code');

        // اتو‌کمپلیت مشتری
        Route::get('customer-autocomplete', function (Request $request) {
            $validated = $request->validate([
                'q' => 'nullable|string|min:2|max:50'
            ]);

            $q = $validated['q'] ?? '';

            $list = DB::table('customers')
                ->where(function ($query) use ($q) {
                    $query->where('full_name', 'like', "%$q%")
                        ->orWhere('phone', 'like', "%$q%")
                        ->orWhere('national_code', 'like', "%$q%");
                })
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'full_name', 'phone', 'national_code']);

            return response()->json($list);
        })->middleware('throttle:30,1');

        // API های AJAX برای فرم صدور فاکتور
        Route::post('invoices/{invoice}/set-payment-type', [InvoiceController::class, 'setPaymentType'])
            ->name('invoices.setPaymentType');
        Route::post('invoices/{invoice}/pay-salon', [InvoiceController::class, 'paySalon'])
            ->name('invoices.paySalon');
        Route::post('invoices/{invoice}/items/{item}/pay-staff', [InvoiceController::class, 'payStaff'])
            ->name('invoices.payStaff');

        // گروه فاکتورها (پیش‌نویس / بیعانه / پرداخت تجمیعی / پرداخت تفکیکی)
        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::post('/draft', [InvoiceController::class, 'storeDraft'])->name('draft');                // ثبت موقت
            Route::post('/{invoice}/deposit', [InvoiceController::class, 'addDeposit'])->name('deposit');   // بیعانه رزرو
            Route::post('/{invoice}/pay/aggregate', [InvoiceController::class, 'payAggregate'])->name('pay.aggregate'); // پرداخت تجمیعی
            Route::post('/{invoice}/pay/split', [InvoiceController::class, 'paySplit'])->name('pay.split');              // پرداخت تفکیکی
        });

        // آیتم‌های معوق پورسانت در یک فاکتور (برای JS لیست فاکتورها)
        Route::get('invoices/{invoice}/pending-items', function (\App\Models\Invoice $invoice) {
            $items = DB::table('invoice_items as ii')
                ->join('service_types as st', 'st.id', '=', 'ii.service_type_id')
                ->leftJoin('staff as s', 's.id', '=', 'ii.staff_id')
                ->where('ii.invoice_id', $invoice->id)
                ->where('ii.staff_commission_status', 'pending')
                ->orderBy('ii.id')
                ->get([
                    'ii.id',
                    'ii.total',
                    'ii.staff_id',
                    'ii.staff_commission_amount',
                    'st.title as service_title',
                    's.full_name as staff_name'
                ]);
            return response()->json(['success' => true, 'items' => $items]);
        })->name('admin.invoices.pending-items');


        // ریسورس فاکتورها + اکشن‌های تکمیلی
        Route::resource('invoices', \App\Http\Controllers\Admin\InvoiceController::class);
        Route::post('invoices/{invoice}/finalize', [\App\Http\Controllers\Admin\InvoiceController::class, 'finalize'])
            ->name('invoices.finalize');
        Route::post('invoices/{invoice}/deposits', [\App\Http\Controllers\Admin\InvoiceController::class, 'addDeposit'])
            ->name('invoices.add-deposit');
        Route::delete('invoices/{invoice}/deposits/{income}', [\App\Http\Controllers\Admin\InvoiceController::class, 'removeDeposit'])
            ->name('invoices.remove-deposit');

        // حذف اجباری فاکتور
        Route::delete('invoices/{invoice}/force', [InvoiceController::class, 'forceDestroy'])
            ->name('invoices.force');




        // --- Aggregate (پرداخت‌های سالن) ---
        // ✅ درست: داخل گروه /admin هستیم، پس دیگر /admin اول مسیر نمی‌گذاریم
        Route::get('deposits/{salonIncome}',  [\App\Http\Controllers\Admin\InvoiceDepositController::class, 'show'])->name('deposits.show');
        Route::put('deposits/{salonIncome}',  [\App\Http\Controllers\Admin\InvoiceDepositController::class, 'update'])->name('deposits.update');
        Route::delete('deposits/{salonIncome}',  [\App\Http\Controllers\Admin\InvoiceDepositController::class, 'destroy'])->name('deposits.destroy');


        // --- Split (پرداخت‌های پرسنل) ---
        Route::get('invoices/{invoice}/staff-payments', [\App\Http\Controllers\Admin\InvoiceSplitPayController::class, 'listStaffPayments'])->name('admin.invoices.staff_payments');
        Route::put('staff-payments/{staffIncome}', [\App\Http\Controllers\Admin\InvoiceSplitPayController::class, 'updateStaffPayment'])->name('admin.staff_payments.update');
        Route::delete('staff-payments/{staffIncome}', [\App\Http\Controllers\Admin\InvoiceSplitPayController::class, 'destroyStaffPayment'])->name('admin.staff_payments.destroy');





        // پیش‌نمایش محاسبه پورسانت معرف
        Route::post('referrer/preview', [InvoiceController::class, 'previewReferrerCommission'])
            ->name('referrer.preview');

        /*
        |--------------------------------------------------------------------------
        |  کد معرف (Referral Code) — اندپوینت‌های کمکی
        |--------------------------------------------------------------------------
        */

        // بررسی وجود کد معرف
        Route::get('check-referral-code', function (Request $request) {
            $code   = strtoupper($request->query('code'));
            $prefix = substr($code, 0, 2); // C- , S- , A-
            $exists = false;
            $type   = null;

            if ($prefix === 'C-') {
                $exists = DB::table('customers')->where('referral_code', $code)->exists();
                $type   = 'customer';
            } elseif ($prefix === 'S-') {
                $exists = DB::table('staff')->where('referral_code', $code)->exists();
                $type   = 'staff';
            } elseif ($prefix === 'A-') {
                $exists = DB::table('admin_users')->where('referral_code', $code)->exists();
                $type   = 'admin';
            }

            return response()->json(['exists' => $exists, 'type' => $type]);
        });

        // یافتن کد معرف بر اساس کدملی مشتری
        Route::get('find-referral-by-national-code', function (Request $request) {
            $customer = DB::table('customers')->where('national_code', $request->query('national_code'))->first();
            return response()->json(['code' => $customer?->referral_code]);
        });

        // کد معرف بر اساس کدملی + نوع کاربر (Customer/Staff/Admin)
        Route::get('referral-code-by-national', function (Request $request) {
            $type  = $request->query('type'); // 'customer' | 'staff' | 'admin'
            $ncode = $request->query('national_code');

            if (!in_array($type, ['customer', 'staff', 'admin']) || !preg_match('/^\d{10}$/', (string) $ncode)) {
                return response()->json(['message' => 'پارامترها نامعتبر هستند.'], 422);
            }

            $map = [
                'customer' => ['table' => 'customers',   'nc' => 'national_code', 'rc' => 'referral_code', 'type' => 'customer'],
                'staff'    => ['table' => 'staff',       'nc' => 'national_code', 'rc' => 'referral_code', 'type' => 'staff'],
                'admin'    => ['table' => 'admin_users', 'nc' => 'national_code', 'rc' => 'referral_code', 'type' => 'admin'],
            ];

            $row = DB::table($map[$type]['table'])
                ->where($map[$type]['nc'], $ncode)
                ->first([$map[$type]['rc'] . ' as code']);

            if (!$row || !$row->code) {
                return response()->json(['exists' => false, 'code' => null, 'type' => $map[$type]['type']], 404);
            }

            return response()->json(['exists' => true, 'code' => $row->code, 'type' => $map[$type]['type']]);
        })->name('referral-code-by-national');

        /*
        |--------------------------------------------------------------------------
        |  کمیسیون پرسنل (Skills & Commission Rules)
        |--------------------------------------------------------------------------
        */
        Route::prefix('staff-commissions')->group(function () {
            Route::get('/', [StaffCommissionController::class, 'index'])->name('staff-commissions.index');
            Route::get('/{staff_id}/skills', [StaffCommissionController::class, 'getSkills'])->name('staff-commissions.skills');
            Route::post('/{staff_id}/save', [StaffCommissionController::class, 'save'])->name('staff-commissions.save');
            Route::delete('/{staff_id}/delete/{category_id}', [StaffCommissionController::class, 'deleteCommission'])->name('staff-commissions.delete');
        });

        // دریافت category_id بر اساس service_id (برای صفحه‌ی ویرایش)
        Route::get('service-category-of/{service_id}', function ($service_id) {
            $service_id = (int) $service_id; // ورژن 181
            return response()->json([
                'category_id' => DB::table('service_types')->where('id', $service_id)->value('category_id')
            ]);
        })->whereNumber('service_id');

        // استخراج مقدار کمیسیون پرسنل برای یک دسته‌بندی
        Route::get('staff-commission-value/{staff_id}/{category_id}', function ($staff_id, $category_id) {
            $staff_id    = (int) $staff_id;
            $category_id = (int) $category_id;

            if (!DB::table('staff')->where('id', $staff_id)->exists()) {
                return response()->json(['message' => 'پرسنل یافت نشد.'], 404);
            }
            if (!DB::table('service_categories')->where('id', $category_id)->exists()) {
                return response()->json(['message' => 'دسته‌بندی یافت نشد.'], 404);
            }

            $commission = DB::table('staff_skills_commission')
                ->where('staff_id', $staff_id)
                ->where('category_id', $category_id)
                ->first();

            return response()->json([
                'commission_type'  => $commission->commission_type  ?? 'percent',
                'commission_value' => $commission->commission_value ?? 0,
            ]);
        })->whereNumber('staff_id')->whereNumber('category_id');

        // پرداخت پورسانت (تراکنش) — با کنترل همزمانی/تکرار
        Route::post('pay-commission', function (Request $req) {
            // 1) ولیدیشن ورودی
            $validator = Validator::make($req->all(), [
                'staff_id'               => 'required|integer|exists:staff,id',
                'salonbankaccount_id'    => [
                    'required',
                    'integer',
                    Rule::exists('salonbankaccounts', 'id')->where(fn($q) => $q->where('is_active', 1)),
                ],
                'staffpaymentgateway_id' => 'nullable|integer|exists:staff_payment_gateways,id',
                'invoice_item_id'        => 'required|integer|exists:invoice_items,id',
                'amount'                 => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // 2) بررسی تعلق آیتم به همان پرسنل + وضعیت پرداخت‌نشده
            $item = DB::table('invoice_items')->where('id', $req->invoice_item_id)->first();
            if (!$item) {
                return response()->json(['success' => false, 'message' => 'آیتم یافت نشد.'], 404);
            }

            if ((int) $item->staff_id !== (int) $req->staff_id) {
                return response()->json(['success' => false, 'message' => 'این آیتم برای این پرسنل نیست.'], 422);
            }

            if ($item->staff_commission_status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'وضعیت پورسانت قابل پرداخت نیست.'], 422);
            }

            // 2.1) اگر gateway ارسال شد، بررسی تعلق به همان پرسنل
            if ($req->filled('staffpaymentgateway_id')) {
                $gatewayOk = DB::table('staff_payment_gateways')
                    ->where('id', $req->staffpaymentgateway_id)
                    ->where('staff_id', $req->staff_id)
                    ->exists();

                if (!$gatewayOk) {
                    return response()->json([
                        'success' => false,
                        'message' => 'درگاه پرداخت انتخابی متعلق به این پرسنل نیست.',
                    ], 422);
                }
            }

            // 3) جلوگیری از پرداخت تکراری
            $alreadyPaid = DB::table('staff_incomes')
                ->where('invoice_item_id', $req->invoice_item_id)
                ->where('commission_status', 'credit')
                ->exists();

            if ($alreadyPaid) {
                return response()->json([
                    'success' => false,
                    'message' => 'این پورسانت قبلاً پرداخت شده است.',
                ], 409);
            }

            // 4) تراکنش + هندل خطای یونیک
            try {
                DB::transaction(function () use ($req) {
                    DB::table('staff_incomes')->updateOrInsert(
                        [
                            'staff_id'        => $req->staff_id,
                            'invoice_id'      => DB::table('invoice_items')->where('id', $req->invoice_item_id)->value('invoice_id'),
                            'invoice_item_id' => $req->invoice_item_id,
                        ],
                        [
                            'amount'            => abs($req->amount),
                            'commission_status' => 'credit',
                            'created_at'        => now(),
                            'updated_at'        => now(),
                            'reference_number'  => $req->reference_number ?? null,
                        ]
                    );

                    DB::table('invoice_items')
                        ->where('id', $req->invoice_item_id)
                        ->update([
                            'staff_commission_status'  => 'paid',
                            'staff_commission_paid_at' => now(),
                            'updated_at'               => now(),
                        ]);
                });
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'UNIQUE')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'این پورسانت قبلاً پرداخت شده است.',
                    ], 409);
                }
                Log::error('Error in pay-commission: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'خطایی در پردازش درخواست رخ داد. لطفاً دوباره تلاش کنید.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'پرداخت پورسانت با موفقیت انجام شد.',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        |  مدیریت مرخصی‌ها (Leave)
        |--------------------------------------------------------------------------
        */
        Route::resource('staff_leaves', \App\Http\Controllers\Admin\StaffLeaveController::class);
        Route::post('staff_leaves/{id}/approve', [\App\Http\Controllers\Admin\StaffLeaveController::class, 'approve'])
            ->name('staff_leaves.approve');
        Route::post('staff_leaves/{id}/reject', [\App\Http\Controllers\Admin\StaffLeaveController::class, 'reject'])
            ->name('staff_leaves.reject');

        /*
        |--------------------------------------------------------------------------
        |  منابع اصلی (CRUD های عمومی)
        |--------------------------------------------------------------------------
        */
        Route::resources([
            'admins'             => AdminsController::class,
            'customers'          => CustomerController::class,
            'staff'              => StaffController::class,
            'service-categories' => ServiceCategoriesController::class,
            'service-types'      => ServiceTypeController::class,
            'package-categories' => PackageCategoryController::class,
            'received-checks'    => ReceivedCheckController::class,
            'accounts'           => AccountController::class,
            // 'discount-codes'   => DiscountCodeController::class,  // عمداً کامنت مانده
        ]);

        /*
        |--------------------------------------------------------------------------
        |  کدهای تخفیف (Discount Codes)
        |--------------------------------------------------------------------------
        */
        Route::get('discount-codes/list', [DiscountCodeController::class, 'list'])
            ->name('discount-codes.list');

        Route::resource('discount-codes', DiscountCodeController::class)->except(['show']);

        /*
        |--------------------------------------------------------------------------
        |  سرویس‌ها و پکیج‌ها (APIs برای فرم‌ها/فرانت)
        |--------------------------------------------------------------------------
        */

        // دریافت لیست خدمات هر دسته‌بندی
        Route::get('services-by-category/{category_id}', function (Request $request, $category_id) {
            if (!DB::table('service_categories')->where('id', $category_id)->exists()) {
                return response()->json(['message' => 'دسته‌بندی یافت نشد.'], 404);
            }

            // حتی در حالت full هم فیلد alias را برگردانیم تا فرانت تغییری نخواهد
            if ($request->boolean('full')) {
                return response()->json(
                    DB::table('service_types')
                        ->where('is_active', 1)
                        ->where('category_id', $category_id)
                        ->orderBy('title')
                        ->get([
                            'id',
                            'title',
                            'price',
                            'category_id',
                            'is_active',
                            'image',
                            DB::raw('referrer_enabled as referrer_commission_enabled'),
                            'referrer_commission_type',
                            'referrer_commission_value',
                        ])
                );
            }

            return response()->json(
                DB::table('service_types')
                    ->where('is_active', 1)
                    ->where('category_id', $category_id)
                    ->orderBy('title')
                    ->get([
                        'id',
                        'title',
                        'price',
                        'category_id',
                        DB::raw('referrer_enabled as referrer_commission_enabled'),
                        'referrer_commission_type',
                        'referrer_commission_value',
                    ])
            );
        })->whereNumber('category_id');

        // دریافت پرسنل ماهر در یک دسته‌بندی
        Route::get('staff-by-category/{category_id}', function ($category_id) {
            $staffIds = DB::table('staff_service_skills')
                ->where('category_id', $category_id)
                ->where('can_do', 1)
                ->pluck('staff_id');

            $staff = DB::table('staff')
                ->whereIn('id', $staffIds)
                ->where('is_active', 1)
                ->get();

            return response()->json($staff);
        })->whereNumber('category_id');

        // دریافت لیست پکیج‌ها بر اساس دسته‌بندی
        Route::get('packages-by-category/{category_id}', function ($category_id) {
            $packages = DB::table('package_categories as pc')
                ->join('package_services as ps', 'ps.package_category_id', '=', 'pc.id')
                ->join('service_types as st', 'st.id', '=', 'ps.service_type_id')
                ->where('pc.is_active', 1)
                ->where('st.category_id', $category_id)
                ->select(
                    'pc.id',
                    'pc.name',
                    'pc.price',
                    'pc.image',
                    DB::raw('pc.referrer_enabled as referrer_commission_enabled'),
                    'pc.referrer_commission_type',
                    'pc.referrer_commission_value'
                )
                ->distinct()
                ->orderBy('pc.name')
                ->get();

            foreach ($packages as $pkg) {
                $pkg->services = DB::table('package_services as ps')
                    ->join('service_types as st', 'st.id', '=', 'ps.service_type_id')
                    ->where('ps.package_category_id', $pkg->id)
                    ->select(
                        'st.id',
                        'st.title',
                        'st.price',
                        'st.category_id',
                        DB::raw('st.referrer_enabled as referrer_commission_enabled'),
                        'st.referrer_commission_type',
                        'st.referrer_commission_value'
                    )
                    ->get();
            }

            return response()->json($packages);
        })->whereNumber('category_id');

        // جزئیات پکیج
        Route::get('package-detail/{id}', [PackageCategoryController::class, 'packageDetail'])
            ->name('packages.detail')
            ->whereNumber('id');

        // موجودی کیف پول مشتری
        Route::get('customer-wallet-balance', function (Request $request) {
            $customer_id = $request->query('customer_id');
            $wallet = DB::table('customerswallets')->where('customer_id', $customer_id)->first();
            return response()->json([
                'balance' => $wallet ? $wallet->current_balance : 0
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        |  درگاه‌های پرداخت متعلق به پرسنل (برای UI اختیاری)
        |--------------------------------------------------------------------------
        */
        Route::get('staff-payment-gateways/{staff_id}', function ($staff_id) {
            $staff_id = (int) $staff_id;

            if (!DB::table('staff')->where('id', $staff_id)->exists()) {
                return response()->json(['message' => 'پرسنل یافت نشد.'], 404);
            }

            return DB::table('staff_payment_gateways as spg')
                ->leftJoin('bank_lists as b', 'b.id', '=', 'spg.bank_name')
                ->where('spg.staff_id', $staff_id)
                ->orderByDesc('spg.id')
                ->get([
                    'spg.id',
                    'spg.pos_terminal',
                    'spg.bank_account',
                    'spg.card_number',
                    'spg.bank_name', // آیدی بانک
                    DB::raw('COALESCE(b.name, NULL) as bank_title') // نام بانک
                ]);
        })->whereNumber('staff_id');
    });

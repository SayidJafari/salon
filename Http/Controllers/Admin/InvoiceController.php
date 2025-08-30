<?php
// app/Http/Controllers/Admin/InvoiceController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\DiscountCode;
use App\Helpers\JalaliHelper;
use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StaffIncome;
use App\Models\ReservationDeposit;
use App\Services\ReferrerCommissionService;
use App\Models\ReferrerIncome;
use App\Models\SalonIncome;                  // NEW
use Illuminate\Support\Facades\Auth;         // NEW
use Illuminate\Support\Facades\Schema;       // NEW: برای چک کردن ستون invoice_income_id در received_checks
use App\Models\AuditLog;


class InvoiceController extends Controller
{

    // اطلاعات یک پرداخت سالن برای مودال "ویرایش"
    public function getDeposit(\App\Models\SalonIncome $income)
    {
        return response()->json([
            'id'               => (int)$income->id,
            'invoice_id'       => (int)$income->invoice_id,
            'amount'           => (float)$income->amount,
            'method'           => $income->payment_method,
            'reference_number' => $income->reference_number,
            'note'             => $income->note,
            'paid_at'          => $income->paid_at ? (string)$income->paid_at : null,
            'salon_account_id' => $income->salon_account_id,
            'status'           => $income->status,
        ]);
    }

    // حذف پرداخت سالن (سازگار با JS: DELETE /admin/deposits/{id})
    public function destroyDeposit(\App\Models\SalonIncome $income, Request $request)
    {
        $invoice = Invoice::findOrFail($income->invoice_id);

        DB::transaction(function () use ($income, $invoice) {

            // اگر از کیف‌پول بوده، مبلغ را به کیف‌پول برگردان
            if (($income->payment_method ?? null) === 'wallet') {
                $w = DB::table('customerswallets')->where('customer_id', $invoice->customer_id)->lockForUpdate()->first();
                $bal = (float)($w->current_balance ?? 0);
                DB::table('customerswallets')->where('customer_id', $invoice->customer_id)
                    ->update(['current_balance' => $bal + (float)$income->amount, 'last_updated' => now()]);
            }

            // اگر چک لینک شده دارد، حذفش کن
            if (\Illuminate\Support\Facades\Schema::hasColumn('received_checks', 'invoice_income_id')) {
                DB::table('received_checks')->where('invoice_income_id', $income->id)->delete();
            }

            // خود پرداخت
            DB::table('salon_incomes')->where('id', $income->id)->delete();

            // به‌روزرسانی وضعیت پرداخت فاکتور
            $this->refreshInvoicePaymentStatus($invoice);
        });

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => 'پرداخت حذف شد.'])
            : back()->with('success', 'پرداخت حذف شد.');
    }



    public function edit(\App\Models\Invoice $invoice)
    {
        // Preload روابط لازم برای ویرایش
        $invoice->load([
            'items.serviceType:id,title,price,category_id',
            'items.staff:id,full_name',
            'items.staffIncome',                // لود پرداخت‌های تفکیکی پرسنل
            'items.staff.paymentGateways',      // روش‌های پرداخت پرسنل
            'customer:id,full_name,phone',
            'incomes',                          // پرداخت‌های سالن
            'staffIncomes.staff',               // پرداخت‌های پرسنل
        ]);

        // ---- آیتم‌های غیرپکیج
        $singleItems = $invoice->items->whereNull('from_package_category_id')->map(function ($it) {
            $commissionType  = !is_null($it->staff_commission_percent) ? 'percent'
                : (!is_null($it->staff_commission_amount) ? 'amount' : null);
            $commissionValue = !is_null($it->staff_commission_percent) ? (float)$it->staff_commission_percent
                : (!is_null($it->staff_commission_amount) ? (float)$it->staff_commission_amount : null);

            return [
                'type'           => 'service',
                'service_id'    => (int)$it->service_type_id,
                'service_title' => optional($it->serviceType)->title,
                'category_id'   => optional($it->serviceType)->category_id,
                'staff_id'      => (int)$it->staff_id,
                'staff_title'   => optional($it->staff)->full_name,
                'quantity'      => (int)$it->quantity,
                'price'         => (float)$it->price,
                'total'         => (float)$it->total,
                'commission_type'  => $commissionType,
                'commission_value' => $commissionValue,
            ];
        });

        // ---- آیتم‌های پکیج (گروهبندی بر اساس from_package_category_id)
        $pkgGroups = $invoice->items->whereNotNull('from_package_category_id')
            ->groupBy('from_package_category_id');

        $packageItems = $pkgGroups->map(function ($group, $pkgId) {
            $pkg = \App\Models\PackageCategory::find($pkgId);

            return [
                'type'          => 'package',
                'service_id'    => (int)$pkgId,
                'service_title' => $pkg ? $pkg->name : ("پکیج #{$pkgId}"),
                'package_id'    => (int)$pkgId,
                'package_title' => $pkg ? $pkg->name : ("پکیج #{$pkgId}"),
                'price'         => $pkg ? (float)$pkg->price : 0.0,
                'quantity'      => 1,
                'staff_title'   => '-',
                'services'      => $group->map(function ($it) {
                    $commissionType  = !is_null($it->staff_commission_percent) ? 'percent'
                        : (!is_null($it->staff_commission_amount) ? 'amount' : null);
                    $commissionValue = !is_null($it->staff_commission_percent) ? (float)$it->staff_commission_percent
                        : (!is_null($it->staff_commission_amount) ? (float)$it->staff_commission_amount : null);

                    return [
                        'service_id'    => (int)$it->service_type_id,
                        'service_title' => optional($it->serviceType)->title,
                        'price'         => (float)$it->price,
                        'quantity'      => (int)$it->quantity,
                        'staff_id'      => (int)$it->staff_id,
                        'staff_title'   => optional($it->staff)->full_name,
                        'commission_type'  => $commissionType,
                        'commission_value' => $commissionValue,
                    ];
                })->values(),
            ];
        })->values();

        $itemsPreload = $singleItems->values()->merge($packageItems)->values();

        // ---- تخفیف
        $discountPayload = null;
        if ($invoice->discount_code_id && $invoice->relationLoaded('discountCode')) {
            $discountPayload = [
                'id'            => (int)$invoice->discount_code_id,
                'code'          => $invoice->discountCode->code,
                'discount_type' => $invoice->discountCode->discount_type,
                'value'         => (float)$invoice->discountCode->value,
            ];
        }

        // دیتاهای کمکی برای فرم
        $categories    = \App\Models\ServiceCategory::orderBy('title')->get(['id', 'title']);
        $packages      = \App\Models\PackageCategory::orderBy('name')->get(['id', 'name', 'price']);
        $salonAccounts = \App\Models\SalonBankAccount::orderBy('title')->get();

        return view('admin.invoices.edit', [
            'invoice'         => $invoice,
            'categories'      => $categories,
            'packages'        => $packages,
            'salonAccounts'   => $salonAccounts,
            'itemsPreload'    => $itemsPreload,
            'discountPayload' => $discountPayload,
        ]);
    }



    // app/Http/Controllers/Admin/InvoiceController.php

    public function update(Request $request, Invoice $invoice)
    {
        // ✅ اجازهٔ ویرایش حتی اگر فاکتور Final باشد: آن را موقتاً Draft کنیم
        if ($invoice->invoice_status === 'final') {
            // اگر نمی‌خواهید Finalها قابل ویرایش باشند، فقط همین بلاک را حذف/کامنت کنید.
            DB::table('invoices')->where('id', $invoice->id)->update(['invoice_status' => 'draft']);
            $invoice->invoice_status = 'draft';
        }

        // ===== اعتبارسنجی ورودی
        $v = Validator::make($request->all(), [
            'customer_id'       => 'required|exists:customers,id',
            'items'             => 'required|string',    // JSON
            'payment_type'      => 'required|in:aggregate,split',
            'registration_date' => 'nullable|string',    // جلالی
            'discount_code'     => 'nullable|string',
        ]);

        if ($v->fails()) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'errors' => $v->errors()], 422)
                : back()->withErrors($v)->withInput();
        }

        // تبدیل تاریخ جلالی به میلادی
        $p = $request->all();
        if (!empty($p['registration_date'])) {
            $p['registration_date'] = \App\Helpers\JalaliHelper::toGregorianDateTime($p['registration_date']);
            $request->replace($p);
        }

        try {
            $invoiceId = DB::transaction(function () use ($request, $invoice) {

                // 1) پارس آیتم‌ها
                $rows = json_decode($request->input('items', '[]'), true);
                if (!is_array($rows)) {
                    throw new \Exception('ساختار آیتم‌ها نامعتبر است.');
                }

                // 2) آیتم‌های موجود برای تشخیص حذف/ویرایش
                $existing = DB::table('invoice_items')
                    ->where('invoice_id', $invoice->id)
                    ->get()
                    ->keyBy('id');

                $keepIds = [];
                $total   = 0.0;
                $now     = now();

                // کمکی محاسبهٔ کمیسیون


                $calcCommission = function ($commission_type, $commission_value, $price, $qty) {
                    if (!$commission_type || $commission_value === null || $commission_value === '') return [null, null, 0.0];
                    $qty   = max(1, (int)$qty);
                    $price = (float)$price;
                    if ($commission_type === 'percent') {
                        $amount = floor(($price * $qty) * ((float)$commission_value / 100));
                        return ['percent', (float)$commission_value, (float)$amount];
                    }
                    $amount = (float)$commission_value * $qty;  // ← اضافه کن: ضرب در qty برای amount-based
                    return ['amount', (float)$commission_value, (float)$amount];
                };

                $insertItem = function (array $payload) use (&$total, &$keepIds) {
                    $id = DB::table('invoice_items')->insertGetId($payload);
                    $keepIds[] = $id;
                    $total += (float)$payload['total'];
                };

                $upsertItem = function (int $itemId, array $payload) use (&$total, &$keepIds, $existing) {
                    $ex = $existing[$itemId] ?? null;
                    if ($ex && ($ex->staff_commission_status ?? 'pending') !== 'pending') {
                        throw new \Exception("آیتم #{$itemId} قبلاً پورسانتش پرداخت شده و قابل ویرایش نیست.");
                    }
                    DB::table('invoice_items')->where('id', $itemId)->update($payload);
                    $keepIds[] = $itemId;
                    $total += (float)$payload['total'];
                };

                foreach ($rows as $row) {
                    $type = $row['type'] ?? 'service';

                    if ($type === 'package') {
                        $services = $row['services'] ?? [];
                        foreach ($services as $srv) {
                            $serviceId = (int)($srv['service_id'] ?? 0);
                            $staffId   = (int)($srv['staff_id'] ?? 0);
                            $qty       = max(1, (int)($srv['quantity'] ?? 1));
                            $price     = (float)($srv['price'] ?? 0);
                            $totalRow  = $price * $qty;

                            [$ctype, $cval] = $calcCommission($srv['commission_type'] ?? null, $srv['commission_value'] ?? null, $price, $qty);

                            $payload = [
                                'invoice_id'               => $invoice->id,
                                'service_type_id'          => $serviceId,
                                'staff_id'                 => $staffId,
                                'quantity'                 => $qty,
                                'price'                    => $price,
                                'total'                    => $totalRow,
                                'item_status'              => 'pending',
                                'staff_commission_amount'  => $ctype === 'amount'  ? $cval : null,
                                'staff_commission_percent' => $ctype === 'percent' ? $cval : null,
                                'created_at'               => $row['date'] ?? $now,
                                'updated_at'               => $now,
                            ];
                            $insertItem($payload);
                        }
                    } else {
                        $serviceId = (int)($row['service_id'] ?? 0);
                        $staffId   = (int)($row['staff_id'] ?? 0);
                        $qty       = max(1, (int)($row['quantity'] ?? 1));
                        $price     = (float)($row['price'] ?? 0);
                        $totalRow  = $price * $qty;

                        [$ctype, $cval] = $calcCommission($row['commission_type'] ?? null, $row['commission_value'] ?? null, $price, $qty);

                        $payload = [
                            'service_type_id'          => $serviceId,
                            'staff_id'                 => $staffId,
                            'quantity'                 => $qty,
                            'price'                    => $price,
                            'total'                    => $totalRow,
                            'item_status'              => 'pending',
                            'staff_commission_amount'  => $ctype === 'amount'  ? $cval : null,
                            'staff_commission_percent' => $ctype === 'percent' ? $cval : null,
                            'updated_at'               => $now,
                        ];

                        $itemId = (int)($row['id'] ?? 0);
                        if ($itemId && isset($existing[$itemId])) {
                            $upsertItem($itemId, $payload);
                        } else {
                            $payload['invoice_id'] = $invoice->id;
                            $payload['created_at'] = $row['date'] ?? $now;
                            $insertItem($payload);
                        }
                    }
                }

                // حذف آیتم‌هایی که از فرم برداشته شده‌اند
                $toDelete = array_diff($existing->keys()->toArray(), $keepIds);
                if (!empty($toDelete)) {
                    $blocked = DB::table('invoice_items')
                        ->whereIn('id', $toDelete)
                        ->where('staff_commission_status', '!=', 'pending')
                        ->pluck('id')->toArray();
                    if (!empty($blocked)) {
                        throw new \Exception('برخی آیتم‌ها قبلاً تسویه کمیسیون شده‌اند و قابل حذف نیستند: ' . implode(',', $blocked));
                    }
                    DB::table('invoice_items')->whereIn('id', $toDelete)->delete();
                }

                // هدر فاکتور + تخفیف
                $invoice->customer_id       = (int)$request->input('customer_id');
                $invoice->payment_type      = $request->input('payment_type');
                $invoice->registration_date = $request->input('registration_date') ?: $invoice->registration_date;

                $discountId     = null;
                $discountAmount = 0.0;
                $finalAmount    = $total;
                $nowCarbon      = \Carbon\Carbon::now();

                if ($request->filled('discount_code')) {
                    $discount = \App\Models\DiscountCode::where('code', $request->input('discount_code'))
                        ->where('is_active', 1)->lockForUpdate()->first();
                    if ($discount && $this->discountUsableNow($discount, $nowCarbon)) {
                        if (is_null($discount->usage_limit) || $discount->times_used < $discount->usage_limit) {
                            $discountAmount = $discount->discount_type === 'percent'
                                ? floor($total * $discount->value / 100)
                                : min($total, (float)$discount->value);
                            $finalAmount = $total - $discountAmount;
                            $discountId  = $discount->id;
                        }
                    }
                }

                $invoice->total_amount     = $total;
                $invoice->discount_amount  = $discountAmount;
                $invoice->final_amount     = $finalAmount;
                $invoice->discount_code_id = $discountId;
                $invoice->updated_at       = $now;
                $invoice->save();

                // وضعیت پرداخت را بر مبنای salon_incomes به‌روز کن
                $this->refreshInvoicePaymentStatus($invoice->fresh());

                return $invoice->id;
            });

            return $request->expectsJson()
                ? response()->json(['success' => true, 'id' => $invoiceId])
                : redirect()->route('admin.invoices.edit', $invoice->id)->with('success', 'فاکتور ویرایش شد.');
        } catch (\Throwable $e) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $e->getMessage()], 422)
                : back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }


    // اگر همه‌چیز تسویه شد، فاکتور را Final کن
    private function finalizeIfSettled(Invoice $invoice): void
    {
        $invoice   = $invoice->fresh();
        $statusNow = $this->refreshInvoicePaymentStatus($invoice);

        // حالت تجمیعی: کافی است کل فاکتور paid باشد
        if ($invoice->payment_type === 'aggregate') {
            if ($statusNow === 'paid' && $invoice->invoice_status !== 'final') {
                DB::table('invoices')->where('id', $invoice->id)->update(['invoice_status' => 'final']);
            }
            return;
        }

        // حالت تفکیکی: هم سهم سالن و هم کمیسیون همه‌ی پرسنل باید صفر شود
        if ($invoice->payment_type === 'split') {
            $sum = $this->buildSplitSummary($invoice);

            $staffDue = 0.0;
            foreach ($sum['staff'] as $st) {
                $staffDue += (float)($st['due'] ?? 0);
            }
            $salonDue = (float)($sum['salon']['due'] ?? 0);
            $eps = 0.001;
            if ($statusNow === 'paid' && abs($staffDue) <= $eps && abs($salonDue) <= $eps && $invoice->invoice_status !== 'final') {
                DB::table('invoices')->where('id', $invoice->id)->update(['invoice_status' => 'final']);
            }
        }
    }

    public function salonAccountsJson()
    {
        $list = DB::table('salonbankaccounts')
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->get([
                'id',
                'title',
                'bank_name',
                'account_number',
                'card_number',
                'pos_terminal',
            ]);

        return response()->json($list);
    }

    public function pendingItems(Invoice $invoice)
    {
        $items = DB::table('invoice_items')
            ->where('invoice_id', $invoice->id)
            ->where('staff_commission_status', '!=', 'paid')
            ->leftJoin('staff', 'staff.id', '=', 'invoice_items.staff_id')
            ->leftJoin('service_types', 'service_types.id', '=', 'invoice_items.service_type_id')
            ->get([
                'invoice_items.id',
                'service_types.title as service_title',
                'staff.full_name as staff_name',
                'invoice_items.staff_id',
                'invoice_items.staff_commission_amount',
            ]);

        return response()->json(['items' => $items]);
    }

    // مجموع پرداخت‌های ثبت‌شده این فاکتور از جدول salon_incomes
    private function sumInvoiceIncomes(int $invoiceId, ?string $status = 'posted'): float
    {
        $q = DB::table('salon_incomes')->where('invoice_id', $invoiceId);
        if ($status !== null) $q->where('status', $status);
        return (float) $q->sum('amount');
    }

    // محاسبه و به‌روزرسانی status فاکتور فقط بر اساس salon_incomes (+ سازگاری با paid_amount قدیمی)
    private function refreshInvoicePaymentStatus(Invoice $invoice): string
    {
        // مبلغ پرداخت‌شده‌ی سالن
        $sumSalon = (float) DB::table('salon_incomes')
            ->where('invoice_id', $invoice->id)
            ->where('status', 'posted')
            ->sum('amount');

        // در حالت تفکیکی، پرداخت‌های پرسنل را هم به عنوان بخشی از پرداخت مشتری حساب کن
        $sumStaff = 0.0;
        if ($invoice->payment_type === 'split') {
            $sumStaff = (float) DB::table('staff_incomes')
                ->where('invoice_id', $invoice->id)
                ->where('commission_status', 'credit')
                ->sum('amount');
        }

        // paid_amount فقط برای سازگاری با رکوردهای قدیمی
        $legacyPaid = (float) ($invoice->paid_amount ?? 0);

        $paid  = $sumSalon + $sumStaff + $legacyPaid;
        $final = (float) $invoice->final_amount;

        $status = $paid <= 0 ? 'unpaid' : ($paid + 0.0001 < $final ? 'partial' : 'paid');
        DB::table('invoices')->where('id', $invoice->id)->update(['payment_status' => $status]);

        return $status;
    }


    // تاریخ جلالی/میلادی ورودی را به DateTime قابل درج تبدیل می‌کند
    private function parseMaybeJalali(?string $s)
    {
        if (!$s) return null;
        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return Carbon::parse($s);
            $gregorianDate = \App\Helpers\JalaliHelper::toGregorian($s);
            if ($gregorianDate) {
                return Carbon::parse($gregorianDate);
            }
            return null;
        } catch (\Exception $e) {
            return null; // در صورت خطا، null برگردان
        }
    }

    // app/Http/Controllers/Admin/InvoiceController.php
    public function setPaymentType(Request $r, Invoice $invoice)
    {
        $r->validate(['payment_type' => 'required|in:aggregate,split']);
        $invoice->payment_type = $r->payment_type;
        $invoice->save();
        return back();
    }

    public function paySalon(Request $r, Invoice $invoice)
    {
        $data = $r->validate([
            'account_id' => 'required|exists:salonbankaccounts,id',
            'method'     => 'required|in:card_to_card,cash,shaba,account_transfer,wallet,online,pos,cheque',
            'amount'     => 'required|numeric|min:1',
            'reference_number' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:1000',
            'paid_at' => 'nullable|date', // ورودی را می‌گیریم ولی نادیده می‌گیریم
        ]);

        DB::transaction(function () use ($invoice, $data) {
            $now   = now();
            $regAt = \Carbon\Carbon::parse($invoice->registration_date ?? $now);

            // جلوگیری از بیش‌پرداخت بر اساس salon_incomes
            $already = (float)($invoice->paid_amount ?? 0) + $this->sumInvoiceIncomes($invoice->id, null);
            $due     = max(0, (float)$invoice->final_amount - $already);
            $amount  = min((float)$data['amount'], $due);
            if ($amount <= 0) {
                throw new \Exception('فاکتور تسویه شده است.');
            }

            $status = 'posted';
            $incomeId = DB::table('salon_incomes')->insertGetId([
                'invoice_id'       => $invoice->id,
                'salon_account_id' => $data['account_id'],
                'payment_method'   => $data['method'],
                'amount'           => $amount,
                'reference_number' => $data['reference_number'] ?? null,
                'note'             => $data['note'] ?? null,
                'paid_at'          => $regAt,                         // 👈 تاریخ پرداخت = تاریخ فاکتور
                'created_by'       => Auth::guard('admin')->id(),
                'status'           => $status,
                'created_at'       => $regAt,                         // 👈
                'updated_at'       => $regAt,                         // 👈
            ]);

            // رفتار روش‌ها
            if ($data['method'] === 'wallet') {
                // کیف‌پول: فقط balance کم می‌شود
                $w   = DB::table('customerswallets')->where('customer_id', $invoice->customer_id)->lockForUpdate()->first();
                $bal = (float)($w->current_balance ?? 0);
                if ($amount > $bal) {
                    throw new \Exception('مبلغ بیشتر از موجودی کیف‌پول است.');
                }
                DB::table('customerswallets')->where('customer_id', $invoice->customer_id)
                    ->update(['current_balance' => $bal - $amount, 'last_updated' => $regAt]);  // 👈
            } elseif ($data['method'] === 'cheque') {
                // چک: همراه با لینک به ردیف درآمد
                $payload = [
                    'cheque_amount'      => $amount,
                    'cheque_status'      => 'pending',
                    'cheque_issuer_type' => 'customer',
                    'cheque_issuer_id'   => $invoice->customer_id,
                    'receiver'           => 'salon',
                    'receiver_type'      => 'salon',
                    'receiver_id'        => null,
                    'deposit_account_id' => $data['account_id'],
                    'transaction_id'     => null,
                    'status_changed_at'  => $regAt,                     // 👈
                    'created_at'         => $regAt,                     // 👈
                    'updated_at'         => $regAt,                     // 👈
                    'description'        => $data['note'] ?? null,
                ];
                if (Schema::hasColumn('received_checks', 'invoice_income_id')) {
                    $payload['invoice_income_id'] = $incomeId;
                }
                DB::table('received_checks')->insert($payload);
            }

            // فقط status فاکتور را از روی salon_incomes آپدیت کن
            $statusNow = $this->refreshInvoicePaymentStatus($invoice);
            $this->finalizeIfSettled($invoice);

            // اگر لازم است بدهی پرسنل را در حالت draft→paid بسازی:
            if ($invoice->invoice_status === 'draft' && $statusNow === 'paid') {
                foreach ($invoice->items as $it) {
                    if ($it->staff_commission_amount > 0 && !$it->staffIncome) {
                        StaffIncome::create([
                            'staff_id'          => $it->staff_id,
                            'invoice_id'        => $invoice->id,
                            'invoice_item_id'   => $it->id,
                            'amount'            => $it->staff_commission_amount,
                            'commission_status' => 'debt',
                        ]);
                    }
                }
                $invoice->invoice_status = 'final';
                $invoice->save();
            }
        });

        return back()->with('success', 'پرداخت سالن ثبت شد.');
    }

    public function payStaff(Request $r, Invoice $invoice, InvoiceItem $item)
    {
        abort_unless($invoice->payment_type === 'split', 403, 'فقط در حالت تفکیکی مجاز است.');
        $r->validate(['staffpaymentgateway_id' => 'required|exists:staff_payment_gateways,id']);

        $amount = (float) $item->staff_commission_amount;
        if ($amount <= 0) return back();

        DB::transaction(function () use ($invoice, $item, $r, $amount) {

            $regAt = \Carbon\Carbon::parse($invoice->registration_date ?? now()); // 👈

            $gw = DB::table('staff_payment_gateways')->where('id', $r->staffpaymentgateway_id)->first();
            $pm = 'cash';
            if ($gw) {
                if (!empty($gw->pos_terminal))      $pm = 'pos';
                elseif (!empty($gw->card_number))   $pm = 'card_to_card';
                elseif (!empty($gw->bank_account))  $pm = 'account_transfer';
            }

            // فقط در staff_incomes ذخیره کن
            $income = $item->staffIncome()->firstOrCreate(
                ['staff_id' => $item->staff_id, 'invoice_id' => $invoice->id, 'invoice_item_id' => $item->id],
                [
                    'amount'            => $amount,
                    'commission_status' => 'debt',
                    'payment_method'    => $pm,
                ]
            );

            $income->update([
                'amount'            => $amount,
                'commission_status' => 'credit',
                'payment_method'    => $pm,
                'updated_at'        => $regAt,   // 👈
            ]);
        });

        return back()->with('success', 'کمیسیون پرسنل پرداخت شد.');
    }


    public function index(Request $request)
    {
        $q = Invoice::query()
            ->with(['customer'])
            ->withCount('items')

            // جمع دریافتی‌های قطعی سالن
            ->withSum(['incomes as deposits_sum' => function ($q) {
                $q->where('status', 'posted');
            }], 'amount')

            // جمع پرداخت‌های پرسنل که به‌عنوان «اعتباری/واریزی» ثبت شده‌اند
            ->withSum(['staffIncomes as staff_paid_sum' => function ($q) {
                $q->where('commission_status', 'credit');
            }], 'amount')

            ->orderByDesc('id');


        if ($request->filled('from')) {
            $from = JalaliHelper::toGregorianDateTime($request->input('from') . ' 00:00');
            $q->where('created_at', '>=', $from);
        }
        if ($request->filled('to')) {
            $to = JalaliHelper::toGregorianDateTime($request->input('to') . ' 23:59:59');
            $q->where('created_at', '<=', $to);
        }

        if ($request->filled('payment_status')) $q->where('payment_status', $request->input('payment_status'));
        if ($request->filled('customer')) {
            $c = $request->input('customer');
            $q->whereIn('customer_id', function ($qq) use ($c) {
                $qq->from('customers')->select('id')
                    ->where('full_name', 'like', "%$c%")
                    ->orWhere('phone', 'like', "%$c%");
            });
        }

        $invoices = $q->paginate(20);
        return view('admin.invoices.index', compact('invoices'));
    }


    public function destroy(Request $request, Invoice $invoice)
    {
        if ($invoice->invoice_status !== 'draft') {
            $msg = 'فقط فاکتورهای پیش‌نویس قابل حذف هستند.';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('info', $msg);
        }

        // کم‌کردن یک‌بار مصرف کد تخفیف (مثل قبل)
        if ($invoice->discount_code_id) {
            DB::table('discount_codes')
                ->where('id', $invoice->discount_code_id)
                ->update([
                    'times_used' => DB::raw('CASE WHEN times_used > 0 THEN times_used - 1 ELSE 0 END')
                ]);
        }

        DB::transaction(function () use ($invoice) {

            // === 1) برگشت کیف‌پول مشتری براساس پرداخت‌های wallet ===
            $walletRefund = (float) DB::table('salon_incomes')
                ->where('invoice_id', $invoice->id)
                ->where('payment_method', 'wallet')
                ->where('status', 'posted')   // ✅ فقط پرداخت‌های ثبت‌شده
                ->sum('amount');

            if ($walletRefund > 0) {
                DB::table('customerswallets')
                    ->where('customer_id', $invoice->customer_id)
                    ->increment('current_balance', $walletRefund, ['last_updated' => now()]);
            }

            // برای حذف وابستگی چک‌ها
            $incomeIds = DB::table('salon_incomes')
                ->where('invoice_id', $invoice->id)
                ->pluck('id');

            // === 2) پاک‌سازی وابستگی‌ها ===

            // درآمد/بدهی‌های معرف
            DB::table('referrer_incomes')->where('invoice_id', $invoice->id)->delete();

            // درآمدهای پرسنل
            DB::table('staff_incomes')->where('invoice_id', $invoice->id)->delete();


            // چک‌های لینک‌شده
            if ($incomeIds->count() && Schema::hasColumn('received_checks', 'invoice_income_id')) {
                DB::table('received_checks')->whereIn('invoice_income_id', $incomeIds)->delete();
            }

            // درآمدهای سالن
            DB::table('salon_incomes')->where('invoice_id', $invoice->id)->delete();

            // آیتم‌ها
            DB::table('invoice_items')->where('invoice_id', $invoice->id)->delete();

            // خود فاکتور
            DB::table('invoices')->where('id', $invoice->id)->delete();
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'فاکتور حذف شد.']);
        }
        return redirect()->route('admin.invoices.index')->with('success', 'فاکتور حذف شد.');
    }



    public function create()
    {
        $customers  = DB::table('customers')->where('is_suspended', 0)->get();
        $services   = DB::table('service_types')->where('is_active', 1)->get();
        $staff      = DB::table('staff')->where('is_active', 1)->get();
        $categories = DB::table('service_categories')->get();
        $packages   = DB::table('package_categories')->where('is_active', 1)->get();
        $wallet_balance = 0;

        $salonAccounts = DB::table('salonbankaccounts as a')
            ->where('a.is_active', 1)
            ->orderByDesc('a.id')
            ->get([
                'a.id',
                'a.title',
                'a.bank_name',
                'a.account_number',
                'a.card_number',
                'a.pos_terminal'
            ]);

        return view('admin.invoices.create', compact(
            'customers',
            'services',
            'staff',
            'categories',
            'packages',
            'wallet_balance',
            'salonAccounts'
        ));
    }

    public function show(Invoice $invoice)
    {
        //$invoice->load(['items', 'incomes', 'customer']);
        $invoice->load([
            'customer',
            'incomes',
            'items.staff.paymentGateways', // ← این همون خط جدیده
            'items.staffIncome',
            'items.serviceType',
        ]);


        $sumIncomes = $this->sumInvoiceIncomes($invoice->id, 'posted');
        $paid       = (float) ($invoice->paid_amount ?? 0) + $sumIncomes;
        $due        = max(0, (float)$invoice->final_amount - $paid);

        $pendingItems = $invoice->items()
            ->where('staff_commission_status', '!=', 'paid')
            ->count();

        $canFinalize = ($due <= 0) && (
            $invoice->payment_type === 'aggregate' ||
            ($invoice->payment_type === 'split' && $pendingItems === 0)
        );
        $sumDeposits = $sumIncomes;

        return view('admin.invoices.edit', compact('invoice', 'paid', 'due', 'sumDeposits', 'pendingItems', 'canFinalize'));
    }

    private function discountUsableNow($discount, Carbon $now): bool
    {
        if ($discount->valid_from && $now->lt(Carbon::parse($discount->valid_from))) return false;
        if ($discount->valid_until && $now->gt(Carbon::parse($discount->valid_until))) return false;

        if (!empty($discount->days_of_week)) {
            $allowed = array_filter(array_map('trim', explode(',', strtolower($discount->days_of_week))));
            $dayCode = strtolower(substr($now->format('D'), 0, 3));
            if (!in_array($dayCode, $allowed, true)) return false;
        }

        $ts = $discount->time_start ?? null;
        $te = $discount->time_end   ?? null;
        if (!empty($ts) || !empty($te)) {
            $current = $now->format('H:i:s');
            if ($ts && $te) {
                if ($te < $ts) {
                    if (!($current >= $ts || $current <= $te)) return false;
                } else {
                    if (!($current >= $ts && $current <= $te)) return false;
                }
            } elseif ($ts) {
                if (!($current >= $ts)) return false;
            } elseif ($te) {
                if (!($current <= $te)) return false;
            }
        }

        if (!is_null($discount->usage_limit) && (int)$discount->times_used >= (int)$discount->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * ایجاد فاکتور (ثبت موقت/پیش‌نویس)
     */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'customer_id'      => 'required|exists:customers,id',
            'items'            => 'required|string',
            'payment_type'     => 'required|in:aggregate,split',
            'registration_date' => 'nullable|string',
            'discount_code'    => 'nullable|string',
            'wallet_payment'   => 'nullable|numeric|min:0',
        ]);

        if ($v->fails()) {
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'errors' => $v->errors()], 422);
            }
            return redirect()->route('admin.invoices.create')->withErrors($v)->withInput();
        }

        $validated  = $v->validated();
        $rawItems   = json_decode($request->input('items'), true);
        if (!$rawItems || !is_array($rawItems) || count($rawItems) === 0) {
            return response()->json(['success' => false, 'message' => 'هیچ آیتمی انتخاب نشده است!'], 422);
        }

        // نرمال‌سازی آیتم‌ها + محاسبه کمیسیون پرسنل
        $normalizedItems = [];
        $total = 0;

        foreach ($rawItems as $row) {
            $type = $row['type'] ?? 'service';

            if ($type === 'service') {
                $serviceId = (int)$row['service_id'];
                $qty       = max(1, (int)$row['quantity']);
                $staffId   = isset($row['staff_id']) ? (int)$row['staff_id'] : null;

                $service = DB::table('service_types')->where('id', $serviceId)->first(['id', 'price', 'category_id']);
                if (!$service) return response()->json(['success' => false, 'message' => "خدمت {$serviceId} یافت نشد."], 422);
                if (!$staffId)  return response()->json(['success' => false, 'message' => 'پرسنل خدمت مشخص نشده است.'], 422);

                $canDo = DB::table('staff_service_skills')
                    ->where('staff_id', $staffId)->where('category_id', $service->category_id)->where('can_do', 1)->exists();
                if (!$canDo) return response()->json(['success' => false, 'message' => 'پرسنل برای دستهٔ خدمت مجاز نیست.'], 422);

                $price    = (float)$service->price;
                $rowTotal = $price * $qty;

                $commissionAmount = 0;
                $comm = DB::table('staff_skills_commission')
                    ->where('staff_id', $staffId)->where('category_id', $service->category_id)
                    ->first(['commission_type', 'commission_value']);
                if ($comm) {
                    $commissionAmount = $comm->commission_type === 'percent'
                        ? floor($rowTotal * ((float)$comm->commission_value) / 100)
                        : (float)$comm->commission_value * $qty;
                }

                $normalizedItems[] = [
                    'service_type_id'         => $serviceId,
                    'staff_id'                => $staffId,
                    'quantity'                => $qty,
                    'price'                   => $price,
                    'total'                   => $rowTotal,
                    'staff_commission_amount' => $commissionAmount,
                    'created_at'              => $row['date'] ?? now(),
                    'updated_at'              => now(),
                    'item_status'             => 'pending',
                ];
                $total += $rowTotal;
            } elseif ($type === 'package') {
                $packageId = (int)($row['package_id'] ?? 0);
                if ($packageId <= 0) return response()->json(['success' => false, 'message' => 'شناسه پکیج نامعتبر است.'], 422);

                $pkgServices = DB::table('package_services')
                    ->where('package_category_id', $packageId)
                    ->join('service_types', 'service_types.id', '=', 'package_services.service_type_id')
                    ->select('service_types.id as service_id', 'service_types.price as price', 'service_types.category_id as category_id', 'package_services.staff_id as default_staff_id')
                    ->get();
                if ($pkgServices->isEmpty()) return response()->json(['success' => false, 'message' => "برای پکیج {$packageId} خدمتی تعریف نشده است."], 422);

                $selectedMap = [];
                if (!empty($row['services']) && is_array($row['services'])) {
                    foreach ($row['services'] as $sel) {
                        if (!empty($sel['service_id']) && !empty($sel['staff_id'])) {
                            $selectedMap[(int)$sel['service_id']] = (int)$sel['staff_id'];
                        }
                    }
                }

                foreach ($pkgServices as $srv) {
                    $serviceId = (int)$srv->service_id;
                    $price     = (float)$srv->price;
                    $qty       = 1;

                    $staffId = $selectedMap[$serviceId] ?? ($srv->default_staff_id ? (int)$srv->default_staff_id : null);
                    if (!$staffId) return response()->json(['success' => false, 'message' => 'برای یک خدمت پکیج، پرسنل مشخص نشده است.'], 422);

                    $canDo = DB::table('staff_service_skills')
                        ->where('staff_id', $staffId)->where('category_id', $srv->category_id)->where('can_do', 1)->exists();
                    if (!$canDo) return response()->json(['success' => false, 'message' => 'پرسنل انتخابی برای یکی از خدمات پکیج مجاز نیست.'], 422);

                    $rowTotal = $price * $qty;

                    $commissionAmount = 0;
                    $comm = DB::table('staff_skills_commission')
                        ->where('staff_id', $staffId)->where('category_id', $srv->category_id)
                        ->first(['commission_type', 'commission_value']);
                    if ($comm) {
                        $commissionAmount = $comm->commission_type === 'percent'
                            ? floor($rowTotal * ((float)$comm->commission_value) / 100)
                            : (float)$comm->commission_value * $qty;
                    }

                    $normalizedItems[] = [
                        'service_type_id'           => $serviceId,
                        'staff_id'                  => $staffId,
                        'quantity'                  => $qty,
                        'price'                     => $price,
                        'total'                     => $rowTotal,
                        'staff_commission_amount'   => $commissionAmount,
                        'created_at'                => now(),
                        'updated_at'                => now(),
                        'item_status'               => 'pending',
                        'from_package_category_id'  => $packageId,
                    ];
                    $total += $rowTotal;
                }
            }
        }

        $customerId = (int)$validated['customer_id'];

        // پیش‌محاسبه تاریخ میلادی فاکتور
        $gregorianDT = null;
        if ($request->filled('registration_date')) {
            $gregorianDT = JalaliHelper::toGregorianDateTime($request->input('registration_date'));
        }

        // تراکنش ساخت فاکتور
        $invoiceId = DB::transaction(function () use ($request, $validated, $normalizedItems, $total, $customerId, $gregorianDT) {
            $now = now();

            // کیف پول
            $wallet = DB::table('customerswallets')->where('customer_id', $customerId)->lockForUpdate()->first();
            $walletBalance = $wallet ? (float)$wallet->current_balance : 0;
            $walletPayment = (float)$request->input('wallet_payment', 0);

            // تخفیف
            $discountId = null;
            $discount_amount = 0;
            $final_amount = $total;
            if ($request->filled('discount_code')) {
                $discount = DiscountCode::where('code', $request->input('discount_code'))
                    ->where('is_active', 1)->lockForUpdate()->first();
                if ($discount && $this->discountUsableNow($discount, $now)) {
                    if (is_null($discount->usage_limit) || $discount->times_used < $discount->usage_limit) {
                        $discount_amount = $discount->discount_type === 'percent'
                            ? floor($total * $discount->value / 100)
                            : min($total, $discount->value);
                        $final_amount = $total - $discount_amount;
                        $discountId = $discount->id;
                    }
                }
            }

            if ($walletPayment > $walletBalance) throw new \Exception('مبلغ کیف پول بیشتر از موجودی است!');
            if ($walletPayment > $final_amount) $walletPayment = $final_amount;
            $remainingPayment = $final_amount - $walletPayment;

            // تاریخ مرجع برای همه ثبت‌های پرداخت = تاریخ فاکتور
            $regAt = \Carbon\Carbon::parse($gregorianDT ?? $now);  // 👈

            if ($walletPayment > 0) {
                DB::table('customerswallets')->where('customer_id', $customerId)
                    ->update([
                        'current_balance' => $walletBalance - $walletPayment,
                        'last_updated'    => $regAt,                 // 👈
                    ]);
            }

            $invoiceId = DB::table('invoices')->insertGetId([
                'customer_id'       => $validated['customer_id'],
                'payment_type'      => $validated['payment_type'],
                'registration_date' => $gregorianDT,         // 👈 همان تاریخ مرجع
                'total_amount'      => $total,
                'discount_code_id'  => $discountId,
                'discount_amount'   => $discount_amount,
                'final_amount'      => $final_amount,
                'paid_amount'       => 0,
                'payment_status'    => ($remainingPayment <= 0 ? 'paid' : ($walletPayment > 0 ? 'partial' : 'unpaid')),
                'invoice_status'    => 'draft',
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            // ثبت کیف‌پول به عنوان درآمد فاکتور (با تاریخ فاکتور)
            if ($walletPayment > 0) {
                DB::table('salon_incomes')->insert([
                    'invoice_id'       => $invoiceId,
                    'salon_account_id' => null,
                    'payment_method'   => 'wallet',
                    'amount'           => $walletPayment,
                    'reference_number' => null,
                    'note'             => 'پرداخت از کیف پول هنگام ساخت فاکتور',
                    'paid_at'          => $regAt,              // 👈
                    'created_by'       => Auth::guard('admin')->id(),
                    'status'           => 'posted',
                    'created_at'       => $regAt,              // 👈
                    'updated_at'       => $regAt,              // 👈
                ]);
            }

            if ($discountId) {
                DB::table('discount_codes')->where('id', $discountId)->increment('times_used');
            }

            foreach ($normalizedItems as $ni) {
                DB::table('invoice_items')->insert(array_merge($ni, ['invoice_id' => $invoiceId]));
            }

            return $invoiceId;
        });

        // افیلیت
        app(ReferrerCommissionService::class)->ensureDebtRowsByInvoiceId($invoiceId);

        $justStatus = DB::table('invoices')->where('id', $invoiceId)->value('payment_status');
        if ($justStatus === 'paid') {
            app(ReferrerCommissionService::class)->payoutIfInvoicePaidById($invoiceId);
        }

        if ($justStatus === 'paid') {
            $payType = DB::table('invoices')->where('id', $invoiceId)->value('payment_type');
            if ($payType === 'aggregate') {
                app(\App\Services\StaffCommissionService::class)->ensureDebtRowsByInvoiceId($invoiceId);
            }
        }

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            $inv = DB::table('invoices')->where('id', $invoiceId)->first([
                'id',
                'payment_status',
                'final_amount',
                'paid_amount'
            ]);

            $sumIncomes = $this->sumInvoiceIncomes($invoiceId, 'posted');

            return response()->json([
                'success'        => true,
                'invoice_id'     => $invoiceId,
                'id'             => $invoiceId,
                'payment_status' => $inv->payment_status ?? 'unpaid',
                'final_amount'   => (float) $inv->final_amount,
                'paid_amount'    => (float) $inv->paid_amount,
                'sum_incomes'    => (float) $sumIncomes,
                'deposits'       => [],
                'message'        => 'فاکتور (پیش‌نویس) ذخیره شد.',
            ]);
        }

        return redirect()->route('admin.invoices.show', $invoiceId)
            ->with('success', 'فاکتور به‌صورت پیش‌نویس ذخیره شد. حالا می‌توانید پرداخت‌ها را ثبت و نهایی کنید.');
    }


    public function addDeposit(Request $request, Invoice $invoice)
    {
        // --- تبدیل تاریخ‌های جلالی مثل قبل ---
        $p = $request->all();
        if (!empty($p['paid_at'])) {
            $p['paid_at'] = JalaliHelper::toGregorianDateTime($p['paid_at']);
        }
        if (!empty($p['cheque']['issue_date'])) {
            $p['cheque']['issue_date'] = JalaliHelper::toGregorianDateTime($p['cheque']['issue_date']);
        }
        if (!empty($p['cheque']['due_date'])) {
            $p['cheque']['due_date'] = JalaliHelper::toGregorianDateTime($p['cheque']['due_date']);
        }
        $request->replace($p);

        $rules = [
            'amount'           => 'required|numeric|min:1000',
            'method'           => 'required|in:online,pos,cheque,cash,card_to_card,account_transfer,shaba,wallet',
            'reference_number' => 'nullable|string|max:100',
            'note'             => 'nullable|string|max:1000',
            'paid_at'          => 'nullable|date',
            'salon_account_id' => 'nullable|integer|exists:salonbankaccounts,id',

            // فیلدهای چک
            'cheque.serial'        => 'nullable|string|max:255',
            'cheque.bank_name'     => 'nullable|string|max:255',
            'cheque.account'       => 'nullable|string|max:255',
            'cheque.amount'        => 'nullable|numeric|min:1000',
            'cheque.issue_date'    => 'nullable|date',
            'cheque.due_date'      => 'nullable|date|required_if:method,cheque',
            'cheque.issuer'        => 'nullable|string|max:255',
            'cheque.receiver_note' => 'nullable|string|max:1000',
        ];

        if ($invoice->payment_type === 'split') {
            $rules['item_ids']   = 'required|array|min:1';
            $rules['item_ids.*'] = 'integer';
        }

        $data = $request->validate($rules);
        $now  = now();

        $result = DB::transaction(function () use ($data, $invoice, $now) {

            $regAt = \Carbon\Carbon::parse($invoice->registration_date ?? $now); // 👈 پایهٔ همه زمان‌ها

            if ($invoice->payment_type === 'split') {
                $items = DB::table('invoice_items')
                    ->where('invoice_id', $invoice->id)
                    ->whereIn('id', $data['item_ids'])
                    ->where('staff_commission_status', 'pending')
                    ->get();

                if ($items->isEmpty()) {
                    return ['error' => ['item_ids' => 'آیتم معتبری برای تسویه پیدا نشد یا قبلاً پرداخت شده‌اند.']];
                }
                $itemsTotal = (float) $items->sum('total');
                if ((float)$data['amount'] < $itemsTotal) {
                    return ['error' => ['amount' => 'مبلغ پرداخت کمتر از مجموع آیتم‌های انتخاب‌شده است.']];
                }

                foreach ($items as $it) {
                    $commissionAmount = (float)($it->staff_commission_amount ?? 0);
                    $exists = DB::table('staff_incomes')->where('invoice_item_id', $it->id)->exists();

                    if (!$exists && $commissionAmount > 0) {
                        DB::table('staff_incomes')->insert([
                            'staff_id'          => $it->staff_id,
                            'invoice_id'        => $invoice->id,
                            'invoice_item_id'   => $it->id,
                            'amount'            => $commissionAmount,
                            'commission_status' => 'credit',
                            'payment_method'    => $data['method'] ?? null,
                            'created_at'        => $regAt,               // 👈
                            'updated_at'        => $regAt,               // 👈
                        ]);
                    }

                    DB::table('invoice_items')->where('id', $it->id)->update([
                        'staff_commission_status'         => 'paid',
                        'staff_commission_payment_method' => $data['method'],
                        'staff_commission_paid_at'        => $regAt,   // 👈
                        'updated_at'                      => $now,
                    ]);
                }
            }

            // 2) جلوگیری از بیش‌پرداخت
            $sumIncomesBefore = $this->sumInvoiceIncomes($invoice->id, null);
            $already          = (float) ($invoice->paid_amount ?? 0) + $sumIncomesBefore;
            $due              = max(0, (float)$invoice->final_amount - $already);
            $amount           = min((float)$data['amount'], $due);
            if ($amount <= 0) return ['error' => ['amount' => 'مبلغ پرداخت نامعتبر یا فاکتور تسویه است.']];

            // 3) درج در salon_incomes
            $method   = $data['method'];
            $status   = ($method === 'cheque') ? 'pending' : 'posted';
            $incomeId = DB::table('salon_incomes')->insertGetId([
                'invoice_id'       => $invoice->id,
                'salon_account_id' => $data['salon_account_id'] ?? null,
                'payment_method'   => $method,
                'amount'           => $amount,
                'reference_number' => $data['reference_number'] ?? null,
                'note'             => $data['note'] ?? null,
                'paid_at'          => $regAt,                    // 👈
                'created_by'       => Auth::guard('admin')->id(),
                'status'           => $status,
                'created_at'       => $regAt,                    // 👈
                'updated_at'       => $regAt,                    // 👈
            ]);

            // 4) رفتار روش‌ها
            if ($method === 'wallet') {
                $w = DB::table('customerswallets')->where('customer_id', $invoice->customer_id)->lockForUpdate()->first();
                $bal = (float)($w->current_balance ?? 0);
                if ($amount > $bal) return ['error' => ['amount' => 'مبلغ بیشتر از موجودی کیف‌پول است.']];
                DB::table('customerswallets')->where('customer_id', $invoice->customer_id)
                    ->update(['current_balance' => $bal - $amount, 'last_updated' => $regAt]);   // 👈
            } elseif ($method === 'cheque') {
                $ch = $data['cheque'] ?? [];
                $payload = [
                    'cheque_serial'         => $ch['serial'] ?? null,
                    'cheque_account_number' => $ch['account'] ?? null,
                    'cheque_bank_name'      => $ch['bank_name'] ?? null,
                    'cheque_amount'         => $amount,
                    'cheque_issue_date'     => $ch['issue_date'] ?? null,
                    'cheque_due_date'       => $ch['due_date'] ?? null,
                    'cheque_status'         => 'pending',
                    'cheque_issuer'         => $ch['issuer'] ?? null,
                    'cheque_issuer_type'    => 'customer',
                    'cheque_issuer_id'      => $invoice->customer_id,
                    'receiver'              => 'salon',
                    'receiver_type'         => 'salon',
                    'receiver_id'           => null,
                    'deposit_account_id'    => $data['salon_account_id'] ?? null,
                    'transaction_id'        => null,
                    'status_changed_at'     => $regAt,            // 👈
                    'description'           => $ch['receiver_note'] ?? null,
                    'created_at'            => $regAt,            // 👈
                    'updated_at'            => $regAt,            // 👈
                ];
                if (Schema::hasColumn('received_checks', 'invoice_income_id')) {
                    $payload['invoice_income_id'] = $incomeId;
                }
                DB::table('received_checks')->insert($payload);
            }

            // 5) status فاکتور
            $statusNow = $this->refreshInvoicePaymentStatus($invoice);

            // سازگاری با فرانت
            $inc = DB::table('salon_incomes')->where('id', $incomeId)
                ->first(['id', 'amount', 'payment_method as method', 'reference_number', 'paid_at', 'salon_account_id', 'status']);

            return ['ok' => true, 'status' => $statusNow, 'deposit' => $inc];
        });
        $this->finalizeIfSettled($invoice->fresh());

        if (!isset($result['error'])) {
            app(\App\Services\ReferrerCommissionService::class)->ensureDebtRowsByInvoiceId($invoice->id);
            if (($result['status'] ?? null) === 'paid') {
                app(\App\Services\ReferrerCommissionService::class)->payoutIfInvoicePaid($invoice->fresh());
                if ($invoice->payment_type === 'aggregate') {
                    app(\App\Services\StaffCommissionService::class)->ensureDebtRowsByInvoiceId($invoice->id);
                }
            }
        }

        if (isset($result['error'])) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'errors' => $result['error']], 422)
                : back()->withErrors($result['error']);
        }

        return $request->expectsJson()
            ? response()->json([
                'success' => true,
                'payment_status' => $result['status'] ?? 'partial',
                'deposit' => $result['deposit'] ?? null,
                'message' => 'پرداخت ذخیره شد.'
            ])
            : back()->with('success', 'پرداخت ذخیره شد.');
    }


    public function removeIncome(Invoice $invoice, int $incomeId, Request $request)
    {
        $income = DB::table('salon_incomes')->where('id', $incomeId)->first();
        if (!$income || (int)$income->invoice_id !== (int)$invoice->id) abort(404);

        DB::transaction(function () use ($income, $invoice) {

            if (($income->payment_method ?? null) === 'wallet') {
                $w = DB::table('customerswallets')->where('customer_id', $invoice->customer_id)->lockForUpdate()->first();
                $bal = (float)($w->current_balance ?? 0);
                DB::table('customerswallets')->where('customer_id', $invoice->customer_id)
                    ->update(['current_balance' => $bal + (float)$income->amount, 'last_updated' => now()]);
            }

            if (Schema::hasColumn('received_checks', 'invoice_income_id')) {
                DB::table('received_checks')->where('invoice_income_id', $income->id)->delete();
            }

            DB::table('salon_incomes')->where('id', $income->id)->delete();

            $this->refreshInvoicePaymentStatus($invoice);
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'پرداخت حذف شد.']);
        }
        return back()->with('success', 'پرداخت حذف شد.');
    }

    public function finalize(Invoice $invoice, Request $request)
    {
        $sumIncomes   = (float) $this->sumInvoiceIncomes($invoice->id, 'posted');
        $paid         = (float) ($invoice->paid_amount ?? 0) + $sumIncomes;
        $due          = max(0, (float)$invoice->final_amount - $paid);
        $pendingItems = DB::table('invoice_items')
            ->where('invoice_id', $invoice->id)
            ->where('staff_commission_status', 'pending')
            ->count();

        if ($due > 0) {
            $msg = 'تا زمانی که مانده فاکتور صفر نشود، امکان ثبت نهایی نیست.';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('info', $msg);
        }
        if ($invoice->payment_type === 'split' && $pendingItems > 0) {
            $msg = 'در حالت تفکیکی باید کمیسیون تمام آیتم‌ها تسویه شود، سپس ثبت نهایی کنید.';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('info', $msg);
        }
        if ($invoice->invoice_status === 'final') {
            $msg = 'این فاکتور قبلاً نهایی شده است.';
            return $request->expectsJson()
                ? response()->json(['success' => true, 'message' => $msg])
                : back()->with('info', $msg);
        }

        DB::table('invoices')->where('id', $invoice->id)->update(['invoice_status' => 'final']);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'فاکتور با موفقیت نهایی شد.']);
        }

        if ($invoice->payment_type === 'split') {
            $pending = DB::table('invoice_items')
                ->where('invoice_id', $invoice->id)
                ->where('staff_commission_status', '!=', 'paid')
                ->count();
            if ($pending > 0) {
                return back()->withErrors('تا زمانی که کمیسیون همه پرسنل تسویه نشود، فاکتور قابل بستن نیست.')->withInput();
            }
        }
        $inv = $invoice->fresh();
        if ($inv->payment_status === 'paid') {
            app(\App\Services\ReferrerCommissionService::class)->ensureDebtRowsByInvoiceId($inv->id);
            app(\App\Services\ReferrerCommissionService::class)->payoutIfInvoicePaid($inv);
        }

        return back()->with('success', 'فاکتور با موفقیت نهایی شد.');
    }


    public function removeDeposit(Invoice $invoice, \App\Models\SalonIncome $income, Request $request)
    {
        if ($income->invoice_id !== $invoice->id) abort(404);

        DB::transaction(function () use ($income, $invoice) {
            $income->delete();

            $sumPostedIncomes = (float) DB::table('salon_incomes')
                ->where('invoice_id', $invoice->id)
                ->where('status', 'posted')
                ->sum('amount');

            $paid  = (float) ($invoice->paid_amount ?? 0) + $sumPostedIncomes;

            $status = $paid <= 0
                ? 'unpaid'
                : ($paid < (float) $invoice->final_amount ? 'partial' : 'paid');

            DB::table('invoices')->where('id', $invoice->id)->update(['payment_status' => $status]);
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'پرداخت حذف شد.']);
        }
        return back()->with('success', 'پرداخت حذف شد.');
    }

    public function checkDiscountCode(Request $request)
    {
        $code = $request->query('code');
        $now  = now();

        $discount = DiscountCode::where('code', $code)->where('is_active', 1)->first();

        if (!$discount || !$this->discountUsableNow($discount, $now)) {
            return response()->json(['success' => false, 'message' => 'کد تخفیف در این بازه زمانی/روز معتبر نیست!']);
        }

        return response()->json([
            'success'       => true,
            'discount_type' => $discount->discount_type,
            'value'         => $discount->value,
            'message'       => 'کد تخفیف معتبر است.',
        ]);
    }

    // app/Http/Controllers/Admin/InvoiceController.php

    public function previewReferrerCommission(Request $request)
    {
        // 1) سازگاری با فرانت قدیمی و جدید
        $request->validate([
            'items'        => 'required|string',
            'user_id'      => 'nullable|exists:customers,id',
            'customer_id'  => 'nullable|exists:customers,id',
        ]);

        $customerId = (int) ($request->input('customer_id') ?? $request->input('user_id'));
        if ($customerId <= 0) {
            return response()->json(['success' => false, 'message' => 'شناسه مشتری نامعتبر است.'], 422);
        }

        $items = json_decode($request->input('items'), true);
        if (!$items || !is_array($items)) {
            return response()->json(['success' => false, 'message' => 'ساختار آیتم‌ها نامعتبر است.'], 422);
        }

        // کد و نام معرف
        $refCode = app(\App\Services\ReferrerCommissionService::class)->getReferrerCodeForCustomer($customerId);
        if (!$refCode) {
            return response()->json([
                'success'       => true,
                'has_referrer'  => false,
                'referrer_code' => null,
                'referrer_name' => null,
                'total'         => 0,
                'details'       => [],
                'message'       => 'این مشتری معرف ندارد.'
            ]);
        }

        $referrerName =
            DB::table('customers')->where('referral_code', $refCode)->value('full_name')
            ?? DB::table('staff')->where('referral_code', $refCode)->value('full_name')
            ?? (function () use ($refCode) {
                $adm = DB::table('admin_users')->where('referral_code', $refCode)->first(['fullname', 'adminusername']);
                return $adm->fullname ?? $adm->adminusername ?? null;
            })();

        $sum = 0.0;
        $details = [];

        // کمکی: محاسبهٔ کمیسیون پرسنل از روی داده‌های ردیف
        $calcStaffCommission = function (float $price, int $qty, ?string $t, $v): float {
            $total = $price * max(1, $qty);
            if ($t === 'percent') return floor($total * ((float)$v) / 100);
            if ($t === 'amount')  return (float)$v * max(1, $qty);
            return 0.0;
        };

        foreach ($items as $row) {
            $type = $row['type'] ?? 'service';

            if ($type === 'package') {
                $pkgId = (int)($row['package_id'] ?? 0);
                if ($pkgId <= 0) continue;

                $pkg = DB::table('package_categories')
                    ->where('id', $pkgId)
                    ->first([
                        'id',
                        'price',
                        'referrer_enabled',
                        'referrer_commission_type',
                        'referrer_commission_value'
                    ]);

                // ⚠️ از فرانت آرایه می‌آید؛ فقط ایندکس آرایه را استفاده کن
                $pkgServices = is_array($row['services'] ?? null) ? $row['services'] : [];
                $pkgNet = 0.0;

                $packageRuleUsed = ($pkg && (int)$pkg->referrer_enabled === 1);

                foreach ($pkgServices as $srv) {
                    $qty   = max(1, (int)($srv['quantity'] ?? 1));
                    $price = (float)($srv['price'] ?? 0);
                    $srvTotal = $price * $qty;

                    $staffComm = $calcStaffCommission(
                        $price,
                        $qty,
                        $srv['commission_type'] ?? null,
                        $srv['commission_value'] ?? null
                    );
                    $net = max(0, $srvTotal - $staffComm);
                    $pkgNet += $net;

                    if (!$packageRuleUsed) {
                        // قانون در سطح خودِ خدمت
                        $service = DB::table('service_types')->where('id', (int)($srv['service_id'] ?? 0))
                            ->first(['id', 'title', 'referrer_enabled', 'referrer_commission_type', 'referrer_commission_value']);

                        if ($service && (int)$service->referrer_enabled === 1) {
                            $amount = $service->referrer_commission_type === 'percent'
                                ? floor($net * ((float)$service->referrer_commission_value) / 100)
                                : (float)$service->referrer_commission_value * $qty;

                            $sum += $amount;
                            $details[] = [
                                'label'  => $srv['service_title'] ?? ("خدمت #" . ((int)($srv['service_id'] ?? 0))),
                                'base'   => $net, // پایه خالص
                                'source' => 'service',
                                'rule'   => [
                                    'type'  => $service->referrer_commission_type,
                                    'value' => (float)$service->referrer_commission_value,
                                    'note'  => 'پس از کسر کمیسیون پرسنل',
                                ],
                                'amount' => $amount,
                            ];
                        }
                    }
                }

                // قانون سطح پکیج (اگر فعال)
                if ($packageRuleUsed) {
                    $amount = ($pkg->referrer_commission_type === 'percent')
                        ? floor($pkgNet * ((float)$pkg->referrer_commission_value) / 100)
                        : (float)$pkg->referrer_commission_value; // به ازای هر پکیج
                    $sum += $amount;
                    $details[] = [
                        'label'  => ($row['package_title'] ?? "پکیج #{$pkgId}"),
                        'base'   => $pkgNet, // پایه خالص پکیج
                        'source' => 'package',
                        'rule'   => [
                            'type'  => $pkg->referrer_commission_type,
                            'value' => (float)$pkg->referrer_commission_value,
                            'note'  => 'پایه = جمع خالص خدمات پکیج',
                        ],
                        'amount' => $amount,
                    ];
                }
            } else {
                // خدمت تکی
                $serviceId = (int)($row['service_id'] ?? 0);
                if ($serviceId <= 0) continue;

                $qty   = max(1, (int)($row['quantity'] ?? 1));
                $price = (float)($row['price'] ?? 0);
                $total = $price * $qty;

                $staffComm = $calcStaffCommission(
                    $price,
                    $qty,
                    $row['commission_type'] ?? null,
                    $row['commission_value'] ?? null
                );
                $net = max(0, $total - $staffComm);

                $service = DB::table('service_types')->where('id', $serviceId)
                    ->first(['id', 'title', 'referrer_enabled', 'referrer_commission_type', 'referrer_commission_value']);

                if ($service && (int)$service->referrer_enabled === 1) {
                    $amount = $service->referrer_commission_type === 'percent'
                        ? floor($net * ((float)$service->referrer_commission_value) / 100)
                        : (float)$service->referrer_commission_value * $qty;

                    $sum += $amount;
                    $details[] = [
                        'label'  => $row['service_title'] ?? "خدمت #{$serviceId}",
                        'base'   => $net, // پایه خالص
                        'source' => 'service',
                        'rule'   => [
                            'type'  => $service->referrer_commission_type,
                            'value' => (float)$service->referrer_commission_value,
                            'note'  => 'پس از کسر کمیسیون پرسنل',
                        ],
                        'amount' => $amount,
                    ];
                }
            }
        }

        return response()->json([
            'success'       => true,
            'has_referrer'  => true,
            'referrer_code' => $refCode,
            'referrer_name' => $referrerName,
            'total'         => (float)$sum,
            'details'       => $details,
            'message'       => 'محاسبه شد.'
        ]);
    }


    // -------------------------
    // ذخیرهٔ پیش‌نویس مختصر (API کمکی)
    // -------------------------
    public function storeDraft(Request $request)
    {
        $data = $request->validate([
            'customer_id'       => ['required', 'integer', 'exists:customers,id'],
            'registration_date' => ['nullable', 'date'],   // ✅
            'payment_type'      => ['nullable', 'in:aggregate,split'],
            'items'             => ['required', 'array', 'min:1'],
            'items.*.service_type_id' => ['required', 'integer', 'exists:service_types,id'],
            'items.*.staff_id'        => ['required', 'integer', 'exists:staff,id'],
            'items.*.quantity'        => ['nullable', 'integer', 'min:1'],
            'items.*.price'           => ['required', 'numeric', 'min:0'],
            'discount_amount'         => ['nullable', 'numeric', 'min:0'],
        ]);


        $total = 0;
        foreach ($data['items'] as $it) {
            $qty = $it['quantity'] ?? 1;
            $total += ((float)$it['price']) * $qty;
        }
        $discount = (float)($data['discount_amount'] ?? 0);
        $final = max(0, $total - $discount);

        $invoice = Invoice::create([
            'customer_id'       => $data['customer_id'],
            'invoice_status'    => 'draft',
            'registration_date' => $data['registration_date'] ?? now(),  // ✅
            'payment_type'      => $data['payment_type'] ?? 'aggregate',
            'payment_status'    => 'unpaid',
            'discount_amount'   => $discount,
            'total_amount'      => $total,
            'paid_amount'       => 0,
            'final_amount'      => $final,
        ]);

        foreach ($data['items'] as $it) {
            $qty = $it['quantity'] ?? 1;
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'service_type_id' => $it['service_type_id'],
                'staff_id' => $it['staff_id'],
                'quantity' => $qty,
                'price' => $it['price'],
                'total' => ((float)$it['price']) * $qty,
                'item_status' => 'pending',
                'staff_commission_status' => 'pending',
            ]);
        }

        return response()->json([
            'ok' => true,
            'invoice_id' => $invoice->id,
            'message' => 'Invoice draft created.',
        ]);
    }

    public function payAggregate(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'account_id' => ['required_unless:method,wallet', 'integer', 'exists:salonbankaccounts,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'method' => ['required', 'in:card_to_card,cash,shaba,account_transfer,wallet,online,pos,cheque'],
            'description' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date'], // ورودی را می‌گیریم ولی نادیده می‌گیریم
        ]);

        $due = max(0, (float)$invoice->final_amount - ((float)$invoice->paid_amount + $this->sumInvoiceIncomes($invoice->id, null)));
        $payAmount = isset($data['amount']) ? (float)$data['amount'] : $due;
        $payAmount = min($payAmount, $due);
        if ($payAmount <= 0) {
            return response()->json(['ok' => false, 'message' => 'Nothing to pay.'], 422);
        }

        $paidAt = \Carbon\Carbon::parse($invoice->registration_date ?? now()); // 👈

        $status = ($data['method'] === 'cheque') ? 'pending' : 'posted';
        $incomeId = DB::table('salon_incomes')->insertGetId([
            'invoice_id'       => $invoice->id,
            'salon_account_id' => $data['account_id'] ?? null,
            'payment_method'   => $data['method'],
            'amount'           => $payAmount,
            'reference_number' => null,
            'note'             => $data['description'] ?? null,
            'paid_at'          => $paidAt,       // 👈
            'created_by'       => Auth::guard('admin')->id(),
            'status'           => $status,
            'created_at'       => $paidAt,       // 👈
            'updated_at'       => $paidAt,       // 👈
        ]);

        // رفتار روش‌ها
        if ($data['method'] === 'wallet') {
            $w = DB::table('customerswallets')->where('customer_id', $invoice->customer_id)->lockForUpdate()->first();
            $bal = (float)($w->current_balance ?? 0);
            if ($payAmount > $bal) {
                return response()->json(['ok' => false, 'message' => 'مبلغ بیشتر از موجودی کیف‌پول است.'], 422);
            }
            DB::table('customerswallets')->where('customer_id', $invoice->customer_id)
                ->update(['current_balance' => $bal - $payAmount, 'last_updated' => $paidAt]); // 👈
        } elseif ($data['method'] === 'cheque') {
            $payload = [
                'cheque_amount'      => $payAmount,
                'cheque_status'      => 'pending',
                'cheque_issuer_type' => 'customer',
                'cheque_issuer_id'   => $invoice->customer_id,
                'receiver'           => 'salon',
                'receiver_type'      => 'salon',
                'receiver_id'        => null,
                'deposit_account_id' => $data['account_id'] ?? null,
                'transaction_id'     => null,
                'status_changed_at'  => $paidAt,          // 👈
                'created_at'         => $paidAt,          // 👈
                'updated_at'         => $paidAt,          // 👈
                'description'        => $data['description'] ?? null,
            ];
            if (Schema::hasColumn('received_checks', 'invoice_income_id')) {
                $payload['invoice_income_id'] = $incomeId;
            }
            DB::table('received_checks')->insert($payload);
        }

        // به‌روزرسانی وضعیت
        $invoice->refresh();
        $paymentStatus = $this->refreshInvoicePaymentStatus($invoice);
        $this->finalizeIfSettled($invoice);

        app(\App\Services\ReferrerCommissionService::class)->ensureDebtRowsByInvoiceId($invoice->id);

        if ($paymentStatus === 'paid') {
            app(\App\Services\ReferrerCommissionService::class)->ensureDebtRowsByInvoiceId($invoice->id);
            app(\App\Services\ReferrerCommissionService::class)->payoutIfInvoicePaid($invoice->fresh());
        }

        if ($invoice->invoice_status === 'draft' && $paymentStatus === 'paid') {
            $invoice->invoice_status = 'final';
            $invoice->save();
        }

        return response()->json([
            'ok' => true,
            'invoice_id' => $invoice->id,
            'paid_now' => $payAmount,
            'payment_status' => $paymentStatus,
        ]);
    }


    public function splitSummary(Invoice $invoice)
    {
        return response()->json($this->buildSplitSummary($invoice));
    }

    private function buildSplitSummary(Invoice $invoice): array
    {
        $rows = DB::table('invoice_items as ii')
            ->leftJoin('staff', 'staff.id', '=', 'ii.staff_id')
            ->leftJoin('service_types as st', 'st.id', '=', 'ii.service_type_id')
            ->where('ii.invoice_id', $invoice->id)
            ->get([
                'ii.id as invoice_item_id',
                'ii.staff_id',
                'staff.full_name as staff_name',
                'st.title as item_title',
                'ii.staff_commission_amount',
            ]);

        $staff = [];
        foreach ($rows as $r) {
            if (!$r->staff_id) continue;
            if (!isset($staff[$r->staff_id])) {
                $staff[$r->staff_id] = [
                    'staff_id'          => (int) $r->staff_id,
                    'name'              => $r->staff_name ?: '-',
                    'commission_total'  => 0.0,
                    'paid'              => 0.0,
                    'due'               => 0.0,
                    'items'             => [],
                    'gateways'          => [],
                ];
            }
            $amt = (float) ($r->staff_commission_amount ?? 0);
            $staff[$r->staff_id]['commission_total'] += $amt;
            $staff[$r->staff_id]['items'][] = [
                'invoice_item_id' => (int) $r->invoice_item_id,
                'title'           => $r->item_title ?: ('آیتم #' . (int) $r->invoice_item_id),
                'commission'      => $amt,
            ];
        }

        $paidMap = DB::table('staff_incomes')
            ->where('invoice_id', $invoice->id)
            ->where('commission_status', 'credit')
            ->selectRaw('staff_id, COALESCE(SUM(amount),0) AS s')
            ->groupBy('staff_id')
            ->pluck('s', 'staff_id');

        foreach ($staff as $sid => &$s) {
            $s['paid'] = (float) ($paidMap[$sid] ?? 0);
            $s['due']  = max(0, (float) $s['commission_total'] - (float) $s['paid']);
        }
        unset($s);

        if (!empty($staff)) {
            $ids = array_map('intval', array_keys($staff));
            $gws = DB::table('staff_payment_gateways')
                ->whereIn('staff_id', $ids)
                ->get(['id', 'staff_id', 'pos_terminal', 'card_number', 'bank_account']);

            foreach ($gws as $g) {
                $parts = [];
                if (!empty($g->pos_terminal)) $parts[] = 'POS ' . $g->pos_terminal;
                if (!empty($g->card_number))  $parts[] = 'کارت ' . $g->card_number;
                if (!empty($g->bank_account)) $parts[] = 'حساب ' . $g->bank_account;
                $label = $parts ? implode(' / ', $parts) : 'درگاه';

                $sid = (int) $g->staff_id;
                if (isset($staff[$sid])) {
                    $staff[$sid]['gateways'][] = ['id' => (int) $g->id, 'label' => $label];
                }
            }
        }

        $staffList   = array_values($staff);
        $staffTotal  = array_reduce($staffList, fn($s, $x) => $s + (float) ($x['commission_total'] ?? 0), 0.0);

        $refAmount = (float) DB::table('referrer_incomes')->where('invoice_id', $invoice->id)->sum('amount');
        $refName = DB::table('referrer_incomes')->where('invoice_id', $invoice->id)->value('referrer_name') ?? '-';

        $sumPostedIncomes = (float) DB::table('salon_incomes')
            ->where('invoice_id', $invoice->id)
            ->where('status', 'posted')
            ->sum('amount');

        $final      = (float) $invoice->final_amount;
        $shareTotal = max(0, $final - $staffTotal - $refAmount);
        $salonPaid  = min($sumPostedIncomes, $shareTotal);
        $salonDue   = max(0, $shareTotal - $salonPaid);

        return [
            'success'      => true,
            'final_amount' => $final,
            'referrer'     => ['name' => $refName, 'amount' => $refAmount],
            'staff'        => $staffList,
            'salon'        => [
                'share_total' => $shareTotal,
                'paid'        => $salonPaid,
                'due'         => $salonDue,
            ],
        ];
    }

    /**
     * ثبت پرداخت‌های پرسنل (تفکیکی)
     */
    public function paySplitStaff(Request $r, Invoice $invoice)
    {
        abort_unless($invoice->payment_type === 'split', 403, 'فقط در حالت تفکیکی مجاز است.');

        $data = $r->validate([
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.staff_id'      => ['required', 'integer', 'exists:staff,id'],
            'items.*.method'        => ['required', 'in:cash,pos,card_to_card,account_transfer,shaba,online,cheque'],
            'items.*.amount'        => ['required', 'numeric', 'min:1'],
            'items.*.paid_at'       => ['nullable', 'string'],
            'items.*.ref'           => ['nullable', 'string', 'max:100'],
            'items.*.gateway_id'    => ['nullable', 'integer', 'exists:staff_payment_gateways,id'],
        ]);

        DB::transaction(function () use ($invoice, $data) {
            foreach ($data['items'] as $row) {
                $sid    = (int) $row['staff_id'];
                $method = $row['method'];
                $amtReq = (float) $row['amount'];
                $ref    = $row['ref'] ?? null;
                // $paidAt = $this->parseMaybeJalali($row['paid_at'] ?? null) ?? now();
                $paidAt = \Illuminate\Support\Carbon::parse($invoice->registration_date ?? now());

                $commissionTotal = (float) DB::table('invoice_items')
                    ->where('invoice_id', $invoice->id)
                    ->where('staff_id', $sid)
                    ->sum('staff_commission_amount');

                $alreadyPaid = (float) DB::table('staff_incomes')
                    ->where('invoice_id', $invoice->id)
                    ->where('staff_id', $sid)
                    ->where('commission_status', 'credit')
                    ->sum('amount');

                $due = max(0, $commissionTotal - $alreadyPaid);
                $pay = min($amtReq, $due);
                if ($pay <= 0) continue;

                DB::table('staff_incomes')->insert([
                    'staff_id'          => $sid,
                    'invoice_id'        => $invoice->id,
                    'invoice_item_id'   => null,
                    'amount'            => $pay,
                    'commission_status' => 'credit',
                    'payment_method'    => $method,   // 📌 NEW
                    'reference_number'  => $ref,      // 📌 NEW
                    'created_at'        => $paidAt,
                    'updated_at'        => $paidAt,
                ]);
            }
        });
        app(\App\Services\ReferrerCommissionService::class)->ensureDebtRowsByInvoiceId($invoice->id);
        $this->finalizeIfSettled($invoice);

        return response()->json($this->buildSplitSummary($invoice->fresh()));
    }

    /**
     * ثبت پرداخت‌های سهم سالن (تفکیکی)
     */
    public function paySplitSalon(Request $r, Invoice $invoice)
    {
        abort_unless($invoice->payment_type === 'split', 403, 'فقط در حالت تفکیکی مجاز است.');

        $data = $r->validate([
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.method'              => ['required', 'in:card_to_card,cash,shaba,account_transfer,wallet,online,pos,cheque'],
            'items.*.amount'              => ['required', 'numeric', 'min:1'],
            'items.*.paid_at'             => ['nullable', 'string'],
            'items.*.ref'                 => ['nullable', 'string', 'max:100'],
            'items.*.note'                => ['nullable', 'string', 'max:1000'],
            'items.*.salon_account_id'    => ['nullable', 'integer', 'exists:salonbankaccounts,id'],
        ]);

        DB::transaction(function () use ($invoice, $data) {
            // محاسبهٔ مانده فقط بر اساس مبلغ نهایی منهای سهم پرسنل و دریافتی‌های قبلی سالن
            $total    = (float) $invoice->final_amount;
            $salonGot = (float) DB::table('salon_incomes')
                ->where('invoice_id', $invoice->id)
                ->where('status', 'posted')
                ->sum('amount');
            $staffGot = (float) DB::table('staff_incomes')
                ->where('invoice_id', $invoice->id)
                ->where('commission_status', 'credit')
                ->sum('amount');

            $remaining = max(0, $total - $salonGot - $staffGot);

            foreach ($data['items'] as $row) {
                if ($remaining <= 0) break;

                $method   = $row['method'];
                // $paidAt   = $this->parseMaybeJalali($row['paid_at'] ?? null) ?? now();
                $paidAt   = \Illuminate\Support\Carbon::parse($invoice->registration_date ?? now());

                $account  = $row['salon_account_id'] ?? null;
                $ref      = $row['ref'] ?? null;
                $note     = $row['note'] ?? null;
                $reqAmt   = (float) $row['amount'];
                $amount   = min($reqAmt, $remaining);
                if ($amount <= 0) continue;

                $status = ($method === 'cheque') ? 'pending' : 'posted';
                $incomeId = DB::table('salon_incomes')->insertGetId([
                    'invoice_id'       => $invoice->id,
                    'salon_account_id' => $account,
                    'payment_method'   => $method,
                    'amount'           => $amount,
                    'reference_number' => $ref,
                    'note'             => $note,
                    'paid_at'          => $paidAt,
                    'created_by'       => Auth::guard('admin')->id(),
                    'status'           => $status,
                    'created_at'       => $paidAt,
                    'updated_at'       => $paidAt,
                ]);

                if ($method === 'wallet') {
                    $w   = DB::table('customerswallets')->where('customer_id', $invoice->customer_id)->lockForUpdate()->first();
                    $bal = (float)($w->current_balance ?? 0);
                    if ($amount > $bal) {
                        throw new \Exception('مبلغ بیشتر از موجودی کیف‌پول است.');
                    }
                    DB::table('customerswallets')->where('customer_id', $invoice->customer_id)
                        ->update(['current_balance' => $bal - $amount, 'last_updated' => $paidAt]);
                } elseif ($method === 'cheque') {
                    $ch = $row['cheque'] ?? [];
                    $payload = [
                        'cheque_serial'         => $ch['serial']   ?? null,
                        'cheque_account_number' => $ch['account']  ?? null,
                        'cheque_bank_name'      => $ch['bank']     ?? null,
                        'cheque_amount'         => $amount,
                        'cheque_issue_date'     => $paidAt,
                        'cheque_due_date'       => $ch['due']      ?? null,
                        'cheque_status'         => 'pending',
                        'cheque_issuer'         => $ch['issuer']   ?? null,
                        'cheque_issuer_type'    => 'customer',
                        'cheque_issuer_id'      => $invoice->customer_id,
                        'receiver'              => 'salon',
                        'receiver_type'         => 'salon',
                        'receiver_id'           => null,
                        'deposit_account_id'    => $account,
                        'transaction_id'        => null,
                        'status_changed_at'     => $paidAt,
                        'description'           => $ch['note']     ?? null,
                        'created_at'            => $paidAt,
                        'updated_at'            => $paidAt,
                    ];
                    if (Schema::hasColumn('received_checks', 'invoice_income_id')) {
                        $payload['invoice_income_id'] = $incomeId;
                    }
                    DB::table('received_checks')->insert($payload);
                }


                $remaining -= $amount;
            }

            $this->refreshInvoicePaymentStatus($invoice);
            app(\App\Services\ReferrerCommissionService::class)->ensureDebtRowsByInvoiceId($invoice->id);
            $this->finalizeIfSettled($invoice->fresh());
        });

        return response()->json($this->buildSplitSummary($invoice->fresh()));
    }

    /**
     * نهایی‌سازی پرداخت تفکیکی
     */
    public function finalizeSplit(Invoice $invoice, Request $request)
    {
        abort_unless($invoice->payment_type === 'split', 403, 'فقط در حالت تفکیکی مجاز است.');

        $sum = $this->buildSplitSummary($invoice);

        $staffDue = 0.0;
        foreach ($sum['staff'] as $st) {
            $staffDue += (float) ($st['due'] ?? 0);
        }
        $salonDue = (float) ($sum['salon']['due'] ?? 0);

        if ($staffDue > 0 || $salonDue > 0) {
            return response()->json([
                'success' => false,
                'message' => 'تا زمانی که ماندهٔ پرسنل و سالن هر دو صفر نشوند، نهایی‌سازی مجاز نیست.',
                'summary' => $sum,
            ], 422);
        }

        DB::transaction(function () use ($invoice) {
            DB::table('invoices')->where('id', $invoice->id)->update(['invoice_status' => 'final']);
            $statusNow = $this->refreshInvoicePaymentStatus($invoice->fresh());
            if ($statusNow === 'paid') {
                app(\App\Services\ReferrerCommissionService::class)->ensureDebtRowsByInvoiceId($invoice->id);
                app(\App\Services\ReferrerCommissionService::class)->payoutIfInvoicePaid($invoice->fresh());
            }
        });

        $fresh = $invoice->fresh();
        return response()->json([
            'success' => true,
            'message' => 'نهایی‌سازی پرداخت تفکیکی انجام شد.',
            'invoice_status' => $fresh->invoice_status,
            'payment_status' => $fresh->payment_status,
            'summary' => $this->buildSplitSummary($fresh),
        ]);
    }

    public function paySplit(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            // پرداخت به پرسنل (اختیاری)
            'payments'                               => ['nullable', 'array'],
            'payments.*.invoice_item_id'             => ['required_with:payments', 'integer', 'exists:invoice_items,id'],
            'payments.*.staff_id'                    => ['required_with:payments', 'integer', 'exists:staff,id'],
            'payments.*.amount'                      => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.staffpaymentgateway_id'      => ['nullable', 'integer', 'exists:staff_payment_gateways,id'],
            'payments.*.method'                      => ['nullable', 'in:card_to_card,cash,shaba,account_transfer,online,pos,cheque'],
            // می‌توان ref را هم دریافت کرد (سازگاری)
            'payments.*.ref'                         => ['nullable', 'string', 'max:100'],

            // سهم سالن (اختیاری)
            'salon_amount'    => ['nullable', 'numeric', 'min:0'],
            //'salon_account_id' => ['required_with:salon_amount', 'integer', 'exists:salonbankaccounts,id'],
            'salon_amount'     => ['nullable', 'numeric', 'min:0'],
            'salon_account_id' => ['required_if:salon_amount,1,2,3,...', 'integer', 'exists:salonbankaccounts,id'],

            // روش کلی پرداخت (برای سهم سالن)
            'method'      => ['required', 'in:card_to_card,cash,shaba,account_transfer,wallet,online,pos,cheque'],
            'paid_at'     => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        //$paidAt = $this->parseMaybeJalali($data['paid_at'] ?? null) ?? now();
        $paidAt = \Illuminate\Support\Carbon::parse($invoice->registration_date ?? now());

        // -------- 1) پرداخت کمیسیون پرسنل برای هر آیتم --------
        $totalStaff = 0.0;
        if (!empty($data['payments'])) {
            foreach ($data['payments'] as $p) {
                $item = InvoiceItem::query()
                    ->where('id', $p['invoice_item_id'])
                    ->where('invoice_id', $invoice->id)
                    ->firstOrFail();

                if ((int)$item->staff_id !== (int)($p['staff_id'] ?? 0)) {
                    return response()->json(['ok' => false, 'message' => 'Staff mismatch for item ' . $item->id], 422);
                }

                if ($item->staff_commission_status === 'paid') {
                    continue;
                }

                $amount = isset($p['amount']) && $p['amount'] !== '' ? (float)$p['amount'] : (float)($item->staff_commission_amount ?? 0);
                if ($amount <= 0) continue;

                $totalStaff += $amount;

                DB::table('staff_incomes')->insert([
                    'staff_id'          => (int)$p['staff_id'],
                    'invoice_id'        => $invoice->id,
                    'invoice_item_id'   => $item->id,
                    'amount'            => $amount,
                    'commission_status' => 'credit',
                    'payment_method'    => $p['method'] ?? null,        // 📌 NEW
                    'reference_number'  => $p['ref'] ?? null,           // 📌 NEW
                    'created_at'        => $paidAt,
                    'updated_at'        => $paidAt,
                ]);

                $updateData = [
                    'staff_commission_paid_at' => $paidAt,
                    'staff_commission_status'  => 'paid',
                    'updated_at'               => $paidAt,
                ];
                if (!empty($p['method'])) {
                    $updateData['staff_commission_payment_method'] = $p['method'];
                }
                DB::table('invoice_items')->where('id', $item->id)->update($updateData);

                $item->staff_commission_status  = 'paid';
                $item->staff_commission_paid_at = $paidAt;
                if (!empty($p['method'])) $item->staff_commission_payment_method = $p['method'];
                $item->save();
            }
        }

        // -------- 2) سهم سالن: ذخیره در salon_incomes --------
        if (!empty($data['salon_amount']) && !empty($data['salon_account_id'])) {
            $salonPay = (float)$data['salon_amount'];

            $incomeId = DB::table('salon_incomes')->insertGetId([
                'invoice_id'       => $invoice->id,
                'salon_account_id' => $data['salon_account_id'],
                'payment_method'   => $data['method'],       // 📌 CHANGED
                'amount'           => $salonPay,
                'reference_number' => null,
                'note'             => $data['description'] ?? null,
                'paid_at'          => $paidAt,
                'created_by'       => Auth::guard('admin')->id(),
                'status'           => ($data['method'] === 'cheque') ? 'pending' : 'posted',
                'created_at'       => $paidAt,
                'updated_at'       => $paidAt,
            ]);

            if ($data['method'] === 'wallet') {
                $w   = DB::table('customerswallets')->where('customer_id', $invoice->customer_id)->lockForUpdate()->first();
                $bal = (float)($w->current_balance ?? 0);
                if ($salonPay > $bal) {
                    return response()->json(['ok' => false, 'message' => 'مبلغ بیشتر از موجودی کیف‌ پول است.'], 422);
                }
                DB::table('customerswallets')->where('customer_id', $invoice->customer_id)
                    ->update(['current_balance' => $bal - $salonPay, 'last_updated' => $paidAt]);
            } elseif ($data['method'] === 'cheque') {
                $payload = [
                    'cheque_serial'         => null,
                    'cheque_account_number' => null,
                    'cheque_bank_name'      => null,
                    'cheque_amount'         => $salonPay,
                    'cheque_issue_date'     => $paidAt,
                    'cheque_due_date'       => null,
                    'cheque_status'         => 'pending',
                    'cheque_issuer'         => null,
                    'cheque_issuer_type'    => 'customer',
                    'cheque_issuer_id'      => $invoice->customer_id,
                    'receiver'              => 'salon',
                    'receiver_type'         => 'salon',
                    'receiver_id'           => null,
                    'deposit_account_id'    => $data['salon_account_id'],
                    'transaction_id'        => null,
                    'status_changed_at'     => $paidAt,
                    'description'           => $data['description'] ?? null,
                    'created_at'            => $paidAt,
                    'updated_at'            => $paidAt,
                ];
                if (Schema::hasColumn('received_checks', 'invoice_income_id')) {
                    $payload['invoice_income_id'] = $incomeId;
                }
                DB::table('received_checks')->insert($payload);
            }
        }

        // -------- 3) وضعیت فاکتور --------
        $pendingItems = DB::table('invoice_items')
            ->where('invoice_id', $invoice->id)
            ->where('staff_commission_status', '!=', 'paid')
            ->count();

        $statusNow = $this->refreshInvoicePaymentStatus($invoice);

        // همیشه بدهی‌های معرف را بساز/به‌روزرسانی کن
        app(\App\Services\ReferrerCommissionService::class)->ensureDebtRowsByInvoiceId($invoice->id);

        // اگر paid شد، تسویه کن
        if ($statusNow === 'paid') {
            app(\App\Services\ReferrerCommissionService::class)->payoutIfInvoicePaid($invoice->fresh());
        }

        if ($invoice->invoice_status === 'draft' && $statusNow === 'paid' && $pendingItems === 0) {
            DB::table('invoices')->where('id', $invoice->id)->update(['invoice_status' => 'final']);
        }

        // -------- 4) خروجی تحلیلی --------
        $totalStaffCommission = (float) DB::table('invoice_items')
            ->where('invoice_id', $invoice->id)
            ->selectRaw('COALESCE(SUM(staff_commission_amount), 0) AS s')
            ->value('s');

        $salonDueExpected = max(0, (float)$invoice->final_amount - $totalStaffCommission);
        $salonPaid = $this->sumInvoiceIncomes($invoice->id, 'posted');
        $allDone = ($pendingItems === 0) && ($salonPaid + 0.0001 >= $salonDueExpected);

        if ($allDone) {
            DB::table('invoices')->where('id', $invoice->id)->update([
                'paid_amount'    => (float)$invoice->final_amount,
                'payment_status' => 'paid',
                'invoice_status' => $invoice->invoice_status === 'draft' ? 'final' : $invoice->invoice_status,
            ]);
        } else {
            DB::table('invoices')->where('id', $invoice->id)->update([
                'paid_amount'    => min($salonPaid, (float)$invoice->final_amount),
                'payment_status' => ($salonPaid > 0 || $pendingItems < DB::table('invoice_items')->where('invoice_id', $invoice->id)->count())
                    ? 'partial' : 'unpaid',
            ]);
        }
        $this->finalizeIfSettled($invoice);

        return response()->json([
            'ok'                => true,
            'invoice_id'        => $invoice->id,
            'staff_paid_total'  => $totalStaff,
            'payment_status'    => $statusNow,
            'all_done'          => $allDone,
            'pending_items'     => $pendingItems,
            'salon_due'         => $salonDueExpected,
            'salon_paid'        => $salonPaid,
            'salon_remaining'   => max(0, $salonDueExpected - $salonPaid),
        ]);
    }

    // app/Http/Controllers/Admin/InvoiceController.php

    public function forceDestroy(Request $request, Invoice $invoice)
    {
        $admin = Auth::guard('admin')->user();
        if (!$admin || !$admin->is_superadmin) {
            return response()->json(['message' => 'مجوز حذف دائمی ندارید.'], 403);
        }

        $actorId   = (int) ($admin->id ?? 0);
        $invoiceId = (int) $invoice->id;
        $ip        = $request->ip();
        $ua        = substr($request->userAgent() ?? '', 0, 255);

        $walletRefund = 0.0;
        $metaCounts   = [
            'received_checks'    => 0,
            'salon_transactions' => 0,
            'staff_transactions' => 0,
            'salon_incomes'      => 0,
            'invoice_items'      => 0,
        ];

        try {
            DB::transaction(function () use ($invoice, &$walletRefund, &$metaCounts, $actorId, $invoiceId, $ip, $ua) {

                // شناسه‌ها
                $salonIncomeIds = Schema::hasTable('salon_incomes')
                    ? DB::table('salon_incomes')->where('invoice_id', $invoice->id)->pluck('id')->all() : [];
                $staffIncomeIds = Schema::hasTable('staff_incomes')
                    ? DB::table('staff_incomes')->where('invoice_id', $invoice->id)->pluck('id')->all() : [];
                $referrerIncomeIds = Schema::hasTable('referrer_incomes')
                    ? DB::table('referrer_incomes')->where('invoice_id', $invoice->id)->pluck('id')->all() : [];
                $depositIds = Schema::hasTable('reservation_deposits')
                    ? DB::table('reservation_deposits')->where('invoice_id', $invoice->id)->pluck('id')->all() : [];
                $itemIds = DB::table('invoice_items')->where('invoice_id', $invoice->id)->pluck('id')->all();

                // ریفاند کیف پول (wallet/posted)
                $walletRefund = Schema::hasTable('salon_incomes')
                    ? (float) DB::table('salon_incomes')
                        ->where('invoice_id', $invoice->id)
                        ->where('payment_method', 'wallet')
                        ->where('status', 'posted')
                        ->sum('amount')
                    : 0;

                if ($walletRefund > 0 && Schema::hasTable('customerswallets')) {
                    $wallet = DB::table('customerswallets')->where('customer_id', $invoice->customer_id)->lockForUpdate()->first();
                    if ($wallet) {
                        DB::table('customerswallets')->where('id', $wallet->id)->update([
                            'current_balance' => DB::raw('current_balance + ' . $walletRefund),
                            'last_updated'    => now(),
                        ]);
                    } else {
                        DB::table('customerswallets')->insert([
                            'customer_id'     => $invoice->customer_id,
                            'current_balance' => $walletRefund,
                            'created_at'      => now(),
                            'last_updated'    => now(),
                        ]);
                    }
                }

                // اصلاح شمارندهٔ کد تخفیف
                if ($invoice->discount_code_id && Schema::hasTable('discount_codes')) {
                    DB::table('discount_codes')->where('id', $invoice->discount_code_id)->update([
                        'times_used' => DB::raw('CASE WHEN times_used > 0 THEN times_used - 1 ELSE 0 END')
                    ]);
                }

                // حذف چک‌های لینک‌شده (ایمن نسبت به ستون‌های متفاوت)
                if (Schema::hasTable('received_checks')) {
                    if (!empty($salonIncomeIds) && Schema::hasColumn('received_checks', 'invoice_income_id')) {
                        $metaCounts['received_checks'] += DB::table('received_checks')
                            ->whereIn('invoice_income_id', $salonIncomeIds)->delete();
                    }
                    if (!empty($depositIds) && Schema::hasColumn('received_checks', 'invoice_deposit_id')) {
                        $metaCounts['received_checks'] += DB::table('received_checks')
                            ->whereIn('invoice_deposit_id', $depositIds)->delete();
                    }
                }

                // حذف تراکنش‌ها (اگر چنین جدول‌هایی دارید)
                if (Schema::hasTable('salon_transactions')) {
                    $q = DB::table('salon_transactions');
                    if (!empty($salonIncomeIds)) {
                        $q->orWhere(function ($qq) use ($salonIncomeIds) {
                            $qq->where('related_type', 'invoice_income')
                                ->whereIn('related_id', $salonIncomeIds);
                        });
                    }
                    if (!empty($depositIds)) {
                        $q->orWhere(function ($qq) use ($depositIds) {
                            $qq->where('related_type', 'invoice_deposit')
                                ->whereIn('related_id', $depositIds);
                        });
                    }
                    $q->orWhere(function ($qq) use ($invoice) {
                        $qq->where('related_type', 'invoice')->where('related_id', $invoice->id);
                    });
                    $metaCounts['salon_transactions'] += $q->delete();
                }

                if (Schema::hasTable('staff_transactions') && !empty($itemIds)) {
                    $metaCounts['staff_transactions'] += DB::table('staff_transactions')
                        ->where(function ($q) use ($itemIds) {
                            $q->where('related_type', 'invoice_item')->whereIn('related_id', $itemIds);
                        })
                        ->orWhere(function ($q) use ($invoice) {
                            $q->where('related_type', 'invoice')->where('related_id', $invoice->id);
                        })
                        ->delete();
                }

                // حذف رکوردهای مستقیم
                if (Schema::hasTable('referrer_incomes')) {
                    DB::table('referrer_incomes')->where('invoice_id', $invoice->id)->delete();
                }
                if (Schema::hasTable('staff_incomes')) {
                    DB::table('staff_incomes')->where('invoice_id', $invoice->id)->delete();
                }
                if (Schema::hasTable('salon_incomes')) {
                    $metaCounts['salon_incomes'] += DB::table('salon_incomes')->where('invoice_id', $invoice->id)->delete();
                }
                $metaCounts['invoice_items'] += DB::table('invoice_items')->where('invoice_id', $invoice->id)->delete();

                // خود فاکتور
                DB::table('invoices')->where('id', $invoice->id)->delete();

                // لاگ بعد از commit (اختیاری)
                DB::afterCommit(function () use ($actorId, $invoiceId, $walletRefund, $ip, $ua, $metaCounts) {
                    try {
                        if (Schema::hasTable('audit_logs')) {
                            \App\Models\AuditLog::create([
                                'actor_id'    => $actorId,
                                'actor_type'  => 'admin',
                                'action'      => 'invoice.force_delete',
                                'status'      => 'success',
                                'target_type' => 'invoice',
                                'target_id'   => $invoiceId,
                                'message'     => 'Invoice permanently deleted.',
                                'meta'        => ['walletRefund' => $walletRefund, 'counts' => $metaCounts],
                                'ip'          => $ip,
                                'user_agent'  => $ua,
                            ]);
                        }
                    } catch (\Throwable $e) {
                    }
                });
            });

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            // لاگ خطا و پاسخ ایمن
            try {
                if (Schema::hasTable('audit_logs')) {
                    \App\Models\AuditLog::create([
                        'actor_id'    => $actorId,
                        'actor_type'  => 'admin',
                        'action'      => 'invoice.force_delete',
                        'status'      => 'error',
                        'target_type' => 'invoice',
                        'target_id'   => $invoiceId,
                        'message'     => 'Delete failed: ' . mb_substr($e->getMessage(), 0, 480),
                        'meta'        => null,
                        'ip'          => $ip,
                        'user_agent'  => $ua,
                    ]);
                }
            } catch (\Throwable $ignore) {
            }
            report($e);
            return response()->json(['success' => false, 'message' => 'حذف انجام نشد.'], 500);
        }
    }
}

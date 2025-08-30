<?php
// app/Http/Controllers/Admin/ReceivedCheckController.php
namespace App\Http\Controllers\Admin;

use App\Models\ReceivedCheck;
use App\Models\Staff;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Helpers\JalaliHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ReceivedCheckController extends Controller
{
    /**
     * نمایش لیست چک‌های دریافتی (با قابلیت جستجو)
     */
    public function index(Request $request)
    {
        try {
            $q = $request->input('q');

            $checks = ReceivedCheck::query()
                ->leftJoin('bank_lists as bl', 'bl.id', '=', 'received_checks.cheque_bank_name')
                ->select('received_checks.*', 'bl.name as bank_name_text', 'bl.short_name as bank_short_name')
                ->when($q, function ($query, $q) {
                    $like = "%{$q}%";
                    return $query->where(function ($w) use ($like) {
                        $w->where('received_checks.cheque_serial', 'like', $like)
                            ->orWhere('received_checks.cheque_amount', 'like', $like)
                            ->orWhere('received_checks.cheque_issuer', 'like', $like)
                            ->orWhere('received_checks.receiver', 'like', $like)
                            ->orWhere('received_checks.cheque_account_number', 'like', $like)
                            ->orWhere('received_checks.cheque_status', 'like', $like)
                            ->orWhere('received_checks.description', 'like', $like)
                            ->orWhere('received_checks.cheque_issue_date', 'like', $like)
                            ->orWhere('received_checks.cheque_due_date', 'like', $like)
                            // جستجو روی نام/مخفف بانک از جدول bank_lists
                            ->orWhere('bl.name', 'like', $like)
                            ->orWhere('bl.short_name', 'like', $like);
                    });
                })
                ->orderByDesc('received_checks.id')
                ->paginate(15);

            $this->logActivity('مشاهده لیست چک‌های دریافتی', $q ? ('جستجو: ' . $q) : null);

            return panelView('admin', 'received_checks.index', compact('checks', 'q'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در مشاهده لیست چک‌های دریافتی', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * نمایش فرم افزودن چک دریافتی
     */
    public function create()
    {
        try {
            // برای دراپ‌دان بانک‌ها
            $banks = DB::table('bank_lists')->orderBy('name')->get(['id', 'name', 'short_name']);
            $this->logActivity('مشاهده فرم افزودن چک دریافتی', null);

            return panelView('admin', 'received_checks.create', compact('banks'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در نمایش فرم افزودن چک دریافتی', $e->getMessage());
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * ثبت چک جدید
     */
    public function store(Request $request)
    {
        // تاریخ شمسی به میلادی (برای هر دو تاریخ)
        Log::info('مقدار اولیه تاریخ‌ها:', [
            'cheque_issue_date_jalali' => $request->input('cheque_issue_date_jalali'),
            'cheque_due_date_jalali'   => $request->input('cheque_due_date_jalali')
        ]);

        $issue_jalali = $request->input('cheque_issue_date_jalali');
        if ($issue_jalali) {
            $issue_gregorian = JalaliHelper::toGregorian($issue_jalali);
            Log::info('تبدیل تاریخ صدور:', [
                'cheque_issue_date_jalali' => $issue_jalali,
                'cheque_issue_date' => $issue_gregorian
            ]);
            if (!$issue_gregorian) {
                return back()->withErrors(['cheque_issue_date_jalali' => 'تاریخ صدور نامعتبر است.'])->withInput();
            }
            $request->merge(['cheque_issue_date' => $issue_gregorian]);
        }

        $due_jalali = $request->input('cheque_due_date_jalali');
        if ($due_jalali) {
            $due_gregorian = JalaliHelper::toGregorian($due_jalali);
            Log::info('تبدیل تاریخ سررسید:', [
                'cheque_due_date_jalali' => $due_jalali,
                'cheque_due_date' => $due_gregorian
            ]);
            if (!$due_gregorian) {
                return back()->withErrors(['cheque_due_date_jalali' => 'تاریخ سررسید نامعتبر است.'])->withInput();
            }
            $request->merge(['cheque_due_date' => $due_gregorian]);
        }

        if (config('app.debug')) {
            $safe = [
                'cheque_serial' => $request->input('cheque_serial'),
                'cheque_amount' => $request->input('cheque_amount'),
                'cheque_issue_date' => $request->input('cheque_issue_date'),
                'cheque_due_date'   => $request->input('cheque_due_date'),
            ];
            Log::debug('ReceivedCheck sanitized inputs', $safe);
        }

        $data = $request->validate([
            'cheque_serial'          => 'required|string|max:255',
            'cheque_amount'          => 'required|numeric|min:0',
            'cheque_issue_date'      => 'required|date',
            'cheque_due_date'        => 'required|date',

            // 👈 هماهنگ با تغییر DB: عدد و کلیدخارجی به bank_lists(id)
            'cheque_bank_name'       => 'nullable|integer|exists:bank_lists,id',

            // 👈 اندازه تا 255 (مطابق اسکرین‌شات/DB)
            'cheque_account_number'  => 'nullable|string|max:255',

            'cheque_status'          => 'nullable|string|max:30',
            'cheque_issuer'          => 'nullable|string|max:255',
            'cheque_issuer_type'     => 'nullable|string|max:100',
            'cheque_issuer_id'       => 'nullable|integer',
            'receiver'               => 'nullable|string|max:255',
            'receiver_type'          => 'nullable|string|max:100',
            'receiver_id'            => 'nullable|integer',
            'deposit_account_id'     => 'nullable|integer',
            'transaction_id'         => 'nullable|integer',
            'status_changed_at'      => 'nullable|date',
            'description'            => 'nullable|string',
            'transferred_to_type'    => 'nullable|string|max:100',
            'transferred_to_id'      => 'nullable|integer',
            'transferred_at'         => 'nullable|date',
        ]);
        $data = $request->validate($this->rules());

        Log::info('داده‌های تاییدشده برای ذخیره:', $data);

        try {
            $created = ReceivedCheck::create($data);

            Log::info('رکورد جدید چک دریافتی ایجاد شد:', ['id' => $created->id]);
            $this->logActivity('ایجاد چک دریافتی', 'شماره سریال: ' . $created->cheque_serial . ' | مبلغ: ' . $created->cheque_amount);

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => 'چک با موفقیت ثبت شد.']);
            }
            return redirect()->route('admin.received-checks.index')->with('success', 'چک ثبت شد.');
        } catch (\Exception $e) {
            Log::error('خطا در ایجاد چک دریافتی:', ['exception' => $e->getMessage()]);
            $this->logActivity('خطا در ثبت چک دریافتی', $e->getMessage());

            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500);
            }
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * نمایش فرم ویرایش چک دریافتی
     */
    public function edit(ReceivedCheck $receivedCheck)
    {
        try {
            // برای دراپ‌دان بانک‌ها
            $banks = DB::table('bank_lists')->orderBy('name')->get(['id', 'name', 'short_name']);

            $this->logActivity('مشاهده فرم ویرایش چک دریافتی', 'شماره سریال: ' . $receivedCheck->cheque_serial . ' | آی‌دی: ' . $receivedCheck->id);
            return panelView('admin', 'received_checks.edit', compact('receivedCheck', 'banks'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در نمایش فرم ویرایش چک دریافتی', $e->getMessage());
            return redirect()->route('admin.received-checks.index')->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * بروزرسانی چک دریافتی
     */
    public function update(Request $request, ReceivedCheck $receivedCheck)
    {
        $issue_jalali = $request->input('cheque_issue_date_jalali');
        if ($issue_jalali) {
            $issue_gregorian = JalaliHelper::toGregorian($issue_jalali);
            if ($issue_gregorian) {
                $request->merge(['cheque_issue_date' => $issue_gregorian]);
            }
        }
        $due_jalali = $request->input('cheque_due_date_jalali');
        if ($due_jalali) {
            $due_gregorian = JalaliHelper::toGregorian($due_jalali);
            if ($due_gregorian) {
                $request->merge(['cheque_due_date' => $due_gregorian]);
            }
        }

        $data = $request->validate([
            'cheque_serial'          => 'required|string|max:255',
            'cheque_amount'          => 'required|numeric|min:0',
            'cheque_issue_date'      => 'required|date',
            'cheque_due_date'        => 'required|date',

            // 👈 مطابق DB جدید
            'cheque_bank_name'       => 'nullable|integer|exists:bank_lists,id',

            // 👈 افزایش طول
            'cheque_account_number'  => 'nullable|string|max:255',

            'cheque_status'          => 'nullable|string|max:30',
            'cheque_issuer'          => 'nullable|string|max:255',
            'cheque_issuer_type'     => 'nullable|string|max:100',
            'cheque_issuer_id'       => 'nullable|integer',
            'receiver'               => 'nullable|string|max:255',
            'receiver_type'          => 'nullable|string|max:100',
            'receiver_id'            => 'nullable|integer',
            'deposit_account_id'     => 'nullable|integer',
            'transaction_id'         => 'nullable|integer',
            'status_changed_at'      => 'nullable|date',
            'description'            => 'nullable|string',
            'transferred_to_type'    => 'nullable|string|max:100',
            'transferred_to_id'      => 'nullable|integer',
            'transferred_at'         => 'nullable|date',
        ]);
$data = $request->validate($this->rules());

        // اگر انتقال دادند
        if (!empty($data['transferred_to_type']) && !empty($data['transferred_to_id'])) {
            $data['cheque_status'] = 'transferred';
        }

        try {
            $receivedCheck->update($data);
            $this->logActivity('ویرایش چک دریافتی', 'شماره سریال: ' . $receivedCheck->cheque_serial . ' | آی‌دی: ' . $receivedCheck->id);

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => 'چک بروزرسانی شد.']);
            }
            return redirect()->route('admin.received-checks.index')->with('success', 'چک بروزرسانی شد.');
        } catch (\Exception $e) {
            $this->logActivity('خطا در بروزرسانی چک دریافتی', $e->getMessage());
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500);
            }
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * حذف چک دریافتی
     */
    public function destroy(ReceivedCheck $receivedCheck, Request $request)
    {
        try {
            $this->logActivity('حذف چک دریافتی', 'شماره سریال: ' . $receivedCheck->cheque_serial . ' | آی‌دی: ' . $receivedCheck->id);
            $receivedCheck->delete();
            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => 'چک حذف شد.']);
            }
            return redirect()->route('admin.received-checks.index')->with('success', 'چک حذف شد.');
        } catch (\Exception $e) {
            $this->logActivity('خطا در حذف چک دریافتی', $e->getMessage());
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500);
            }
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * جستجوی شخص (مشتری/پرسنل/تماس) برای ارتباط با چک
     */
    public function searchPartyAll(Request $request)
    {
        try {
            $q = $request->input('q');
            if (!$q) return response()->json([]);

            $customers = Customer::where('full_name', 'like', "%$q%")
                ->orWhere('national_code', 'like', "%$q%")
                ->orWhere('phone', 'like', "%$q%")
                ->take(8)->get()
                ->map(function ($item) {
                    return [
                        'type'          => 'customer',
                        'type_fa'       => 'مشتری',
                        'id'            => $item->id,
                        'name'          => $item->full_name,
                        'national_code' => $item->national_code,
                        'phone'         => $item->phone,
                    ];
                });

            $staff = Staff::where('full_name', 'like', "%$q%")
                ->orWhere('national_code', 'like', "%$q%")
                ->orWhere('phone', 'like', "%$q%")
                ->take(8)->get()
                ->map(function ($item) {
                    return [
                        'type'          => 'staff',
                        'type_fa'       => 'پرسنل',
                        'id'            => $item->id,
                        'name'          => $item->full_name,
                        'national_code' => $item->national_code,
                        'phone'         => $item->phone,
                    ];
                });

            $contacts = Contact::where('name', 'like', "%$q%")
                ->orWhere('national_code', 'like', "%$q%")
                ->orWhere('mobile', 'like', "%$q%")
                ->orWhere('phone', 'like', "%$q%")
                ->take(8)->get()
                ->map(function ($item) {
                    return [
                        'type'          => 'contact',
                        'type_fa'       => 'سایر',
                        'id'            => $item->id,
                        'name'          => $item->name,
                        'national_code' => $item->national_code,
                        'phone'         => $item->mobile ?: $item->phone,
                    ];
                });

            $all = $customers->concat($staff)->concat($contacts)->take(12)->values();
            return response()->json($all);
        } catch (\Exception $e) {
            // برای متد جستجو لاگ ثبت نکن تا دیتابیس شلوغ نشود!
            return response()->json([], 500);
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


    // قوانین مشترک برای store و update
    protected function rules(): array
    {
        return [
            'cheque_serial'         => ['required', 'string', 'max:255'],
            'cheque_amount'         => ['required', 'numeric', 'min:0'],
            'cheque_issue_date'     => ['required', 'date'],
            'cheque_due_date'       => ['required', 'date'],

            // کلیدخارجی معتبر به bank_lists(id)
            'cheque_bank_name'      => ['nullable', 'integer', 'exists:bank_lists,id'],

            'cheque_account_number' => ['nullable', 'string', 'max:255'],

            // فقط این 5 مقدار مجازند (هماهنگ با Check Constraint دیتابیس)
            'cheque_status'         => ['nullable', 'in:pending,deposited,returned,void,transferred'],

            'cheque_issuer'         => ['nullable', 'string', 'max:255'],
            'cheque_issuer_type'    => ['nullable', 'string', 'max:100'],
            'cheque_issuer_id'      => ['nullable', 'integer'],

            'receiver'              => ['nullable', 'string', 'max:255'],
            'receiver_type'         => ['nullable', 'string', 'max:100'],
            'receiver_id'           => ['nullable', 'integer'],

            // این دو تا هم FK هستند؛ بهتره exists بگذارید
            'deposit_account_id'    => ['nullable', 'integer', 'exists:salonbankaccounts,id'],
            'transaction_id'        => ['nullable', 'integer', 'exists:transactions,id'],

            'status_changed_at'     => ['nullable', 'date'],
            'description'           => ['nullable', 'string'],

            // اگر انتقال می‌دید، نوع طرف فقط یکی از این 3 تا باشد
            'transferred_to_type'   => ['nullable', 'in:customer,staff,contact'],
            'transferred_to_id'     => ['nullable', 'integer'],
            'transferred_at'        => ['nullable', 'date'],

            // این دو ستون را هم اضافه کنید (در DB دارید)
            'invoice_deposit_id'    => ['nullable', 'integer', 'exists:reservation_deposits,id'],
            'invoice_income_id'     => ['nullable', 'integer', 'exists:salon_incomes,id'],
        ];
    }
}

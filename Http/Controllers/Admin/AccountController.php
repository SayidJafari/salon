<?php
// app/Http/Controllers/Admin/AccountController.php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\SalonBankAccount;
use App\Models\CashBox; // استفاده برای صندوق
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class AccountController extends Controller
{
    /**
     * نمایش لیست حساب‌ها + صندوق‌ها
     */
    public function index(Request $request)
    {
        try {
            $accounts  = SalonBankAccount::orderBy('id', 'desc')->get();
            $cashBoxes = DB::table('cash_boxes')->orderBy('id', 'desc')->get();

            $this->logActivity('مشاهده لیست حساب‌های بانکی و صندوق‌ها');

            if ($request->ajax()) {
                return panelView('admin', 'accounts.partials.list', compact('accounts', 'cashBoxes'));
            }

            return panelView('admin', 'accounts.index', compact('accounts', 'cashBoxes'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در مشاهده لیست حساب‌ها/صندوق‌ها', $e->getMessage());
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500);
            }
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    /**
     * فرم ایجاد (نوع با پارامتر kind مشخص می‌شود)
     */
    public function create(Request $request)
    {
        try {
            $kind  = $request->query('kind', 'bank'); // bank | cashbox
            $banks = DB::table('bank_lists')
                ->orderBy('name')
                ->get(['id', 'name', 'short_name']); // ← برای <select>

            $this->logActivity('نمایش فرم ایجاد ' . ($kind === 'cashbox' ? 'صندوق' : 'حساب بانکی'));

            if ($request->ajax()) {
                return panelView('admin', 'accounts.partials.create', compact('kind', 'banks'));
            }
            return panelView('admin', 'accounts.create', compact('kind', 'banks'));
        } catch (\Exception $e) {
            $this->logActivity('خطا در نمایش فرم ایجاد', $e->getMessage());
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500);
            }
            return redirect()->back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }


    /**
     * ثبت مورد جدید (حساب بانکی یا صندوق)
     */
  public function store(Request $request)
{
    $kind = $request->input('kind', 'bank'); // bank | cashbox

    // اگر سریال پوز وارد شده باشد، has_pos را 1 کن. is_active هم بولی شود.
    $request->merge([
        'has_pos'   => ($request->boolean('has_pos') || filled($request->input('pos_terminal'))) ? 1 : 0,
        'is_active' => $request->boolean('is_active') ? 1 : 0,
    ]);

    // فیلدهای بانکی که اگر خالی باشند null شوند (title را عمداً نمی‌زنیم چون الزامی است)
    foreach (['account_number','shaba_number','card_number','pos_terminal','owner_name','bank_name'] as $f) {
        if ($request->has($f) && $request->input($f) === '') {
            $request->merge([$f => null]);
        }
    }

    // ---------- صندوق ----------
    if ($kind === 'cashbox') {
        $validator = Validator::make($request->all(), [
            'location'  => 'nullable|string|max:255',
            'is_active' => ['required', Rule::in([0, 1])],
        ]);

        if ($validator->fails()) {
            return $request->ajax()
                ? response()->json(['success' => false, 'errors' => $validator->errors()], 422)
                : back()->withErrors($validator)->withInput();
        }

        try {
            $data = Arr::except($validator->validated(), ['kind']);
            $box  = CashBox::create($data);

            $this->logActivity('ایجاد صندوق', 'آی‌دی: ' . $box->id . ' | محل/توضیحات: ' . ($box->location ?? '-'));

            return $request->ajax()
                ? response()->json(['success' => true, 'message' => 'صندوق با موفقیت ثبت شد!'])
                : redirect()->route('admin.accounts.create', ['kind' => 'cashbox'])
                    ->with('success', 'صندوق با موفقیت ثبت شد!');
        } catch (\Exception $e) {
            $this->logActivity('خطا در ایجاد صندوق', $e->getMessage());
            return $request->ajax()
                ? response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500)
                : back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.')->withInput();
        }
    }

    // ---------- حساب بانکی ----------
    $validator = Validator::make($request->all(), [
        'kind'           => ['required', Rule::in(['bank', 'cashbox'])],
        'title'          => 'required|string|max:100',
        'bank_name'      => 'nullable|integer|exists:bank_lists,id',
        'account_number' => 'nullable|string|max:30',
        'shaba_number'   => 'nullable|string|max:30',
        'card_number'    => 'nullable|string|max:30',
        'pos_terminal'   => 'nullable|string|max:100', // سریال پوز
        'owner_name'     => 'nullable|string|max:100',
        'is_active'      => ['required', Rule::in([0, 1])],
        'has_pos'        => ['required', Rule::in([0, 1])],
    ]);

    // اگر has_pos=1 بود، سریال پوز الزامی شود
    $validator->sometimes('pos_terminal', 'required|string|max:100', function ($input) {
        return (int) $input->has_pos === 1;
    });

    if ($validator->fails()) {
        return $request->ajax()
            ? response()->json(['success' => false, 'errors' => $validator->errors()], 422)
            : back()->withErrors($validator)->withInput();
    }

    try {
        $data = Arr::except($validator->validated(), ['kind']);

        // اگر POS ندارد، سریال را null کن
        if (!(int) $data['has_pos']) {
            $data['pos_terminal'] = null;
        }

        $account = SalonBankAccount::create($data);

        $this->logActivity('ایجاد حساب بانکی', 'عنوان حساب: ' . $account->title . ' | آی‌دی: ' . $account->id);

        return $request->ajax()
            ? response()->json(['success' => true, 'message' => 'حساب بانکی با موفقیت ثبت شد!'])
            : redirect()->route('admin.accounts.create', ['kind' => 'bank'])
                ->with('success', 'حساب بانکی با موفقیت ثبت شد!');
    } catch (\Exception $e) {
        $this->logActivity('خطا در ایجاد حساب بانکی', $e->getMessage());
        return $request->ajax()
            ? response()->json(['success' => false, 'message' => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.'], 500)
            : back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.')->withInput();
    }
}


    /**
     * فرم ویرایش (بر اساس kind)
     */
    public function edit(Request $request, $id)
    {
        try {
            $kind = $request->query('kind', 'bank');

            if ($kind === 'cashbox') {
                $box = CashBox::findOrFail($id);
                $this->logActivity('نمایش فرم ویرایش صندوق', 'آی‌دی: ' . $box->id . ' | محل/توضیحات: ' . ($box->location ?? '-'));

                return $request->ajax()
                    ? panelView('admin', 'accounts.partials.edit_cashbox', ['cashbox' => $box, 'kind' => 'cashbox'])
                    : panelView('admin', 'accounts.edit_cashbox', ['cashbox' => $box, 'kind' => 'cashbox']);
            }

            $account = SalonBankAccount::findOrFail($id);

            // ← لیست بانک‌ها برای <select>
            $banks = DB::table('bank_lists')
                ->orderBy('name')
                ->get(['id', 'name', 'short_name']);

            $this->logActivity('نمایش فرم ویرایش حساب بانکی', 'آی‌دی: ' . $account->id . ' | عنوان حساب: ' . $account->title);

            if ($request->ajax()) {
                return panelView('admin', 'accounts.partials.edit', compact('account', 'banks') + ['kind' => 'bank']);
            }
            return panelView('admin', 'accounts.edit', compact('account', 'banks') + ['kind' => 'bank']);
        } catch (\Exception $e) {
            $this->logActivity('خطا در نمایش فرم ویرایش', $e->getMessage());
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'خطایی رخ داد.'], 500);
            }
            return redirect()->route('admin.accounts.index')->with('error', 'خطایی رخ داد.');
        }
    }


    /**
     * ویرایش مورد (حساب بانکی یا صندوق)
     */
   public function update(Request $request, $id)
{
    $kind = $request->input('kind', $request->query('kind', 'bank'));

    // اگر سریال پوز وارد شده باشد، has_pos را 1 کن. is_active هم بولی شود.
    $request->merge([
        'has_pos'   => ($request->boolean('has_pos') || filled($request->input('pos_terminal'))) ? 1 : 0,
        'is_active' => $request->boolean('is_active') ? 1 : 0,
    ]);

    // فیلدهای بانکی خالی → null
    foreach (['account_number','shaba_number','card_number','pos_terminal','owner_name','bank_name'] as $f) {
        if ($request->has($f) && $request->input($f) === '') {
            $request->merge([$f => null]);
        }
    }

    // ---------- صندوق ----------
    if ($kind === 'cashbox') {
        try {
            $box = CashBox::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'location'  => 'nullable|string|max:255',
                'is_active' => ['required', Rule::in([0, 1])],
            ]);

            if ($validator->fails()) {
                return $request->ajax()
                    ? response()->json(['success' => false, 'errors' => $validator->errors()], 422)
                    : back()->withErrors($validator)->withInput();
            }

            $data = Arr::except($validator->validated(), ['kind']);
            $box->update($data);

            $this->logActivity('ویرایش صندوق', 'آی‌دی: ' . $box->id . ' | محل/توضیحات: ' . ($box->location ?? '-'));

            return $request->ajax()
                ? response()->json(['success' => true, 'message' => 'صندوق با موفقیت ویرایش شد!'])
                : redirect()->route('admin.accounts.index')->with('success', 'صندوق با موفقیت ویرایش شد!');
        } catch (\Exception $e) {
            $this->logActivity('خطا در ویرایش صندوق', $e->getMessage());
            return $request->ajax()
                ? response()->json(['success' => false, 'message' => 'خطایی رخ داد.'], 500)
                : back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.')->withInput();
        }
    }

    // ---------- حساب بانکی ----------
    try {
        $account = SalonBankAccount::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'kind'           => ['required', Rule::in(['bank', 'cashbox'])],
            'title'          => 'required|string|max:100',
            'bank_name'      => 'nullable|integer|exists:bank_lists,id',
            'account_number' => 'nullable|string|max:30',
            'shaba_number'   => 'nullable|string|max:30',
            'card_number'    => 'nullable|string|max:30',
            'pos_terminal'   => 'nullable|string|max:100',
            'owner_name'     => 'nullable|string|max:100',
            'is_active'      => ['required', Rule::in([0, 1])],
            'has_pos'        => ['required', Rule::in([0, 1])],
        ]);

        $validator->sometimes('pos_terminal', 'required|string|max:100', fn($input) => (int)$input->has_pos === 1);

        if ($validator->fails()) {
            return $request->ajax()
                ? response()->json(['success' => false, 'errors' => $validator->errors()], 422)
                : back()->withErrors($validator)->withInput();
        }

        $data = Arr::except($validator->validated(), ['kind']);

        if (!(int) $data['has_pos']) {
            $data['pos_terminal'] = null;
        }

        $account->update($data);

        $this->logActivity('ویرایش حساب بانکی', 'آی‌دی: ' . $account->id . ' | عنوان جدید: ' . $request->title);

        return $request->ajax()
            ? response()->json(['success' => true, 'message' => 'حساب بانکی با موفقیت ویرایش شد!'])
            : redirect()->route('admin.accounts.index')->with('success', 'حساب بانکی با موفقیت ویرایش شد!');
    } catch (\Exception $e) {
        $this->logActivity('خطا در ویرایش حساب بانکی', $e->getMessage());
        return $request->ajax()
            ? response()->json(['success' => false, 'message' => 'خطایی رخ داد.'], 500)
            : back()->with('error', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.')->withInput();
    }
}


    /**
     * حذف حساب/صندوق
     */
    public function destroy(Request $request, $id)
    {
        $kind = $request->input('kind', $request->query('kind', 'bank'));

        try {
            if ($kind === 'cashbox') {
                $box = CashBox::findOrFail($id);
                $this->logActivity('حذف صندوق', 'آی‌دی: ' . $box->id . ' | محل/توضیحات: ' . ($box->location ?? '-'));
                $box->delete();

                return $request->ajax()
                    ? response()->json(['success' => true, 'message' => 'صندوق با موفقیت حذف شد!'])
                    : redirect()->route('admin.accounts.index')->with('success', 'صندوق حذف شد!');
            }


            $account = SalonBankAccount::findOrFail($id);
            $this->logActivity('حذف حساب بانکی', 'آی‌دی: ' . $account->id . ' | عنوان: ' . $account->title);
            $account->delete();

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => 'حساب با موفقیت حذف شد!']);
            }
            return redirect()->route('admin.accounts.index')->with('success', 'حساب حذف شد!');
        } catch (\Exception $e) {
            $this->logActivity('خطا در حذف مورد', $e->getMessage());
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'خطایی رخ داد.'], 500);
            }
            return redirect()->route('admin.accounts.index')->with('error', 'خطایی رخ داد.');
        }
    }

    /**
     * ثبت لاگ اکشن‌های مدیر
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
            Log::error('Error in logActivity: ' . $e->getMessage(), ['action' => $action, 'details' => $details]);
        }
    }
}

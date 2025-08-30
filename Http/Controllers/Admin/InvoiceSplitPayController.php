<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\StaffIncome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceSplitPayController extends Controller
{
    // لیست پرداخت‌های پرسنل یک فاکتور (برای پرکردن جدول در صفحهٔ ویرایش)
    public function listStaffPayments(Invoice $invoice)
    {
        $rows = DB::table('staff_incomes as s')
            ->join('staff as st', 'st.id', '=', 's.staff_id')
            ->where('s.invoice_id', $invoice->id)
            ->where('s.commission_status', 'credit')   // فقط پرداخت‌شده‌ها
            ->orderByDesc('s.id')
            ->get([
                's.id',
                's.staff_id',
                'st.full_name as staff_name',
                's.amount',
                DB::raw("COALESCE(s.payment_method,'') as method"),
                DB::raw("COALESCE(s.reference_number,'') as ref"),
                DB::raw("strftime('%Y-%m-%d %H:%M', s.created_at) as paid_at"),
            ]);

        return response()->json(['success' => true, 'items' => $rows]);
    }

    // ویرایش یک پرداخت پرسنل
    public function updateStaffPayment(Request $request, StaffIncome $staffIncome)
    {
        $data = $request->validate([
            'amount'   => ['required', 'numeric', 'min:0'],
            'method'   => ['required', Rule::in(['cash','pos','card_to_card','account_transfer','online','cheque','wallet','shaba'])],
            'ref'      => ['nullable','string','max:190'],
        ]);

        $staffIncome->update([
            'amount'          => $data['amount'],
            'payment_method'  => $data['method'],
            'reference_number'=> $data['ref'] ?? null,
        ]);

        // به‌روزرسانی وضعیت فاکتور (اینجا اختیاری است اما خوب است)
        optional($staffIncome->invoice)->recalcPaymentStatus();

        return response()->json(['success' => true, 'payment' => $staffIncome->fresh()]);
    }

    // حذف پرداخت پرسنل
    public function destroyStaffPayment(StaffIncome $staffIncome)
    {
        $invoice = $staffIncome->invoice;
        $staffIncome->delete();
        $invoice?->recalcPaymentStatus();

        return response()->json(['success' => true]);
    }
}

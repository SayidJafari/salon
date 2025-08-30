<?php

namespace App\Http\Controllers\Admin;

use App\Models\SalonIncome;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;   // ⬅️ این خط را اضافه کنید

class InvoiceDepositController extends Controller
{
    public function show(SalonIncome $salonIncome)
    {
        $salonIncome->load('invoice');
        return response()->json([
            'success' => true,
            'deposit' => [
                'id'               => (int) $salonIncome->id,
                'invoice_id'       => (int) $salonIncome->invoice_id,
                'amount'           => (float) $salonIncome->amount,
                'method'           => $salonIncome->payment_method,
                'reference_number' => $salonIncome->reference_number,
                'paid_at'          => optional($salonIncome->paid_at)->format('Y-m-d H:i'),
                'salon_account_id' => $salonIncome->salon_account_id,
                'note'             => $salonIncome->note,
                'status'           => $salonIncome->status,
            ],
        ]);
    }

    public function update(Request $req, SalonIncome $salonIncome)
    {
        $data = $req->validate([
            'amount'            => ['required','numeric','min:0'],
            'method'            => ['required', Rule::in(['cash','pos','card_to_card','account_transfer','online','cheque','wallet','shaba'])],
            'reference_number'  => ['nullable','string','max:190'],
            'paid_at'           => ['nullable','date'],
            'salon_account_id'  => ['nullable','integer'],
            'note'              => ['nullable','string','max:500'],
        ]);

        $salonIncome->update([
            'amount'            => $data['amount'],
            'payment_method'    => $data['method'],
            'reference_number'  => $data['reference_number'] ?? null,
            'paid_at'           => $data['paid_at'] ?? null,
            'salon_account_id'  => $data['salon_account_id'] ?? null,
            'note'              => $data['note'] ?? null,
        ]);

        $invoice = Invoice::find($salonIncome->invoice_id);
        if ($invoice) $invoice->recalcPaymentStatus();

        return response()->json([
            'success'        => true,
            'deposit'        => $salonIncome->fresh(),
            'payment_status' => $invoice->payment_status ?? null,
            'sum_incomes'    => (float) ($invoice?->incomes()->sum('amount') ?? 0),
            'final_amount'   => (float) ($invoice?->final_amount ?? 0),
        ]);
    }

    public function destroy(SalonIncome $salonIncome)
    {
        $invoice = Invoice::find($salonIncome->invoice_id);
        $salonIncome->delete();
        if ($invoice) $invoice->recalcPaymentStatus();

        return response()->json([
            'success'        => true,
            'payment_status' => $invoice->payment_status ?? null,
            'sum_incomes'    => (float) ($invoice?->incomes()->sum('amount') ?? 0),
            'final_amount'   => (float) ($invoice?->final_amount ?? 0),
        ]);
    }
}
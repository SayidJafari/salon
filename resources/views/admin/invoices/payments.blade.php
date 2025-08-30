{{-- resources/views/admin/invoices/payments.blade.php --}}


<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

  {{-- کادر حقوق پرسنل --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <h5>حقوق پرسنل</h5>

      {{-- سوییچ نوع پرداخت فاکتور --}}
      <form method="POST" action="{{ route('admin.invoices.setPaymentType', $invoice) }}">
        @csrf
        <select name="payment_type" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="aggregate" @selected($invoice->payment_type === 'aggregate')>تجمیعی</option>
          <option value="split" @selected($invoice->payment_type === 'split')>تفکیکی</option>
        </select>
      </form>
    </div>

    <div class="card-body">
      <table class="table">
        <thead>
          <tr>
            <th>پرسنل</th>
            <th>خدمت</th>
            <th class="text-end">کمیسیون</th>
            <th>پرداخت</th>
            <th>وضعیت</th>
          </tr>
        </thead>
        <tbody>
          @foreach($invoice->items as $item)
          @php
          $commission = (float) $item->staff_commission_amount;
          $income = $item->staffIncome; // رابطه hasOne به جدول staff_incomes
          @endphp
          <tr>
            <td>{{ $item->staff->full_name ?? '-' }}</td>
            <td>{{ $item->serviceType->title ?? '-' }}</td>
            <td class="text-end">{{ number_format($commission) }}</td>
            <td>
              @if($invoice->payment_type === 'split')
              <form method="POST" action="{{ route('admin.invoices.payStaff', [$invoice, $item]) }}" class="d-flex gap-2">
                @csrf
                <select name="staffpaymentgateway_id" class="form-select form-select-sm" required>
                  <option value="">انتخاب درگاه/کارت</option>
                  @foreach(($item->staff?->paymentGateways ?? collect()) as $gw)
                  <option value="{{ $gw->id }}">{{ $gw->card_number ?? $gw->bank_account ?? $gw->pos_terminal }}</option>
                  @endforeach
                </select>
                <button class="btn btn-sm btn-success">پرداخت شد</button>
              </form>
              @else
              <span class="badge bg-warning">ثبت به‌عنوان طلب</span>
              @endif
            </td>
            <td>
              @if(optional($income)->commission_status === 'credit')
              <span class="badge bg-success">تسویه شد</span>
              @elseif(optional($income)->commission_status === 'debt')
              <span class="badge bg-secondary">در انتظار</span>
              @else
              <span class="badge bg-light text-dark">—</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      <div class="text-end mt-2">
        مجموع کمیسیون پرسنل: <b>{{ number_format($invoice->items->sum('staff_commission_amount')) }}</b>
      </div>
    </div>
  </div>

  {{-- کادر درآمد سالن --}}
  <div class="card">
    <div class="card-header">
      <h5>درآمد سالن</h5>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('admin.invoices.paySalon', $invoice) }}" class="row g-2">
        @csrf
        <div class="col-md-6">
          <label class="form-label">حساب سالن</label>
          <select name="account_id" class="form-select" required>
            @foreach($accounts as $acc)
            <option value="{{ $acc->id }}">{{ $acc->title }} ({{ $acc->bank_name }})</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">روش پرداخت</label>
          <select name="method" class="form-select" required>
            <option value="cash">نقدی</option>
            <option value="pos">POS</option>
            <option value="online">آنلاین</option>
            <option value="card_to_card">کارت‌به‌کارت</option>
            <option value="shaba">شبا</option>
            <option value="account_transfer">انتقال داخلی</option>
            <option value="cheque">چک</option>
            <option value="wallet">کیف پول</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">مبلغ</label>
          <input type="number" name="amount" class="form-control" required min="0" step="1000">
        </div>
        <div class="col-12">
          <button class="btn btn-primary">ثبت سهم سالن</button>
        </div>
      </form>

      <hr>
      <div class="small text-muted">
        سهم سالن (تقریبی): <b>{{ number_format($invoice->final_amount - $invoice->items->sum('staff_commission_amount')) }}</b>
      </div>

      {{-- تاریخچه پرداخت‌های فاکتور --}}
      <table class="table mt-3">
        <thead>
          <tr>
            <th>تاریخ</th>
            <th>روش</th>
            <th class="text-end">مبلغ</th>
          </tr>
        </thead>
        <tbody>
          @foreach($invoice->incomes as $dep)
          <tr>
            <td>{{ jdate($dep->paid_at)->format('Y/m/d H:i') }}</td>
            <td>{{ $dep->method }}</td>
            <td class="text-end">{{ number_format($dep->amount) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

</div>
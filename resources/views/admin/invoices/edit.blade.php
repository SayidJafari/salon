{{-- resources/views/admin/invoices/edit.blade.php --}}
@extends('layouts.app')
@section('content')

@php
use Morilog\Jalali\Jalalian;

$jdate = $invoice->registration_date
? Jalalian::fromDateTime($invoice->registration_date)->format('Y/m/d H:i')
: Jalalian::now()->format('Y/m/d H:i');

$itemsPreload = $invoice->items->map(function($it){
$srv = $it->serviceType ?? $it->service ?? null;
$staff = $it->staff ?? null;

$commissionType = !is_null($it->staff_commission_percent) ? 'percent'
: (!is_null($it->staff_commission_amount) ? 'amount' : null);
$commissionValue = !is_null($it->staff_commission_percent) ? (float)$it->staff_commission_percent
: (!is_null($it->staff_commission_amount) ? (float)$it->staff_commission_amount : null);

return [
'id' => (int)($it->id),
'type' => 'service',
'category_id' => (int)($srv->category_id ?? 0),
'service_id' => (int)($it->service_type_id ?? $it->service_id),
'service_title' => (string)($srv->title ?? ''),
'staff_id' => (int)($it->staff_id ?? 0),
'staff_title' => (string)($staff->full_name ?? ''),
'quantity' => (int)($it->quantity ?? 1),
'price' => (float)($it->price ?? 0),
'commission_type' => $commissionType,
'commission_value' => $commissionValue,
];
})->values();

$paidSalon = (int) $invoice->incomes->sum('amount');
$final = (int) ($invoice->final_amount ?? 0);
$dueSalon = max(0, $final - $paidSalon);

$discountPayload = $invoice->discount_amount > 0
? ['discount_type' => 'amount', 'value' => (int)$invoice->discount_amount, 'code' => optional($invoice->discountCode)->code]
: null;
@endphp

<div class="container-fluid px-2" style="max-width:1600px; margin:auto;">
  <h2 class="fw-bold mb-4">
    <i class="fa fa-file-invoice"></i> ویرایش فاکتور #{{ $invoice->id }}
  </h2>

  {{-- ===== فرم ویرایش ===== --}}
  <form id="invoiceEditForm"
    action="{{ route('admin.invoices.update', $invoice->id) }}"
    method="POST"
    class="modern-form shadow-sm"
    style="width:100%;max-width:100%;">
    @csrf
    @method('PUT')

    <input type="hidden" id="items-json" name="items" value="[]">
    <input type="hidden" id="saved-invoice-id" name="saved_invoice_id" value="{{ $invoice->id }}">

    <div class="row g-3 align-items-end">
      {{-- مشتری --}}
      <div class="col-md-3 col-12 position-relative">
        <label class="form-label">مشتری</label>
        <input type="text" id="customer_search" class="form-control form-control-lg"
          autocomplete="off" placeholder="نام، موبایل یا کدملی مشتری..."
          value="{{ $invoice->customer->full_name ?? '' }}">
        <input type="hidden" name="customer_id" id="customer_id" required
          value="{{ $invoice->customer_id }}">
        <div id="customer_search_suggestions" class="list-group position-absolute w-100" style="z-index: 100;"></div>
        <div id="customer_selected_badge" class="mt-2">
          @if($invoice->customer)
          <span class="badge bg-success">{{ $invoice->customer->full_name }} — {{ $invoice->customer->phone }}</span>
          @endif
        </div>
      </div>

      {{-- کیف پول (نمایشی) --}}
      <div class="col-md-3 col-12">
        <label class="form-label">مبلغ قابل برداشت از کیف پول:</label>
        <input type="number" name="wallet_payment" id="wallet-payment-input" readonly class="form-control" value="0">
      </div>

      {{-- تاریخ ثبت --}}
      <div class="col-md-3 col-12">
        <label class="form-label">تاریخ ثبت</label>
        <input type="text" name="registration_date" id="registration_date"
          class="form-control jdatetime"
          autocomplete="off" readonly
          style="background:#fff;cursor:pointer;"
          value="{{ $jdate }}">
      </div>

      {{-- نوع پرداخت --}}
      <div class="col-md-3 col-12">
        <label class="form-label">نوع پرداخت</label>
        <select name="payment_type" id="payment_type" class="form-control form-control-lg" required>
          <option value="aggregate" @selected($invoice->payment_type==='aggregate')>تجمیعی (کل مبلغ به حساب سالن)</option>
          <option value="split" @selected($invoice->payment_type==='split')>تفکیکی (پرداخت تفکیک شده بین پرسنل و سالن)</option>
        </select>
      </div>

      {{-- کد تخفیف --}}
      <div class="col-md-3 col-12">
        <label class="form-label">کد تخفیف (اختیاری)</label>
        <div class="input-group input-group-lg" style="border-radius: 12px; overflow: hidden;">
          <input type="text" id="discount-code-input" name="discount_code" class="form-control"
            value="{{ optional($invoice->discountCode)->code }}" placeholder="کد تخفیف">
          <button type="button" class="btn btn-primary" id="apply-discount-btn"
            style="min-width: 80px; font-size: 1rem; border-radius: 0 12px 12px 0;">اعمال</button>
        </div>
        <div id="discount-message" class="text-danger small mt-1"></div>
      </div>

      {{-- وضعیت انجام کار --}}
      <div class="col-md-3 col-12">
        <div class="border rounded-3 p-3 shadow-sm"
          style="background:#63E6BE; border-color:hsla(0, 0%, 0%, 1.00);">
          <label class="form-label mb-2">آیا کار مشتری به پایان رسیده است؟</label>
          <div class="form-check">
            <input type="checkbox" name="in_progress" class="form-check-input" id="in_progress" value="1"
              @checked(old('in_progress', $invoice->in_progress ?? false))>
            <label class="form-check-label" for="in_progress">در حال انجام / انجام شده</label>
          </div>
        </div>
      </div>
    </div>

    {{-- ===== خدمات ===== --}}
    <div class="row justify-content-center my-4">
      <div class="col-12 col-lg-10">
        <div class="card shadow-sm border-0 rounded-4 p-3 mb-3">
          <div class="row gx-2 gy-2 align-items-end form-row-unified">
            <div class="col-lg-2 col-md-4 col-12">
              <label class="form-label">دسته‌بندی خدمات</label>
              <select id="category-select" class="form-control form-control-sm">
                <option value="">انتخاب کنید...</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->title }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-lg-2 col-md-4 col-12">
              <label class="form-label">خدمت/محصول</label>
              <select id="service-select" class="form-control form-control-sm" disabled>
                <option value="">انتخاب کنید...</option>
              </select>
            </div>
            <div class="col-lg-2 col-md-4 col-12">
              <label class="form-label">پرسنل مجری</label>
              <select id="staff-select" class="form-control form-control-sm" disabled>
                <option value=""> انتخاب کنید...</option>
              </select>
            </div>
            <div class="col-lg-1 col-md-2 col-4">
              <label class="form-label">تعداد</label>
              <input type="number" min="1" value="1" id="item-qty" class="form-control form-control-sm text-center">
            </div>
            <div class="col-lg-2 col-md-3 col-8">
              <label class="form-label">قیمت</label>
              <input type="number" id="item-price" class="form-control form-control-sm text-center" readonly>
            </div>
            <div class="col-lg-1 col-md-2 col-4 d-flex align-items-end">
              <button type="button" id="add-item-btn"
                class="btn d-inline-flex align-items-center justify-content-center gap-2 px-5"
                style="background:#FF5EAD;color:#fff;font-size:.95rem;border:none;border-radius:999px;height:36px;line-height:1;white-space:nowrap;">
                <i class="fa fa-plus"></i>
                <span>افزودن خدمات</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== پکیج ===== --}}
      <div class="col-12 col-lg-10">
        <div class="card shadow-sm border-0 rounded-4 p-3 mb-2">
          <div class="row gx-2 gy-2 align-items-end">
            <div class="col-md-8">
              <label class="form-label">پکیج مورد نظر</label>
              <select id="package-select" class="form-control form-control-sm">
                <option value="">یک پکیج انتخاب کنید...</option>
                @foreach($packages as $pkg)
                <option value="{{ $pkg->id }}" data-price="{{ $pkg->price }}">{{ $pkg->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button type="button" id="add-package-btn"
                class="btn d-inline-flex align-items-center justify-content-center gap-2 px-5"
                style="background:#FF5EAD;color:#fff;font-size:.95rem;border:none;border-radius:999px;height:36px;line-height:1;white-space:nowrap;">
                <i class="fa fa-plus"></i>
                <span>افزودن پکیج</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- جدول آیتم‌ها --}}
    <div class="mt-4">
      <table class="table table-bordered" id="items-table" style="width:100%;min-width:900px;">
        <thead class="table-light">
          <tr>
            <th>خدمت/محصول</th>
            <th>پرسنل</th>
            <th>تعداد</th>
            <th>قیمت واحد</th>
            <th>کیمسیون-پرسنل</th>
            <th>جمع</th>
            <th>حذف</th>
          </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
          <tr>
            <td colspan="5" class="text-end fw-bold">جمع کل</td>
            <td id="total-amount" class="fw-bold">۰</td>
            <td></td>
          </tr>
          <tr id="discount-row" @if($invoice->discount_amount<=0) style="display:none;" @endif>
              <td colspan="5" class="text-end fw-bold text-success">تخفیف</td>
              <td id="discount-amount" class="fw-bold text-success">{{ number_format((int)$invoice->discount_amount) }}</td>
              <td></td>
          </tr>
          <tr id="final-row" @if($invoice->final_amount<=0) style="display:none;" @endif>
              <td colspan="5" class="text-end fw-bold text-primary">مبلغ نهایی</td>
              <td id="final-amount" class="fw-bold text-primary">{{ number_format((int)$invoice->final_amount) }}</td>
              <td></td>
          </tr>
          <tr id="referrer-row" style="display:none;">
            <td colspan="5" class="text-end fw-bold">
              کمیسیون معرف
              <span id="referrer-name" class="ms-2 text-muted"></span>
              <a href="#" id="referrer-toggle" class="ms-2 small">جزئیات</a>
            </td>
            <td id="referrer-amount" class="fw-bold">۰</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
      <div id="referrer-breakdown" class="mt-2" style="display:none;"></div>
    </div>

    {{-- CTA پایین جدول --}}
    <div class="row mt-4">
      <div class="col-12 col-lg-10 mx-auto">
        <div class="d-flex flex-column flex-md-row gap-3 align-items-stretch">
          <button type="submit" id="btn-save-edit"
            class="btn btn-primary btn-lg flex-grow-1 py-3"
            style="border-radius:11px; font-weight:700; font-size:1.15rem;">
            <i class="fa fa-save ms-1"></i>
            ذخیرهٔ تغییرات
          </button>

          <a href="{{ route('admin.invoices.index') }}"
            class="btn btn-outline-secondary btn-lg px-4"
            style="border-radius:11px; font-weight:700; font-size:1.55rem;">
            <i class="fa fa-arrow-right ms-1"></i> بازگشت
          </a>
        </div>
      </div>
    </div>
  </form>

  {{-- ===== بعد از ثبت ===== --}}
  <div id="after-save-box" style="display:block">
    <div class="d-flex align-items-center gap-2 mb-3">
      <span class="badge bg-success" id="status-invoice">
        {{ $invoice->invoice_status === 'final' ? 'نهایی شده' : 'ثبت موقت شده' }}
      </span>
      <span class="badge bg-info" id="status-payment-type">
        {{ $invoice->payment_type==='split' ? 'تفکیکی' : 'تجمیعی' }}
      </span>
      <span class="badge bg-secondary" id="status-payment">
        @php
        $ps = $invoice->payment_status;
        echo $ps==='paid' ? 'پرداخت شده' : ($ps==='partial' ? 'پرداخت جزئی' : 'پرداخت نشده');
        @endphp
      </span>
    </div>

    {{-- پنل پرداخت تجمیعی (بدون جدول پرداخت‌های سالن) --}}
    <div id="pay-aggregate-pane" class="card @if($invoice->payment_type!=='aggregate') d-none @endif mb-4">
      <div class="card-header">پرداخت سالن (تجمیعی)</div>
      <div class="card-body">
        {{-- اینجا بیلدر تجمیعی خودت می‌مونه (چک/حساب/…)، فقط جدول پرداخت‌ها را دیگر اینجا نگذار --}}
        <hr class="my-4">
        <div id="pb-summary" class="alert alert-info d-flex justify-content-between align-items-center">
          <div>
            مبلغ نهایی: <b id="pb-final">{{ number_format($final) }}</b>
            &nbsp;|&nbsp; پرداخت‌شده: <b id="pb-paid">{{ number_format($paidSalon) }}</b>
            &nbsp;|&nbsp; باقی‌مانده: <b id="pb-due">{{ number_format($dueSalon) }}</b>
          </div>
        </div>
      </div>
    </div>

    {{-- پنل پرداخت تفکیکی --}}
    <div id="pay-split-pane" class="card @if($invoice->payment_type!=='split') d-none @endif">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>پرداخت تفکیکی</span>
        <div class="small text-muted">
          <span class="me-3">وضعیت پرسنل: <b id="split-staff-due-badge">-</b></span>
          <span class="me-3">وضعیت سالن: <b id="split-salon-due-badge">-</b></span>
          <span>معرف: <b id="split-referrer-badge">-</b></span>
        </div>
      </div>
      <div class="card-body">
        {{-- کارت: سهم پرسنل (ثبت جدید توسط JS) --}}
        <div class="border rounded-3 p-3 mb-4">
          <h5 class="m-0 mb-3">سهم پرسنل</h5>
          <div class="table-responsive">
            <table class="table table-sm align-middle" id="split-staff-table">
              <thead>
                <tr>
                  <th>پرسنل</th>
                  <th>کمیسیون</th>
                  <th>پرداختی</th>
                  <th>باقیمانده</th>
                  <th>روش</th>
                  <th>درگاه/کارت</th>
                  <th>مبلغ جدید</th>
                  <th>تاریخ</th>
                  <th>مرجع</th>
                  <th>افزودن</th>
                </tr>
              </thead>
              <tbody><!-- JS پر می‌کند --></tbody>
            </table>
          </div>
          <div class="text-end mt-3">
            <button type="button" id="btn-staff-commit" class="btn btn-success">ثبت پرداخت‌های پرسنل</button>
          </div>
        </div>

        {{-- کارت: سهم سالن (ثبت جدید توسط JS) --}}
        <div class="border rounded-3 p-3 mb-2">
          <h5 class="m-0 mb-3">سهم سالن</h5>
          <div class="row g-3 align-items-end mb-2" id="split-salon-builder">
            <div class="col-md-3">
              <label class="form-label">روش پرداخت</label>
              <select id="split-salon-method" class="form-select">
                <option value="">انتخاب کنید...</option>
                <option value="cash">نقد</option>
                <option value="pos">POS</option>
                <option value="wallet">کیف پول</option>
                <option value="card_to_card">کارت به کارت</option>
                <option value="account_transfer">انتقال حساب</option>
                <option value="shaba">شبا</option>
                <option value="online">آنلاین</option>
                <option value="cheque">چک</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">مبلغ</label>
              <input type="number" id="split-salon-amount" class="form-control" min="0">
            </div>
            <div class="col-md-3">
              <label class="form-label">حساب سالن</label>
              <select id="split-salon-account" class="form-select">
                <option value="">انتخاب کنید…</option>
              </select>
              <div class="form-text">برای «کیف پول» اجباری نیست.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">شماره مرجع</label>
              <input type="text" id="split-salon-ref" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">تاریخ پرداخت</label>
              <input type="text" id="split-salon-paid-at" class="form-control jdatetime" placeholder="YYYY/MM/DD">
            </div>
            <div class="col-md-12 text-end">
              <button type="button" id="btn-save-salon" class="btn btn-primary">ثبت پرداخت‌های سالن</button>
            </div>
          </div>

          <div class="table-responsive mt-3" id="split-salon-list-wrap" style="display:none;">
            <table class="table table-sm align-middle" id="split-salon-list">
              <thead>
                <tr>
                  <th>مبلغ</th>
                  <th>روش</th>
                  <th>حساب سالن</th>
                  <th>شماره مرجع</th>
                  <th>تاریخ</th>
                  <th>حذف</th>
                </tr>
              </thead>
              <tbody><!-- JS پر می‌کند --></tbody>
            </table>
            <div class="d-flex justify-content-between align-items-center">
              <div>جمع پرداخت‌های سالن: <b id="split-salon-queued-sum">۰</b></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ====== پرداخت‌های سالن (ثبت‌شده) — همیشه نمایش بده ====== --}}
    <div class="card mt-4">
      <div class="card-header">پرداخت‌های سالن (ثبت‌شده)</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm" id="deposits-table">
            <thead>
              <tr>
                <th>مبلغ</th>
                <th>روش</th>
                <th>شماره مرجع</th>
                <th>تاریخ</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
              @foreach($invoice->incomes as $dep)
              <tr data-id="{{ $dep->id }}">
                <td>{{ number_format((int)$dep->amount) }}</td>
                <td>{{ $dep->payment_method }}</td>
                <td>{{ $dep->reference_number }}</td>
                <td>{{ $dep->paid_at ? \Morilog\Jalali\Jalalian::fromDateTime($dep->paid_at)->format('Y/m/d H:i') : '-' }}</td>
                <td>
                  <button type="button" class="btn btn-sm btn-primary btn-edit-deposit" data-id="{{ $dep->id }}">ویرایش</button>
                  <button type="button" class="btn btn-sm btn-danger btn-del-deposit" data-id="{{ $dep->id }}">حذف</button>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- پرداخت‌های پرسنل (ثبت‌شده) --}}
    <div class="card mt-4">
      <div class="card-header">پرداخت‌های پرسنل (ثبت‌شده)</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm" id="split-paid-table">
            <thead>
              <tr>
                <th>پرسنل</th>
                <th>مبلغ</th>
                <th>روش</th>
                <th>شماره سند</th>
                <th>تاریخ</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
              @php
              $staffPaid = $invoice->staffIncomes()->with('staff')->orderByDesc('id')->get();
              @endphp
              @forelse($staffPaid as $row)
              <tr data-id="{{ $row->id }}" data-staff="{{ $row->staff_id }}">
                <td>{{ $row->staff->full_name ?? '-' }}</td>
                <td>{{ number_format($row->amount) }}</td>
                <td>{{ $row->payment_method ?? '-' }}</td>
                <td>{{ $row->reference_number ?? '' }}</td>
                <td>{{ jdate($row->created_at)->format('Y/m/d H:i') }}</td>
                <td>
                  <button class="btn btn-sm btn-primary sp-edit" data-id="{{ $row->id }}">ویرایش</button>
                  <button class="btn btn-sm btn-danger sp-del" data-id="{{ $row->id }}">حذف</button>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="6" class="text-center">موردی ثبت نشده</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  {{-- پرلود داده‌ها برای اسکریپت‌ها --}}
  <div id="invoice-preload"
    data-items='@json($itemsPreload)'
    data-discount='@json($discountPayload)'
    data-total='{{ (int) $invoice->total_amount }}'
    data-final='{{ (int) $invoice->final_amount }}'
    data-paid='{{ (int) $invoice->incomes->sum("amount") }}'
    data-due='{{ max(0, (int)$invoice->final_amount - (int)$invoice->incomes->sum("amount")) }}'
    data-customer-id='{{ (int) $invoice->customer_id }}'>
  </div>

  <!-- Modal ویرایش پرداخت سالن -->
  <div class="modal fade" id="depositModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">ویرایش پرداخت سالن</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="dep-id">
          <div class="mb-2">
            <label class="form-label">مبلغ</label>
            <input type="number" id="dep-amount" class="form-control">
          </div>
          <div class="mb-2">
            <label class="form-label">روش</label>
            <select id="dep-method" class="form-select">
              <option value="cash">نقد</option>
              <option value="pos">POS</option>
              <option value="card_to_card">کارت به کارت</option>
              <option value="account_transfer">انتقال حساب</option>
              <option value="online">آنلاین</option>
              <option value="cheque">چک</option>
              <option value="wallet">کیف پول</option>
              <option value="shaba">شبا</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">شماره مرجع</label>
            <input type="text" id="dep-ref" class="form-control">
          </div>
          <div class="mb-2">
            <label class="form-label">تاریخ پرداخت</label>
            <input type="text" id="dep-paid-at" class="form-control jdatetime">
          </div>
          <div class="mb-2">
            <label class="form-label">حساب سالن (در صورت نیاز)</label>
            <select id="dep-account" class="form-select">
              <option value="">انتخاب کنید…</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">توضیح</label>
            <textarea id="dep-note" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
          <button class="btn btn-primary" id="dep-save">ذخیره</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal ویرایش پرداخت پرسنل -->
  <div class="modal fade" id="staffPayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">ویرایش پرداخت پرسنل</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="sp-id">
          <div class="mb-2"><label class="form-label">مبلغ</label>
            <input type="number" id="sp-amount" class="form-control">
          </div>
          <div class="mb-2"><label class="form-label">روش</label>
            <select id="sp-method" class="form-select">
              <option value="cash">نقد</option>
              <option value="pos">POS</option>
              <option value="card_to_card">کارت به کارت</option>
              <option value="account_transfer">انتقال حساب</option>
              <option value="online">آنلاین</option>
              <option value="cheque">چک</option>
              <option value="shaba">شبا</option>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">تاریخ</label>
            <input type="text" id="sp-paid-at" class="form-control jdatetime">
          </div>
          <div class="mb-2"><label class="form-label">درگاه/کارت</label>
            <select id="sp-gateway" class="form-select">
              <option value="">انتخاب کنید…</option>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">شماره سند</label>
            <input type="text" id="sp-ref" class="form-control">
          </div>
          <div class="mb-2"><label class="form-label">توضیح</label>
            <textarea id="sp-note" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
          <button class="btn btn-primary" id="sp-save">ذخیره</button>
        </div>
      </div>
    </div>
  </div>

  {{-- استایل‌ها (خلاصه) --}}
  <style>
    .status-chipbar {
      background: #fff;
      border: 1px solid #eee;
      border-radius: 12px;
      padding: 6px 10px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, .04)
    }

    .apply-discount-btn-custom {
      height: 48px;
      padding: 0 20px;
      font-size: 1rem;
      border-radius: 12px
    }

    @media (max-width:900px) {
      .apply-discount-btn-custom {
        width: 100% !important;
        margin-top: 6px
      }
    }

    .btn-pink {
      background: #FF5EAD !important;
      color: #fff !important;
      border: none !important;
      font-weight: 600;
      font-size: 1.08rem;
      border-radius: 12px;
      transition: background .2s
    }

    .btn-pink:hover,
    .btn-pink:focus {
      background: #e03b8c !important;
      color: #fff !important
    }

    .form-control,
    .form-select,
    .form-control-lg {
      min-height: 48px !important;
      font-size: 1.06rem !important;
      border-radius: 12px !important
    }

    .form-label {
      font-weight: 500
    }

    .btn-lg {
      min-width: 130px
    }

    @media (max-width:900px) {

      .btn-lg,
      .btn-pink {
        width: 100% !important;
        min-width: 0 !important
      }
    }
  </style>

  <script>
    window.csrfToken = '{{ csrf_token() }}';
  </script>

  {{-- اسکریپت‌های پرلود و جمع‌بندی --}}
  <script>
    (function() {
      const el = document.getElementById('invoice-preload');
      const get = (k) => (el && el.dataset[k]) ? el.dataset[k] : null;

      // پرلود داده‌ها
      window.items = get('items') ? JSON.parse(get('items')) : [];
      window.currentDiscount = get('discount') ? JSON.parse(get('discount')) : null;
      window._computedTotal = parseInt(get('total') || '0', 10);

      // مقداردهی چند فیلد
      const cid = document.getElementById('customer_id');
      if (cid && get('customerId')) cid.value = get('customerId');

      // اگر تابع رندر آیتم‌ها (از create) موجود است
      if (typeof renderItems === 'function') renderItems();

      // خلاصه تجمیعی
      const nf = new Intl.NumberFormat('fa-IR');
      const setText = (id, val) => {
        const x = document.getElementById(id);
        if (x) x.innerText = nf.format(parseInt(val || '0', 10));
      };
      setText('pb-final', get('final'));
      setText('pb-paid', get('paid'));
      setText('pb-due', get('due'));
    })();
  </script>

  <script>
    // قرار دادن آیتم‌ها قبل از ارسال فرم
    document.getElementById('invoiceEditForm').addEventListener('submit', function() {
      const input = document.getElementById('items-json');
      try {
        input.value = JSON.stringify(window.items || []);
      } catch (e) {}
    });

    // نمایش/مخفی‌سازی فیلدهای چک مشابه create
    document.addEventListener('change', function(e) {
      if (e.target && e.target.id === 'pb-method') {
        var isCheque = e.target.value === 'cheque';
        var box = document.getElementById('cheque-fields');
        if (box) box.style.display = isCheque ? '' : 'none';
        var acc = document.getElementById('pb-account');
        if (acc) acc.disabled = (e.target.value === 'wallet');
      }
      if (e.target && e.target.id === 'split-salon-method') {
        var isCheque2 = e.target.value === 'cheque';
        var box2 = document.getElementById('split-cheque-fields');
        if (box2) box2.style.display = isCheque2 ? '' : 'none';
        var acc2 = document.getElementById('split-salon-account');
        if (acc2) acc2.disabled = (e.target.value === 'wallet');
      }
    });

    // مقداردهی اولیه برخی فیلدها
    (function() {
      const reg = document.getElementById('registration_date');
      if (reg && !reg.value) reg.value = '{{ $jdate }}';

      const pay = document.getElementById('payment_type');
      if (pay) pay.value = '{{ $invoice->payment_type }}';

      const dc = document.getElementById('discount-code-input');
      if (dc) dc.value = '{{ optional($invoice->discountCode)->code }}';

      const cid = document.getElementById('customer_id');
      if (cid) cid.value = '{{ $invoice->customer_id }}';

      const f = document.getElementById('pb-final');
      const p = document.getElementById('pb-paid');
      const d = document.getElementById('pb-due');
      if (f) f.innerText = '{{ number_format($final) }}';
      if (p) p.innerText = '{{ number_format($paidSalon) }}';
      if (d) d.innerText = '{{ number_format($dueSalon) }}';
    })();
  </script>

  @endsection
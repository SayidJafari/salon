{{-- resources/views/admin/invoices/create.blade.php --}}
@extends('layouts.app')
@section('content')

<div class="container-fluid px-2" style="max-width:1600px; margin:auto;">
  <h2 class="fw-bold mb-4">
    <i class="fa fa-file-invoice"></i> صدور فاکتور جدید
  </h2>

  <form id="invoiceCreateForm" action="{{ route('admin.invoices.store') }}" method="POST" class="modern-form shadow-sm" style="width:100%;max-width:100%;">
    @csrf
    <input type="hidden" id="items-json" name="items" value="[]">

    {{-- شناسه فاکتور ذخیره‌شده بعد از ثبت موقت (برای ثبت پرداخت‌ها) --}}
    <input type="hidden" id="saved-invoice-id" name="saved_invoice_id" value="">

    <div class="row g-3 align-items-end">
      {{-- مشتری --}}
      <div class="col-md-3 col-12 position-relative">
        <label class="form-label">مشتری</label>
        <input type="text" id="customer_search" class="form-control form-control-lg" autocomplete="off" placeholder="نام، موبایل یا کدملی مشتری...">
        <input type="hidden" name="customer_id" id="customer_id" required>
        <div id="customer_search_suggestions" class="list-group position-absolute w-100" style="z-index: 100;"></div>
        <div id="customer_selected_badge" class="mt-2"></div>
      </div>

      <div class="col-md-3 col-12">
        <label class="form-label">مبلغ قابل برداشت از کیف پول:</label>
        <input type="number" name="wallet_payment" id="wallet-payment-input" readonly class="form-control" value="0">
      </div>



      <input type="text" name="registration_date" id="registration_date"
        class="form-control jdatetime"
        autocomplete="off" readonly
        style="background:#fff;cursor:pointer;"
        value="{{ old('registration_date', \Morilog\Jalali\Jalalian::now()->format('Y/m/d H:i')) }}">




      {{-- نوع پرداخت --}}
      <div class="col-md-3 col-12">
        <label class="form-label">نوع پرداخت</label>
        <select name="payment_type" id="payment_type" class="form-control form-control-lg" required>
          <option value="aggregate" selected>تجمیعی (کل مبلغ به حساب سالن)</option>
          <option value="split">تفکیکی (پرداخت تفکیک شده بین پرسنل و سالن)</option>
        </select>
      </div>

      {{-- کد تخفیف --}}
      <div class="col-md-3 col-12">
        <label class="form-label">کد تخفیف (اختیاری)</label>
        <div class="input-group input-group-lg" style="border-radius: 12px; overflow: hidden;">
          <input type="text" id="discount-code-input" name="discount_code" class="form-control" placeholder="کد تخفیف">
          <button type="button"
            class="btn btn-primary"
            id="apply-discount-btn"
            style="min-width: 80px; font-size: 1rem; border-radius: 0 12px 12px 0;">
            اعمال
          </button>
        </div>
        <div id="discount-message" class="text-danger small mt-1"></div>
      </div>

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

    {{-- ردیف کارت‌های خدمات و پکیج --}}
    <div class="row justify-content-center my-4">
      {{-- خدمات تکی --}}
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

      {{-- پکیج --}}
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
      {{-- (توجه) باکس پرداخت به پایین صفحه منتقل شده است --}}
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
          <tr id="discount-row" style="display:none;">
            <td colspan="5" class="text-end fw-bold text-success">تخفیف</td>
            <td id="discount-amount" class="fw-bold text-success">۰</td>
            <td></td>
          </tr>
          <tr id="final-row" style="display:none;">
            <td colspan="5" class="text-end fw-bold text-primary">مبلغ نهایی</td>
            <td id="final-amount" class="fw-bold text-primary">۰</td>
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

    {{-- CTA: دکمه‌های اصلی فاکتور (زیر جدول) --}}
    <div class="row mt-4">
      <div class="col-12 col-lg-10 mx-auto">
        <div class="d-flex flex-column flex-md-row gap-3 align-items-stretch">
          @unless(isset($invoice) && $invoice->id)
          <button type="button" id="btn-draft"
            class="btn btn-primary btn-lg flex-grow-1 py-3"
            style="border-radius:11px; font-weight:700; font-size:1.15rem;">
            <i class="fa fa-save ms-1"></i>
            ثبت موقت (پس از تأیید پرسنل مجری خدمات، روی «ثبت موقت» کلیک کنید.)
          </button>
          @endunless

          <a href="{{ route('admin.invoices.index') }}"
            class="btn btn-outline-secondary btn-lg px-4"
            style="border-radius:11px; font-weight:700; font-size:1.55rem;">
            <i class="fa fa-arrow-right ms-1"></i> انصراف
          </a>
        </div>
      </div>
    </div>

    {{-- جای قدیم پرداخت در همین فایل نبود --}}
  </form>

  <!-- ===== بعد از ثبت موقت نمایش بده ===== -->
  <div id="after-save-box" style="display:none">
    <div class="d-flex align-items-center gap-2 mb-3">
      <span class="badge bg-success" id="status-invoice">ثبت موقت شد</span>
      <span class="badge bg-info" id="status-payment-type">تجمیعی</span>
      <span class="badge bg-secondary" id="status-payment">پرداخت نشده</span>
    </div>

    <!-- ===== فرم پرداخت «تجمیعی» (سهم سالن) ===== -->
    <div id="pay-aggregate-pane" class="card d-none mb-4">
      <div class="card-header">پرداخت سالن (تجمیعی)</div>
      <div class="card-body">


        {{-- جزئیات چک - فقط وقتی روش = cheque نمایش داده شود --}}
        <div id="cheque-fields" class="col-12 mt-3" style="display:none;">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">شماره چک</label>
              <input type="text" id="cheque-serial" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">بانک</label>
              <input type="text" id="cheque-bank" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">شماره حساب</label>
              <input type="text" id="cheque-account" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">تاریخ سررسید</label>
              <input type="text" id="cheque-due" class="form-control datepicker" placeholder="YYYY/MM/DD">
            </div>
            <div class="col-md-6">
              <label class="form-label">نام/کد صادرکننده</label>
              <input type="text" id="cheque-issuer" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">توضیح گیرنده</label>
              <input type="text" id="cheque-note" class="form-control">
            </div>
          </div>
        </div>


        {{-- ===== پرداخت ترکیبی: افزودن چند روش پرداخت و ثبت یکجا (Aggregate) ===== --}}
        <hr class="my-4">

        <div id="pb-summary" class="alert alert-info d-flex justify-content-between align-items-center">
          <div>
            مبلغ نهایی: <b id="pb-final">۰</b>
            &nbsp;|&nbsp; پرداخت‌شده: <b id="pb-paid">۰</b>
            &nbsp;|&nbsp; باقی‌مانده: <b id="pb-due">۰</b>
          </div>
        </div>

        <div id="pb-builder" class="border rounded-3 p-3">
          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label">روش پرداخت (جدید)</label>
              <select id="pb-method" class="form-select">
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
              <label class="form-label">مبلغ این روش</label>
              <input type="number" id="pb-amount" class="form-control" placeholder="مثلاً 300000">
              <div class="form-text">پیش‌فرض: باقی‌ماندهٔ بدهی</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">حساب بانکی سالن</label>
              <select id="pb-account" class="form-select">
                @foreach($salonAccounts ?? [] as $acc)
                <option value="{{ $acc->id }}">{{ $acc->title }} — {{ $acc->bank_name }} ({{ $acc->account_number }})</option>
                @endforeach
              </select>
              <div class="form-text">برای «کیف پول» اجباری نیست.</div>
            </div>

            <div class="col-md-3">
              <label class="form-label">شماره مرجع</label>
              <input type="text" id="pb-ref" class="form-control" placeholder="اختیاری">
            </div>

<input type="hidden" id="pb-paid-at">


            <div class="col-md-6">
              <label class="form-label">توضیح</label>
              <input type="text" id="pb-note" class="form-control" placeholder="اختیاری">
            </div>

            <div class="col-12">
              <button type="button" id="pb-add" class="btn btn-outline-success">
                + افزودن روش پرداخت
              </button>
            </div>
          </div>

          {{-- توجه: برای روش "چک"، از همین فیلدهای چک بالای فرم استفاده می‌کنیم --}}
        </div>

        <div class="table-responsive mt-3">
          <table class="table table-sm align-middle" id="pb-list">
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
            <tbody></tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-2">
          <div class="fw-bold">باقی‌مانده: <span id="pb-remaining">۰</span></div>
          <button type="button" id="pb-commit" class="btn btn-primary">
            ثبت پرداخت‌های انتخاب‌شده
          </button>
        </div>
        {{-- ===== پایان پرداخت ترکیبی ===== --}}

        <div class="table-responsive mt-4">
          <table class="table table-sm" id="deposits-table">
            <thead>
              <tr>
                <th>مبلغ</th>
                <th>روش</th>
                <th>شماره مرجع</th>
                <th>تاریخ</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===== فرم پرداخت «تفکیکی» (پرسنل + سهم سالن) ===== -->
    <!-- ===== فرم پرداخت «تفکیکی» (پرسنل + سهم سالن + معرف) ===== -->
    <div id="pay-split-pane" class="card d-none">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>پرداخت تفکیکی</span>
        <div class="small text-muted">
          <span class="me-3">وضعیت پرسنل: <b id="split-staff-due-badge">-</b></span>
          <span class="me-3">وضعیت سالن: <b id="split-salon-due-badge">-</b></span>
          <span>معرف: <b id="split-referrer-badge">-</b></span>
        </div>
      </div>

      <div class="card-body">

        <!-- ========== کارت A: پرداخت سهم پرسنل ========== -->
        <div class="border rounded-3 p-3 mb-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0">سهم پرسنل</h5>
            <div class="status-chipbar">
              مجموع کمیسیون: <b id="staff-total-commission">۰</b> |
              پرداخت‌شده: <b id="staff-total-paid">۰</b> |
              باقی‌مانده: <b id="staff-total-due" class="text-danger">۰</b>
            </div>
          </div>

        <div class="table-responsive">
  <table class="table table-sm align-middle" id="split-staff-table" style="width:100%; table-layout:fixed;">
    <thead class="table-light">
      <tr>
        <th style="width:140px">پرسنل</th>
        <th style="width:180px">خدمات/آیتم‌ها</th>
        <th style="width:110px">کمیسیون</th>
        <th style="width:110px">نوع پرداخت</th>
        <th style="width:110px">تاریخ</th>
        <th style="width:280px">شماره سند</th>
        <th style="width:180px">شماره حساب</th>
        <th style="width:140px">اعمال</th>
        <th style="width:140px">وضعیت</th>
        <!--<th style="width:90px">000</th>-->
      </tr>
    </thead>
    <tbody id="split-staff-tbody">
      <!-- با JS پر می‌شود -->
    </tbody>
  </table>
</div>


          <!-- لیست پرداخت‌های queued برای هر پرسنل (نمایش تجمیعی) -->
          <div class="mt-3" id="staff-queued-wrap" style="display:none;">
            <h6 class="mb-2">پرداخت‌های انتخاب‌شدهٔ پرسنل</h6>
            <div class="table-responsive">
              <table class="table table-sm" id="staff-queued-table">
                <thead>
                  <tr>
                    <th>پرسنل</th>
                    <th>روش</th>
                    <th>مبلغ</th>
                    <th>درگاه/کارت</th>
                    <th>شماره مرجع</th>
                    <th>تاریخ</th>
                    <th>حذف</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <div>جمع پرداخت‌های پرسنل: <b id="staff-queued-sum">۰</b></div>
              <button type="button" id="btn-staff-commit" class="btn btn-success">
                ثبت پرداخت‌های پرسنل
              </button>
            </div>
          </div>
        </div>

        <!-- ========== کارت B: پرداخت سهم سالن ========== -->
        <div class="border rounded-3 p-3 mb-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0">سهم سالن</h5>
            <div class="status-chipbar">
              مبلغ نهایی: <b id="salon-final">۰</b> |
              کمیسیون پرسنل: <b id="salon-staff-comm">۰</b> |
              سهم معرف: <b id="salon-referrer">۰</b> |
              <span class="ms-2">سهم سالن: <b id="salon-share-total">۰</b></span> |
              پرداخت‌شده: <b id="salon-share-paid">۰</b> |
              باقی‌مانده: <b id="salon-share-due" class="text-danger">۰</b>
            </div>
          </div>

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
              <input type="number" id="split-salon-amount" class="form-control" placeholder="مثلاً 300000">
              <div class="form-text">پیش‌فرض: باقی‌ماندهٔ سهم سالن</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">حساب بانکی سالن</label>
              <select id="split-salon-account" class="form-select">
                @foreach($salonAccounts ?? [] as $acc)
                <option value="{{ $acc->id }}">{{ $acc->title }} — {{ $acc->bank_name }} ({{ $acc->account_number }})</option>
                @endforeach
              </select>
              <div class="form-text">برای «کیف پول» اجباری نیست.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">شماره مرجع</label>
              <input type="text" id="split-salon-ref" class="form-control" placeholder="اختیاری">
            </div>
<input type="hidden" id="split-salon-paid-at">

            <div class="col-md-6">
              <label class="form-label">توضیح</label>
              <input type="text" id="split-salon-note" class="form-control" placeholder="اختیاری">
            </div>
            <div class="col-12">
              <button type="button" id="btn-salon-add" class="btn btn-outline-success">+ افزودن به لیست</button>
            </div>
          </div>

          <!-- فیلدهای چک (فقط اگر روش = cheque) -->
          <div id="split-cheque-fields" class="col-12 mt-2" style="display:none;">
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">شماره چک</label>
                <input type="text" id="split-cheque-serial" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">بانک</label>
                <input type="text" id="split-cheque-bank" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">شماره حساب</label>
                <input type="text" id="split-cheque-account" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">تاریخ سررسید</label>
                <input type="text" id="split-cheque-due" class="form-control datepicker" placeholder="YYYY/MM/DD">
              </div>
              <div class="col-md-6">
                <label class="form-label">نام/کد صادرکننده</label>
                <input type="text" id="split-cheque-issuer" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">توضیح گیرنده</label>
                <input type="text" id="split-cheque-note" class="form-control">
              </div>
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
              <tbody></tbody>
            </table>
            <div class="d-flex justify-content-between align-items-center">
              <div>جمع پرداخت‌های سالن: <b id="split-salon-queued-sum">۰</b></div>
              <button type="button" id="btn-save-salon" class="btn btn-primary">ثبت پرداخت‌های سهم سالن</button>
            </div>
          </div>
        </div>

        <!-- ========== کارت C: سهم معرف (نمایشی) ========== -->
        <div class="border rounded-3 p-3">
          <h5 class="m-0 mb-2">سهم معرف</h5>
          <div class="small text-muted">
            معرف: <b id="referrer-name-box">-</b> |
            مبلغ: <b id="referrer-amount-box">۰</b> |
            وضعیت واریز کیف پول: <b id="referrer-status-box">در انتظار تسویه کامل فاکتور</b>
          </div>
        </div>

        <!-- ========== نوار نهایی‌سازی ========== -->
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="text-muted small">
            زمانی فعال می‌شود که هم «باقی‌ماندهٔ پرسنل» و هم «باقی‌ماندهٔ سالن» صفر باشد.
          </div>
          <button type="button" id="btn-finalize-split" class="btn btn-dark" disabled>نهایی‌سازی پرداخت تفکیکی</button>
        </div>

      </div>
    </div>

  </div> {{-- after-save-box --}}
</div> {{-- container-fluid --}}


<style>

#split-staff-table th:nth-child(5),
#split-staff-table td:nth-child(5) {
  display: none;
}

#split-staff-table th,
#split-staff-table td {
  text-align: center;
  vertical-align: middle;
}

#split-staff-table input,
#split-staff-table select,
#split-staff-table button {
  height: 38px;
  font-size: 0.95rem;
  border-radius: 8px;
}


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

<script>
  // این صفحه همیشه با state خالی شروع شود
  window.items = [];
  window.currentDiscount = null;
  window._computedTotal = 0;

  // نمایش/عدم نمایش فیلدهای چک بر اساس روش پرداخت سالن (aggregate)
  document.addEventListener('change', function(e) {
    // Aggregate (سهم سالن بعد از ثبت موقت)
    if (e.target && e.target.id === 'pb-method') {
      var isCheque = e.target.value === 'cheque';
      var box = document.getElementById('cheque-fields');
      if (box) box.style.display = isCheque ? '' : 'none';

      // اجباری نبودن حساب برای کیف‌پول
      var acc = document.getElementById('pb-account');
      if (acc) acc.disabled = (e.target.value === 'wallet');
    }

    // Split (سهم سالنِ تفکیکی)
    if (e.target && e.target.id === 'split-salon-method') {
      var isCheque2 = e.target.value === 'cheque';
      var box2 = document.getElementById('split-cheque-fields');
      if (box2) box2.style.display = isCheque2 ? '' : 'none';

      var acc2 = document.getElementById('split-salon-account');
      if (acc2) acc2.disabled = (e.target.value === 'wallet');
    }


  });
</script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    function syncPaidAtFromRegistration() {
      var regEl = document.getElementById('registration_date');
      var regVal = regEl ? regEl.value : '';
      var agg = document.getElementById('pb-paid-at');
      var split = document.getElementById('split-salon-paid-at');
      if (agg)   agg.value = regVal;
      if (split) split.value = regVal;
    }
    // بار اول
    syncPaidAtFromRegistration();
    // اگر کاربر تاریخ فاکتور را عوض کرد (با همان جداول جلالی)
    document.getElementById('registration_date')?.addEventListener('change', syncPaidAtFromRegistration);
  });
</script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var regEl = document.getElementById('registration_date');
    var tbody = document.getElementById('split-staff-tbody');

    function syncStaffPaidAt() {
      if (!tbody) return;
      var regVal = regEl ? regEl.value : '';

      // برای هر ردیف پرسنل، ستون 5 (تاریخ) را پیدا کن و ورودی‌هایش را با تاریخ فاکتور پر کن
      Array.from(tbody.querySelectorAll('tr')).forEach(function (tr) {
        var td = tr.children[4]; // ستون پنجم (index = 4)
        if (!td) return;

        td.querySelectorAll('input, select, textarea').forEach(function (inp) {
          try {
            inp.value = regVal;
            // ظاهر دیت‌پیکر را هم حذف کنیم تا چیزی نبیند
            if (inp.type && inp.type !== 'hidden') {
              inp.type = 'hidden';
            }
          } catch (e) {}
        });
      });
    }

    // بارِ اول
    syncStaffPaidAt();

    // اگر تاریخ فاکتور عوض شد، دوباره سنک کن
    regEl?.addEventListener('change', syncStaffPaidAt);

    // اگر JS ردیف‌ها را اضافه/حذف می‌کند، با MutationObserver هر تغییر را سنک کنیم
    if (tbody) {
      var obs = new MutationObserver(syncStaffPaidAt);
      obs.observe(tbody, { childList: true, subtree: true });
    }

    // قبل از ثبت پرداخت‌های پرسنل هم یک‌بار دیگر سنک شود
    document.getElementById('btn-staff-commit')?.addEventListener('click', syncStaffPaidAt);
  });
</script>

@endsection
@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">

<div class="container">

  {{-- هدر صفحه + دکمه --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="fw-bold mb-0">
      <i class="fa fa-file-invoice"></i> فاکتورها
    </h2>
    <a href="{{ route('admin.invoices.create') }}" class="btn btn-success">
      <i class="fa fa-plus"></i> فاکتور جدید
    </a>
  </div>

  {{-- فیلترها --}}
  <div class="card shadow-sm rounded-4 p-3 mb-3">
    <form id="invoice-filter-form" class="row g-2 align-items-end" method="get" action="{{ route('admin.invoices.index') }}">
      <div class="col-md-3">
        <label class="form-label"><i class="fa fa-user"></i> مشتری</label>
        <input name="customer" id="flt-customer" class="form-control" placeholder="نام/موبایل" value="{{ request('customer') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label"><i class="fa fa-calendar"></i> از تاریخ</label>
        <input name="from" id="flt-from" class="form-control datepicker" placeholder="YYYY/MM/DD" value="{{ request('from') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label"><i class="fa fa-calendar"></i> تا تاریخ</label>
        <input name="to" id="flt-to" class="form-control datepicker" placeholder="YYYY/MM/DD" value="{{ request('to') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label"><i class="fa fa-wallet"></i> وضعیت پرداخت</label>
        <select name="payment_status" id="flt-status" class="form-control">
          <option value="">همه</option>
          @foreach(['unpaid'=>'پرداخت‌نشده','partial'=>'نیمه‌پرداخت','paid'=>'پرداخت‌شده'] as $k=>$t)
          <option value="{{ $k }}" @selected(request('payment_status')===$k)>{{ $t }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-primary flex-grow-1"><i class="fa fa-search"></i> جستجو</button>
        <button type="button" class="btn btn-light" id="btn-clear-filters"><i class="fa fa-times"></i></button>
      </div>
    </form>
  </div>

  {{-- ابزارهای سریع --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    <div class="input-group" style="max-width:360px">
      <span class="input-group-text"><i class="fa fa-search"></i></span>
      <input id="live-search" class="form-control" placeholder="جستجوی سریع در نتایج همین صفحه...">
    </div>
    <button class="btn btn-outline-secondary" id="btn-print"><i class="fa fa-print"></i> چاپ</button>
    <button class="btn btn-outline-secondary" id="btn-export"><i class="fa fa-file-excel"></i> خروجی Excel</button>
  </div>

  @if($invoices->isEmpty())
  <div class="alert alert-warning">هیچ فاکتوری ثبت نشده است.</div>
  @else
  <div class="table-responsive shadow rounded-4 bg-white staff-modern-table">
    <table class="table table-bordered table-hover align-middle modern-table" id="invoices-table">
      <thead class="table-light">
        <tr>
          <th style="width:40px"><input type="checkbox" id="chk-all"></th>
          <th data-sort="int">#</th>
          <th data-sort="text">مشتری</th>
          <th data-sort="text">وضعیت‌ها</th>
          <th data-sort="num">نهایی (ت)</th>
          <th data-sort="num">پرداخت‌شده (ت)</th>
          <th data-sort="num">باقی‌مانده (ت)</th>
          <th data-sort="date">تاریخ</th>
          <th style="width:90px">اقدامات</th>
        </tr>
      </thead>
      <tbody>
        @foreach($invoices as $inv)
@php
$sumDeposits = \DB::table('salon_incomes')->where('invoice_id',$inv->id)->where('status','posted')->sum('amount');
$paid = (float)($inv->paid_amount ?? 0) + (float)($inv->deposits_sum ?? 0);
$due = max(0, (float)$inv->final_amount - $paid);
$jdate = \Morilog\Jalali\Jalalian::fromDateTime($inv->registration_date)->format('Y/m/d H:i');
$name = $inv->customer->full_name ?? '---';
$phone = $inv->customer->phone ?? '';
@endphp

@php
// جمع قطعی‌های سالن که از Controller آمده است
$sumSalon = (float)($inv->deposits_sum ?? 0);

// جمع پرداخت‌های پرسنل (فقط رکوردهای اعتباری)، از Controller
$sumStaff = (float)($inv->staff_paid_sum ?? 0);

// برای سازگاری با رکوردهای قدیمی
$legacy = (float)($inv->paid_amount ?? 0);

// مبلغ پرداخت‌شده‌ی واقعی = سالن + پرسنل (+ قدیمی)
$paid = $sumSalon + $sumStaff + $legacy;

// باقی‌مانده
$due = max(0, (float)$inv->final_amount - $paid);

$jdate = \Morilog\Jalali\Jalalian::fromDateTime($inv->registration_date)->format('Y/m/d H:i');
$name = $inv->customer->full_name ?? '---';
$phone = $inv->customer->phone ?? '';
@endphp


        <tr
          data-search="{{ $inv->id }} {{ $name }} {{ $phone }} {{ $inv->payment_status }} {{ $inv->payment_type }}"
          data-id="{{ $inv->id }}"
          data-final="{{ (float)$inv->final_amount }}"
          data-paid="{{ $paid }}"
          data-due="{{ $due }}"
          data-date="{{ $jdate }}">
          <td><input type="checkbox" class="row-check"></td>
          <td class="fw-bold text-muted">#{{ $inv->id }}</td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <span class="avatar-circle">{{ mb_substr($name,0,1) }}</span>
              <div>
                <div>{{ $name }}</div>
                @if($phone)<div class="small text-muted">{{ $phone }}</div>@endif
              </div>
            </div>
          </td>
          <td>
            <span class="badge bg-info text-white" title="نوع پرداخت">
              {{ $inv->payment_type === 'split' ? 'تفکیکی' : 'تجمیعی' }}
            </span>
            @if($inv->payment_status === 'paid')
            <span class="badge bg-success">پرداخت‌شده</span>
            @elseif($inv->payment_status === 'partial')
            <span class="badge bg-warning text-dark">نیمه‌پرداخت</span>
            @else
            <span class="badge bg-danger">پرداخت‌نشده</span>
            @endif
            @if($inv->invoice_status === 'final')
            <span class="badge bg-secondary">نهایی</span>
            @else
            <span class="badge bg-light text-dark">پیش‌نویس</span>
            @endif
          </td>
          <td>{{ number_format($inv->final_amount) }}</td>
          <td>{{ number_format($paid) }}</td>
          <td class="{{ $due>0 ? 'text-danger' : 'text-success' }}">{{ number_format($due) }}</td>
          <td>{{ $jdate }}</td>
          <td class="text-center">
            <div class="btn-group">

            {{-- دکمه ویرایش --}}
<a href="{{ route('admin.invoices.edit', $inv->id) }}"
   class="btn btn-sm btn-outline-primary" title="ویرایش">
  <i class="fa fa-edit"></i>
</a>


              <a href="{{ route('admin.invoices.show',$inv->id) }}" class="btn btn-sm btn-outline-primary" title="مشاهده">
                <i class="fa fa-eye"></i>
              </a>

              @if($inv->payment_type === 'split' && $inv->payment_status !== 'paid')
              <button type="button" class="btn btn-sm btn-outline-success btn-quick-settle" data-id="{{ $inv->id }}" title="تسویه سریع">
                <i class="fa fa-bolt"></i>
              </button>
              @endif

              {{-- حذف معمولی: فقط پیش‌نویس --}}
              @if($inv->invoice_status === 'draft')
              <button type="button" class="btn btn-sm btn-outline-danger btn-delete"
                data-url="{{ route('admin.invoices.destroy',$inv->id) }}" title="حذف">
                <i class="fa fa-trash"></i>
              </button>
              @else
              {{-- حذف دائمی: فقط برای سوپرادمین --}}
              @if(auth('admin')->user()?->is_superadmin)
              <button type="button" class="btn btn-sm btn-outline-danger btn-delete"
                data-url="{{ route('admin.invoices.force',$inv->id) }}"
                data-force="1" title="حذف دائمی (ثبت‌شده)">
                <i class="fa fa-trash"></i>
              </button>
              @endif
              @endif
            </div>

          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- کارت جمع‌ها --}}
  <div class="row g-3 mt-3" id="totals-row">
    <div class="col-md-4">
      <div class="card shadow-sm rounded-4 p-3">
        <div class="small text-muted">جمع نهایی</div>
        <div class="h5 mb-0" id="ttl-final">0</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm rounded-4 p-3">
        <div class="small text-muted">جمع پرداخت‌شده</div>
        <div class="h5 mb-0" id="ttl-paid">0</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm rounded-4 p-3">
        <div class="small text-muted">جمع باقیمانده</div>
        <div class="h5 mb-0" id="ttl-due">0</div>
      </div>
    </div>
  </div>

  {{-- صفحه‌بندی --}}
  <div class="mt-3">
    {{ $invoices->withQueryString()->links() }}
  </div>
  @endif
</div>

{{-- مودال تسویه سریع --}}
<div class="modal fade" id="quickSettleModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="fa fa-bolt"></i> تسویه سریع آیتم‌های تفکیکی</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="qs-body">در حال بارگذاری...</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
        <button class="btn btn-success" id="qs-confirm">ثبت پرداخت</button>
      </div>
    </div>
  </div>
</div>


@endsection
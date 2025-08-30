<!--
    فایل: resources/views/admin/manage_customer/create.blade.php
-->

@extends('layouts.app')

@section('content')

<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">


<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" style="color:#444">
            <i class="fa fa-user-plus"></i> ثبت نام مشتری جدید
        </h2>
        <!--
        <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary customers-back-btn">
            <i class="fa fa-arrow-right"></i> بازگشت به لیست
        </a>
        -->
    </div>

    @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form id="customerCreateForm" action="{{ route('admin.customers.store') }}" method="POST" class="modern-form shadow-sm">
        @csrf
        <div id="customer-create-message"></div>


        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-user"></i> نام و نام خانوادگی
            </label>
            <input type="text" name="full_name" class="form-control"
                required placeholder="نام کامل مشتری"
                value="{{ old('full_name') }}">
        </div>

        <!-- فیلد یوزرنیم مشتری -->
        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-user-circle"></i> یوزرنیم مشتری
            </label>
            <input type="text" name="customerusername" class="form-control"
                required placeholder="یوزرنیم (حروف لاتین، عدد، _ )"
                value="{{ old('customerusername') }}">
        </div>

        <!-- فیلد پسورد مشتری -->
        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-lock"></i> رمز عبور
            </label>
            <input type="password" name="password" class="form-control"
                required placeholder="رمز عبور قوی انتخاب کنید">
        </div>
        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-lock"></i> تکرار رمز عبور
            </label>
            <input type="password" name="password_confirmation" class="form-control"
                required placeholder="تکرار رمز عبور">
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-id-card"></i> کد ملی
            </label>
            <input type="text" name="national_code" class="form-control"
                required placeholder="کد ملی را وارد کنید"
                value="{{ old('national_code') }}">
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-phone"></i> شماره تماس
            </label>
            <input type="text" name="phone" class="form-control"
                required placeholder="مثلاً 09xxxxxxxxx"
                value="{{ old('phone') }}">
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-envelope"></i> ایمیل
            </label>
            <input type="email" name="email" class="form-control"
                placeholder="ایمیل (اختیاری)"
                value="{{ old('email') }}">
        </div>




        <div class="mb-3">
            <label for="referrer_type" class="form-label">
                <i class="fa fa-user-tag"></i> سمت معرف شما به سالن:
            </label>
            <select name="referrer_type" id="referrer_type" class="form-control">
                <option value="" {{ old('referrer_type') == '' ? 'selected' : '' }}>هیچ‌کس</option>
                <option value="admin" {{ old('referrer_type') == 'admin' ? 'selected' : '' }}>مدیر</option>
                <option value="staff" {{ old('referrer_type') == 'staff' ? 'selected' : '' }}>پرسنل</option>
                <option value="customer" {{ old('referrer_type') == 'customer' ? 'selected' : '' }}>مشتری دیگر</option>
            </select>
        </div>

        <!-- کد معرف -->
        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-key"></i> کد معرف شما
            </label>
            <small class="d-block text-muted mb-1">
                فقط حروف بزرگ A-Z، عدد و خط تیره مجاز است (مثلاً <code>C-XYZ123</code>)
            </small>
            <input type="text" name="referred_by" id="referred_by" class="form-control"
                placeholder="مثلاً: S-XYZ123" maxlength="20" autocomplete="off"
                value="{{ old('referred_by') }}">
            <span id="referred_by_error" style="display:none"></span>
        </div>

        <!-- جستجوی کد معرف با کد ملی معرف (در صورت نیاز) -->
        <div id="search-by-national-code" style="display:none; margin-top: 15px;">
            <label class="form-label">
                <i class="fa fa-search"></i> کد ملی معرف را وارد کنید:
            </label>
            <input type="text" id="referrer_national_code" class="form-control" placeholder="کد ملی معرف">
<button type="button"
        id="btn-find-referral"
        class="btn btn-primary mt-2"
        data-lookup-url="{{ route('admin.referral-code-by-national') }}">
  <i class="fa fa-search"></i> پیدا کن
</button>

            <span id="national-code-result" style="color: green; margin-right:8px;"></span>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-success px-4">
                <i class="fa fa-check"></i> ثبت مشتری
            </button>



       <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary px-4 customer-back-btn">
                <i class="fa fa-arrow-right"></i> انصراف
            </a>

        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof ajaxFormSubmit === 'function') {
    ajaxFormSubmit('customerCreateForm');
  }
});
</script>

@endsection
<!-- resources/views/admin/manage_customer/create.blade.php -->
@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">
            <i class="fa fa-user-tie"></i> ثبت نام پرسنل جدید
        </h2>
        <!--
        <a href="{{ url('/staff') }}" class="btn btn-outline-secondary staff-back-btn" data-url="{{ url('/staff') }}">
            <i class="fa fa-arrow-right"></i> بازگشت به لیست
        </a>
         -->
    </div>

    <form id="staffCreateForm" action="{{ route('admin.staff.store') }}" method="POST"
          class="p-4 rounded-4 shadow-sm bg-white modern-form">
        @csrf
        <div id="staff-create-message"></div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-user"></i> نام کامل</label>
            <input type="text" name="full_name" class="form-control" required placeholder="نام و نام خانوادگی" value="{{ old('full_name') }}">
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-user"></i> نام کاربری پرسنل</label>
            <input type="text"
                   name="staffusername"
                   class="form-control"
                   required
                   placeholder="نام کاربری یکتا (مثلاً: s.jafari)"
                   pattern="^[a-zA-Z0-9._\-]+$"
                   value="{{ old('staffusername') }}">
            <small class="text-muted">مثلاً: h.jafari یا sayid_jafari</small>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-lock"></i> رمز عبور ورود</label>
            <input type="password" name="password" class="form-control" required
                   placeholder="رمز عبور قوی برای پرسنل وارد کنید">
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-lock"></i> تکرار رمز عبور</label>
            <input type="password" name="password_confirmation" class="form-control" required
                   placeholder="تکرار رمز عبور">
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-phone"></i> شماره تماس</label>
            <input type="text" name="phone" class="form-control" required placeholder="مثلاً 09xxxxxxxxx" value="{{ old('phone') }}">
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-id-card"></i> کد ملی</label>
            <input type="text" name="national_code" class="form-control" required placeholder="کد ملی" value="{{ old('national_code') }}">
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-calendar"></i> تاریخ استخدام (شمسی)</label>
            <input type="text"
                   name="hire_date_jalali"
                   id="hire_date_jalali"
                   class="form-control datepicker"
                   autocomplete="off" readonly
                   required
                   value="{{ old('hire_date_jalali', isset($staff) ? \App\Helpers\JalaliHelper::toJalali($staff->hire_date) : '') }}">
        </div>

        {{-- ================== اطلاعات بانکی/پرداختی پرسنل ================== --}}

        {{-- انتخاب بانک از bank_lists (اختیاری) --}}
        <div class="mb-3">
            <label for="bank_name" class="form-label">
                <i class="fa fa-building-columns"></i> بانک
            </label>
            <select name="bank_name" id="bank_name" class="form-select">
                <option value="">— انتخاب کنید —</option>
                @foreach(($banks ?? []) as $b)
                    <option value="{{ $b->id }}" {{ (string)old('bank_name') === (string)$b->id ? 'selected' : '' }}>
                        {{ $b->name }} @if($b->short_name) ({{ $b->short_name }}) @endif
                    </option>
                @endforeach
            </select>
            <small class="text-muted d-block mt-1">انتخاب بانک اختیاری است.</small>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-credit-card"></i> شماره ترمینال POS</label>
            <input type="text" name="pos_terminal" class="form-control" required placeholder="شماره ترمینال" value="{{ old('pos_terminal') }}">
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-university"></i> شماره حساب بانکی</label>
            <input type="text" name="bank_account" class="form-control" required placeholder="شماره حساب" value="{{ old('bank_account') }}">
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-credit-card"></i> شماره کارت بانکی</label>
            <input type="text" name="card_number" class="form-control" required placeholder="شماره کارت" value="{{ old('card_number') }}">
        </div>

        {{-- ================== مهارت‌ها ================== --}}
        <div class="mb-3">
            <label for="categories" class="form-label">
                <i class="fa fa-cogs"></i> مهارت‌ها (دسته‌بندی خدمات):
            </label>
            <select name="categories[]" id="categories"
                    class="form-select @error('categories') is-invalid @enderror"
                    multiple required>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->title }}</option>
                @endforeach
            </select>
            <small class="text-muted">با نگه داشتن Ctrl می‌توانید چند مورد انتخاب کنید.</small>
            @error('categories')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3 form-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
                   {{ old('is_active', true) ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="is_active">
                <i class="fa fa-toggle-on text-success"></i> فعال باشد؟
            </label>
        </div>

        <!-- فیلد سمت معرف -->
        <div class="mb-3">
            <label for="referrer_type" class="form-label">
                <i class="fa fa-user-tag"></i> سمت معرف:
            </label>
            <select name="referrer_type" id="referrer_type" class="form-control">
                <option value="" {{ old('referrer_type') == '' ? 'selected' : '' }}>هیچ‌کس</option>
                <option value="admin" {{ old('referrer_type') == 'admin' ? 'selected' : '' }}>مدیر</option>
                <option value="staff" {{ old('referrer_type') == 'staff' ? 'selected' : '' }}>پرسنل دیگر</option>
                <option value="customer" {{ old('referrer_type') == 'customer' ? 'selected' : '' }}>مشتری</option>
            </select>
        </div>

        <!-- فیلد کد معرف -->
        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-key"></i> کد معرف شما
            </label>
            <small class="d-block text-muted mb-1">مثلاً <code>P-ABC123</code></small>
            <input type="text" name="referred_by" id="referred_by" class="form-control" maxlength="20" value="{{ old('referred_by') }}">
            <span id="referred_by_error" style="color:red; display:none;"></span>
        </div>

        <!-- جستجوی کد معرف با کد ملی معرف (در صورت نیاز) -->
        <div id="search-by-national-code" style="display:none; margin-top:15px;">
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

            <span id="national-code-result" style="color:green; margin-right:8px;"></span>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-success px-4">
                <i class="fa fa-check"></i> ثبت پرسنل
            </button>

            <a href="{{ route('admin.staff.index') }}" class="btn btn-outline-secondary px-4 staff-back-btn">
                <i class="fa fa-arrow-right"></i> انصراف
            </a>
        </div>
    </form>
</div>

@endsection

@section('scripts')
<script src="{{ asset('js/admin/staff-form.js') }}"></script>
@endsection

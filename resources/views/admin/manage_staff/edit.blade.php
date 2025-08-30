<!-- resources/views/admin/manage_staff/edit.blade.php -->
@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">
            <i class="fa fa-user-edit"></i> ویرایش پرسنل
        </h2>
        <a href="{{ route('admin.staff.index') }}" class="btn btn-outline-secondary staff-back-btn" data-url="{{ route('admin.staff.index') }}">
            <i class="fa fa-arrow-right"></i> بازگشت به لیست
        </a>
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

    <form id="staffEditForm" action="{{ route('admin.staff.update', $staff->id) }}" method="POST" class="p-4 rounded-4 shadow-sm bg-white modern-form">
        @csrf
        @method('PUT')

        <div id="staff-edit-message"></div>

        <!-- نمایش فقط خواندنی یوزرنیم -->
        <div class="mb-3">
            <label class="form-label"><i class="fa fa-user-circle"></i> نام کاربری پرسنل</label>
            <input type="text" value="{{ $staff->staffusername }}" class="form-control bg-light" readonly tabindex="-1">
            <input type="hidden" name="staffusername" value="{{ $staff->staffusername }}">
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-user"></i> نام کامل</label>
            <input type="text" name="full_name" value="{{ old('full_name', $staff->full_name) }}" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-phone"></i> شماره تماس</label>
            <input type="text" name="phone" value="{{ old('phone', $staff->phone) }}" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-id-card"></i> کد ملی</label>
            <input type="text" name="national_code" value="{{ old('national_code', $staff->national_code) }}" class="form-control" required>
        </div>

        <!-- فیلد تغییر رمز عبور (اختیاری) -->
        <div class="mb-3">
            <label class="form-label"><i class="fa fa-key"></i> رمز عبور جدید (در صورت تغییر)</label>
            <input type="password" name="password" class="form-control" placeholder="رمز عبور جدید را وارد کنید">
        </div>
        <div class="mb-3">
            <label class="form-label"><i class="fa fa-key"></i> تکرار رمز عبور جدید</label>
            <input type="password" name="password_confirmation" class="form-control" placeholder="تکرار رمز عبور جدید">
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
                    <option value="{{ $b->id }}"
                        {{ (string)old('bank_name', $paymentGateway->bank_name ?? '') === (string)$b->id ? 'selected' : '' }}>
                        {{ $b->name }} @if($b->short_name) ({{ $b->short_name }}) @endif
                    </option>
                @endforeach
            </select>
            <small class="text-muted d-block mt-1">انتخاب بانک اختیاری است.</small>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-credit-card"></i> شماره ترمینال POS</label>
            <input type="text" name="pos_terminal" class="form-control"
                   value="{{ old('pos_terminal', $paymentGateway->pos_terminal ?? '') }}">
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-university"></i> شماره حساب بانکی</label>
            <input type="text" name="bank_account" class="form-control"
                   value="{{ old('bank_account', $paymentGateway->bank_account ?? '') }}">
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="fa fa-credit-card"></i> شماره کارت بانکی</label>
            <input type="text" name="card_number" class="form-control"
                   value="{{ old('card_number', $paymentGateway->card_number ?? '') }}">
        </div>

        {{-- ================== مهارت‌ها ================== --}}
        <div class="mb-3">
            <label class="form-label"><i class="fa fa-cogs"></i> مهارت‌ها (خدمات قابل انجام):</label>
            <select id="categories" name="categories[]" class="form-select" multiple required>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ in_array($cat->id, $staffCategories ?? []) ? 'selected' : '' }}>
                        {{ $cat->title }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted">با نگه داشتن دکمه Ctrl می‌توانید چند مورد انتخاب کنید.</small>
        </div>

        @php
            $jalaliDate = $staff->hire_date ? \Morilog\Jalali\Jalalian::fromDateTime($staff->hire_date)->format('Y/m/d') : '';
        @endphp
        <div class="mb-3">
            <label class="form-label"><i class="fa fa-calendar"></i> تاریخ استخدام (شمسی)</label>
            <input type="text"
                   id="hire_date_jalali"
                   name="hire_date_jalali"
                   value="{{ old('hire_date_jalali', $jalaliDate) }}"
                   class="form-control datepicker"
                   required
                   autocomplete="off"
                   readonly>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" class="form-check-input" id="activeCheck" {{ old('is_active', $staff->is_active) ? 'checked' : '' }}>
            <label class="form-check-label" for="activeCheck"><i class="fa fa-toggle-on"></i> فعال باشد</label>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-success px-4">
                <i class="fa fa-check"></i> ذخیره تغییرات
            </button>
            <a href="{{ route('admin.staff.index') }}" class="btn btn-outline-secondary px-4 staff-back-btn" data-url="{{ route('admin.staff.index') }}">
                <i class="fa fa-arrow-right"></i> انصراف
            </a>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="{{ asset('js/libs/persian-datepicker.min.css') }}">
<script src="{{ asset('js/libs/jquery.min.js') }}"></script>
<script src="{{ asset('js/libs/persian-date.min.js') }}"></script>
<script src="{{ asset('js/libs/persian-datepicker.min.js') }}"></script>
<script src="{{ asset('js/admin/staff-form.js') }}"></script>
@endsection

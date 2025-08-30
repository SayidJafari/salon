<!--  resources/views/admin/manage_customer/edit.blade.php -->
@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">
            <i class="fa fa-user-edit"></i> ویرایش مشتری
        </h2>
        <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary staff-back-btn" data-url="{{ route('admin.customers.index') }}">
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

    <form id="customerEditForm" action="{{ route('admin.customers.update', $customer->id) }}" method="POST" class="p-4 rounded-4 shadow-sm bg-white modern-form">
        @csrf
        @method('PUT')

        <div id="customer-edit-message"></div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-user"></i> نام و نام خانوادگی
            </label>
            <input type="text" name="full_name" value="{{ $customer->full_name }}" class="form-control" required>
        </div>

        <!-- یوزرنیم فقط نمایش داده شود -->
        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-user"></i> یوزرنیم (فقط نمایش)
            </label>
            <input type="text" class="form-control bg-light" value="{{ $customer->customerusername }}" readonly>

            <!-- این خط زیر اضافه می‌شود 👇 -->
            <input type="hidden" name="customerusername" value="{{ $customer->customerusername }}">
        </div>


        <!-- فیلد تغییر پسورد (در صورت نیاز) -->
        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-lock"></i> رمز عبور جدید (در صورت تغییر)
            </label>
            <input type="password" name="password" class="form-control" placeholder="رمز عبور جدید را وارد کنید">
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-id-card"></i> کد ملی
            </label>
            <input type="text" name="national_code" value="{{ $customer->national_code }}" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-phone"></i> شماره تماس
            </label>
            <input type="text" name="phone" value="{{ $customer->phone }}" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-envelope"></i> ایمیل
            </label>
            <input type="email" name="email" value="{{ $customer->email }}" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-key"></i> کد معرفی (کدی که باید به مشتریان جدید بدهید)
            </label>
            <input type="text" name="referral_code" value="{{ $customer->referral_code }}" class="form-control" readonly>
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-user-tag"></i> کد معرف شما (کد شخصی که توسط ایشان معرفی شده‌اید)
            </label>
            <input type="text" name="referred_by" value="{{ $customer->referred_by }}" class="form-control">
        </div>

        <div class="mb-3 form-check">
            <input type="hidden" name="is_suspended" value="0">
            <input type="checkbox" name="is_suspended" id="is_suspended" class="form-check-input"
                value="1" {{ $customer->is_suspended ? 'checked' : '' }}>
            <label class="form-check-label" for="is_suspended">وضعیت تعلیق حساب</label>
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-wallet"></i> موجودی کیف پول
            </label>
            <input type="text" class="form-control bg-light" value="{{ number_format($customer->wallet_balance) }} تومان" readonly>
            {{-- اگر می‌خواهید صرفاً نمایش باشد و اصلاً به فرم ارسال نشود، فقط این input را قرار دهید --}}
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-success px-4">
                <i class="fa fa-save"></i> ذخیره تغییرات
            </button>
            <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary staff-back-btn" data-url="{{ route('admin.customers.index') }}">
                <i class="fa fa-arrow-right"></i> بازگشت به لیست
            </a>
        </div>
    </form>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        ajaxFormSubmit('customerEditForm');
    });
</script>

@endsection
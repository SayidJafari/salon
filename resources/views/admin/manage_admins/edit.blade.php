<!-- # resources/views/admin/manage_admins/edit.blade.php  -->

@extends('layouts.app')

@section('content')
<!-- استایل مدرن فرم‌ها -->
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<!-- اگر admin-reg.css داری، بجای بالا از آن استفاده کن -->

<!-- FontAwesome برای آیکون‌های قشنگ (اگر از قبل نداشتی) -->
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">


<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" style="color: #444;">
            <i class="fa fa-user-edit"></i> ویرایش مدیر
        </h2>
        <a href="{{ route('admin.admins.index') }}" class="btn btn-outline-secondary admin-back-btn">
            <i class="fa fa-arrow-right"></i> بازگشت به لیست
        </a>
    </div>

    <div id="admin-edit-message"></div>

    <form id="adminEditForm"
        action="{{ route('admin.admins.update', $admin->id) }}"
        method="POST"
        class="modern-form shadow-sm">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-user"></i> نام کاربری
            </label>
            <input type="text" name="adminusername" class="form-control" required value="{{ $admin->adminusername }}">
        </div>



        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-lock"></i> رمز عبور جدید (در صورت تغییر)
            </label>
            <input type="password" name="password" class="form-control" placeholder="رمز عبور جدید را وارد کنید">
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-lock"></i> تکرار رمز عبور جدید
            </label>
            <input type="password" name="password_confirmation" class="form-control" placeholder="تکرار رمز عبور جدید">
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" name="is_superadmin" value="1" class="form-check-input"
                id="superadmin" {{ $admin->is_superadmin ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="superadmin">
                <i class="fa fa-crown text-warning"></i> مدیر کل (Super Admin)
            </label>
        </div>
<div class="mb-3">
    <label class="form-label">
        <i class="fa fa-user"></i> نام کامل
    </label>
    <input type="text" name="fullname" class="form-control" required value="{{ $admin->fullname ?? '' }}">
</div>

<div class="mb-3">
    <label class="form-label">
        <i class="fa fa-phone"></i> تلفن
    </label>
    <input type="text" name="phones" class="form-control" value="{{ $admin->phones ?? '' }}">
</div>

<div class="mb-3">
    <label class="form-label">
        <i class="fa fa-map-marker"></i> آدرس
    </label>
    <input type="text" name="addresses" class="form-control" value="{{ $admin->addresses ?? '' }}">
</div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-success px-4">
                <i class="fa fa-save"></i> ذخیره تغییرات
            </button>
            <a href="{{ route('admin.admins.index') }}" class="btn btn-outline-secondary px-4 admin-back-btn">
                <i class="fa fa-arrow-right"></i> انصراف
            </a>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        ajaxFormSubmit('adminEditForm');
    });
</script>
@endsection
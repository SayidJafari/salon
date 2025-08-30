<!-- resources/views/admin/manage_admins/create.blade.php -->


@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/app-table.css') }}">
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">

<!-- FontAwesome (برای آیکون‌های قشنگ) -->
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">


<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" style="color: #444;">
            <i class="fa fa-user-plus"></i> افزودن مدیر جدید
        </h2>
        <!--
        <a href="{{ route('admin.admins.index') }}" class="btn btn-outline-secondary admin-back-btn">
            <i class="fa fa-arrow-right"></i> بازگشت به لیست
        </a>
         -->
    </div>

    <div id="admin-create-message"></div>
    {{-- محل نمایش پیام موفقیت یا خطا برای فرم ثبت مدیر --}}

    <form id="adminCreateForm"
        action="{{ route('admin.admins.store') }}"
        method="POST"
        class="modern-form shadow-sm">

        @csrf

        <div class="mb-3">
            <label for="adminusername" class="form-label">
                <i class="fa fa-user"></i> نام کاربری
            </label>
            <input type="text" name="adminusername" id="adminusername"
                class="form-control"
                required
                placeholder="نام کاربری را وارد کنید"
                value="{{ old('adminusername', $editAdmin->adminusername ?? '') }}">
        </div>


        <div class="mb-3">
            <label for="password" class="form-label">
                <i class="fa fa-lock"></i> رمز عبور
            </label>
            <input type="password" name="password" id="password"
                class="form-control"
                required
                placeholder="رمز عبور قوی وارد کنید">
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">
                <i class="fa fa-lock"></i> تکرار رمز عبور
            </label>
            <input type="password" name="password_confirmation" id="password_confirmation"
                class="form-control"
                required
                placeholder="تکرار رمز عبور">
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" name="is_superadmin"
                class="form-check-input"
                id="superadminCheck"
                value="1"
                {{ old('is_superadmin') ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="superadminCheck">
                <i class="fa fa-crown text-warning"></i> مدیر کل (Super Admin)
            </label>
        </div>

        <div class="mb-3">
            <label for="fullname" class="form-label"><i class="fa fa-user"></i> نام کامل</label>
            <input type="text" name="fullname" id="fullname"
                class="form-control"
                required
                placeholder="نام کامل مدیر را وارد کنید"
                value="{{ old('fullname') }}">
        </div>
        <div class="mb-3">
            <label for="phones" class="form-label"><i class="fa fa-phone"></i> تلفن</label>
            <input type="text" name="phones" id="phones"
                class="form-control"
                placeholder="شماره تلفن مدیر"
                value="{{ old('phones') }}">
        </div>
        <div class="mb-3">
            <label for="addresses" class="form-label"><i class="fa fa-map-marker"></i> آدرس</label>
            <input type="text" name="addresses" id="addresses"
                class="form-control"
                placeholder="آدرس مدیر"
                value="{{ old('addresses') }}">
        </div>


        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-success px-4">
                <i class="fa fa-check"></i> ثبت مدیر
            </button>
            <a href="{{ route('admin.admins.index') }}" class="btn btn-outline-secondary px-4 admin-back-btn">
                <i class="fa fa-arrow-right"></i> انصراف
            </a>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        ajaxFormSubmit('adminCreateForm');
    });
</script>@endsection
<!-- # resources/views/admin/discount_codes/create.blade.php -->
@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">


<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" id="discount-form-title" style="color:#444">
            <i class="fa fa-ticket-alt"></i>
            {{ isset($editDiscount) ? 'ویرایش کد تخفیف' : 'افزودن کد تخفیف جدید' }}
        </h2>
        @if(isset($editDiscount))
        <a href="{{ route('admin.discount-codes.create') }}" class="btn btn-outline-secondary px-4">
            <i class="fa fa-arrow-right"></i> لغو و بازگشت
        </a>
        @endif
    </div>

    <div id="discount-message"></div>

    <form id="discountCodeForm"
        action="{{ isset($editDiscount) ? route('admin.discount-codes.update', $editDiscount->id) : route('admin.discount-codes.store') }}"
        method="POST" enctype="multipart/form-data"
        class="modern-form shadow-sm p-4 rounded-4 bg-white mb-4">
        @csrf
        @if(isset($editDiscount))
        @method('PUT')
        <input type="hidden" name="discount_id" id="discount_id" value="{{ $editDiscount->id }}">
        @endif

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="code" class="form-label">
                    <i class="fa fa-barcode"></i> کد تخفیف:
                </label>
                <input type="text" id="code" name="code" class="form-control"
                    value="{{ old('code', $editDiscount->code ?? '') }}" required placeholder="کد تخفیف را وارد کنید">
            </div>
            <div class="col-md-6 mb-3">
                <label for="discount_type" class="form-label">
                    <i class="fa fa-percent"></i> نوع تخفیف:
                </label>
                <select id="discount_type" name="discount_type" class="form-control" required>
                    <option value="percent" {{ old('discount_type', $editDiscount->discount_type ?? '') == 'percent' ? 'selected' : '' }}>درصدی</option>
                    <option value="amount" {{ old('discount_type', $editDiscount->discount_type ?? '') == 'amount' ? 'selected' : '' }}>مبلغی</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="value" class="form-label">
                    <i class="fa fa-money-bill"></i> مقدار تخفیف:
                </label>
                <input type="number" id="value" name="value" class="form-control"
                    value="{{ old('value', $editDiscount->value ?? '') }}" required placeholder="مثلاً ۲۰">
            </div>
            <div class="col-md-6 mb-3">
                <label for="usage_limit" class="form-label">
                    <i class="fa fa-users"></i> حداکثر دفعات استفاده:
                </label>
                <input type="number" id="usage_limit" name="usage_limit" class="form-control"
                    value="{{ old('usage_limit', $editDiscount->usage_limit ?? '') }}" required placeholder="مثلاً ۱۰۰">
            </div>
            <div class="col-md-6 mb-3">
                <label for="valid_from_jalali" class="form-label">
                    <i class="fa fa-calendar-alt"></i> شروع اعتبار:
                </label>
                <input type="text" id="valid_from_jalali" name="valid_from_jalali" class="form-control datepicker"
                    value="{{ old('valid_from_jalali', isset($editDiscount->valid_from) ? \App\Helpers\JalaliHelper::toJalali($editDiscount->valid_from) : '') }}">
            </div>
            <div class="col-md-6 mb-3">
                <label for="valid_until_jalali" class="form-label">
                    <i class="fa fa-calendar-check"></i> پایان اعتبار:
                </label>
                <input type="text" id="valid_until_jalali" name="valid_until_jalali" class="form-control datepicker"
                    value="{{ old('valid_until_jalali', isset($editDiscount->valid_until) ? \App\Helpers\JalaliHelper::toJalali($editDiscount->valid_until) : '') }}">
            </div>
            <div class="col-md-6 mb-3">
                <label for="is_active" class="form-label">
                    <i class="fa fa-toggle-on"></i> وضعیت:
                </label>
                <select id="is_active" name="is_active" class="form-control">
                    <option value="1" {{ old('is_active', $editDiscount->is_active ?? 1) == 1 ? 'selected' : '' }}>فعال</option>
                    <option value="0" {{ old('is_active', $editDiscount->is_active ?? 1) == 0 ? 'selected' : '' }}>غیرفعال</option>
                </select>
            </div>
        </div>

        <div class="d-flex gap-2 mt-2">
            <button type="submit" id="saveDiscountBtn" class="btn btn-{{ isset($editDiscount) ? 'warning' : 'success' }} px-4">
                <i class="fa {{ isset($editDiscount) ? 'fa-save' : 'fa-plus' }}"></i>
                {{ isset($editDiscount) ? 'ذخیره تغییرات' : 'ثبت کد تخفیف' }}
            </button>
            @if(isset($editDiscount))
            <a href="{{ route('admin.discount-codes.create') }}" class="btn btn-outline-secondary px-4">
                <i class="fa fa-arrow-right"></i> انصراف
            </a>
            @endif
        </div>
    </form>

    <hr>
    <h4 class="fw-bold mb-3 mt-4"><i class="fa fa-list"></i> لیست کدهای تخفیف</h4>
    <div id="discount-list">
        @include('admin.discount_codes.list', ['discounts' => $discounts])
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        ajaxFormSubmit('discountCodeForm');
    });
</script>
@endsection

@push('scripts')
<script src="{{ asset('js/admin/discount-code-form.js') }}"></script>
@endpush
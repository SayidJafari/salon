<!-- # resources/views/admin/staff_commission/index.blade.php -->
@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">


<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" style="color:#444">
            <i class="fa fa-percent"></i> مدیریت کمسیون پرسنل
        </h2>



    </div>

    {{-- انتخاب پرسنل --}}
    <div class="mb-4" style="direction: rtl;">
        <div style="display: inline-flex; align-items: center; gap: 10px;">
            <label for="staff_select" class="form-label fw-bold mb-0" style="white-space: nowrap;">
                <i class="fa fa-user-tie ms-1"></i> انتخاب پرسنل:
            </label>

            <select id="staff_select" class="form-select modern-form" style="width: 300px;">
                <option value="">-- انتخاب کنید --</option>
                @foreach($staff as $person)
                <option value="{{ $person->id }}">{{ $person->full_name }} ({{ $person->national_code }})</option>
                @endforeach
            </select>
        </div>
    </div>




    {{-- پیام عملیات --}}
    <div id="commission-message"></div>

    {{-- فرم و جدول مهارت‌ها --}}
    <form id="commissionForm" style="display:none">
        @csrf
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle modern-table shadow rounded-4 bg-white staff-modern-table">
                <thead class="table-light text-center">
                    <tr>
                        <th>مهارت</th>
                        <th>نوع کیمسیون</th>
                        <th>مقدار</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody id="skillsRows">
                    {{-- ردیف‌های مهارت‌ها با JS پر می‌شود --}}
                </tbody>
            </table>
        </div>
        <div class="d-flex gap-2 mt-3">

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn px-4" style="background-color: #e83e8c; color: #fff;">
                    <i class="fa fa-save"></i> ثبت یا بروزرسانی کمسیون‌ها
                </button>

            </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    // مقدار csrf را برای استفاده در فایل js جداگانه در window قرار می‌دهیم
    window.csrfToken = '{{ csrf_token() }}';
</script>
<script src="{{ asset('js/admin/staff-commission.js') }}"></script>
@endsection
@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="modern-form shadow-sm p-4 rounded-4 bg-white my-5">
                <h4 class="fw-bold mb-4 d-flex align-items-center" style="color:#444">
                    <i class="fa fa-plus-circle ms-2"></i>
                    ثبت چک دریافتی جدید
                </h4>
                <form action="{{ route('admin.received-checks.store') }}" method="post" id="receivedCheckForm" autocomplete="off">
                    @csrf

                    <div class="alert alert-info mb-4 small">
                        <b>توضیح:</b> صادرکننده چک کسی است که چک را نوشته و امضا کرده است.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fa fa-hashtag"></i> شماره سریال چک <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="cheque_serial" class="form-control" required value="{{ old('cheque_serial') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fa fa-money-bill"></i> مبلغ <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="cheque_amount" class="form-control" required value="{{ old('cheque_amount') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fa fa-calendar-plus"></i> تاریخ صدور چک <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="cheque_issue_date_jalali" id="cheque_issue_date_jalali" class="form-control datepicker" autocomplete="off" required value="{{ old('cheque_issue_date_jalali') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fa fa-calendar-alt"></i> تاریخ سررسید <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="cheque_due_date_jalali" id="cheque_due_date_jalali" class="form-control datepicker" autocomplete="off" required value="{{ old('cheque_due_date_jalali') }}">
                    </div>

                    {{-- انتخاب بانک (cheque_bank_name = FK به bank_lists.id) --}}
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fa fa-university"></i> بانک
                        </label>
                        <select name="cheque_bank_name" id="cheque_bank_name" class="form-select">
                            <option value="">— انتخاب کنید —</option>
                            @foreach(($banks ?? []) as $b)
                                <option value="{{ $b->id }}" {{ (string)old('cheque_bank_name')===(string)$b->id ? 'selected' : '' }}>
                                    {{ $b->name }} @if(!empty($b->short_name)) ({{ $b->short_name }}) @endif
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted d-block mt-1">اختیاری؛ در صورت تمایل شماره حساب را نیز وارد کنید.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fa fa-credit-card"></i> شماره حساب بانکی
                        </label>
                        <input type="text" name="cheque_account_number" class="form-control" value="{{ old('cheque_account_number') }}">
                    </div>

                    <!-- شروع بخش جستجوی صادرکننده (این بخش تغییر نکند) -->
                    <div class="mb-3 position-relative">
                        <label class="form-label">
                            <i class="fa fa-search"></i> جستجوی صادرکننده (نام، کدملی یا موبایل)
                        </label>
                        <input type="text" id="issuer_search" class="form-control" autocomplete="off" placeholder="مثلاً علی یا ۰۹۱۲ یا کدملی...">
                        <input type="hidden" name="cheque_issuer_type" id="issuer_type">
                        <input type="hidden" name="cheque_issuer_id" id="issuer_id">
                        <div id="issuer_search_suggestions" class="list-group position-absolute w-100" style="z-index:99;"></div>
                        <div id="issuer_selected_badge" class="mt-2"></div>
                    </div>
                    <!-- پایان بخش جستجوی صادرکننده -->

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fa fa-user"></i> نام صادرکننده
                        </label>
                        <input type="text" name="cheque_issuer" id="issuer" class="form-control" value="{{ old('cheque_issuer') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fa fa-user"></i> نام دریافت‌کننده
                        </label>
                        <input type="text" name="receiver" class="form-control" value="{{ old('receiver') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fa fa-align-left"></i> توضیحات
                        </label>
                        <input type="text" name="description" class="form-control" value="{{ old('description') }}">
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-success px-4">
                            <i class="fa fa-check"></i> ثبت چک
                        </button>
                        <a href="{{ route('admin.received-checks.index') }}" class="btn btn-outline-secondary px-4 received-check-back-btn">
                            <i class="fa fa-arrow-right"></i> بازگشت
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.initReceivedCheckCreateForm === "function") window.initReceivedCheckCreateForm();
    // فعالسازی دیت‌پیکر شمسی برای هر دو تاریخ
    if (typeof $ !== "undefined" && $('.datepicker').length) {
        $('.datepicker').each(function() {
            let initialVal = $(this).val();
            $(this).persianDatepicker({
                format: 'YYYY/MM/DD',
                observer: true,
                autoClose: true,
                initialValue: !!initialVal,
                initialValueType: 'persian',
                calendar: { persian: { locale: 'fa' } },
                onShow: function() { $(this).attr('readonly', true); }
            });
        });
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ajaxFormSubmit === 'function') {
        ajaxFormSubmit('receivedCheckForm');
    }
});
</script>
@endpush

@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">


<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="modern-form shadow-sm p-4 rounded-4 bg-white my-5">
                <h4 class="fw-bold mb-4 d-flex align-items-center text-warning" style="color:#444">
                    <i class="fa fa-edit ms-2"></i>
                    ویرایش چک دریافتی
                </h4>
                <form action="{{ route('admin.received-checks.update', $receivedCheck) }}" method="post" id="receivedCheckEditForm" autocomplete="off">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-barcode"></i> سریال چک <span class="text-danger">*</span></label>
                        <input type="text" name="cheque_serial" class="form-control" required value="{{ old('cheque_serial', $receivedCheck->cheque_serial) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-credit-card"></i> شماره حساب</label>
                        <input type="text" name="cheque_account_number" class="form-control" value="{{ old('cheque_account_number', $receivedCheck->cheque_account_number) }}">
                    </div>

                    {{-- انتخاب بانک (cheque_bank_name = FK به bank_lists.id) --}}
                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-university"></i> نام بانک</label>
                        <select name="cheque_bank_name" id="cheque_bank_name" class="form-select">
                            <option value="">— انتخاب کنید —</option>
                            @foreach(($banks ?? []) as $b)
                                <option value="{{ $b->id }}" {{ (string)old('cheque_bank_name', $receivedCheck->cheque_bank_name)===(string)$b->id ? 'selected' : '' }}>
                                    {{ $b->name }} @if(!empty($b->short_name)) ({{ $b->short_name }}) @endif
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted d-block mt-1">اختیاری</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-money-bill"></i> مبلغ <span class="text-danger">*</span></label>
                        <input type="number" name="cheque_amount" class="form-control" required value="{{ old('cheque_amount', $receivedCheck->cheque_amount) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-calendar-plus"></i> تاریخ صدور چک <span class="text-danger">*</span></label>
                        <input type="text" name="cheque_issue_date_jalali" id="cheque_issue_date_jalali" class="form-control datepicker" autocomplete="off" required value="{{ old('cheque_issue_date_jalali', isset($receivedCheck->cheque_issue_date) ? \App\Helpers\JalaliHelper::toJalali($receivedCheck->cheque_issue_date) : '') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-calendar-alt"></i> تاریخ سررسید <span class="text-danger">*</span></label>
                        <input type="text" name="cheque_due_date_jalali" id="cheque_due_date_jalali" class="form-control datepicker" autocomplete="off" required value="{{ old('cheque_due_date_jalali', isset($receivedCheck->cheque_due_date) ? \App\Helpers\JalaliHelper::toJalali($receivedCheck->cheque_due_date) : '') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-info-circle"></i> وضعیت چک</label>
                        <select name="cheque_status" class="form-control">
                            <option value="pending" {{ old('cheque_status', $receivedCheck->cheque_status)=='pending' ? 'selected' : '' }}>در انتظار</option>
                            <option value="paid" {{ old('cheque_status', $receivedCheck->cheque_status)=='paid' ? 'selected' : '' }}>وصول شده</option>
                            <option value="returned" {{ old('cheque_status', $receivedCheck->cheque_status)=='returned' ? 'selected' : '' }}>برگشتی</option>
                            <option value="canceled" {{ old('cheque_status', $receivedCheck->cheque_status)=='canceled' ? 'selected' : '' }}>باطل شده</option>
                            <option value="transferred" {{ old('cheque_status', $receivedCheck->cheque_status)=='transferred' ? 'selected' : '' }}>منتقل شده</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-user"></i> صادرکننده</label>
                        <input type="text" name="cheque_issuer" class="form-control" value="{{ old('cheque_issuer', $receivedCheck->cheque_issuer) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-id-badge"></i> نوع صادرکننده</label>
                        <select name="cheque_issuer_type" class="form-control">
                            <option value="">-</option>
                            <option value="customer" {{ old('cheque_issuer_type', $receivedCheck->cheque_issuer_type)=='customer' ? 'selected' : '' }}>مشتری</option>
                            <option value="staff" {{ old('cheque_issuer_type', $receivedCheck->cheque_issuer_type)=='staff' ? 'selected' : '' }}>پرسنل</option>
                            <option value="contact" {{ old('cheque_issuer_type', $receivedCheck->cheque_issuer_type)=='contact' ? 'selected' : '' }}>سایر</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-user"></i> دریافت‌کننده</label>
                        <input type="text" name="receiver" class="form-control" value="{{ old('receiver', $receivedCheck->receiver) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-id-badge"></i> نوع دریافت‌کننده</label>
                        <select name="receiver_type" class="form-control">
                            <option value="">-</option>
                            <option value="customer" {{ old('receiver_type', $receivedCheck->receiver_type)=='customer' ? 'selected' : '' }}>مشتری</option>
                            <option value="staff" {{ old('receiver_type', $receivedCheck->receiver_type)=='staff' ? 'selected' : '' }}>پرسنل</option>
                            <option value="contact" {{ old('receiver_type', $receivedCheck->receiver_type)=='contact' ? 'selected' : '' }}>سایر</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-info"></i> توضیحات</label>
                        <input type="text" name="description" class="form-control" value="{{ old('description', $receivedCheck->description) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-university"></i> حساب خواباندن چک</label>
                        <input type="text" name="deposit_account_id" class="form-control" value="{{ old('deposit_account_id', $receivedCheck->deposit_account_id) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-random"></i> آیدی تراکنش</label>
                        <input type="text" name="transaction_id" class="form-control" value="{{ old('transaction_id', $receivedCheck->transaction_id) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-clock"></i> تاریخ تغییر وضعیت</label>
                        <input type="text" name="status_changed_at_jalali" id="status_changed_at_jalali" class="form-control datepicker" value="{{ old('status_changed_at_jalali', isset($receivedCheck->status_changed_at) ? \App\Helpers\JalaliHelper::toJalali($receivedCheck->status_changed_at) : '') }}">
                    </div>
                    <!-- شروع بخش انتقال چک با جستجوی هوشمند -->
                    <div class="mb-3 position-relative">
                        <label class="form-label"><i class="fa fa-search"></i> جستجوی طرف حساب انتقال (نام، کدملی یا موبایل)</label>
                        <input type="text" id="party_search" class="form-control" autocomplete="off"
                            placeholder="مثلاً رضا یا ۰۹۱۲ یا کدملی...">
                        <input type="hidden" name="transferred_to_type" id="party_type"
                            value="{{ old('transferred_to_type', $receivedCheck->transferred_to_type) }}">
                        <input type="hidden" name="transferred_to_id" id="party_id"
                            value="{{ old('transferred_to_id', $receivedCheck->transferred_to_id) }}">
                        <div id="party_search_suggestions" class="list-group position-absolute w-100" style="z-index:99;"></div>
                        @if($receivedCheck->transferred_to_type && $receivedCheck->transferred_to_id)
                        <div class="mt-1 small text-success">
                            انتخاب قبلی: {{ $receivedCheck->transferred_to_type }}
                        </div>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fa fa-calendar"></i> تاریخ انتقال</label>
                        <input type="text" name="transferred_at_jalali" id="transferred_at_jalali" class="form-control datepicker" value="{{ old('transferred_at_jalali', isset($receivedCheck->transferred_at) ? \App\Helpers\JalaliHelper::toJalali($receivedCheck->transferred_at) : '') }}">
                    </div>
                    <!-- پایان بخش انتقال چک -->

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-warning px-4">
                            <i class="fa fa-save"></i> ذخیره تغییرات
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
        // فعالسازی دیت‌پیکر شمسی برای همه datepickerها
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
        if (typeof window.initReceivedCheckEditForm === "function") window.initReceivedCheckEditForm();
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        ajaxFormSubmit('receivedCheckEditForm');
    });
</script>
@endpush

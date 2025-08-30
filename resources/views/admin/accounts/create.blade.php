{{-- resources/views/admin/accounts/create.blade.php --}}
@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">

@php
    $initKind  = old('kind', request('kind','bank'));
    $isCashbox = ($initKind === 'cashbox');
@endphp

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" style="color:#444">
            <i class="fa fa-university"></i> ثبت حساب مالی سالن
        </h2>
        <a href="{{ route('admin.accounts.index') }}"
           class="btn btn-outline-secondary staff-back-btn"
           data-url="{{ route('admin.accounts.index') }}">
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

    <div id="account-message"></div>

    <form id="accountCreateForm"
          action="{{ route('admin.accounts.store') }}"
          method="POST"
          class="modern-form shadow-sm p-4 rounded-4 bg-white mb-4">
        @csrf

        {{-- نوع رکورد --}}
        <div class="mb-3">
            <label for="kind" class="form-label">
                <i class="fa fa-exchange-alt"></i> نوع رکورد
            </label>
            <select id="kind" name="kind" class="form-select">
                <option value="bank" {{ $initKind === 'bank' ? 'selected' : '' }}>حساب بانکی</option>
                <option value="cashbox" {{ $initKind === 'cashbox' ? 'selected' : '' }}>صندوق</option>
            </select>
            <small class="text-muted d-block mt-1">با انتخاب «صندوق»، فقط بخش انتخاب بانک مخفی می‌شود.</small>
        </div>

        {{-- فقط این بخش در حالت cashbox مخفی شود --}}
        <div class="field-bank-name mb-3 {{ $isCashbox ? 'd-none' : '' }}">
            <label for="bank_name" class="form-label">
                <i class="fa fa-building-columns"></i> بانک
            </label>
            <select id="bank_name" name="bank_name" class="form-select">
                <option value="">— انتخاب کنید —</option>
                @foreach(($banks ?? []) as $b)
                    <option value="{{ $b->id }}" {{ (string)old('bank_name')===(string)$b->id ? 'selected' : '' }}>
                        {{ $b->name }} @if($b->short_name) ({{ $b->short_name }}) @endif
                    </option>
                @endforeach
            </select>
            <small class="text-muted d-block mt-1">
                انتخاب بانک اختیاری است؛ در صورت نیاز می‌توانید شعبه/عنوان حساب را در فیلد پایین وارد کنید.
            </small>
        </div>

        {{-- بقیه فیلدها همیشه نمایش داده شوند --}}
        <div class="field-title mb-3">
            <label for="title" class="form-label">
                <i class="fa fa-tag"></i> شعبه
            </label>
            <input type="text" id="title" name="title" class="form-control"
                   value="{{ old('title') }}" placeholder="مثلاً بانک ملت - شعبه ...">
        </div>

        <div class="field-owner mb-3">
            <label for="owner_name" class="form-label">
                <i class="fa fa-user"></i> نام صاحب حساب
            </label>
            <input type="text" id="owner_name" name="owner_name" class="form-control"
                   value="{{ old('owner_name') }}">
        </div>

        <div class="field-account mb-3">
            <label for="account_number" class="form-label">
                <i class="fa fa-hashtag"></i> شماره حساب
            </label>
            <input type="text" id="account_number" name="account_number" class="form-control"
                   value="{{ old('account_number') }}">
        </div>

        <div class="field-card mb-3">
            <label for="card_number" class="form-label">
                <i class="fa fa-credit-card"></i> شماره کارت
            </label>
            <input type="text" id="card_number" name="card_number" class="form-control"
                   value="{{ old('card_number') }}">
        </div>

        <div class="field-shaba mb-3">
            <label for="shaba_number" class="form-label">
                <i class="fa fa-random"></i> شماره شبا
            </label>
            <input type="text" id="shaba_number" name="shaba_number" class="form-control"
                   value="{{ old('shaba_number') }}">
        </div>

        {{-- تیک POS (اختیاری) --}}
        <div class="pos-section mb-3 form-check">
            <input type="checkbox" name="has_pos" id="has_pos" value="1"
                   class="form-check-input" {{ old('has_pos') ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="has_pos">
                <i class="fa fa-credit-card"></i> این حساب دستگاه کارتخوان (POS) دارد
            </label>
        </div>

        {{-- سریال دستگاه کارتخوان - همیشه نمایش داده می‌شود --}}
        <div class="mb-3">
            <label for="pos_terminal" class="form-label">
                <i class="fa fa-barcode"></i> سریال دستگاه کارتخوان (POS)
            </label>
            <input type="text"
                   id="pos_terminal"
                   name="pos_terminal"
                   class="form-control"
                   placeholder="مثلاً 123456789012345"
                   value="{{ old('pos_terminal') }}">
            <small class="text-muted d-block mt-1">اگر دستگاه ندارید خالی بگذارید.</small>
        </div>

        {{-- (اختیاری) نام صندوق --}}
        <div class="field-location mb-3">
            <label for="location" class="form-label">
                <i class="fa fa-map-marker-alt"></i> نام صندوق
            </label>
            <input type="text" id="location" name="location" class="form-control"
                   value="{{ old('location') }}" placeholder="مثال: صندوق اصلی پذیرش">
        </div>

        {{-- فعال بودن --}}
        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" id="is_active" value="1"
                   class="form-check-input" {{ old('is_active',1) ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="is_active">
                <i class="fa fa-toggle-on text-success"></i> فعال باشد
            </label>
        </div>

        <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-success px-4 fw-bold">
                <i class="fa fa-check"></i> ثبت
            </button>
            <a href="{{ route('admin.accounts.index') }}"
               class="btn btn-outline-secondary staff-back-btn"
               data-url="{{ route('admin.accounts.index') }}">
                <i class="fa fa-arrow-right"></i> انصراف
            </a>
        </div>
    </form>
</div>

{{-- فقط مخفی/نمایشِ بخش بانک در حالت صندوق --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const kind = document.getElementById('kind');
    const bankBlock = document.querySelector('.field-bank-name');
    function sync() { if (bankBlock) bankBlock.classList.toggle('d-none', kind && kind.value === 'cashbox'); }
    kind && kind.addEventListener('change', sync);
    sync();
});
</script>
@endsection

@section('scripts')
<script src="{{ asset('js/admin/account-form.js') }}"></script>
@endsection

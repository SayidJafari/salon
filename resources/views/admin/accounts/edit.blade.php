<!-- resources/views/admin/accounts/edit.blade.php -->

@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" style="color:#444">
            <i class="fa fa-edit"></i> ویرایش حساب مالی سالن
        </h2>
        <a href="#" class="btn btn-outline-secondary staff-back-btn menu-ajax"
           data-url="{{ route('admin.accounts.index') }}">
            <i class="fa fa-arrow-right"></i> بازگشت به لیست
        </a>
    </div>

    <div id="account-message"></div>

    <form id="accountEditForm"
          action="{{ route('admin.accounts.update', ['account' => $account->id, 'kind' => 'bank']) }}"
          method="POST"
          class="modern-form shadow-sm p-4 rounded-4 bg-white mb-4">
        @csrf
        @method('PUT')

        {{-- برای اینکه کنترلر مسیر بانکی را انتخاب کند --}}
        <input type="hidden" name="kind" value="bank">

        {{-- انتخاب بانک (bank_lists) --}}
        <div class="mb-3">
            <label for="bank_name" class="form-label">
                <i class="fa fa-building-columns"></i> بانک
            </label>
            <select name="bank_name" id="bank_name" class="form-select">
                <option value="">— انتخاب کنید —</option>
                @foreach(($banks ?? []) as $b)
                    <option value="{{ $b->id }}" {{ (string)old('bank_name', $account->bank_name)===(string)$b->id ? 'selected' : '' }}>
                        {{ $b->name }} @if($b->short_name) ({{ $b->short_name }}) @endif
                    </option>
                @endforeach
            </select>
        </div>

        {{-- نام حساب/شعبه --}}
        <div class="mb-3">
            <label for="title" class="form-label">
                <i class="fa fa-tag"></i> نام حساب/شعبه
            </label>
            <input type="text" name="title" id="title" class="form-control"
                   required value="{{ old('title', $account->title) }}">
        </div>

        {{-- نام صاحب حساب --}}
        <div class="mb-3">
            <label for="owner_name" class="form-label">
                <i class="fa fa-user"></i> نام صاحب حساب
            </label>
            <input type="text" name="owner_name" id="owner_name" class="form-control"
                   value="{{ old('owner_name', $account->owner_name) }}">
        </div>

        {{-- شماره حساب --}}
        <div class="mb-3">
            <label for="account_number" class="form-label">
                <i class="fa fa-hashtag"></i> شماره حساب
            </label>
            <input type="text" name="account_number" id="account_number" class="form-control"
                   value="{{ old('account_number', $account->account_number) }}">
        </div>

        {{-- شماره کارت --}}
        <div class="mb-3">
            <label for="card_number" class="form-label">
                <i class="fa fa-credit-card"></i> شماره کارت
            </label>
            <input type="text" name="card_number" id="card_number" class="form-control"
                   value="{{ old('card_number', $account->card_number) }}">
        </div>

        {{-- شماره شبا --}}
        <div class="mb-3">
            <label for="shaba_number" class="form-label">
                <i class="fa fa-random"></i> شماره شبا
            </label>
            <input type="text" name="shaba_number" id="shaba_number" class="form-control"
                   value="{{ old('shaba_number', $account->shaba_number) }}">
        </div>

        {{-- آیا POS دارد؟ --}}
        <div class="mb-3 form-check">
            <input type="hidden" name="has_pos" value="0"> {{-- اگر تیک نخورَد مقدار 0 ارسال شود --}}
            <input type="checkbox" name="has_pos" id="has_pos" value="1"
                   class="form-check-input" {{ old('has_pos', $account->has_pos) ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="has_pos">
                <i class="fa fa-credit-card"></i> این حساب دستگاه کارتخوان (POS) دارد
            </label>
        </div>

        {{-- کد دستگاه کارتخوان (همیشه نمایان) --}}
        <div class="mb-3 field-pos-terminal">
            <label for="pos_terminal" class="form-label">
                <i class="fa fa-barcode"></i> کد دستگاه کارتخوان (POS)
            </label>
            <input type="text" name="pos_terminal" id="pos_terminal" class="form-control"
                   value="{{ old('pos_terminal', $account->pos_terminal) }}">
        </div>

        {{-- فعال باشد --}}
        <div class="mb-3 form-check">
            <input type="hidden" name="is_active"  value="0">
            <input type="checkbox" name="is_active" id="is_active" value="1"
                   class="form-check-input" {{ old('is_active', $account->is_active) ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="is_active">
                <i class="fa fa-toggle-on text-success"></i> فعال باشد
            </label>
        </div>

        <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-primary px-4 fw-bold">
                <i class="fa fa-save"></i> ذخیره تغییرات
            </button>
            <a href="#" class="btn btn-outline-secondary staff-back-btn menu-ajax"
               data-url="{{ route('admin.accounts.index') }}">
                <i class="fa fa-arrow-right"></i> انصراف
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // ارسال ایجکس (در صورت داشتن helper)
  if (typeof ajaxFormSubmit === 'function') {
    ajaxFormSubmit('accountEditForm', function (data) {
      if (data && data.success && typeof loadPartial === 'function') {
        loadPartial('{{ route("admin.accounts.index") }}');
      }
    }, {
      beforeSend: function(form, formData) {
        const active = form.querySelector('#is_active')?.checked ? '1' : '0';
        const pos    = form.querySelector('#has_pos')?.checked ? '1' : '0';
        formData.set('is_active', active);
        formData.set('has_pos', pos);
        // اگر has_pos نزده باشد، در بک‌اند هم نال می‌کنیم؛ حذف این خط اختیاری است
        // if (pos === '0') formData.delete('pos_terminal');
      }
    });
  }
});
</script>
@endsection

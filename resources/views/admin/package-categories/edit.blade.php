<!-- # resources/views/admin/package-categories/edit.blade.php -->

@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">


<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" style="color:#444">
            <i class="fa fa-box-open"></i> ویرایش پک خدمات
        </h2>

        <a href="{{ route('admin.package-categories.index') }}" class="btn btn-outline-secondary staff-back-btn" data-url="{{ route('admin.package-categories.index') }}">
            <i class="fa fa-arrow-right"></i> بازگشت به لیست
        </a>
    </div>

    <form id="package-categoryEditForm"
        action="{{ route('admin.package-categories.update', $package->id) }}"
        method="POST"
        class="modern-form shadow-sm p-4 rounded-4 bg-white"
        enctype="multipart/form-data"
        style="max-width:600px;margin:auto;">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="name" class="form-label">
                <i class="fa fa-box"></i> نام پک <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" name="name" id="name" required value="{{ old('name', $package->name) }}">
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">
                <i class="fa fa-align-left"></i> توضیحات
            </label>
            <textarea class="form-control" name="description" id="description" rows="2">{{ old('description', $package->description) }}</textarea>
        </div>
<div class="mb-3 p-3 rounded-3" style="background:#f8fafc;border:1px dashed #dbe3ea">
  <div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" role="switch"
           id="pkg_ref_comm_enabled"
           name="ref_comm_enabled" value="1"
           {{ old('ref_comm_enabled', ($package->referrer_enabled ?? 0) ? 1 : 0) ? 'checked' : '' }}>
    <label class="form-check-label" for="pkg_ref_comm_enabled">فعال‌سازی کمیسیون معرف برای این پک</label>
  </div>

  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label">نوع کمیسیون</label>
      <select name="ref_comm_type" id="pkg_ref_comm_type" class="form-control">
        <option value="">— انتخاب کنید —</option>
        <option value="percent" {{ old('ref_comm_type', $package->referrer_commission_type ?? '')==='percent'?'selected':'' }}>درصدی</option>
        <option value="amount"  {{ old('ref_comm_type', $package->referrer_commission_type ?? '')==='amount'?'selected':''  }}>مبلغ ثابت</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">مقدار</label>
      <input type="number" step="0.01" name="ref_comm_value" id="pkg_ref_comm_value" class="form-control"
             value="{{ old('ref_comm_value', $package->referrer_commission_value ?? '') }}"
             placeholder="مثلاً 10 برای درصد، یا 50000 برای مبلغ ثابت">
    </div>
    <div class="col-md-4">
      <small class="text-muted d-block">
        اگر «درصدی» باشد، مقدار درصد از جمع ردیف‌های پک محاسبه می‌شود. اگر «مبلغ ثابت» باشد، مقدار ثابت برای کل پک است.
      </small>
    </div>
  </div>
</div>

        <hr>
        <h5 class="fw-bold mb-2"><i class="fa fa-list"></i> انتخاب خدمات پک <span class="text-danger">*</span></h5>
<div class="row" id="services-list">
    @foreach($services as $service)
        @php
            $checked = $package->services->contains($service->id);
            $quantity = $checked ? $package->services->find($service->id)->pivot->quantity : 1;
            $selectedStaffId = $selectedStaffs[$service->id] ?? null;
            $staffs = $serviceStaffs[$service->id] ?? collect();
        @endphp
        <div class="col-12 col-md-6 mb-2">
            <div class="d-flex align-items-center justify-content-between bg-light rounded-3 p-2">
                <label class="d-flex align-items-center gap-2 mb-0" style="font-weight: 500;">
                    <input type="checkbox" name="services[]" value="{{ $service->id }}" data-price="{{ $service->price }}" {{ $checked ? 'checked' : '' }}>
                    <span>{{ $service->title }}</span>
                    <span class="badge bg-light text-dark ms-1">{{ number_format($service->price) }} تومان</span>
                </label>
                <div style="min-width:150px;">
                    <select name="staffs[{{ $service->id }}]" class="form-select form-select-sm staff-select" {{ $checked ? '' : 'disabled' }}>
                        <option value="">انتخاب پرسنل</option>
                        @foreach($staffs as $staff)
                            <option value="{{ $staff->id }}" {{ $selectedStaffId == $staff->id ? 'selected' : '' }}>{{ $staff->full_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    @endforeach
</div>

        <div class="mb-3 mt-4">
            <label class="fw-bold">جمع قیمت خدمات انتخاب شده: <span id="total-services-price" class="text-primary">0</span> تومان</label>
        </div>

        <div class="mb-3">
            <label for="price" class="form-label">
                <i class="fa fa-money-bill"></i> قیمت کل پک <span class="text-danger">*</span>
            </label>
            <input type="number" class="form-control" name="price" id="price" required step="0.01" value="{{ old('price', $package->price) }}">
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" name="is_active" id="is_active" value="1" {{ old('is_active', $package->is_active) ? 'checked' : '' }}>
            <label for="is_active" class="form-check-label">فعال باشد</label>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-success px-4">
                <i class="fa fa-save"></i> ذخیره تغییرات
            </button>
            <a href="{{ route('admin.package-categories.index') }}" class="btn btn-outline-secondary staff-back-btn">
                <i class="fa fa-arrow-right"></i> بازگشت به لیست
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    ajaxFormSubmit('package-categoryEditForm');
    initPackageCreateForm();
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const en = document.getElementById('pkg_ref_comm_enabled');
  const ty = document.getElementById('pkg_ref_comm_type');
  const va = document.getElementById('pkg_ref_comm_value');
  function toggle() {
    const on = en && en.checked;
    if (ty) ty.disabled = !on;
    if (va) va.disabled = !on;
  }
  if (en) { en.addEventListener('change', toggle); toggle(); }
});
</script>

@endsection

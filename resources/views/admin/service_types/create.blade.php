<!--  # resources/views/admin/service_categories/create.blade.php -->

@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">

<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">
            <i class="fa fa-cogs"></i>
            {{ isset($editType) ? 'ویرایش نوع خدمت' : 'افزودن نوع خدمت جدید' }}
        </h2>
        @if(isset($editType))


        <a href="{{ route('admin.service-types.create') }}" class="btn btn-outline-secondary staff-back-btn" data-url="{{ route('admin.service-types.create') }}">
            <i class="fa fa-arrow-right"></i> لغو و بازگشت
        </a>
        @endif
    </div>

    @if(session('success'))
    <div class="alert alert-success rounded-3 shadow-sm">{{ session('success') }}</div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger rounded-3 shadow-sm mb-3">
        @foreach ($errors->all() as $error)
        <div>{{ $error }}</div>
        @endforeach
    </div>
    @endif

    <form action="{{ isset($editType) ? route('admin.service-types.update', $editType->id) : route('admin.service-types.store') }}"
        method="POST" enctype="multipart/form-data"
        class="modern-form shadow-sm mb-4 bg-white">
        @csrf
        @if(isset($editType))
        @method('PUT')
        @endif
        <div class="mb-3">
            <label for="category_id" class="form-label">
                <i class="fa fa-layer-group"></i> دسته‌بندی:
            </label>
            <select name="category_id" class="form-control" required>
                <option value="">-- انتخاب کنید --</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}"
                    {{ (old('category_id') == $cat->id || (isset($editType) && $editType->category_id == $cat->id)) ? 'selected' : '' }}>
                    {{ $cat->title }}
                </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label for="title" class="form-label">
                <i class="fa fa-tag"></i> عنوان نوع خدمت:
            </label>
            <input type="text" name="title" class="form-control"
                value="{{ old('title', $editType->title ?? '') }}" required
                placeholder="مثلاً: ماساژ درمانی">
        </div>

        <div class="mb-3">
            <label for="price" class="form-label">
                <i class="fa fa-money-bill"></i> قیمت (اختیاری):
            </label>
            <input type="number" step="0.01" name="price" class="form-control"
                value="{{ old('price', $editType->price ?? '') }}"
                placeholder="مثلاً ۱۵۰۰۰۰">
        </div>
        {{-- === کمیسیون معرف (اختیاری) === --}}
        <div class="mb-3 p-3 rounded-3" style="background:#f8fafc;border:1px dashed #dbe3ea">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="ref_comm_enabled"
                    name="ref_comm_enabled" value="1"
                    {{ old('ref_comm_enabled', ($editType->referrer_enabled ?? 0) ? 1 : 0) ? 'checked' : '' }}
                    <label class="form-check-label" for="ref_comm_enabled">
                فعال‌سازی کمیسیون معرف برای این خدمت
                </label>
            </div>

            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">نوع کمیسیون</label>
                    <select name="ref_comm_type" id="ref_comm_type" class="form-control">
                        <option value="">— انتخاب کنید —</option>
                        <option value="percent" {{ old('ref_comm_type', $editType->referrer_commission_type ?? '')==='percent'?'selected':'' }}>درصدی</option>
                        <option value="amount" {{ old('ref_comm_type', $editType->referrer_commission_type ?? '')==='amount'?'selected':'' }}>مبلغ ثابت</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">مقدار</label>
                    <input type="number" step="0.01" name="ref_comm_value" id="ref_comm_value" class="form-control"
                        value="{{ old('ref_comm_value', $editType->referrer_commission_value ?? '') }}"
                        placeholder="مثلاً 10 برای درصد، یا 50000 برای مبلغ ثابت">
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">
                        اگر «درصدی» باشد، مقدار به‌صورت درصد از جمع ردیف محاسبه می‌شود. اگر «مبلغ ثابت» باشد، مقدار به‌ازای هر واحد خدمت لحاظ می‌شود.
                    </small>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="image" class="form-label">
                <i class="fa fa-image"></i> عکس نوع خدمت:
            </label>
            <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png" @if(!isset($editType)) required @endif>
            @if(isset($editType) && $editType->image)
            @php
            $cat = $categories->firstWhere('id', $editType->category_id);
            @endphp
            @if($cat)
            <img src="{{ asset('uploads/service_types/' . $cat->folder . '/' . $editType->image) }}" style="max-width:80px;margin-top:10px;" class="rounded shadow-sm border">
            @else
            <div class="text-danger mt-2">دسته‌بندی مرتبط با این خدمت یافت نشد.</div>
            @endif
            @endif
        </div>

        <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-{{ isset($editType) ? 'warning' : 'success' }} px-4">
                <i class="fa {{ isset($editType) ? 'fa-save' : 'fa-plus' }}"></i>
                {{ isset($editType) ? 'ذخیره تغییرات' : 'ثبت نوع خدمت' }}
            </button>
            @if(isset($editType))


            <a href="{{ route('admin.service-types.create') }}" class="btn btn-outline-secondary staff-back-btn" data-url="{{ route('admin.service-types.create') }}">
                <i class="fa fa-arrow-right"></i> لغو و بازگشت
            </a>

            @endif
        </div>
    </form>

    <h4 class="fw-bold mb-3 mt-5"><i class="fa fa-list"></i> لیست انواع خدمات</h4>

    @if($types->isEmpty())
    <div class="alert alert-warning rounded-3 shadow-sm">هیچ نوع خدمتی ثبت نشده است.</div>
    @else
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle modern-table">
            <thead class="table-light">
                <tr>
                    <th>دسته‌بندی</th>
                    <th>عنوان نوع</th>
                    <th>قیمت</th>
                    <th>کمیسیون (فعال؟)</th>
                    <th>نوع کمیسیون</th>
                    <th>مقدار</th>
                    <th>عکس</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @foreach($types as $type)
                @php
                $cat = $categories->firstWhere('id', $type->category_id);
                @endphp
                <tr>
                    <td class="fw-bold text-dark">{{ $cat ? $cat->title : '---' }}</td>
                    <td>{{ $type->title }}</td>
                    <td>
                        @if($type->price)
                        <span class="badge bg-info text-white">{{ number_format($type->price) }}</span>
                        @else
                        <span class="badge bg-secondary">متغیر</span>
                        @endif
                    </td>
                    <td>
                        @if(!empty($type->referrer_enabled))
                        <span class="badge bg-success">فعال</span>
                        @else
                        <span class="badge bg-secondary">غیرفعال</span>
                        @endif
                    </td>
                    <td>{{ $type->referrer_commission_type ?? '-' }}</td>
                    <td>{{ $type->referrer_commission_value ?? '-' }}</td>

                    <!-- ✅ اینجا باید کد عکس اضافه بشه -->
                    <td>
                        @if($type->image && $cat)
                        <img src="{{ asset('uploads/service_types/' . $cat->folder . '/' . $type->image) }}"
                            style="max-width:80px;" class="rounded shadow-sm border">
                        @else
                        <span class="text-muted">بدون عکس</span>
                        @endif
                    </td>
                    <!-- تا اینجا -->

                    <td>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.service-types.edit', $type->id) }}" class="btn btn-sm btn-outline-primary rounded-circle edit-type-btn" title="ویرایش">
                                <i class="fa fa-pen"></i>
                            </a>
                            <form action="{{ route('admin.service-types.destroy', $type->id) }}" method="POST" style="display:inline;" class="delete-type-form d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="حذف" onclick="return confirm('حذف این نوع خدمت انجام شود؟');">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>

<script>
    (function() {
        const en = document.getElementById('ref_comm_enabled');
        const ty = document.getElementById('ref_comm_type');
        const va = document.getElementById('ref_comm_value');

        function toggle() {
            const on = en && en.checked;
            if (ty) ty.disabled = !on;
            if (va) va.disabled = !on;
            if (!on) {
                // خالی‌کردن اختیاری؛ اگر نمی‌خواهید پاک شود، این دو خط را حذف کنید
                // ty.value = '';
                // va.value = '';
            }
        }
        if (en) {
            en.addEventListener('change', toggle);
            toggle();
        }
    })();
</script>

@endsection
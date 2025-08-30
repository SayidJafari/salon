<!-- # resources/views/admin/package-categories/create.blade.php -->
@extends('layouts.app')
@section('content')

<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">


<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" style="color:#444">
            <i class="fa fa-boxes"></i> ساخت پک جدید
        </h2>

        <a href="{{ route('admin.package-categories.index') }}" class="btn btn-outline-secondary staff-back-btn" data-url="{{ route('admin.package-categories.index') }}">
            <i class="fa fa-arrow-right"></i> لغو و بازگشت
        </a>


    </div>

    @if ($errors->any())
    <div class="alert alert-danger rounded-3">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('admin.package-categories.store') }}" method="post" class="modern-form shadow-sm mb-4" enctype="multipart/form-data">
        @csrf

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-cube"></i> نام پک
            </label>
            <input type="text" name="name" class="form-control" required placeholder="مثلاً: پک اقتصادی" value="{{ old('name') }}">
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-align-left"></i> توضیحات
            </label>
            <textarea name="description" class="form-control" placeholder="توضیحات پک">{{ old('description') }}</textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-money-bill-wave"></i> قیمت پک (تومان)
            </label>
            <input type="number" name="price" step="0.01" class="form-control" required placeholder="مثلاً: 150000" value="{{ old('price') }}">
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-image"></i> تصویر (آدرس یا آپلود)
            </label>
            <input type="file" name="image" class="form-control" placeholder="لینک عکس (اختیاری)" value="{{ old('image') }}">
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" checked>
            <label class="form-check-label fw-bold" for="is_active">
                <i class="fa fa-toggle-on text-success"></i> فعال باشد؟
            </label>
        </div>

        <hr class="my-4">
        <h4 class="fw-bold mb-3"><i class="fa fa-tasks"></i> انتخاب خدمات پک</h4>
        <div class="row g-2">
            @foreach($services as $index => $service)
            <div class="col-md-6 col-lg-4">
                <div class="border rounded-3 px-3 py-2 mb-2 d-flex align-items-center justify-content-between" style="background:#f8fafc;">
                    <div>
                        <label class="d-flex align-items-center mb-0" style="gap:6px">
                            <input type="checkbox" name="services[]" value="{{ $service->id }}" style="accent-color:#ff4c8c;">
                            <span>{{ $service->title }}</span>
                            <span class="badge bg-light text-dark ms-1">{{ number_format($service->price) }} تومان</span>
                        </label>
                    </div>
                    <div>
                       <!-- <small>تعداد:</small>
                        <input type="number" name="quantities[{{ $index }}]" value="1" min="1"
                               class="form-control form-control-sm d-inline-block" style="width:70px;text-align:center;"> -->
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-success px-4">
                <i class="fa fa-save"></i> ذخیره پک
            </button>


        <a href="{{ route('admin.package-categories.index') }}" class="btn btn-outline-secondary staff-back-btn" data-url="{{ route('admin.package-categories.index') }}">
            <i class="fa fa-arrow-right"></i> لغو و بازگشت
        </a>

        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    ajaxFormSubmit('packageCategoryCreateForm');
});
</script>

@endsection

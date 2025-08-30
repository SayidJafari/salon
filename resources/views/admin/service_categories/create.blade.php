
<!-- # resources/views/admin/service_categories/create.blade.php -->
@extends('layouts.app')

@section('content')

<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">

<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" style="color:#444">
            <i class="fa fa-layer-group"></i> ایجاد دسته‌بندی جدید خدمات
        </h2>
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

    <form id="service-categoriesCreateForm"
          action="{{ route('admin.service-categories.store') }}"
          method="POST"
          class="modern-form shadow-sm mb-4">

        @csrf

        <div class="mb-3">
            <label for="title" class="form-label">
                <i class="fa fa-tag"></i> عنوان دسته‌بندی
            </label>
            <input type="text" name="title" class="form-control"
                   required placeholder="مثلاً: زیبایی و سلامت" value="{{ old('title') }}">
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">
                <i class="fa fa-align-left"></i> توضیحات
            </label>
            <textarea name="description" class="form-control" placeholder="توضیحات دسته‌بندی (اختیاری)">{{ old('description') }}</textarea>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-success px-4">
                <i class="fa fa-plus"></i> ثبت دسته‌بندی
            </button>
            <a href="{{ route('admin.service-categories.index') }}" class="btn btn-outline-secondary px-4">
                <i class="fa fa-arrow-right"></i> بازگشت
            </a>
        </div>
    </form>

    <h4 class="fw-bold mb-3 mt-5">
        <i class="fa fa-list"></i> لیست دسته‌بندی‌های خدمات
    </h4>

    @if ($categories->isEmpty())
        <div class="alert alert-warning rounded-4 shadow-sm">هیچ دسته‌بندی‌ای ثبت نشده است.</div>
    @else
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle modern-table shadow rounded-4 bg-white staff-modern-table">
            <thead class="table-light">
                <tr>
                    <th>عنوان دسته‌بندی</th>
                    <th>توضیحات</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @foreach($categories as $category)
                <tr>
<td class="fw-bold text-dark">{{ $category->title }}</td>
                    <td style="min-width:140px">{{ $category->description }}</td>
                    <td>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.service-categories.edit', $category->id) }}"
                               class="btn btn-sm btn-outline-primary rounded-circle edit-category-btn" title="ویرایش">
                                <i class="fa fa-pen"></i>
                            </a>
                            <form action="{{ route('admin.service-categories.destroy', $category->id) }}" method="POST" style="display:inline;" class="delete-category-form d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="حذف">
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
document.addEventListener('DOMContentLoaded', function() {
    // ارسال فرم ایجاد دسته‌بندی با AJAX
    ajaxFormSubmit('service-categoriesCreateForm');

    // دکمه ویرایش دسته‌بندی (در لیست)
    ajaxEditButtons('.edit-category-btn');

    // حذف دسته‌بندی
    ajaxDeleteForm('.delete-category-form', 'آیا از حذف این دسته‌بندی مطمئن هستید؟', function(data){
        if(data.success){
            // نمایش پیام موفقیت
            document.getElementById('category-list-message').innerHTML =
                '<div class="alert alert-success">' + data.message + '</div>';
            // بارگذاری مجدد لیست دسته‌بندی‌ها (بدون رفرش کل صفحه)
            loadPartial('{{ route("admin.service-categories.index") }}');
        }else{
            document.getElementById('category-list-message').innerHTML =
                '<div class="alert alert-danger">' + (data.message || 'خطایی رخ داده است!') + '</div>';
        }
    });
});
</script>

@endsection

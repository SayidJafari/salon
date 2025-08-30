
<!-- # resources/views/admin/service_categories/edit.blade.php -->
@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}"> 

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" style="color:#444">
            <i class="fa fa-pen-to-square"></i>
            ویرایش دسته‌بندی خدمات
        </h2>


        <a href="{{ route('admin.service-categories.index') }}" class="btn btn-outline-secondary staff-back-btn" data-url="{{ route('admin.service-categories.index') }}">
            <i class="fa fa-arrow-right"></i> بازگشت به لیست
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

    <form id="service-categoryEditForm" action="{{ route('admin.service-categories.update', $category->id) }}" method="POST" class="modern-form shadow-sm p-4 rounded-4 bg-white mb-4">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label for="title" class="form-label">
                <i class="fa fa-tag"></i> عنوان دسته‌بندی
            </label>
            <input type="text" name="title" class="form-control" required value="{{ old('title', $category->title) }}">
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">
                <i class="fa fa-align-left"></i> توضیحات
            </label>
            <textarea name="description" class="form-control">{{ old('description', $category->description) }}</textarea>
        </div>
        <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-success px-4">
                <i class="fa fa-check"></i> ذخیره تغییرات
            </button>
        <a href="{{ route('admin.service-categories.index') }}" class="btn btn-outline-secondary staff-back-btn" data-url="{{ route('admin.service-categories.index') }}">
            <i class="fa fa-arrow-right"></i> بازگشت به لیست
        </a>

        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    ajaxFormSubmit('service-categoryEditForm');
});
</script>

@endsection


<!-- # resources/views/admin/service_categories/index.blade.php -->
@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}"> 

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" style="color:#444">
            <i class="fa fa-list"></i> لیست دسته‌بندی‌های خدمات
        </h2>
        <a href="{{ route('admin.service-categories.create') }}" class="btn btn-success px-4 add-category-btn">
            <i class="fa fa-plus"></i> افزودن دسته‌بندی جدید
        </a>
    </div>
    
    {{-- پیام موفقیت --}}
    @if(session('success'))
        <div class="alert alert-success rounded-3 shadow-sm">{{ session('success') }}</div>
    @endif

    {{-- پیام خطای حذف --}}
    @if($errors->has('delete'))
        <div class="alert alert-danger rounded-3 shadow-sm">
            {{ $errors->first('delete') }}
        </div>
    @endif

    <div class="table-responsive shadow rounded-4 bg-white staff-modern-table modern-table">
        <table class="table table-bordered table-hover align-middle modern-table">
            <thead class="table-light">
                <tr>
                    <th>عنوان دسته‌بندی</th>
                    <th>توضیحات</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
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
                @empty
                <tr>
                    <td colspan="3" class="text-center text-muted">هیچ دسته‌بندی‌ای ثبت نشده است.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

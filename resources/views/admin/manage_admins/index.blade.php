
<!-- resources/views/admin/manage_admins/index.blade.php -->

        
@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold mb-0">
            <i class="fa fa-users"></i>
            لیست مدیران
        </h2>
        <a href="#" class="btn btn-success menu-ajax"
           data-url="{{ route('admin.admins.create') }}">
            <i class="fa fa-plus"></i> ثبت مدیر جدید
        </a>
    </div>

    <div id="admin-list-message"></div>

    @if ($admins->isEmpty())
        <div class="alert alert-warning">هیچ مدیری ثبت نشده است.</div>
    @else
        <div class="table-responsive shadow rounded-4 bg-white staff-modern-table">
            <table class="table table-bordered  table-hover align-middle modern-table text-center">
<thead class="table-light">
    <tr>
        <th>شناسه</th>
        <th>نام کاربری</th>
        <th>نام کامل</th>
        <th>تلفن</th>
        <th>آدرس</th>
        <th style="width: 90px;">کد شما</th>
        <th style="width: 90px;">نقش</th>
        <th>تاریخ ثبت</th>
        <th>عملیات</th>
    </tr>
</thead>
<tbody>
@foreach($admins as $admin)
<tr>
    <td class="fw-bold text-muted">{{ $admin->id }}</td>
    <td>
        <div class="d-flex align-items-center justify-content-center gap-2">
            <span class="avatar-circle" title="اولین حرف نام">
                {{ mb_substr($admin->adminusername, 0, 1) }}
            </span>
            <span>{{ $admin->adminusername }}</span>
        </div>
    </td>
    <td>{{ $admin->fullname ?? '—' }}</td>
    <td>{{ $admin->phones ?? '—' }}</td>
    <td>{{ $admin->addresses ?? '—' }}</td>
    <td>
        <div class="d-flex flex-column align-items-center">
            <span class="badge bg-info text-white mt-1">
                {{ $admin->referral_code ?? '—' }}
            </span>
        </div>
    </td>
    <td>
        <div class="d-flex flex-column align-items-center">
            <small class="text-muted">مدیر کل</small>
            @if($admin->is_superadmin)
                <span class="badge bg-success mt-1">بله</span>
            @else
                <span class="badge bg-secondary mt-1">خیر</span>
            @endif
        </div>
    </td>
    <td>
        <span class="badge bg-light text-secondary fw-normal">
            {{ \Morilog\Jalali\Jalalian::fromDateTime($admin->created_at)->format('Y/m/d') }}
        </span>
    </td>
    <td>
        <div class="d-flex justify-content-center gap-2">
            <a href="#" class="btn btn-sm btn-outline-primary rounded-circle edit-admin-btn"
               data-url="{{ route('admin.admins.edit', $admin->id) }}"
               title="ویرایش مدیر">
                <i class="fa fa-pen"></i>
            </a>
            <form class="delete-admin-form d-inline" data-action="{{ route('admin.admins.destroy', $admin->id) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="حذف مدیر">
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
document.addEventListener("DOMContentLoaded", function() {
    
    // دکمه ویرایش مدیر (لود فرم ویرایش)
    ajaxEditButtons('.edit-admin-btn');
    
    // فرم حذف مدیر
    ajaxDeleteForm('.delete-admin-form', 'آیا از حذف این مدیر مطمئن هستید؟', function(data){
        if(data.success){
            document.getElementById('admin-list-message').innerHTML =
                '<div class="alert alert-success">' + data.message + '</div>';
loadPartial('{{ route("admin.admins.index") }}');
        }else{
            document.getElementById('admin-list-message').innerHTML =
                '<div class="alert alert-danger">' + (data.message || 'خطایی رخ داده است!') + '</div>';
        }
    });

 

});
</script>
@endsection

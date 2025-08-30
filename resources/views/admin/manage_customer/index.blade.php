<!--  resources/views/admin/manage_customer/index.blade.php -->

@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold mb-0">
            <i class="fa fa-users"></i>
            لیست مشتریان
        </h2>
        <a href="#" class="btn btn-success menu-ajax" data-url="{{ route('admin.customers.create') }}">
            <i class="fa fa-plus"></i> افزودن مشتری
        </a>
    </div>

    <div id="customer-list-message"></div>

    @if ($customers->isEmpty())
    <div class="alert alert-warning">هیچ مشتری ثبت نشده است.</div>
    @else
    <div class="table-responsive shadow rounded-4 bg-white staff-modern-table">
        <table class="table table-bordered  table-hover align-middle modern-table">
            <thead class="table-light">
                <tr>
                    <th>شناسه</th>
                    <th>نام کامل</th>
                    <th>یوزرنیم</th> <!-- ستونی که اضافه شده -->
                    <th>کد ملی</th>
                    <th>موبایل</th>
                    <th>ایمیل</th>
                    <th>کد شما</th>
                    <th>کد معرف شما</th>
                    <th>موجودی کیف پول</th>
                    <th>تعلیق؟</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($customers as $customer)
                <tr>
                    <td class="fw-bold text-muted">{{ $customer->id }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar-circle">{{ mb_substr($customer->full_name,0,1) }}</span>
                            <span>{{ $customer->full_name }}</span>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark px-2">{{ $customer->customerusername ?? '—' }}</span>
                    </td>
                    <td>{{ $customer->national_code }}</td>
                    <td><span class="badge bg-light text-dark px-3">{{ $customer->phone }}</span></td>
                    <td>{{ $customer->email }}</td>
                    <td><span class="badge bg-info text-white">{{ $customer->referral_code ?? '—' }}</span></td>
                    <td><span class="badge bg-light text-dark">{{ $customer->referred_by ?? '—' }}</span></td>
<td>{{ number_format($customer->wallet_balance ?? 0) }} <span class="text-muted">تومان</span></td>
                    <td>
                        @if($customer->is_suspended)
                            <span class="badge bg-danger">غیر فعال</span>
                        @else
                            <span class="badge bg-success">فعال</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <a href="#" class="btn btn-sm btn-outline-primary rounded-circle edit-customer-btn"
                                data-url="{{ route('admin.customers.edit', $customer->id) }}"
                                title="ویرایش">
                                <i class="fa fa-pen"></i>
                            </a>
                            <form class="delete-customer-form d-inline" data-action="{{ route('admin.customers.destroy', $customer->id) }}">
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
document.addEventListener("DOMContentLoaded", function() {

    // دکمه ویرایش مشتری
    ajaxEditButtons('.edit-customer-btn');

    // حذف مشتری
    ajaxDeleteForm('.delete-customer-form', 'آیا از حذف این مشتری مطمئن هستید؟', function(data){
        if(data.success){
            document.getElementById('customer-list-message').innerHTML =
                '<div class="alert alert-success">' + data.message + '</div>';
            loadPartial('{{ route("admin.customers.index") }}');
        }else{
            document.getElementById('customer-list-message').innerHTML =
                '<div class="alert alert-danger">' + (data.message || 'خطایی رخ داده است!') + '</div>';
        }
    });

});
</script>



@endsection

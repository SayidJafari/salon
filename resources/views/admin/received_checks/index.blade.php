@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="{{ asset('css/app-table.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">

<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">
            <i class="fa fa-money-check-alt"></i>
            لیست چک‌ها
        </h2>
        <a href="{{ route('admin.received-checks.create') }}"
            class="btn btn-success menu-ajax px-4 add-received-check-btn"
            data-url="{{ route('admin.received-checks.create') }}">
            <i class="fa fa-plus"></i> افزودن چک جدید
        </a>
    </div>

    <div id="received-check-list-message"></div>
    <div id="received-check-form-container"></div>

    <form method="get" action="{{ route('admin.received-checks.index') }}" class="row mb-4 g-2 align-items-center">
        @csrf
        <div class="col flex-grow-1">
            <input type="text" name="q" id="received-check-search" class="form-control"
                value="{{ $q ?? '' }}" placeholder="جستجو در همه فیلدها...">
        </div>
        <div class="col-auto">
            <button class="btn btn-info px-4"><i class="fa fa-search"></i> جستجو</button>
        </div>
    </form>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle modern-table text-center">
            <thead class="table-light align-middle" style="font-size: 1.01rem;">
                <tr>
                    <th>سریال چک</th>
                    <th>شماره حساب</th>
                    <th>بانک</th>
                    <th>مبلغ</th>
                    <th>تاریخ صدور</th>
                    <th>تاریخ سررسید</th>
                    <th>وضعیت</th>
                    <th>صادرکننده</th>
                    <th>جایگاه</th>
                    <th>دریافت‌کننده</th>
                    <th>نوع دریافت‌کننده</th>
                    <th>توضیحات</th>
                    <th>وضعیت انتقال</th>
                    <th>خواباندن به حساب</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $types = ['customer' => 'مشتری', 'staff' => 'پرسنل', 'contact' => 'سایر'];
                @endphp
                @foreach($checks as $check)
                <tr>
                    <td class="fw-bold">{{ $check->cheque_serial }}</td>
                    <td class="fw-bold">{{ $check->cheque_account_number }}</td>

                    {{-- نام بانک از join (bank_name)؛ اگر نبود، خط تیره --}}
                    <td class="fw-bold">
                        {{ $check->bank_name ? $check->bank_name : '—' }}
                    </td>

                    <td><span class="badge bg-success fs-6">{{ number_format($check->cheque_amount) }}</span></td>
                    <td class="fw-bold">
                        {{ \App\Helpers\JalaliHelper::toJalali($check->cheque_issue_date) }}
                    </td>
                    <td class="fw-bold">
                        {{ \App\Helpers\JalaliHelper::toJalali($check->cheque_due_date) }}
                    </td>
                    <td>
                        @php
                        $status = $check->cheque_status ?? $check->status;
                        $statusFa = match($status) {
                            'pending' => 'در انتظار',
                            'paid' => 'وصول شده',
                            'returned' => 'برگشتی',
                            'canceled' => 'باطل شده',
                            'transferred' => 'منتقل شده',
                            default => $status
                        };
                        $badgeClass = match($status) {
                            'pending' => 'bg-warning text-dark',
                            'paid' => 'bg-success',
                            'returned' => 'bg-danger',
                            'canceled' => 'bg-secondary',
                            'transferred' => 'bg-info text-dark',
                            default => 'bg-light text-dark'
                        };
                        @endphp
                        <span class="badge {{ $badgeClass }} fs-6">{{ $statusFa }}</span>
                    </td>
                    <td>{{ $check->cheque_issuer }}</td>
                    <td>{{ $types[$check->cheque_issuer_type ?? ''] ?? '-' }}</td>
                    <td>{{ $check->receiver }}</td>
                    <td>{{ $types[$check->receiver_type ?? ''] ?? '-' }}</td>
                    <td style="max-width: 180px;">{{ $check->description }}</td>
                    <td>
                        @if($check->transferred_to_type || $check->transferred_to_id)
                            <span class="badge bg-info text-dark">
                                {{ $types[$check->transferred_to_type ?? ''] ?? '-' }}
                                {{ $check->transferredParty()->full_name ?? $check->transferredParty()->name ?? '' }}
                                @if($check->transferred_at)
                                    <br><span class="text-secondary small">{{ \App\Helpers\JalaliHelper::toJalali($check->transferred_at) }}</span>
                                @endif
                            </span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if($check->deposit_account_id)
                            <span class="badge bg-primary">خوابانده شده</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="{{ route('admin.received-checks.edit', $check) }}"
                                class="btn btn-sm btn-outline-primary rounded-circle edit-received-check-btn"
                                data-url="{{ route('admin.received-checks.edit', $check) }}"
                                title="ویرایش">
                                <i class="fa fa-pen"></i>
                            </a>
                            <form action="{{ route('admin.received-checks.destroy', $check) }}"
                                method="post" style="display:inline;"
                                class="delete-received-check-form"
                                data-action="{{ route('admin.received-checks.destroy', $check) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger rounded-circle" onclick="return confirm('حذف شود؟')" title="حذف">
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

    <div class="d-flex justify-content-center">
        {{ $checks->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof initReceivedCheckForm === "function") initReceivedCheckForm();
    });
</script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        ajaxEditButtons('.edit-received-check-btn');
        ajaxDeleteForm('.delete-received-check-form', 'آیا از حذف این چک مطمئن هستید؟', function(data) {
            if (data.success) {
                document.getElementById('received-check-list-message').innerHTML =
                    '<div class="alert alert-success">' + data.message + '</div>';
                loadPartial('{{ route("admin.received-checks.index") }}');
            } else {
                document.getElementById('received-check-list-message').innerHTML =
                    '<div class="alert alert-danger">' + (data.message || 'خطایی رخ داده است!') + '</div>';
            }
        });
    });
</script>
@endpush

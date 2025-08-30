
<!-- # resources/views/admin/discount_codes/list.blade.php -->
@if($discounts->isEmpty())
    <div class="alert alert-warning mb-0">هیچ کد تخفیفی ثبت نشده است.</div>
@else
    <style>
        .discount-table th, .discount-table td { font-size: 0.97rem; padding-top:8px; padding-bottom:8px;}
    </style>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle modern-table discount-table">
            <thead class="table-light text-center">
                <tr>
                    <th>کد</th>
                    <th>نوع</th>
                    <th>مقدار</th>
                    <th>دفعات مجاز</th>
                    <th>استفاده شده</th>
                    <th>تاریخ شروع</th>
                    <th>تاریخ پایان</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
            @foreach($discounts as $discount)
                <tr id="discount-row-{{ $discount->id }}">
                    <td class="fw-bold">{{ $discount->code }}</td>
                    <td>
                        <span class="badge {{ $discount->discount_type == 'percent' ? 'bg-info text-white' : 'bg-primary' }}">
                            <i class="fa {{ $discount->discount_type == 'percent' ? 'fa-percent' : 'fa-money-bill' }}"></i>
                            {{ $discount->discount_type == 'percent' ? 'درصدی' : 'مبلغی' }}
                        </span>
                    </td>
                    <td>
                        @if($discount->discount_type == 'percent')
                            <span class="badge bg-light text-info">{{ $discount->value }}%</span>
                        @else
                            <span class="badge bg-light text-success">{{ number_format($discount->value) }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-light">{{ $discount->usage_limit }}</span>
                    </td>
                    <td>
                        <span class="badge bg-light">{{ $discount->times_used }}</span>
                    </td>
                    <td>
                        <span class="badge bg-light">
                            {{-- برای شمسی کردن تاریخ از JalaliHelper استفاده کن یا همون Carbon --}}
                            @if($discount->valid_from)
                                {{ \App\Helpers\JalaliHelper::toJalali($discount->valid_from) }}
                            @else
                                -
                            @endif
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-light">
                            @if($discount->valid_until)
                                {{ \App\Helpers\JalaliHelper::toJalali($discount->valid_until) }}
                            @else
                                -
                            @endif
                        </span>
                    </td>
                    <td>
                        @if($discount->is_active)
                            <span class="badge bg-success">فعال</span>
                        @else
                            <span class="badge bg-danger">غیرفعال</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex gap-2 justify-content-center">
                            <button class="btn btn-sm btn-outline-primary rounded-circle edit-discount-btn" title="ویرایش" data-id="{{ $discount->id }}">
                                <i class="fa fa-pen"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger rounded-circle delete-discount-btn" title="حذف" data-id="{{ $discount->id }}">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

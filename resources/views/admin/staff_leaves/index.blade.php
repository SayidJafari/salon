<!-- resources/views/admin/staff_leaves/index.blade.php -->
@extends('layouts.app')

@section('content')


<div class="card shadow-sm border-0 rounded-4 p-4" style="max-width:1150px;margin:auto;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0" style="color:#444;">
            <i class="fa fa-calendar-alt fa-lg text-pink" style="color:#ff80a5;"></i>
            لیست مرخصی‌های پرسنل
        </h3>
        <a href="#" class="btn btn-lg px-4 menu-ajax"
            data-url="{{ route('admin.staff_leaves.create') }}"
            style="background:#ff80a5;color:#fff; border-radius: 18px; font-weight: bold;">
            <i class="fa fa-plus"></i> مرخصی جدید
        </a>


    </div>

    <div class="table-responsive rounded-4 shadow-sm" style="background:#f9f6fb;">
        <table class="table table-bordered align-middle modern-table text-center mb-0" style="font-size:1.04rem;border-radius:12px;overflow:hidden;">
            <thead class="table-light" style="font-size:1.07rem;">
                <tr style="background:#fde6ef;">
                    <th>#</th>
                    <th>پرسنل</th>
                    <th>نوع</th>
                    <th>تاریخ شروع</th>
                    <th>تاریخ پایان</th>
                    <th>ساعت شروع</th>
                    <th>ساعت پایان</th>
                    <th>وضعیت</th>
                    <th>توضیحات</th>
                    <th>تایید/رد</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leaves as $i => $leave)
                <tr>
                    <td class="text-muted fw-bold">{{ ($leaves->currentPage() - 1) * $leaves->perPage() + $i + 1 }}</td>
                    <td>
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <span class="avatar-circle bg-pink text-white fw-bold">{{ mb_substr($leave->staff->full_name ?? '-', 0, 1) }}</span>
                            <span>{{ $leave->staff->full_name ?? '-' }}</span>
                        </div>
                    </td>
                    <td>
                        <span class="badge {{ $leave->leave_type=='ساعتی'?'bg-info':'bg-primary' }} px-3">{{ $leave->leave_type }}</span>
                    </td>
                    <td>{{ jdate($leave->start_date)->format('Y/m/d') }}</td>
                    <td>{{ jdate($leave->end_date)->format('Y/m/d') }}</td>
                    <td>{{ $leave->start_time ? \Illuminate\Support\Str::limit($leave->start_time,5,'') : '-' }}</td>
                    <td>{{ $leave->end_time ? \Illuminate\Support\Str::limit($leave->end_time,5,'') : '-' }}</td>
                    <td>
                        @if($leave->status == 'approved')
                        <span class="badge bg-success">تایید شده</span>
                        @elseif($leave->status == 'rejected')
                        <span class="badge bg-danger">رد شده</span>
                        @else
                        <span class="badge bg-warning text-dark">در انتظار تایید</span>
                        @endif
                    </td>

                    <td>{{ $leave->description ?: '-' }}</td>

                    <td>
                        <button type="button" class="btn btn-outline-success btn-sm rounded-circle btn-approve" data-id="{{ $leave->id }}" title="تایید">
                            <i class="fa fa-check"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm rounded-circle btn-reject" data-id="{{ $leave->id }}" title="رد">
                            <i class="fa fa-times"></i>
                        </button>
                    </td>

                    <td>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="#" class="btn btn-sm btn-outline-info rounded-circle menu-ajax"
                                data-url="{{ route('admin.staff_leaves.edit', $leave->id) }}" title="ویرایش">
                                <i class="fa fa-pen"></i>
                            </a>

                            <form method="POST" action="{{ route('admin.staff_leaves.destroy', $leave->id) }}" style="display:inline;" onsubmit="return confirm('آیا از حذف اطمینان دارید؟')">
                                @csrf @method('DELETE')
<!-- فقط دکمه، بدون فرم -->
<button type="button"
        class="btn btn-sm btn-outline-danger rounded-circle btn-delete-leave"
        data-id="{{ $leave->id }}"
        title="حذف">
    <i class="fa fa-trash"></i>
</button>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">موردی ثبت نشده است.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="my-3 px-1">
            {!! $leaves->links() !!}
        </div>
    </div>
</div>

<style>
    .avatar-circle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        background: #ffb3c9 !important;
        color: #fff !important;
        font-weight: bold;
        box-shadow: 0 0 3px #efefef;
    }

    .modern-table th,
    .modern-table td {
        vertical-align: middle !important;
        text-align: center;
    }

    .modern-table tbody tr {
        border-bottom: 1.5px solid #f0c7d5;
        transition: background 0.13s;
    }

    .modern-table tbody tr:hover {
        background: #fff2f7;
    }

    .btn-pink,
    .bg-pink {
        background: #ff80a5 !important;
        color: #fff !important;
    }
</style>


@endsection
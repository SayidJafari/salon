<!-- resources/views/admin/staff_leaves/create.blade.php -->

@extends('layouts.app')
@section('content')

<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">


<div class="container" style="max-width:620px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" style="color:#444">
            <i class="fa fa-calendar-plus"></i> ثبت مرخصی جدید پرسنل
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

    <form id="staffLeaveForm" method="POST" action="{{ route('admin.staff_leaves.store') }}" class="modern-form shadow-sm p-4 rounded-4">
        @csrf

        <div class="mb-3">
            <label class="form-label fw-bold">
                <i class="fa fa-user-tie"></i> انتخاب پرسنل
            </label>
            <select name="staff_id" class="form-control" required>
                <option value="">انتخاب پرسنل...</option>
                @foreach($staff as $s)
                    <option value="{{ $s->id }}" @selected(old('staff_id')==$s->id)>{{ $s->full_name }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">
                <i class="fa fa-list"></i> نوع مرخصی
            </label>
            <select name="leave_type" id="leave_type" class="form-control" required>
                <option value="روزانه" @selected(old('leave_type')=='روزانه')>روزانه</option>
                <option value="ساعتی" @selected(old('leave_type')=='ساعتی')>ساعتی</option>
            </select>
        </div>

        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label fw-bold">
                    <i class="fa fa-calendar"></i> تاریخ شروع
                </label>
                <input type="text" name="start_date" id="start_date" class="form-control datepicker"
                    autocomplete="off" readonly style="background:#fff;cursor:pointer;" required value="{{ old('start_date') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">
                    <i class="fa fa-calendar"></i> تاریخ پایان
                </label>
                <input type="text" name="end_date" id="end_date" class="form-control datepicker"
                    autocomplete="off" readonly style="background:#fff;cursor:pointer;" required value="{{ old('end_date') }}">
            </div>
        </div>

        <div class="row g-2 mt-2 field-time" style="display:none;">
            <div class="col-md-6">
                <label class="form-label fw-bold">
                    <i class="fa fa-clock"></i> ساعت شروع
                </label>
                <input type="time" name="start_time" class="form-control" value="{{ old('start_time') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">
                    <i class="fa fa-clock"></i> ساعت پایان
                </label>
                <input type="time" name="end_time" class="form-control" value="{{ old('end_time') }}">
            </div>
        </div>

        <div class="mb-3 mt-2">
            <label class="form-label fw-bold">
                <i class="fa fa-comment-alt"></i> توضیحات (اختیاری)
            </label>
            <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
        </div>

        <div class="d-flex gap-2 mt-4 justify-content-center">
            <button type="submit" class="btn px-4" style="background:#ff80a5;color:#fff;">
                <i class="fa fa-check"></i> ثبت مرخصی
            </button>
            <a href="{{ route('admin.staff_leaves.index') }}" class="btn btn-outline-secondary px-4">
                <i class="fa fa-arrow-right"></i> انصراف
            </a>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<!-- توجه: حتماً فایل جاوااسکریپت زیر باید قبل از این فراخوانی شده باشد -->
<script>
    if (typeof window.initStaffLeaveForm === "function") window.initStaffLeaveForm();
</script>
@endpush

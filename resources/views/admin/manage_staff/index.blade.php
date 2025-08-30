<!-- resources/views/admin/manage_staff/index.blade.php -->

@extends('layouts.app')

@section('content')

<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">

<style>
    /* خواناتر شدن نام‌کاربری */
    .username-badge {
        background: #343a40 !important; /* bg-dark */
        color: #fff !important;
        padding: .35rem .6rem;
        border-radius: .5rem;
        font-weight: 600;
    }
    .code-badge {
        font-weight: 700;
        letter-spacing: .5px;
    }
</style>

<div id="staff-list-message"></div>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold mb-0">
            <i class="fa fa-users"></i>
            لیست پرسنل سالن
        </h2>
        <a href="#" class="btn btn-success menu-ajax" data-url="{{ route('admin.staff.create') }}">
            <i class="fa fa-plus"></i> افزودن پرسنل
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($staff->isEmpty())
        <div class="alert alert-warning">هیچ پرسنلی ثبت نشده است.</div>
    @else
        <div class="table-responsive shadow rounded-4 bg-white staff-modern-table">
            <table class="table table-bordered table-hover align-middle modern-table text-center">
                <thead class="table-light">
                    <tr>
                        <th>شناسه</th>
                        <th>نام کاربری</th>
                        <th>نام کامل</th>
                        <th>کد ملی</th>
                        <th>شماره تماس</th>
                        <th>استخدام</th>
                        <th style="width: 90px;">وضعیت</th>
                        <th style="width: 120px;">کد شما</th>
                        <th style="width: 120px;">کد معرف شما</th>
                        <th>بانک</th>          <!-- ← ستون جدید -->
                        <th>POS</th>
                        <th>شماره حساب</th>
                        <th>شماره کارت</th>
                        <th>مهارت‌ها</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($staff as $person)
                        <tr>
                            <td class="fw-bold text-muted">{{ $person->id }}</td>

                            <td>
                                <span class="badge username-badge">{{ $person->staffusername ?? '—' }}</span>
                            </td>

                            <td>
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <span class="avatar-circle">{{ mb_substr($person->full_name, 0, 1) }}</span>
                                    <span>{{ $person->full_name }}</span>
                                </div>
                            </td>

                            <td>{{ $person->national_code }}</td>
                            <td>{{ $person->phone }}</td>
                            <td>{{ \App\Helpers\JalaliHelper::toJalali($person->hire_date) }}</td>

                            <td>
                                <div class="d-flex flex-column align-items-center">
                                    @if($person->is_active)
                                        <span class="badge bg-success mt-1">فعال</span>
                                    @else
                                        <span class="badge bg-danger mt-1">غیرفعال</span>
                                    @endif
                                </div>
                            </td>

                            <td>
                                <div class="d-flex flex-column align-items-center">
                                    <span class="badge bg-info text-white code-badge mt-1">
                                        {{ $person->referral_code ?? '—' }}
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div class="d-flex flex-column align-items-center">
                                    <span class="badge bg-light text-dark code-badge mt-1">
                                        {{ $person->referred_by ?? '—' }}
                                    </span>
                                </div>
                            </td>

                            <!-- نام بانک از bank_lists -->
                            <td>
                                @if($person->bank_title)
                                    <span class="">
                                        {{ $person->bank_title }}
                                        @if($person->bank_short_name)
                                            ({{ $person->bank_short_name }})
                                        @endif
                                    </span>
                                @else
                                    —
                                @endif
                            </td>

                            <td>{{ $person->pos_terminal ?? '—' }}</td>
                            <td>{{ $person->bank_account ?? '—' }}</td>
                            <td>{{ $person->card_number ?? '—' }}</td>

                            <!-- مهارت‌ها بدون کوئری داخل Blade -->
                            <td>
                                @php
                                    $titles = collect($skills[$person->id] ?? [])->pluck('title')->all();
                                @endphp
                                @if(count($titles))
                                    <div class="d-flex flex-wrap justify-content-center">
                                        @foreach($titles as $title)
                                            <span class="badge bg-primary m-1">{{ $title }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="badge bg-secondary">بدون مهارت</span>
                                @endif
                            </td>

                            <td>
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="#" class="btn btn-sm btn-outline-primary rounded-circle edit-staff-btn"
                                       data-url="{{ route('admin.staff.edit', $person->id) }}"
                                       title="ویرایش">
                                        <i class="fa fa-pen"></i>
                                    </a>
                                    <form class="delete-staff-form d-inline"
                                          data-action="{{ route('admin.staff.destroy', $person->id) }}">
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
document.addEventListener("DOMContentLoaded", function () {

    // دکمه ویرایش پرسنل (باز کردن فرم ایجکس)
    if (typeof ajaxEditButtons === 'function') {
        ajaxEditButtons('.edit-staff-btn');
    } else {
        // fallback ساده
        document.querySelectorAll('.edit-staff-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const url = this.getAttribute('data-url');
                if (url && typeof loadPartial === 'function') loadPartial(url);
            });
        });
    }

    // حذف پرسنل با AJAX
    if (typeof ajaxDeleteForm === 'function') {
        ajaxDeleteForm('.delete-staff-form', 'آیا از حذف این پرسنل مطمئن هستید؟', function (data) {
            const msg = document.getElementById('staff-list-message');
            if (data.success) {
                msg.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                if (typeof loadPartial === 'function') {
                    loadPartial('{{ route("admin.staff.index") }}');
                }
            } else {
                msg.innerHTML = '<div class="alert alert-danger">' + (data.message || 'خطایی رخ داده است!') + '</div>';
            }
        });
    } else {
        // fallback دستی در صورت نبود helper
        document.querySelectorAll('.delete-staff-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!confirm('آیا از حذف این پرسنل مطمئن هستید؟')) return;

                const url = form.getAttribute('data-action');
                const formData = new FormData(form);

                let csrfToken = window.csrfToken;
                if (!csrfToken) {
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) csrfToken = meta.getAttribute('content');
                }

                fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        ...(csrfToken ? {'X-CSRF-TOKEN': csrfToken} : {})
                    }
                })
                .then(r => r.json())
                .then(data => {
                    const msg = document.getElementById('staff-list-message');
                    if (data.success) {
                        msg.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                        if (typeof loadPartial === 'function') loadPartial('{{ route("admin.staff.index") }}');
                    } else {
                        msg.innerHTML = '<div class="alert alert-danger">' + (data.message || 'خطایی رخ داده است!') + '</div>';
                    }
                })
                .catch(() => {
                    document.getElementById('staff-list-message').innerHTML =
                        '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>';
                });
            });
        });
    }
});
</script>

@endsection

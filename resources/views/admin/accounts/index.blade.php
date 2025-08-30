{{-- resources/views/admin/accounts/index.blade.php --}}
@extends('layouts.app')

@section('content')

<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">

<div id="account-list-message"></div>

<div class="container">

    {{-- هدر + دکمه افزودن (دو گزینه: حساب بانکی / صندوق) --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold mb-0" style="color:#444">
            <i class="fa fa-university"></i> لیست حساب‌های مالی سالن
        </h2>

        <div class="btn-group">
            <a href="{{ route('admin.accounts.create', ['kind' => 'bank']) }}"
                class="btn btn-success menu-ajax"
                data-url="{{ route('admin.accounts.create', ['kind' => 'bank']) }}">
                <i class="fa fa-plus"></i> افزودن حساب بانکی
            </a>
            <button type="button" class="btn btn-success dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="visually-hidden">Toggle Dropdown</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a href="{{ route('admin.accounts.create', ['kind' => 'cashbox']) }}"
                        class="dropdown-item menu-ajax"
                        data-url="{{ route('admin.accounts.create', ['kind' => 'cashbox']) }}">
                        <i class="fa fa-cash-register"></i> افزودن صندوق
                    </a>
                </li>
            </ul>
        </div>
    </div>

    @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- جدول: حساب‌های بانکی --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-header bg-light fw-bold">
            <i class="fa fa-piggy-bank text-primary"></i> حساب‌های بانکی
        </div>
        <div class="card-body p-0">
            @if ($accounts->isEmpty())
            <div class="p-3">هیچ حساب بانکی ثبت نشده است.</div>
            @else
            <div class="table-responsive staff-modern-table">
                <table class="table table-bordered table-hover align-middle modern-table text-center mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>شناسه</th>
                            <th>نام حساب</th>
                            <th>شماره حساب</th>
                            <th>سریال کارتخوان</th>
                            <th>شماره شبا</th>
                            <th>شماره کارت</th>
                            <th>صاحب حساب</th>
                            <th>فعال؟</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($accounts as $acc)
                        <tr>
                            <td class="fw-bold text-muted">{{ $acc->id }}</td>

                            <td>
                                <div class="d-flex align-items-center gap-2 justify-content-center">
                                    <span class="avatar-circle">{{ mb_substr($acc->title, 0, 1) }}</span>
                                    <span>{{ $acc->title }}</span>
                                </div>
                            </td>

                            <td>{{ $acc->account_number }}</td>
                            <td>{{ $acc->pos_terminal }}</td>
                            <td>{{ $acc->shaba_number }}</td>
                            <td>{{ $acc->card_number }}</td>
                            <td>{{ $acc->owner_name }}</td>
                            <td>
                                @if ($acc->is_active)
                                <span class="badge bg-success">فعال</span>
                                @else
                                <span class="badge bg-danger">غیرفعال</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="{{ route('admin.accounts.edit', ['account' => $acc->id, 'kind' => 'bank']) }}"
                                        class="btn btn-sm btn-outline-primary rounded-circle edit-account-btn menu-ajax"
                                        data-url="{{ route('admin.accounts.edit', ['account' => $acc->id, 'kind' => 'bank']) }}"
                                        title="ویرایش">
                                        <i class="fa fa-pen"></i>
                                    </a>
                                    <form class="delete-account-form d-inline"
                                        data-action="{{ route('admin.accounts.destroy', ['account' => $acc->id, 'kind' => 'bank']) }}">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="kind" value="bank">
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
    </div>

    {{-- جدول: صندوق‌ها --}}
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-light fw-bold">
            <i class="fa fa-cash-register text-success"></i> صندوق‌ها
        </div>
        <div class="card-body p-0">
            @if (empty($cashBoxes) || $cashBoxes->isEmpty())
            <div class="p-3">هیچ صندوقی ثبت نشده است.</div>
            @else
            <div class="table-responsive staff-modern-table">
                <table class="table table-bordered table-hover align-middle modern-table text-center mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>شناسه</th>
                            <th>محل/توضیحات صندوق</th>
                            <th>فعال؟</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cashBoxes as $cb)
                        <tr>
                            <td class="fw-bold text-muted">{{ $cb->id }}</td>
                            <td>{{ $cb->location }}</td>
                            <td>
                                @if ($cb->is_active)
                                <span class="badge bg-success">فعال</span>
                                @else
                                <span class="badge bg-danger">غیرفعال</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="{{ route('admin.accounts.edit', ['account' => $cb->id, 'kind' => 'cashbox']) }}"
                                        class="btn btn-sm btn-outline-primary rounded-circle edit-cashbox-btn menu-ajax"
                                        data-url="{{ route('admin.accounts.edit', ['account' => $cb->id, 'kind' => 'cashbox']) }}"
                                        title="ویرایش">
                                        <i class="fa fa-pen"></i>
                                    </a>
                                    <form class="delete-cashbox-form d-inline"
                                        data-action="{{ route('admin.accounts.destroy', ['account' => $cb->id, 'kind' => 'cashbox']) }}">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="kind" value="cashbox">
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
    </div>

</div>

{{-- اسکریپت delegated فقط یکبار bind شود حتی بعد از loadPartial --}}
<script>
    (function() {
        if (window._accountsDelegatedBound) return;
        window._accountsDelegatedBound = true;

        // کلیک عمومی برای لینک/دکمه‌های ایجکس
        document.addEventListener('click', function(e) {
            const el = e.target.closest('a.menu-ajax, button.menu-ajax');
            if (!el) return;

            e.preventDefault();
            const url = el.getAttribute('data-url') || el.getAttribute('href');
            if (url && typeof loadPartial === 'function') {
                loadPartial(url);
            }
        });

        // حذف‌های ایجکس (بانک + صندوق) با delegation
        document.addEventListener('submit', function(e) {
            const form = e.target.closest('.delete-account-form, .delete-cashbox-form');
            if (!form) return;

            e.preventDefault();
            e.stopPropagation();
            if (!confirm('آیا از حذف این مورد مطمئن هستید؟')) return;

            const url = form.getAttribute('data-action');
            const formData = new FormData(form);

            // CSRF
            let csrfToken = window.csrfToken;
            if (!csrfToken) {
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) csrfToken = meta.getAttribute('content');
            }

            fetch(url, {
                    method: 'POST',
                    body: formData, // شامل _method=DELETE و input hidden kind
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        ...(csrfToken ? {
                            'X-CSRF-TOKEN': csrfToken
                        } : {})
                    }
                })
                .then(r => r.json())
                .then(data => {
                    const msg = document.getElementById('account-list-message');
                    if (data && data.success) {
                        if (msg) msg.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                        if (typeof loadPartial === 'function') {
                            loadPartial('{{ route("admin.accounts.index") }}');
                        }
                    } else {
                        if (msg) msg.innerHTML = '<div class="alert alert-danger">' + ((data && data.message) || 'خطایی رخ داده است!') + '</div>';
                    }
                })
                .catch(() => {
                    const msg = document.getElementById('account-list-message');
                    if (msg) msg.innerHTML = '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>';
                });
        });
    })();
</script>
<script>
    // ...existing code...
    // کلیک عمومی برای لینک/دکمه‌های ایجکس
    document.addEventListener('click', function(e) {
        const el = e.target.closest('a.menu-ajax, button.menu-ajax');
        if (!el) return;

        e.preventDefault();
        const url = el.getAttribute('data-url') || el.getAttribute('href');
        if (url && typeof loadPartial === 'function') {
            loadPartial(url);
        }
    });
    // ...existing code...
</script>
<script>
    // ...existing code...
    // حذف‌های ایجکس (بانک + صندوق) با delegation
    document.addEventListener('submit', function(e) {
        const form = e.target.closest('.delete-account-form, .delete-cashbox-form');
        if (!form) return;

        e.preventDefault();
        if (!confirm('آیا از حذف این مورد مطمئن هستید؟')) return;

        const url = form.getAttribute('data-action');
        const formData = new FormData(form);

        // CSRF
        let csrfToken = window.csrfToken;
        if (!csrfToken) {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) csrfToken = meta.getAttribute('content');
        }

        fetch(url, {
                method: 'POST',
                body: formData, // شامل _method=DELETE و input hidden kind
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    ...(csrfToken ? {
                        'X-CSRF-TOKEN': csrfToken
                    } : {})
                }
            })
            .then(r => r.json())
            .then(data => {
                const msg = document.getElementById('account-list-message');
                if (data && data.success) {
                    if (msg) msg.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                    if (typeof loadPartial === 'function') {
                        loadPartial('{{ route("admin.accounts.index") }}');
                    }
                } else {
                    if (msg) msg.innerHTML = '<div class="alert alert-danger">' + ((data && data.message) || 'خطایی رخ داده است!') + '</div>';
                }
            })
            .catch(() => {
                const msg = document.getElementById('account-list-message');
                if (msg) msg.innerHTML = '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>';
            });
    });
    // ...existing code...
</script>

@endsection
@extends('layouts.app')

@section('content')
<!-- استایل سفارشی فقط برای فرم و ردیف خدمات -->
<style>
    /* کادر اصلی فرم */
    .container>.modern-form {
        max-width: 1850px;
        margin: 0 auto 38px auto !important;
        background: #f6f8fb;
        border-radius: 20px;
        box-shadow: 0 5px 24px #ded9f85c;
        padding: 32px 38px 24px 38px;
        transition: max-width 0.3s;
    }

    /* فاصله بین ردیف خدمات */
    #services-list>.col-12 {
        margin-bottom: 14px;
    }

    /* کارت هر خدمت شفاف و فاصله‌دار */
    #services-list .d-flex.align-items-center.justify-content-between.bg-light {
        background: #fff !important;
        border: 1.1px solid #e7eaf3;
        border-radius: 15px !important;
        padding: 16px 22px !important;
        margin-bottom: 0px;
        box-shadow: 0 2px 8px #d2daeb1a;
        min-height: 60px;
        transition: flex-direction 0.3s;
    }

    #services-list label {
        font-size: 1.04rem;
        margin-bottom: 0 !important;
    }

    #services-list .badge {
        font-size: 0.93rem;
        padding: 3px 12px 3px 12px;
    }

    #services-list .staff-select {
        border-radius: 8px;
        font-size: 1rem;
    }

    /* واکنش‌گرا کردن عرض فرم */
    @media (max-width: 1600px) {
        .container>.modern-form {
            max-width: 1200px;
        }
    }

    @media (max-width: 1200px) {
        .container>.modern-form {
            max-width: 960px;
            padding: 24px 16px 16px 16px;
        }
    }

    @media (max-width: 991px) {
        .container>.modern-form {
            max-width: 100vw;
            border-radius: 14px;
            padding: 14px 5vw 10px 5vw;
        }

        #services-list>.col-12.col-md-6 {
            width: 100%;
            max-width: 100%;
            flex: 0 0 100%;
        }
    }

    @media (max-width: 767px) {
        .container>.modern-form {
            padding: 10px 2vw 9px 2vw;
        }

        #services-list .d-flex {
            flex-direction: column !important;
            gap: 12px;
        }

        #services-list label {
            font-size: 0.96rem;
        }

        #services-list .badge {
            font-size: 0.86rem;
        }
    }

    @media (max-width: 480px) {
        .container>.modern-form {
            padding: 3px 0vw 3px 0vw;
        }

        #services-list label {
            font-size: 0.89rem;
        }

        #services-list .badge {
            font-size: 0.81rem;
            padding: 2px 8px;
        }
    }
</style>

<script>
    console.log('JS Loaded!');
</script>
<link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">


<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0" style="color:#444">
            <i class="fa fa-boxes"></i> ساخت پک جدید
        </h2>
        <a href="{{ route('admin.package-categories.index') }}" class="btn btn-outline-secondary staff-back-btn" data-url="{{ route('admin.package-categories.index') }}">
            <i class="fa fa-arrow-right"></i> لغو و بازگشت
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

    <form id="package-categoriesCreateForm" action="{{ route('admin.package-categories.store') }}" method="post" autocomplete="off" enctype="multipart/form-data" class="modern-form shadow-sm mb-4">
        @csrf

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-cube"></i> نام پک
            </label>
            <input type="text" class="form-control" name="name" required placeholder="مثلاً: پک اقتصادی" value="{{ old('name') }}">
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="fa fa-align-left"></i> توضیحات
            </label>
            <textarea class="form-control" name="description" rows="2" placeholder="توضیحات پک">{{ old('description') }}</textarea>
        </div>
        <!-- داخل فرم #package-categoriesCreateForm، مثلا بعد از توضیحات -->
        <div class="mb-3 p-3 rounded-3" style="background:#f8fafc;border:1px dashed #dbe3ea">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch"
                    id="pkg_ref_comm_enabled" name="ref_comm_enabled" value="1"
                    {{ old('ref_comm_enabled') ? 'checked' : '' }}>
                <label class="form-check-label" for="pkg_ref_comm_enabled">فعال‌سازی کمیسیون معرف برای این پک</label>
            </div>

            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">نوع کمیسیون</label>
                    <select name="ref_comm_type" id="pkg_ref_comm_type" class="form-control">
                        <option value="">— انتخاب کنید —</option>
                        <option value="percent" {{ old('ref_comm_type')==='percent'?'selected':'' }}>درصدی</option>
                        <option value="amount" {{ old('ref_comm_type')==='amount'?'selected':''  }}>مبلغ ثابت</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">مقدار</label>
                    <input type="number" step="0.01" name="ref_comm_value" id="pkg_ref_comm_value" class="form-control"
                        value="{{ old('ref_comm_value') }}"
                        placeholder="مثلاً 10 برای درصد، یا 50000 برای مبلغ ثابت">
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">
                        اگر «درصدی» باشد، مقدار درصد از جمع ردیف‌های پک محاسبه می‌شود. اگر «مبلغ ثابت» باشد، مقدار ثابت برای کل پک است.
                    </small>
                </div>
            </div>
        </div>

        <hr>
        <label class="form-label fw-bold mb-2">
            <i class="fa fa-tasks"></i> انتخاب خدمات پک <span class="text-danger">*</span>
        </label>
        <div class="row" id="services-list">
            @foreach($services as $service)
            <div class="col-12 col-md-6 mb-2">
                <div class="d-flex align-items-center justify-content-between bg-light rounded-3 p-2">
                    <label class="d-flex align-items-center gap-2 mb-0" style="font-weight: 500;">
                        <input type="checkbox" name="services[]" value="{{ $service->id }}" data-price="{{ $service->price }}">
                        <span>{{ $service->title }}</span>
                        <span class="badge bg-light text-dark ms-1">{{ number_format($service->price) }} تومان</span>
                    </label>
                    <div style="min-width:150px;">
                        <select name="staffs[{{ $service->id }}]" class="form-select form-select-sm staff-select" disabled>
                            <option value="">انتخاب پرسنل</option>
                            @foreach($service->specialist_staffs as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mb-3 mt-4">
            <label class="fw-bold">جمع قیمت خدمات انتخاب شده: <span id="total-services-price" class="text-primary">0</span> تومان</label>
            <br>
            <label class="fw-bold mt-2">قیمت کل پک (با تخفیف):</label>
            <input type="number" class="form-control w-50" name="price" id="package-price-input" required step="0.01" value="{{ old('price') }}">
        </div>

        <div class="mb-3">
            <label for="image" class="form-label">
                <i class="fa fa-image"></i> عکس کاور پک:
            </label>
            <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png">
            @if(isset($editPackage) && $editPackage->image)
            <img src="{{ asset('uploads/packages/' . $editPackage->image) }}" style="max-width:80px;margin-top:10px;" class="rounded shadow-sm border">
            @endif
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
            <label class="form-check-label fw-bold" for="is_active">
                <i class="fa fa-toggle-on text-success"></i> فعال باشد
            </label>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-success px-4 fw-bold">
                <i class="fa fa-save"></i> ذخیره پک
            </button>
            <a href="{{ route('admin.package-categories.index') }}" class="btn btn-outline-secondary staff-back-btn" data-url="{{ route('admin.package-categories.index') }}">
                <i class="fa fa-arrow-right"></i> لغو و بازگشت
            </a>
        </div>
    </form>

    <!-- لیست پک‌ها -->
    <h4 class="fw-bold mb-3 mt-5"><i class="fa fa-list"></i> لیست پک‌ها</h4>
    <div class="card shadow rounded-4">
        <div class="card-body">
            @if(session('success'))
            <div class="alert alert-success mb-4">{{ session('success') }}</div>
            @endif
            <div class="table-responsive shadow rounded-4 bg-white staff-modern-table">
                <table class="table table-borderless table-hover align-middle modern-table">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>نام</th>
                            <th>جمع خدمات</th>
                            <th>قیمت پک</th>
                            <th>وضعیت</th>
                            <th>کمیسیون (فعال؟)</th>
                            <th>نوع/مقدار</th>

                            <th>خدمات</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($packages as $package)
                        @php
                        $total_price = 0;
                        foreach($package->services as $srv) {
                        $total_price += ($srv->price * $srv->pivot->quantity);
                        }
                        @endphp
                        <tr>
                            <td class="fw-bold text-muted">{{ $package->id }}</td>
                            <td>{{ $package->name }}</td>
                            <td>
                                <span class="badge bg-info">{{ number_format($total_price) }}</span>
                            </td>
                            <td>
                                <span class="badge bg-success">{{ number_format($package->price) }}</span>
                            </td>
                            <td>
                                @if($package->is_active)
                                <span class="badge bg-success">فعال</span>
                                @else
                                <span class="badge bg-danger">غیرفعال</span>
                                @endif
                            </td>

                            <td>
  @if($package->referrer_enabled)
    <span class="badge bg-success">فعال</span>
  @else
    <span class="badge bg-secondary">غیرفعال</span>
  @endif
</td>
<td>
  @if($package->referrer_enabled)
    @if(($package->referrer_commission_type ?? 'percent') === 'percent')
      {{ number_format($package->referrer_commission_value, 2) }}%
    @else
      {{ number_format($package->referrer_commission_value) }} تومان
    @endif
  @else
    -
  @endif
</td>

                            <td>
                                @foreach($package->services as $srv)
                                <span class="badge bg-light text-dark m-1">{{ $srv->title }} × {{ $srv->pivot->quantity }}</span>
                                @endforeach
                            </td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="{{ route('admin.package-categories.edit', $package) }}"
                                        class="btn btn-sm btn-warning rounded-3 fw-bold edit-package-category-btn" title="ویرایش">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    <form action="{{ route('admin.package-categories.destroy', $package) }}" method="POST" style="display:inline-block" class="delete-package-category-form">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger rounded-3 fw-bold" onclick="return confirm('حذف شود؟')">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">هیچ پکی وجود ندارد.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center">
                {{ $packages->links() ?? '' }}
            </div>
        </div>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        ajaxFormSubmit('package-categoriesCreateForm');
    });
</script>
<script>
    (function() {
        const en = document.getElementById('pkg_ref_comm_enabled');
        const ty = document.getElementById('pkg_ref_comm_type');
        const va = document.getElementById('pkg_ref_comm_value');

        function toggle() {
            const on = en && en.checked;
            if (ty) ty.disabled = !on;
            if (va) va.disabled = !on;
        }
        if (en) {
            en.addEventListener('change', toggle);
            toggle();
        }
    })();
</script>

@endsection
<!--resources/views/layouts/admin.blade.php -->

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'پنل مدیریت')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- فقط نسخه rtl بوت‌استرپ رو لود کن -->
    <link rel="stylesheet" href="{{ asset('css/bootstrap.rtl.min.css') }}">
    <!-- لود Font Awesome برای آیکون‌های منو (با SRI امن‌تر شده) -->
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <!-- استایل اصلی اپ (RTL) -->
    <link rel="stylesheet" href="{{ asset('css/app-rtl.css') }}">
    <!-- استایل صفحات اعتبارسنجی (RTL) -->
    <link rel="stylesheet" href="{{ asset('css/auth-rtl.css') }}">
    <!-- استایل‌های سفارشی پروژه (RTL) -->
    <link rel="stylesheet" href="{{ asset('css/custom-rtl.css') }}">
    <link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">
    <link rel="stylesheet" href="{{ asset('js/libs/persian-datepicker.min.css') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Nunito:400,700">

    <!-- پیشنهاد امنیتی (CSP) -->
    <meta http-equiv="Content-Security-Policy" content="
  default-src 'self';
  img-src 'self' data:;
  style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;
  font-src  'self' data: https://fonts.gstatic.com;
  script-src 'self' 'unsafe-inline';
  connect-src 'self';
">

</head>

<body style="background:#E8E8E8">
    @php
    $admin = Auth::guard('admin')->user();
    $adminName = $admin ? ($admin->fullname ?? $admin->adminusername ?? '---') : '---';
    @endphp

    <!-- هدر بالای پنل مدیریت -->
    <header style="background: #f7496cff; color: #fff; height: 55px; display: flex; align-items: center; justify-content: space-between; padding: 0 20px;">
        @php
        $admin = Auth::guard('admin')->user();
        $adminName = $admin ? ($admin->fullname ?? $admin->adminusername ?? '---') : '---';
        @endphp

        <div style="display: flex; align-items: center;">
            <span style="font-size: 1.5rem; font-weight: bold; margin-left: 15px;">
                <i class="fa fa-user"></i>
                رباب
            </span>
            <!-- دکمه همبرگری برای باز/بستن سایدبار -->
            <button class="btn btn-link" id="toggleSidebar" style="color:#fff;font-size:22px; margin-right:8px;">
                <i class="fa fa-bars"></i>
            </button>
        </div>
        <div style="display: flex; align-items: center; gap: 22px;">
            <!-- آیکون اعلان با شمارنده -->
            <a href="#" style="color:#ff8566; position: relative;">
                <i class="fa fa-bell"></i>
                <span style="position: absolute; top: -10px; right: -8px; background: orange; color: #fff; border-radius: 50%; font-size: 12px; padding: 1px 6px;">7</span>
            </a>
            <!-- آیکون پیام -->
            <a href="#" style="color:#85d2f4;"><i class="fa fa-envelope"></i></a>
            <!-- منوی پروفایل -->
            <div class="dropdown profile-dropdown" style="display:inline-block;">
                <a href="#" class="dropdown-toggle d-flex align-items-center justify-content-between flex-row" data-bs-toggle="dropdown" aria-expanded="false" style="color:#fff; min-width: 160px;">
                    <span style="font-weight:bold; margin-left: 50px;"> مدیر سرکار خانم </span>
                    <i class="fa fa-caret-down" style="margin-left:5px;"></i>
                    <img src="{{ asset('img/sample/no_avatar.jpg') }}" alt="پروفایل" style="width:32px;height:32px;border-radius:50%;">
                </a>
                <ul class="dropdown-menu">
                    <li class="text-center p-3" style="background:#338ccc;color:#fff;border-radius:6px 6px 0 0;">
                        <img src="{{ asset('img/sample/no_avatar.jpg') }}" alt="پروفایل" style="width:60px;height:60px;border-radius:50%;border:2px solid #fff;">
                        <div class="mt-2" style="font-size:11px;">{{ $adminName }}</div>
                    </li>
                    <li><a class="dropdown-item" href="#"><i class="fa fa-user"></i> پروفایل من</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fa fa-cog"></i> تنظیمات حساب</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fa fa-lock"></i> قفل</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger" style="width:100%;text-align:right">
                                <i class="fa fa-sign-out"></i> خروج
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <div class="d-flex">
        <!-- سایدبار راست -->
        <nav id="sidebar" style="width:220px;min-height:100vh;background:#212631;color:#fff; overflow-y: auto;">
            <div class="p-3" style="font-weight:bold;">سیتم مدیریت رباب</div>

            <!-- کلاس sidebar-menu برای اعمال استایل‌های جدید -->
            <ul class="nav flex-column sidebar-menu" style="text-align:right;padding-right:8px;">
                <!-- داشبورد -->
                <li class="nav-item mb-2">
                    <a href="#" id="menu-dashboard" class="nav-link text-white d-flex align-items-center justify-content-between">
                        <span>داشبورد</span>
                        <i class="fa fa-home"></i>
                    </a>
                </li>

                <!-- ثبت‌نام‌ها -->
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center justify-content-between {{ request()->is('admins/create') || request()->is('staff/create') || request()->is('customers/create') ? 'active' : '' }}" data-bs-toggle="collapse" href="#signupMenu" role="button" aria-expanded="false" aria-controls="signupMenu">
                        <span>ثبت‌نام ‌کاربران</span>
                        <i class="fa fa-angle-down"></i>
                    </a>
                    <div class="collapse" id="signupMenu">
                        <ul class="nav flex-column pe-3">
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/admins/create"><i class="fa fa-user-shield"></i><span>مدیران</span></a></li>
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/staff/create"><i class="fa fa-user-tie"></i><span>پرسنل</span></a></li>
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/customers/create"><i class="fa fa-user"></i><span>مشتری</span></a></li>
                        </ul>
                    </div>
                </li>

                <!-- سوابق کاربران -->
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center justify-content-between" data-bs-toggle="collapse" href="#viewSignupMenu" role="button" aria-expanded="false" aria-controls="viewSignupMenu">
                        <span>سوابق کاربران</span>
                        <i class="fa fa-angle-down"></i>
                    </a>
                    <div class="collapse" id="viewSignupMenu">
                        <ul class="nav flex-column pe-3">
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/admins/"><i class="fa fa-user-shield"></i><span>مدیران</span></a></li>
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/staff/"><i class="fa fa-user-tie"></i><span>پرسنل</span></a></li>
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/customers/"><i class="fa fa-user"></i><span>مشتریان</span></a></li>
                        </ul>
                    </div>
                </li>

                <!-- خدمات سالون -->
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center justify-content-between" data-bs-toggle="collapse" href="#servicesMenu" role="button" aria-expanded="false" aria-controls="servicesMenu">
                        <span>خدمات سالون</span>
                        <i class="fa fa-angle-down"></i>
                    </a>
                    <div class="collapse" id="servicesMenu">
                        <ul class="nav flex-column pe-3">
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/service-categories/create"><i class="fa fa-layer-group"></i><span>دسته بندی</span></a></li>
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/service-types/create"><i class="fa fa-cut"></i><span>خدمات</span></a></li>
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/package-categories"><i class="fa fa-box"></i><span>پکیج ها</span></a></li>
                        </ul>
                    </div>
                </li>

                <!-- امور مالی -->
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center justify-content-between" data-bs-toggle="collapse" href="#financeMenu" role="button" aria-expanded="false" aria-controls="financeMenu">
                        <span>امور مالی</span>
                        <i class="fa fa-angle-down"></i>
                    </a>
                    <div class="collapse" id="financeMenu">
                        <ul class="nav flex-column pe-3">
                            <li class="nav-item mb-1">
                                <a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax"
                                    data-url="/admin/invoices">
                                    <i class="fa fa-list"></i><span>لیست فاکتورها</span>
                                </a>
                            </li>
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/discount-codes/create"><i class="fa fa-gift"></i><span>کد تخفیف</span></a></li>

                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/staff-commissions"><i class="fa fa-percent"></i><span>کمسیون پرسنل</span></a></li>

                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/accounts"><i class="fa fa-university"></i><span>حساب‌های بانکی</span></a></li>
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/received-checks"><i class="fa fa-money-check"></i><span>چک‌های دریافتی</span></a></li>
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/cheque-books"><i class="fa fa-copy"></i><span>دسته‌چک‌ها</span></a></li>
                        </ul>
                    </div>
                </li>

                <!-- مدیریت سالن -->
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center justify-content-between"
                        data-bs-toggle="collapse" href="#salonManagementMenu" role="button"
                        aria-expanded="false" aria-controls="salonManagementMenu">
                        <span>مدیریت سالن</span>
                        <i class="fa fa-angle-down"></i>
                    </a>
                    <div class="collapse" id="salonManagementMenu">
                        <ul class="nav flex-column pe-3">
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/staff_leaves"><i class="fa fa-calendar-alt"></i><span>مرخصی پرسنل</span></a></li>
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/activity_logs"><i class="fa fa-file-invoice"></i><span>لاگ فعالیت‌ها</span></a></li>
                        </ul>
                    </div>
                </li>

                <!-- گزارشات (منوی جدید) -->
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center justify-content-between" data-bs-toggle="collapse" href="#reportsMenu" role="button" aria-expanded="false" aria-controls="reportsMenu">
                        <span>گزارشات</span>
                        <i class="fa fa-angle-down"></i>
                    </a>
                    <div class="collapse" id="reportsMenu">
                        <ul class="nav flex-column pe-3">
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/reports/sales"><i class="fa fa-chart-line"></i><span>گزارش فروش</span></a></li>
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/reports/finance"><i class="fa fa-file-invoice-dollar"></i><span>گزارش مالی</span></a></li>
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2 menu-ajax" data-url="/admin/reports/staff"><i class="fa fa-users"></i><span>گزارش پرسنل</span></a></li>
                        </ul>
                    </div>
                </li>

                <!-- پشتیبانی -->
                <li class="nav-item mb-2">
                    <a class="nav-link text-white d-flex align-items-center justify-content-between" data-bs-toggle="collapse" href="#supportMenu" role="button" aria-expanded="false" aria-controls="supportMenu">
                        <span>پشتیبانی</span>
                        <i class="fa fa-angle-down"></i>
                    </a>
                    <div class="collapse" id="supportMenu">
                        <ul class="nav flex-column pe-3">
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2"><i class="fa fa-ticket-alt"></i><span>تیکت‌ها</span></a></li>
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2"><i class="fa fa-envelope"></i><span>ایمیل</span></a></li>
                            <li class="nav-item mb-1"><a href="#" class="nav-link text-white d-flex align-items-center gap-2"><i class="fa fa-comments"></i><span>بررسی نظرات</span></a></li>
                        </ul>
                    </div>
                </li>
            </ul>
        </nav>

        <!-- بدنه اصلی -->
        <main class="flex-grow-1 p-4" style="background:#f3f6fa;">
            @yield('dashboard-cards')
            <div id="dynamic-content">
                @yield('content')
            </div>
        </main>
            <!-- فوتر جدید -->

    </div>

    <script src="{{ asset('js/libs/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/libs/jquery.min.js') }}"></script>
    <script src="{{ asset('js/libs/persian-date.min.js') }}"></script>
    <script src="{{ asset('js/libs/persian-datepicker.min.js') }}"></script>

    <script>
        // اسکریپت سایدبار (نمایش/مخفی)
        document.getElementById('toggleSidebar').onclick = function() {
            document.getElementById('sidebar').classList.toggle('hide');
        };
    </script>

    <script>
        // --- شروع: ابزارهای سفت‌وسخت امنیتی برای loadPartial ---
        // Sanitizer سبک برای حذف <script> و event handler ها و URLهای javascript:
        function sanitizeHTML(raw) {
            try {
                const parser = new DOMParser();
                const doc = parser.parseFromString(raw, 'text/html');

                // حذف تمام اسکریپت‌ها و تگ‌های خطرناک
                doc.querySelectorAll('script, iframe, object, embed, link[rel=import]').forEach(n => n.remove());

                // حذف event handler ها و جاوااسکریپت در href/src
                doc.querySelectorAll('*').forEach(el => {
                    [...el.attributes].forEach(attr => {
                        const name = attr.name.toLowerCase();
                        const val = (attr.value || '').trim().toLowerCase();
                        if (name.startsWith('on')) el.removeAttribute(attr.name);
                        if ((name === 'href' || name === 'src') && val.startsWith('javascript:')) {
                            el.removeAttribute(attr.name);
                        }
                    });
                });
                return doc.body.innerHTML;
            } catch (e) {
                return raw; // اگر خطایی شد، حداقل چیزی را برنگردانیم.
            }
        }

        // فقط آدرس‌های امن را بپذیر
        function isAllowedPartial(url) {
            const u = new URL(url, location.origin);
            if (u.origin !== location.origin) return false; // فقط same-origin

            const allowedPaths = [
                /^\/admin\/admins(\/create|\/\d+\/edit)?\/?$/,
                /^\/admin\/staff(\/create|\/\d+\/edit)?\/?$/,
                /^\/admin\/customers(\/create|\/\d+\/edit)?\/?$/,
                /^\/admin\/service-categories(\/create|\/\d+\/edit)?\/?$/,
                /^\/admin\/service-types(\/create|\/\d+\/edit)?\/?$/,
                /^\/admin\/package-categories(\/create|\/\d+\/edit)?\/?$/,
                /^\/admin\/invoices(\/(create|\d+|\d+\/edit))?\/?$/,
                /^\/admin\/discount-codes(\/create|\/\d+\/edit)?\/?$/,
                /^\/admin\/staff-commissions(\/\d+\/edit)?\/?$/,
                /^\/admin\/commission-rules(\/create|\/\d+\/edit)?\/?$/,
                /^\/admin\/accounts(\/create|\/\d+\/edit)?\/?$/,
                /^\/admin\/received-checks(\/create|\/\d+\/edit)?\/?$/,
                /^\/admin\/cheque-books(\/create|\/\d+\/edit)?\/?$/,
                /^\/admin\/staff_leaves(\/create|\/\d+\/edit)?\/?$/,
                /^\/admin\/activity_logs(\/\d+)?\/?$/,
                /^\/admin\/reports\/?(sales|finance|staff)?\/?$/,
                /^\/admin\/salon\/?$/,
            ];

            return allowedPaths.some(rx => rx.test(u.pathname));
        }


        // --- پایان: ابزارهای امنیتی ---

        document.addEventListener('DOMContentLoaded', function() {
            // همه منوهای ajax با کلاس menu-ajax
            document.querySelectorAll('.menu-ajax').forEach(function(menu) {
                menu.addEventListener('click', function(e) {
                    e.preventDefault();
                    var url = this.getAttribute('data-url');
                    if (url) {
                        loadPartial(url);
                    }
                });
            });

            // داشبورد (برگشت به کارت‌ها)
            var dashboardMenu = document.getElementById('menu-dashboard');
            if (dashboardMenu) {
                dashboardMenu.addEventListener('click', function(e) {
                    e.preventDefault();
                    var cards = document.getElementById('dashboard-cards');
                    if (cards) cards.style.display = '';
                    document.getElementById('dynamic-content').innerHTML = '';
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }
        });

        window.isPartialLoading = false;

        function loadPartial(url) {
            if (window.isPartialLoading) return;

            // امنیت: فقط آدرس‌های مجاز
            if (!isAllowedPartial(url)) {
                console.warn('Blocked partial (not allowed):', url);
                return;
            }

            window.isPartialLoading = true;

            // spinner فقط روی بخش مورد نظر اضافه کن
            let container = document.getElementById('dynamic-content');
            let spinner = document.createElement('div');
            spinner.className = 'mini-spinner';
            spinner.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">در حال بارگذاری...</span></div>';
            spinner.style.textAlign = 'center';
            container.appendChild(spinner);

            fetch(url, {
                    credentials: 'include',
                    headers: {
                        'X-CSRF-TOKEN': window.csrfToken
                    }
                })
                .then(async (response) => {
                    if (!response.ok) throw new Error('یافت نشد!');
                    const ct = (response.headers.get('content-type') || '').toLowerCase();

                    // اگر JSON بود، یعنی این URL برای partial مناسب نیست
                    if (ct.includes('application/json')) {
                        const data = await response.json().catch(() => ({}));
                        const msg = data.message || 'پاسخ JSON دریافت شد؛ این مسیر باید HTML برگرداند.';
                        throw new Error(msg);
                    }

                    return response.text();
                })
                .then(html => {
                    container.innerHTML = sanitizeHTML(html);

                    // اجرای توابع init
                    if (typeof initCustomerForm === 'function') initCustomerForm();
                    if (typeof initCustomerListEvents === 'function') initCustomerListEvents();
                    if (typeof initUserForm === 'function') initUserForm();
                    if (typeof initStaffForm === 'function') initStaffForm();
                    if (typeof initAdminsForm === 'function') initAdminsForm();
                    if (typeof initAdminListEvents === 'function') initAdminListEvents();
                    if (typeof initServiceCategoryForm === 'function') initServiceCategoryForm();
                    if (typeof initServiceTypesForm === 'function') initServiceTypesForm();
                    if (typeof initDiscountCodeForm === 'function') initDiscountCodeForm();
                    if (typeof window.initAccountForm === 'function') window.initAccountForm();
                    if (typeof initStaffCommissionForm === 'function') initStaffCommissionForm();
                    if (typeof initAccountListEvents === 'function') initAccountListEvents();
                    if (typeof initPackageCategoryForm === 'function') initPackageCategoryForm();
                    if (typeof initReceivedCheckForm === 'function') initReceivedCheckForm();
                    if (typeof initReceivedCheckEditForm === 'function') initReceivedCheckEditForm();
                    if (typeof window.initReceivedCheckCreateForm === 'function') window.initReceivedCheckCreateForm();
                    if (typeof window.initInvoiceForm === 'function') window.initInvoiceForm();
                    if (typeof window.initInvoiceCustomerAutoComplete === 'function') window.initInvoiceCustomerAutoComplete();
                    if (typeof window.initStaffLeaveForm === 'function') window.initStaffLeaveForm();
                    if (typeof initCommissionRules === 'function') initCommissionRules(); // برای صفحه لیست
                    if (typeof initCommissionRuleForm === 'function') initCommissionRuleForm(); // برای create/edit
                })
                .catch(err => {
                    container.innerHTML = '<div class="text-danger py-5 text-center">مشکلی پیش آمده!<br>' + err.message + '</div>';
                })
                .finally(() => {
                    window.isPartialLoading = false;
                });
        }

        // هندل دکمه Back/Forward مرورگر
        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.url) {
                loadPartial(event.state.url, false);
            }
        });
    </script>
    <script>
        // بعد از تعریف loadPartial
        document.getElementById('dynamic-content').addEventListener('click', function(e) {
            const a = e.target.closest('a');
            if (!a) return;
            const href = a.getAttribute('href') || a.dataset.url;
            if (!href) return;

            // فقط لینک های داخلی و مجاز را AJAX لود کن
            try {
                const abs = new URL(href, location.origin).href;
                if (isAllowedPartial(abs)) {
                    e.preventDefault();
                    loadPartial(abs);
                }
            } catch (_) {}
        });
    </script>


    <script src="{{ asset('js/admin/ajax-form.js') }}"></script>

    <script src="{{ asset('js/admin/customer-form.js') }}"></script>
    <script src="{{ asset('js/admin/admins-form.js') }}"></script>
    <script src="{{ asset('js/admin/service-category-form.js') }}"></script>
    <script src="{{ asset('js/admin/service-types-form.js') }}"></script>
    <script src="{{ asset('js/admin/discount-code-form.js') }}"></script>
    <script src="{{ asset('js/admin/account-form.js') }}"></script>
    <script src="{{ asset('js/admin/package-category-form.js') }}"></script>
    <script src="{{ asset('js/admin/received-check-form.js') }}"></script>
    <script src="{{ asset('js/admin/package-category-form.js') }}"></script>
    <script src="{{ asset('js/admin/staff-commission.js') }}"></script>
    <script src="{{ asset('js/admin/invoice-form.js') }}"></script>
    <script src="{{ asset('js/admin/staff-leave-form.js') }}"></script>

    <script>
        window.csrfToken = '{{ csrf_token() }}';
    </script>
    <!-- فوتر -->
<footer style="background:#212631; color:#fff; padding:15px 10px; font-size:14px; border-top:6px solid #f7496c; text-align:center;">
    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px;">
        <div>© ۱۴۰۳ تمامی حقوق محفوظ است | سیستم مدیریت رباب</div>
        <div>
            <a href="#" style="color:#f7496c; text-decoration:none; margin:0 8px; font-size:18px;">
                <i class="fab fa-instagram"></i>
            </a>
            <a href="#" style="color:#f7496c; text-decoration:none; margin:0 8px; font-size:18px;">
                <i class="fab fa-telegram"></i>
            </a>
            <a href="#" style="color:#f7496c; text-decoration:none; margin:0 8px; font-size:18px;">
                <i class="fab fa-whatsapp"></i>
            </a>
        </div>
    </div>
</footer>


    @stack('scripts')
</body>

</html>
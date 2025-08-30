<!-- resources/views/layouts/admin/dashboard.blade.php -->
@extends('layouts.admin')

@section('content')

<!-- استایل کارت‌ها و افکت سکه -->

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'پنل مدیریت')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- فقط نسخه rtl بوت‌استرپ رو لود کن -->
    <link rel="stylesheet" href="{{ asset('css/bootstrap.rtl.min.css') }}">
    <!-- لود Font Awesome برای آیکون‌های منو -->
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">

    <!-- استایل اصلی اپ (RTL) -->
    <link rel="stylesheet" href="{{ asset('css/app-rtl.css') }}">
    <!-- استایل صفحات اعتبارسنجی (RTL) -->
    <link rel="stylesheet" href="{{ asset('css/auth-rtl.css') }}">
    <!-- استایل‌های سفارشی پروژه (RTL) -->
    <link rel="stylesheet" href="{{ asset('css/custom-rtl.css') }}">
    <link rel="stylesheet" href="{{ asset('css/DashboardPages.css') }}">


</head>

<!-- عنوان خوش آمدگویی -->
<div class="py-3 px-3 mb-3 bg-white rounded shadow-sm" style="font-size: 1.4rem; font-weight: bold;">
    به خانه دیجیتال خود خوش آمدید

</div>

<!-- کارت‌های داشبورد را داخل این div قرار بده -->
<div id="dashboard-cards">
    <div class="row gy-4">
        <!-- کارت چشم -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="dashboard-card p-4 text-white" style="background:#4391d6;">
                <span class="icon-bg">
                    <!-- SVG آیکون چشم -->
                    <svg width="68" height="68" viewBox="0 0 70 70" fill="none">
                        <path fill="#fff" d="M29.379,14.958C26.633,12.368,22,7.6,16,7.6S5.366,12.368,2.621,14.958C1.793,15.739,1.794,16.264,2.622,17.045C5.367,19.637,10,24.4,16,24.4S26.633,19.632,29.379,17.043C30.207,16.261,30.207,15.738,29.379,14.958ZM16,21.5C12.96,21.5,10.5,19.037,10.5,16C10.5,12.965,12.96,10.5,16,10.5C19.033,10.5,21.5,12.965,21.5,16C21.5,19.037,19.033,21.5,16,21.5ZM13,16C13,17.656,14.345,19,16,19C17.656,19,19,17.656,19,16V15.975C18.68,16.3,18.242,16.5,17.75,16.5C16.785,16.5,16,15.716,16,14.75C16,14.055,16.41,13.449,16.996,13.17C16.687,13.06,16.35,13,16,13C14.345,13,13,14.345,13,16Z" transform="scale(2.1)" />
                    </svg>
                </span>
                <div class="content">
                    <div style="font-size:38px;font-weight:800;">۹,۵۰۰</div>
                    <div style="font-size:15px;margin-bottom:5px;">بازدید امروز</div>
                    <div style="font-size:13px;">هفته قبل <span style="font-weight:700">98,000</span></div>
                    <div style="font-size:13px;">ماه قبل <span style="font-weight:700">396,000</span></div>
                </div>
            </div>
        </div>
        <!-- کارت قلک با سکه -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="dashboard-card piggy-hover p-4 text-white" style="background:#e56565; overflow:visible;">
                <span class="piggy-container">
                    <!-- SVG آیکون قلک -->
                    <svg width="70" height="70" viewBox="0 0 70 70" fill="none">
                        <g>
                            <path fill="#F2B2B2" d="M13.745,18.354V19.42C13.316,19.32,13.102,19.152,13.102,18.914C13.103,18.732,13.2,18.4,13.745,18.354ZM14.251,20.514V21.662C14.9,21.599,14.948,21.275,14.948,21.074C14.948,20.865,14.715,20.678,14.251,20.514ZM18,20C18,22.209,16.209,24,14,24S10,22.209,10,20S11.791,16,14,16S18,17.791,18,20ZM15.937,20.951C15.951,20.705,15.701,20.099,15.376,19.912C15.118,19.764,14.6,19.599,14.296,19.543V18.381C14.6,18.4,14.7,18.6,14.788,18.75C14.87,18.941,15.017,19.037,15.226,19.037H15.773C15.755,18.572,15.501,17.699,14.297,17.574L14.3,17C14.3,17,13.941,16.987,13.804,16.987H13.79V17.574C12.499,17.599,12.247,18.504,12.231,18.969C12.2,19.9,12.6,20.1,13.746,20.4V21.6C13.591,21.573,13.146,21.5,13.094,20.911H12.164C12.201,21.423,12.646,22.501,13.746,22.501V23H14.246V22.5C15.5,22.5,15.9,21.6,15.937,20.951Z" transform="scale(2.1)" />
                            <path fill="#F2B2B2" d="M26,24C25.303,24.329,26.033,26.905,25.199,27.61C24.705,28.214,22.385,27.852,22.136,27.967C21.419,27.807,22.439,25.487,21.597,25.619C20.247,26.006,16.628,26.029,16.085,25.978C15.937,25.962,16.085,27.806,15.571,27.966C14.634,27.997,13.727,28.025,13.353,27.95C12.164,27.983,12.915,25.688,12.612,25.454C12.43,25.395,11.276,25.12,11.1,25.052C10.802,25.13,10.835,26.652,10.399,27.009C9.67,27.333,7.687,26.999,7.408,26.427C6.963,24.906,8.218,23.243,7.965,22.989C6.98,22.29,6.71,22.737,6.001,21.837C4.574,20.267,3.694,21.544,2.397,20.382C1.604,19.31,2.2,16.419,2.46,16.08C3.004,15.373,3.55,16.288,4,15.086C4.189,12.448,5.332,10.052,7.371,8.205C6.382,7.194,4.564,4.929,6.667,3.733C8.704,3.318,12.581,6.06,12.581,6.06S14.931,5.334,17.519,5.334C24.871,5.334,29.999,10.178,29.999,16.151C30,19.741,29.211,21.938,26,24Z" transform="scale(2.1)" />
                        </g>
                    </svg>
                    <!-- سکه‌ها -->
                    <span class="coin coin1"></span>
                    <span class="coin coin2"></span>
                    <span class="coin coin3"></span>
                </span>
                <div class="content">
                    <div style="font-size:38px;font-weight:800;">۱۰۰</div>
                    <div style="font-size:15px;margin-bottom:5px;">فروش امروز</div>
                    <div style="font-size:13px;">هفته قبل <span style="font-weight:700">920</span></div>
                    <div style="font-size:13px;">ماه قبل <span style="font-weight:700">3,929</span></div>
                </div>
            </div>
        </div>
        <!-- کارت عضویت جدید -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="dashboard-card p-4 text-white" style="background:#f4a62c;">
                <span class="icon-bg">
                    <!-- SVG عضویت جدید -->
                    <svg width="68" height="68" viewBox="0 0 70 70" fill="none">
                        <path fill="#fff" opacity="0.5" d="M26,28H6V4H26V28Z" transform="matrix(2.1875,0,0,2.1875,63.4375,0)" />
                        <path fill="#fff" opacity="1" d="M28,3.2V28.801C28,29.463,27.463,30,26.801,30H22V28H24V26H22V24H24V22H22V20H24V18H22V16H24V14H22V12H24V10H22V8H24V6H22V4H24V2H26.801C27.463,2,28,2.537,28,3.2Z" transform="matrix(2.1875,0,0,2.1875,0,0)" />
                        <path fill="#fff" opacity="1" d="M22,26H20V24H22V22H20V20H22V18H20V16H22V14H20V12H22V10H20V8H22V6H20V4H22V2H5.2C4.537,2,4,2.537,4,3.2V28.801C4,29.463,4.537,30,5.2,30H22V26Z" transform="matrix(2.1875,0,0,2.1875,0,0)" />
                    </svg>
                </span>
                <div class="content">
                    <div style="font-size:38px;font-weight:800;">۵,۰۰۰</div>
                    <div style="font-size:15px;margin-bottom:5px;">عضویت جدید</div>
                    <div style="font-size:13px;">هفته قبل <span style="font-weight:700">42,000</span></div>
                    <div style="font-size:13px;">ماه قبل <span style="font-weight:700">173,929</span></div>
                </div>
            </div>
        </div>
        <!-- کارت کاربران -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="dashboard-card p-4 text-white" style="background:#16c7be;">
                <span class="icon-bg">
                    <!-- SVG کاربران سه نفره -->
                    <svg width="68" height="68" viewBox="0 0 70 70" fill="none">
                        <!-- ... (همان کد SVG قبلی) ... -->
                        <path fill="#ffffff" stroke="none"
                            d="M8.672,21.086C8.573,20.894,8.524,20.755,8.524,20.755S8.092,21.01,8.092,20.291S8.524,20.755,8.956,17.979C8.956,17.979,10.154,17.62,9.914,14.639H9.627C9.627,14.639,9.77,14.007,9.864,13.211C9.86,12.881,9.87,12.53,9.901,12.145L9.939,11.689C9.918,11.162,9.832,10.684,9.627,10.376C8.907,9.298,8.619,8.579,7.036,8.064C5.453,7.55,6.028,7.652,4.878,7.705C3.726,7.757,2.766,8.423,2.766,8.785C2.766,8.785,2.046,8.837,1.758,9.145C1.487,9.435,1.053,10.71,1.001,11.163V11.464C1.048,12.163,1.259,14.086,1.47,14.54L1.184,14.643C0.946,17.623,2.143,17.984,2.143,17.984C2.575,20.756,3.007,19.575,3.007,20.294C3.007,21.014,2.575,20.757,2.575,20.757S2.192,21.889,1.232,22.299C1.171,22.322,1.093,22.356,1,22.395V28h5.2c.041-1.368-.054-3.135,.615-3.849,.356-.38,1.523-1.005,6.351-3.065Z"
                            opacity="1"
                            stroke-width="0"
                            transform="matrix(2.1875,0,0,2.1875,0,0)" />
                        <!-- ... بقیه pathها ... -->
                    </svg>
                </span>
                <div class="content">
                    <div style="font-size:38px;font-weight:800;">۸,۰۰۰</div>
                    <div style="font-size:15px;margin-bottom:5px;">کاربر ثبت‌شده</div>
                    <div style="font-size:13px;">هفته قبل <span style="font-weight:700">56,000</span></div>
                    <div style="font-size:13px;">ماه قبل <span style="font-weight:700">219,864</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- فرم‌ها و صفحات جانبی اینجا لود میشه -->
<div id="dynamic-content"></div>


<script>
    function loadPartial(url) {
        // کارت‌های داشبورد رو مخفی کن
        document.getElementById('dashboard-cards').style.display = 'none';

        // بخش لودینگ نشون بده (اختیاری)
        document.getElementById('dynamic-content').innerHTML = '<div class="text-center py-5">در حال بارگذاری...</div>';

        // درخواست ajax ساده
        fetch(url)
            .then(response => response.text())
            .then(html => {
                document.getElementById('dynamic-content').innerHTML = html;
            });
    }

    // وقتی یکی از دکمه‌های منو کلیک شد، فرم مربوطه لود میشه
    document.addEventListener('DOMContentLoaded', function() {
        // پرسنل
        var staffMenu = document.getElementById('menu-create-staff');
        if (staffMenu) {
            staffMenu.addEventListener('click', function(e) {
                e.preventDefault();
                loadPartial('/staff/create');
            });
        }
        // مشتری (در آینده اضافه کن!)
        // var userMenu = document.getElementById('menu-create-user');
        // if (userMenu) {
        //     userMenu.addEventListener('click', function(e) {
        //         e.preventDefault();
        //         loadPartial('/admin/users/create');
        //     });
        // }

        // داشبورد (برگشت به کارت‌ها)
        var dashboardMenu = document.getElementById('menu-dashboard');
        if (dashboardMenu) {
            dashboardMenu.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('dashboard-cards').style.display = '';
                document.getElementById('dynamic-content').innerHTML = '';
            });
        }
    });
</script>



@endsection
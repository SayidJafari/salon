// public/js/admin/initStaffForm.js
// مدیریت فرم‌های کارمندان (ثبت، ویرایش، حذف) با AJAX و نمایش پیام‌ها
// این کد به‌گونه‌ای نوشته شده که فقط یک بار روی هر فرم اجرا شود

window.initStaffForm = function () {
    console.log("staff-form.js با موفقیت لود شد!");

    // اعتبارسنجی ایمیل فقط کاراکترهای مجاز
    const emailInput = document.querySelector('input[name="email"]');
    if (emailInput) {
        emailInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^a-zA-Z0-9@._-]/g, '');
        });
    }

    // اعتبارسنجی کد معرف (فرمت و چک سرور)
    const referralInput = document.getElementById("referred_by");
    const errorSpan = document.getElementById("referred_by_error");
    if (referralInput && errorSpan) {
        referralInput.addEventListener("input", function () {
            let val = this.value.toUpperCase();
            val = val.replace(/[^A-Z0-9\-]/g, '');
            if (val.length === 1 && !['A', 'S', 'C', 'N'].includes(val[0])) val = '';
            if (val.length === 2 && val[1] !== '-') {
                val = val[0] + '-' + val[1];
            }
            let parts = val.split('-');
            if (parts.length > 2) val = parts[0] + '-' + parts[1].replace(/-/g, '');
            this.value = val;
            errorSpan.style.display = 'none';
        });

        referralInput.addEventListener("blur", function () {
            const code = this.value.trim().toUpperCase();
            if (!code) {
                errorSpan.style.display = 'none';
                document.getElementById("search-by-national-code")?.style.setProperty('display', 'none');
                return;
            }
            if (code.length < 4) return;
            fetch('/check-referral-code?code=' + code)
                .then(res => res.json())
                .then(data => {
                    if (!data.exists) {
                        errorSpan.innerText = "کد معرف یافت نشد! اگر معرف شما ثبت‌نام کرده لطفاً کد ملی او را وارد کنید.";
                        errorSpan.style.display = 'block';
                        document.getElementById("search-by-national-code")?.style.setProperty('display', 'block');
                    } else {
                        errorSpan.style.display = 'none';
                        document.getElementById("search-by-national-code")?.style.setProperty('display', 'none');
                    }
                })
                .catch(() => {
                    errorSpan.innerText = "خطا در ارتباط با سرور!";
                    errorSpan.style.display = 'block';
                });
        });
    }

    // جستجو بر اساس کد ملی معرف
    window.findReferralCodeByNationalCode = function () {
        const code = document.getElementById("referrer_national_code")?.value.trim();
        const resultSpan = document.getElementById("national-code-result");
        if (!code || code.length < 8) {
            if (resultSpan) resultSpan.innerText = "کد ملی باید حداقل 8 رقم باشد!";
            return;
        }
        fetch('/find-referral-by-national-code?national_code=' + code)
            .then(res => res.json())
            .then(data => {
                if (data.code) {
                    if (resultSpan) resultSpan.innerText = "کد معرف: " + data.code;
                    const referredByInput = document.getElementById("referred_by");
                    if (referredByInput) referredByInput.value = data.code;
                    document.getElementById("search-by-national-code")?.style.setProperty('display', 'none');
                    if (errorSpan) errorSpan.style.display = 'none';
                } else {
                    if (resultSpan) resultSpan.innerText = "کاربری با این کد ملی یافت نشد!";
                }
            });
    };

    // اعتبارسنجی شماره تماس فقط عدد
    const phoneInput = document.querySelector('input[name="phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }

    // اعتبارسنجی کد ملی فقط عدد
    const nationalCodeInput = document.querySelector('input[name="national_code"]');
    if (nationalCodeInput) {
        nationalCodeInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }

    // تبدیل تاریخ شمسی به میلادی قبل از ارسال فرم
    const form = document.getElementById('staffCreateForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            // تبدیل تاریخ شمسی به میلادی
            const jalaliInput = document.getElementById('hire_date_jalali');
            const gregorianInput = document.getElementById('hire_date');
            if (jalaliInput && gregorianInput) {
                let jalaliValue = jalaliInput.value;
                let gregorianValue = window.PersianDateHelper.toGregorian(jalaliValue);
                gregorianInput.value = gregorianValue;
            }

            // ارسال فرم با AJAX
            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(response => response.json())
                .then(data => {
                    const messageDiv = document.getElementById('staff-create-message');
                    if (data.success) {
                        if (messageDiv) messageDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                        form.reset();
                    } else if (data.errors) {
                        let errorsHtml = '<div class="alert alert-danger"><ul>';
                        Object.values(data.errors).forEach(function (msg) {
                            errorsHtml += '<li>' + msg + '</li>';
                        });
                        errorsHtml += '</ul></div>';
                        if (messageDiv) messageDiv.innerHTML = errorsHtml;
                    }
                })
                .catch(() => {
                    const messageDiv = document.getElementById('staff-create-message');
                    if (messageDiv) messageDiv.innerHTML = '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>';
                });

            return false;
        });
    }
};

document.addEventListener("DOMContentLoaded", function () {
    if (typeof initStaffForm === "function") initStaffForm();
});

// public/js/admin/admins-form.js
window.initAdminsForm = function () {

    console.log("admins-form.js با موفقیت لود شد!");

    // ---- اعتبارسنجی adminusername (مدیر) ----
    const adminUsernameInput = document.querySelector('input[name="adminusername"]');
    if (adminUsernameInput) {
        adminUsernameInput.oninput = null;
        adminUsernameInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
        });
    }

    // ---- اعتبارسنجی staffusername (پرسنل) ----
    const staffUsernameInput = document.querySelector('input[name="staffusername"]');
    if (staffUsernameInput) {
        staffUsernameInput.oninput = null;
        staffUsernameInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^a-zA-Z0-9_.]/g, '');
        });
    }

    // کد معرف (فرمت و چک سرور)
    const referralInput = document.getElementById("referred_by");
    const errorSpan = document.getElementById("referred_by_error");

    if (referralInput && errorSpan) {
        referralInput.oninput = null;
        referralInput.onblur = null;

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
            if (!code || code.length < 4) return;

            fetch('/admin/check-referral-code?code=' + code)
                .then(res => res.json())
                .then(data => {
                    if (!data.exists) {
                        errorSpan.innerText = "کد معرف یافت نشد!";
                        errorSpan.style.display = 'block';
                    } else {
                        errorSpan.style.display = 'none';
                    }
                })
                .catch(() => {
                    errorSpan.innerText = "خطا در ارتباط با سرور!";
                    errorSpan.style.display = 'block';
                });
        });
    }

    // 👇 فرم ثبت/ویرایش مدیر یا پرسنل
    var form =
        document.getElementById('adminCreateForm')
        || document.getElementById('adminEditForm')
        || document.getElementById('staffCreateForm')
        || document.getElementById('staffEditForm');
    var msgBox =
        document.getElementById('admin-create-message')
        || document.getElementById('admin-edit-message')
        || document.getElementById('staff-create-message')
        || document.getElementById('staff-edit-message');

    if (form) {
        form.onsubmit = null;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            var formData = new FormData(form);

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
                    if (data.success) {
                        if (msgBox) msgBox.innerHTML =
                            '<div class="alert alert-success">' + data.message + '</div>';
                        form.reset && form.reset();
                        // اگر ویرایش بود، لیست را رفرش کن
                        if ((form.id === 'adminEditForm' || form.id === 'staffEditForm') && typeof loadPartial === "function") {
                            loadPartial(form.id === 'adminEditForm' ? '/admin/admins' : '/admin/staff');
                        }
                    } else if (data.errors) {
                        let errorsHtml = '<div class="alert alert-danger"><ul>';
                        Object.values(data.errors).forEach(function (msg) {
                            errorsHtml += '<li>' + msg + '</li>';
                        });
                        errorsHtml += '</ul></div>';
                        if (msgBox) msgBox.innerHTML = errorsHtml;
                    }
                })
                .catch(() => {
                    if (msgBox) msgBox.innerHTML =
                        '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>';
                });

            return false;
        });
    }

    // 👇 رویدادهای لیست مدیران (ویرایش و حذف) با پاک کردن event قبلی
    document.querySelectorAll('.edit-admin-btn').forEach(function (btn) {
        btn.onclick = null;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            if (url) {
                loadPartial(url);
            }
        });
    });

    document.querySelectorAll('.delete-admin-form').forEach(function (form) {
        form.onsubmit = null;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!confirm('آیا از حذف این مدیر مطمئن هستید؟')) return;

            const formData = new FormData(this);
            const url = this.getAttribute('data-action');

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('admin-list-message').innerHTML =
                            '<div class="alert alert-success">' + data.message + '</div>';
                        loadPartial('/admin/admins');
                    } else {
                        document.getElementById('admin-list-message').innerHTML =
                            '<div class="alert alert-danger">' + (data.message || 'خطایی رخ داده است!') + '</div>';
                    }
                })
                .catch(() => {
                    document.getElementById('admin-list-message').innerHTML =
                        '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>';
                });
        });
    });

    // 👇 رویدادهای لیست پرسنل (ویرایش و حذف) مشابه ادمین (در صورت نیاز اضافه شود)
    document.querySelectorAll('.edit-staff-btn').forEach(function (btn) {
        btn.onclick = null;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            if (url) {
                loadPartial(url);
            }
        });
    });

    document.querySelectorAll('.delete-staff-form').forEach(function (form) {
        form.onsubmit = null;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!confirm('آیا از حذف این پرسنل مطمئن هستید؟')) return;

            const formData = new FormData(this);
            const url = this.getAttribute('data-action');

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('staff-list-message').innerHTML =
                            '<div class="alert alert-success">' + data.message + '</div>';
                        loadPartial('/admin/staff');
                    } else {
                        document.getElementById('staff-list-message').innerHTML =
                            '<div class="alert alert-danger">' + (data.message || 'خطایی رخ داده است!') + '</div>';
                    }
                })
                .catch(() => {
                    document.getElementById('staff-list-message').innerHTML =
                        '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>';
                });
        });
    });

    // 👇 حل مشکل نهایی (دکمه ثبت مدیر جدید)
    document.querySelectorAll('.menu-ajax').forEach(function (menu) {
        menu.onclick = null;
        menu.addEventListener('click', function (e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            if (url) {
                loadPartial(url);
            }
        });
    });

    // دکمه بازگشت/انصراف ایجکس برای فرم‌های ادمین و پرسنل
    document.querySelectorAll('.admin-back-btn, .staff-back-btn').forEach(function (btn) {
        btn.removeEventListener('click', btn._adminBackAjax);
        btn._adminBackAjax = function (e) {
            e.preventDefault();
            var url = btn.getAttribute('data-url') || btn.getAttribute('href');
            if (url && typeof loadPartial === "function") {
                loadPartial(url);
            }
        };
        btn.addEventListener('click', btn._adminBackAjax);
    });

};

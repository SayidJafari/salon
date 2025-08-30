// public/js/admin/staff-form.js

window.initStaffForm = function () {

    console.log("✅ staff-form.js لود شد!");

    const btn = document.getElementById('btn-find-referral');
    if (btn) {
        btn.onclick = null;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            findReferralCodeByNationalCode();
        });
    }

    // جلوگیری از چندبار بایند شدن (delegation برای حالت partial)
    if (!window._bindStaffFindReferralDelegated) {
        document.addEventListener('click', function (e) {
            const x = e.target.closest('#btn-find-referral');
            if (x) {
                e.preventDefault();
                findReferralCodeByNationalCode();
            }
        });
        window._bindStaffFindReferralDelegated = true;
    }

    // اعتبارسنجی کلاینت (ایمیل)
    const emailInput = document.querySelector('input[name="email"]');
    if (emailInput) {
        emailInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^a-zA-Z0-9@._-]/g, '');
        });
    }
    // شماره تماس و کد ملی فقط عدد
    ['phone', 'national_code'].forEach(function (name) {
        var input = document.querySelector('input[name="' + name + '"]');
        if (input) input.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    });

    // اعتبارسنجی کد معرف پرسنل (input و blur)
    var referralInput = document.getElementById("referred_by");
    var errorSpan = document.getElementById("referred_by_error");
    if (referralInput && errorSpan) {
        referralInput.addEventListener("input", function () {
            let val = this.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '');
            if (val.length === 1 && !['A', 'S', 'C', 'N'].includes(val[0])) val = '';
            if (val.length === 2 && val[1] !== '-') val = val[0] + '-' + val[1];
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
            fetch('/admin/check-referral-code?code=' + encodeURIComponent(code))
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

    // جستجو بر اساس کد ملی معرف (در صورت وجود)
    // --- جستجوی کد معرف با کد ملی (برای پرسنل) ---
    window.findReferralCodeByNationalCode = async function () {
        const typeSel = document.getElementById('referrer_type');
        const ncodeIn = document.getElementById('referrer_national_code');
        const resultLbl = document.getElementById('national-code-result');
        const refInput = document.getElementById('referred_by');
        const errSpan = document.getElementById('referred_by_error');

        if (resultLbl) { resultLbl.textContent = ''; resultLbl.style.color = 'inherit'; }
        if (errSpan) { errSpan.style.display = 'none'; errSpan.textContent = ''; }

        const type = typeSel ? typeSel.value : '';
        const ncode = (ncodeIn && ncodeIn.value ? ncodeIn.value.trim() : '');

        if (!type) {
            if (resultLbl) { resultLbl.style.color = 'red'; resultLbl.textContent = 'لطفاً «سمت معرف» را انتخاب کنید.'; }
            return;
        }
        if (!/^\d{10}$/.test(ncode)) {
            if (resultLbl) { resultLbl.style.color = 'red'; resultLbl.textContent = 'کد ملی ۱۰ رقمی نامعتبر است.'; }
            return;
        }

        const btn = document.getElementById('btn-find-referral');
        const baseUrl = (btn && btn.dataset.lookupUrl) ? btn.dataset.lookupUrl : '/admin/referral-code-by-national';
        const url = `${baseUrl}?type=${encodeURIComponent(type)}&national_code=${encodeURIComponent(ncode)}`;

        try {
            const resp = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'same-origin',
                redirect: 'follow'
            });

            const ct = (resp.headers.get('content-type') || '').toLowerCase();
            const isJSON = ct.includes('application/json');
            const data = isJSON ? await resp.json() : await resp.text();

            if (!isJSON) {
                if (resultLbl) {
                    resultLbl.style.color = 'red';
                    resultLbl.textContent = 'پاسخ JSON دریافت نشد. شاید لاگین منقضی شده یا ریدایرکت شدید.';
                }
                return;
            }

            if (!resp.ok) {
                const msg = (data && data.message) ? data.message : 'کد معرفی با این کد ملی یافت نشد.';
                if (resultLbl) { resultLbl.style.color = 'red'; resultLbl.textContent = msg; }
                return;
            }

            if (data && data.exists && data.code) {
                if (refInput) refInput.value = data.code;
                if (typeSel && data.type) typeSel.value = data.type; // سینک با جواب
                if (resultLbl) {
                    resultLbl.style.color = 'green';
                    resultLbl.textContent = `کد معرف پیدا شد: ${data.code}`;
                }
                const box = document.getElementById('search-by-national-code');
                if (box) box.style.display = 'none';
            } else {
                if (resultLbl) { resultLbl.style.color = 'red'; resultLbl.textContent = 'کدی پیدا نشد.'; }
            }
        } catch (e) {
            if (resultLbl) { resultLbl.style.color = 'red'; resultLbl.textContent = 'خطا در ارتباط با سرور!'; }
        }
    };

    // هندل فرم ثبت یا ویرایش (فقط یکبار و بدون تکرار)
    var form = document.getElementById('staffCreateForm') || document.getElementById('staffEditForm');
    var messageDiv = document.getElementById('staff-create-message') || document.getElementById('staff-edit-message');

    // اگر قبلاً این listener اضافه نشده، اضافه کن
    if (form && !form.dataset.boundSubmit) {
        form.dataset.boundSubmit = 'true';
        form.addEventListener('submit', function (e) {
            console.log('Form SUBMIT HANDLER FIRED!');

            e.preventDefault();

            var submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn.disabled) return; // ✅ از ثبت دوباره جلوگیری می‌کند
            submitBtn.disabled = true;

            var formData = new FormData(form);
            var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            // اگر بانک انتخاب نشده باشد، فیلد را نفرست
            if (formData.get('bank_name') === '' || formData.get('bank_name') === null) {
                formData.delete('bank_name');
            }

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
                .then(response => response.json())
                .then(data => {
                    submitBtn.disabled = false;
                    if (data.success) {
                        messageDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                        form.reset();
                        setTimeout(() => { messageDiv.innerHTML = ''; }, 8000); // 👈 این خط را اینجا اضافه کن

                    } else if (data.errors) {
                        let errorsHtml = '<div class="alert alert-danger"><ul>';
                        Object.values(data.errors).forEach(msg => errorsHtml += '<li>' + msg + '</li>');
                        errorsHtml += '</ul></div>';
                        messageDiv.innerHTML = errorsHtml;
                    } else {
                        messageDiv.innerHTML = '<div class="alert alert-danger">خطای سرور</div>';
                    }
                })
                .catch(() => {
                    submitBtn.disabled = false;
                    messageDiv.innerHTML = '<div class="alert alert-danger">خطا در ارتباط</div>';
                });
        });
    }


    // فعال سازی تقویم شمسی برای تاریخ استخدام
    // فعال سازی تقویم شمسی فقط روی فیلدهای تاریخ
    if ($('.datepicker').length) {
        $('.datepicker').each(function () {
            let initialVal = $(this).val();
            $(this).persianDatepicker({
                format: 'YYYY/MM/DD',
                observer: true,
                autoClose: true,
                initialValue: !!initialVal, // اگر مقدار اولیه داشت true شود
                initialValueType: 'persian',
                calendar: {
                    persian: {
                        locale: 'fa'
                    }
                },
                onShow: function () {
                    $(this).attr('readonly', true);
                }
            });
        });

    }



    // دکمه‌های بازگشت (انصراف) AJAX
    handleStaffBackButtons();
};

// هندل AJAX دکمه انصراف فقط یکبار!
function handleStaffBackButtons() {
    document.querySelectorAll('.staff-back-btn').forEach(function (btn) {
        btn.removeEventListener('click', btn._staffBackAjax);
        btn._staffBackAjax = function (e) {
            e.preventDefault();
            if (btn && typeof btn.getAttribute === 'function')
                var url = btn.getAttribute('data-url') || btn.getAttribute('href');
            if (url && typeof loadPartial === "function") {
                loadPartial(url);
            }
            else {
                // اگر loadPartial نبود (مثلاً حالت fallback)، رفتاری معمولی داشته باشد
                window.location.href = url;
            }
        };
        btn.addEventListener('click', btn._staffBackAjax);
    });
}

// هندل حذف و ویرایش (delegation فقط یکبار)
document.addEventListener("DOMContentLoaded", function () {
    if (typeof initStaffForm === "function") initStaffForm();

    // delegation ویرایش پرسنل
    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-staff-btn');
        if (editBtn) {
            e.preventDefault();
            const url = editBtn.getAttribute('data-url');
            if (url && typeof loadPartial === 'function') {
                loadPartial(url);
            }
        }
    });

    // delegation حذف پرسنل
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('.delete-staff-form');
        if (form) {
            e.preventDefault();
            const url = form.getAttribute('data-action');
            if (!confirm('آیا از حذف پرسنل مطمئن هستید؟')) return;
            const formData = new FormData(form);

            let csrfToken = window.csrfToken;
            if (!csrfToken) {
                let meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) csrfToken = meta.getAttribute('content');
            }

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'پرسنل با موفقیت حذف شد.');
                        if (typeof loadPartial === 'function') loadPartial('/admin/staff');
                    } else {
                        alert(data.message || 'خطایی رخ داده است.');
                    }
                })
                .catch(() => {
                    alert('خطا در ارتباط با سرور!');
                });
        }
    });
});

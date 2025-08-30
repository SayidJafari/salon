// public/js/admin/customer-form.js

window.initCustomerForm = function () {

    // فقط برای تست (در کنسول ببینید که فایل درست لود شده)
    console.log("✅ customer-form.js با موفقیت لود شد!");

    const btn = document.getElementById('btn-find-referral');
    if (btn) {
        btn.onclick = null;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            console.log('btn-find-referral clicked');
            findReferralCodeByNationalCode();
        });
    }

    if (!window._bindFindReferralDelegated) {
        document.addEventListener('click', function (e) {
            const x = e.target.closest('#btn-find-referral');
            if (x) {
                e.preventDefault();
                console.log('delegated: btn-find-referral clicked');
                findReferralCodeByNationalCode();
            }
        });
        window._bindFindReferralDelegated = true;
    }



    // ---- اعتبارسنجی یوزرنیم (customerusername فقط حروف، عدد و _ مجاز است) ----
    const usernameInput = document.querySelector('input[name="customerusername"]');
    if (usernameInput) {
        usernameInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
        });
    }

    // ---- اعتبارسنجی ایمیل (اجباری نیست اما امنیتی است) ----
    const emailInput = document.querySelector('input[name="email"]');
    if (emailInput) {
        emailInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^a-zA-Z0-9@._-]/g, '');
        });
    }

    // ---- اعتبارسنجی پسورد و تاییدیه (فقط نمایش خطا سمت کلاینت، امنیت اصلی سمت سرور) ----
    const passwordInput = document.querySelector('input[name="password"]');
    const passwordConfirmInput = document.querySelector('input[name="password_confirmation"]');
    if (passwordInput && passwordConfirmInput) {
        passwordConfirmInput.addEventListener('input', function () {
            let msgDiv = document.getElementById('password-match-error');
            if (!msgDiv) {
                msgDiv = document.createElement('div');
                msgDiv.id = 'password-match-error';
                msgDiv.style.color = 'red';
                passwordConfirmInput.parentNode.appendChild(msgDiv);
            }
            if (this.value && this.value !== passwordInput.value) {
                msgDiv.textContent = "رمز عبور و تکرار آن یکسان نیست!";
            } else {
                msgDiv.textContent = '';
            }
        });
    }

    // ---- کد معرف: فرمت و بررسی ایجکس ----
    const referralInput = document.getElementById("referred_by");
    const errorSpan = document.getElementById("referred_by_error");
    if (referralInput && errorSpan) {
        referralInput.addEventListener("input", function () {
            let val = this.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '');
            // اگر فقط یک کاراکتر است، باید از لیست مجاز شروع شود
            if (val.length === 1 && !['A', 'S', 'C', 'N'].includes(val[0])) val = '';
            if (val.length === 2 && val[1] !== '-') {
                if (['A', 'S', 'C', 'N'].includes(val[0])) {
                    val = val[0] + '-' + val[1];
                }
            }
            let parts = val.split('-');
            if (parts.length > 2) val = parts[0] + '-' + parts[1].replace(/-/g, '');
            this.value = val;
            errorSpan.style.display = 'none';
        });

        referralInput.addEventListener("blur", function () {
            const code = this.value.trim().toUpperCase();
            if (!code || code.length < 4) return;
            fetch('/admin/check-referral-code?code=' + encodeURIComponent(code)) // ← اسلش اضافه شد
                .then(res => res.json())
                .then(data => {
                    if (!data.exists) {
                        errorSpan.innerText = "کد معرف یافت نشد! اگر معرف شما ثبت‌نام کرده لطفاً کد ملی او را وارد کنید.";
                        errorSpan.style.display = 'block';
                        const byNat = document.getElementById("search-by-national-code");
                        if (byNat) byNat.style.display = 'block';
                    } else {
                        errorSpan.style.display = 'none';
                        const byNat = document.getElementById("search-by-national-code");
                        if (byNat) byNat.style.display = 'none';
                    }
                })
                .catch(() => {
                    errorSpan.innerText = "خطا در ارتباط با سرور!";
                    errorSpan.style.display = 'block';
                });
        });
    }

    // ---- مدیریت ارسال فرم ثبت یا ویرایش مشتری با AJAX ----
    // دو آی‌دی ممکن (create و edit) در هر صفحه فقط یکی فعال است
    var form = document.getElementById('customerCreateForm') || document.getElementById('customerEditForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            // حذف هر پیام قبلی (تا نمایش تکراری نشود)
            form.querySelectorAll('.alert').forEach(el => el.remove());
            var formData = new FormData(form);

            // CSRF Token اتوماتیک: ابتدا از window.csrfToken و اگر نبود از meta
            let csrfToken = window.csrfToken;
            if (!csrfToken) {
                let meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) csrfToken = meta.getAttribute('content');
            }

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                }
            })
                .then(async (response) => {
                    const ct = (response.headers.get('content-type') || '').toLowerCase();
                    const isJSON = ct.includes('application/json');

                    if (response.ok) {
                        return isJSON ? await response.json() : await response.text();
                    }

                    // غیر 2xx:
                    if (isJSON) {
                        const data = await response.json();
                        // بفرستیم به مرحله بعد تا مثل موفق، errors را رندر کنیم
                        return Promise.reject({ type: 'json', data, status: response.status });
                    } else {
                        const html = await response.text();
                        return Promise.reject({ type: 'html', html, status: response.status });
                    }
                })

                .then(data => {
                    if (data.success) {
                        // پیام موفقیت بالای فرم
                        form.insertAdjacentHTML('afterbegin',
                            '<div class="alert alert-success" id="form-success-alert">' + data.message + '</div>');
                        setTimeout(() => {
                            const alertDiv = document.getElementById('form-success-alert');
                            if (alertDiv) alertDiv.remove();
                        }, 5000);

                    } else if (data.errors) {
                        const allMsgs = [];
                        Object.values(data.errors).forEach(v => {
                            if (Array.isArray(v)) allMsgs.push(...v);
                            else if (v) allMsgs.push(v);
                        });
                        let errorsHtml = '<div class="alert alert-danger" id="form-error-alert"><ul>';
                        allMsgs.forEach(m => { errorsHtml += '<li>' + m + '</li>'; });
                        errorsHtml += '</ul></div>';
                        form.insertAdjacentHTML('afterbegin', errorsHtml);
                        setTimeout(() => {
                            const alertDiv = document.getElementById('form-error-alert');
                            if (alertDiv) alertDiv.remove();
                        }, 5000);


                    } else if (data.message) {
                        form.insertAdjacentHTML('afterbegin',
                            '<div class="alert alert-danger">' + data.message + '</div>');
                        setTimeout(() => {
                            const alertDiv = document.getElementById('form-error-alert');
                            if (alertDiv) alertDiv.remove();
                        }, 5000);

                    }
                })
                .catch(err => {
                    if (err.type === 'json' && err.data) {
                        const data = err.data;

                        // خلاصه بالای فرم
                        if (data.message) {
                            form.insertAdjacentHTML('afterbegin',
                                '<div class="alert alert-danger" id="form-error-alert">' + data.message + '</div>');
                        }

                        // رندر خطاهای فیلدی لاراول (که آرایه هستند)
                        if (data.errors) {
                            // پاک‌سازی حالت‌های قبلی
                            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                            form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());

                            // لیست خلاصه
                            const allMsgs = [];
                            Object.values(data.errors).forEach(v => {
                                if (Array.isArray(v)) allMsgs.push(...v);
                                else if (v) allMsgs.push(v);
                            });
                            if (allMsgs.length) {
                                let errorsHtml = '<div class="alert alert-danger" id="form-error-alert"><ul>';
                                allMsgs.forEach(m => { errorsHtml += '<li>' + m + '</li>'; });
                                errorsHtml += '</ul></div>';
                                form.insertAdjacentHTML('afterbegin', errorsHtml);
                            }

                            // نمایش کنار فیلدها (Bootstrap-like)
                            Object.entries(data.errors).forEach(([name, msgs]) => {
                                const input = form.querySelector('[name="' + name + '"]');
                                const firstMsg = Array.isArray(msgs) ? msgs[0] : msgs;
                                if (input) {
                                    input.classList.add('is-invalid');
                                    const fb = document.createElement('div');
                                    fb.className = 'invalid-feedback';
                                    fb.textContent = firstMsg;
                                    input.insertAdjacentElement('afterend', fb);
                                }
                                // مخصوص کد معرف:
                                if (name === 'referred_by') {
                                    const span = document.getElementById('referred_by_error');
                                    if (span) {
                                        span.innerText = firstMsg;
                                        span.style.display = 'block';
                                    }
                                }
                            });
                        }
                    } else if (err.type === 'html') {
                        // صفحه‌ی خطای HTML لاراول
                        form.insertAdjacentHTML('afterbegin', err.html);
                    } else {
                        form.insertAdjacentHTML('afterbegin',
                            '<div class="alert alert-danger" id="form-error-alert">خطا در ارتباط با سرور!</div>');
                    }
                });
            return false;
        });

    }
};

// --- جستجوی کد معرف با کد ملی ---
window.findReferralCodeByNationalCode = async function () {
    console.log('findReferralCodeByNationalCode clicked');

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

    // URL از data-attribute (به‌جای هاردکد)
    const btn = document.getElementById('btn-find-referral');
    const baseUrl = (btn && btn.dataset.lookupUrl) ? btn.dataset.lookupUrl : '/admin/referral-code-by-national';
    const url = `${baseUrl}?type=${encodeURIComponent(type)}&national_code=${encodeURIComponent(ncode)}`;
    console.log('fetch url:', url, 'type:', type, 'ncode:', ncode);

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
        console.log('status:', resp.status, 'isJSON:', isJSON, 'data:', data);

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
            if (typeSel && data.type) typeSel.value = data.type;
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
        console.error(e);
        if (resultLbl) { resultLbl.style.color = 'red'; resultLbl.textContent = 'خطا در ارتباط با سرور!'; }
    }
};





// -------------- رویدادهای ایجکس برای لیست مشتریان (حذف/ویرایش) --------------
window.initCustomerListEvents = function () {

    // دکمه‌های ویرایش مشتری (باز کردن فرم ایجکس)
    document.querySelectorAll('.edit-customer-btn').forEach(function (btn) {
        btn.onclick = null; // جلوگیری از دوبار bind شدن
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            if (url && typeof loadPartial === "function") {
                loadPartial(url);
            }
        });
    });

    // فرم‌های حذف مشتری
    document.querySelectorAll('.delete-customer-form').forEach(function (form) {
        form.onsubmit = null; // جلوگیری از تداخل
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!confirm('آیا از حذف این مشتری مطمئن هستید؟')) return;
            const formData = new FormData(this);
            const url = this.getAttribute('data-action');

            // توکن CSRF
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
                    const msgDiv = document.getElementById('customer-list-message');
                    if (data.success) {
                        if (msgDiv) msgDiv.innerHTML =
                            '<div class="alert alert-success">' + data.message + '</div>';
                        if (typeof loadPartial === "function") loadPartial('/admin/customers');
                    } else {
                        if (msgDiv) msgDiv.innerHTML =
                            '<div class="alert alert-danger">' + (data.message || 'خطایی رخ داده است!') + '</div>';
                    }
                })
                .catch(() => {
                    const msgDiv = document.getElementById('customer-list-message');
                    if (msgDiv) msgDiv.innerHTML =
                        '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>';
                });
        });
    });

    // دکمه‌های ajax برای افزودن مشتری (مثلاً modal یا partial)
    document.querySelectorAll('.menu-ajax').forEach(function (menu) {
        menu.onclick = null;
        menu.addEventListener('click', function (e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            if (url && typeof loadPartial === "function") {
                loadPartial(url);
            }
        });
    });

    // دکمه‌های بازگشت به لیست مشتریان (ajax)
    handleCustomerBackButtons();
};


function handleCustomerBackButtons() {
    document.querySelectorAll('.customer-back-btn').forEach(function (btn) {
        // حذف رویداد قبلی برای جلوگیری از چندبار ثبت شدن
        btn.removeEventListener('click', btn._customerBackAjax);
        btn._customerBackAjax = function (e) {
            e.preventDefault();
            var url = btn.getAttribute('data-url') || btn.getAttribute('href');
            // فقط اگر loadPartial تعریف شده (در داشبورد SPA)
            if (url && typeof loadPartial === "function") {
                loadPartial(url);
            }
            else {
                // اگر loadPartial نبود (مثلاً حالت fallback)، رفتاری معمولی داشته باشد
                window.location.href = url;
            }
        };
        btn.addEventListener('click', btn._customerBackAjax);
    });
}



// اجرا هر بار پس از بارگذاری صفحه (یا بعد از loadPartial)
document.addEventListener("DOMContentLoaded", function () {
    if (typeof initCustomerForm === "function") initCustomerForm();
    if (typeof initCustomerListEvents === "function") initCustomerListEvents();


});

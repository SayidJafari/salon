// public/js/admin/account-form.js
// اسکریپت مدیریت فرم‌های حساب/صندوق (ثبت، ویرایش، حذف و نمایش داینامیک فیلدها) با AJAX و بدون رفرش صفحه

// تابع اصلی مقداردهی اولیه فرم ثبت/ویرایش
window.initAccountForm = function () {
    // هم ایجاد هم ویرایش
    const form = document.getElementById('accountCreateForm') || document.getElementById('accountEditForm');
    if (!form) return;

    // سوییچر ادغام‌شده: bank | cashbox  (اختیاری است)
    const kindInput = form.querySelector('[name="kind"]'); // هم hidden و هم select را پوشش می‌دهد
    // منطق قبلی
    const typeInput = document.getElementById('type');           // ممکن است حذف شده باشد
    const hasPosInput = document.getElementById('has_pos');      // در ساختار جدید استفاده می‌شود

    // فیلدهای نمایش/مخفی‌سازی
    const titleField = document.querySelector('.field-title');
    const ownerField = document.querySelector('.field-owner');
    const shabaField = document.querySelector('.field-shaba');
    const cardField = document.querySelector('.field-card');
    const accField = document.querySelector('.field-account');
    const posTerminalField = document.querySelector('.field-pos-terminal');
    const posSection = document.querySelector('.pos-section');   // چک‌باکس POS (اگر وجود داشته باشد)

    // فیلد مخصوص صندوق
    const locationField = document.querySelector('.field-location');

    // اگر لیبل متنی دارید (در بعضی ویوها داریم)
    const titleLabelSpan = document.querySelector('.title-label');
    const titleInput = document.getElementById('title');

    let messageDiv = document.getElementById('account-message');
    if (!messageDiv) {
        messageDiv = document.createElement('div');
        messageDiv.id = 'account-message';
        form.prepend(messageDiv);
    }

    function hide(el) { if (el) el.style.display = 'none'; }
    function show(el) { if (el) el.style.display = ''; }

    // نمایش فیلدها بر اساس kind و سپس type (منطق قبلی حذف نشده)
    window.updateFields = function () {
        // 1) اگر kind داریم: ابتدا بر اساس آن سوییچ کن
        if (kindInput) {
            const k = kindInput.value || 'bank';
            if (k === 'cashbox') {
                // فقط فیلدهای صندوق
                show(locationField);        // صندوق: محل/توضیحات
                hide(titleField);           // ستون title در cash_boxes حذف شده
                hide(ownerField);
                hide(shabaField);
                hide(cardField);
                hide(accField);
                hide(posTerminalField);
                hide(posSection);

                // اگر لیبل پویا وجود دارد، متنش را متناسب با صندوق تنظیم کن
                if (titleLabelSpan) titleLabelSpan.textContent = 'نام صندوق';
                if (titleInput) titleInput.placeholder = 'مثلاً صندوق اصلی پذیرش';

                return; // برای صندوق نیازی به منطق type نیست
            } else {
                // بانک: location مخفی شود و ادامه بده به منطق type/بانکی
                hide(locationField);

                // لیبل پویا برای بانک
                if (titleLabelSpan) titleLabelSpan.textContent = 'نام بانک/شعبه یا حساب';
                if (titleInput && !titleInput.placeholder) {
                    titleInput.placeholder = 'مثلاً بانک ملت - شعبه ...';
                }
            }
        }

        // 2) منطق قبلی برای type (اگر وجود داشته باشد)
        if (typeInput) {
            hide(titleField);
            hide(ownerField);
            hide(shabaField);
            hide(cardField);
            hide(accField);
            hide(posTerminalField);
            if (posSection) hide(posSection);

            const val = typeInput.value;
            if (val === 'bank') {
                show(titleField);
                show(ownerField);
                show(shabaField);
                show(cardField);
                show(accField);
                if (posSection) show(posSection);
            } else if (val === 'cash') {
                show(titleField);
            } else if (val === 'pos') {
                show(titleField);
                show(accField);
                show(posTerminalField);
                if (posSection) show(posSection);
            } else if (val === 'wallet') {
                show(titleField);
                show(accField);
            }

            // اگر همزمان has_pos تیک خورد، POS را نشان بده
            if (hasPosInput && hasPosInput.checked) {
                show(posTerminalField);
            }
            return;
        }

        // 3) اگر type حذف شده: فیلدهای اصلی بانک را نمایش بده (حالت پیش‌فرض بانک)
        show(titleField);
        show(ownerField);
        show(shabaField);
        show(cardField);
        show(accField);
        if (posSection) show(posSection);
        if (posTerminalField) {
            if (hasPosInput && hasPosInput.checked) show(posTerminalField);
            else hide(posTerminalField);
        }
    };

    if (kindInput) kindInput.addEventListener('change', updateFields);
    if (typeInput) typeInput.addEventListener('change', updateFields);
    if (hasPosInput) hasPosInput.addEventListener('change', updateFields);
    updateFields();

    // --- ارسال AJAX (ثبت/ویرایش) ---
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        messageDiv.innerHTML = '';

        const formData = new FormData(form);

        // is_active
 const activeInput =
   form.querySelector('#is_active') ||
   form.querySelector('input[type="checkbox"][name="is_active"]');
        formData.set('is_active', activeInput && activeInput.checked ? '1' : '0');

        // kind
        let kind = kindInput ? (kindInput.value || 'bank') : 'bank';
        formData.set('kind', kind);

        if (kindInput) {
            kind = kindInput.value || 'bank';
            formData.set('kind', kind);
        }

        // has_pos و pos_terminal (فقط در حالت bank معنا دارد)
        if (kind === 'bank') {
if (hasPosInput) {
    let hasPos = hasPosInput.checked ? '1' : '0';
    const serial = (formData.get('pos_terminal') || '').trim();

    // اگر تیک نزده ولی سریال وارد شده، has_pos را 1 کن
    if (hasPos === '0' && serial !== '') {
        hasPos = '1';
    }
    formData.set('has_pos', hasPos);

    // دقت: اینجا دیگه pos_terminal را حذف نکن!
}

            // فیلدهای بانکی: اگر خالی‌اند، نفرست تا گیر UNIQUE نخوری
['bank_name', 'account_number', 'shaba_number', 'card_number', 'pos_terminal', 'owner_name', 'title'].forEach(n => {
                const v = formData.get(n);
                if (v === '' || v == null) formData.delete(n);
            });
        } else {
            ['bank_name', 'account_number', 'shaba_number', 'card_number', 'pos_terminal', 'owner_name', 'title'].forEach(n => formData.delete(n));

        }

        // CSRF
        let csrfToken = window.csrfToken;
        if (!csrfToken) {
            const meta = document.querySelector('meta[name="csrf-token"]');
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
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    messageDiv.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                    // اگر create است فرم ریست شود
                    if (!form.querySelector('input[name="_method"]')) {
                        form.reset();
                    }
                    updateFields();

                    // اگر loadPartial هست، بعد از موفقیت می‌تونیم برگردیم به لیست
                    if (typeof loadPartial === 'function' && form.dataset.redirectTo) {
                        loadPartial(form.dataset.redirectTo);
                    }
                } else if (data && data.errors) {
                    let errorsHtml = '<div class="alert alert-danger"><ul>';
                    Object.values(data.errors).forEach(function (msg) {
                        if (Array.isArray(msg)) {
                            msg.forEach(m => errorsHtml += '<li>' + m + '</li>');
                        } else {
                            errorsHtml += '<li>' + msg + '</li>';
                        }
                    });
                    errorsHtml += '</ul></div>';
                    messageDiv.innerHTML = errorsHtml;
                } else {
                    messageDiv.innerHTML = '<div class="alert alert-danger">خطای نامشخص!</div>';
                }
            })
            .catch(() => {
                messageDiv.innerHTML = '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>';
            });
    });
};


// رویدادهای لیست (حذف و ویرایش ایجکس)
window.initAccountListEvents = function () {
    // حذف حساب/صندوق
    document.querySelectorAll('.delete-account-form').forEach(function (form) {
        form.onsubmit = null; // جلوگیری از دو بار bind
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!confirm('آیا از حذف این مورد مطمئن هستید؟')) return;

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
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                }
            })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        if (typeof loadPartial === "function") {
                            loadPartial('/admin/accounts');
                        }
                    } else {
                        alert((data && data.message) || 'خطایی رخ داده است!');
                    }
                })
                .catch(() => {
                    alert('خطا در ارتباط با سرور!');
                });
        });
    });

    // ویرایش ایجکس
    document.querySelectorAll('.edit-account-btn.menu-ajax').forEach(function (btn) {
        btn.onclick = null;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            if (url && typeof loadPartial === "function") {
                loadPartial(url);
            }
        });
    });

    // دکمه‌های بازگشت
    handleBackButtons();
};


// مدیریت دکمه‌های بازگشت ایجکس
function handleBackButtons() {
    document.querySelectorAll('.back-btn').forEach(function (btn) {
        btn.removeEventListener('click', btn._backAjax);
        btn._backAjax = function (e) {
            e.preventDefault();
            const url = btn.getAttribute('data-url');
            if (url && typeof loadPartial === "function") {
                loadPartial(url);
            }
        };
        btn.addEventListener('click', btn._backAjax);
    });
}

// اجرای خودکار پس از لود
document.addEventListener('DOMContentLoaded', function () {
    if (typeof initAccountForm === "function") initAccountForm();
    if (typeof initAccountListEvents === "function") initAccountListEvents();
});


// === [GLOBAL Delegated Handlers] ===

// هر چیزی با کلاس menu-ajax را به صورت delegated هندل می‌کنیم
document.addEventListener('click', function (e) {
    const el = e.target.closest('a.menu-ajax, button.menu-ajax');
    if (!el) return;

    e.preventDefault();
    const url = el.getAttribute('data-url') || el.getAttribute('href');
    if (url && typeof loadPartial === 'function') {
        loadPartial(url);
    }
});

// فرم‌های حذف (بانک + صندوق) به صورت delegated
document.addEventListener('submit', function (e) {
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
        body: formData,               // شامل _method=DELETE هم هست
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
        }
    })
        .then(r => r.json())
        .then(data => {
            const msg = document.getElementById('account-list-message');
            if (data && data.success) {
                if (msg) msg.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                if (typeof loadPartial === 'function') {
                    loadPartial('/admin/accounts');
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

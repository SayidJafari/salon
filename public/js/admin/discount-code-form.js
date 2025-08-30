// public/js/admin/discount-code-form.js

// ======= فعالسازی دیت‌پیکر شمسی برای همه فیلدهای datepicker در این صفحه =======
document.addEventListener('DOMContentLoaded', function () {
    if (typeof $ !== "undefined" && $('.datepicker').length) {
        $('.datepicker').each(function () {
            let initialVal = $(this).val();
            $(this).persianDatepicker({
                format: 'YYYY/MM/DD',
                observer: true,
                autoClose: true,
                initialValue: !!initialVal,
                initialValueType: 'persian',
                calendar: { persian: { locale: 'fa' } },
                onShow: function () { $(this).attr('readonly', true); }
            });
        });
    }
});

// ======= مدیریت فرم کد تخفیف =======
window.initDiscountCodeForm = function () {
    var form = document.getElementById('discountCodeForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        let url = form.action;
        let formData = new FormData(form);

        // همیشه مقدار discount_type باید انگلیسی باشد!
        let discountTypeSelect = document.getElementById('discount_type');
        if (discountTypeSelect) {
            let val = discountTypeSelect.value;
            // اگر کاربر دستی تغییر داده و مقدار فارسی وارد کرده بود، تصحیحش کن
            if (val === 'مبلغی') discountTypeSelect.value = 'amount';
            if (val === 'درصدی') discountTypeSelect.value = 'percent';
        }

        let msgDiv = document.getElementById('discount-message');
        if (msgDiv) msgDiv.innerHTML = '';

        fetch(url, {
            method: form.querySelector('input[name="_method"]') ? 'POST' : 'POST',
            body: new FormData(form),
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(r => r.json())
            .then(data => {
                if (msgDiv) {
                    if (data.success) {
                        msgDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                        loadDiscountList();
                        form.reset();
                    } else {
                        let html = '<div class="alert alert-danger">';
                        if (data.errors) {
                            Object.values(data.errors).forEach(e => html += '<div>' + e + '</div>');
                        } else {
                            html += data.message;
                        }
                        html += '</div>';
                        msgDiv.innerHTML = html;
                    }
                }
            })
            .catch(() => {
                if (msgDiv)
                    msgDiv.innerHTML = '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>';
            });
    });

    function loadDiscountList() {
        fetch('/admin/discount-codes/list')
            .then(r => r.text())
            .then(html => {
                let list = document.getElementById('discount-list');
                if (list) list.innerHTML = html;
                addRowEventListeners();
            });
    }

    function addRowEventListeners() {
        // حذف
        document.querySelectorAll('.delete-discount-btn').forEach(btn => {
            btn.onclick = function () {
                let id = this.getAttribute('data-id');
                if (confirm('حذف این کد انجام شود؟')) {
                    fetch('/admin/discount-codes/' + id, {
                        method: 'DELETE',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                        }
                    })
                        .then(r => r.json())
                        .then(data => {
                            let msgDiv = document.getElementById('discount-message');
                            if (data.success) {
                                let row = document.getElementById('discount-row-' + id);
                                if (row) row.remove();
                                if (msgDiv)
                                    msgDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                            } else {
                                if (msgDiv)
                                    msgDiv.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
                            }
                        });
                }
            }
        });

        // ویرایش
        document.querySelectorAll('.edit-discount-btn').forEach(btn => {
            btn.onclick = function () {
                let id = this.getAttribute('data-id');
                fetch('/admin/discount-codes/' + id + '/edit')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.discount) {
                            document.getElementById('discount-form-title').innerText = 'ویرایش کد تخفیف';
                            form.action = '/admin/discount-codes/' + id;
                            // حذف input قبلی _method (اگر وجود داشت)
                            let oldMethodInput = form.querySelector('input[name="_method"]');
                            if (oldMethodInput) oldMethodInput.remove();

                            // مقداردهی PUT به _method
                            let methodInput = document.createElement('input');
                            methodInput.type = 'hidden';
                            methodInput.name = '_method';
                            methodInput.value = 'PUT';
                            form.appendChild(methodInput);

                            // همیشه مقدار discount_type باید انگلیسی باشد!
                            let type = data.discount.discount_type;
                            if (type === 'مبلغی') type = 'amount';
                            if (type === 'درصدی') type = 'percent';

                            document.getElementById('code').value = data.discount.code;
                            document.getElementById('discount_type').value = type;
                            document.getElementById('value').value = data.discount.value;
                            document.getElementById('usage_limit').value = data.discount.usage_limit;
                            // مقدار اولیه فیلد تاریخ برای دیت‌پیکر شمسی
                            if (document.getElementById('valid_from_jalali')) {
                                document.getElementById('valid_from_jalali').value = data.valid_from_jalali || '';
                            }
                            if (document.getElementById('valid_until_jalali')) {
                                document.getElementById('valid_until_jalali').value = data.valid_until_jalali || '';
                            }

                            document.getElementById('is_active').value = data.discount.is_active;
                        }
                    });
            }
        });

    }

    addRowEventListeners();
};
document.addEventListener("DOMContentLoaded", function () {
    if (typeof initDiscountCodeForm === "function") initDiscountCodeForm();
});

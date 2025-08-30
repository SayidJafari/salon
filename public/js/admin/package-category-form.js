// اجرای رویدادهای فرم ایجاد پک (همیشه)
window.initPackageCreateForm = function () {
    document.querySelectorAll('input[type="checkbox"][name="services[]"]').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var quantityInput = this.closest('div').querySelector('input[type="number"]');
            if (quantityInput) {
                quantityInput.disabled = !this.checked;
                if (this.checked && !quantityInput.value) quantityInput.value = 1;
            }
            calculateTotalServicesPrice();
        });
    });

    document.querySelectorAll('input[type="number"][name^="quantities"]').forEach(function (input) {
        input.addEventListener('input', calculateTotalServicesPrice);
    });

    function calculateTotalServicesPrice() {
        let total = 0;
        document.querySelectorAll('input[type="checkbox"][name="services[]"]:checked').forEach(function (checkbox) {
            var price = parseFloat(checkbox.getAttribute('data-price')) || 0;
            var quantityInput = checkbox.closest('div').querySelector('input[type="number"]');
            var qty = quantityInput && !quantityInput.disabled ? parseInt(quantityInput.value) : 1;
            total += price * (qty > 0 ? qty : 1);
        });
        let totalPriceSpan = document.getElementById('total-services-price');
        if (totalPriceSpan) totalPriceSpan.innerText = total.toLocaleString('fa-IR');
    }

    calculateTotalServicesPrice();
};

// تابع فعال/غیرفعال کردن سلکت پرسنل مرتبط با هر خدمت
window.enableStaffSelectForServices = function () {
    document.querySelectorAll('input[type="checkbox"][name="services[]"]').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            // سلکت مربوط به این چک‌باکس را دقیق پیدا کن
            var wrapper = this.closest('.d-flex.align-items-center.justify-content-between.bg-light.rounded-3.p-2');
            var staffSelect = wrapper ? wrapper.querySelector('.staff-select') : null;
            if (staffSelect) {
                staffSelect.disabled = !this.checked;
                if (!this.checked) staffSelect.value = '';
            }
        });
    });
    console.log('کد فعال‌سازی سلکت پرسنل اجرا شد!');
};

// مدیریت فرم‌های دسته‌بندی پک‌ها (ثبت، ویرایش، حذف) با AJAX
window.initPackageCategoryForm = function () {
    console.log("✅ package-category-form.js با موفقیت لود شد!");

    // دکمه ویرایش ایجکس
    document.querySelectorAll('.edit-package-category-btn').forEach(function (btn) {
        btn.removeEventListener('click', btn._editClickAjax);
        btn._editClickAjax = function (e) {
            e.preventDefault();
            var url = btn.getAttribute('href');
            if (url && typeof loadPartial === "function") {
                loadPartial(url);
            }
        };
        btn.addEventListener('click', btn._editClickAjax);
    });

    // ثبت و ویرایش ایجکس
    ['package-categoriesCreateForm', 'package-categoryEditForm'].forEach(function (formId) {
        var form = document.getElementById(formId);
        if (form) {
            form.onsubmit = null;
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                form.querySelectorAll('.alert').forEach(el => el.remove());
                var formData = new FormData(form);

                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                    .then(response => {
                        if (response.redirected) {
                            loadPartial('/admin/package-categories');
                            return;
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data) return;
                        if (data.success) {
                            form.insertAdjacentHTML('afterbegin', '<div class="alert alert-success">' + data.message + '</div>');
                            loadPartial('/admin/package-categories');
                        } else if (data.errors) {
                            let errorsHtml = '<div class="alert alert-danger"><ul>';
                            Object.values(data.errors).forEach(function (msg) {
                                errorsHtml += '<li>' + msg + '</li>';
                            });
                            errorsHtml += '</ul></div>';
                            form.insertAdjacentHTML('afterbegin', errorsHtml);
                        }
                    })
                    .catch(() => {
                        form.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>');
                    });
            });
        }
    });

    // حذف ایجکس
    document.querySelectorAll('.delete-package-category-form').forEach(function (form) {
        form.onsubmit = null;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!confirm('حذف این پک انجام شود؟')) return;

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
                        loadPartial('/admin/package-categories');
                    } else {
                        alert(data.message || 'امکان حذف پک وجود ندارد.');
                    }
                })
                .catch(() => {
                    alert('خطا در ارتباط با سرور!');
                });
        });
    });

    // دکمه افزودن ایجکس
    document.querySelectorAll('.add-package-category-btn').forEach(function (btn) {
        btn.removeEventListener('click', btn._addCatAjax);
        btn._addCatAjax = function (e) {
            e.preventDefault();
            var url = btn.getAttribute('href');
            if (url && typeof loadPartial === "function") {
                loadPartial(url);
            }
        };
        btn.addEventListener('click', btn._addCatAjax);
    });

    // فراخوانی تابع محاسبه قیمت‌ها برای فرم‌های ایجاد و ویرایش
    if (typeof window.initPackageCreateForm === "function") window.initPackageCreateForm();

    // فراخوانی تابع فعال/غیرفعال کردن سلکت پرسنل بعد از هر بار لود فرم با AJAX
    if (typeof window.enableStaffSelectForServices === "function") window.enableStaffSelectForServices();
};

// فقط یک بار، در لود اولیه صفحه اجرا کن (برای SPA یا partial)
document.addEventListener("DOMContentLoaded", function () {
    if (typeof window.initPackageCategoryForm === "function") window.initPackageCategoryForm();
    if (typeof window.initPackageCreateForm === "function") window.initPackageCreateForm();
    if (typeof window.enableStaffSelectForServices === "function") window.enableStaffSelectForServices();
});

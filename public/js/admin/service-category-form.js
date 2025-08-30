// public/js/admin/service-category-form.js



window.initServiceCategoryForm = function () {
    // دکمه ویرایش ایجکس
    document.querySelectorAll('.edit-category-btn').forEach(function (btn) {
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
    ['service-categoriesCreateForm', 'service-categoryEditForm'].forEach(function (formId) {
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
                            loadPartial('/admin/service-categories');
                            return;
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data) return;
                        if (data.success) {
                            form.insertAdjacentHTML('afterbegin', '<div class="alert alert-success">' + data.message + '</div>');
                            loadPartial('/admin/service-categories');
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
    document.querySelectorAll('.delete-category-form').forEach(function (form) {
        form.onsubmit = null;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!confirm('حذف این دسته‌بندی انجام شود؟')) return;

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
                        loadPartial('/admin/service-categories');
                    } else {
                        alert(data.message || 'امکان حذف دسته‌بندی وجود ندارد.');
                    }
                })
                .catch(() => {
                    alert('خطا در ارتباط با سرور!');
                });
        });
    });


    // دکمه افزودن ایجکس
    document.querySelectorAll('.add-category-btn').forEach(function (btn) {
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










};

document.addEventListener("DOMContentLoaded", function () {
    if (typeof initServiceCategoryForm === "function") initServiceCategoryForm();
});

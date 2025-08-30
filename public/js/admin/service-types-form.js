// public/js/admin/service-types-form.js


window.initServiceTypesForm = function () {
    // دکمه ویرایش ایجکس
    document.querySelectorAll('.edit-type-btn').forEach(function(btn){
        btn.removeEventListener('click', btn._editTypeAjax);
        btn._editTypeAjax = function(e){
            e.preventDefault();
            var url = btn.getAttribute('href');
            if (url && typeof loadPartial === "function") {
                loadPartial(url);
            }
        };
        btn.addEventListener('click', btn._editTypeAjax);
    });

    // دکمه افزودن ایجکس
    document.querySelectorAll('.add-type-btn').forEach(function(btn){
        btn.removeEventListener('click', btn._addTypeAjax);
        btn._addTypeAjax = function(e){
            e.preventDefault();
            var url = btn.getAttribute('href');
            if (url && typeof loadPartial === "function") {
                loadPartial(url);
            }
        };
        btn.addEventListener('click', btn._addTypeAjax);
    });

    // ثبت و ویرایش ایجکس (فرم مشترک!)
    var form = document.querySelector('form[action*="service-types"]');
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
                    loadPartial('/admin/service-types/create');
                    return;
                }
                return response.json();
            })
            .then(data => {
                if (!data) return;
                if (data.success) {
                    form.insertAdjacentHTML('afterbegin', '<div class="alert alert-success">' + data.message + '</div>');
                    loadPartial('/admin/service-types/create');
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

    // حذف ایجکس
    document.querySelectorAll('.delete-type-form').forEach(function(form){
        form.onsubmit = null;
        form.addEventListener('submit', function(e){
            e.preventDefault();
            if (!confirm('حذف این نوع خدمت انجام شود؟')) return;

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
                    loadPartial('/admin/service-types/create');
                } else {
                    alert(data.message || 'امکان حذف نوع خدمت وجود ندارد.');
                }
            })
            .catch(() => {
                alert('خطا در ارتباط با سرور!');
            });
        });
    });
};

document.addEventListener("DOMContentLoaded", function () {
    if (typeof initServiceTypesForm === "function") initServiceTypesForm();
});

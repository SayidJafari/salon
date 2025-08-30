// public/js/admin/ajax-form.js
function ajaxFormSubmit(formId, successCallback = null, errorCallback = null) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        const formData = new FormData(form);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch(form.action, {
                method: form.method || 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                form.querySelectorAll('.alert').forEach(alert => alert.remove());
                if (data.success) {
                    form.insertAdjacentHTML('afterbegin',
                        '<div class="alert alert-success">' + data.message + '</div>');
                    if (successCallback) successCallback(data);
                } else if (data.errors) {
                    let errorsHtml = '<div class="alert alert-danger"><ul>';
                    Object.values(data.errors).forEach(msg => errorsHtml += '<li>' + msg + '</li>');
                    errorsHtml += '</ul></div>';
                    form.insertAdjacentHTML('afterbegin', errorsHtml);
                    if (errorCallback) errorCallback(data);
                }
            })
            .catch(() => {
                form.insertAdjacentHTML('afterbegin',
                    '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>');
            });

        return false;
    });
}

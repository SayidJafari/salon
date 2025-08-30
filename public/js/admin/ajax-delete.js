// public/js/admin/ajax-deleted.js
function ajaxDeleteForm(formSelector, confirmMessage = 'آیا مطمئن هستید؟', callback = null) {
    document.querySelectorAll(formSelector).forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!confirm(confirmMessage)) return;

            const formData = new FormData(form);
            const url = form.getAttribute('data-action') || form.action;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            fetch(url, {
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
                if (callback) callback(data);
                else {
                    if(data.success){
                        alert(data.message || 'حذف با موفقیت انجام شد.');
                        location.reload();
                    } else {
                        alert(data.message || 'خطایی رخ داد.');
                    }
                }
            })
            .catch(() => {
                alert('خطا در ارتباط با سرور!');
            });
        });
    });
}

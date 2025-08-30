// public/js/admin/ajax-edit.js
function ajaxEditButtons(buttonSelector) {
    document.querySelectorAll(buttonSelector).forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            if (url && typeof loadPartial === 'function') {
                loadPartial(url);
            }
        });
    });
}

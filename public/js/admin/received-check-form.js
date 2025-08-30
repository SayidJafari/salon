// public/js/admin/received-check-form.js
// مدیریت فرم‌های چک دریافتی (ثبت، ویرایش، حذف) با
// AJAX و نمایش پیام‌ها

window.initReceivedCheckForm = function () {

    // تاریخ‌شمار شمسی
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

    // توکن CSRF (برای همه درخواست‌ها استفاده می‌شود)
    let csrfToken = window.csrfToken;
    if (!csrfToken) {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) csrfToken = meta.getAttribute('content');
    }

    // ثبت و ویرایش چک ایجکس
    var form = document.getElementById('receivedCheckForm') || document.getElementById('receivedCheckEditForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            form.querySelectorAll('.alert').forEach(el => el.remove());

            var formData = new FormData(form);

            fetch(form.action, {
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
                    if (data && data.success) {
                        form.insertAdjacentHTML('afterbegin', '<div class="alert alert-success">' + data.message + '</div>');
                        if (typeof loadPartial === "function") {
                            loadPartial('/admin/received-checks');
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
                        form.insertAdjacentHTML('afterbegin', errorsHtml);
                    } else {
                        form.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger">خطای نامشخص!</div>');
                    }
                })
                .catch(() => {
                    form.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>');
                });
        });
    }

    // دکمه ویرایش چک (ایجکس)
    document.querySelectorAll('.edit-received-check-btn').forEach(function (btn) {
        btn.onclick = null;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var url = btn.getAttribute('data-url');
            if (url && typeof loadPartial === "function") loadPartial(url);
        });
    });

    // حذف چک ایجکس
    document.querySelectorAll('.delete-received-check-form').forEach(function (form) {
        form.onsubmit = null;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!confirm('آیا از حذف این چک مطمئن هستید؟')) return;

            var formData = new FormData(form);
            var url = form.getAttribute('data-action') || form.action;

            fetch(url, {
                method: 'POST',
                body: formData, // شامل _method=DELETE
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                }
            })
                .then(response => response.json())
                .then(data => {
                    const msgBox = document.getElementById('received-check-list-message');
                    if (data && data.success) {
                        if (msgBox) msgBox.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                        if (typeof loadPartial === "function") {
                            loadPartial('/admin/received-checks');
                        }
                    } else {
                        if (msgBox) msgBox.innerHTML =
                            '<div class="alert alert-danger">' + ((data && data.message) || 'خطایی رخ داده است!') + '</div>';
                    }
                })
                .catch(() => {
                    const msgBox = document.getElementById('received-check-list-message');
                    if (msgBox) msgBox.innerHTML = '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>';
                });
        });
    });

    // دکمه "افزودن چک جدید" (ایجکس)
    document.querySelectorAll('.add-received-check-btn').forEach(function (btn) {
        btn.onclick = null;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var url = btn.getAttribute('data-url');
            if (url && typeof loadPartial === "function") loadPartial(url);
        });
    });

    // جستجو در چک‌ها (live search)
    var searchInput = document.getElementById('received-check-search');
    if (searchInput) {
        let searchTimeout = null;
        searchInput.oninput = null;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            let inputEl = this;
            searchTimeout = setTimeout(function () {
                let q = inputEl.value.trim();
                let url = '/admin/received-checks?q=' + encodeURIComponent(q);
                if (typeof loadPartial === "function") loadPartial(url);
            }, 800);
        });
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimeout);
                let q = this.value.trim();
                let url = '/admin/received-checks?q=' + encodeURIComponent(q);
                if (typeof loadPartial === "function") loadPartial(url);
            }
        });
    }

    // دکمه برگشت
    document.querySelectorAll('.received-check-back-btn').forEach(function (btn) {
        btn.removeEventListener('click', btn._receivedCheckBackAjax);
        btn._receivedCheckBackAjax = function (e) {
            e.preventDefault();
            var url = btn.getAttribute('data-url');
            if (document.referrer && document.referrer.includes('/admin/dashboard')) {
                window.history.back();
            } else if (url && typeof loadPartial === "function") {
                loadPartial(url);
            } else if (url) {
                window.location.href = url;
            } else {
                window.location.href = '/admin/dashboard';
            }
        };
        btn.addEventListener('click', btn._receivedCheckBackAjax);
    });
};

// جستجوی طرف حساب در فرم ویرایش
window.initReceivedCheckEditForm = function () {
    console.log("initReceivedCheckEditForm Called!"); // خط تست
    let searchInput = document.getElementById('party_search');
    let typeInput = document.getElementById('party_type');
    let idInput = document.getElementById('party_id');
    let suggestions = document.getElementById('party_search_suggestions');
    if (!searchInput) return;

    let timer;
    searchInput.addEventListener('input', function () {
        typeInput.value = '';
        idInput.value = '';
        clearTimeout(timer);
        let q = this.value.trim();
        if (q.length >= 2) {
            timer = setTimeout(() => {
                fetch('/admin/search-party-all?q=' + encodeURIComponent(q))
                    .then(res => res.json())
                    .then(list => {
                        suggestions.innerHTML = '';
                        if (list.length === 0) {
                            suggestions.style.display = 'none';
                            return;
                        }
                        suggestions.style.display = '';
                        list.forEach(item => {
                            let div = document.createElement('div');
                            div.className = 'list-group-item list-group-item-action';
                            div.textContent =
                                `[${item.type_fa}] ` + item.name +
                                (item.national_code ? ' | کدملی: ' + item.national_code : '') +
                                (item.phone ? ' | تلفن: ' + item.phone : '');
                            div.dataset.type = item.type;
                            div.dataset.id = item.id;
                            div.addEventListener('click', function () {
                                searchInput.value = item.name;
                                typeInput.value = item.type;
                                idInput.value = item.id;
                                suggestions.innerHTML = '';
                                suggestions.style.display = 'none';

                                let badge = document.getElementById('selected-party-badge');
                                if (!badge) {
                                    badge = document.createElement('div');
                                    badge.id = 'selected-party-badge';
                                    badge.className = 'mt-2 alert alert-success py-1 px-2 small';
                                    searchInput.parentElement.appendChild(badge);
                                }
                                badge.textContent =
                                    `[${item.type_fa}] ${item.name}` +
                                    (item.national_code ? ' | کدملی: ' + item.national_code : '') +
                                    (item.phone ? ' | تلفن: ' + item.phone : '');
                            });
                            suggestions.appendChild(div);
                        });
                    });
            }, 250);
        } else {
            suggestions.innerHTML = '';
            suggestions.style.display = 'none';
        }
    });
};

// جستجوی هوشمند صادرکننده فقط برای صفحه ایجاد (Create)
window.initReceivedCheckCreateForm = function () {
    let issuerSearch = document.getElementById('issuer_search');
    let issuerType = document.getElementById('issuer_type');
    let issuerId = document.getElementById('issuer_id');
    let issuerSuggestions = document.getElementById('issuer_search_suggestions');
    let issuerInput = document.getElementById('issuer');
    let issuerBadge = document.getElementById('issuer_selected_badge');

    if (issuerSearch) {
        let timer;
        issuerSearch.addEventListener('input', function () {
            issuerType.value = '';
            issuerId.value = '';
            clearTimeout(timer);
            let q = this.value.trim();
            if (q.length >= 0) {
                timer = setTimeout(() => {
                    fetch('/admin/search-party-all?q=' + encodeURIComponent(q))
                        .then(res => res.json())
                        .then(list => {
                            issuerSuggestions.innerHTML = '';
                            if (issuerBadge) issuerBadge.innerHTML = '';
                            if (list.length === 0) {
                                issuerSuggestions.style.display = 'none';
                                return;
                            }
                            issuerSuggestions.style.display = '';
                            list.forEach(item => {
                                let div = document.createElement('div');
                                div.className = 'list-group-item list-group-item-action';
                                div.textContent =
                                    `[${item.type_fa}] ` + item.name +
                                    (item.national_code ? ' | کدملی: ' + item.national_code : '') +
                                    (item.phone ? ' | تلفن: ' + item.phone : '');
                                div.dataset.type = item.type;
                                div.dataset.id = item.id;
                                div.addEventListener('click', function () {
                                    issuerSearch.value = item.name;
                                    issuerType.value = item.type;
                                    issuerId.value = item.id;
                                    issuerSuggestions.innerHTML = '';
                                    issuerSuggestions.style.display = 'none';
                                    if (issuerInput) issuerInput.value = item.name || '';
                                    if (issuerBadge) {
                                        issuerBadge.innerHTML =
                                            `<div class="alert alert-success py-1 px-2 small mb-2">
                                                انتخاب شد: [${item.type_fa}] ${item.name}
                                                ${(item.national_code ? ' | کدملی: ' + item.national_code : '')}
                                                ${(item.phone ? ' | تلفن: ' + item.phone : '')}
                                            </div>`;
                                    }
                                });
                                issuerSuggestions.appendChild(div);
                            });
                        });
                }, 250);
            } else {
                issuerSuggestions.innerHTML = '';
                issuerSuggestions.style.display = 'none';
                if (issuerBadge) issuerBadge.innerHTML = '';
            }
        });
    }
};

document.addEventListener("DOMContentLoaded", function () {
    if (typeof initReceivedCheckForm === "function") initReceivedCheckForm();
});

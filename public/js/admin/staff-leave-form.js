// public/js/admin/staff-leave-form.js
window.initStaffLeaveForm = function() {
    // کنترل نمایش فیلدهای ساعت برای مرخصی ساعتی
    const leaveType = document.getElementById('leave_type');
    if (leaveType) {
        const timeFields = document.querySelectorAll('.field-time');
        function updateFields() {
            if (leaveType.value === 'ساعتی') {
                timeFields.forEach(f => f.style.display = '');
            } else {
                timeFields.forEach(f => f.style.display = 'none');
            }
        }
        leaveType.addEventListener('change', updateFields);
        updateFields();
    }

    // فعال سازی تقویم شمسی فقط روی فیلدهای تاریخ
    if ($('.datepicker').length) {
    $('.datepicker').each(function() {
        let initialVal = $(this).val();
        $(this).persianDatepicker({
            format: 'YYYY/MM/DD',
            observer: true,
            autoClose: true,
            initialValue: !!initialVal, // اگر مقدار اولیه داشت true شود
            initialValueType: 'persian',
            calendar:{
                persian: {
                    locale: 'fa'
                }
            },
            onShow: function() {
                $(this).attr('readonly', true);
            }
        });
    });
}


    // ارسال فرم (ثبت/ویرایش) مرخصی با AJAX
    const form = document.getElementById('staffLeaveForm');
    if (form) {
        form.onsubmit = function(e) {
            e.preventDefault();
            let formData = new FormData(form);
            // اگر edit باشه method باید PUT باشه
            let method = form.getAttribute('method')?.toUpperCase() || 'POST';
            // برای ویرایش (method PUT)، لازمه _method=PUT در فرم باشه (فراخوانی شده در blade)
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.csrfToken || document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            })
            .then(res => {
                let ct = res.headers.get('content-type');
                if (ct && ct.indexOf('application/json') !== -1) {
                    return res.json();
                } else {
                    // حالت fallback برای زمانی که پاسخ html بده
                    return res.text().then(html => ({status:'success', html}));
                }
            })
            .then(resp => {
                if (resp.status === 'success' && resp.html) {
                    let container = document.getElementById('dynamic-content');
                    if (container) {
                        container.innerHTML = resp.html;
                        // مجدد ایندکس رو اینیت کن
                        if (typeof window.initStaffLeaveIndex === "function") window.initStaffLeaveIndex();
                    }
                } else if (resp.errors) {
                    // نمایش خطاها (اگر از ولیدیشن ajax استفاده شد)
                    let err = Object.values(resp.errors).join('<br>');
                    alert(err);
                } else {
                    alert('خطا در ثبت!');
                }
            })
            .catch(err => {
                alert('خطا در ثبت!');
            });
        };
    }
};

// تایید/رد مرخصی با ajax و رفرش لیست
window.initStaffLeaveIndex = function() {
    // دکمه تایید
    $(document).off('click', '.btn-approve').on('click', '.btn-approve', function(e) {
        e.preventDefault();
        let id = $(this).data('id');
        $.post('/admin/staff_leaves/' + id + '/approve', {
            _token: window.csrfToken
        }, function(resp){
            // اگر خروجی json بود:
            if (resp.status === 'success' && resp.html) {
                let container = document.getElementById('dynamic-content');
                if (container) {
                    container.innerHTML = resp.html;
                    if(typeof window.initStaffLeaveIndex === "function") window.initStaffLeaveIndex();
                }
            } else {
                // fallback
                let url = '/admin/staff_leaves';
                fetch(url).then(res=>res.text()).then(html=>{
                    let container = document.getElementById('dynamic-content');
                    if(container) {
                        container.innerHTML = html;
                        if(typeof window.initStaffLeaveIndex === "function") window.initStaffLeaveIndex();
                    }
                });
            }
        }, 'json');
    });

    // دکمه رد
    $(document).off('click', '.btn-reject').on('click', '.btn-reject', function(e) {
        e.preventDefault();
        let id = $(this).data('id');
        $.post('/admin/staff_leaves/' + id + '/reject', {
            _token: window.csrfToken
        }, function(resp){
            if (resp.status === 'success' && resp.html) {
                let container = document.getElementById('dynamic-content');
                if (container) {
                    container.innerHTML = resp.html;
                    if(typeof window.initStaffLeaveIndex === "function") window.initStaffLeaveIndex();
                }
            } else {
                let url = '/admin/staff_leaves';
                fetch(url).then(res=>res.text()).then(html=>{
                    let container = document.getElementById('dynamic-content');
                    if(container) {
                        container.innerHTML = html;
                        if(typeof window.initStaffLeaveIndex === "function") window.initStaffLeaveIndex();
                    }
                });
            }
        }, 'json');
    });


 // دکمه حذف
    $(document).off('click', '.btn-delete-leave').on('click', '.btn-delete-leave', function(e) {
        e.preventDefault();
        let id = $(this).data('id');
        if (!confirm('آیا از حذف این مرخصی مطمئن هستید؟')) return;

        $.ajax({
            url: '/admin/staff_leaves/' + id,
            type: 'POST',
            data: {
                _token: window.csrfToken,
                _method: 'DELETE'
            },
            success: function(resp) {
                // بعد از حذف، لیست را مجدد لود کن
                fetch('/admin/staff_leaves')
                    .then(res => res.text())
                    .then(html => {
                        let container = document.getElementById('dynamic-content');
                        if (container) {
                            container.innerHTML = html;
                            if (typeof window.initStaffLeaveIndex === "function") window.initStaffLeaveIndex();
                        }
                    });
            },
            error: function() {
                alert('خطا در حذف!');
            }
        });
    });










    // دکمه مرخصی جدید (اگر ajax خواستی)
    $(document).off('click', '.menu-ajax[data-url*="staff_leaves/create"]').on('click', '.menu-ajax[data-url*="staff_leaves/create"]', function(e) {
        e.preventDefault();
        let url = $(this).data('url');
        fetch(url)
        .then(res=>res.text())
        .then(html=>{
            let container = document.getElementById('dynamic-content');
            if(container) {
                container.innerHTML = html;
                if(typeof window.initStaffLeaveForm === "function") window.initStaffLeaveForm();
            }
        });
    });

    // دکمه ویرایش مرخصی (ajax)
    $(document).off('click', '.menu-ajax[data-url*="staff_leaves/"][data-url$="/edit"]').on('click', '.menu-ajax[data-url*="staff_leaves/"][data-url$="/edit"]', function(e) {
        e.preventDefault();
        let url = $(this).data('url');
        fetch(url)
        .then(res=>res.text())
        .then(html=>{
            let container = document.getElementById('dynamic-content');
            if(container) {
                container.innerHTML = html;
                if(typeof window.initStaffLeaveForm === "function") window.initStaffLeaveForm();
            }
        });
    });
};

// همیشه بعد از هر بار لود/بارگذاری ajax، دوباره اینیت کن
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.initStaffLeaveForm === "function") window.initStaffLeaveForm();
    if (typeof window.initStaffLeaveIndex === "function") window.initStaffLeaveIndex();
});

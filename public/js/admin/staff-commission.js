// public/js/admin/staff-commission.js


window.initStaffCommissionForm = function() {
    const staffSelect = document.getElementById('staff_select');
    const commissionForm = document.getElementById('commissionForm');
    const skillsRows = document.getElementById('skillsRows');
    const messageDiv = document.getElementById('commission-message');
    let selectedStaffId = null;
    let skillsData = [];

    if (!staffSelect) return;

    staffSelect.addEventListener('change', function() {
        skillsRows.innerHTML = '';
        messageDiv.innerHTML = '';
        if (this.value) {
            selectedStaffId = this.value;
            fetch(`/admin/staff-commissions/${selectedStaffId}/skills`)
                .then(res => res.json())
                .then(data => {
                    skillsData = data.skills;
                    if (skillsData.length) {
                        renderSkills(skillsData);
                        commissionForm.style.display = '';
                    } else {
                        commissionForm.style.display = 'none';
                        messageDiv.innerHTML = '<div class="alert alert-warning">این پرسنل مهارتی ثبت نشده دارد.</div>';
                    }
                });
        } else {
            commissionForm.style.display = 'none';
            selectedStaffId = null;
        }
    });

    function renderSkills(skills) {
        skillsRows.innerHTML = '';
        skills.forEach(function(skill, idx) {
            let row = document.createElement('tr');
            row.innerHTML += `<td>${skill.category_title}</td>`;
            let typeOptions = `
                <option value="percent" ${skill.commission_type === 'percent' ? 'selected' : ''}>درصد</option>
                <option value="amount"  ${skill.commission_type === 'amount' ? 'selected' : ''}>مبلغ</option>
            `;
            row.innerHTML += `<td>
                <select name="commission_type" class="form-select" data-row="${idx}">
                    <option value="">انتخاب کنید</option>
                    ${typeOptions}
                </select>
            </td>`;
            let val = skill.commission_value ?? '';
            row.innerHTML += `<td>
                <input type="number" min="0" name="commission_value" class="form-control" value="${val}" data-row="${idx}">
            </td>`;
            if (skill.commission_type) {
                row.innerHTML += `<td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteCommission(${selectedStaffId},${skill.category_id},this)">حذف</button>
                </td>`;
            } else {
                row.innerHTML += `<td></td>`;
            }
            row.innerHTML += `<input type="hidden" name="category_id" value="${skill.category_id}" data-row="${idx}">`;
            skillsRows.appendChild(row);
        });
    }

    commissionForm.addEventListener('submit', function(e){
        e.preventDefault();
        messageDiv.innerHTML = '';
        let rows = Array.from(skillsRows.querySelectorAll('tr'));
        let commissions = [];
        rows.forEach(function(row, idx){
            let type = row.querySelector('select[name="commission_type"]').value;
            let value = row.querySelector('input[name="commission_value"]').value;
            let catId = row.querySelector('input[name="category_id"]').value;
            if(type && value) {
                commissions.push({
                    category_id: catId,
                    commission_type: type,
                    commission_value: value
                });
            }
        });
        if(!commissions.length) {
            messageDiv.innerHTML = '<div class="alert alert-danger">حداقل یک کمسیون وارد کنید.</div>';
            return;
        }
        fetch(`/admin/staff-commissions/${selectedStaffId}/save`, {
            method: 'POST',
            body: JSON.stringify({commissions: commissions}),
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken
            }
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                messageDiv.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                staffSelect.dispatchEvent(new Event('change'));
            } else {
                messageDiv.innerHTML = `<div class="alert alert-danger">خطا: ${data.message || 'ذخیره نشد'}</div>`;
            }
        }).catch(()=>{
            messageDiv.innerHTML = '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>';
        });
    });

    window.deleteCommission = function(staffId, catId, btn){
        if(!confirm('آیا مطمئن هستید می‌خواهید این کمسیون را حذف کنید؟')) return;
        fetch(`/admin/staff-commissions/${staffId}/delete/${catId}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken
            }
        })
        .then(res => res.json())
        .then(data => {
            if(data.success){
                messageDiv.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                staffSelect.dispatchEvent(new Event('change'));
            }else{
                messageDiv.innerHTML = `<div class="alert alert-danger">${data.message || 'خطا در حذف'}</div>`;
            }
        })
        .catch(()=>{
            messageDiv.innerHTML = '<div class="alert alert-danger">خطا در ارتباط با سرور!</div>';
        });
    }
};

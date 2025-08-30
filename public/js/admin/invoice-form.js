// public/js/admin/invoice-form.js

window.initInvoiceForm = function () {
    const form = document.getElementById('invoiceCreateForm') || document.getElementById('invoiceEditForm');
    if (!form) return;

    // ===== [PATCH] ویرایش فاکتور: پرلود آیتم‌ها + سابمیت Ajax =====
    (function patchEditForm() {
        if (form.id !== 'invoiceEditForm') return;

        // 1) پرلود آیتم‌ها از <div id="invoice-preload" ...>
        const preload = document.getElementById('invoice-preload');
        if (preload) {
            try { window.items = JSON.parse(preload.dataset.items || '[]'); } catch (_) { window.items = []; }
            try { window.currentDiscount = preload.dataset.discount ? JSON.parse(preload.dataset.discount) : null; } catch (_) { window.currentDiscount = null; }
            window._computedTotal = Number(preload.dataset.total || 0);
            // جدول را رندر کن
            if (typeof window.renderItems === 'function') window.renderItems();
        } else {
            if (!Array.isArray(window.items)) window.items = [];
        }

        // 2) جلوگیری از چندبار بایند شدن
        if (form.dataset.ajaxBound === '1') return;
        form.dataset.ajaxBound = '1';

        // 3) سابمیت Ajax (داخل داشبورد بمانیم)
        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            // قبل از ارسال، آیتم‌ها را در فیلد مخفی بریز
            const itemsJson = document.getElementById('items-json');
            try { itemsJson.value = JSON.stringify(window.items || []); } catch (_) { itemsJson.value = '[]'; }

            const fd = new FormData(form);
            // اطمینان از حضور متد PUT (Blade @method('PUT') معمولاً خودش اضافه می‌کند)
            if (!fd.get('_method')) fd.append('_method', 'PUT');

            try {
                const res = await fetch(form.action, {
                    method: form.method || 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                // تلاش برای خواندن JSON؛ اگر نبود، با رفرش ادامه بده
                let data = null;
                const ct = (res.headers.get('content-type') || '').toLowerCase();
                if (ct.includes('application/json')) {
                    data = await res.json().catch(() => null);
                }

                if (!res.ok) {
                    const msg = data?.message || (data?.errors && Object.values(data.errors)[0]) || 'ذخیره ناموفق';
                    alert(msg);
                    return;
                }

                // موفق: دوباره همان صفحه ویرایش را داخل داشبورد لود کن
                const id = (data?.id || data?.invoice_id || document.getElementById('saved-invoice-id')?.value);
                const url = '/admin/invoices/' + id + '/edit';
                if (typeof window.loadPartial === 'function') window.loadPartial(url);
                else location.href = url;
            } catch (err) {
                alert('خطا در ارتباط با سرور');
            }
        });
    })();
    // ===== [END PATCH] =====



    // ===== Fix: preload items & AJAX-submit for EDIT page =====
    (function () {
        try {
            // When page is loaded via the AJAX dashboard (layouts/admin.blade),
            // inline <script> tags inside the partial are removed for safety.
            // So we must hydrate `window.items` from the hidden #invoice-preload node here.
            if (form.id === 'invoiceEditForm') {
                // 1) Preload items/totals from data-* attributes if present
                var preload = document.getElementById('invoice-preload');
                if (preload) {
                    try { window.items = JSON.parse(preload.dataset.items || '[]'); } catch (_) { window.items = []; }
                    try { window.currentDiscount = preload.dataset.discount ? JSON.parse(preload.dataset.discount) : null; } catch (_) { window.currentDiscount = null; }
                    // totals (used by payment builder)
                    window._computedTotal = Number(preload.dataset.total || 0);
                    // set hidden customer_id if exists
                    var cid = document.getElementById('customer_id');
                    if (cid && preload.dataset.customerId) cid.value = preload.dataset.customerId;
                } else {
                    if (!Array.isArray(window.items)) window.items = [];
                }

                // 2) Make sure hidden input `items` is filled right before Ajax creates FormData
                //    The 'formdata' event fires when new FormData(form) is constructed.
                form.addEventListener('formdata', function (ev) {
                    try { ev.formData.set('items', JSON.stringify(window.items || [])); } catch (_) { }
                });

                // 3) Submit the edit form via AJAX so the app stays inside the dashboard
                if (typeof ajaxFormSubmit === 'function') {
                    ajaxFormSubmit('invoiceEditForm', function (data) {
                        // After successful save, reload this edit page inside the dashboard
                        var id = (data && (data.id || data.invoice_id)) || document.getElementById('saved-invoice-id')?.value;
                        var url = '/admin/invoices/' + id + '/edit';
                        if (typeof loadPartial === 'function') {
                            loadPartial(url);
                        } else {
                            // fallback for direct full-page usage
                            location.href = url;
                        }
                    });
                }

                // 4) Render items if renderer exists
                if (typeof window.renderItems === 'function') {
                    window.renderItems();
                }
            }
        } catch (e) { console.error('initInvoiceForm(edit) preload/AJAX error:', e); }
    })();


    // === Helperهای استاندارد دیت‌پیکر (یک‌بار برای همیشه) ===
    function initPDP(el, opts) {
        try {
            const inst = $(el).data('datepicker') || $(el).data('pDatepicker');
            if (inst && typeof inst.destroy === 'function') inst.destroy();
            $(el).next('.pwt-datepicker-container').remove();
        } catch (_) { }
        $(el).persianDatepicker(opts);
        el.dataset.pdpInit = '1';
    }

    window.attachPersianPickers = function (root = document) {
        const nodes = root.querySelectorAll('.jdate, .jdatetime');
        nodes.forEach(el => {
            if (el.dataset.pdpInit === '1') return;

            const withTime = el.classList.contains('jdatetime');
            const initialVal = $(el).val();

            const opts = {
                format: withTime ? 'YYYY/MM/DD HH:mm' : 'YYYY/MM/DD',
                observer: true,
                autoClose: true,
                initialValue: !!initialVal,
                initialValueType: 'persian',
                calendar: { persian: { locale: 'fa' } },
                timePicker: withTime
                    ? { enabled: true, step: 5, hour: { step: 1 }, minute: { step: 5 } }
                    : { enabled: false },
                onShow: function () { $(el).attr('readonly', true); }
            };

            initPDP(el, opts);
        });
    }


    if (form.dataset.inited === '1') return;   // هر بار DOM جدید، این هم صفر است
    form.dataset.inited = '1';
    // پنل تفکیکی را همین‌جا بوت کن (ایمن در برابر چندبار اجرا)
    if (typeof window.bootstrapSplitPane === 'function') {
        window.bootstrapSplitPane();
    }

    // 1) تک‌مرجع: اگر هنوز آرایه‌ای نیست بساز
    if (!Array.isArray(window.items)) window.items = [];
    // 2) چون صفحه با AJAX می‌آید، اینجا آرایه را «درجا» خالی کن (نه با انتساب آرایه جدید)
    if (/\/admin\/invoices\/create(?:$|\/|\?)/.test(location.pathname)) {
        window.items.length = 0;          // ← مهم: همان reference خالی می‌شود
        window.currentDiscount = null;
        window._computedTotal = 0;
    }

    const serviceSelect = form.querySelector('#service-select');
    const categorySelect = form.querySelector('#category-select');
    const staffSelect = form.querySelector('#staff-select');
    const qtyInput = form.querySelector('#item-qty');

    // // فعال سازی دیت تایم پیکر شمسی برای فیلدهای تاریخ فاکتور و آیتم‌ها
    // if ($('.datepicker').length) {
    //     $('.datepicker').each(function () {
    //         if (this.dataset.pdpInit === '1') return; // جلوگیری از دوبار اینیت
    //         this.dataset.pdpInit = '1';
    //         let initialVal = $(this).val();
    //         $(this).persianDatepicker({
    //             format: 'YYYY/MM/DD HH:mm', // فعال‌سازی ساعت همزمان با تاریخ!
    //             observer: true,
    //             autoClose: true,
    //             initialValue: !!initialVal,
    //             initialValueType: 'persian',
    //             calendar: {
    //                 persian: {
    //                     locale: 'fa'
    //                 }
    //             },
    //             timePicker: {
    //                 enabled: true, // حتما این خط را اضافه کن
    //                 step: 5,
    //                 hour: { step: 1 },
    //                 minute: { step: 5 }
    //             },
    //             onShow: function () {
    //                 $(this).attr('readonly', true);
    //             }
    //         });
    //     });
    // }

    window.attachPersianPickers(form);

    // گرفتن المنت‌های صفحه
    // === المان‌های سهم سالن (پنل تفکیکی)
    const splitSalonAccount = document.getElementById('split-salon-account');
    const splitSalonAmount = document.getElementById('split-salon-amount');
    const btnSaveSalon = document.getElementById('btn-save-salon');


    const priceInput = document.getElementById('item-price');
    const dateInput = document.getElementById('item-date');
    const addItemBtn = document.getElementById('add-item-btn');
    const itemsTableBody = document.querySelector('#items-table tbody');
    const totalAmountTd = document.getElementById('total-amount');
    const itemsJson = document.getElementById('items-json');
    const discountCodeInput = document.getElementById('discount-code-input');
    const applyDiscountBtn = document.getElementById('apply-discount-btn');
    const discountMessage = document.getElementById('discount-message');
    const discountAmountTd = document.getElementById('discount-amount');
    const finalAmountTd = document.getElementById('final-amount');
    const discountRow = document.getElementById('discount-row');
    const finalRow = document.getElementById('final-row');
    const refRow = document.getElementById('referrer-row');
    const refAmountTd = document.getElementById('referrer-amount');
    const refBreakdown = document.getElementById('referrer-breakdown');


    // ==== پکیج ====
    const packageSelect = document.getElementById('package-select');
    const addPackageBtn = document.getElementById('add-package-btn');

    async function fillSalonAccountsIfEmpty() {
        const selAgg = document.getElementById('pb-account');            // تجمیعی
        const selSplit = document.getElementById('split-salon-account');   // تفکیکی
        const hasRealOptions = (sel) =>
            sel && Array.from(sel.options || []).some(o => String(o.value || '').trim() !== '');

        if (!selAgg && !selSplit) return; // جایی که UI اصلاً نباشد

        // اگر از قبل گزینه‌های واقعی دارد، کاری نکن
        if ((selAgg && hasRealOptions(selAgg)) || (selSplit && hasRealOptions(selSplit))) return;

        try {
            const res = await fetch('/admin/salon-accounts', {
                credentials: 'include',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const list = await res.json();

            const opts = list.map(a => {
                const extra =
                    a.account_number ? `(${a.account_number})` :
                        (a.card_number ? `(${a.card_number})` :
                            (a.pos_terminal ? `(POS ${a.pos_terminal})` : ''));
                return `<option value="${a.id}">${a.title || 'حساب'} — ${a.bank_name || ''} ${extra}</option>`;
            }).join('');

            if (selAgg) selAgg.innerHTML = `<option value="">انتخاب کنید…</option>${opts}`;
            if (selSplit) selSplit.innerHTML = `<option value="">انتخاب کنید…</option>${opts}`;
        } catch (e) {
            console.error('load salon accounts failed', e);
        }
    }



    // لیست آیتم‌های جدول فاکتور
    let items = window.items;
    // --- هندلر ثبت موقت (AJAX) ---
    (function attachDraftHandler() {
        const form = document.getElementById('invoiceCreateForm');
        const btnDraft = document.getElementById('btn-draft');
        const itemsJson = document.getElementById('items-json');
        const savedIdInput = document.getElementById('saved-invoice-id');
        const badgeInvoice = document.getElementById('status-invoice');
        const afterBox = document.getElementById('after-save-box');

        // اگر قبلاً بایند شده، دوباره وصل نکن
        if (!form || !btnDraft || btnDraft.dataset.bound === '1') return;
        btnDraft.dataset.bound = '1';

        btnDraft.addEventListener('click', async function () {
            try {
                // قبل از ارسال: آیتم‌ها را داخل input مخفی بریز
                if (window.items) itemsJson.value = JSON.stringify(window.items);

                if (!itemsJson.value || itemsJson.value === '[]') {
                    alert('حداقل یک آیتم اضافه کنید.');
                    return;
                }
                if (!document.getElementById('customer_id').value) {
                    alert('مشتری را انتخاب کنید.');
                    return;
                }

                // قبل از ساخت FormData مقدار input مخفی را پر کن
                if (window.items) itemsJson.value = JSON.stringify(window.items);

                // حتماً درون FormData ست کن (اگر input بیرون فرم بود یا name اشتباه بود هم جبران می‌کند)
                const fd = new FormData(form);
                fd.set('items', itemsJson.value);  // ← کلیدی‌ترین خط

                const res = await fetch(form.action, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': window.csrfToken
                    },
                    credentials: 'include'
                });


                let data = null;
                try { data = await res.json(); } catch (_) { }

                if (!res.ok || !data || data.success !== true) {
                    const msg = (data && (data.message || (data.errors && Object.values(data.errors).flat()[0]))) || 'ذخیره پیش‌نویس انجام نشد.';
                    alert(msg);
                    return;
                }

                // هم id و هم invoice_id را ساپورت کن
                const newId = data.invoice_id || data.id;
                if (savedIdInput) savedIdInput.value = newId;

                if (badgeInvoice) {
                    badgeInvoice.className = 'badge bg-success';
                    badgeInvoice.textContent = 'ثبت موقت شد';
                }

                const btnDraft = document.getElementById('btn-draft');
                if (btnDraft) btnDraft.style.display = 'none';

                const afterBox = document.getElementById('after-save-box');
                if (afterBox) {
                    afterBox.style.display = '';
                    afterBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                // با توجه به نوع پرداخت، پانل درست را نشان بده
                if (typeof window.switchPaymentPane === 'function') window.switchPaymentPane();
                // ←←← جمع نهایی و پرداخت‌شده را به سازندهٔ پرداخت بده
                if (window.updatePaymentBuilderTotals) {
                    const fin = data.final_amount ?? data.final ?? data.final_total ?? data.total ?? window._computedTotal ?? 0;
                    const paid = (data.sum_incomes ?? data.paid_amount ?? data.paid ?? 0);
                    window.updatePaymentBuilderTotals(fin, paid);
                }

                // اگر لازم بود فوراً جدول پرسنل را بساز
                if (document.querySelector('select[name="payment_type"]')?.value === 'split') {
                    if (typeof window.initSplitUI === 'function')
                        window.initSplitUI(savedIdInput?.value || newId); // ← نسخه‌ی جدید: split-summary
                }




                // و اگر دوست داری ایونت هم بفرستی:
                document.dispatchEvent(new CustomEvent('invoice:draft-saved'));

            } catch (err) {
                console.error(err);
                alert('خطای غیرمنتظره در ثبت پیش‌نویس.');
            }
        });
    })();

    // افزودن پکیج به جدول آیتم‌ها + واکشی کمیسیون هر خدمت داخلی
    if (addPackageBtn && packageSelect) {
        addPackageBtn.addEventListener('click', function () {
            const selected = packageSelect.options[packageSelect.selectedIndex];
            if (!packageSelect.value) {
                alert('ابتدا یک پکیج انتخاب کنید!');
                return;
            }

            // گرفتن خدمات و پرسنل هر خدمت از سرور
            fetch('/admin/package-detail/' + packageSelect.value, {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(res => res.json())
                .then(async data => {
                    let servicesWithCommission = [];
                    for (let srv of data.services) {
                        let commission_type = null;
                        let commission_value = null;
                        // اگر پرسنل پیش‌فرض و کتگوری دارد، درخواست کمیسیون را بزن
                        if (srv.default_staff_id && srv.category_id) {
                            try {
                                let url = `/admin/staff-commission-value/${srv.default_staff_id}/${srv.category_id}`;
                                let commissionRes = await fetch(url, {
                                    credentials: 'include',
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                                })
                                let commission = await commissionRes.json();
                                commission_type = commission.commission_type;
                                commission_value = commission.commission_value;
                            } catch (e) {
                                commission_type = null;
                                commission_value = null;
                            }
                        }
                        servicesWithCommission.push({
                            service_id: srv.service_id,
                            service_title: srv.service_title,
                            price: srv.price,
                            staff_id: srv.default_staff_id,
                            staff_title: srv.all_staff.find(st => st.id == srv.default_staff_id)?.full_name || '',
                            all_staff: srv.all_staff,
                            category_id: srv.category_id,
                            commission_type: commission_type,
                            commission_value: commission_value
                        });
                    }

                    // افزودن پکیج به آرایه آیتم‌ها
                    const item = {
                        type: 'package',
                        package_id: packageSelect.value,
                        service_title: '[پکیج] ' + selected.text,
                        price: Number(selected.getAttribute('data-price')) || 0,
                        quantity: 1,
                        date: (new Date()).toISOString().split('T')[0],
                        staff_title: '-',
                        services: servicesWithCommission
                    };
                    items.push(item);
                    renderItems();
                    clearDiscount();
                });
        });
    }


    // ---- جدید: پر کردن کمبو خدمات بر اساس دسته‌بندی ----
    // if (categorySelect && serviceSelect) {
    //     categorySelect.addEventListener('change', function () {
    //         var categoryId = this.value;
    //         serviceSelect.innerHTML = '<option value="">در حال بارگذاری...</option>';
    //         serviceSelect.disabled = true;
    //         if (!categoryId) {
    //             serviceSelect.innerHTML = '<option value="">ابتدا دسته‌بندی را انتخاب کنید...</option>';
    //             return;
    //         }
    //         fetch('/admin/services-by-category/' + categoryId, {
    //             credentials: 'include',

    //             headers: {
    //                 'Accept': 'application/json',
    //                 'X-Requested-With': 'XMLHttpRequest'
    //             }
    //         })
    //             .then(res => {
    //                 if (!res.ok) {
    //                     // تلاش می‌کنیم پیام خطا را از JSON بخوانیم
    //                     return res.json().then(j => {
    //                         throw new Error(j.message || 'خطا در دریافت لیست خدمات');
    //                     }).catch(() => {
    //                         throw new Error('خطا در دریافت لیست خدمات');
    //                     });
    //                 }
    //                 return res.json();
    //             })
    //             .then(services => {
    //                 let options = '<option value="">انتخاب کنید...</option>';
    //                 services.forEach(service => {
    //                     options += `<option value="${service.id}" data-price="${service.price}" data-category="${service.category_id}">${service.title}</option>`;
    //                 });
    //                 serviceSelect.innerHTML = options;
    //                 serviceSelect.disabled = false;
    //             })
    //             .catch(err => {
    //                 serviceSelect.innerHTML = '<option value="">خطا در دریافت خدمات</option>';
    //                 serviceSelect.disabled = true;
    //                 console.error(err);
    //             });

    //     });
    // }

    // آپدیت همزمان خدمات و پرسنل با تغییر دسته‌بندی
    if (categorySelect && serviceSelect && staffSelect) {
        categorySelect.addEventListener('change', function () {
            const categoryId = this.value;

            // ----- آپدیت خدمات -----
            serviceSelect.innerHTML = '<option value="">در حال بارگذاری...</option>';
            serviceSelect.disabled = true;
            if (!categoryId) {
                serviceSelect.innerHTML = '<option value="">ابتدا دسته‌بندی را انتخاب کنید...</option>';
                staffSelect.innerHTML = '<option value="">ابتدا دسته‌بندی را انتخاب کنید...</option>';
                staffSelect.disabled = true;
                return;
            }
            fetch('/admin/services-by-category/' + categoryId, {
                credentials: 'include',

                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(res => {
                    if (!res.ok) {
                        return res.json().then(j => {
                            throw new Error(j.message || 'خطا در دریافت لیست خدمات');
                        }).catch(() => {
                            throw new Error('خطا در دریافت لیست خدمات');
                        });
                    }
                    return res.json();
                })
                .then(services => {
                    let options = '<option value="">انتخاب کنید...</option>';
                    services.forEach(service => {
                        options += `<option value="${service.id}" data-price="${service.price}" data-category="${service.category_id}">${service.title}</option>`;
                    });
                    serviceSelect.innerHTML = options;
                    serviceSelect.disabled = false;
                })
                .catch(err => {
                    serviceSelect.innerHTML = '<option value="">خطا در دریافت خدمات</option>';
                    serviceSelect.disabled = true;
                    console.error(err);
                });


            // ----- آپدیت پرسنل -----
            staffSelect.innerHTML = '<option value="">در حال بارگذاری...</option>';
            staffSelect.disabled = true;
            fetch('/admin/staff-by-category/' + categoryId, {
                credentials: 'include',

                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(res => {
                    if (!res.ok) {
                        return res.json().then(j => {
                            throw new Error(j.message || 'خطا در دریافت لیست پرسنل');
                        }).catch(() => {
                            throw new Error('خطا در دریافت لیست پرسنل');
                        });
                    }
                    return res.json();
                })
                .then(staff => {
                    let options = '<option value="">انتخاب کنید...</option>';
                    staff.forEach(st => {
                        options += `<option value="${st.id}">${st.full_name}</option>`;
                    });
                    staffSelect.innerHTML = options;
                    staffSelect.disabled = false;
                })
                .catch(err => {
                    staffSelect.innerHTML = '<option value="">خطا در دریافت پرسنل</option>';
                    staffSelect.disabled = true;
                    console.error(err);
                });

        });
    }

    // اگر اصلاً سرویس وجود ندارد این فرم نیست!
    if (!serviceSelect) return;

    // مقداردهی اولیه تاریخ
    if (dateInput) dateInput.value = (new Date()).toISOString().split('T')[0];

    // ---- قیمت اتوماتیک با انتخاب خدمت ----
    serviceSelect.onchange = null;
    serviceSelect.addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        const price = selected.getAttribute('data-price') || '';
        priceInput.value = price;
    });

    // ---- افزودن آیتم ----
    if (addItemBtn) {
        addItemBtn.onclick = function () {
            var serviceValue = document.getElementById('service-select').value;
            var staffValue = document.getElementById('staff-select').value;
            var qtyValue = document.getElementById('item-qty').value;
            var priceValue = document.getElementById('item-price').value;

            // ساخت لیست پرسنل خدمت
            let allStaffList = [];
            if (staffSelect.options.length > 0) {
                for (let i = 0; i < staffSelect.options.length; i++) {
                    const opt = staffSelect.options[i];
                    if (opt.value) {
                        allStaffList.push({
                            id: parseInt(opt.value),
                            full_name: opt.text,
                        });
                    }
                }
            }

            if (!serviceValue || !staffValue || !qtyValue || !priceValue) {
                alert('همه فیلدها را کامل کنید!');
                return;
            }

            // مقدار دسته‌بندی سرویس
            const selectedService = serviceSelect.options[serviceSelect.selectedIndex];
            const categoryId = selectedService.getAttribute('data-category');

            // ابتدا کمیسیون را واکشی کن و بعد آیتم را اضافه کن
            fetch(`/admin/staff-commission-value/${staffValue}/${categoryId}`, {
                credentials: 'include',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })

                .then(res => res.json())
                .then(data => {
                    const item = {
                        service_id: serviceSelect.value,
                        staff_id: staffSelect.value,
                        category_id: categoryId,
                        quantity: Number(qtyInput.value),
                        price: Number(priceInput.value),
                        date: dateInput ? dateInput.value : null,
                        service_title: selectedService.text,
                        staff_title: staffSelect.options[staffSelect.selectedIndex].text,
                        all_staff: allStaffList,
                        commission_type: data.commission_type,
                        commission_value: data.commission_value,
                        type: 'service'
                    };
                    items.push(item);
                    renderItems();
                    clearItemInputs();
                    clearDiscount();
                });
        };

        // ---- رندر جدول آیتم‌ها ----
        function renderItems() {
            itemsTableBody.innerHTML = '';
            let total = 0;

            items.forEach((item, idx) => {
                if (item.type === 'package') {
                    // مجموع قیمت خدمات داخلی پکیج (حتی اگر خود پکیج price داشت نادیده بگیر!)
                    let packageTotal = 0;
                    let rowsServices = '';
                    if (item.services && item.services.length) {
                        item.services.forEach((srv, srvIdx) => {
                            packageTotal += (srv.price * (srv.quantity || 1));
                            // کمیسیون این سرویس داخلی پکیج را محاسبه و آماده کن
                            let commissionAmount = 0;
                            let commissionCell = '-';
                            if (srv.commission_type && srv.commission_value != null && srv.commission_value !== '') {
                                if (srv.commission_type === 'percent') {
                                    commissionAmount = Math.floor((srv.price * (srv.quantity || 1)) * (srv.commission_value / 100));
                                    commissionCell = `${srv.commission_value}% <span class="text-muted small">(${commissionAmount.toLocaleString()} تومان)</span>`;
                                } else if (srv.commission_type === 'amount') {
                                    commissionAmount = Number(srv.commission_value) * (srv.quantity || 1);
                                    commissionCell = `${Number(srv.commission_value).toLocaleString()} تومان`;
                                }
                            }
                            rowsServices += `
                        <tr>
                            <td>${srv.service_title}</td>
                            <td>
                                <select onchange="window.changePackageStaff(${idx}, ${srvIdx}, this.value)">
                                    ${srv.all_staff.map(st => `<option value="${st.id}" ${st.id == srv.staff_id ? 'selected' : ''}>${st.full_name}</option>`).join('')}
                                </select>
                            </td>
                            <td>${srv.price.toLocaleString()}</td>
                            <td>${commissionCell}</td>
                        </tr>
                    `;
                        });
                    }
                    let row = document.createElement('tr');
                    row.innerHTML = `
                <td colspan="6" style="background: #fcf9e7;">
                    <span class="badge bg-warning text-dark">پکیج</span> ${item.service_title}
                    <span class="ms-3 text-info small">جمع خدمات: ${packageTotal.toLocaleString()} تومان</span>
                    <table class="table table-sm mt-2 mb-0" style="background:#f9f9f9;">
                        <thead>
                            <tr>
                                <th>خدمت</th>
                                <th>پرسنل</th>
                                <th>قیمت</th>
                                <th>کمیسیون پرسنل</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsServices}
                        </tbody>
                    </table>
                </td>
                <td>
                    <button class="btn btn-danger btn-sm" onclick="window.removeItem(${idx})">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            `;
                    itemsTableBody.appendChild(row);
                    total += packageTotal; // جمع همه خدمات پکیج
                } else if (item.type === 'service' || item.type === 'single') {
                    // مقدار کمیسیون ردیف را محاسبه کن
                    let commissionAmount = 0;
                    let commissionCell = '-';
                    if (item.commission_type && item.commission_value != null && item.commission_value !== '') {
                        if (item.commission_type === 'percent') {
                            commissionAmount = Math.floor((item.price * item.quantity) * (item.commission_value / 100));
                            commissionCell = `${item.commission_value}% <span class="text-muted small">(${commissionAmount.toLocaleString()} تومان)</span>`;
                        } else if (item.commission_type === 'amount') {
                            commissionAmount = Number(item.commission_value) * item.quantity;
                            commissionCell = `${Number(item.commission_value).toLocaleString()} تومان`;
                        }
                    }
                    let row = document.createElement('tr');
                    row.innerHTML = `
                <td>${item.service_title}</td>
                <td>
                    <select onchange="window.changeStaff(${idx}, this.value)">
                        ${item.all_staff ? item.all_staff.map(st => `
                            <option value="${st.id}" ${st.id == item.staff_id ? 'selected' : ''}>
                                ${st.full_name}
                            </option>
                        `).join('') : `<option value="${item.staff_id}" selected>${item.staff_title || '-'}</option>`}
                    </select>
                </td>
                <td>${item.quantity}</td>
                <td>${item.price.toLocaleString()}</td>
                <td>${commissionCell}</td>
                <td>${(item.price * item.quantity).toLocaleString()}</td>
                <td>
                    <button class="btn btn-danger btn-sm" onclick="window.removeItem(${idx})"><i class="fa fa-trash"></i></button>
                </td>
            `;
                    itemsTableBody.appendChild(row);
                    total += item.price * item.quantity;
                }
            });

            totalAmountTd.textContent = total.toLocaleString();
            itemsJson.value = JSON.stringify(items);
            window._computedTotal = total; // ← جمع کل را برای تخفیف نگه می‌داریم
            updateReferrerPreview();


            if (window.currentDiscount) applyDiscount(window.currentDiscount);
            window.items = items;
            const savedId = document.getElementById('saved-invoice-id')?.value;
            const isSplit = document.querySelector('select[name="payment_type"]')?.value === 'split';

            const staffSectionEl = document.getElementById('staff-payment-section');

            if (isSplit && savedId && typeof window.initSplitUI === 'function') {
                window.initSplitUI(savedId);
            }
            console.log("window.items = items; Called!"); // خط تست

        }


        // ... سایر توابع مثل renderItems و ...

        // تابع تغییر پرسنل داخلی پکیج و واکشی مجدد کمیسیون
        window.changePackageStaff = async function (pkgIdx, srvIdx, staffId) {
            const pkg = items[pkgIdx];
            const srv = pkg.services[srvIdx];
            srv.staff_id = parseInt(staffId);
            const staffObj = srv.all_staff.find(st => st.id == staffId);
            srv.staff_title = staffObj ? staffObj.full_name : '';
            // کمیسیون جدید را واکشی کن
            let commissionRes = await fetch(`/admin/staff-commission-value/${srv.staff_id}/${srv.category_id}`, {
                credentials: 'include',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }); let commission = await commissionRes.json();
            srv.commission_type = commission.commission_type;
            srv.commission_value = commission.commission_value;
            renderItems();
        };

        // تغییر پرسنل خدمت تکی + واکشی مجدد کمیسیون
        window.changeStaff = function (idx, staffId) {
            const item = items[idx];
            item.staff_id = parseInt(staffId);
            if (item.all_staff) {
                const staffObj = item.all_staff.find(st => st.id == staffId);
                item.staff_title = staffObj ? staffObj.full_name : '';
            }
            // کمیسیون جدید را واکشی کن
            fetch(`/admin/staff-commission-value/${item.staff_id}/${item.category_id}`, {
                credentials: 'include',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(res => res.json())
                .then(data => {
                    item.commission_type = data.commission_type;
                    item.commission_value = data.commission_value;
                    renderItems();
                });
        };

        window.removeItem = function (idx) {
            items.splice(idx, 1);
            renderItems();
            clearDiscount();
        };

        // --- رندر اولیه برای صفحهٔ «ویرایش» (اگر آیتم از سرور preload شده) ---
        if (Array.isArray(items) && items.length) {
            renderItems();
            if (itemsJson) itemsJson.value = JSON.stringify(items);
        }


        function clearItemInputs() {
            serviceSelect.value = '';
            staffSelect.value = '';
            qtyInput.value = 1;
            priceInput.value = '';
            if (dateInput) {
                dateInput.value = (new Date()).toISOString().split('T')[0];
            }
        }

        applyDiscountBtn.onclick = function () {
            const code = discountCodeInput.value.trim();
            if (!code) {
                discountMessage.innerText = 'کد تخفیف وارد نشده!';
                clearDiscount();
                return;
            }
            fetch('/admin/discount-code/check?code=' + encodeURIComponent(code), {
                credentials: 'include',

                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': window.csrfToken
                }
            })
                .then(res => {
                    if (!res.ok) {
                        return res.json().then(j => {
                            throw new Error(j.message || 'خطا در بررسی کد تخفیف');
                        }).catch(() => {
                            throw new Error('خطا در بررسی کد تخفیف');
                        });
                    }
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        const total = Number(window._computedTotal || 0);
                        const discount = data.discount_type === 'percent'
                            ? Math.floor(total * data.value / 100)
                            : Math.min(total, data.value);
                        const final = total - discount;

                        discountAmountTd.innerText = discount.toLocaleString('fa-IR');
                        finalAmountTd.innerText = final.toLocaleString('fa-IR');
                        discountRow.style.display = '';
                        finalRow.style.display = '';
                        discountMessage.innerText = data.message;

                        window.currentDiscount = { ...data, code };
                    } else {
                        discountMessage.innerText = data.message || 'کد تخفیف معتبر نیست!';
                        clearDiscount();
                    }
                })
                .catch(err => {
                    discountMessage.innerText = (err && err.message) ? err.message : 'خطا در بررسی کد تخفیف!';
                    clearDiscount();
                });

        };

        function applyDiscount(discount) {
            const total = Number(window._computedTotal || 0);
            const discountValue = discount.discount_type === 'percent'
                ? Math.floor(total * discount.value / 100)
                : Math.min(total, discount.value);
            const final = total - discountValue;

            discountAmountTd.innerText = discountValue.toLocaleString('fa-IR');
            finalAmountTd.innerText = final.toLocaleString('fa-IR');
            discountRow.style.display = '';
            finalRow.style.display = '';
        }

        function clearDiscount() {
            discountRow.style.display = 'none';
            finalRow.style.display = 'none';
            discountAmountTd.innerText = '۰';
            finalAmountTd.innerText = '۰';
            discountMessage.innerText = '';
            window.currentDiscount = null;
        }

        async function updateReferrerPreview() {
            try {
                if (!refRow || !refAmountTd) return;
                const userId = document.getElementById('customer_id')?.value;
                if (!userId || !window.items || !Array.isArray(window.items) || window.items.length === 0) {
                    refRow.style.display = 'none';
                    if (refBreakdown) refBreakdown.style.display = 'none';
                    return;
                }

                const res = await fetch('/admin/referrer/preview', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': window.csrfToken,
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        user_id: userId,
                        items: JSON.stringify(window.items)
                    })
                });

                const data = await res.json();
                if (!res.ok || !data || data.success !== true || !data.has_referrer) {
                    refRow.style.display = 'none';
                    if (refBreakdown) refBreakdown.style.display = 'none';
                    return;
                }

                const total = Number(data.total || 0);
                if (total > 0) {
                    refAmountTd.textContent = total.toLocaleString() + ' تومان';
                    refRow.style.display = '';
                    if (refBreakdown && Array.isArray(data.details)) {
                        refBreakdown.innerHTML = `
          <div class="card card-body">
            <div class="fw-bold mb-2">
              کمیسیون معرف: ${data.referrer_name || '-'}
              <span class="text-muted">(کد: ${data.referrer_code || '-'})</span>
            </div>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead>
                  <tr>
                    <th>ردیف</th>
                    <th>پایه (خالص پس از کمیسیون پرسنل)</th>
                    <th>قاعده</th>
                    <th>کمیسیون</th>
                  </tr>
                </thead>
                <tbody>
                  ${data.details.map((d, i) => `
                    <tr>
                      <td>${d.label || ('#' + (i + 1))}</td>
                      <td>${Number(d.base || 0).toLocaleString()}</td>
                        <td>${d.rule
                                ? (d.rule.type === 'percent'
                                    ? `${Number(d.rule.value)}٪`
                                    : `${Number(d.rule.value).toLocaleString()} تومان`
                                ) + (d.rule.note ? ` — ${d.rule.note}` : '')
                                : '-'
                            }</td>
                      <td>${Number(d.amount || 0).toLocaleString()}</td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          </div>`;
                        // اگر می‌خوای همیشه باز باشه:
                        refBreakdown.style.display = '';
                    }
                } else {
                    refRow.style.display = 'none';
                    if (refBreakdown) refBreakdown.style.display = 'none';
                }
            } catch (e) {
                console.error('referrer preview error:', e);
                if (refRow) refRow.style.display = 'none';
                if (refBreakdown) refBreakdown.style.display = 'none';
            }
        }
        // ====== سوئیچ بین پنل تجمیعی/تفکیکی + رویدادهای مرتبط ======
        const payTypeSel = document.querySelector('select[name="payment_type"]');
        const afterBox = document.getElementById('after-save-box');
        const aggPane = document.getElementById('pay-aggregate-pane');
        const splitPane = document.getElementById('pay-split-pane');
        const savedIdInput2 = document.getElementById('saved-invoice-id');

        // (1) جدول Split را از سرور بگیر و رندر کن
        window.loadSplitTable = async function (invId) {
            const tbody = document.querySelector('#split-staff-table tbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="6">در حال بارگذاری…</td></tr>';
            try {
                const res = await fetch(`/admin/invoices/${invId}/pending-items`, { credentials: 'include' });
                const j = await res.json();
                const map = {};
                (j.items || []).forEach(it => {
                    const amt = Number(it.staff_commission_amount || 0);
                    if (!it.staff_id) return;
                    // if (!map[it.staff_id]) map[it.staff_id] = { staff_id: it.staff_id, staff_name: it.staff_name, commission: 0, items: [] };
                 
                 if (!map[it.staff_id]) 
    map[it.staff_id] = { staff_id: it.staff_id, staff_name: it.staff_name, commission: 0, items: [] };

                 
// if (!map[it.staff_id]) 
//     map[it.staff_id] = { ...(map[it.staff_id] || {}), staff_id: it.staff_id, staff_name: it.staff_name, commission: 0, items: [] };


                    map[it.staff_id].commission += amt;
                    map[it.staff_id].items.push({ id: it.id, title: it.service_title, commission: amt });
                });

                const values = Object.values(map);
                // محاسبه‌ی کمیسیون کل همه‌ی آیتم‌ها از روی window.items (ثابت و مستقل از پرداخت‌ها)
                // محاسبه‌ی کمیسیون کل همه‌ی آیتم‌ها از روی window.items (ثابت)
                const calcCommission = (type, val, price, qty = 1) => {
                    if (!type || val == null || val === '') return 0;
                    return (type === 'percent')
                        ? Math.floor((Number(price) * Number(qty)) * (Number(val) / 100))
                        : Number(val) * Number(qty);
                };

                let staffTotalAll = 0;
                const allItems = Array.isArray(window.items) ? window.items : [];
                allItems.forEach(it => {
                    if (it.type === 'package' && Array.isArray(it.services)) {
                        it.services.forEach(srv => {
                            staffTotalAll += calcCommission(srv.commission_type, srv.commission_value, srv.price, srv.quantity || 1);
                        });
                    } else if (it.type === 'service') {
                        staffTotalAll += calcCommission(it.commission_type, it.commission_value, it.price, it.quantity || 1);
                    }
                });

                // مبلغ نهایی (final-amount اگر هست، وگرنه total-amount)
                const fa2en = s => s.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
                    .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
                const readNumber = t => Number(fa2en(String(t)).replace(/[^0-9.-]/g, '')) || 0;

                const finalEl = document.getElementById('final-amount');
                const totalEl = document.getElementById('total-amount');
                let finalTotal = readNumber(finalEl?.textContent);
                if (!finalTotal) finalTotal = readNumber(totalEl?.textContent);
                // سهم سالن = مبلغ نهایی − مجموع کل کمیسیون‌ها (یک عدد ثابت)
                const salonShareFixed = Math.max(0, finalTotal - staffTotalAll);
                if (splitSalonAmount) splitSalonAmount.value = salonShareFixed;


                if (!values.length) { tbody.innerHTML = '<tr><td colspan="6">آیتم پرداخت‌نشده‌ای نیست.</td></tr>'; return; }

                const rows = [];
                for (const s of values) {
                    let gws = [];
                    try {
                        const gRes = await fetch(`/admin/staff-payment-gateways/${s.staff_id}`, { credentials: 'include' });
                        gws = gRes.ok ? await gRes.json() : [];
                    } catch (_) { }
                    const options = gws.map(g => {
                        const parts = []; if (g.pos_terminal) parts.push('POS ' + g.pos_terminal); if (g.card_number) parts.push('کارت ' + g.card_number); if (g.bank_account) parts.push('حساب ' + g.bank_account);
                        return `<option value="${g.id}">${parts.join(' / ') || 'درگاه'}</option>`;
                    }).join('');

                    rows.push(`
  <tr data-staff="${s.staff_id}" data-items='${encodeURIComponent(JSON.stringify(s.items))}'>
    <!-- پرسنل -->
    <td>${s.staff_name || '-'}</td>

    <!-- خدمات -->
    <td>${s.items.map(i => `${i.title} <span class="text-muted small">(${i.commission.toLocaleString()}ت)</span>`).join('، ')}</td>

    <!-- کمیسیون -->
    <td class="text-success fw-bold" data-total="${s.commission}">
      ${s.commission.toLocaleString()} تومان
    </td>

    <!-- روش پرداخت -->
    <td>
      <select class="form-select staff-method">
        <option value="pos">POS</option>
        <option value="cash">نقد</option>
        <option value="card_to_card">کارت به کارت</option>
        <option value="account_transfer">انتقال حساب</option>
        <option value="online">آنلاین</option>
        <option value="cheque">چک</option>
      </select>
    </td>

    <!-- تاریخ پرداخت -->
    <td>
      <input type="text" class="form-control staff-paid-at datepicker" placeholder="YYYY/MM/DD HH:mm">
    </td>

    <!-- شماره سند -->
    <td>
      <input type="text" class="form-control staff-ref" placeholder="شماره سند">
    </td>

    <!-- درگاه/کارت -->
    <td>
      <select class="form-select staff-gateway">
        <option value="">انتخاب درگاه/کارت</option>
        ${options}
      </select>
    </td>

    <!-- عملیات -->
    <td>
      <button type="button" class="btn btn-success btn-sm btn-pay-staff">پرداخت شد</button>
    </td>

    <!-- وضعیت -->
    <td class="status">در انتظار</td>
  </tr>
`);

                }
                tbody.innerHTML = rows.join('');

                window.attachPersianPickers(document.getElementById('split-staff-table'));


            } catch (e) {
                console.error(e);
                tbody.innerHTML = '<tr><td colspan="6" class="text-danger">خطا در بارگذاری</td></tr>';
            }
        };





        // (2) خودِ سوئیچر UI
        // سوئیچ‌کردن بین پنل‌ها (دوباره قرار بده اگر نداری)
        window.switchPaymentPane = function () {
            fillSalonAccountsIfEmpty(); // مطمئن شو کمبوها پُر هستند

            const afterBox = document.getElementById('after-save-box');
            const aggPane = document.getElementById('pay-aggregate-pane');
            const splitPane = document.getElementById('pay-split-pane');
            const payTypeSel = document.querySelector('select[name="payment_type"]');
            const savedId = document.getElementById('saved-invoice-id')?.value;

            // فقط بعد از ثبت موقت
            if (!afterBox || afterBox.style.display === 'none' || !savedId) {
                aggPane?.classList.add('d-none');
                splitPane?.classList.add('d-none');
                return;
            }

            const isAgg = (payTypeSel?.value || 'aggregate') === 'aggregate';
            aggPane?.classList.toggle('d-none', !isAgg);
            splitPane?.classList.toggle('d-none', isAgg);

            const badge = document.getElementById('status-payment-type');
            if (badge) badge.textContent = isAgg ? 'تجمیعی' : 'تفکیکی';

            if (!isAgg) {
                // لود جدول پرسنل
                if (typeof window.loadSplitTable === 'function') window.loadSplitTable(savedId);
                // لود پرداخت‌های ثبت‌شده‌ی پرسنل
                if (typeof window.loadStaffPaidTable === 'function') window.loadStaffPaidTable(savedId);
            }
        };

        payTypeSel?.addEventListener('change', window.switchPaymentPane);
        // بعد از ثبت موقت هم رویداد dispatch می‌کنی؛ همان کد باقی بماند
        document.addEventListener('invoice:draft-saved', window.switchPaymentPane);

        // —— helper مشترک ——
        function _nf(n) { return Number(n || 0).toLocaleString('fa-IR'); }
        async function _fillSalonAccountsSelect(sel) {
            try {
                const res = await fetch('/admin/salon-accounts', { credentials: 'include', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const list = await res.json();
                sel.innerHTML = '<option value=\"\">انتخاب کنید…</option>' + (list || []).map(a => {
                    const extra = a.account_number ? `(${a.account_number})` : (a.card_number ? `(${a.card_number})` : (a.pos_terminal ? `(POS ${a.pos_terminal})` : ''));
                    return `<option value=\"${a.id}\">${a.title || 'حساب'} — ${a.bank_name || ''} ${extra}</option>`;
                }).join('');
            } catch (_) { }
        }



        // ====== ویرایش/حذف پرداخت سالن ======
        (function bindAggregateEditDelete() {
            const tb = document.querySelector('#deposits-table tbody');
            if (!tb || tb.dataset.bound === '1') return; tb.dataset.bound = '1';

            const mEl = document.getElementById('depositModal');
            const bsModal = (window.bootstrap && mEl) ? new bootstrap.Modal(mEl) : null;

            tb.addEventListener('click', async (e) => {
                const tr = e.target.closest('tr'); if (!tr) return;
                const id = tr.dataset.id;

                // حذف
                if (e.target.closest('.btn-del-deposit')) {
                    if (!confirm('این پرداخت حذف شود؟')) return;
                    const res = await fetch(`/admin/deposits/${id}`, {
                        method: 'DELETE', credentials: 'include',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.csrfToken }
                    });
                    const j = await res.json().catch(() => ({}));
                    if (!res.ok || !j.success) { alert(j.message || 'حذف ناموفق'); return; }
                    tr.remove();
                    // جمع‌ها
                    if (window.updatePaymentBuilderTotals) window.updatePaymentBuilderTotals(j.final_amount || 0, j.sum_incomes || 0);
                    const st = document.getElementById('status-payment'); if (st) st.textContent = (j.payment_status === 'paid' ? 'تسویه شد' : (j.payment_status === 'partial' ? 'پرداخت جزئی' : 'پرداخت نشده'));
                    return;
                }

                // ویرایش: مودال را با دیتا پر کن
                if (e.target.closest('.btn-edit-deposit')) {
                    const res = await fetch(`/admin/deposits/${id}`, { credentials: 'include', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const j = await res.json();
                    if (!j.success) { alert('خواندن دیتا ناموفق'); return; }

                    document.getElementById('dep-id').value = j.deposit.id;
                    document.getElementById('dep-amount').value = j.deposit.amount || 0;
                    document.getElementById('dep-method').value = j.deposit.payment_method || 'cash';
                    document.getElementById('dep-ref').value = j.deposit.reference_number || '';
                    document.getElementById('dep-paid-at').value = (j.deposit.paid_at || '').toString().replace('T', ' ').slice(0, 16);
                    await _fillSalonAccountsSelect(document.getElementById('dep-account'));
                    if (j.deposit.salon_account_id) document.getElementById('dep-account').value = j.deposit.salon_account_id;
                    document.getElementById('dep-note').value = j.deposit.note || '';

                    // دیت‌پیکر
                    if (window.attachPersianPickers && mEl) window.attachPersianPickers(mEl);
                    bsModal && bsModal.show();
                }
            });

            document.getElementById('dep-save')?.addEventListener('click', async () => {
                const id = document.getElementById('dep-id').value;
                const payload = {
                    amount: Number(document.getElementById('dep-amount').value || 0),
                    method: document.getElementById('dep-method').value,
                    reference_number: document.getElementById('dep-ref').value || null,
                    paid_at: document.getElementById('dep-paid-at').value || null,
                    salon_account_id: document.getElementById('dep-account').value || null,
                    note: document.getElementById('dep-note').value || null
                };
                const res = await fetch(`/admin/deposits/${id}`, {
                    method: 'PUT', credentials: 'include',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.csrfToken },
                    body: JSON.stringify(payload)
                });
                const j = await res.json().catch(() => ({}));
                if (!res.ok || !j.success) { alert(j.message || 'ذخیره ناموفق'); return; }

                // بروزرسانی سطر جدول
                const tr = document.querySelector(`#deposits-table tbody tr[data-id="${id}"]`);
                if (tr) {
                    tr.children[0].textContent = _nf(j.deposit.amount || 0);
                    tr.children[1].textContent = j.deposit.payment_method || '-';
                    tr.children[2].textContent = j.deposit.reference_number || '-';
                    tr.children[3].textContent = (j.deposit.paid_at || '').toString().replace('T', ' ').slice(0, 16);
                }
                if (window.updatePaymentBuilderTotals) window.updatePaymentBuilderTotals(j.final_amount || 0, j.sum_incomes || 0);
                const st = document.getElementById('status-payment'); if (st) st.textContent = (j.payment_status === 'paid' ? 'تسویه شد' : (j.payment_status === 'partial' ? 'پرداخت جزئی' : 'پرداخت نشده'));

                window.bootstrap && bootstrap.Modal.getInstance(document.getElementById('depositModal'))?.hide();
            });
        })();





        // ====== پرداخت‌های پرسنلِ ثبت‌شده ======
        // بارگذاری پرداخت‌های پرسنل در صفحهٔ ویرایش
        window.loadStaffPaidTable = async function (invId) {
            const tbody = document.querySelector('#split-paid-table tbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">در حال بارگذاری…</td></tr>';

            try {
                const res = await fetch(`/admin/invoices/${invId}/staff-payments`, { credentials: 'same-origin' });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const j = await res.json();

                if (!j.items || !j.items.length) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center">موردی ثبت نشده</td></tr>';
                    return;
                }

tbody.innerHTML = j.items.map(r => `
  <tr data-id="${r.id}" data-staff="${r.staff_id}">
    <td>${r.staff_name || '-'}</td>
    <td>${Number(r.amount || 0).toLocaleString('fa-IR')}</td>
    <td>${r.method || '-'}</td>
    <td>${r.ref || ''}</td>
    <td>${r.paid_at || ''}</td>
    <td>
      <button class="btn btn-sm btn-primary sp-edit" data-id="${r.id}">ویرایش</button>
      <button class="btn btn-sm btn-danger sp-del" data-id="${r.id}">حذف</button>
    </td>
  </tr>
`).join('');
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-danger text-center">خطا در بارگذاری پرداخت‌ها</td></tr>';
            }
        };


        (function bindStaffPaidActions() {
            const tb = document.querySelector('#split-paid-table tbody'); if (!tb || tb.dataset.bound === '1') return; tb.dataset.bound = '1';
            const modalEl = document.getElementById('staffPayModal');
            const bsModal = (window.bootstrap && modalEl) ? new bootstrap.Modal(modalEl) : null;

            tb.addEventListener('click', async (e) => {
                const tr = e.target.closest('tr'); if (!tr) return;
                const id = tr.dataset.id;

                // حذف
                if (e.target.closest('.sp-del')) {
                    if (!confirm('این پرداخت پرسنل حذف شود؟')) return;
                    const res = await fetch(`/admin/staff-payments/${id}`, {
                        method: 'DELETE', credentials: 'include',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.csrfToken }
                    });
                    const j = await res.json().catch(() => ({}));
                    if (!res.ok || !j.success) { alert(j.message || 'حذف ناموفق'); return; }
                    tr.remove();
                    return;
                }

                // ویرایش
                if (e.target.closest('.sp-edit')) {
                    // پرکردن گیت‌وی‌های پرسنل
                    try {
                        const staffId = tr.dataset.staff;
                        const gwSel = document.getElementById('sp-gateway');
                        gwSel.innerHTML = '<option value=\"\">انتخاب کنید…</option>';
                        const resGw = await fetch(`/admin/staff-payment-gateways/${staffId}`, { credentials: 'include', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const gws = await resGw.json();
                        gwSel.innerHTML += (gws || []).map(g => {
                            const parts = []; if (g.pos_terminal) parts.push('POS ' + g.pos_terminal); if (g.card_number) parts.push('کارت ' + g.card_number); if (g.bank_account) parts.push('حساب ' + g.bank_account);
                            return `<option value=\"${g.id}\">${parts.join(' / ') || 'درگاه'}</option>`;
                        }).join('');
                    } catch (_) { }

                    document.getElementById('sp-id').value = id;
                    document.getElementById('sp-amount').value = tr.children[1].textContent.replace(/[^\d]/g, '');
                    document.getElementById('sp-method').value = tr.children[2].textContent.trim() || 'cash';
                    document.getElementById('sp-ref').value = tr.children[3].textContent.trim() === '-' ? '' : tr.children[3].textContent.trim();
                    document.getElementById('sp-paid-at').value = tr.children[4].textContent.trim() === '-' ? '' : tr.children[4].textContent.trim();
                    if (window.attachPersianPickers) window.attachPersianPickers(modalEl);
                    bsModal && bsModal.show();
                }
            });

            document.getElementById('sp-save')?.addEventListener('click', async () => {
                const id = document.getElementById('sp-id').value;
                const payload = {
                    amount: Number(document.getElementById('sp-amount').value || 0),
                    method: document.getElementById('sp-method').value,
                    paid_at: document.getElementById('sp-paid-at').value || null,
                    gateway_id: document.getElementById('sp-gateway').value || null,
                    ref: document.getElementById('sp-ref').value || null,
                    note: document.getElementById('sp-note').value || null,
                };
                const res = await fetch(`/admin/staff-payments/${id}`, {
                    method: 'PUT', credentials: 'include',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.csrfToken },
                    body: JSON.stringify(payload)
                });
                const j = await res.json().catch(() => ({}));
                if (!res.ok || !j.success) { alert(j.message || 'ذخیره ناموفق'); return; }

                // رفرش سریع سطر
                const tr = document.querySelector(`#split-paid-table tbody tr[data-id="${id}"]`);
                if (tr) {
                    tr.children[1].textContent = _nf(payload.amount);
                    tr.children[2].textContent = payload.method;
                    tr.children[3].textContent = payload.ref || '-';
                    tr.children[4].textContent = (payload.paid_at || '-');
                }
                window.bootstrap && bootstrap.Modal.getInstance(document.getElementById('staffPayModal'))?.hide();
            });
        })();












        /** =========================
         *  سازنده پرداخت ترکیبی (Aggregate)
         *  ========================= */
        (function paymentBuilder() {
            const methodSel = document.getElementById('pb-method');
            const amountInput = document.getElementById('pb-amount');
            const accountSel = document.getElementById('pb-account');
            const refInput = document.getElementById('pb-ref');
            const paidAtInput = document.getElementById('pb-paid-at');
            const noteInput = document.getElementById('pb-note');
            const addBtn = document.getElementById('pb-add');
            const listTBody = document.querySelector('#pb-list tbody');
            const remainingEl = document.getElementById('pb-remaining');
            const finalEl = document.getElementById('pb-final');
            const paidEl = document.getElementById('pb-paid');
            const dueEl = document.getElementById('pb-due');
            const chequeFields = document.getElementById('cheque-fields');

            if (!methodSel || !listTBody) return; // اگر UI نیست، ادامه نده

            const bankRouted = new Set(['online', 'pos', 'card_to_card', 'account_transfer', 'shaba', 'cheque']);

            // state
            if (!window.invPayment) window.invPayment = { final: 0, paid: 0, staged: [] };

            // ابزارهای کمکی
            const toNum = (x) => Number(x || 0);
            const fmt = (n) => toNum(n).toLocaleString();

            function stagedSum() {
                return window.invPayment.staged.reduce((s, p) => s + toNum(p.amount), 0);
            }
            function remaining() {
                return Math.max(0, toNum(window.invPayment.final) - (toNum(window.invPayment.paid) + stagedSum()));
            }
            function paintSummary() {
                finalEl.textContent = fmt(window.invPayment.final);
                paidEl.textContent = fmt(window.invPayment.paid);
                dueEl.textContent = fmt(remaining());
                remainingEl.textContent = fmt(remaining());
                if (!amountInput.value) amountInput.value = remaining(); // پیش‌فرض
            }
            function renderList() {
                listTBody.innerHTML = '';
                window.invPayment.staged.forEach((p, idx) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
        <td>${fmt(p.amount)}</td>
        <td>${p.method}</td>
        <td>${p.salon_account_id || '-'}</td>
        <td>${p.reference_number || '-'}</td>
        <td>${p.paid_at || '-'}</td>
        <td><button class="btn btn-sm btn-outline-danger" data-rm="${idx}">حذف</button></td>
      `;
                    listTBody.appendChild(tr);
                });
            }
            listTBody.addEventListener('click', (e) => {
                const btn = e.target.closest('button[data-rm]');
                if (!btn) return;
                const i = +btn.dataset.rm;
                window.invPayment.staged.splice(i, 1);
                paintSummary(); renderList();
            });
            // --- Split: Salon payments queue (add/list/commit) ---
            (function bootSplitSalonUI() {
                const methodSel = document.getElementById('split-salon-method');
                const chequeBox = document.getElementById('split-cheque-fields');
                const addBtn = document.getElementById('btn-salon-add');
                const saveBtn = document.getElementById('btn-save-salon');
                const tbody = document.querySelector('#split-salon-list tbody');
                const wrap = document.getElementById('split-salon-list-wrap');
                const sumBox = document.getElementById('split-salon-queued-sum');

                // اگر صفحه‌ی فعلی اصلاً این بخش را ندارد، خارج شو
                if (!addBtn || !tbody) return;

                // آرایه‌ی صف پرداخت‌های سالن
                if (!Array.isArray(window.splitSalonQueue)) window.splitSalonQueue = [];

                // تغییر روش → نمایش/عدم نمایش فیلدهای چک
                if (methodSel && !methodSel.dataset.bound) {
                    methodSel.dataset.bound = '1';
                    methodSel.addEventListener('change', () => {
                        if (chequeBox) chequeBox.style.display = methodSel.value === 'cheque' ? '' : 'none';
                    });
                }

                // رندر جدول صف
                function render() {
                    tbody.innerHTML = '';
                    let sum = 0;
                    window.splitSalonQueue.forEach((row, i) => {
                        sum += Number(row.amount || 0);
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
        <td class="text-end">${Number(row.amount || 0).toLocaleString('fa-IR')}</td>
        <td>${row.method}</td>
        <td>${row.salon_account_id ?? '-'}</td>
        <td>${row.ref ?? '-'}</td>
        <td>${row.paid_at || '-'}</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" data-rm="${i}">حذف</button></td>`;
                        tbody.appendChild(tr);
                    });
                    if (wrap) wrap.style.display = window.splitSalonQueue.length ? '' : 'none';
                    if (sumBox) sumBox.textContent = Number(sum).toLocaleString('fa-IR');
                }

                // حذف آیتم از صف (event delegation)
                if (!tbody.dataset.bound) {
                    tbody.dataset.bound = '1';
                    tbody.addEventListener('click', (e) => {
                        const btn = e.target.closest('button[data-rm]');
                        if (!btn) return;
                        const i = +btn.dataset.rm;
                        window.splitSalonQueue.splice(i, 1);
                        render();
                    });
                }

                // افزودن به لیست
                if (!addBtn.dataset.bound) {
                    addBtn.dataset.bound = '1';
                    addBtn.addEventListener('click', () => {
                        const method = document.getElementById('split-salon-method')?.value || '';
                        const amount = Number(document.getElementById('split-salon-amount')?.value || 0);
                        const account = document.getElementById('split-salon-account')?.value || '';
                        const ref = document.getElementById('split-salon-ref')?.value?.trim() || null;
                        const paidAt = document.getElementById('split-salon-paid-at')?.value || null;
                        const note = document.getElementById('split-salon-note')?.value?.trim() || null;

                        // ولیدیشن ساده سمت کلاینت
                        const needsAccount = (m) => !['wallet'].includes(m);
                        if (!method) return alert('روش پرداخت سالن را انتخاب کنید.');
                        if (amount <= 0) return alert('مبلغ سهم سالن نامعتبر است.');
                        if (needsAccount(method) && method !== 'wallet' && !account) return alert('حساب بانکی سالن را انتخاب کنید.');

                        // در صورت چک
                        let cheque;
                        if (method === 'cheque') {
                            cheque = {
                                serial: document.getElementById('split-cheque-serial')?.value || null,
                                bank: document.getElementById('split-cheque-bank')?.value || null,
                                account: document.getElementById('split-cheque-account')?.value || null,
                                due: document.getElementById('split-cheque-due')?.value || null,
                                issuer: document.getElementById('split-cheque-issuer')?.value || null,
                                note: document.getElementById('split-cheque-note')?.value || null
                            };
                        }

                        window.splitSalonQueue.push({
                            method,
                            amount,
                            paid_at: paidAt,
                            ref,
                            note,
                            salon_account_id: method === 'wallet' ? null : (account ? Number(account) : null),
                            cheque
                        });

                        // پاک‌سازی جزء به جزء
                        document.getElementById('split-salon-amount').value = '';
                        document.getElementById('split-salon-ref').value = '';
                        document.getElementById('split-salon-paid-at').value = '';
                        document.getElementById('split-salon-note').value = '';
                        render();
                    });
                }

                // ثبت کل صف پرداخت‌های سالن
                if (saveBtn && !saveBtn.dataset.bound) {
                    // جلوگیری از بایند قدیمی در فایل (که روی btn-save-salon گوش می‌داد)
                    saveBtn.dataset.spBound = '1';
                    saveBtn.dataset.bound = '1';

                    saveBtn.addEventListener('click', async () => {
                        const invoiceId = document.getElementById('saved-invoice-id')?.value;
                        if (!invoiceId) return alert('ابتدا «ثبت موقت» را انجام دهید.');
                        if (!window.splitSalonQueue.length) return alert('لطفاً حداقل یک پرداخت سالن به لیست اضافه کنید.');

                        try {
                            const res = await fetch(`/admin/invoices/${invoiceId}/split/pay-salon`, {
                                method: 'POST',
                                credentials: 'include',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')?.content) || window.csrfToken,
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({ items: window.splitSalonQueue })
                            });
                            const j = await res.json().catch(() => ({}));
                            if (!res.ok || j.success === false || j.ok === false) {
                                alert(j.message || 'خطا در ثبت پرداخت‌های سالن');
                                return;
                            }
                            // موفق → خالی کردن صف و رفرش جدول پرسنل (اختیاری)
                            window.splitSalonQueue.length = 0;
                            render();
                            if (typeof window.loadSplitTable === 'function') window.loadSplitTable(invoiceId);
                            alert('پرداخت‌های سالن ثبت شد.');
                        } catch (err) {
                            alert(err?.message || 'خطای ارتباطی.');
                        }
                    });
                }

                // رندر اولیه
                render();
            })();

            // همگام‌سازی نمایش فیلدهای چک با انتخاب روش
            methodSel.addEventListener('change', () => {
                const isCheque = methodSel.value === 'cheque';
                if (chequeFields) chequeFields.style.display = isCheque ? '' : 'none';
                paintSummary();
            });

            // افزودن به لیست
            addBtn.addEventListener('click', () => {
                const m = methodSel.value;
                const a = toNum(amountInput.value);
                const rem = remaining();

                if (!m) return alert('روش پرداخت را انتخاب کنید.');
                if (a <= 0) return alert('مبلغ نامعتبر است.');
                if (a > rem) return alert('مبلغ بیشتر از باقی‌مانده است.');

                // محدودیت کیف‌پول
                if (m === 'wallet') {
                    const w = document.getElementById('wallet-payment-input');
                    const bal = toNum(w?.dataset.available || w?.value);
                    if (a > bal) return alert('مبلغ بیش از موجودی کیف‌پول است.');
                }

                const payment = {
                    method: m,
                    amount: a,
                    reference_number: refInput.value || null,
                    note: noteInput.value || null,
                    paid_at: paidAtInput.value || null,
                    salon_account_id: (bankRouted.has(m) && m !== 'wallet') ? (accountSel?.value || null) : null
                };

                if (m === 'cheque') {
                    payment.cheque = {
                        serial: document.getElementById('cheque-serial')?.value || null,
                        bank_name: document.getElementById('cheque-bank')?.value || null,
                        account: document.getElementById('cheque-account')?.value || null,
                        amount: a,
                        issue_date: document.getElementById('pb-paid-at')?.value || null,
                        due_date: document.getElementById('cheque-due')?.value || null,
                        issuer: document.getElementById('cheque-issuer')?.value || null,
                        receiver_note: document.getElementById('cheque-note')?.value || null
                    };
                    if (!payment.cheque.due_date) return alert('تاریخ سررسید چک را وارد کنید.');
                }

                window.invPayment.staged.push(payment);

                // پاکسازی‌های سبک
                refInput.value = '';
                noteInput.value = '';
                amountInput.value = ''; // بعداً با باقی‌مانده پر می‌شود

                paintSummary(); renderList();
            });

            // ثبت یک‌جا
            document.getElementById('pb-commit')?.addEventListener('click', async () => {
                const invoiceId = document.getElementById('saved-invoice-id')?.value;
                if (!invoiceId) return alert('ابتدا «ثبت موقت» را انجام دهید.');
                if (!window.invPayment.staged.length) return alert('هیچ پرداختی در لیست نیست.');

                try {
                    for (const p of window.invPayment.staged) {
                        const res = await fetch(`/admin/invoices/${invoiceId}/deposits`, {
                            method: 'POST',
                            credentials: 'include',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': window.csrfToken,
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(p)
                        });
                        const j = await res.json().catch(() => ({}));
                        if (!res.ok || !j.success) {
                            throw new Error(j.message || 'خطا در ثبت یکی از پرداخت‌ها');
                        }

                        // به جدول رسمی پرداخت‌ها اضافه کن
                        // به جدول رسمی پرداخت‌ها اضافه کن (با data-id تا ویرایش/حذف کار کند)
                        const tb = document.querySelector('#deposits-table tbody');
                        if (tb && j.deposit) {
                            const tr = document.createElement('tr');
                            tr.setAttribute('data-id', String(j.deposit.id));
                            tr.innerHTML = `
    <td>${fmt(j.deposit.amount || 0)}</td>
    <td>${j.deposit.method || '-'}</td>
    <td>${j.deposit.reference_number || '-'}</td>
    <td>${(j.deposit.paid_at || '').toString().replace('T', ' ')}</td>
    <td>
      <button type="button" class="btn btn-sm btn-outline-primary btn-edit-deposit">ویرایش</button>
      <button type="button" class="btn btn-sm btn-outline-danger btn-del-deposit">حذف</button>
    </td>`;
                            tb.prepend(tr);
                        }


                        // پرداخت‌شده را زیاد کن
                        window.invPayment.paid = toNum(window.invPayment.paid) + toNum(p.amount);

                        // چیپ وضعیت را آپدیت کن
                        const st = document.getElementById('status-payment');
                        if (st) st.textContent = (j.payment_status === 'paid' ? 'تسویه شد' : (j.payment_status === 'partial' ? 'پرداخت جزئی' : 'پرداخت نشده'));
                    }

                    // لیست staged را خالی کن
                    window.invPayment.staged.length = 0;
                    paintSummary(); renderList();
                    alert('همه پرداخت‌ها ثبت شد.');
                } catch (err) {
                    alert(err?.message || 'خطا در ثبت پرداخت‌ها');
                }
            });

            // تابع عمومی برای ست‌کردن مبالغ از بیرون (بعد از ثبت موقت)
            window.updatePaymentBuilderTotals = function (finalAmount, paidAmount) {
                window.invPayment.final = toNum(finalAmount);
                window.invPayment.paid = toNum(paidAmount);
                paintSummary(); renderList();
            };

            // بار اول
            paintSummary(); renderList();
        })();


        // const btnSaveDep = document.getElementById('btn-save-deposit');
        // if (btnSaveDep && btnSaveDep.dataset.bound !== '1') {
        //   btnSaveDep.dataset.bound = '1';
        //   btnSaveDep.addEventListener('click', function () {
        //     // دکمه‌ی ثبت یک‌جا را شبیه‌سازی کن
        //     document.getElementById('pb-commit')?.click();
        //   });
        // }


        // (6) کلیک روی «پرداخت شد» در جدول تفکیکی
        // (6) کلیک روی «پرداخت شد» در جدول تفکیکی
        document.getElementById('split-staff-table')?.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-pay-staff');
             if (!btn) return;
            const tr = btn.closest('tr');
            const staffId = +tr.dataset.staff;
            const invoiceId = savedIdInput2?.value;
            if (!invoiceId) return alert('ابتدا «ثبت موقت» را انجام دهید.');

            const items = JSON.parse(decodeURIComponent(tr.dataset.items || '%5B%5D')); // [{id,title,commission}]
            if (!items.length) return;

            const payments = items.map(i => ({ invoice_item_id: i.id, staff_id: staffId, amount: i.commission }));
            const gw = tr.querySelector('.staff-gateway')?.value;
            if (gw) payments.forEach(p => p.staffpaymentgateway_id = +gw);

            // ⬅️⬅️⬅️ دو خطِ اضافه‌شده: خواندن «شماره سند» از سطر و افزودن به هر payment
            const ref = tr.querySelector('.staff-ref')?.value?.trim() || null;
            if (ref) payments.forEach(p => p.ref = ref);

            // 🟢 روش و تاریخ را از خودِ سطر بخوان
            const method = tr.querySelector('.staff-method')?.value || 'cash';
            const paidAt = tr.querySelector('.staff-paid-at')?.value || null;
            // روش‌های بانکی، درگاه می‌خواهند
            const needsGateway = ['pos', 'card_to_card', 'account_transfer', 'shaba', 'online'].includes(method);
            if (needsGateway && !gw) return alert('برای این روش، انتخاب درگاه/کارت پرسنل الزامی است.');

            // const payload = {
            //     payments,
            //     method: method,
            //     paid_at: paidAt,          // می‌توانید خالی بگذارید
            //     description: document.getElementById('split-note')?.value || '',
            //     salon_account_id: null,
            //     salon_amount: 0
            // };

            const payload = {
    payments,
    method: method,
    paid_at: paidAt || null,
    description: document.getElementById('split-note')?.value || ''
};
delete payload.salon_amount;
delete payload.salon_account_id;


            try {
                const res = await fetch(`/admin/invoices/${invoiceId}/pay/split`, {
                    method: 'POST', credentials: 'include',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.csrfToken, 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const j = await res.json().catch(() => ({}));
                if (!res.ok || !j.ok) { alert(j.message || 'خطا در پرداخت تفکیکی'); return; }

                tr.querySelector('.status').textContent = 'پرداخت شد';
                tr.classList.add('table-success');
                const st = document.getElementById('status-payment');
                if (st) st.textContent = (j.payment_status === 'paid' ? 'تسویه شد' : j.payment_status);

                window.loadSplitTable(invoiceId);
            } catch (e) { console.error(e); alert('خطای ارتباطی.'); }
        });

        // ثبت سهم سالن در حالت تفکیکی
        // if (btnSaveSalon && btnSaveSalon.dataset.bound !== '1') {
        //     btnSaveSalon.dataset.bound = '1';
        //     btnSaveSalon.addEventListener('click', async () => {
        //         const invoiceId = document.getElementById('saved-invoice-id')?.value;
        //         if (!invoiceId) return alert('ابتدا «ثبت موقت» را انجام دهید.');
        //         if (!splitSalonAccount?.value) return alert('حساب سالن را انتخاب کنید.');

        //         const amount = Number(splitSalonAmount?.value || 0);
        //         if (amount <= 0) return alert('مبلغ سهم سالن نامعتبر است.');

        //         const payload = {
        //             payments: [], // فقط سهم سالن
        //             method: document.getElementById('split-method').value,
        //             paid_at: document.getElementById('split-paid-at').value || null,
        //             description: document.getElementById('split-note').value || '',
        //             salon_account_id: Number(splitSalonAccount.value),
        //             salon_amount: amount
        //         };

        //         try {
        //             const res = await fetch(`/admin/invoices/${invoiceId}/pay/split`, {
        //                 method: 'POST',
        //                 credentials: 'include',
        //                 headers: {
        //                     'Accept': 'application/json',
        //                     'X-Requested-With': 'XMLHttpRequest',
        //                     'X-CSRF-TOKEN': window.csrfToken,
        //                     'Content-Type': 'application/json'
        //                 },
        //                 body: JSON.stringify(payload)
        //             });
        //             const j = await res.json().catch(() => ({}));
        //             if (!res.ok || !j.ok) { alert(j.message || 'خطا در ثبت سهم سالن'); return; }

        //             console.log('Server response:', j);
        //             const statusPayment = document.getElementById('status-payment');
        //             if (statusPayment && j && typeof j.payment_status !== 'undefined') {
        //                 statusPayment.textContent = j.payment_status === 'paid' ? 'تسویه شد' : (j.payment_status || '');
        //             } else {
        //                 console.warn('status-payment element or payment_status is missing');
        //             } alert('سهم سالن ثبت شد.');
        //             window.loadSplitTable(invoiceId); // تازه‌سازی جدول تفکیکی و محاسبه مجدد
        //         } catch (e) {
        //             console.error(e);
        //             alert('خطای ارتباطی.');
        //         }
        //     });
        // }


        window._invoiceFormInited = true;
    }



};

document.addEventListener('click', (e) => {
    const link = e.target.closest('a[href$="/admin/invoices/create"]');
    if (!link) return;
    if (!Array.isArray(window.items)) window.items = [];
    window.items.length = 0;
    window.currentDiscount = null;
    window._computedTotal = 0;
});


/* ===========================
حذف فاکتور: بایند سراسری (idempotent)
=========================== */
(function bindInvoiceDeleteOnce() {
    if (window.__invDeleteBound__) return;
    window.__invDeleteBound__ = true;

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-delete');
        if (!btn) return;

        e.preventDefault();
        if (!confirm('این فاکتور حذف شود؟')) return;

        const url = btn.dataset.url || btn.getAttribute('href');
        if (!url) { alert('آدرس حذف یافت نشد.'); return; }

        const csrf = (document.querySelector('meta[name="csrf-token"]')?.content) || (window.csrfToken || '');

        try {
            const res = await fetch(url, {
                method: 'DELETE',
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf
                }
            });

            if (res.redirected && /\/admin\/login|\/login/.test(res.url)) {
                alert('ابتدا وارد شوید.');
                return;
            }

            let data = {};
            try { data = await res.json(); } catch { }

            if (res.ok && (data.success || data.ok)) {
                btn.closest('tr')?.remove();
                if (typeof window.recalcInvoiceListTotals === 'function') {
                    window.recalcInvoiceListTotals();
                }
            } else {
                if (res.status === 419) alert('نشست شما منقضی شده. صفحه را رفرش کنید.');
                else if (res.status === 401) alert('ابتدا وارد شوید.');
                else alert(data.message || 'خطا در حذف فاکتور');
            }
        } catch (err) {
            console.error(err);
            alert('خطای ارتباطی هنگام حذف.');
        }
    });
})();

/* ===========================
   محاسبهٔ دوباره کارت جمع‌ها (سراسری)
=========================== */
window.recalcInvoiceListTotals = window.recalcInvoiceListTotals || function () {
    const table = document.getElementById('invoices-table');
    if (!table) return;

    let f = 0, p = 0, d = 0;
    table.querySelectorAll('tbody tr').forEach(tr => {
        if (tr.style.display === 'none') return;
        f += Number(tr.dataset.final || 0);
        p += Number(tr.dataset.paid || 0);
        d += Number(tr.dataset.due || 0);
    });

    const put = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val.toLocaleString() + ' تومان';
    };
    put('ttl-final', f);
    put('ttl-paid', p);
    put('ttl-due', d);
};





// همیشه در بارگذاری کامل صفحه اجرا کن
// همیشه در بارگذاری کامل صفحه اجرا کن
document.addEventListener('DOMContentLoaded', () => { window.initInvoiceForm?.(); });
document.addEventListener('DOMContentLoaded', () => { window.initInvoiceCustomerAutoComplete?.(); });

if (typeof loadPartial === 'function') {
    const originalLoadPartial = loadPartial;
    loadPartial = function (url) {
        originalLoadPartial(url);
        setTimeout(() => window.initInvoiceForm?.(), 600);
        setTimeout(() => window.initInvoiceCustomerAutoComplete?.(), 650);
    };
}

document.addEventListener('DOMContentLoaded', function () {
    // ====== المنت‌های ثابت صفحه ======
    const paymentTypeSelect = document.querySelector('select[name="payment_type"]');
    const staffSection = document.getElementById('staff-payment-section');
    const staffTableBody = document.getElementById('staff-payment-table-body');

    // ====== فرم و دکمه‌های ثبت موقت/نهایی ======
    const form = document.getElementById('invoiceCreateForm');
    const itemsJson = document.getElementById('items-json');
    const btnFinal = document.getElementById('btn-final');  // دکمه ثبت نهایی (اگر وجود داشته باشد)
    const btnDraft = document.getElementById('btn-draft');  // دکمه ثبت موقت (اگر وجود داشته باشد)
    let submitType = null; // 'final' | 'draft' | null

    if (btnFinal) {
        btnFinal.addEventListener('click', function () {
            submitType = 'final';
        });
    }
    if (btnDraft) {
        btnDraft.addEventListener('click', function () {
            submitType = 'draft';
        });
    }

    if (form && itemsJson) {
        form.addEventListener('submit', function (e) {
            // همیشه قبل از ارسال، JSON آیتم‌ها را بروز کن
            try { if (window.items) itemsJson.value = JSON.stringify(window.items); } catch (_) { }

            // اگر کاربر «ثبت نهایی» زد، حداقل یک آیتم لازم است
            if (submitType === 'final' && (!itemsJson.value || itemsJson.value === '[]')) {
                e.preventDefault();
                alert('برای ثبت نهایی باید حداقل یک آیتم اضافه کنید.');
                return;
            }
            // در حالت ثبت موقت (draft) اجازه ارسال بدون آیتم را می‌دهیم (طبق نیاز شما)
            // اگر نخواستی، می‌تونی مثل قبل جلوش رو بگیری.
        });
    }

    // ====== جدول تسویه کمیسیون (لیست درگاه/کارت/حساب هر پرسنل) ======
    async function getStaffAccounts(staffId) {
        try {
            const res = await fetch('/admin/staff-payment-gateways/' + staffId, {
                credentials: 'include',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!res.ok) {
                const j = await res.json().catch(() => ({}));
                throw new Error(j.message || 'خطا در دریافت درگاه‌های پرسنل');
            }

            const gateways = await res.json(); // [{id,pos_terminal,bank_account,card_number},...]
            return gateways.flatMap(g => {
                const opts = [];
                if (g.pos_terminal) opts.push({ id: g.id, title: 'POS ' + g.pos_terminal });
                if (g.card_number) opts.push({ id: g.id, title: 'کارت ' + g.card_number });
                if (g.bank_account) opts.push({ id: g.id, title: 'حساب ' + g.bank_account });
                return opts.length ? opts : [{ id: g.id, title: 'درگاه' }];
            });
        } catch (err) {
            console.error('getStaffAccounts error:', err);
            return [];
        }
    }


    // همیشه تابع را روی window بگذار تا از جاهای دیگر هم قابل‌فراخوانی باشد
    window.renderStaffTable = async function () {
        // برای اطمینان، دوباره المنت‌ها را از DOM بگیر (مثل نسخه قدیم)
        const _paymentTypeSelect = document.querySelector('select[name="payment_type"]');
        const _staffSection = document.getElementById('staff-payment-section');
        const _staffTableBody = document.getElementById('staff-payment-table-body');

        if (!_staffTableBody) return;

        _staffTableBody.innerHTML = '';
        let staffMap = {};

        // ایمنی: اگر window.items هنوز مقدار دهی نشده، خالی در نظر بگیر
        const currentItems = Array.isArray(window.items) ? window.items : [];

        currentItems.forEach(item => {
            // پکیج: خدمات داخلی را بررسی کن
            if (item.type === 'package' && Array.isArray(item.services)) {
                item.services.forEach(service => {
                    if (!service.staff_id) return;
                    if (!staffMap[service.staff_id]) {
                        staffMap[service.staff_id] = {
                            staff_id: service.staff_id,
                            staff_title: service.staff_title,
                            commission: 0,
                            services: [],
                        };
                    }
                    // محاسبه کمیسیون
                    let commissionAmount = 0;
                    if (service.commission_type === 'percent') {
                        commissionAmount = Math.floor((service.price * (service.quantity || 1)) * (service.commission_value / 100));
                    } else if (service.commission_type === 'amount') {
                        commissionAmount = Number(service.commission_value) * (service.quantity || 1);
                    }
                    staffMap[service.staff_id].commission += commissionAmount;
                    staffMap[service.staff_id].services.push(service.service_title + ' (' + commissionAmount.toLocaleString() + 'ت)');
                });
            }
            // آیتم‌های عادی (service)
            else if (item.staff_id) {
                if (!staffMap[item.staff_id]) {
                    staffMap[item.staff_id] = {
                        staff_id: item.staff_id,
                        staff_title: item.staff_title,
                        commission: 0,
                        services: [],
                    };
                }
                let commissionAmount = 0;
                if (item.commission_type === 'percent') {
                    commissionAmount = Math.floor((item.price * item.quantity) * (item.commission_value / 100));
                } else if (item.commission_type === 'amount') {
                    commissionAmount = Number(item.commission_value) * item.quantity;
                }
                staffMap[item.staff_id].commission += commissionAmount;
                staffMap[item.staff_id].services.push(item.service_title + ' (' + commissionAmount.toLocaleString() + 'ت)');
            }
        });

        // اگر نوع پرداخت split نیست، سکشن را مخفی کن و برگرد
        if (_paymentTypeSelect && _paymentTypeSelect.value !== 'split') {
            if (_staffSection) _staffSection.style.display = 'none';
            _staffTableBody.innerHTML = '';
            return;
        }

        // ساخت ردیف‌های جدول
        for (let staffId in staffMap) {
            const s = staffMap[staffId];
            const accounts = await getStaffAccounts(staffId);
            const servicesStr = s.services.join('، ');

            const row = document.createElement('tr');
            row.innerHTML = `
<td>${s.staff_title || '-'}</td>
<td>${servicesStr || '-'}</td>
<td class="text-success fw-bold">${(s.commission || 0).toLocaleString()} تومان</td>
<td>
    <select class="form-select" name="staff_account[${staffId}]">
        <option value="">انتخاب درگاه/کارت</option>
        ${accounts.map(acc => `<option value="${acc.id}">${acc.title}</option>`).join('')}
    </select>
</td>
<td>
    <button type="button" class="btn btn-success btn-sm pay-btn" data-staff="${staffId}">
        پرداخت شد
    </button>
</td>
<td class="payment-status" id="status-${staffId}">در انتظار</td>
            `;
            _staffTableBody.appendChild(row);
        }

        // نمایش سکشن
        if (_staffSection) _staffSection.style.display = '';
        console.log('renderStaffTable → rows:', Object.keys(staffMap).length);
    };

    // تغییر نوع پرداخت → نمایش/مخفی‌سازی سکشن و رندر جدول
    // تغییر نوع پرداخت → فقط بعد از «ثبت موقت» نمایش بده
    if (paymentTypeSelect) {
        paymentTypeSelect.addEventListener('change', function () {
            const savedId = document.getElementById('saved-invoice-id')?.value;
            const isSplit = this.value === 'split';

            if (isSplit && savedId) {
                staffSection?.style.setProperty('display', '');
                if (typeof window.renderStaffTable === 'function') window.renderStaffTable();
            } else {
                staffSection?.style.setProperty('display', 'none');
                if (staffTableBody) staffTableBody.innerHTML = '';
            }
        });

        // وضعیت اولیه صفحه
        (function bootstrapStaffPane() {
            const savedId = document.getElementById('saved-invoice-id')?.value;
            const isSplit = paymentTypeSelect.value === 'split';

            if (isSplit && savedId) {
                staffSection?.style.setProperty('display', '');
                if (typeof window.renderStaffTable === 'function') window.renderStaffTable();
            } else {
                staffSection?.style.setProperty('display', 'none');
                if (staffTableBody) staffTableBody.innerHTML = '';
            }
        })();
    }

});





window.initInvoiceCustomerAutoComplete = function () {
    console.log("initInvoiceCustomerAutoComplete Called!"); // خط تست

    let searchInput = document.getElementById('customer_search');
    let idInput = document.getElementById('customer_id');
    let suggestions = document.getElementById('customer_search_suggestions');
    let badge = document.getElementById('customer_selected_badge');
    if (!searchInput) return;
    let timer;
    searchInput.addEventListener('input', function () {
        const w = document.getElementById('wallet-payment-input');
        if (w) { w.value = 0; w.dataset.available = 0; }

        idInput.value = '';
        badge.innerHTML = '';
        clearTimeout(timer);
        let q = this.value.trim();
        if (q.length >= 2) {
            timer = setTimeout(function () {
                fetch('/admin/customer-autocomplete?q=' + encodeURIComponent(q), {
                    credentials: 'include',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
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
                            div.textContent = `${item.full_name} (${item.phone})`
                                + (item.national_code ? ' | ' + item.national_code : '');
                            div.dataset.id = item.id;
                            div.addEventListener('click', function () {
                                searchInput.value = item.full_name + (item.phone ? ' (' + item.phone + ')' : '');
                                idInput.value = item.id;
                                suggestions.innerHTML = '';
                                suggestions.style.display = 'none';
                                badge.innerHTML = `<div class="alert alert-success py-1 px-2 small mb-2">
                                    انتخاب شد: ${item.full_name} ${(item.phone ? ' | ' + item.phone : '')} ${(item.national_code ? ' | ' + item.national_code : '')}
                                </div>`;
                                // 👇👇 این بلوک را همین‌جا اضافه کن
                                fetch(`/admin/customer-wallet-balance?user_id=${item.id}`, {
                                    credentials: 'include',
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                                })
                                    .then(r => r.json())
                                    .then(j => {
                                        const w = document.getElementById('wallet-payment-input');
                                        if (w) {
                                            const bal = Number(j.balance || 0);
                                            w.value = bal;                 // نمایش موجودی
                                            w.dataset.available = bal;     // برای اعتبارسنجی کلاینتی
                                        }
                                    })
                                    .catch(() => { });
                                // 👆👆
                            });
                            suggestions.appendChild(div);
                        });
                    });
            }, 250);
        } else {
            suggestions.innerHTML = '';
            suggestions.style.display = 'none';
            badge.innerHTML = '';
        }
    });



    // ————————————————————————————————————————————————
    // نسخهٔ کامل داخل یک تابع:
    // هیچ فایل جدا لازم نیست؛ هر بار امن و idempotent
    // ————————————————————————————————————————————————
    window.bootstrapSplitPane = async function () {
        // 0) گارد اینکه این UI دوبار بوت نشه
        const root = document.getElementById('pay-split-pane');
        if (!root || root.dataset.spBooted === '1') return;
        root.dataset.spBooted = '1';

        // 1) یک‌بار برای همیشه: پچ fetch و تزریق CSS
        (function patchFetchOnce() {
            if (window.__SP_FETCH_PATCHED__) return;
            const _fetch = window.fetch.bind(window);
            window.fetch = async function (input, init = {}) {
                try {
                    const url = typeof input === 'string' ? input : (input?.url || '');
                    const isSplitPay = /\/admin\/invoices\/\d+\/pay\/split\b/.test(url);
                    const isPost = (init?.method || '').toUpperCase() === 'POST';
                    const hdr = new Headers(init?.headers || {});
                    const isJson = (hdr.get('Content-Type') || '').includes('application/json');

                    if (isSplitPay && isPost && isJson && typeof init.body === 'string') {
                        const payload = JSON.parse(init.body || '{}') || {};

                        // اگر سهم سالن پرداخت نمی‌شود، کلیدهای سالن را حذف کن تا 422 نگیری
                        const sa = Number(payload.salon_amount || 0);
                        if (!sa || sa <= 0) {
                            delete payload.salon_amount;
                            delete payload.salon_account_id;
                        }

                        // تمیزکاری مقدارهای خالی
                        ['description', 'paid_at', 'ref', 'reference_number', 'note'].forEach(k => {
                            if (payload[k] === '') payload[k] = null;
                        });

                        init.body = JSON.stringify(payload);
                    }
                } catch (_) { /* ignore */ }

                return _fetch(input, init);
            };
            window.__SP_FETCH_PATCHED__ = true;
        })();

        (function injectSplitCssOnce() {
            if (document.getElementById('sp-inline-style')) return;
            const style = document.createElement('style');
            style.id = 'sp-inline-style';
            style.innerHTML = `
      #pay-split-pane .table-responsive { overflow-x: visible !important; }
      #pay-split-pane table { width: 100% !important; min-width: 0 !important; table-layout: auto !important; }
      #pay-split-pane th, #pay-split-pane td { white-space: normal !important; }
      #pay-split-pane thead th[style*="min-width"] { min-width: auto !important; }
    `;
            document.head.appendChild(style);
        })();

        // 2) Helperها (فقط داخل همین تابع)
        const _fa2en = s => String(s ?? '')
            .replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
            .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
        const _demoney = s => Number(_fa2en(s).replace(/[^\d.-]/g, '')) || 0;
        const _money = n => Number(n || 0).toLocaleString('fa-IR');
        const _needsAccount = m => ['pos', 'card_to_card', 'account_transfer', 'shaba', 'online', 'cheque'].includes(m);

        function _hideHelperTexts() {
            document.querySelectorAll('#pay-split-pane .form-text, #pb-builder .form-text')
                .forEach(el => { el.style.display = 'none'; });
        }


        async function _initPersianPickers(rootNode = document) {
            window.attachPersianPickers(rootNode);

            async function _fillSalonAccountsIfEmpty() {
                const selAgg = document.getElementById('pb-account');
                const selSplit = document.getElementById('split-salon-account');
                if (!selAgg && !selSplit) return;

                const hasRealOptions = (sel) =>
                    sel && Array.from(sel.options || []).some(o => String(o.value || '').trim() !== '');

                const needAgg = selAgg && !hasRealOptions(selAgg);
                const needSplit = selSplit && !hasRealOptions(selSplit);
                if (!needAgg && !needSplit) return;

                try {
                    const res = await fetch('/admin/salon-accounts', {
                        credentials: 'include',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const list = await res.json();
                    const opts = (list || []).map(a => {
                        const extra =
                            a.account_number ? `(${a.account_number})` :
                                (a.card_number ? `(${a.card_number})` :
                                    (a.pos_terminal ? `(POS ${a.pos_terminal})` : ''));
                        return `<option value="${a.id}">${a.title || 'حساب'} — ${a.bank_name || ''} ${extra}</option>`;
                    }).join('');

                    if (selAgg && needAgg) selAgg.innerHTML = `<option value="">انتخاب کنید…</option>${opts}`;
                    if (selSplit && needSplit) selSplit.innerHTML = `<option value="">انتخاب کنید…</option>${opts}`;
                } catch (e) { console.error('load salon accounts failed', e); }
            }



            function _computeSalonBadges() {
                const finalEl = document.getElementById('final-amount');
                const totalEl = document.getElementById('total-amount');
                let finalTotal = _demoney(finalEl?.textContent || '') || _demoney(totalEl?.textContent || '0');

                // مجموع کمیسیون‌های پرسنل از window.items
                const items = Array.isArray(window.items) ? window.items : [];
                const calc = (type, val, price, qty = 1) => {
                    if (!type || val == null || val === '') return 0;
                    return type === 'percent'
                        ? Math.floor((Number(price) * Number(qty)) * (Number(val) / 100))
                        : Number(val) * Number(qty);
                };
                let staffAll = 0;
                items.forEach(it => {
                    if (it.type === 'package' && Array.isArray(it.services)) {
                        it.services.forEach(s => staffAll += calc(s.commission_type, s.commission_value, s.price, s.quantity || 1));
                    } else if (it.type === 'service') {
                        staffAll += calc(it.commission_type, it.commission_value, it.price, it.quantity || 1);
                    }
                });

                // کمیسیون معرف (در صورت نمایش)
                const refAmount = _demoney(document.getElementById('referrer-amount')?.textContent || '0');
                const salonShare = Math.max(0, finalTotal - staffAll - refAmount);

                // پُر کردن مقادیر (اگر عناصر وجود دارند)
                const put = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = _money(val); };
                put('salon-final', finalTotal);
                put('salon-staff-comm', staffAll);
                put('salon-referrer', refAmount);
                put('salon-share-total', salonShare);

                // paid/due (نمایش ساده بدون تماس سرور)
                const paid = 0;
                const due = Math.max(0, salonShare - paid);
                put('salon-share-paid', paid);
                const dueEl = document.getElementById('salon-share-due');
                if (dueEl) {
                    dueEl.textContent = _money(due);
                    dueEl.classList.toggle('text-danger', due > 0);
                    dueEl.classList.toggle('text-success', due <= 0);
                }

                // چیپ‌ها (اگر باشند)
                const staffBadge = document.getElementById('split-staff-due-badge');
                if (staffBadge) staffBadge.textContent = staffBadge.textContent || _money(staffAll);
                const salonBadge = document.getElementById('split-salon-due-badge');
                if (salonBadge) {
                    salonBadge.textContent = _money(due);
                    salonBadge.classList.toggle('text-danger', due > 0);
                    salonBadge.classList.toggle('text-success', due <= 0);
                }
                const refBadge = document.getElementById('split-referrer-badge');
                if (refBadge) refBadge.textContent = _money(refAmount);
            }

            function _bindRowPickerBoost() {
                const tbl = document.getElementById('split-staff-table');
                if (!tbl || tbl.dataset.spBound === '1') return;
                tbl.dataset.spBound = '1';
                tbl.addEventListener('click', () => _initPersianPickers(tbl));
            }

            function _bindSalonCommitIfNeeded() {
                const btn = document.getElementById('btn-save-salon');
                if (!btn || btn.dataset.spBound === '1') return;
                btn.dataset.spBound = '1';

                btn.addEventListener('click', async () => {
                    const invoiceId = document.getElementById('saved-invoice-id')?.value;
                    if (!invoiceId) return alert('ابتدا «ثبت موقت» را انجام دهید.');

                    const method = document.getElementById('split-salon-method')?.value || '';
                    const amount = Number(document.getElementById('split-salon-amount')?.value || 0);
                    const account = document.getElementById('split-salon-account')?.value || '';
                    const ref = document.getElementById('split-ref')?.value?.trim()
                        || document.getElementById('split-salon-ref')?.value?.trim()
                        || null;
                    const paidAt = document.getElementById('split-paid-at')?.value
                        || document.getElementById('split-salon-paid-at')?.value
                        || null;
                    const note = document.getElementById('split-note')?.value || null;

                    if (!method) return alert('روش پرداخت سالن را انتخاب کنید.');
                    if (!amount || amount <= 0) return alert('مبلغ سهم سالن نامعتبر است.');
                    if (_needsAccount(method) && method !== 'wallet' && !account) return alert('حساب بانکی سالن را انتخاب کنید.');

                    const payload = {
                        payments: [],                 // فقط سهم سالن
                        method: method,
                        paid_at: paidAt || null,
                        description: note || null,
                        salon_amount: amount,
                        salon_account_id: account ? Number(account) : null,
                        ref: ref || null
                    };

                    try {
                        const res = await fetch(`/admin/invoices/${invoiceId}/pay/split`, {
                            method: 'POST',
                            credentials: 'include',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': window.csrfToken,
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });
                        const j = await res.json().catch(() => ({}));
                        if (!res.ok || !j.ok) {
                            alert(j.message || 'خطا در ثبت سهم سالن');
                            return;
                        }
                        const st = document.getElementById('status-payment');
                        if (st) st.textContent = (j.payment_status === 'paid' ? 'تسویه شد' : (j.payment_status || ''));

                        // تازه‌سازی
                        if (typeof window.loadSplitTable === 'function') {
                            window.loadSplitTable(invoiceId);
                        }
                        _computeSalonBadges();
                        alert('سهم سالن ثبت شد.');
                    } catch (e) {
                        console.error(e);
                        alert('خطای ارتباطی.');
                    }
                });
            }

            // 3) بوت UI هر بار
            _hideHelperTexts();
            // _initPersianPickers();
            _initPersianPickers(root);


            await _fillSalonAccountsIfEmpty();

            _bindSalonCommitIfNeeded();
            _bindRowPickerBoost();


            // اگر فاکتور ثبت موقت شده و حالت split است، جدول را با کد فعلی‌ات لود کن
            // اگر فاکتور ثبت موقت شده و حالت split است
            const savedId = document.getElementById('saved-invoice-id')?.value;
            const payType = document.querySelector('select[name="payment_type"]')?.value || 'aggregate';
            if (savedId && payType === 'split') {
                // اگر جدول آیتم‌های تفکیکی داری، آن را هم لود کن
                if (typeof window.loadSplitTable === 'function') {
                    await window.loadSplitTable(savedId);
                }
                // پرداخت‌های ثبت‌شده‌ی پرسنل را «همیشه» لود کن
                if (typeof window.loadStaffPaidTable === 'function') {
                    await window.loadStaffPaidTable(savedId);
                }
            }

            _computeSalonBadges();
        };

        // اگر لازم داری از بیرون صدا بزنی:
        window.initSplitUI = (invoiceId) => {
            const saved = document.getElementById('saved-invoice-id');
            if (saved && invoiceId) saved.value = invoiceId;
            window.bootstrapSplitPane();
        };




        /* === Invoices Index (لیست فاکتورها) — همه در همین فایل === */
        (function () {
            // فقط اگر جدول لیست فاکتورها وجود دارد، این بخش را بوت کن
            function initInvoicesIndexPage() {
                const table = document.getElementById('invoices-table');
                if (!table || table.dataset.bound === '1') return;
                table.dataset.bound = '1';

                // 1) دیت‌پیکرهای فرم فیلتر (yyyy/mm/dd بدون ساعت)
                function attachSimpleDatePickers(root = document) {
                    const nodes = root.querySelectorAll('.datepicker');
                    nodes.forEach(el => {
                        if (el.dataset.pdpInit === '1') return;
                        const initialVal = window.$ ? window.$(el).val() : (el.value || '');
                        const opts = {
                            format: 'YYYY/MM/DD',
                            observer: true,
                            autoClose: true,
                            initialValue: !!initialVal,
                            initialValueType: 'persian',
                            calendar: { persian: { locale: 'fa' } },
                            timePicker: { enabled: false },
                            onShow: function () { try { $(el).attr('readonly', true); } catch (_) { } }
                        };
                        try {
                            // از initPDP موجود استفاده می‌کنیم تا نمونه‌های قبلی clean شوند
                            if (typeof initPDP === 'function') initPDP(el, opts);
                            else if (window.$) $(el).persianDatepicker(opts);
                            el.dataset.pdpInit = '1';
                        } catch (_) { }
                    });
                }
                attachSimpleDatePickers();

                // 2) tooltips بوت‌استرپ (اختیاری)
                if (window.bootstrap) {
                    const tt = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tt.map(el => new bootstrap.Tooltip(el));
                }

                // 3) ذخیره/اعمال فیلترها در localStorage
                (function persistFilters() {
                    const ids = ['flt-customer', 'flt-from', 'flt-to', 'flt-status'];
                    ids.forEach(id => {
                        const el = document.getElementById(id);
                        if (!el) return;
                        const key = 'invflt:' + id;
                        if (!el.value && localStorage.getItem(key)) el.value = localStorage.getItem(key);
                        el.addEventListener('change', () => localStorage.setItem(key, el.value));
                    });
                    document.getElementById('btn-clear-filters')?.addEventListener('click', () => {
                        ids.forEach(id => {
                            localStorage.removeItem('invflt:' + id);
                            const el = document.getElementById(id);
                            if (el) el.value = '';
                        });
                    });
                })();

                // 4) انتخاب همه
                const chkAll = document.getElementById('chk-all');
                chkAll?.addEventListener('change', () => {
                    table.querySelectorAll('.row-check').forEach(c => c.checked = chkAll.checked);
                });

                // 5) جستجوی زنده روی جدول همین صفحه
                const live = document.getElementById('live-search');
                const calcTotals = () => {
                    let f = 0, p = 0, d = 0;
                    table.querySelectorAll('tbody tr').forEach(tr => {
                        if (tr.style.display === 'none') return;
                        f += Number(tr.dataset.final || 0);
                        p += Number(tr.dataset.paid || 0);
                        d += Number(tr.dataset.due || 0);
                    });
                    const put = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val.toLocaleString() + ' تومان'; };
                    put('ttl-final', f); put('ttl-paid', p); put('ttl-due', d);
                };
                live?.addEventListener('input', () => {
                    const q = live.value.trim().toLowerCase();
                    table.querySelectorAll('tbody tr').forEach(tr => {
                        const t = (tr.dataset.search || '').toLowerCase();
                        tr.style.display = t.includes(q) ? '' : 'none';
                    });
                    calcTotals();
                });

                // 6) سورت ساده کلاینتی
                table.querySelectorAll('thead th[data-sort]').forEach((th, idx) => {
                    th.style.cursor = 'pointer';
                    th.addEventListener('click', () => {
                        const dir = th.dataset.dir === 'asc' ? 'desc' : 'asc';
                        th.dataset.dir = dir;
                        const rows = Array.from(table.querySelectorAll('tbody tr'));
                        const type = th.dataset.sort;
                        rows.sort((a, b) => {
                            let av, bv;
                            switch (type) {
                                case 'num':
                                    av = +a.children[idx].innerText.replace(/,/g, '');
                                    bv = +b.children[idx].innerText.replace(/,/g, '');
                                    break;
                                case 'int':
                                    av = +a.children[idx].innerText.replace(/[^\d]/g, '');
                                    bv = +b.children[idx].innerText.replace(/[^\d]/g, '');
                                    break;
                                case 'date':
                                    av = new Date(a.dataset.date);
                                    bv = new Date(b.dataset.date);
                                    break;
                                default:
                                    av = (a.children[idx].innerText || '').trim();
                                    bv = (b.children[idx].innerText || '').trim();
                            }
                            return dir === 'asc' ? (av > bv ? 1 : -1) : (av < bv ? 1 : -1);
                        });
                        const tb = table.querySelector('tbody');
                        rows.forEach(r => tb.appendChild(r));
                        calcTotals();
                    });
                });

                // 7) چاپ
                document.getElementById('btn-print')?.addEventListener('click', () => {
                    const win = window.open('', '_blank');
                    const html = `
      <html dir="rtl"><head>
        <title>چاپ فاکتورها</title>
        <style>
          body{font-family:Tahoma, sans-serif;padding:20px}
          table{width:100%;border-collapse:collapse}
          th,td{border:1px solid #ccc;padding:6px 8px;font-size:12px;text-align:center}
          thead{background:#f7f7f7}
        </style>
      </head><body>${table.outerHTML}</body></html>`;
                    win.document.write(html); win.document.close(); win.focus(); win.print();
                });

                // 8) خروجی CSV (اکسل باز می‌کند)
                document.getElementById('btn-export')?.addEventListener('click', () => {
                    const rows = [['شناسه', 'مشتری', 'نوع/پرداخت', 'نهایی', 'پرداخت‌شده', 'باقی‌مانده', 'تاریخ']];
                    table.querySelectorAll('tbody tr').forEach(tr => {
                        if (tr.style.display === 'none') return;
                        const tds = tr.querySelectorAll('td');
                        rows.push([
                            tds[1].innerText.trim(),
                            tds[2].innerText.replace(/\s+/g, ' ').trim(),
                            tds[3].innerText.replace(/\s+/g, ' ').trim(),
                            tds[4].innerText.trim(),
                            tds[5].innerText.trim(),
                            tds[6].innerText.trim(),
                            tds[7].innerText.trim()
                        ]);
                    });
                    const csv = rows.map(r => r.map(x => `"${x.replace(/"/g, '""')}"`).join(',')).join('\n');
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob); a.download = 'invoices.csv'; a.click();
                });



                // 10) «تسویهٔ سریع» برای فاکتورهای split
                (function quickSettle() {
                    const modalEl = document.getElementById('quickSettleModal');
                    if (!modalEl) return;
                    if (modalEl.dataset.bound === '1') return;
                    modalEl.dataset.bound = '1';

                    const bodyBox = document.getElementById('qs-body');
                    const btnConfirm = document.getElementById('qs-confirm');
                    const bsModal = window.bootstrap ? new bootstrap.Modal(modalEl) : null;
                    if (!bsModal) { alert('Bootstrap لود نشده است. صفحه را refresh کنید.'); return; }
                    let qsInvoiceId = null;

                    table.addEventListener('click', async (e) => {
                        const btn = e.target.closest('.btn-quick-settle'); if (!btn) return;
                        qsInvoiceId = btn.dataset.id;
                        if (bodyBox) bodyBox.innerHTML = 'در حال بارگذاری...';
                        bsModal && bsModal.show();

                        try {
                            const res = await fetch(`/admin/invoices/${qsInvoiceId}/pending-items`, {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            const data = await res.json().catch(() => null);
                            if (!res.ok || !data || !data.success) {
                                bodyBox.innerHTML = 'خطا در دریافت آیتم‌ها.'; return;
                            }
                            const rows = (data.items || []).map(it => `
            <tr>
              <td><input type="checkbox" class="qs-chk" data-id="${it.id}" data-total="${it.total}"></td>
              <td>${it.service_title}</td>
              <td>${it.staff_name || '-'}</td>
              <td>${Number(it.total || 0).toLocaleString()}</td>
            </tr>`).join('');

                            bodyBox.innerHTML = `
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead><tr><th style="width:40px"></th><th>خدمت</th><th>پرسنل</th><th>مبلغ آیتم</th></tr></thead>
                <tbody>${rows}</tbody>
              </table>
            </div>
            <div class="text-end">مجموع انتخاب شده: <span id="qs-sum">0</span> تومان</div>`;

                            const updateSum = () => {
                                let s = 0; document.querySelectorAll('.qs-chk:checked').forEach(c => s += Number(c.dataset.total || 0));
                                document.getElementById('qs-sum').innerText = s.toLocaleString();
                            };
                            document.querySelectorAll('.qs-chk').forEach(c => c.addEventListener('change', updateSum));
                        } catch (_) {
                            bodyBox.innerHTML = 'خطا در دریافت آیتم‌ها.';
                        }
                    });

                    btnConfirm?.addEventListener('click', async () => {
                        const ids = Array.from(document.querySelectorAll('.qs-chk:checked')).map(c => Number(c.dataset.id));
                        if (!ids.length) { alert('هیچ آیتمی انتخاب نشده.'); return; }
                        const sum = Array.from(document.querySelectorAll('.qs-chk:checked')).reduce((a, c) => a + Number(c.dataset.total || 0), 0);

                        const fd = new FormData();
                        fd.append('amount', sum);
                        fd.append('method', 'pos');
                        ids.forEach(id => fd.append('item_ids[]', id));

                        const res = await fetch(`/admin/invoices/${qsInvoiceId}/deposits`, {
                            method: 'POST',
                            body: fd,
                            credentials: 'include',
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.csrfToken }
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.success) {
                            alert('پرداخت ذخیره شد.');
                            bsModal && bsModal.hide();
                            location.reload();
                        } else {
                            alert((data.errors && Object.values(data.errors).flat()[0]) || data.message || 'خطا در ثبت پرداخت');
                        }
                    });
                })();

                // 11) محاسبه اولیه کارت جمع‌ها
                (function bootTotals() { try { calcTotals(); } catch (_) { } })();
            }

            // اجرا در بارگذاری کامل صفحه
            document.addEventListener('DOMContentLoaded', initInvoicesIndexPage);

            // اگر صفحات با AJAX لود می‌شوند، همان هوک قبلی loadPartial را بسط بده
            if (typeof loadPartial === 'function') {
                const _orig = loadPartial;
                window.loadPartial = function (url) {
                    _orig(url);
                    setTimeout(() => { try { initInvoicesIndexPage(); } catch (_) { } }, 600);
                };
            }
        })();


// بعد از بوت، جدول پرداخت‌های پرسنل را هم فوراً لود کن
const savedIdEl = document.getElementById('saved-invoice-id');
const currentInvoiceId = savedIdEl ? savedIdEl.value : null;
if (currentInvoiceId && typeof window.loadStaffPaidTable === 'function') {
    await window.loadStaffPaidTable(currentInvoiceId);
}

    
    
    
    
    }




};


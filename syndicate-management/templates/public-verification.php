<?php if (!defined('ABSPATH')) exit; ?>
<div class="sm-verify-container" dir="rtl" style="max-width: 1000px; margin: 0 auto; padding: 40px 20px;">
    <!-- Professional Alerts Section -->
    <div style="background: #f0f7ff; border-right: 5px solid #3182ce; padding: 25px; border-radius: 12px; margin-bottom: 40px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        <div style="display: flex; gap: 15px; align-items: flex-start;">
            <span class="dashicons dashicons-info" style="color: #3182ce; font-size: 24px; width: 24px; height: 24px;"></span>
            <div>
                <h4 style="margin: 0 0 10px 0; color: #2c5282; font-weight: 800; font-size: 1.1em;">تعليمات وإرشادات التحقق</h4>
                <p style="margin: 0; color: #2b6cb0; font-size: 14px; line-height: 1.7;">
                    يرجى إدخال الاسم الكامل أو الرقم القومي بدقة لضمان صحة نتائج التحقق. تأكد من مطابقة كافة التفاصيل للسجلات الرسمية الصادرة عن النقابة. في حال وجود أي اختلاف أو صعوبة في التحقق، يرجى التواصل مع إدارة النقابة مباشرة للحصول على المساعدة اللازمة.
                </p>
            </div>
        </div>
    </div>

    <div class="sm-verify-header" style="text-align: center; margin-bottom: 50px;">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 70px; height: 70px; background: rgba(246, 48, 73, 0.1); border-radius: 24px; margin-bottom: 20px;">
            <span class="dashicons dashicons-shield-check" style="font-size: 36px; width: 36px; height: 36px; color: var(--sm-primary-color);"></span>
        </div>
        <h1 style="margin: 0; font-weight: 900; font-size: 2.5em; color: var(--sm-dark-color); letter-spacing: -1px;">بوابة التحقق الرقمية الموحدة</h1>
        <p style="color: #64748b; font-size: 16px; margin-top: 10px; font-weight: 500;">استعلم فورياً عن صحة العضويات والتراخيص المهنية المعتمدة</p>
    </div>

    <div class="sm-verify-search-wrapper" style="background: #fff; padding: 40px; border-radius: 30px; border: 1px solid #e2e8f0; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05); position: relative;">
        <form id="sm-verify-form">
            <div style="display: grid; grid-template-columns: 1.2fr 2.5fr auto; gap: 20px; align-items: flex-end;">
                <div class="sm-form-group" style="margin-bottom: 0;">
                    <label class="sm-label" style="margin-bottom: 12px; font-weight: 700; color: #4a5568;">مجال الاستعلام</label>
                    <select id="sm-verify-type" class="sm-select" style="background: #f8fafc; height: 55px; border-radius: 15px; border-color: #e2e8f0; font-weight: 600;">
                        <option value="all">الاسم / الرقم القومي</option>
                        <option value="membership">رقم القيد النقابي</option>
                        <option value="license">رقم ترخيص المنشأة</option>
                        <option value="practice">رقم تصريح المزاولة</option>
                    </select>
                </div>
                <div class="sm-form-group" style="margin-bottom: 0; position: relative;">
                    <label class="sm-label" style="margin-bottom: 12px; font-weight: 700; color: #4a5568;">بيانات البحث</label>
                    <input type="text" id="sm-verify-value" class="sm-input" autocomplete="off"
                           placeholder="أدخل الاسم أو الرقم المراد التحقق منه..."
                           style="background: #f8fafc; height: 55px; border-radius: 15px; border-color: #e2e8f0; padding-right: 15px; font-weight: 600;">
                    <div id="sm-verify-suggestions" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 15px; margin-top: 5px; z-index: 1000; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); overflow: hidden;"></div>
                </div>
                <button type="submit" class="sm-btn" style="height: 55px; padding: 0 45px; font-weight: 800; font-size: 16px; border-radius: 15px; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(246, 48, 73, 0.3);">
                    <span class="dashicons dashicons-search" style="margin-top: 3px;"></span> بحث وتحقق
                </button>
            </div>
        </form>
    </div>

    <div id="sm-verify-loading" style="display: none; text-align: center; padding: 60px;">
        <div class="sm-spinner" style="margin: 0 auto 15px;"></div>
        <p style="color: #64748b; font-weight: 600;">جاري مطابقة البيانات مع السجلات الرسمية...</p>
    </div>

    <div id="sm-verify-results" style="margin-top: 50px; display: grid; gap: 30px;"></div>
</div>

<style>
/* Enhanced Portal Styles */
.sm-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid rgba(246, 48, 73, 0.1);
    border-top: 4px solid var(--sm-primary-color);
    border-radius: 50%;
    animation: sm-spin 1s linear infinite;
}
@keyframes sm-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.sm-verify-card-professional {
    background: #fff;
    border-radius: 24px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    transition: 0.3s;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.sm-verify-card-professional:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }

.sm-verify-suggestion-item {
    padding: 12px 20px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    transition: 0.2s;
}
.sm-verify-suggestion-item:hover { background: #f8fafc; color: var(--sm-primary-color); }
.sm-verify-suggestion-item:last-child { border-bottom: none; }

@media (max-width: 992px) {
    .sm-verify-search-wrapper > form > div { grid-template-columns: 1fr !important; }
}
</style>

<script>
(function($) {
    const searchInput = $('#sm-verify-value');
    const suggestions = $('#sm-verify-suggestions');
    const searchType = $('#sm-verify-type');
    let typingTimer;

    searchInput.on('input', function() {
        clearTimeout(typingTimer);
        const val = $(this).val();
        if (val.length < 3) {
            suggestions.hide();
            return;
        }

        typingTimer = setTimeout(() => {
            fetch(`${ajaxurl}?action=sm_verify_suggest&query=${val}&type=${searchType.val()}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data.length > 0) {
                    suggestions.empty().show();
                    res.data.forEach(item => {
                        suggestions.append(`<div class="sm-verify-suggestion-item" onclick="smSelectSuggestion('${item}')">${item}</div>`);
                    });
                } else suggestions.hide();
            });
        }, 300);
    });

    window.smSelectSuggestion = function(val) {
        searchInput.val(val);
        suggestions.hide();
        $('#sm-verify-form').submit();
    };

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.sm-form-group').length) suggestions.hide();
    });

    $('#sm-verify-form').on('submit', function(e) {
        e.preventDefault();
        const val = searchInput.val();
        const type = searchType.val();
        const resultsArea = $('#sm-verify-results').empty();
        const loading = $('#sm-verify-loading').show();
        suggestions.hide();

        const fd = new FormData();
        fd.append('action', 'sm_verify_document');
        fd.append('search_value', val);
        fd.append('search_type', type);

        fetch(ajaxurl, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            loading.hide();
            if (res.success) {
                renderResults(res.data);
            } else {
                resultsArea.append(`
                    <div style="background: #fff; padding: 40px; border-radius: 24px; text-align: center; border: 1px solid #feb2b2; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);">
                        <div style="font-size: 50px; margin-bottom: 20px;">🔍</div>
                        <h3 style="color: #c53030; font-weight: 800; margin-bottom: 10px;">لم يتم العثور على بيانات مطابقة</h3>
                        <p style="color: #718096; max-width: 400px; margin: 0 auto;">${res.data}</p>
                    </div>
                `);
            }
        });
    });

    function renderResults(data) {
        const results = $('#sm-verify-results');
        const today = new Date();

        for (let k in data) {
            const doc = data[k];
            let isValid = true;
            let statusLabel = 'صالح ومعتمد';
            let statusIcon = 'dashicons-shield-check';

            if (doc.expiry) {
                const expiry = new Date(doc.expiry);
                if (expiry < today) {
                    isValid = false;
                    statusLabel = 'منتهي الصلاحية';
                    statusIcon = 'dashicons-warning';
                }
            }

            let html = `
                <div class="sm-verify-card-professional">
                    <div style="background: ${isValid ? 'linear-gradient(135deg, #38a169 0%, #2f855a 100%)' : 'linear-gradient(135deg, #e53e3e 0%, #c53030 100%)'}; padding: 25px 35px; color: #fff; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; font-weight: 700; margin-bottom: 5px;">نوع المستند</div>
                            <h3 style="margin: 0; font-weight: 900; font-size: 1.4em; color: #fff;">${doc.label}</h3>
                        </div>
                        <div style="background: rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 12px; backdrop-filter: blur(5px); display: flex; align-items: center; gap: 10px;">
                            <span class="dashicons ${statusIcon}"></span>
                            <span style="font-weight: 800; font-size: 14px;">${statusLabel}</span>
                        </div>
                    </div>
                    <div style="padding: 35px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; background: #fff;">
            `;

            if (k === 'membership') {
                html += `
                    <div class="sm-verify-item-box"><label style="display:block; font-size:11px; color:#94a3b8; font-weight:700; margin-bottom:5px;">الاسم الكامل</label><div style="font-weight:800; font-size:1.1em;">${doc.name}</div></div>
                    <div class="sm-verify-item-box"><label style="display:block; font-size:11px; color:#94a3b8; font-weight:700; margin-bottom:5px;">رقم القيد النقابي</label><div style="font-weight:800; font-size:1.1em;">${doc.number}</div></div>
                    <div class="sm-verify-item-box"><label style="display:block; font-size:11px; color:#94a3b8; font-weight:700; margin-bottom:5px;">التخصص المهني</label><div style="font-weight:800; font-size:1.1em;">${doc.specialization}</div></div>
                    <div class="sm-verify-item-box"><label style="display:block; font-size:11px; color:#94a3b8; font-weight:700; margin-bottom:5px;">الدرجة الوظيفية</label><div style="font-weight:800; font-size:1.1em;">${doc.grade}</div></div>
                    <div class="sm-verify-item-box" style="grid-column: span 2; background: #f8fafc; padding: 15px; border-radius: 12px;"><label style="display:block; font-size:11px; color:#94a3b8; font-weight:700; margin-bottom:5px;">صلاحية العضوية حتى</label><div style="font-weight:900; font-size:1.2em; color:${isValid ? '#38a169' : '#e53e3e'}">${doc.expiry || 'غير محدد'}</div></div>
                `;
            } else if (k === 'license') {
                html += `
                    <div class="sm-verify-item-box" style="grid-column: span 2;"><label style="display:block; font-size:11px; color:#94a3b8; font-weight:700; margin-bottom:5px;">اسم المنشأة</label><div style="font-weight:800; font-size:1.2em;">${doc.facility_name}</div></div>
                    <div class="sm-verify-item-box"><label style="display:block; font-size:11px; color:#94a3b8; font-weight:700; margin-bottom:5px;">رقم الرخصة</label><div style="font-weight:800; font-size:1.1em;">${doc.number}</div></div>
                    <div class="sm-verify-item-box"><label style="display:block; font-size:11px; color:#94a3b8; font-weight:700; margin-bottom:5px;">فئة المنشأة</label><div style="font-weight:800; font-size:1.1em;">${doc.category}</div></div>
                    <div class="sm-verify-item-box" style="grid-column: span 2;"><label style="display:block; font-size:11px; color:#94a3b8; font-weight:700; margin-bottom:5px;">العنوان المسجل</label><div style="font-weight:800; font-size:1.1em;">${doc.address}</div></div>
                    <div class="sm-verify-item-box" style="grid-column: span 2; background: #f8fafc; padding: 15px; border-radius: 12px;"><label style="display:block; font-size:11px; color:#94a3b8; font-weight:700; margin-bottom:5px;">تاريخ انتهاء الترخيص</label><div style="font-weight:900; font-size:1.2em; color:${isValid ? '#38a169' : '#e53e3e'}">${doc.expiry || 'غير محدد'}</div></div>
                `;
            } else if (k === 'practice') {
                html += `
                    <div class="sm-verify-item-box" style="grid-column: span 2;"><label style="display:block; font-size:11px; color:#94a3b8; font-weight:700; margin-bottom:5px;">اسم صاحب التصريح</label><div style="font-weight:800; font-size:1.2em;">${doc.name}</div></div>
                    <div class="sm-verify-item-box"><label style="display:block; font-size:11px; color:#94a3b8; font-weight:700; margin-bottom:5px;">رقم تصريح المزاولة</label><div style="font-weight:800; font-size:1.1em;">${doc.number}</div></div>
                    <div class="sm-verify-item-box"><label style="display:block; font-size:11px; color:#94a3b8; font-weight:700; margin-bottom:5px;">تاريخ الإصدار</label><div style="font-weight:800; font-size:1.1em;">${doc.issue_date}</div></div>
                    <div class="sm-verify-item-box" style="grid-column: span 2; background: #f8fafc; padding: 15px; border-radius: 12px;"><label style="display:block; font-size:11px; color:#94a3b8; font-weight:700; margin-bottom:5px;">تاريخ انتهاء التصريح</label><div style="font-weight:900; font-size:1.2em; color:${isValid ? '#38a169' : '#e53e3e'}">${doc.expiry || 'غير محدد'}</div></div>
                `;
            }

            html += `</div></div>`;
            resultsArea.append(html);
        }
    }
})(jQuery);
</script>

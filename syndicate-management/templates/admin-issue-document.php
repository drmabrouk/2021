<?php if (!defined('ABSPATH')) exit; ?>
<?php
$user = wp_get_current_user();
$syndicate = SM_Settings::get_syndicate_info();
?>
<div class="sm-issue-document" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h2 style="margin:0; font-weight: 800; color: var(--sm-dark-color);">إصدار مستند رسمي جديد</h2>
        <a href="<?php echo add_query_arg(['sm_tab' => 'global-archive', 'sub_tab' => 'issued']); ?>" class="sm-btn sm-btn-outline" style="width:auto;">
            <span class="dashicons dashicons-list-view"></span> سجل المستندات الصادرة
        </a>
    </div>

    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 35px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);">
        <form id="sm-issue-doc-form">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 35px;">
                <div class="sm-form-group">
                    <label class="sm-label">نوع الوثيقة الرسمية:</label>
                    <select name="doc_type" id="doc_type" class="sm-select">
                        <option value="report">تقرير فني / إداري</option>
                        <option value="statement">إفادة رسمية (بيان)</option>
                        <option value="certificate">شهادة معتمدة</option>
                    </select>
                </div>

                <div class="sm-form-group">
                    <label class="sm-label">عنوان الوثيقة:</label>
                    <input type="text" name="title" id="doc_title" class="sm-input" placeholder="مثال: إفادة قيد نقابي" required>
                </div>

                <div class="sm-form-group">
                    <label class="sm-label">قيمة رسوم الاستخراج:</label>
                    <div style="position:relative;">
                        <input type="number" name="fees" id="doc_fees" class="sm-input" value="0" step="0.01">
                        <span style="position:absolute; left:15px; top:12px; font-size:12px; color:#94a3b8;">ج.م</span>
                    </div>
                </div>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 15px; padding: 25px; margin-bottom: 35px;">
                <h4 style="margin: 0 0 20px 0; color: var(--sm-dark-color); display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-admin-users"></span> بيانات صاحب الوثيقة (اختياري)
                </h4>
                <div style="display: flex; gap: 20px; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label class="sm-label" style="font-size: 12px;">البحث بالرقم القومي للربط التلقائي:</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="member_search_val" class="sm-input" placeholder="أدخل الرقم القومي المكون من 14 رقم">
                            <button type="button" onclick="smLookupMemberForDoc()" class="sm-btn" style="width: auto; padding: 0 25px; background: var(--sm-dark-color);">
                                <span class="dashicons dashicons-search" style="margin-top:4px;"></span> تحقق
                            </button>
                        </div>
                    </div>
                    <div id="member_display_box" style="flex: 1; height: 50px; background: #fff; border: 1px solid #cbd5e0; border-radius: 10px; display: flex; align-items: center; padding: 0 20px; font-weight: 700; color: var(--sm-primary-color); display: none;">
                        <!-- Member Name Here -->
                    </div>
                </div>
                <input type="hidden" name="member_id" id="doc_member_id" value="0">
            </div>

            <div class="sm-form-group">
                <label class="sm-label">نص ومحتوى الوثيقة:</label>
                <div style="margin-bottom: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="sm-btn sm-btn-outline" style="width: auto; padding: 4px 12px; font-size: 11px;" onclick="smInsertDocTag('{name}')">إدراج {الاسم}</button>
                    <button type="button" class="sm-btn sm-btn-outline" style="width: auto; padding: 4px 12px; font-size: 11px;" onclick="smInsertDocTag('{nid}')">إدراج {الرقم القومي}</button>
                    <button type="button" class="sm-btn sm-btn-outline" style="width: auto; padding: 4px 12px; font-size: 11px;" onclick="smInsertDocTag('{date}')">إدراج {تاريخ اليوم}</button>
                    <button type="button" class="sm-btn sm-btn-outline" style="width: auto; padding: 4px 12px; font-size: 11px;" onclick="smInsertDocTag('{serial}')">إدراج {رقم تسلسلي}</button>
                </div>
                <textarea name="content" id="doc_content" style="width: 100%; min-height: 450px; padding: 30px; border: 1px solid #cbd5e0; border-radius: 12px; font-family: 'Arial', sans-serif; font-size: 18px; line-height: 2; text-align: justify;" placeholder="اكتب نص الوثيقة الرسمي هنا..."></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 35px; border-top: 1px solid #eee; padding-top: 30px;">
                <div>
                    <h4 style="margin: 0 0 20px 0;">خيارات الإخراج النهائي (Layout Options):</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <label class="sm-checkbox-label" style="background:#f8fafc; padding:12px; border-radius:10px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="header" checked> الترويسة والشعار الرسمي
                        </label>
                        <label class="sm-checkbox-label" style="background:#f8fafc; padding:12px; border-radius:10px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="footer" checked> التوقيعات والختم النقابي
                        </label>
                        <label class="sm-checkbox-label" style="background:#f8fafc; padding:12px; border-radius:10px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="qr" checked> كود التحقق الرقمي QR
                        </label>
                        <div style="display:flex; align-items:center; gap:10px; background:#f8fafc; padding:12px; border-radius:10px; border:1px solid #e2e8f0;">
                            <span style="font-size:13px; color:#64748b;">نمط الإطار:</span>
                            <select name="frame_type" class="sm-select" style="padding:4px 10px; height:auto; width:auto; flex:1;">
                                <option value="none">بدون إطار</option>
                                <option value="simple">إطار كلاسيكي</option>
                                <option value="double">إطار مزدوج</option>
                                <option value="ornamental">إطار مزخرف</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px; justify-content: center;">
                    <button type="button" onclick="smIssueDocumentAction('pdf')" class="sm-btn" style="height: 55px; font-weight: 800; background: #111F35; font-size: 1.1em;">
                        <span class="dashicons dashicons-pdf" style="margin-top:4px;"></span> حفظ وتوليد الوثيقة PDF
                    </button>
                    <p style="text-align: center; color: #94a3b8; font-size: 11px; margin: 0;">* سيتم أرشفة الوثيقة تلقائياً في السجل الرقمي</p>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
#a4-preview p { margin-bottom: 1.5em; text-align: justify; }
.preview-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #000; padding-bottom: 10mm; margin-bottom: 15mm; }
.preview-footer { position: absolute; bottom: 20mm; left: 20mm; right: 20mm; display: flex; justify-content: space-between; border-top: 1px solid #eee; padding-top: 5mm; }
.frame-simple { border: 1mm solid #000; margin: 5mm; min-height: calc(297mm - 10mm); }
.frame-double { border: 3px double #000; margin: 5mm; min-height: calc(297mm - 10mm); }
.frame-ornamental { border: 10px solid transparent; border-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect x="0" y="0" width="100" height="100" fill="none" stroke="black" stroke-width="10" stroke-dasharray="20,10"/></svg>') 30 stretch; margin: 5mm; min-height: calc(297mm - 10mm); }
</style>

<script>
function smInsertDocTag(tag) {
    const el = document.getElementById('doc_content');
    const start = el.selectionStart;
    const end = el.selectionEnd;
    const text = el.value;
    el.value = text.substring(0, start) + tag + text.substring(end);
    el.focus();
}

function smLookupMemberForDoc() {
    const val = document.getElementById('member_search_val').value;
    if(!val) return;
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="dashicons dashicons-update" style="animation: smRotate 1s linear infinite;"></span>';

    const fd = new FormData();
    fd.append('action', 'sm_get_member_ajax');
    fd.append('national_id', val);
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        const box = document.getElementById('member_display_box');
        if(res.success) {
            document.getElementById('doc_member_id').value = res.data.id;
            box.innerText = '✅ العضو المرتبط: ' + res.data.name;
            box.style.display = 'flex';
            smShowNotification('تم العثور على العضو وربطه بالوثيقة');
        } else {
            alert('عذراً، لم يتم العثور على عضو بهذا الرقم القومي');
            document.getElementById('doc_member_id').value = '0';
            box.style.display = 'none';
        }
    });
}

// Function removed from frontend: smUpdatePreview (Now focused on final output quality)

function smIssueDocumentAction(format) {
    const title = document.getElementById('doc_title').value;
    const content = document.getElementById('doc_content').value;
    if(!title || !content) return alert('يرجى إكمال العنوان والمحتوى أولاً');

    if(!confirm('هل أنت متأكد من حفظ وإصدار هذا المستند؟ سيتم أرشفته تلقائياً في السجل.')) return;

    const form = document.getElementById('sm-issue-doc-form');
    const fd = new FormData(form);
    fd.append('action', 'sm_generate_pub_doc');
    fd.append('content', content);
    fd.append('format', format);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_pub_action"); ?>');

    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        if(res.success) {
            smShowNotification('تم إصدار المستند بنجاح');
            window.open(res.data.url, '_blank');
            setTimeout(() => location.href = '<?php echo add_query_arg(['sm_tab' => 'global-archive', 'sub_tab' => 'issued']); ?>', 1000);
        } else {
            alert('خطأ: ' + res.data);
        }
    });
}

// Initial call
smUpdatePreview();
</script>

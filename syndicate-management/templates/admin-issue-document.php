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

    <div style="display: grid; grid-template-columns: 350px 1fr; gap: 30px;">
        <!-- Configuration Sidebar -->
        <div class="sm-doc-config" style="background: #f8fafc; padding: 25px; border-radius: 15px; border: 1px solid #e2e8f0; align-self: start;">
            <form id="sm-issue-doc-form">
                <div class="sm-form-group">
                    <label class="sm-label">نوع المستند:</label>
                    <select name="doc_type" id="doc_type" class="sm-select" onchange="smUpdatePreview()">
                        <option value="report">تقرير رسمي</option>
                        <option value="statement">إفادة / بيان</option>
                        <option value="certificate">شهادة تقدير / خبرة</option>
                    </select>
                </div>

                <div class="sm-form-group">
                    <label class="sm-label">عنوان المستند:</label>
                    <input type="text" name="title" id="doc_title" class="sm-input" placeholder="مثال: إفادة قيد نقابي" oninput="smUpdatePreview()" required>
                </div>

                <div class="sm-form-group">
                    <label class="sm-label">ربط بالعضو (اختياري - أدخل الرقم القومي):</label>
                    <div style="display:flex; gap:5px;">
                        <input type="text" id="member_search_val" class="sm-input" placeholder="290...">
                        <button type="button" onclick="smLookupMemberForDoc()" class="sm-btn" style="width:auto; padding:0 10px;"><span class="dashicons dashicons-search"></span></button>
                    </div>
                    <input type="hidden" name="member_id" id="doc_member_id" value="0">
                    <div id="member_display" style="font-size:11px; margin-top:5px; color:var(--sm-primary-color); font-weight:700;"></div>
                </div>

                <div class="sm-form-group">
                    <label class="sm-label">قيمة الرسوم المقررة:</label>
                    <input type="number" name="fees" id="doc_fees" class="sm-input" value="0" step="0.01">
                </div>

                <hr style="margin:20px 0; border:none; border-top:1px solid #cbd5e0;">

                <div class="sm-form-group">
                    <label class="sm-label">خيارات التصميم:</label>
                    <div style="display:grid; gap:8px;">
                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                            <input type="checkbox" name="header" checked onchange="smUpdatePreview()"> إدراج الترويسة الرسمية
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                            <input type="checkbox" name="footer" checked onchange="smUpdatePreview()"> إدراج التذييل (التوقيعات)
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                            <input type="checkbox" name="qr" checked onchange="smUpdatePreview()"> إدراج كود التحقق QR
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                            <input type="checkbox" name="barcode" onchange="smUpdatePreview()"> إدراج باركود تسلسلي
                        </label>
                    </div>
                </div>

                <div class="sm-form-group">
                    <label class="sm-label">إطار الصفحة:</label>
                    <select name="frame_type" id="frame_type" class="sm-select" onchange="smUpdatePreview()">
                        <option value="none">بدون إطار</option>
                        <option value="simple">إطار بسيط</option>
                        <option value="double">إطار مزدوج</option>
                        <option value="ornamental">إطار مزخرف</option>
                    </select>
                </div>

                <div style="margin-top:25px; display:grid; gap:10px;">
                    <button type="button" onclick="smIssueDocumentAction('pdf')" class="sm-btn" style="height:45px; font-weight:800; background:#111F35;">
                        <span class="dashicons dashicons-pdf" style="margin-top:12px;"></span> حفظ وإصدار PDF
                    </button>
                    <button type="button" onclick="smIssueDocumentAction('image')" class="sm-btn sm-btn-outline" style="height:45px; font-weight:800;">
                        <span class="dashicons dashicons-format-image" style="margin-top:12px;"></span> حفظ وإصدار صورة A4
                    </button>
                </div>
            </form>
        </div>

        <!-- Document Preview/Editor Area -->
        <div class="sm-doc-preview-wrap">
            <div style="background: #fff; border: 1px solid #cbd5e0; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                <h4 style="margin:0 0 15px 0;">محتوى المستند (نص المستند):</h4>
                <textarea id="doc_content" style="width: 100%; min-height: 400px; padding: 20px; border: 1px solid #cbd5e0; border-radius: 8px; font-family: 'Arial', sans-serif; font-size: 16px; line-height: 1.8;" oninput="smUpdatePreview()"></textarea>
                <p style="font-size:11px; color:#666; margin-top:5px;">* يمكنك استخدام العلامات: {name}, {nid}, {date}, {serial} لاستبدالها تلقائياً.</p>
            </div>

            <!-- Live A4 Preview -->
            <div style="background: #525659; padding: 40px; border-radius: 12px; display: flex; justify-content: center; overflow-x: auto;">
                <div id="a4-preview" style="width: 210mm; min-height: 297mm; background: #fff; padding: 20mm; box-shadow: 0 0 20px rgba(0,0,0,0.3); position: relative; font-family: 'Arial', sans-serif; color: #000; box-sizing: border-box;">
                    <!-- Content injected by JS -->
                </div>
            </div>
        </div>
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
function smLookupMemberForDoc() {
    const val = document.getElementById('member_search_val').value;
    if(!val) return;
    const fd = new FormData();
    fd.append('action', 'sm_get_member_ajax');
    fd.append('national_id', val);
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        if(res.success) {
            document.getElementById('doc_member_id').value = res.data.id;
            document.getElementById('member_display').innerText = 'تم الربط بالعضو: ' + res.data.name;
            smUpdatePreview();
        } else {
            alert('لم يتم العثور على العضو');
            document.getElementById('doc_member_id').value = '0';
            document.getElementById('member_display').innerText = '';
        }
    });
}

function smUpdatePreview() {
    const form = document.getElementById('sm-issue-doc-form');
    const preview = document.getElementById('a4-preview');
    const title = document.getElementById('doc_title').value || 'عنوان المستند';
    const content = document.getElementById('doc_content').value;
    const type = document.getElementById('doc_type').value;
    const hasHeader = form.header.checked;
    const hasFooter = form.footer.checked;
    const hasQR = form.qr.checked;
    const frame = document.getElementById('frame_type').value;

    let html = '';

    // Header
    if(hasHeader) {
        html += `
            <div class="preview-header">
                <div style="text-align:right;">
                    <div style="font-weight:bold; font-size:18px;"><?php echo esc_js($syndicate['authority_name']); ?></div>
                    <div style="font-weight:bold; font-size:20px; color:var(--sm-dark-color);"><?php echo esc_js($syndicate['syndicate_name']); ?></div>
                </div>
                <?php if($syndicate['syndicate_logo']): ?>
                    <img src="<?php echo esc_url($syndicate['syndicate_logo']); ?>" style="max-height:80px;">
                <?php endif; ?>
            </div>
        `;
    }

    // Title
    html += `<div style="text-align:center; margin-bottom:10mm;"><h1 style="text-decoration:underline; font-size:24px;">${title}</h1></div>`;

    // Body
    let processedContent = content.replace(/\n/g, '<br>');
    processedContent = processedContent.replace(/{date}/g, '<?php echo date('Y-m-d'); ?>');
    processedContent = processedContent.replace(/{serial}/g, 'PUB-<?php echo date('Y'); ?>-XXXXX');

    const memberId = document.getElementById('doc_member_id').value;
    const memberName = document.getElementById('member_display').innerText.replace('تم الربط بالعضو: ', '');
    const memberNid = document.getElementById('member_search_val').value;

    if (memberId != '0') {
        processedContent = processedContent.replace(/{name}/g, memberName);
        processedContent = processedContent.replace(/{nid}/g, memberNid);
    }

    html += `<div style="font-size:16px; line-height:1.8; min-height:150mm;">${processedContent}</div>`;

    // QR Code Placeholder
    if(hasQR) {
        html += `<div style="position:absolute; bottom:40mm; left:20mm; width:30mm; height:30mm; border:1px solid #ddd; display:flex; align-items:center; justify-content:center; font-size:10px; color:#999; background:#f9f9f9;">QR CODE</div>`;
    }

    // Footer
    if(hasFooter) {
        html += `
            <div class="preview-footer">
                <div style="text-align:center;">
                    <strong>توقيع المسؤول</strong><br><br>...................
                </div>
                <div style="width:35mm; height:35mm; border:2px dashed #ccc; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#ccc; font-size:12px;">ختم النقابة</div>
            </div>
        `;
    }

    preview.innerHTML = html;

    // Apply frame class to a wrapper or inner div
    preview.className = '';
    if(frame !== 'none') {
        preview.classList.add('frame-' + frame);
    }
}

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

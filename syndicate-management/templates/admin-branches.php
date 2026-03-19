<?php if (!defined('ABSPATH')) exit; ?>
<div class="sm-content-wrapper">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
        <h2 style="margin:0; font-weight:800; color:var(--sm-dark-color);">إدارة فروع ولجان النقابة</h2>
        <button onclick="smOpenBranchModal()" class="sm-btn" style="width:auto; padding:10px 25px;">+ إضافة فرع جديد</button>
    </div>

    <div id="sm-branches-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:25px;">
        <?php
        $branches = SM_DB::get_branches_data();
        if (empty($branches)): ?>
            <div style="grid-column: 1/-1; text-align:center; padding:50px; background:#fff; border-radius:15px; border:1px dashed #cbd5e0;">
                <p style="color:#718096;">لا توجد فروع مسجلة حالياً. قم بإضافة أول فرع للبدء.</p>
            </div>
        <?php else: foreach($branches as $b): ?>
            <div class="sm-branch-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition:0.3s; position:relative;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px;">
                    <div style="width:45px; height:45px; background:var(--sm-primary-color); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff;">
                        <span class="dashicons dashicons-location"></span>
                    </div>
                    <div style="display:flex; gap:5px;">
                        <button onclick='smEditBranch(<?php echo json_encode($b); ?>)' class="sm-btn sm-btn-outline" style="padding:4px 8px; font-size:11px;">تعديل</button>
                        <button onclick="smDeleteBranch(<?php echo $b->id; ?>)" class="sm-btn" style="background:#e53e3e; padding:4px 8px; font-size:11px;">حذف</button>
                    </div>
                </div>
                <h3 style="margin:0 0 10px 0; font-weight:800; color:var(--sm-dark-color);"><?php echo esc_html($b->name); ?></h3>
                <div style="font-size:13px; color:#64748b; margin-bottom:15px; min-height:40px; line-height:1.6;"><?php echo esc_html($b->address); ?></div>

                <div style="border-top:1px solid #f1f5f9; padding-top:15px; display:grid; gap:8px;">
                    <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:#4a5568;">
                        <span class="dashicons dashicons-admin-users" style="font-size:16px; width:16px; height:16px; color:var(--sm-primary-color);"></span>
                        <strong>المدير:</strong> <?php echo esc_html($b->manager ?: 'غير محدد'); ?>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:#4a5568;">
                        <span class="dashicons dashicons-phone" style="font-size:16px; width:16px; height:16px; color:var(--sm-primary-color);"></span>
                        <strong>الهاتف:</strong> <?php echo esc_html($b->phone); ?>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:#4a5568;">
                        <span class="dashicons dashicons-email" style="font-size:16px; width:16px; height:16px; color:var(--sm-primary-color);"></span>
                        <strong>البريد:</strong> <?php echo esc_html($b->email); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div id="sm-branch-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 600px;">
        <div class="sm-modal-header">
            <h3><span id="sm-branch-modal-title">إضافة فرع جديد</span></h3>
            <button class="sm-modal-close" onclick="document.getElementById('sm-branch-modal').style.display='none'">&times;</button>
        </div>
        <form id="sm-branch-form" style="padding: 25px;">
            <input type="hidden" name="id" id="sm_branch_id">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div class="sm-form-group"><label class="sm-label">اسم الفرع:</label><input type="text" name="name" class="sm-input" required></div>
                <div class="sm-form-group"><label class="sm-label">كود الفرع (Slug):</label><input type="text" name="slug" class="sm-input" required placeholder="example: cairo-east"></div>
                <div class="sm-form-group"><label class="sm-label">اسم مدير الفرع:</label><input type="text" name="manager" class="sm-input"></div>
                <div class="sm-form-group"><label class="sm-label">رقم الهاتف:</label><input type="text" name="phone" class="sm-input"></div>
                <div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">البريد الإلكتروني:</label><input type="email" name="email" class="sm-input"></div>
                <div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">العنوان التفصيلي:</label><input type="text" name="address" class="sm-input"></div>
                <div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">وصف إضافي:</label><textarea name="description" class="sm-textarea" rows="2"></textarea></div>
            </div>

            <div style="margin-top:20px; border-top:1px solid #edf2f7; pt:20px;">
                <h4 style="margin:20px 0 15px 0; color:var(--sm-primary-color); font-weight:800; font-size:14px;">بيانات التحصيل المالي للفروع</h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="sm-form-group"><label class="sm-label">رقم الآيبان (IBAN):</label><input type="text" name="bank_iban" class="sm-input"></div>
                    <div class="sm-form-group"><label class="sm-label">حساب البنك المحلي:</label><input type="text" name="bank_local" class="sm-input"></div>
                    <div class="sm-form-group"><label class="sm-label">رقم المحفظة الإلكترونية:</label><input type="text" name="digital_wallet" class="sm-input"></div>
                    <div class="sm-form-group"><label class="sm-label">عنوان انستا باي (Instapay):</label><input type="text" name="instapay_id" class="sm-input"></div>
                </div>
            </div>
            <button type="submit" class="sm-btn" style="width:100%; margin-top:20px;">حفظ بيانات الفرع</button>
        </form>
    </div>
</div>

<script>
window.smOpenBranchModal = function() {
    document.getElementById('sm-branch-form').reset();
    document.getElementById('sm_branch_id').value = '';
    document.getElementById('sm-branch-modal-title').innerText = 'إضافة فرع جديد';
    document.getElementById('sm-branch-modal').style.display = 'flex';
};

window.smEditBranch = function(b) {
    const f = document.getElementById('sm-branch-form');
    document.getElementById('sm_branch_id').value = b.id;
    f.name.value = b.name;
    f.slug.value = b.slug;
    f.manager.value = b.manager || '';
    f.phone.value = b.phone || '';
    f.email.value = b.email || '';
    f.address.value = b.address || '';
    f.description.value = b.description || '';
    f.bank_iban.value = b.bank_iban || '';
    f.bank_local.value = b.bank_local || '';
    f.digital_wallet.value = b.digital_wallet || '';
    f.instapay_id.value = b.instapay_id || '';
    document.getElementById('sm-branch-modal-title').innerText = 'تعديل بيانات الفرع';
    document.getElementById('sm-branch-modal').style.display = 'flex';
};

window.smDeleteBranch = function(id) {
    if (!confirm('هل أنت متأكد من حذف هذا الفرع؟')) return;
    const fd = new FormData();
    fd.append('action', 'sm_delete_branch');
    fd.append('id', id);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
    fetch(ajaxurl, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
        if (res.success) location.reload();
        else alert('خطأ: ' + res.data);
    });
};

document.getElementById('sm-branch-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'sm_save_branch');
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
    fetch(ajaxurl, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
        if (res.success) {
            smShowNotification('تم حفظ بيانات الفرع');
            location.reload();
        } else alert('خطأ: ' + res.data);
    });
});
</script>

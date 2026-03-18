<?php if (!defined('ABSPATH')) exit; ?>
<div class="sm-branches-management" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h2 style="margin:0; font-weight: 800; color: var(--sm-dark-color);">إدارة فروع النقابة</h2>
        <div style="display:flex; gap:10px;">
            <button onclick="smOpenAddBranchModal()" class="sm-btn" style="width:auto;">+ إضافة فرع جديد</button>
            <button onclick="smExportBranches()" class="sm-btn sm-btn-outline" style="width:auto;"><span class="dashicons dashicons-download"></span> تصدير البيانات</button>
        </div>
    </div>

    <div class="sm-card-grid" style="grid-template-columns: repeat(2, 1fr); gap: 25px;">
        <?php
        $branches = SM_DB::get_branches_data();
        if (empty($branches)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 60px; color: #94a3b8; background: #f8fafc; border-radius: 20px; border: 1px dashed #cbd5e0;">
                <span class="dashicons dashicons-networking" style="font-size: 40px; width: 40px; height: 40px; margin-bottom: 15px; opacity: 0.5;"></span>
                <p>لم يتم إضافة أي فروع بعد. ابدأ بإضافة فرع جديد للنقابة.</p>
            </div>
        <?php else: ?>
            <?php foreach ($branches as $b): ?>
                <div class="sm-branch-card" style="background: #fff; border: 1px solid var(--sm-border-color); border-radius: 24px; padding: 30px; display: flex; flex-direction: column; transition: 0.3s; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--sm-primary-color), var(--sm-secondary-color)); border-radius: 18px; display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 10px 15px -3px rgba(246, 48, 73, 0.2);">
                            <span class="dashicons dashicons-location" style="font-size: 30px; width: 30px; height: 30px;"></span>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button onclick='smEditBranch(<?php echo json_encode($b); ?>)' class="sm-btn sm-btn-outline" style="padding: 5px 12px; font-size: 12px; height: 32px; width: auto;">تعديل</button>
                            <button onclick="smDeleteBranch(<?php echo $b->id; ?>, '<?php echo esc_js($b->name); ?>')" class="sm-btn" style="padding: 5px 12px; font-size: 12px; height: 32px; width: auto; background: #e53e3e;">حذف</button>
                        </div>
                    </div>

                    <h3 style="margin: 0 0 10px 0; font-weight: 800; color: var(--sm-dark-color); font-size: 1.4em;"><?php echo esc_html($b->name); ?></h3>
                    <div style="display: flex; align-items: center; gap: 8px; color: var(--sm-primary-color); font-weight: 700; font-size: 12px; margin-bottom: 15px;">
                        <span class="dashicons dashicons-businessman" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        مدير الفرع: <?php echo esc_html($b->manager ?: 'غير محدد'); ?>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr; gap: 10px; margin-top: auto; padding-top: 20px; border-top: 1px solid #f1f5f9; font-size: 13px; color: #64748b;">
                        <div style="display: flex; align-items: center; gap: 10px;"><span class="dashicons dashicons-phone"></span> <?php echo esc_html($b->phone); ?></div>
                        <div style="display: flex; align-items: center; gap: 10px;"><span class="dashicons dashicons-email"></span> <?php echo esc_html($b->email); ?></div>
                        <div style="display: flex; align-items: center; gap: 10px;"><span class="dashicons dashicons-location-alt"></span> <?php echo esc_html($b->address); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Branch Modal -->
<div id="sm-branch-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 650px;">
        <div class="sm-modal-header">
            <h3 id="branch-modal-title">إضافة فرع جديد</h3>
            <button class="sm-modal-close" onclick="document.getElementById('sm-branch-modal').style.display='none'">&times;</button>
        </div>
        <form id="sm-branch-form" style="padding: 25px;">
            <input type="hidden" name="id" id="branch-id">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="sm-form-group"><label class="sm-label">اسم الفرع:</label><input type="text" name="name" class="sm-input" required></div>
                <div class="sm-form-group"><label class="sm-label">الكود التعريفي (Slug):</label><input type="text" name="slug" id="branch-slug" class="sm-input" required placeholder="cairo"></div>
                <div class="sm-form-group"><label class="sm-label">مدير الفرع:</label><input type="text" name="manager" class="sm-input"></div>
                <div class="sm-form-group"><label class="sm-label">رقم الهاتف:</label><input type="text" name="phone" class="sm-input"></div>
                <div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">البريد الإلكتروني:</label><input type="email" name="email" class="sm-input"></div>
                <div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">العنوان الجغرافي:</label><input type="text" name="address" class="sm-input"></div>
                <div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">وصف الفرع:</label><textarea name="description" class="sm-textarea" rows="3"></textarea></div>
            </div>
            <button type="submit" class="sm-btn" style="width: 100%; margin-top: 15px; height: 45px; font-weight: 800;">حفظ بيانات الفرع</button>
        </form>
    </div>
</div>

<script>
function smOpenAddBranchModal() {
    const f = document.getElementById('sm-branch-form');
    f.reset();
    document.getElementById('branch-id').value = '';
    document.getElementById('branch-slug').readOnly = false;
    document.getElementById('branch-modal-title').innerText = 'إضافة فرع جديد';
    document.getElementById('sm-branch-modal').style.display = 'flex';
}

function smEditBranch(b) {
    const f = document.getElementById('sm-branch-form');
    document.getElementById('branch-id').value = b.id;
    f.name.value = b.name;
    f.slug.value = b.slug;
    document.getElementById('branch-slug').readOnly = true;
    f.manager.value = b.manager || '';
    f.phone.value = b.phone || '';
    f.email.value = b.email || '';
    f.address.value = b.address || '';
    f.description.value = b.description || '';
    document.getElementById('branch-modal-title').innerText = 'تعديل بيانات الفرع: ' + b.name;
    document.getElementById('sm-branch-modal').style.display = 'flex';
}

document.getElementById('sm-branch-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'sm_save_branch');
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    fetch(ajaxurl, {method: 'POST', body: fd}).then(r=>r.json()).then(res=>{
        if(res.success) {
            smShowNotification('تم حفظ بيانات الفرع بنجاح');
            location.reload();
        } else {
            alert('خطأ: ' + res.data);
        }
    });
});

function smDeleteBranch(id, name) {
    if(!confirm('هل أنت متأكد من حذف فرع "' + name + '"؟')) return;
    const fd = new FormData();
    fd.append('action', 'sm_delete_branch');
    fd.append('id', id);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    fetch(ajaxurl, {method: 'POST', body: fd}).then(r=>r.json()).then(res=>{
        if(res.success) {
            smShowNotification('تم حذف الفرع بنجاح');
            location.reload();
        }
    });
}

function smExportBranches() {
    window.location.href = ajaxurl + '?action=sm_export_branches&nonce=<?php echo wp_create_nonce("sm_admin_action"); ?>';
}
</script>

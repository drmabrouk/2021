<?php if (!defined('ABSPATH')) exit; ?>
<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

$status_filter = $_GET['status_filter'] ?? '';
$branch_filter = $_GET['branch_filter'] ?? '';
$search_query = $_GET['search'] ?? '';

$query = "SELECT * FROM {$wpdb->prefix}sm_membership_requests WHERE 1=1";
$params = [];

if ($status_filter) {
    $query .= " AND status = %s";
    $params[] = $status_filter;
} else {
    $query .= " AND status NOT IN ('approved', 'rejected')";
}

if ($branch_filter) {
    $query .= " AND governorate = %s";
    $params[] = $branch_filter;
}

if ($search_query) {
    $query .= " AND (name LIKE %s OR national_id LIKE %s)";
    $params[] = '%' . $wpdb->esc_like($search_query) . '%';
    $params[] = '%' . $wpdb->esc_like($search_query) . '%';
}

$query .= " ORDER BY created_at DESC";

if (!empty($params)) {
    $requests = $wpdb->get_results($wpdb->prepare($query, $params));
} else {
    $requests = $wpdb->get_results($query);
}

$govs = SM_Settings::get_governorates();
$univs = SM_Settings::get_universities();
$facs = SM_Settings::get_faculties();
$depts = SM_Settings::get_departments();
$branches = SM_DB::get_branches_data();

$status_labels = [
    'Pending Payment' => ['label' => 'بانتظار السداد', 'color' => '#64748b'],
    'Payment Under Review' => ['label' => 'مراجعة الدفع', 'color' => '#f59e0b'],
    'Payment Approved' => ['label' => 'انتظار الوثائق الرقمية', 'color' => '#3b82f6'],
    'Awaiting Physical Documents' => ['label' => 'بانتظار الأصول', 'color' => '#8b5cf6'],
    'Under Final Review' => ['label' => 'المراجعة النهائية', 'color' => '#10b981'],
    'approved' => ['label' => 'مقبول', 'color' => '#27ae60'],
    'rejected' => ['label' => 'مرفوض', 'color' => '#e53e3e']
];
?>
<div class="sm-content-wrapper" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h2 style="margin:0; font-weight: 800; color: var(--sm-dark-color);">إدارة طلبات العضوية</h2>
            <p style="margin:5px 0 0 0; color:#64748b; font-size:13px;">مراجعة طلبات الانضمام الجديدة، التحقق من السداد، وفحص المستندات.</p>
        </div>
        <div style="background: var(--sm-primary-color); color: #fff; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 700;">
            العدد: <?php echo count($requests); ?>
        </div>
    </div>

    <!-- Filters Bar -->
    <div style="background:#fff; padding:20px; border-radius:15px; margin-bottom:25px; box-shadow:0 2px 4px rgba(0,0,0,0.02); display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap; border:1px solid #e2e8f0;">
        <div style="flex:1; min-width:200px;">
            <label style="display:block; font-size:12px; font-weight:700; color:#64748b; margin-bottom:8px;">بحث بالاسم أو الرقم القومي:</label>
            <input type="text" id="req-search" class="sm-input" value="<?php echo esc_attr($search_query); ?>" placeholder="أدخل بيانات البحث...">
        </div>
        <div style="width:180px;">
            <label style="display:block; font-size:12px; font-weight:700; color:#64748b; margin-bottom:8px;">الحالة:</label>
            <select id="req-status" class="sm-select">
                <option value="">كل الطلبات المعلقة</option>
                <?php foreach($status_labels as $val => $info): ?>
                    <option value="<?php echo $val; ?>" <?php selected($status_filter, $val); ?>><?php echo $info['label']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="width:180px;">
            <label style="display:block; font-size:12px; font-weight:700; color:#64748b; margin-bottom:8px;">الفرع:</label>
            <select id="req-branch" class="sm-select">
                <option value="">كل الفروع</option>
                <?php foreach($branches as $b): ?>
                    <option value="<?php echo $b->slug; ?>" <?php selected($branch_filter, $b->slug); ?>><?php echo $b->name; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button onclick="smApplyReqFilters()" class="sm-btn" style="width:auto; height:42px; padding:0 25px;">تصفية</button>
        <button onclick="location.href='admin.php?page=sm-membership-requests'" class="sm-btn sm-btn-outline" style="width:auto; height:42px;">تصفير</button>
    </div>

    <div class="sm-table-container" style="border-radius:15px; overflow:hidden; border:1px solid #e2e8f0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
        <table class="sm-table">
            <thead>
                <tr>
                    <th style="width:250px;">بيانات المتقدم</th>
                    <th>الفرع المطلوب</th>
                    <th>الحالة الحالية</th>
                    <th>التواصل</th>
                    <th>الإجراءات السريعة</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 60px; color: #94a3b8; background:#fff;">لا توجد طلبات تطابق معايير البحث حالياً.</td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $r): ?>
                        <tr id="req-row-<?php echo $r->id; ?>">
                            <td>
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <div style="width:40px; height:40px; background:#f1f5f9; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#94a3b8;">
                                        <span class="dashicons dashicons-admin-users"></span>
                                    </div>
                                    <div>
                                        <div style="font-weight: 800; color:var(--sm-dark-color); font-size:14px;"><?php echo esc_html($r->name); ?></div>
                                        <div style="font-size: 11px; color: #64748b; font-family:monospace;"><?php echo esc_html($r->national_id); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size:13px; font-weight:700; color:var(--sm-primary-color);"><?php echo esc_html(SM_Settings::get_branch_name($r->governorate)); ?></div>
                                <div style="font-size:11px; color:#94a3b8; margin-top:3px;"><?php echo date('Y-m-d H:i', strtotime($r->created_at)); ?></div>
                            </td>
                            <td>
                                <?php $s = $status_labels[$r->status] ?? ['label' => $r->status, 'color' => '#64748b']; ?>
                                <span style="display:inline-block; padding:5px 12px; border-radius:30px; background:<?php echo $s['color']; ?>15; color:<?php echo $s['color']; ?>; font-size:11px; font-weight:800; border:1px solid <?php echo $s['color']; ?>25;">
                                    <?php echo $s['label']; ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size: 12px; font-weight:600; color:#4a5568;"><?php echo esc_html($r->phone); ?></div>
                                <div style="font-size: 11px; color: #94a3b8;"><?php echo esc_html($r->email); ?></div>
                            </td>
                            <td>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <button onclick="smToggleReqDetails(<?php echo $r->id; ?>)" class="sm-btn sm-btn-outline" style="padding:6px 12px; font-size:11px; height:auto; width:auto;">التفاصيل</button>
                                    <div class="sm-actions-dropdown" style="position:relative; display:inline-block;">
                                        <button class="sm-btn" style="padding:6px 12px; font-size:11px; height:auto; width:auto; background:var(--sm-dark-color);">خيارات ▾</button>
                                        <div class="sm-actions-content" style="display:none; position:absolute; left:0; top:100%; background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1); z-index:100; min-width:160px;">
                                            <a href="#" onclick="processMembership(<?php echo $r->id; ?>, 'update'); return false;" style="display:block; padding:10px 15px; font-size:12px; color:#4a5568; text-decoration:none; border-bottom:1px solid #f1f5f9;">تحديث الحالة</a>
                                            <a href="#" onclick="smPrintReqForm(<?php echo $r->id; ?>); return false;" style="display:block; padding:10px 15px; font-size:12px; color:#4a5568; text-decoration:none; border-bottom:1px solid #f1f5f9;">طباعة النموذج (A4)</a>
                                            <?php if($r->status === 'Under Final Review'): ?>
                                                <a href="#" onclick="processMembership(<?php echo $r->id; ?>, 'approved'); return false;" style="display:block; padding:10px 15px; font-size:12px; color:#27ae60; text-decoration:none; border-bottom:1px solid #f1f5f9; font-weight:700;">اعتماد نهائي</a>
                                            <?php endif; ?>
                                            <a href="#" onclick="rejectMembership(<?php echo $r->id; ?>); return false;" style="display:block; padding:10px 15px; font-size:12px; color:#e53e3e; text-decoration:none;">رفض الطلب</a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr id="req-details-<?php echo $r->id; ?>" style="display:none; background:#f8fafc;">
                            <td colspan="5" style="padding:25px; border-top:none;">
                                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:30px;">
                                    <div>
                                        <h4 style="margin:0 0 15px 0; font-size:13px; color:var(--sm-primary-color); border-bottom:1px solid #e2e8f0; padding-bottom:8px;">البيانات الأكاديمية</h4>
                                        <div style="font-size:12px; line-height:1.8;">
                                            <strong>الجامعة:</strong> <?php echo esc_html($univs[$r->university] ?? $r->university); ?><br>
                                            <strong>الكلية:</strong> <?php echo esc_html($facs[$r->faculty] ?? $r->faculty); ?><br>
                                            <strong>القسم:</strong> <?php echo esc_html($depts[$r->department] ?? $r->department); ?><br>
                                            <strong>سنة التخرج:</strong> <?php echo esc_html($r->graduation_date); ?><br>
                                            <strong>الدرجة العلمية:</strong> <?php echo esc_html($r->academic_degree); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 style="margin:0 0 15px 0; font-size:13px; color:var(--sm-primary-color); border-bottom:1px solid #e2e8f0; padding-bottom:8px;">العنوان المهني والسكن</h4>
                                        <div style="font-size:12px; line-height:1.8;">
                                            <strong>محافظة الإقامة:</strong> <?php echo esc_html($govs[$r->residence_governorate] ?? $r->residence_governorate); ?><br>
                                            <strong>المدينة:</strong> <?php echo esc_html($r->residence_city); ?><br>
                                            <strong>العنوان بالتفصيل:</strong> <?php echo esc_html($r->residence_street); ?><br>
                                            <strong>الدرجة المستهدفة:</strong> <?php echo esc_html($r->professional_grade); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 style="margin:0 0 15px 0; font-size:13px; color:var(--sm-primary-color); border-bottom:1px solid #e2e8f0; padding-bottom:8px;">مرفقات السداد والوثائق</h4>
                                        <div style="font-size:12px; line-height:1.8; margin-bottom:10px;">
                                            <strong>وسيلة الدفع:</strong> <?php echo esc_html($r->payment_method ?: '---'); ?><br>
                                            <strong>رقم المرجع:</strong> <?php echo esc_html($r->payment_reference ?: '---'); ?>
                                        </div>
                                        <div style="display:flex; flex-wrap:wrap; gap:10px;">
                                            <?php if($r->payment_screenshot_url): ?><a href="<?php echo esc_url($r->payment_screenshot_url); ?>" target="_blank" class="sm-doc-link">إيصال الدفع</a><?php endif; ?>
                                            <?php if($r->doc_qualification_url): ?><a href="<?php echo esc_url($r->doc_qualification_url); ?>" target="_blank" class="sm-doc-link">المؤهل</a><?php endif; ?>
                                            <?php if($r->doc_id_url): ?><a href="<?php echo esc_url($r->doc_id_url); ?>" target="_blank" class="sm-doc-link">البطاقة</a><?php endif; ?>
                                            <?php if($r->doc_photo_url): ?><a href="<?php echo esc_url($r->doc_photo_url); ?>" target="_blank" class="sm-doc-link">الصورة</a><?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div style="margin-top:25px; display:flex; gap:15px; border-top:1px solid #e2e8f0; padding-top:20px;">
                                    <?php if($r->status === 'Payment Under Review'): ?>
                                        <button onclick="processMembership(<?php echo $r->id; ?>, 'Payment Approved')" class="sm-btn" style="width:auto; background:#38a169;">تأكيد استلام الدفع</button>
                                    <?php endif; ?>
                                    <?php if($r->status === 'Payment Approved' || $r->status === 'Awaiting Physical Documents'): ?>
                                        <button onclick="processMembership(<?php echo $r->id; ?>, 'Under Final Review')" class="sm-btn" style="width:auto; background:#3182ce;">تأكيد استلام وفحص الأصول</button>
                                    <?php endif; ?>
                                    <button onclick="smPrintReqForm(<?php echo $r->id; ?>)" class="sm-btn sm-btn-outline" style="width:auto;">تحميل نموذج القيد PDF</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.sm-doc-link { display:inline-block; padding:4px 10px; background:#fff; border:1px solid #e2e8f0; border-radius:6px; color:var(--sm-primary-color); text-decoration:none; font-weight:700; font-size:11px; }
.sm-doc-link:hover { background:var(--sm-primary-color); color:#fff; border-color:var(--sm-primary-color); }
.sm-actions-dropdown:hover .sm-actions-content { display:block !important; }
</style>

<script>
function smApplyReqFilters() {
    const s = document.getElementById('req-search').value;
    const st = document.getElementById('req-status').value;
    const b = document.getElementById('req-branch').value;
    let url = 'admin.php?page=sm-membership-requests';
    if(s) url += '&search=' + encodeURIComponent(s);
    if(st) url += '&status_filter=' + encodeURIComponent(st);
    if(b) url += '&branch_filter=' + encodeURIComponent(b);
    location.href = url;
}

function smToggleReqDetails(id) {
    const row = document.getElementById('req-details-' + id);
    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}

function smPrintReqForm(id) {
    window.open(ajaxurl + '?action=sm_print&type=membership_form&request_id=' + id, '_blank');
}

function processMembership(requestId, status) {
    if (status === 'update') {
        const newStatus = prompt("أدخل الحالة الجديدة (مثال: Payment Under Review, Awaiting Physical Documents, Under Final Review):");
        if (!newStatus) return;
        status = newStatus;
    }

    let msg = "هل أنت متأكد من تغيير حالة الطلب؟";
    if(status === 'approved') msg = "هل أنت متأكد من القبول النهائي؟ سيتم إنشاء حساب عضو وتفعيل دخوله للنظام.";

    if (!confirm(msg)) return;

    const fd = new FormData();
    fd.append('action', 'sm_process_membership_request');
    fd.append('request_id', requestId);
    fd.append('status', status);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    fetch(ajaxurl, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('تم تحديث حالة الطلب بنجاح');
            location.reload();
        } else {
            alert('خطأ: ' + res.data);
        }
    });
}

function rejectMembership(requestId) {
    const reason = prompt("يرجى إدخال سبب الرفض ليتمكن المتقدم من رؤيته:");
    if (reason === null) return;
    if (!reason) return alert("يجب إدخال سبب الرفض.");

    const fd = new FormData();
    fd.append('action', 'sm_process_membership_request');
    fd.append('request_id', requestId);
    fd.append('status', 'rejected');
    fd.append('reason', reason);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    fetch(ajaxurl, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('تم رفض الطلب بنجاح');
            location.reload();
        } else {
            alert('خطأ: ' + res.data);
        }
    });
}
</script>

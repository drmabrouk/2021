<?php if (!defined('ABSPATH')) exit; ?>
<?php
$user = wp_get_current_user();
$roles = (array)$user->roles;
$is_official = in_array('sm_syndicate_admin', $roles) || in_array('sm_system_admin', $roles) || in_array('administrator', $roles);

$member_id = 0;
global $wpdb;
$member_by_wp = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", $user->ID));
if ($member_by_wp) $member_id = $member_by_wp->id;

// Fetch services
$services = SM_DB::get_services(['status' => $is_official ? 'any' : 'active', 'is_deleted' => 0]);
$deleted_services = $is_official ? SM_DB::get_services(['is_deleted' => 1]) : [];
$my_requests = $member_id ? SM_DB::get_service_requests(['member_id' => $member_id]) : [];
$all_requests = $is_official ? SM_DB::get_service_requests() : [];
?>

<div class="sm-services-container" dir="rtl">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h2 style="margin:0; font-weight: 800; color: var(--sm-dark-color);">الخدمات الرقمية</h2>
        <?php if ($is_official): ?>
            <button onclick="smOpenAddServiceModal()" class="sm-btn" style="width:auto;">+ إضافة خدمة جديدة</button>
        <?php endif; ?>
    </div>

    <div class="sm-tabs-wrapper" style="display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
        <button class="sm-tab-btn sm-active" onclick="smOpenInternalTab('available-services', this)">الخدمات المتاحة</button>
        <button class="sm-tab-btn" onclick="smOpenInternalTab('requests-history', this)"><?php echo $is_official ? 'طلبات الخدمة' : 'طلباتي السابقة'; ?></button>
        <?php if ($is_official): ?>
            <button class="sm-tab-btn" onclick="smOpenInternalTab('deleted-services', this)">الخدمات المحذوفة</button>
        <?php endif; ?>
    </div>

    <!-- TAB: Available Services -->
    <div id="available-services" class="sm-internal-tab">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
            <?php if (empty($services)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 50px; color: #94a3b8;">لا توجد خدمات متاحة حالياً.</div>
            <?php else: ?>
                <?php foreach ($services as $s):
                    $is_active = $s->status === 'active';
                ?>
                    <div class="sm-service-card" style="background: #fff; border: 1px solid var(--sm-border-color); border-radius: 15px; padding: 25px; display: flex; flex-direction: column; transition: 0.3s; box-shadow: 0 4px 6px rgba(0,0,0,0.02); opacity: <?php echo $is_active ? '1' : '0.7'; ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                            <div style="width: 50px; height: 50px; background: <?php echo $is_active ? 'var(--sm-primary-color)' : '#94a3b8'; ?>; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff;">
                                <span class="dashicons <?php echo esc_attr($s->icon ?: 'dashicons-cloud'); ?>" style="font-size: 24px; width: 24px; height: 24px;"></span>
                            </div>
                            <?php if ($is_official): ?>
                                <span class="sm-badge <?php echo $is_active ? 'sm-badge-high' : 'sm-badge-low'; ?>" style="font-size: 10px;">
                                    <?php echo $is_active ? 'نشطة' : 'معطلة'; ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h3 style="margin: 0 0 10px 0; font-weight: 800; color: var(--sm-dark-color);"><?php echo esc_html($s->name); ?></h3>
                        <p style="font-size: 13px; color: #64748b; line-height: 1.6; margin-bottom: 20px; flex: 1;"><?php echo esc_html($s->description); ?></p>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 15px; border-top: 1px solid #f1f5f9;">
                            <div style="font-weight: 700; color: var(--sm-primary-color);"><?php echo $s->fees > 0 ? number_format($s->fees, 2) . ' ج.م' : 'خدمة مجانية'; ?></div>
                            <?php if ($is_official): ?>
                                <div style="display: flex; gap: 5px;">
                                    <button class="sm-btn sm-btn-outline" style="padding: 5px 10px; font-size: 11px;" onclick='editService(<?php echo json_encode($s); ?>)'>تعديل</button>
                                    <?php if ($is_active): ?>
                                        <button class="sm-btn" style="padding: 5px 10px; font-size: 11px; background: #f6993f;" onclick="toggleServiceStatus(<?php echo $s->id; ?>, 'suspended')">تعطيل</button>
                                    <?php else: ?>
                                        <button class="sm-btn" style="padding: 5px 10px; font-size: 11px; background: #38a169;" onclick="toggleServiceStatus(<?php echo $s->id; ?>, 'active')">تنشيط</button>
                                    <?php endif; ?>
                                    <button class="sm-btn" style="padding: 5px 10px; font-size: 11px; background: #e53e3e;" onclick="deleteService(<?php echo $s->id; ?>)">حذف</button>
                                </div>
                            <?php else: ?>
                                <?php if ($is_active): ?>
                                    <button class="sm-btn" style="width: auto; padding: 8px 20px;" onclick='requestService(<?php echo json_encode($s); ?>)'>طلب الخدمة</button>
                                <?php else: ?>
                                    <button class="sm-btn" style="width: auto; padding: 8px 20px; background: #cbd5e0; cursor: not-allowed;" disabled>غير متوفرة</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Deleted Services (Trash) -->
    <?php if ($is_official): ?>
    <div id="deleted-services" class="sm-internal-tab" style="display: none;">
        <div class="sm-table-container">
            <table class="sm-table">
                <thead>
                    <tr>
                        <th>الخدمة</th>
                        <th>التصنيف</th>
                        <th>الرسوم</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deleted_services)): ?>
                        <tr><td colspan="4" style="text-align: center; padding: 40px;">لا توجد خدمات محذوفة حالياً.</td></tr>
                    <?php else: foreach ($deleted_services as $ds): ?>
                        <tr>
                            <td><strong><?php echo esc_html($ds->name); ?></strong></td>
                            <td><?php echo esc_html($ds->category); ?></td>
                            <td><?php echo number_format($ds->fees, 2); ?> ج.م</td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <button onclick="restoreService(<?php echo $ds->id; ?>)" class="sm-btn" style="width: auto; padding: 5px 15px; background: #38a169;">استعادة</button>
                                    <button onclick="deleteServicePermanent(<?php echo $ds->id; ?>)" class="sm-btn" style="width: auto; padding: 5px 15px; background: #e53e3e;">حذف نهائي</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- TAB: Requests History -->
    <div id="requests-history" class="sm-internal-tab" style="display: none;">
        <div class="sm-table-container">
            <table class="sm-table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>مقدم الطلب</th>
                        <th>بيانات التواصل</th>
                        <th>الخدمة المطلوبة</th>
                        <th>تاريخ الطلب</th>
                        <th>الحالة</th>
                        <th style="width: 150px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $target_requests = $is_official ? $all_requests : $my_requests;
                    if (empty($target_requests)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">لا توجد طلبات سابقة مسجلة في النظام.</td></tr>
                    <?php else:
                        $union_statuses = [
                            'pending' => 'قيد الانتظار',
                            'under_review' => 'قيد المراجعة الفنية',
                            'processing' => 'جاري التنفيذ',
                            'awaiting_payment' => 'بانتظار السداد',
                            'payment_verified' => 'تم تأكيد الدفع',
                            'approved' => 'مكتمل / معتمد',
                            'issued' => 'تم إصدار المستند',
                            'delivered' => 'تم التسليم للعضو',
                            'rejected' => 'مرفوض',
                            'cancelled' => 'ملغى من العضو',
                            'on_hold' => 'معلق مؤقتاً',
                            'needs_info' => 'نقص في البيانات'
                        ];
                        foreach ($target_requests as $r):
                            $status_label = $union_statuses[$r->status] ?? $r->status;
                            $status_class = in_array($r->status, ['approved', 'issued', 'delivered', 'payment_verified']) ? 'sm-badge-high' : (in_array($r->status, ['rejected', 'cancelled']) ? 'sm-badge-urgent' : 'sm-badge-low');

                            // Get member details for better display
                            $m_gov = SM_Settings::get_governorates()[$r->governorate] ?? $r->governorate;
                        ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--sm-primary-color);">#<?php echo $r->id; ?></td>
                                <td>
                                    <div style="font-weight: 800; color: var(--sm-dark-color);"><?php echo esc_html($r->member_name ?: 'طلب خارجي'); ?></div>
                                    <div style="font-size: 10px; color: #64748b; margin-top: 3px;">الرقم القومي: <?php echo esc_html($r->national_id ?: '---'); ?></div>
                                    <div style="font-size: 10px; color: var(--sm-primary-color); font-weight: 700;"><?php echo esc_html($m_gov ?: '---'); ?></div>
                                </td>
                                <td>
                                    <div style="font-size: 11px;"><?php echo esc_html($r->phone ?: '---'); ?></div>
                                    <div style="font-size: 11px; color: #64748b;"><?php echo esc_html($r->email ?: '---'); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 700;"><?php echo esc_html($r->service_name); ?></div>
                                    <div style="font-size: 10px; color: #38a169; font-weight: 700;"><?php echo number_format($r->fees_paid, 2); ?> ج.م مسددة</div>
                                </td>
                                <td><?php echo date_i18n('j F Y', strtotime($r->created_at)); ?></td>
                                <td><span class="sm-badge <?php echo $status_class; ?>" style="font-size: 11px; padding: 4px 10px;"><?php echo $status_label; ?></span></td>
                                <td>
                                    <div class="sm-actions-dropdown">
                                        <button class="sm-actions-trigger">الخيارات <span class="dashicons dashicons-arrow-down-alt2"></span></button>
                                        <div class="sm-actions-content">
                                            <a href="javascript:void(0)" onclick='viewRequest(<?php echo json_encode($r); ?>)' class="sm-action-item">
                                                <span class="dashicons dashicons-visibility"></span> تفاصيل البيانات
                                            </a>
                                            <a href="<?php echo admin_url('admin-ajax.php?action=sm_print_service_request&id=' . $r->id); ?>" target="_blank" class="sm-action-item" style="color: #27ae60;">
                                                <span class="dashicons dashicons-printer"></span> طباعة PDF
                                            </a>
                                            <?php if ($r->status == 'approved'): ?>
                                                <a href="<?php echo add_query_arg(['sm_tab' => 'member-profile', 'member_id' => $r->member_id, 'sub_tab' => 'documents']); ?>" class="sm-action-item">
                                                    <span class="dashicons dashicons-portfolio"></span> الأرشيف الرقمي
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($is_official): ?>
                                                <a href="javascript:void(0)" onclick="smOpenProcessModal(<?php echo $r->id; ?>, '<?php echo $r->status; ?>', '<?php echo esc_js($r->admin_notes); ?>')" class="sm-action-item" style="font-weight: 800; color: var(--sm-primary-color);">
                                                    <span class="dashicons dashicons-yes-alt"></span> تحديث الحالة والملاحظات
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="add-service-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 600px;">
        <div class="sm-modal-header"><h3>إضافة خدمة رقمية جديدة</h3><button class="sm-modal-close" onclick="document.getElementById('add-service-modal').style.display='none'">&times;</button></div>
        <form id="add-service-form" style="padding: 20px;">
            <div class="sm-form-group"><label class="sm-label">اسم الخدمة:</label><input name="name" type="text" class="sm-input" required></div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="sm-form-group"><label class="sm-label">تصنيف الخدمة:</label><input name="category" type="text" class="sm-input" placeholder="مثال: تراخيص، شهادات، إلخ"></div>
                <div class="sm-form-group"><label class="sm-label">كود الأيقونة (Dashicons):</label><input name="icon" type="text" class="sm-input" placeholder="dashicons-cloud" value="dashicons-cloud"></div>
            </div>
            <div class="sm-form-group"><label class="sm-label">وصف الخدمة:</label><textarea name="description" class="sm-textarea" rows="3"></textarea></div>
            <div class="sm-form-group"><label class="sm-label">الرسوم (0 للمجانية):</label><input name="fees" type="number" step="0.01" class="sm-input" value="0"></div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="sm-form-group">
                    <label class="sm-label">حالة الخدمة:</label>
                    <select name="status" class="sm-select">
                        <option value="active">نشطة (مفعلة)</option>
                        <option value="suspended">معطلة (موقوفة)</option>
                    </select>
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">تتطلب تسجيل دخول؟</label>
                    <select name="requires_login" class="sm-select">
                        <option value="1">نعم (للأعضاء فقط)</option>
                        <option value="0">لا (خدمة عامة)</option>
                    </select>
                </div>
            </div>

            <div class="sm-form-group">
                <label class="sm-label">حقول إضافية مطلوبة عند الطلب:</label>
                <div id="sm-required-fields-builder" style="background: #f1f5f9; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div id="fields-list"></div>
                    <button type="button" onclick="smAddRequiredField()" class="sm-btn sm-btn-outline" style="width: 100%; margin-top: 10px; font-size: 12px; background: #fff;">+ إضافة حقل إضافي</button>
                </div>
            </div>

            <div class="sm-form-group">
                <label class="sm-label">البيانات الشخصية المطلوبة من ملف العضو:</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <?php
                    $profile_fields = [
                        'name' => 'الاسم الكامل',
                        'national_id' => 'الرقم القومي',
                        'membership_number' => 'رقم العضوية',
                        'professional_grade' => 'الدرجة الوظيفية',
                        'specialization' => 'التخصص',
                        'phone' => 'رقم الهاتف',
                        'email' => 'البريد الإلكتروني',
                        'governorate' => 'الفرع',
                        'facility_name' => 'اسم المنشأة'
                    ];
                    foreach ($profile_fields as $key => $label): ?>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="profile_fields[]" value="<?php echo $key; ?>"> <?php echo $label; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="sm-btn" style="width: 100%; height: 45px; font-weight: 700; margin-top: 10px;">إضافة الخدمة وتفعيلها</button>
        </form>
    </div>
</div>

<div id="request-service-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 600px;">
        <div class="sm-modal-header"><h3>طلب خدمة: <span id="req-service-name"></span></h3><button class="sm-modal-close" onclick="document.getElementById('request-service-modal').style.display='none'">&times;</button></div>
        <form id="submit-request-form" style="padding: 20px;">
            <input type="hidden" name="service_id" id="req-service-id">
            <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
            <div id="dynamic-fields-container"></div>
            <div style="background: #fffaf0; padding: 15px; border-radius: 8px; border: 1px solid #feebc8; margin-top: 15px; font-size: 13px;">
                <strong>الرسوم المستحقة: </strong> <span id="req-service-fees"></span>
                <p style="margin: 5px 0 0 0; color: #744210;">* سيتم إضافة الرسوم إلى حسابك المالي عند اعتماد الطلب.</p>
            </div>
            <button type="submit" class="sm-btn" style="margin-top: 20px;">تأكيد وتقديم الطلب</button>
        </form>
    </div>
</div>

<div id="view-request-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 600px;">
        <div class="sm-modal-header"><h3>تفاصيل الطلب</h3><button class="sm-modal-close" onclick="document.getElementById('view-request-modal').style.display='none'">&times;</button></div>
        <div id="request-details-body" style="padding: 20px;"></div>
    </div>
</div>

<div id="process-request-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 500px;">
        <div class="sm-modal-header"><h3>تحديث حالة الطلب</h3><button class="sm-modal-close" onclick="document.getElementById('process-request-modal').style.display='none'">&times;</button></div>
        <form id="process-request-form" style="padding: 20px;">
            <input type="hidden" name="id" id="proc-req-id">
            <div class="sm-form-group">
                <label class="sm-label">الحالة الجديدة:</label>
                <select name="status" id="proc-req-status" class="sm-select">
                    <?php foreach($union_statuses as $slug => $label) echo "<option value='$slug'>$label</option>"; ?>
                </select>
            </div>
            <div class="sm-form-group">
                <label class="sm-label">ملاحظات إدارية (تظهر للعضو عند التتبع):</label>
                <textarea name="notes" id="proc-req-notes" class="sm-textarea" rows="4"></textarea>
            </div>
            <button type="submit" class="sm-btn" style="width: 100%;">حفظ التحديثات</button>
        </form>
    </div>
</div>

<script>
(function($) {
    window.smRefreshServicesList = function() {
        const container = $('#available-services');
        container.css('opacity', '0.5');
        fetch(ajaxurl + '?action=sm_get_services_html&nonce=<?php echo wp_create_nonce("sm_admin_action"); ?>&t=' + Date.now())
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const tempDiv = $('<div>').append($.parseHTML(res.data.html));
                    const newContent = tempDiv.find('#available-services').html();
                    container.html(newContent);
                    container.css('opacity', '1');
                }
            });
    };

    window.smAddRequiredField = function(data = {name: '', label: '', type: 'text'}) {
        const container = $('#fields-list');
        const id = Date.now() + Math.random();
        const html = `
            <div class="sm-field-row" id="field_${id}" style="display: flex; gap: 5px; margin-bottom: 8px;">
                <input type="text" placeholder="اسم الحقل (لاتيني)" class="sm-input req-field-name" value="${data.name}" style="flex: 1; font-size: 12px; padding: 5px;">
                <input type="text" placeholder="تسمية الحقل (عربي)" class="sm-input req-field-label" value="${data.label}" style="flex: 1; font-size: 12px; padding: 5px;">
                <select class="sm-select req-field-type" style="width: 80px; font-size: 11px; padding: 0 5px;">
                    <option value="text" ${data.type==='text'?'selected':''}>نص</option>
                    <option value="number" ${data.type==='number'?'selected':''}>رقم</option>
                    <option value="date" ${data.type==='date'?'selected':''}>تاريخ</option>
                </select>
                <button type="button" onclick="$('#field_${id}').remove()" class="sm-btn" style="background: #e53e3e; width: 30px; padding: 0;">&times;</button>
            </div>
        `;
        container.append(html);
    };

    window.smOpenAddServiceModal = function() {
        const modal = $('#add-service-modal');
        modal.find('h3').text('إضافة خدمة رقمية جديدة');
        const form = $('#add-service-form');
        form[0].reset();
        form.find('input[name="profile_fields[]"]').prop('checked', false);
        $('#fields-list').empty();

        form.off('submit').on('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);

            const profileFields = [];
            $(this).find('input[name="profile_fields[]"]:checked').each(function() {
                profileFields.push($(this).val());
            });
            fd.append('selected_profile_fields', JSON.stringify(profileFields));

            const reqFields = [];
            $('.sm-field-row').each(function() {
                const name = $(this).find('.req-field-name').val();
                const label = $(this).find('.req-field-label').val();
                const type = $(this).find('.req-field-type').val();
                if (name && label) reqFields.push({name, label, type});
            });
            fd.append('required_fields', JSON.stringify(reqFields));

            fd.append('action', 'sm_add_service');
            fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
            fetch(ajaxurl, {method: 'POST', body: fd}).then(r=>r.json()).then(res=>{
                if (res.success) {
                    smShowNotification('تم إضافة الخدمة بنجاح');
                    smRefreshServicesList();
                    $('#add-service-modal').fadeOut();
                } else {
                    smShowNotification(res.data, true);
                }
            });
        });
        modal.fadeIn().css('display', 'flex');
    };

    window.toggleServiceStatus = function(id, status) {
        const fd = new FormData();
        fd.append('action', 'sm_update_service');
        fd.append('id', id);
        fd.append('status', status);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
        fetch(ajaxurl, {method: 'POST', body: fd}).then(r=>r.json()).then(res=>{
            if (res.success) smRefreshServicesList();
        });
    };

    window.deleteService = function(id) {
        if (!confirm('هل أنت متأكد من نقل هذه الخدمة إلى سلة المحذوفات؟')) return;
        const fd = new FormData();
        fd.append('action', 'sm_delete_service');
        fd.append('id', id);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
        fetch(ajaxurl, {method: 'POST', body: fd}).then(r=>r.json()).then(res=>{
            if (res.success) location.reload();
        });
    };

    window.restoreService = function(id) {
        if (!confirm('هل أنت متأكد من استعادة هذه الخدمة؟')) return;
        const fd = new FormData();
        fd.append('action', 'sm_restore_service');
        fd.append('id', id);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
        fetch(ajaxurl, {method: 'POST', body: fd}).then(r=>r.json()).then(res=>{
            if (res.success) location.reload();
        });
    };

    window.deleteServicePermanent = function(id) {
        if (!confirm('تحذير: سيتم حذف الخدمة نهائياً من قاعدة البيانات. هل أنت متأكد؟')) return;
        const fd = new FormData();
        fd.append('action', 'sm_delete_service');
        fd.append('id', id);
        fd.append('permanent', 1);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
        fetch(ajaxurl, {method: 'POST', body: fd}).then(r=>r.json()).then(res=>{
            if (res.success) location.reload();
        });
    };

    window.editService = function(s) {
        const modal = $('#add-service-modal');
        modal.find('h3').text('تعديل الخدمة: ' + s.name);
        modal.find('[name="name"]').val(s.name);
        modal.find('[name="category"]').val(s.category);
        modal.find('[name="icon"]').val(s.icon || 'dashicons-cloud');
        modal.find('[name="description"]').val(s.description);
        modal.find('[name="fees"]').val(s.fees);
        modal.find('[name="status"]').val(s.status);
        modal.find('[name="requires_login"]').val(s.requires_login);

        $('#fields-list').empty();
        if (s.required_fields) {
            try {
                const fields = JSON.parse(s.required_fields);
                fields.forEach(f => smAddRequiredField(f));
            } catch(e) {}
        }

        modal.find('input[name="profile_fields[]"]').prop('checked', false);
        if (s.selected_profile_fields) {
            try {
                const fields = JSON.parse(s.selected_profile_fields);
                fields.forEach(f => {
                    modal.find(`input[value="${f}"]`).prop('checked', true);
                });
            } catch(e) {}
        }

        $('#add-service-form').off('submit').on('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const profileFields = [];
            $(this).find('input[name="profile_fields[]"]:checked').each(function() {
                profileFields.push($(this).val());
            });
            fd.append('selected_profile_fields', JSON.stringify(profileFields));

            const reqFields = [];
            $('.sm-field-row').each(function() {
                const name = $(this).find('.req-field-name').val();
                const label = $(this).find('.req-field-label').val();
                const type = $(this).find('.req-field-type').val();
                if (name && label) reqFields.push({name, label, type});
            });
            fd.append('required_fields', JSON.stringify(reqFields));

            fd.append('id', s.id);
            fd.append('action', 'sm_update_service');
            fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

            fetch(ajaxurl, {method: 'POST', body: fd}).then(r=>r.json()).then(res=>{
                if (res.success) {
                    smShowNotification('تم تحديث الخدمة بنجاح');
                    smRefreshServicesList();
                    $('#add-service-modal').fadeOut();
                } else {
                    smShowNotification(res.data, true);
                }
            });
        });

        modal.fadeIn().css('display', 'flex');
    };

    window.requestService = function(s) {
        $('#req-service-name').text(s.name);
        $('#req-service-id').val(s.id);
        $('#req-service-fees').text(s.fees > 0 ? s.fees + ' ج.م' : 'مجاناً');

        const container = $('#dynamic-fields-container').empty();

        // Add notice about profile fields
        if (s.selected_profile_fields) {
            const pFields = JSON.parse(s.selected_profile_fields);
            if (pFields.length > 0) {
                container.append('<p style="font-size:12px; color:#666; margin-bottom:15px; background:#f0f4f8; padding:10px; border-radius:5px;">سيتم سحب بياناتك الشخصية (الاسم، الرقم القومي، إلخ) تلقائياً من ملفك الشخصي لإدراجها في المستند.</p>');
            }
        }

        try {
            const fields = JSON.parse(s.required_fields);
            fields.forEach(f => {
                container.append(`
                    <div class="sm-form-group">
                        <label class="sm-label">${f.label}:</label>
                        <input name="field_${f.name}" type="${f.type || 'text'}" class="sm-input" required>
                    </div>
                `);
            });
        } catch(e) { console.error(e); }

        $('#request-service-modal').fadeIn().css('display', 'flex');
    };

    $('#submit-request-form').on('submit', function(e) {
        e.preventDefault();
        const data = {};
        $(this).serializeArray().forEach(item => {
            if (item.name.startsWith('field_')) data[item.name.replace('field_', '')] = item.value;
        });

        const fd = new FormData();
        fd.append('action', 'sm_submit_service_request');
        fd.append('service_id', $('#req-service-id').val());
        fd.append('member_id', $(this).find('[name="member_id"]').val());
        fd.append('request_data', JSON.stringify(data));
        fd.append('nonce', '<?php echo wp_create_nonce("sm_service_action"); ?>');

        fetch(ajaxurl, {method: 'POST', body: fd}).then(r=>r.json()).then(res=>{
            if (res.success) {
                smShowNotification('تم تقديم الطلب بنجاح');
                setTimeout(() => location.reload(), 1000);
            } else {
                smShowNotification(res.data, true);
            }
        });
    });

    window.viewRequest = function(r) {
        const body = $('#request-details-body').empty();
        const data = JSON.parse(r.request_data);
        let html = `
            <div style="background:#f8fafc; padding:15px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:20px;">
                <h4 style="margin:0 0 10px 0; color:var(--sm-primary-color);">بيانات مقدم الطلب</h4>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:13px;">
                    <div><strong>الاسم:</strong> ${r.member_name || 'طلب خارجي'}</div>
                    <div><strong>الرقم القومي:</strong> ${r.national_id || '---'}</div>
                    <div><strong>رقم الهاتف:</strong> ${r.phone || '---'}</div>
                    <div><strong>البريد:</strong> ${r.email || '---'}</div>
                </div>
            </div>
            <div style="margin-bottom:20px;"><strong style="color:var(--sm-dark-color);">الخدمة المطلوبة:</strong> <span class="sm-badge sm-badge-low">${r.service_name}</span></div>
            <h4 style="border-bottom:1px solid #eee; padding-bottom:8px;">بيانات نموذج الخدمة</h4>
            <div style="display:grid; gap:12px; margin-top:10px;">`;

        for (let k in data) {
            html += `<div style="background:#fff; padding:10px; border-radius:5px; border:1px solid #f1f5f9;">
                        <span style="color:#64748b; font-weight:600; font-size:11px; display:block; margin-bottom:3px;">${k}</span>
                        <div style="font-weight:700;">${data[k]}</div>
                     </div>`;
        }
        html += `</div>`;
        body.append(html);
        $('#view-request-modal').fadeIn().css('display', 'flex');
    };

    window.smOpenProcessModal = function(id, status, notes) {
        $('#proc-req-id').val(id);
        $('#proc-req-status').val(status);
        $('#proc-req-notes').val(notes);
        $('#process-request-modal').fadeIn().css('display', 'flex');
    };

    $('#process-request-form').on('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'sm_process_service_request');
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

        fetch(ajaxurl, {method: 'POST', body: fd}).then(r=>r.json()).then(res=>{
            if (res.success) {
                smShowNotification('تم تحديث الطلب بنجاح');
                setTimeout(() => location.reload(), 1000);
            } else {
                smShowNotification(res.data, true);
            }
        });
    });

    window.smRollbackLog = function(logId) {
        if (!confirm('هل أنت متأكد من استعادة هذه الخدمة؟')) return;
        const fd = new FormData();
        fd.append('action', 'sm_rollback_log_ajax');
        fd.append('log_id', logId);
        fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
        fetch(ajaxurl, {method: 'POST', body: fd}).then(r=>r.json()).then(res=>{
            if (res.success) {
                smShowNotification('تمت الاستعادة بنجاح');
                smRefreshServicesList();
                location.reload();
            } else {
                smShowNotification(res.data, true);
            }
        });
    };

})(jQuery);
</script>

<?php if (!defined('ABSPATH')) exit; ?>
<div class="sm-public-page" dir="rtl">
    <div class="sm-tracking-search-box" style="background: linear-gradient(135deg, #fff 0%, #f9fafb 100%); border: 1px solid #e2e8f0; border-radius: 30px; padding: 40px; margin-bottom: 50px; color: var(--sm-dark-color); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);">
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 50px; height: 50px; background: rgba(246, 48, 73, 0.1); border-radius: 15px; margin-bottom: 15px;"><span class="dashicons dashicons-search" style="font-size: 24px; width: 24px; height: 24px; color: var(--sm-primary-color);"></span></div>
            <h2 style="margin: 0; font-weight: 900; font-size: 2em; color: var(--sm-dark-color);">متابعة حالة الطلبات</h2>
            <p style="margin: 10px 0 0 0; color: #64748b; font-size: 15px; font-weight: 500;">استعلم عن حالة طلبك الرقمي لحظياً باستخدام كود التتبع الموحد</p>
        </div>
        <div style="display: flex; gap: 15px; max-width: 650px; margin: 0 auto; background: #fff; padding: 10px; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.02);">
            <div style="flex: 1; position: relative; display: flex; align-items: center;"><span class="dashicons dashicons-text-page" style="position: absolute; right: 15px; color: #94a3b8;"></span><input type="text" id="sm_service_tracking_input" placeholder="أدخل كود الطلب" style="width: 100%; padding: 15px 40px 15px 20px; border-radius: 15px; border: 1px solid transparent; background: #f8fafc; color: var(--sm-dark-color); font-family: 'Rubik', sans-serif; font-size: 15px; outline: none; transition: 0.3s; font-weight: 500;"></div>
            <button onclick="smTrackServiceRequest()" style="background: var(--sm-primary-color); color: #fff; border: none; padding: 0 35px; border-radius: 15px; font-weight: 800; font-size: 15px; cursor: pointer; transition: 0.3s; font-family: 'Rubik', sans-serif; box-shadow: 0 4px 12px rgba(246, 48, 73, 0.3);">بحث وتتبع</button>
        </div>
        <div id="sm-tracking-results-area" style="margin-top: 30px; display: none; background: #fff; border-radius: 20px; padding: 30px; border: 1px solid #e2e8f0; animation: smFadeIn 0.4s ease; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);"></div>
        <?php if ($is_logged_in && $current_member): ?>
            <?php global $wpdb; $my_requests = $wpdb->get_results($wpdb->prepare("SELECT r.*, s.name as service_name FROM {$wpdb->prefix}sm_service_requests r JOIN {$wpdb->prefix}sm_services s ON r.service_id = s.id WHERE r.member_id = %d ORDER BY r.created_at DESC LIMIT 5", $current_member->id)); if ($my_requests): ?>
            <div style="margin-top: 35px; border-top: 1px solid #e2e8f0; padding-top: 25px;"><h4 style="margin: 0 0 15px 0; font-weight: 800; color: var(--sm-dark-color); display: flex; align-items: center; gap: 10px;"><span class="dashicons dashicons-clock" style="color:var(--sm-primary-color);"></span> طلباتك الأخيرة</h4><div style="display: grid; gap: 10px;"><?php foreach ($my_requests as $mr): $track_code = date('Ymd', strtotime($mr->created_at)) . $mr->id; $labels = ['pending' => 'قيد الانتظار', 'approved' => 'مكتمل', 'rejected' => 'مرفوض']; ?><div style="background: #fff; padding: 12px 15px; border-radius: 12px; border: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; transition: 0.2s; cursor: pointer;" onclick="document.getElementById('sm_service_tracking_input').value='<?php echo $track_code; ?>'; smTrackServiceRequest();"><div><div style="font-weight: 700; color: var(--sm-dark-color); font-size: 14px;"><?php echo esc_html($mr->service_name); ?></div><div style="font-size: 10px; color: #94a3b8; margin-top: 2px;">كود التتبع: #<?php echo $track_code; ?></div></div><div style="display: flex; align-items: center; gap: 10px;"><span style="font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0;"><?php echo $labels[$mr->status] ?? $mr->status; ?></span><span class="dashicons dashicons-arrow-left-alt2" style="font-size: 14px; color: var(--sm-primary-color);"></span></div></div><?php endforeach; ?></div></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="sm-services-layout" style="display: flex; gap: 30px; margin-top: 30px; align-items: flex-start;">
        <div class="sm-services-sidebar" style="width: 280px; flex-shrink: 0; background: #fff; border: 1px solid var(--sm-border-color); border-radius: 20px; padding: 25px; position: sticky; top: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);"><h4 style="margin: 0 0 20px 0; font-weight: 800; color: var(--sm-dark-color); display: flex; align-items: center; gap: 10px; font-size: 1em;"><span style="display:flex; align-items:center; justify-content:center; width:28px; height:28px; background:var(--sm-primary-color); color:#fff; border-radius:8px;"><span class="dashicons dashicons-filter" style="font-size: 16px; width: 16px; height: 16px;"></span></span> فلترة الخدمات</h4><div style="margin-bottom: 20px;"><label class="sm-label" style="font-size: 12px; margin-bottom: 5px; display: block; color: #64748b;">البحث بالاسم:</label><div style="position: relative;"><input type="text" id="sm_service_search_filter" placeholder="ابحث..." style="width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid #e2e8f0; font-family: 'Rubik', sans-serif; outline: none;" oninput="smApplyServiceFilters()"><span class="dashicons dashicons-search" style="position: absolute; left: 8px; top: 8px; color: #94a3b8; font-size: 16px;"></span></div></div><div style="margin-bottom: 20px;"><label class="sm-label" style="font-size: 12px; margin-bottom: 5px; display: block; color: #64748b;">تصنيف الخدمة:</label><select id="sm_service_cat_filter" class="sm-select" onchange="smApplyServiceFilters()" style="width: 100%; border-radius: 10px; font-size: 13px;"><?php foreach ($categories as $cat): ?><option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option><?php endforeach; ?></select></div></div>
        <div class="sm-services-grid-wrapper" style="flex: 1;">
            <div id="sm-services-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                <?php if (empty($services)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #94a3b8; background: #fff; border-radius: 15px; border: 1px dashed #cbd5e0;"><p>لا توجد خدمات متاحة حالياً.</p></div>
                <?php else:
                    $count = 0;
                    foreach ($services as $s):
                        $count++;
                        $s_cat = $s->category ?: 'عام';
                        $access_type = $s->requires_login ? 'members' : 'public';
                ?>
                    <div class="sm-service-card-modern" data-category="<?php echo esc_attr($s_cat); ?>" data-name="<?php echo esc_attr($s->name); ?>" data-access="<?php echo $access_type; ?>" style="background: #fff; border: 1px solid var(--sm-border-color); border-radius: 20px; padding: 25px; display: <?php echo $count > 6 ? 'none' : 'flex'; ?>; flex-direction: column; transition: all 0.3s ease; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                            <div class="sm-service-icon" style="width: 50px; height: 50px; background: linear-gradient(135deg, var(--sm-primary-color), var(--sm-secondary-color)); border-radius: 15px; display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 8px 12px -3px rgba(246, 48, 73, 0.2);"><span class="dashicons <?php echo esc_attr($s->icon ?: 'dashicons-cloud'); ?>" style="font-size: 24px; width: 24px; height: 24px;"></span></div>
                            <div><span style="display: inline-block; padding: 4px 10px; background: #f0f4f8; color: #4a5568; border-radius: 8px; font-size: 10px; font-weight: 700;"><?php echo esc_html($s_cat); ?></span></div>
                        </div>
                        <h3 style="margin: 0 0 10px 0; font-weight: 800; color: var(--sm-dark-color); font-size: 1.3em; line-height: 1.3;"><?php echo esc_html($s->name); ?></h3>
                        <p style="font-size: 13px; color: #64748b; line-height: 1.6; margin-bottom: 20px; flex: 1;"><?php echo esc_html($s->description); ?></p>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                            <div style="display: flex; flex-direction: column;"><span style="font-size: 10px; color: #94a3b8; font-weight: 600;">رسوم الخدمة</span><span style="font-weight: 900; color: var(--sm-primary-color); font-size: 1.1em;"><?php echo $s->fees > 0 ? number_format($s->fees, 2) . ' <small>ج.م</small>' : 'خدمة مجانية'; ?></span></div>
                            <?php $btn_onclick = $is_logged_in ? "smOpenProgressiveForm(this, ".json_encode($s).")" : "window.location.href='".esc_url($login_url)."'"; ?>
                            <button onclick='<?php echo $btn_onclick; ?>' class="sm-btn-sleek sm-service-trigger" style="background: var(--sm-dark-color); color: #fff; padding: 8px 20px; border: none; border-radius: 12px; font-weight: 700; font-size: 13px; cursor: pointer; transition: 0.3s;">طلب خدمة</button>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <?php if (count($services) > 6): ?>
                <div style="text-align: center; margin-top: 40px;">
                    <button id="sm_load_more_services" onclick="smLoadMoreServices()" class="sm-btn sm-btn-outline" style="width: auto; padding: 12px 50px; font-weight: 800; font-size: 15px; border-radius: 15px;">عرض المزيد من الخدمات</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="sm-service-dropdown-container" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(5px); z-index:100000; justify-content:center; align-items:center; padding:20px;"><div id="sm-service-dropdown-content" style="background:#fff; width:100%; max-width:550px; border-radius:24px; padding:40px; position:relative;"><button onclick="document.getElementById('sm-service-dropdown-container').style.display='none'" style="position:absolute; top:20px; left:20px; border:none; background:none; font-size:24px; cursor:pointer; color:#94a3b8;">&times;</button><div id="sm-dropdown-body"></div></div></div>

<script>
window.smLoadMoreServices = function() {
    const hiddenCards = document.querySelectorAll('.sm-service-card-modern[style*="display: none"]');
    for (let i = 0; i < Math.min(hiddenCards.length, 6); i++) {
        hiddenCards[i].style.display = 'flex';
    }
    if (document.querySelectorAll('.sm-service-card-modern[style*="display: none"]').length === 0) {
        document.getElementById('sm_load_more_services').style.display = 'none';
    }
};

window.smTrackServiceRequest = function() {
    const code = document.getElementById('sm_service_tracking_input').value; const area = document.getElementById('sm-tracking-results-area'); if(!code) return alert('يرجى إدخال كود التتبع');
    const fd = new FormData(); fd.append('action', 'sm_track_service_request'); fd.append('tracking_code', code);
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{ area.style.display = 'block'; if(res.success) { const r = res.data; area.innerHTML = `<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: separate; background: #fff; border-radius: 15px; overflow: hidden; border: 1px solid #f1f5f9;"><thead style="background: #f8fafc;"><tr><th style="padding: 15px 20px; text-align: right; font-weight: 800; color: var(--sm-dark-color);">البيان</th><th style="padding: 15px 20px; text-align: right; font-weight: 800; color: var(--sm-dark-color);">التفاصيل</th></tr></thead><tbody><tr><td style="padding: 15px 20px; color: #64748b; font-weight: 600;">كود التتبع</td><td style="padding: 15px 20px; color: var(--sm-dark-color); font-weight: 800;">${code}</td></tr><tr><td style="padding: 15px 20px; color: #64748b; font-weight: 600;">نوع الخدمة</td><td style="padding: 15px 20px; color: var(--sm-dark-color); font-weight: 700;">${r.service}</td></tr><tr><td style="padding: 15px 20px; color: #64748b; font-weight: 600;">الحالة الحالية</td><td style="padding: 15px 20px;"><span style="background: rgba(246, 48, 73, 0.1); color: var(--sm-primary-color); padding: 5px 15px; border-radius: 20px; font-weight: 800; font-size: 14px;">${r.status}</span></td></tr></tbody></table></div>`; } else area.innerHTML = `<div style="text-align:center; color:#e53e3e; font-weight:700;">${res.data}</div>`; });
};

window.smOpenProgressiveForm = function(btn, s) {
    const container = document.getElementById('sm-service-dropdown-container'); const body = document.getElementById('sm-dropdown-body'); container.style.display = 'flex';
    let reqFields = []; try { reqFields = JSON.parse(s.required_fields); } catch(e){}
    body.innerHTML = `<div style="margin-bottom:30px;"><h3 style="margin:0; font-weight:900; color:var(--sm-dark-color);">طلب خدمة: ${s.name}</h3></div><form id="sm-public-service-form"><input type="hidden" name="service_id" value="${s.id}"><div class="sm-form-step active" id="step-1"><h4 style="margin-bottom:20px; color:var(--sm-primary-color); font-weight:800;">1. البيانات المطلوبة</h4>${reqFields.length > 0 ? reqFields.map(f => `<div class="sm-form-group"><label class="sm-label">${f.label}:</label><input name="field_${f.name}" type="${f.type||'text'}" class="sm-input" required></div>`).join('') : '<p>لا توجد حقول إضافية مطلوبة.</p>'}<button type="submit" class="sm-btn" style="width:100%; margin-top:20px;">تأكيد وتقديم الطلب</button></div></form>`;
    document.getElementById('sm-public-service-form').onsubmit = function(e) {
        e.preventDefault(); const formData = new FormData(this); const data = {}; formData.forEach((value, key) => { if(key.startsWith('field_')) data[key.replace('field_', '')] = value; });
        const fd = new FormData(); fd.append('action', 'sm_submit_service_request'); fd.append('service_id', formData.get('service_id')); fd.append('member_id', '<?php echo $current_member ? $current_member->id : 0; ?>'); fd.append('request_data', JSON.stringify(data));
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{ if(res.success) { body.innerHTML = `<div style="text-align:center;"><div style="font-size:60px; margin-bottom:20px;">✅</div><h3 style="font-weight:900; font-size:1.8em;">تم تقديم طلبك بنجاح!</h3><div style="background:#f8fafc; border:2px dashed var(--sm-primary-color); padding:15px; font-size:24px; font-weight:900; color:var(--sm-primary-color); border-radius:15px; margin-bottom:30px;">${res.data}</div><button onclick="location.reload()" class="sm-btn" style="width:100%;">إغلاق</button></div>`; } else alert(res.data); });
    };
};

function smApplyServiceFilters() {
    const q = document.getElementById('sm_service_search_filter').value.toLowerCase(); const cat = document.getElementById('sm_service_cat_filter').value;
    const cards = document.querySelectorAll('.sm-service-card-modern');
    let visibleCount = 0;
    cards.forEach(card => {
        const matches = card.dataset.name.toLowerCase().includes(q) && (cat === 'الكل' || card.dataset.category === cat);
        if (matches) {
            visibleCount++;
            card.style.display = visibleCount <= 6 ? 'flex' : 'none';
        } else {
            card.style.display = 'none';
        }
    });
    document.getElementById('sm_load_more_services').style.display = visibleCount > 6 ? 'inline-block' : 'none';
}
</script>

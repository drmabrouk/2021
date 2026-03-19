<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Authentication Module
 * Handles login, registration, OTP, and account activation.
 */
class SM_Auth {
    public static function register_shortcodes() {
        add_shortcode('sm_login', array(__CLASS__, 'shortcode_login'));
        add_shortcode('login-page', array(__CLASS__, 'shortcode_login_page'));
    }

    public static function shortcode_login() {
        if (is_user_logged_in()) {
            wp_redirect(home_url('/sm-admin'));
            exit;
        }
        $syndicate = SM_Settings::get_syndicate_info();
        ob_start();
        ?>
        <div class="sm-login-container" style="display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; background: #f8fafc;">
            <div class="sm-login-box" style="width: 100%; max-width: 420px; background: #ffffff; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.08); overflow: hidden; border: 1px solid #f1f5f9;" dir="rtl">
                <div style="background: var(--sm-dark-color); padding: 30px 25px; text-align: center; color: #fff; position: relative;">
                    <?php if (!empty($syndicate['syndicate_logo'])): ?>
                        <img src="<?php echo esc_url($syndicate['syndicate_logo']); ?>" style="max-height: 60px; margin-bottom: 15px; display: inline-block; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
                    <?php endif; ?>
                    <h2 style="margin: 0; font-weight: 900; color: #fff; font-size: 1.4em; letter-spacing: -0.5px;"><?php echo esc_html($syndicate['syndicate_name']); ?></h2>
                    <p style="margin: 5px 0 0 0; color: #e2e8f0; font-size: 0.8em; opacity: 0.9;">المنصة الرقمية للخدمات النقابية الموحدة</p>
                </div>
                <div style="padding: 30px 30px;">
                    <?php if (isset($_GET['login']) && $_GET['login'] == 'failed'): ?>
                        <div style="background: #fff5f5; color: #c53030; padding: 10px; border-radius: 8px; border: 1px solid #feb2b2; margin-bottom: 20px; font-size: 0.85em; text-align: center; font-weight: 600;">⚠️ بيانات الدخول غير صحيحة</div>
                    <?php endif; ?>
                    <style>
                        #sm_login_form p { margin-bottom: 15px; position: relative; }
                        #sm_login_form label { display: none; }
                        #sm_login_form input[type="text"], #sm_login_form input[type="password"] {
                            width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 10px;
                            background: #fcfcfc; font-size: 14px; transition: 0.3s; font-family: "Rubik", sans-serif;
                        }
                        #sm_login_form input:focus { border-color: var(--sm-primary-color); outline: none; background: #fff; }
                        #sm_login_form .login-remember { display: flex; align-items: center; gap: 8px; font-size: 0.8em; color: #64748b; margin-top: -5px; }
                        #sm_login_form input[type="submit"] {
                            width: 100%; padding: 14px; background: var(--sm-primary-color); color: #fff; border: none;
                            border-radius: 10px; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.3s;
                        }
                        #sm_login_form input[type="submit"]:hover { opacity: 0.9; transform: translateY(-1px); }
                        .sm-login-footer-links { margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
                        .sm-footer-btn { text-decoration: none !important; padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 700; text-align: center; transition: 0.2s; border: 1px solid #e2e8f0; color: #4a5568; box-shadow: none !important; }
                        .sm-footer-btn:hover { background: #f8fafc; border-color: #cbd5e0; }
                        .sm-footer-btn-primary { background: #f1f5f9; color: var(--sm-dark-color) !important; border: 1px solid #e2e8f0; }
                        .sm-footer-btn-primary:hover { background: #e2e8f0; }
                        .sm-password-toggle { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; transition: 0.2s; z-index: 5; }
                        .sm-password-toggle:hover { color: var(--sm-primary-color); }
                    </style>
                    <?php
                    $args = array(
                        'echo' => false,
                        'redirect' => home_url('/sm-admin'),
                        'form_id' => 'sm_login_form',
                        'label_remember' => 'تذكرني',
                        'label_log_in' => 'دخول النظام',
                        'remember' => true
                    );
                    $form = wp_login_form($args);
                    $form = str_replace('name="log"', 'name="log" placeholder="الرقم القومي أو اسم المستخدم"', $form);
                    $form = str_replace('name="pwd"', 'name="pwd" id="sm_login_pwd" placeholder="كلمة المرور"', $form);
                    $form = str_replace('</p>', '<span class="dashicons dashicons-visibility sm-password-toggle" onclick="smTogglePass(\'sm_login_pwd\', this)"></span></p>', $form);
                    $form = preg_replace('/(<input[^>]+name="log"[^>]+>)\s*<span[^>]+><\/span>/', '$1', $form);
                    echo $form;
                    ?>
                    <div class="sm-login-footer-links">
                        <a href="javascript:void(0)" onclick="smToggleRegistration()" class="sm-footer-btn sm-footer-btn-primary">عضو جديد</a>
                        <a href="javascript:void(0)" onclick="smToggleActivation()" class="sm-footer-btn">تفعيل حساب</a>
                        <a href="javascript:void(0)" onclick="smToggleRecovery()" style="grid-column: span 2; color: #64748b; font-size: 12px; text-decoration: none; text-align: center; margin-top: 10px;">نسيت كلمة المرور؟</a>
                    </div>
                </div>
            </div>
        </div>

        <?php
        include SM_PLUGIN_DIR . 'includes/modules/auth/login-modals.php';
        return ob_get_clean();
    }

    public static function shortcode_login_page() {
        if (!is_user_logged_in()) {
            return '<div class="sm-topbar-login" style="display:flex; align-items:center; gap:8px; font-weight:700; margin:0; padding:0;">
                <span class="dashicons dashicons-lock" style="color:#e53e3e; font-size:20px; width:20px; height:20px;"></span>
                <a href="' . home_url('/sm-login') . '" style="text-decoration:none; color:inherit;">Register / Login</a>
            </div>';
        }
        $user = wp_get_current_user();
        $greeting = ((int)current_time('G') >= 5 && (int)current_time('G') < 12) ? 'صباح الخير' : 'مساء الخير';
        ob_start();
        ?>
        <div class="sm-topbar-user-wrap" style="position:relative; display:inline-block; margin:0; padding:0;">
            <div class="sm-user-dropdown">
                <div class="sm-user-profile-nav" onclick="smToggleUserDropdown()" style="display: flex; align-items: center; gap: 10px; background: #fff; padding: 5px 10px; border-radius: 50px; border: 1px solid #e2e8f0; cursor: pointer; transition: 0.2s;">
                    <div style="text-align: right;">
                        <div style="font-size: 11px; font-weight: 700; color: var(--sm-dark-color); line-height: 1.2;"><?php echo $greeting . '، ' . $user->display_name; ?></div>
                        <div style="font-size: 9px; color: #38a169;">متصل الآن <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 8px; width: 8px; height: 8px;"></span></div>
                    </div>
                    <?php echo get_avatar($user->ID, 28, '', '', array('style' => 'border-radius: 50%; border: 2px solid var(--sm-primary-color); width: 28px; height: 28px; object-fit: cover;')); ?>
                </div>
                <div id="sm-user-dropdown-menu" style="display: none; position: absolute; top: 110%; left: 0; background: white; border: 1px solid var(--sm-border-color); border-radius: 12px; width: 280px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); z-index: 100000; animation: smFadeIn 0.2s ease-out; padding: 8px 0; margin: 0;">
                    <div id="sm-profile-view">
                        <div style="padding: 10px 20px; border-bottom: 1px solid #f0f0f0; margin-bottom: 5px;">
                            <div style="font-weight: 800; color: var(--sm-dark-color);"><?php echo $user->display_name; ?></div>
                            <div style="font-size: 11px; color: var(--sm-text-gray);"><?php echo $user->user_email; ?></div>
                        </div>
                        <?php if (!in_array('sm_member', (array)$user->roles)): ?>
                            <a href="javascript:smEditProfile()" class="sm-dropdown-item"><span class="dashicons dashicons-edit"></span> تعديل البيانات الشخصية</a>
                        <?php else: ?>
                            <a href="javascript:smEditProfile()" class="sm-dropdown-item"><span class="dashicons dashicons-lock"></span> تغيير كلمة المرور</a>
                        <?php endif; ?>
                        <?php if (current_user_can('manage_options')): ?>
                            <a href="<?php echo add_query_arg('sm_tab', 'global-settings', home_url('/sm-admin')); ?>" class="sm-dropdown-item"><span class="dashicons dashicons-admin-generic"></span> إعدادات النظام</a>
                        <?php endif; ?>
                        <a href="javascript:location.reload()" class="sm-dropdown-item"><span class="dashicons dashicons-update"></span> تحديث الصفحة</a>
                    </div>
                    <div id="sm-profile-edit" style="display: none; padding: 15px;">
                        <div style="font-weight: 800; margin-bottom: 15px; font-size: 13px; border-bottom: 1px solid #eee; padding-bottom: 10px;">تعديل الملف الشخصي</div>
                        <div class="sm-form-group" style="margin-bottom: 10px;">
                            <label class="sm-label" style="font-size: 11px;">الاسم المفضل:</label>
                            <input type="text" id="sm_edit_display_name" class="sm-input" style="padding: 8px; font-size: 12px;" value="<?php echo esc_attr($user->display_name); ?>" <?php if (in_array('sm_member', (array)$user->roles)) echo 'disabled style="background:#f1f5f9; cursor:not-allowed;"'; ?>>
                        </div>
                        <div class="sm-form-group" style="margin-bottom: 10px;">
                            <label class="sm-label" style="font-size: 11px;">البريد الإلكتروني:</label>
                            <input type="email" id="sm_edit_user_email" class="sm-input" style="padding: 8px; font-size: 12px;" value="<?php echo esc_attr($user->user_email); ?>" <?php if (in_array('sm_member', (array)$user->roles)) echo 'disabled style="background:#f1f5f9; cursor:not-allowed;"'; ?>>
                        </div>
                        <div class="sm-form-group" style="margin-bottom: 15px;">
                            <label class="sm-label" style="font-size: 11px;">كلمة مرور جديدة (اختياري):</label>
                            <input type="password" id="sm_edit_user_pass" class="sm-input" style="padding: 8px; font-size: 12px;" placeholder="********">
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button onclick="smSaveProfile()" class="sm-btn" style="flex: 1; height: 32px; font-size: 11px; padding: 0;">حفظ</button>
                            <button onclick="document.getElementById('sm-profile-edit').style.display='none'; document.getElementById('sm-profile-view').style.display='block';" class="sm-btn sm-btn-outline" style="flex: 1; height: 32px; font-size: 11px; padding: 0;">إلغاء</button>
                        </div>
                    </div>
                    <hr style="margin: 5px 0; border: none; border-top: 1px solid #eee;">
                    <a href="<?php echo wp_logout_url(home_url('/sm-login')); ?>" class="sm-dropdown-item" style="color: #e53e3e;"><span class="dashicons dashicons-logout"></span> تسجيل الخروج</a>
                </div>
            </div>
        </div>
        <script>
        if (typeof smToggleUserDropdown !== 'function') {
            window.smToggleUserDropdown = function() {
                const menu = document.getElementById('sm-user-dropdown-menu');
                if (menu.style.display === 'none') {
                    menu.style.display = 'block';
                    document.getElementById('sm-profile-view').style.display = 'block';
                    document.getElementById('sm-profile-edit').style.display = 'none';
                } else {
                    menu.style.display = 'none';
                }
            };
            window.smEditProfile = function() {
                document.getElementById('sm-profile-view').style.display = 'none';
                document.getElementById('sm-profile-edit').style.display = 'block';
            };
            window.smSaveProfile = function() {
                const name = document.getElementById('sm_edit_display_name').value;
                const email = document.getElementById('sm_edit_user_email').value;
                const pass = document.getElementById('sm_edit_user_pass').value;
                const formData = new FormData();
                formData.append('action', 'sm_update_profile_ajax');
                formData.append('display_name', name);
                formData.append('user_email', email);
                formData.append('user_pass', pass);
                formData.append('nonce', '<?php echo wp_create_nonce("sm_profile_action"); ?>');
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert('تم تحديث الملف الشخصي بنجاح');
                        location.reload();
                    } else {
                        alert('خطأ: ' + res.data);
                    }
                });
            };
            document.addEventListener('click', function(e) {
                const dropdown = document.querySelector('.sm-user-dropdown');
                const menu = document.getElementById('sm-user-dropdown-menu');
                if (dropdown && !dropdown.contains(e.target)) {
                    if (menu) menu.style.display = 'none';
                }
            });
        }
        </script>
        <?php
        return ob_get_clean();
    }

    public static function ajax_forgot_password_otp() {
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member || !$member->wp_user_id) {
            wp_send_json_error('الرقم القومي غير مسجل في النظام');
        }
        $user = get_userdata($member->wp_user_id);
        if (!$user) {
            wp_send_json_error('بيانات الحساب غير موجودة');
        }
        $otp = sprintf("%06d", mt_rand(1, 999999));
        update_user_meta($user->ID, 'sm_recovery_otp', $otp);
        update_user_meta($user->ID, 'sm_recovery_otp_time', time());
        update_user_meta($user->ID, 'sm_recovery_otp_used', 0);
        $syndicate = SM_Settings::get_syndicate_info();
        $subject = "رمز استعادة كلمة المرور - " . $syndicate['syndicate_name'];
        $message = "عزيزي العضو " . $member->name . ",\n\n" . "رمز التحقق الخاص بك هو: " . $otp . "\n" . "هذا الرمز صالح لمدة 10 دقائق فقط ولمرة واحدة.\n\n" . "إذا لم تطلب هذا الرمز، يرجى تجاهل هذه الرسالة.\n";
        wp_mail($member->email, $subject, $message);
        wp_send_json_success('تم إرسال رمز التحقق إلى بريدك الإلكتروني المسجل');
    }

    public static function ajax_reset_password_otp() {
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $otp = sanitize_text_field($_POST['otp'] ?? '');
        $new_pass = $_POST['new_password'] ?? '';
        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member || !$member->wp_user_id) {
            wp_send_json_error('بيانات غير صحيحة');
        }
        $user_id = $member->wp_user_id;
        $saved_otp = get_user_meta($user_id, 'sm_recovery_otp', true);
        $otp_time = get_user_meta($user_id, 'sm_recovery_otp_time', true);
        $otp_used = get_user_meta($user_id, 'sm_recovery_otp_used', true);
        if ($otp_used || $saved_otp !== $otp || (time() - $otp_time) > 600) {
            update_user_meta($user_id, 'sm_recovery_otp_used', 1);
            wp_send_json_error('رمز التحقق غير صحيح أو منتهي الصلاحية');
        }
        if (strlen($new_pass) < 10 || !preg_match('/^[a-zA-Z0-9]+$/', $new_pass)) {
            wp_send_json_error('كلمة المرور يجب أن تكون 10 أحرف على الأقل وتتكون من حروف وأرقام فقط بدون رموز');
        }
        wp_set_password($new_pass, $user_id);
        update_user_meta($user_id, 'sm_recovery_otp_used', 1);
        wp_send_json_success('تمت إعادة تعيين كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول');
    }

    public static function ajax_activate_account_step1() {
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $membership_number = sanitize_text_field($_POST['membership_number'] ?? '');
        $branch_slug = sanitize_text_field($_POST['branch'] ?? '');

        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member) {
            wp_send_json_error('الرقم القومي غير موجود في السجلات المهنية.');
        }
        if ($member->membership_number !== $membership_number) {
            wp_send_json_error('بيانات التحقق غير صحيحة، يرجى مراجعة رقم القيد.');
        }
        if ($member->governorate !== $branch_slug) {
            wp_send_json_error('العضو غير مسجل في الفرع المختار. يرجى اختيار الفرع الصحيح.');
        }

        wp_send_json_success('تم التحقق بنجاح. يرجى إكمال بيانات التواصل');
    }

    public static function ajax_activate_account_final() {
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $membership_number = sanitize_text_field($_POST['membership_number'] ?? '');
        $new_email = sanitize_email($_POST['email'] ?? '');
        $new_phone = sanitize_text_field($_POST['phone'] ?? '');
        $new_pass = $_POST['password'] ?? '';
        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member || $member->membership_number !== $membership_number) {
            wp_send_json_error('فشل التحقق من الهوية');
        }
        if (strlen($new_pass) < 10 || !preg_match('/^[a-zA-Z0-9]+$/', $new_pass)) {
            wp_send_json_error('كلمة المرور يجب أن تكون 10 أحرف على الأقل وتتكون من حروف وأرقام فقط');
        }
        if (!is_email($new_email)) {
            wp_send_json_error('بريد إلكتروني غير صحيح');
        }
        SM_DB::update_member($member->id, ['email' => $new_email, 'phone' => $new_phone]);
        if ($member->wp_user_id) {
            wp_update_user(['ID' => $member->wp_user_id, 'user_email' => $new_email, 'user_pass' => $new_pass]);
            update_user_meta($member->wp_user_id, 'sm_phone', $new_phone);
            delete_user_meta($member->wp_user_id, 'sm_temp_pass');
        }
        wp_send_json_success('تم تفعيل الحساب بنجاح. يمكنك الآن تسجيل الدخول');
        SM_Notifications::send_template_notification($member->id, 'welcome_activation');
    }

    public static function ajax_submit_membership_request() {
        check_ajax_referer('sm_registration_nonce', 'nonce');
        global $wpdb;
        $nid = sanitize_text_field($_POST['national_id']);
        if (SM_DB::member_exists($nid)) {
            wp_send_json_error('عذراً، هذا الرقم القومي مسجل مسبقاً في النظام كعضو مفعل.');
        }
        $exists_request = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_membership_requests WHERE national_id = %s", $nid));
        if ($exists_request) {
            wp_send_json_error('عذراً، يوجد طلب عضوية قيد المراجعة بهذا الرقم القومي.');
        }
        $insert_data = [
            'national_id' => $nid,
            'name' => sanitize_text_field($_POST['name']),
            'phone' => sanitize_text_field($_POST['phone']),
            'email' => sanitize_email($_POST['email']),
            'residence_governorate' => sanitize_text_field($_POST['residence_governorate']),
            'residence_city' => sanitize_text_field($_POST['residence_city']),
            'residence_street' => sanitize_text_field($_POST['residence_street']),
            'university' => sanitize_text_field($_POST['university']),
            'faculty' => sanitize_text_field($_POST['faculty']),
            'department' => sanitize_text_field($_POST['department']),
            'specialization' => sanitize_text_field($_POST['specialization']),
            'graduation_date' => sanitize_text_field($_POST['graduation_date']),
            'academic_degree' => sanitize_text_field($_POST['academic_degree']),
            'professional_grade' => sanitize_text_field($_POST['professional_grade']),
            'governorate' => sanitize_text_field($_POST['governorate']),
            'sub_syndicate' => sanitize_text_field($_POST['sub_syndicate'] ?? ''),
            'status' => 'Pending Shipment',
            'current_stage' => 5,
            'created_at' => current_time('mysql')
        ];
        $res = $wpdb->insert("{$wpdb->prefix}sm_membership_requests", $insert_data);
        if ($res) {
            $request_id = $wpdb->insert_id;
            $tracking_code = 'REG-' . date('Ymd') . $request_id;
            wp_send_json_success($tracking_code);
        } else {
            wp_send_json_error('فشل في إرسال الطلب: ' . $wpdb->last_error);
        }
    }

    public static function ajax_track_membership_request() {
        global $wpdb;
        $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_membership_requests WHERE national_id = %s", sanitize_text_field($_POST['national_id'])));
        if (!$req) {
            wp_send_json_error('Not found');
        }
        $map = [
            'Pending Payment Verification' => 'قيد مراجعة الدفع',
            'approved' => 'تم القبول',
            'rejected' => 'مرفوض',
            'pending' => 'قيد المراجعة'
        ];
        wp_send_json_success([
            'status' => $map[$req->status] ?? $req->status,
            'current_stage' => $req->current_stage,
            'rejection_reason' => $req->notes ?? ''
        ]);
    }
}

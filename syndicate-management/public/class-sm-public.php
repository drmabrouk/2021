<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Public {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function hide_admin_bar_for_non_admins($show) {
        return current_user_can('administrator') ? $show : false;
    }

    public function restrict_admin_access() {
        if (is_user_logged_in()) {
            if (get_user_meta(get_current_user_id(), 'sm_account_status', true) === 'restricted') {
                wp_logout();
                wp_redirect(home_url('/sm-login?login=failed'));
                exit;
            }
        }
        if (is_admin() && !defined('DOING_AJAX') && !current_user_can('manage_options')) {
            wp_redirect(home_url('/sm-admin'));
            exit;
        }
    }

    public function enqueue_styles() {
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";', 'before');
        wp_enqueue_style('dashicons');
        wp_enqueue_style('google-font-rubik', 'https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;700;800;900&display=swap', array(), null);
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
        wp_enqueue_style($this->plugin_name, SM_PLUGIN_URL . 'assets/css/sm-public.css', array('dashicons'), $this->version, 'all');

        $appearance = SM_Settings::get_appearance();
        $custom_css = "
            :root {
                --sm-primary-color: {$appearance['primary_color']};
                --sm-secondary-color: {$appearance['secondary_color']};
                --sm-accent-color: {$appearance['accent_color']};
                --sm-dark-color: {$appearance['dark_color']};
                --sm-radius: {$appearance['border_radius']};
            }
            .sm-content-wrapper, .sm-admin-dashboard, .sm-container,
            .sm-content-wrapper *:not(.dashicons), .sm-admin-dashboard *:not(.dashicons), .sm-container *:not(.dashicons) {
                font-family: 'Rubik', sans-serif !important;
            }
            .sm-admin-dashboard { font-size: {$appearance['font_size']}; }
        ";
        wp_add_inline_style($this->plugin_name, $custom_css);
    }

    public function register_shortcodes() {
        SM_Auth::register_shortcodes();
        SM_Service_Manager::register_shortcodes();

        add_shortcode('sm_admin', array($this, 'shortcode_admin_dashboard'));
        add_shortcode('verify', array($this, 'shortcode_verify'));
        add_shortcode('sm_branches', array($this, 'shortcode_branches'));
        add_shortcode('contact', array($this, 'shortcode_contact'));

        add_filter('authenticate', array($this, 'custom_authenticate'), 20, 3);
        add_filter('auth_cookie_expiration', array($this, 'custom_auth_cookie_expiration'), 10, 3);
    }

    public function custom_auth_cookie_expiration($exp, $uid, $rem) {
        return $rem ? 30 * DAY_IN_SECONDS : $exp;
    }

    public function custom_authenticate($user, $username, $password) {
        if (empty($username) || empty($password)) {
            return $user;
        }
        if ($user instanceof WP_User) {
            return $user;
        }

        $code_query = new WP_User_Query([
            'meta_query' => [['key' => 'sm_syndicateMemberIdAttr', 'value' => $username]],
            'number' => 1
        ]);
        $found = $code_query->get_results();
        if (!empty($found)) {
            $u = $found[0];
            if (wp_check_password($password, $u->user_pass, $u->ID)) {
                return $u;
            }
        }

        global $wpdb;
        $member_wp_id = $wpdb->get_var($wpdb->prepare("SELECT wp_user_id FROM {$wpdb->prefix}sm_members WHERE national_id = %s", $username));
        if ($member_wp_id) {
            $u = get_userdata($member_wp_id);
            if ($u && wp_check_password($password, $u->user_pass, $u->ID)) {
                return $u;
            }
        }
        return $user;
    }

    public function shortcode_verify() {
        ob_start();
        include SM_PLUGIN_DIR . 'templates/public-verification.php';
        return ob_get_clean();
    }

    public function shortcode_branches() {
        $branches = SM_DB::get_branches_data();
        ob_start();
        ?>
        <div class="sm-public-page" dir="rtl">
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:30px; padding:50px 40px; margin-bottom:50px; text-align:center; box-shadow:0 10px 15px -3px rgba(0,0,0,0.05);">
                <div style="display:inline-flex; align-items:center; justify-content:center; width:60px; height:60px; background:rgba(246, 48, 73, 0.1); border-radius:20px; margin-bottom:20px;">
                    <span class="dashicons dashicons-networking" style="font-size:30px; width:30px; height:30px; color:var(--sm-primary-color);"></span>
                </div>
                <h2 style="margin:0; font-weight:900; font-size:2.5em; color:var(--sm-dark-color);">الفروع واللجان النقابية</h2>
                <p style="color:#64748b; margin-top:10px;">استكشف فروع النقابة وتواصل مع الإدارة المختصة في منطقتك</p>
                <div style="max-width:500px; margin:30px auto 0; position:relative;">
                    <input type="text" id="sm_branch_search" placeholder="ابحث عن فرع محدد..." style="width:100%; padding:15px 45px 15px 20px; border-radius:15px; border:1px solid #e2e8f0; font-family:'Rubik',sans-serif; outline:none;" oninput="smFilterBranchesPublic(this.value)">
                    <span class="dashicons dashicons-search" style="position:absolute; right:15px; top:15px; color:#94a3b8;"></span>
                </div>
            </div>
            <div id="sm-branches-grid-public" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(350px, 1fr)); gap:30px;">
                <?php if(empty($branches)): ?>
                    <div style="grid-column:1/-1; text-align:center; padding:50px; background:#fff; border-radius:20px; border:1px dashed #cbd5e0;">
                        <p style="color:#718096; margin:0;">لا توجد فروع مسجلة حالياً.</p>
                    </div>
                <?php else: foreach($branches as $b): ?>
                    <div class="sm-branch-card-public" data-name="<?php echo esc_attr($b->name); ?>" style="background:#fff; border:1px solid #e2e8f0; border-radius:24px; padding:30px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); transition:0.3s; position:relative; display:flex; flex-direction:column;">
                        <div style="display:flex; align-items:center; gap:20px; margin-bottom:20px;">
                            <div style="width:50px; height:50px; background:var(--sm-primary-color); border-radius:15px; display:flex; align-items:center; justify-content:center; color:#fff; flex-shrink:0;">
                                <span class="dashicons dashicons-location"></span>
                            </div>
                            <div style="flex:1;">
                                <h3 style="margin:0; font-weight:800; color:var(--sm-dark-color); font-size:1.4em;"><?php echo esc_html($b->name); ?></h3>
                                <div style="font-size:12px; color:#94a3b8; margin-top:4px;">كود الفرع: <?php echo esc_html($b->slug); ?></div>
                            </div>
                        </div>

                        <div style="margin-bottom:20px; font-size:14px; color:#64748b; line-height:1.6; min-height:45px;">
                            <?php echo esc_html(mb_strimwidth($b->address, 0, 100, "...")); ?>
                        </div>

                        <button onclick="smToggleBranchDetails(this)" class="sm-btn sm-btn-outline" style="width:100%; border-radius:12px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px;">
                            عرض التفاصيل <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>

                        <div class="sm-branch-details-expanded" style="display:none; margin-top:25px; padding-top:25px; border-top:1px solid #f1f5f9; animation: smFadeIn 0.3s ease;">
                            <div style="display:grid; gap:15px;">
                                <div style="background:#f8fafc; padding:15px; border-radius:12px;">
                                    <div style="font-size:11px; color:#94a3b8; margin-bottom:4px;">مدير الفرع</div>
                                    <div style="font-weight:700; color:var(--sm-dark-color);"><?php echo esc_html($b->manager ?: 'غير محدد'); ?></div>
                                </div>
                                <div style="display:flex; align-items:center; gap:12px; font-size:13px; color:#4a5568;">
                                    <span class="dashicons dashicons-phone" style="color:var(--sm-primary-color); font-size:18px;"></span>
                                    <strong>الهاتف:</strong> <?php echo esc_html($b->phone ?: '---'); ?>
                                </div>
                                <div style="display:flex; align-items:center; gap:12px; font-size:13px; color:#4a5568;">
                                    <span class="dashicons dashicons-email" style="color:var(--sm-primary-color); font-size:18px;"></span>
                                    <strong>البريد:</strong> <?php echo esc_html($b->email ?: '---'); ?>
                                </div>
                                <div style="display:flex; align-items:start; gap:12px; font-size:13px; color:#4a5568;">
                                    <span class="dashicons dashicons-admin-home" style="color:var(--sm-primary-color); font-size:18px;"></span>
                                    <div><strong>العنوان بالكامل:</strong><br><span style="display:inline-block; margin-top:4px;"><?php echo esc_html($b->address); ?></span></div>
                                </div>
                                <?php if($b->description): ?>
                                    <div style="font-size:12px; color:#718096; font-style:italic; margin-top:10px; border-top:1px dashed #e2e8f0; padding-top:10px;">
                                        <?php echo nl2br(esc_html($b->description)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <script>
        function smFilterBranchesPublic(val) {
            const cards = document.querySelectorAll('.sm-branch-card-public');
            const search = val.trim().toLowerCase();
            cards.forEach(c => {
                const name = c.dataset.name.toLowerCase();
                c.style.display = name.includes(search) ? 'flex' : 'none';
            });
        }
        function smToggleBranchDetails(btn) {
            const card = btn.closest('.sm-branch-card-public');
            const details = card.querySelector('.sm-branch-details-expanded');
            const isVisible = details.style.display === 'block';

            // Close all others optional? Let's keep it simple first

            details.style.display = isVisible ? 'none' : 'block';
            btn.innerHTML = isVisible ?
                'عرض التفاصيل <span class="dashicons dashicons-arrow-down-alt2"></span>' :
                'إخفاء التفاصيل <span class="dashicons dashicons-arrow-up-alt2"></span>';

            if (!isVisible) {
                btn.style.background = 'var(--sm-dark-color)';
                btn.style.color = '#fff';
            } else {
                btn.style.background = 'transparent';
                btn.style.color = 'inherit';
            }
        }
        </script>
        <?php
        return ob_get_clean();
    }

    public function shortcode_admin_dashboard() {
        if (!is_user_logged_in()) {
            return SM_Auth::shortcode_login();
        }
        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        $active_tab = isset($_GET['sm_tab']) ? sanitize_text_field($_GET['sm_tab']) : 'summary';
        $stats = SM_DB::get_statistics();

        ob_start();
        include SM_PLUGIN_DIR . 'templates/public-admin-panel.php';
        return ob_get_clean();
    }

    public function shortcode_contact() {
        ob_start();
        ?>
        <div class="sm-contact-wrapper" dir="rtl" style="max-width: 800px; margin: 60px auto; background: #fff; padding: 40px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);">
            <div style="text-align: center; margin-bottom: 35px;">
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 60px; background: rgba(246, 48, 73, 0.1); border-radius: 20px; margin-bottom: 20px;">
                    <span class="dashicons dashicons-email-alt" style="font-size: 30px; width: 30px; height: 30px; color: var(--sm-primary-color);"></span>
                </div>
                <h2 style="margin: 0; font-weight: 900; font-size: 2em;">تواصل مع الإدارة</h2>
            </div>
            <form id="sm-public-contact-form">
                <?php wp_nonce_field('sm_contact_action', 'nonce'); ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="sm-form-group"><input type="text" name="name" class="sm-input" required placeholder="الاسم الكامل"></div>
                    <div class="sm-form-group"><input type="text" name="phone" class="sm-input" required placeholder="رقم الهاتف"></div>
                </div>
                <div class="sm-form-group" style="margin-bottom: 20px;">
                    <input type="email" name="email" class="sm-input" required placeholder="البريد الإلكتروني">
                </div>
                <div class="sm-form-group" style="margin-bottom: 20px;">
                    <input type="text" name="subject" class="sm-input" required placeholder="عنوان الرسالة">
                </div>
                <div class="sm-form-group" style="margin-bottom: 30px;">
                    <textarea name="message" class="sm-textarea" rows="6" required placeholder="كيف يمكننا مساعدتك؟"></textarea>
                </div>
                <button type="submit" class="sm-btn" style="width: 100%; height: 55px; font-weight: 800;">إرسال الرسالة الآن</button>
            </form>
            <div id="sm-contact-success" style="display: none; text-align: center; padding: 40px 0;">
                <div style="font-size: 60px; margin-bottom: 20px;">✅</div>
                <h3 style="font-weight: 900;">تم إرسال رسالتك بنجاح!</h3>
                <button onclick="location.reload()" class="sm-btn" style="margin-top: 30px; width: auto; padding: 0 50px;">إرسال رسالة أخرى</button>
            </div>
        </div>
        <script>
        document.getElementById('sm-public-contact-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const btn = form.querySelector('button[type="submit"]');
            const fd = new FormData(form);
            fd.append('action', 'sm_submit_contact_form');
            btn.disabled = true;
            btn.innerText = 'جاري الإرسال...';
            fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    form.style.display = 'none';
                    document.getElementById('sm-contact-success').style.display = 'block';
                } else {
                    alert('خطأ: ' + res.data);
                    btn.disabled = false;
                    btn.innerText = 'إرسال الرسالة الآن';
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function login_failed($username) {
        $ref = wp_get_referer();
        if ($ref && !strstr($ref, 'wp-login') && !strstr($ref, 'wp-admin')) {
            wp_redirect(add_query_arg('login', 'failed', $ref));
            exit;
        }
    }

    public function log_successful_login($user_login, $user) {
        SM_Logger::log('تسجيل دخول', "المستخدم: $user_login");
    }

    public function inject_global_alerts() {
        if (!is_user_logged_in()) {
            return;
        }
        $alerts = SM_DB::get_active_alerts_for_user(get_current_user_id());
        if (empty($alerts)) {
            return;
        }
        foreach ($alerts as $a) {
            $bg = $a->severity === 'critical' ? '#fff5f5' : ($a->severity === 'warning' ? '#fffaf0' : '#fff');
            $border = $a->severity === 'critical' ? '#feb2b2' : ($a->severity === 'warning' ? '#f6ad55' : '#e2e8f0');
            ?>
            <div id="sm-global-alert-<?php echo $a->id; ?>" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; display:flex; align-items:center; justify-content:center;">
                <div style="background:<?php echo $bg; ?>; border:2px solid <?php echo $border; ?>; border-radius:15px; width:90%; max-width:500px; padding:30px; text-align:center; direction:rtl;">
                    <h2 style="margin:0 0 15px 0; font-weight:800;"><?php echo esc_html($a->title); ?></h2>
                    <div style="margin-bottom:25px;"><?php echo wp_kses_post($a->message); ?></div>
                    <button onclick="smAcknowledgeAlert(<?php echo $a->id; ?>)" class="sm-btn" style="width:100%;"><?php echo $a->must_acknowledge ? 'إقرار واستمرار' : 'إغلاق'; ?></button>
                </div>
            </div>
            <?php
        }
        ?>
        <script>
        function smAcknowledgeAlert(aid) {
            const fd = new FormData();
            fd.append('action', 'sm_acknowledge_alert');
            fd.append('alert_id', aid);
            fetch(ajaxurl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                document.getElementById('sm-global-alert-' + aid).remove();
            });
        }
        </script>
        <?php
    }

    public function handle_form_submission() {
        if (isset($_POST['sm_save_appearance'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
            $data = SM_Settings::get_appearance();
            foreach ($data as $k => $v) {
                if (isset($_POST[$k])) {
                    $data[$k] = sanitize_text_field($_POST[$k]);
                }
            }
            SM_Settings::save_appearance($data);
            wp_redirect(add_query_arg('sm_tab', 'global-settings', wp_get_referer()));
            exit;
        }

        if (isset($_POST['sm_save_settings_unified'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
            $info = SM_Settings::get_syndicate_info();
            $fields = [
                'syndicate_name' => 'syndicate_name',
                'syndicate_officer_name' => 'syndicate_officer_name',
                'syndicate_phone' => 'phone',
                'syndicate_email' => 'email',
                'syndicate_postal_code' => 'postal_code',
                'syndicate_logo' => 'syndicate_logo',
                'syndicate_address' => 'address',
                'syndicate_map_link' => 'map_link',
                'syndicate_extra_details' => 'extra_details'
            ];

            foreach($fields as $post_key => $info_key) {
                if(isset($_POST[$post_key])) {
                    $info[$info_key] = sanitize_text_field($_POST[$post_key]);
                }
            }
            SM_Settings::save_syndicate_info($info);

            // Save Appearance (Colors & Design)
            SM_Settings::save_appearance([
                'primary_color' => sanitize_hex_color($_POST['primary_color']),
                'secondary_color' => sanitize_hex_color($_POST['secondary_color']),
                'accent_color' => sanitize_hex_color($_POST['accent_color']),
                'dark_color' => sanitize_hex_color($_POST['dark_color']),
                'bg_color' => sanitize_hex_color($_POST['bg_color']),
                'sidebar_bg_color' => sanitize_hex_color($_POST['sidebar_bg_color']),
                'font_color' => sanitize_hex_color($_POST['font_color']),
                'border_color' => sanitize_hex_color($_POST['border_color']),
                'font_size' => sanitize_text_field($_POST['font_size']),
                'font_weight' => sanitize_text_field($_POST['font_weight']),
                'line_spacing' => sanitize_text_field($_POST['line_spacing'])
            ]);

            $labels = SM_Settings::get_labels();
            foreach($labels as $key => $val) {
                if (isset($_POST[$key])) {
                    $labels[$key] = sanitize_text_field($_POST[$key]);
                }
            }
            SM_Settings::save_labels($labels);

            wp_redirect(add_query_arg(['sm_tab' => 'global-settings', 'sub' => 'init', 'settings_saved' => 1], wp_get_referer()));
            exit;
        }
    }

    public function ajax_refresh_dashboard() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        wp_send_json_success(array('stats' => SM_DB::get_statistics()));
    }

    public function ajax_update_member_photo() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_photo_action', 'sm_photo_nonce');
        $mid = intval($_POST['member_id']);
        if (!SM_Member_Manager::can_access_member($mid)) {
            wp_send_json_error('Access denied');
        }
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $att_id = media_handle_upload('member_photo', 0);
        if (is_wp_error($att_id)) {
            wp_send_json_error($att_id->get_error_message());
        }
        $url = wp_get_attachment_url($att_id);
        SM_DB::update_member_photo($mid, $url);
        wp_send_json_success(array('photo_url' => $url));
    }

    public function ajax_get_conversations() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_message_action', 'nonce');
        $user = wp_get_current_user();
        $gov = get_user_meta($user->ID, 'sm_governorate', true);
        $has_full = current_user_can('sm_full_access') || current_user_can('manage_options');

        if (!$gov && !$has_full) {
            wp_send_json_error('No governorate assigned');
        }

        if (in_array('sm_syndicate_member', (array)$user->roles)) {
            $offs = SM_DB::get_governorate_officials($gov);
            $data = [];
            foreach($offs as $o) {
                $data[] = [
                    'official' => [
                        'ID' => $o->ID,
                        'display_name' => $o->display_name,
                        'avatar' => get_avatar_url($o->ID)
                    ]
                ];
            }
            wp_send_json_success(['type' => 'member_view', 'officials' => $data]);
        } else {
            $t_gov = $has_full ? null : $gov;
            $convs = SM_DB::get_governorate_conversations($t_gov);
            foreach($convs as &$c) {
                $c['member']->avatar = $c['member']->photo_url ?: get_avatar_url($c['member']->wp_user_id ?: 0);
            }
            wp_send_json_success(['type' => 'official_view', 'conversations' => $convs]);
        }
    }

    public function ajax_mark_read() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_message_action', 'nonce');
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}sm_messages", ['is_read' => 1], ['receiver_id' => get_current_user_id(), 'sender_id' => intval($_POST['other_user_id'])]);
        wp_send_json_success();
    }

    public function ajax_get_tickets() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_ticket_action', 'nonce');
        wp_send_json_success(SM_DB::get_tickets($_GET));
    }

    public function ajax_create_ticket() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_ticket_action', 'nonce');
        global $wpdb;
        $member = $wpdb->get_row($wpdb->prepare("SELECT id, governorate FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", get_current_user_id()));
        if (!$member) {
            wp_send_json_error('Member profile not found');
        }
        $url = null;
        if (!empty($_FILES['attachment']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            $att_id = media_handle_upload('attachment', 0);
            if (!is_wp_error($att_id)) {
                $url = wp_get_attachment_url($att_id);
            }
        }
        $tid = SM_DB::create_ticket([
            'member_id' => $member->id,
            'subject' => sanitize_text_field($_POST['subject']),
            'category' => sanitize_text_field($_POST['category']),
            'priority' => sanitize_text_field($_POST['priority'] ?? 'medium'),
            'message' => sanitize_textarea_field($_POST['message']),
            'province' => $member->governorate,
            'file_url' => $url
        ]);
        if ($tid) {
            wp_send_json_success($tid);
        } else {
            wp_send_json_error('Failed to create ticket');
        }
    }

    public function ajax_get_ticket_details() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_ticket_action', 'nonce');
        $id = intval($_GET['id']);
        $ticket = SM_DB::get_ticket($id);
        if (!$ticket) {
            wp_send_json_error('Ticket not found');
        }
        $user = wp_get_current_user();
        if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) {
            if (in_array('sm_syndicate_admin', $user->roles)) {
                $gov = get_user_meta($user->ID, 'sm_governorate', true);
                if ($gov && $ticket->province !== $gov) {
                    wp_send_json_error('Access denied');
                }
            } else {
                global $wpdb;
                $mid = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", $user->ID));
                if ($ticket->member_id != $mid) {
                    wp_send_json_error('Access denied');
                }
            }
        }
        wp_send_json_success(array('ticket' => $ticket, 'thread' => SM_DB::get_ticket_thread($id)));
    }

    public function ajax_add_ticket_reply() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_ticket_action', 'nonce');
        $tid = intval($_POST['ticket_id']);
        $url = null;
        if (!empty($_FILES['attachment']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            $att_id = media_handle_upload('attachment', 0);
            if (!is_wp_error($att_id)) {
                $url = wp_get_attachment_url($att_id);
            }
        }
        $rid = SM_DB::add_ticket_reply([
            'ticket_id' => $tid,
            'sender_id' => get_current_user_id(),
            'message' => sanitize_textarea_field($_POST['message']),
            'file_url' => $url
        ]);
        if ($rid) {
            if (!in_array('sm_syndicate_member', wp_get_current_user()->roles)) {
                SM_DB::update_ticket_status($tid, 'in-progress');
            }
            wp_send_json_success($rid);
        } else {
            wp_send_json_error('Failed to add reply');
        }
    }

    public function ajax_close_ticket() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_ticket_action', 'nonce');
        if (SM_DB::update_ticket_status(intval($_POST['id']), 'closed')) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to close ticket');
        }
    }

    public function handle_print() {
        if (!current_user_can('sm_print_reports')) {
            wp_die('Unauthorized');
        }
        $type = sanitize_text_field($_GET['print_type'] ?? '');
        $mid = intval($_GET['member_id'] ?? 0);
        if ($mid && !SM_Member_Manager::can_access_member($mid)) {
            wp_die('Access denied');
        }
        switch($type) {
            case 'id_card':
                include SM_PLUGIN_DIR . 'templates/print-id-cards.php';
                break;
            case 'credentials':
                include SM_PLUGIN_DIR . 'templates/print-member-credentials.php';
                break;
            default:
                wp_die('Invalid print type');
        }
        exit;
    }

    public function ajax_get_counts() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        wp_send_json_success(['pending_reports' => SM_DB::get_pending_reports_count()]);
    }

    public function ajax_add_staff() {
        if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicateMemberAction')) {
            wp_send_json_error('Security check failed');
        }
        $user = sanitize_user($_POST['user_login']);
        $email = sanitize_email($_POST['user_email']);
        $name = sanitize_text_field($_POST['display_name']);
        $role = sanitize_text_field($_POST['role']);

        if (username_exists($user) || email_exists($email)) {
            wp_send_json_error('User or Email already exists');
        }
        $pass = !empty($_POST['user_pass']) ? $_POST['user_pass'] : 'IRS' . mt_rand(1000000000, 9999999999);
        $uid = wp_insert_user([
            'user_login' => $user,
            'user_email' => $email,
            'display_name' => $name,
            'user_pass' => $pass,
            'role' => $role
        ]);
        if (is_wp_error($uid)) {
            wp_send_json_error($uid->get_error_message());
        }
        update_user_meta($uid, 'sm_temp_pass', $pass);
        update_user_meta($uid, 'sm_syndicateMemberIdAttr', sanitize_text_field($_POST['officer_id']));
        update_user_meta($uid, 'sm_phone', sanitize_text_field($_POST['phone']));
        update_user_meta($uid, 'sm_rank', sanitize_text_field($_POST['rank']));
        update_user_meta($uid, 'sm_account_status', 'active');

        $gov = sanitize_text_field($_POST['governorate'] ?? '');
        if (in_array('sm_syndicate_admin', (array)wp_get_current_user()->roles)) {
            $gov = get_user_meta(get_current_user_id(), 'sm_governorate', true);
        }
        update_user_meta($uid, 'sm_governorate', $gov);
        SM_Logger::log('إضافة مستخدم', "الاسم: $name الدور: $role");
        wp_send_json_success($uid);
    }

    public function ajax_update_staff() {
        if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicateMemberAction')) {
            wp_send_json_error('Security check failed');
        }
        $uid = intval($_POST['edit_officer_id']);
        $role = sanitize_text_field($_POST['role']);
        $data = [
            'ID' => $uid,
            'display_name' => sanitize_text_field($_POST['display_name']),
            'user_email' => sanitize_email($_POST['user_email'])
        ];
        if (!empty($_POST['user_pass'])) {
            $data['user_pass'] = $_POST['user_pass'];
            update_user_meta($uid, 'sm_temp_pass', $_POST['user_pass']);
        }
        wp_update_user($data);
        $u = new WP_User($uid);
        $u->set_role($role);
        update_user_meta($uid, 'sm_syndicateMemberIdAttr', sanitize_text_field($_POST['officer_id']));
        update_user_meta($uid, 'sm_phone', sanitize_text_field($_POST['phone']));
        update_user_meta($uid, 'sm_rank', sanitize_text_field($_POST['rank']));
        update_user_meta($uid, 'sm_account_status', sanitize_text_field($_POST['account_status']));
        SM_Logger::log('تحديث مستخدم', "الاسم: {$_POST['display_name']}");
        wp_send_json_success('Updated');
    }

    public function ajax_delete_staff() {
        if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        if (!wp_verify_nonce($_POST['nonce'], 'sm_syndicateMemberAction')) {
            wp_send_json_error('Security check failed');
        }
        $uid = intval($_POST['user_id']);
        if ($uid === get_current_user_id()) {
            wp_send_json_error('Cannot delete yourself');
        }
        wp_delete_user($uid);
        wp_send_json_success('Deleted');
    }

    public function ajax_bulk_delete_users() {
        if (!current_user_can('sm_manage_users')) {
            wp_send_json_error('Unauthorized');
        }
        if (!wp_verify_nonce($_POST['nonce'], 'sm_syndicateMemberAction')) {
            wp_send_json_error('Security check failed');
        }
        $ids = explode(',', $_POST['user_ids']);
        foreach ($ids as $id) {
            $id = intval($id);
            if ($id === get_current_user_id()) {
                continue;
            }
            wp_delete_user($id);
        }
        wp_send_json_success();
    }

    public function ajax_cancel_survey() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}sm_surveys", ['status' => 'cancelled'], ['id' => intval($_POST['id'])]);
        wp_send_json_success();
    }

    public function ajax_get_survey_results() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        wp_send_json_success(SM_DB::get_survey_results(intval($_GET['id'])));
    }

    public function ajax_export_survey_results() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $id = intval($_GET['id']);
        $results = SM_DB::get_survey_results($id);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="survey-'.$id.'.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Question', 'Answer', 'Count']);
        foreach ($results as $r) {
            foreach ($r['answers'] as $ans => $count) {
                fputcsv($out, [$r['question'], $ans, $count]);
            }
        }
        fclose($out);
        exit;
    }

    public function ajax_delete_gov_data() {
        if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        global $wpdb;
        $gov = sanitize_text_field($_POST['governorate']);
        if (!$gov) {
            wp_send_json_error('فرع غير محددة');
        }
        $m_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE governorate = %s", $gov));
        if (empty($m_ids)) {
            wp_send_json_success('لا توجد بيانات');
        }
        $uids = $wpdb->get_col($wpdb->prepare("SELECT wp_user_id FROM {$wpdb->prefix}sm_members WHERE governorate = %s AND wp_user_id IS NOT NULL", $gov));
        if (!empty($uids)) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            foreach ($uids as $uid) wp_delete_user($uid);
        }
        $ids_str = implode(',', array_map('intval', $m_ids));
        $wpdb->query("DELETE FROM {$wpdb->prefix}sm_payments WHERE member_id IN ($ids_str)");
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sm_members WHERE governorate = %s", $gov));
        SM_Logger::log('حذف بيانات فرع', "تم مسح كافة بيانات فرع: $gov");
        wp_send_json_success();
    }

    public function ajax_merge_gov_data() {
        if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        $gov = sanitize_text_field($_POST['governorate']);
        if (empty($_FILES['backup_file']['tmp_name'])) {
            wp_send_json_error('الملف غير موجود');
        }
        $data = json_decode(file_get_contents($_FILES['backup_file']['tmp_name']), true);
        if (!$data || !isset($data['members'])) {
            wp_send_json_error('تنسيق غير صحيح');
        }
        $success = 0;
        foreach ($data['members'] as $row) {
            if ($row['governorate'] !== $gov || SM_DB::member_exists($row['national_id'])) {
                continue;
            }
            unset($row['id']);
            $tp = 'IRS' . mt_rand(1000000000, 9999999999);
            $uid = wp_insert_user([
                'user_login' => $row['national_id'],
                'user_email' => $row['email'] ?: $row['national_id'] . '@irseg.org',
                'display_name' => $row['name'],
                'user_pass' => $tp,
                'role' => 'sm_syndicate_member'
            ]);
            if (!is_wp_error($uid)) {
                $row['wp_user_id'] = $uid;
                update_user_meta($uid, 'sm_temp_pass', $tp);
                update_user_meta($uid, 'sm_governorate', $gov);
            }
            global $wpdb;
            if ($wpdb->insert("{$wpdb->prefix}sm_members", $row)) {
                $success++;
            }
        }
        wp_send_json_success("تم دمج $success عضواً.");
    }

    public function ajax_delete_log() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}sm_logs", ['id' => intval($_POST['log_id'])]);
        wp_send_json_success();
    }

    public function ajax_clear_all_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_logs");
        wp_send_json_success();
    }

    public function ajax_get_user_role() {
        if (!current_user_can('sm_manage_users')) {
            wp_send_json_error('Unauthorized');
        }
        $u = get_userdata(intval($_GET['user_id']));
        if ($u) {
            wp_send_json_success(['role' => !empty($u->roles) ? $u->roles[0] : '']);
        } else {
            wp_send_json_error('User not found');
        }
    }

    public function ajax_update_service() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        if (SM_DB::update_service(intval($_POST['id']), $_POST)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed');
        }
    }

    public function ajax_get_services_html() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        ob_start();
        include SM_PLUGIN_DIR . 'templates/admin-services.php';
        wp_send_json_success(['html' => ob_get_clean()]);
    }

    public function ajax_delete_service() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        if (SM_DB::delete_service(intval($_POST['id']), !empty($_POST['permanent']))) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed');
        }
    }

    public function ajax_restore_service() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        if (SM_DB::restore_service(intval($_POST['id']))) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed');
        }
    }

    public function ajax_print_license() {
        if (!current_user_can('sm_print_reports')) {
            wp_die('Unauthorized');
        }
        $mid = intval($_GET['member_id'] ?? 0);
        if (!$mid || !SM_Member_Manager::can_access_member($mid)) {
            wp_die('Access denied');
        }
        include SM_PLUGIN_DIR . 'templates/print-practice-license.php';
        exit;
    }

    public function ajax_print_facility() {
        if (!current_user_can('sm_print_reports')) {
            wp_die('Unauthorized');
        }
        $mid = intval($_GET['member_id'] ?? 0);
        if (!$mid || !SM_Member_Manager::can_access_member($mid)) {
            wp_die('Access denied');
        }
        include SM_PLUGIN_DIR . 'templates/print-facility-license.php';
        exit;
    }

    public function ajax_print_invoice() {
        $pid = intval($_GET['payment_id'] ?? 0);
        global $wpdb;
        $pmt = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$wpdb->prefix}sm_payments WHERE id = %d", $pid));
        if (!$pmt || !SM_Member_Manager::can_access_member($pmt->member_id)) {
            wp_die('Unauthorized');
        }
        include SM_PLUGIN_DIR . 'templates/print-invoice.php';
        exit;
    }

    public function ajax_print_service_request() {
        global $wpdb;
        $req = $wpdb->get_row($wpdb->prepare("SELECT member_id, status FROM {$wpdb->prefix}sm_service_requests WHERE id = %d", intval($_GET['id'])));
        if (!$req || !SM_Member_Manager::can_access_member($req->member_id)) {
            wp_die('Unauthorized');
        }
        include SM_PLUGIN_DIR . 'templates/print-service-request.php';
        exit;
    }

    public function ajax_submit_update_request_ajax() {
        if (!is_user_logged_in()) {
            wp_send_json_error('يجب تسجيل الدخول');
        }
        check_ajax_referer('sm_update_request', 'nonce');
        $mid = intval($_POST['member_id']);
        if (!SM_Member_Manager::can_access_member($mid)) {
            wp_send_json_error('Access denied');
        }
        if (SM_DB::add_update_request($mid, $_POST)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed');
        }
    }

    public function ajax_process_update_request_ajax() {
        if (!current_user_can('sm_manage_members')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_update_request', 'nonce');
        if (SM_DB::process_update_request(intval($_POST['request_id']), sanitize_text_field($_POST['status']))) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed');
        }
    }

    public function ajax_submit_professional_request() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_professional_action', 'nonce');
        $mid = intval($_POST['member_id']);
        if (!SM_Member_Manager::can_access_member($mid)) {
            wp_send_json_error('Access denied');
        }
        if (SM_DB::add_professional_request($mid, sanitize_text_field($_POST['request_type']))) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed');
        }
    }

    public function ajax_process_professional_request() {
        if (!current_user_can('sm_manage_members')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        if (SM_DB::process_professional_request(intval($_POST['request_id']), sanitize_text_field($_POST['status']), sanitize_textarea_field($_POST['notes'] ?? ''))) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed');
        }
    }

    public function ajax_track_membership_request() {
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

    public function ajax_submit_membership_request_stage3() {
        $nid = sanitize_text_field($_POST['national_id']);
        global $wpdb;
        if (!empty($_FILES)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $upd = ['status' => 'Awaiting Physical Documents', 'current_stage' => 3];
            $map = [
                'doc_qualification' => 'doc_qualification_url',
                'doc_id' => 'doc_id_url',
                'doc_military' => 'doc_military_url',
                'doc_criminal' => 'doc_criminal_url',
                'doc_photo' => 'doc_photo_url'
            ];
            foreach ($map as $f => $c) {
                if (!empty($_FILES[$f])) {
                    $u = wp_handle_upload($_FILES[$f], ['test_form' => false]);
                    if (isset($u['url'])) {
                        $upd[$c] = $u['url'];
                    }
                }
            }
            $wpdb->update("{$wpdb->prefix}sm_membership_requests", $upd, ['national_id' => $nid]);
            wp_send_json_success();
        }
        wp_send_json_error('No files.');
    }

    public function ajax_get_template_ajax() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
        $t = SM_Notifications::get_template(sanitize_text_field($_POST['type']));
        if ($t) {
            wp_send_json_success($t);
        } else {
            wp_send_json_error('Not found');
        }
    }

    public function ajax_upload_document() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_document_action', 'nonce');
        $mid = intval($_POST['member_id']);
        if (!SM_Member_Manager::can_access_member($mid)) {
            wp_send_json_error('Access denied');
        }
        if (empty($_FILES['document_file']['name'])) {
            wp_send_json_error('No file');
        }
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $aid = media_handle_upload('document_file', 0);
        if (is_wp_error($aid)) {
            wp_send_json_error($aid->get_error_message());
        }
        $did = SM_DB::add_document([
            'member_id' => $mid,
            'category' => sanitize_text_field($_POST['category']),
            'title' => sanitize_text_field($_POST['title']),
            'file_url' => wp_get_attachment_url($aid),
            'file_type' => get_post_mime_type($aid)
        ]);
        if ($did) {
            wp_send_json_success(['doc_id' => $did]);
        } else {
            wp_send_json_error('Failed');
        }
    }

    public function ajax_get_documents() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        $mid = intval($_GET['member_id']);
        if (!SM_Member_Manager::can_access_member($mid)) {
            wp_send_json_error('Access denied');
        }
        wp_send_json_success(SM_DB::get_member_documents($mid, $_GET));
    }

    public function ajax_delete_document() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_document_action', 'nonce');
        global $wpdb;
        $doc = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$wpdb->prefix}sm_documents WHERE id = %d", intval($_POST['doc_id'])));
        if (!$doc || !SM_Member_Manager::can_access_member($doc->member_id)) {
            wp_send_json_error('Access denied');
        }
        if (SM_DB::delete_document(intval($_POST['doc_id']))) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed');
        }
    }

    public function ajax_get_document_logs() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        global $wpdb;
        $doc = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$wpdb->prefix}sm_documents WHERE id = %d", intval($_GET['doc_id'])));
        if (!$doc || !SM_Member_Manager::can_access_member($doc->member_id)) {
            wp_send_json_error('Access denied');
        }
        wp_send_json_success(SM_DB::get_document_logs(intval($_GET['doc_id'])));
    }

    public function ajax_log_document_view() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        global $wpdb;
        $doc = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$wpdb->prefix}sm_documents WHERE id = %d", intval($_POST['doc_id'])));
        if (!$doc || !SM_Member_Manager::can_access_member($doc->member_id)) {
            wp_send_json_error('Access denied');
        }
        SM_DB::log_document_action(intval($_POST['doc_id']), 'view');
        wp_send_json_success();
    }

    public function ajax_get_pub_template() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
        global $wpdb;
        $t = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_pub_templates WHERE id = %d", intval($_GET['id'])));
        if ($t) {
            wp_send_json_success($t);
        } else {
            wp_send_json_error('Not found');
        }
    }

    public function ajax_generate_pub_doc() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_pub_action', 'nonce');
        $did = SM_DB::generate_pub_document([
            'title' => sanitize_text_field($_POST['title']),
            'content' => wp_kses_post($_POST['content']),
            'member_id' => intval($_POST['member_id'] ?? 0),
            'options' => [
                'doc_type' => sanitize_text_field($_POST['doc_type'] ?? 'report'),
                'fees' => floatval($_POST['fees'] ?? 0),
                'header' => isset($_POST['header']),
                'footer' => isset($_POST['footer']),
                'qr' => isset($_POST['qr']),
                'barcode' => isset($_POST['barcode'])
            ]
        ]);
        if ($did) {
            wp_send_json_success(['url' => admin_url('admin-ajax.php?action=sm_print_pub_doc&id=' . $did . '&format=' . sanitize_text_field($_POST['format'] ?? 'pdf'))]);
        } else {
            wp_send_json_error('Failed');
        }
    }

    public function ajax_print_pub_doc() {
        global $wpdb;
        $doc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_pub_documents WHERE id = %d", intval($_GET['id'])));
        if (!$doc) {
            wp_die('Not found');
        }
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sm_pub_documents SET download_count = download_count + 1 WHERE id = %d", $doc->id));
        include SM_PLUGIN_DIR . 'templates/print-pub-document.php';
        exit;
    }

    public function ajax_save_pub_identity() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_pub_action', 'nonce');
        $info = SM_Settings::get_syndicate_info();
        $info['syndicate_name'] = sanitize_text_field($_POST['syndicate_name']);
        $info['authority_name'] = sanitize_text_field($_POST['authority_name']);
        $info['phone'] = sanitize_text_field($_POST['phone']);
        $info['email'] = sanitize_email($_POST['email']);
        $info['address'] = sanitize_text_field($_POST['address']);
        $info['syndicate_logo'] = esc_url_raw($_POST['syndicate_logo']);
        $info['authority_logo'] = esc_url_raw($_POST['authority_logo']);
        SM_Settings::save_syndicate_info($info);
        wp_send_json_success();
    }

    public function ajax_save_pub_template() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_pub_action', 'nonce');
        if (SM_DB::save_pub_template($_POST)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed');
        }
    }

    public function ajax_delete_alert() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        if (SM_DB::delete_alert(intval($_POST['id']))) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed');
        }
    }

    public function ajax_acknowledge_alert() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        if (SM_DB::acknowledge_alert(intval($_POST['alert_id']), get_current_user_id())) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed');
        }
    }

    public function ajax_delete_branch() {
        if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        if (SM_DB::delete_branch(intval($_POST['id']))) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed');
        }
    }

    public function ajax_export_branches() {
        if (!current_user_can('sm_full_access')) {
            wp_die('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        $bs = SM_DB::get_branches_data();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=branches.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Slug', 'Name', 'Phone', 'Email', 'Address']);
        foreach ($bs as $b) fputcsv($out, [$b->id, $b->slug, $b->name, $b->phone, $b->email, $b->address]);
        fclose($out);
        exit;
    }

    public static function ajax_get_test_questions() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        $test_id = intval($_GET['test_id']);
        // Capability check: admins or the user assigned to the test
        $can_view = current_user_can('sm_manage_system');
        if (!$can_view) {
            global $wpdb;
            $is_assigned = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_test_assignments WHERE test_id = %d AND user_id = %d", $test_id, get_current_user_id()));
            if ($is_assigned) $can_view = true;
        }

        if (!$can_view) {
            wp_send_json_error('Access denied');
        }

        wp_send_json_success(SM_DB_Education::get_test_questions($test_id));
    }

    public function ajax_verify_suggest() {
        global $wpdb;
        $q = sanitize_text_field($_GET['query'] ?? '');
        if (strlen($q) < 3) {
            wp_send_json_success([]);
        }
        $s = '%' . $wpdb->esc_like($q) . '%';
        $res = $wpdb->get_results($wpdb->prepare("SELECT name, national_id FROM {$wpdb->prefix}sm_members WHERE name LIKE %s OR national_id LIKE %s LIMIT 5", $s, $s));
        $sug = [];
        foreach ($res as $r) {
            $sug[] = $r->name;
            $sug[] = $r->national_id;
        }
        wp_send_json_success(array_values(array_unique(array_filter($sug))));
    }
}

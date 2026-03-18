<?php

class SM_Public {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function hide_admin_bar_for_non_admins($show) {
        if (!current_user_can('administrator')) {
            return false;
        }
        return $show;
    }

    private function can_manage_user($target_user_id) {
        if (current_user_can('sm_full_access') || current_user_can('manage_options')) return true;

        $current_user = wp_get_current_user();
        $target_user = get_userdata($target_user_id);
        if (!$target_user) return false;

        // Syndicate Admins can only manage Syndicate Members
        if (in_array('sm_syndicate_admin', (array)$current_user->roles)) {
            // Cannot manage System Admins
            if (in_array('sm_system_admin', (array)$target_user->roles)) return false;
            // Cannot manage other Syndicate Admins
            if (in_array('sm_syndicate_admin', (array)$target_user->roles)) return false;

            // Must be in the same governorate
            $my_gov = get_user_meta($current_user->ID, 'sm_governorate', true);
            $target_gov = get_user_meta($target_user_id, 'sm_governorate', true);
            if ($my_gov && $target_gov && $my_gov !== $target_gov) return false;

            return true;
        }

        return false;
    }

    private function can_access_member($member_id) {
        if (current_user_can('sm_full_access') || current_user_can('manage_options')) return true;

        $member = SM_DB::get_member_by_id($member_id);
        if (!$member) return false;

        $user = wp_get_current_user();

        // Members can access their own record
        if (in_array('sm_syndicate_member', (array)$user->roles) && $member->wp_user_id == $user->ID) {
            return true;
        }

        // Syndicate Admins check governorate
        if (in_array('sm_syndicate_admin', (array)$user->roles)) {
            $my_gov = get_user_meta($user->ID, 'sm_governorate', true);
            if ($my_gov && $member->governorate !== $my_gov) {
                return false;
            }
            return true;
        }

        // Syndicate Members check governorate
        if (in_array('sm_syndicate_member', (array)$user->roles)) {
             $my_gov = get_user_meta($user->ID, 'sm_governorate', true);
             if ($my_gov && $member->governorate !== $my_gov) {
                 return false;
             }
             return true;
        }

        return false;
    }

    public function restrict_admin_access() {
        if (is_user_logged_in()) {
            $status = get_user_meta(get_current_user_id(), 'sm_account_status', true);
            if ($status === 'restricted') {
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
        add_shortcode('sm_login', array($this, 'shortcode_login'));
        add_shortcode('sm_admin', array($this, 'shortcode_admin_dashboard'));
        add_shortcode('verify', array($this, 'shortcode_verify'));
        add_shortcode('login-page', array($this, 'shortcode_login_page'));

        // Page Customization Shortcodes
        add_shortcode('services', array($this, 'shortcode_services'));
        add_shortcode('sm_branches', array($this, 'shortcode_branches'));

        add_filter('authenticate', array($this, 'custom_authenticate'), 20, 3);
        add_filter('auth_cookie_expiration', array($this, 'custom_auth_cookie_expiration'), 10, 3);
    }

    public function custom_auth_cookie_expiration($expiration, $user_id, $remember) {
        if ($remember) {
            return 30 * DAY_IN_SECONDS; // 30 days
        }
        return $expiration;
    }

    public function custom_authenticate($user, $username, $password) {
        if (empty($username) || empty($password)) return $user;

        // If already authenticated by standard means, return
        if ($user instanceof WP_User) return $user;

        // 1. Check for Syndicate Admin/Member ID Code (meta)
        $code_query = new WP_User_Query(array(
            'meta_query' => array(
                array('key' => 'sm_syndicateMemberIdAttr', 'value' => $username)
            ),
            'number' => 1
        ));
        $found = $code_query->get_results();
        if (!empty($found)) {
            $u = $found[0];
            if (wp_check_password($password, $u->user_pass, $u->ID)) return $u;
        }

        // 2. Check for National ID in sm_members table (if user_login is different)
        global $wpdb;
        $member_wp_id = $wpdb->get_var($wpdb->prepare("SELECT wp_user_id FROM {$wpdb->prefix}sm_members WHERE national_id = %s", $username));
        if ($member_wp_id) {
            $u = get_userdata($member_wp_id);
            if ($u && wp_check_password($password, $u->user_pass, $u->ID)) return $u;
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
                <p style="margin:10px 0 0 0; color:#64748b; font-size:16px; font-weight:500;">تواصل مع فروع النقابة في كافة محافظات الجمهورية</p>

                <div style="max-width:500px; margin:30px auto 0; position:relative;">
                    <input type="text" id="sm_branch_search" placeholder="ابحث عن فرع محدد..."
                           style="width:100%; padding:15px 45px 15px 20px; border-radius:15px; border:1px solid #e2e8f0; font-family:'Rubik',sans-serif; outline:none;"
                           oninput="smFilterBranchesPublic(this.value)">
                    <span class="dashicons dashicons-search" style="position:absolute; right:15px; top:15px; color:#94a3b8;"></span>
                </div>
            </div>

            <div id="sm-branches-grid-public" style="display:grid; grid-template-columns:repeat(2, 1fr); gap:30px;">
                <?php if(empty($branches)): ?>
                    <p style="grid-column:span 2; text-align:center; color:#94a3b8;">لا توجد فروع مسجلة حالياً.</p>
                <?php else: foreach($branches as $b): ?>
                    <div class="sm-branch-card-public" data-name="<?php echo esc_attr($b->name); ?>"
                         style="background:#fff; border:1px solid #e2e8f0; border-radius:24px; padding:30px; cursor:pointer; transition:0.3s; box-shadow:0 4px 6px rgba(0,0,0,0.02);"
                         onclick='smShowBranchDetails(<?php echo json_encode($b); ?>)'>
                        <div style="display:flex; align-items:center; gap:20px;">
                            <div style="width:50px; height:50px; background:var(--sm-primary-color); border-radius:15px; display:flex; align-items:center; justify-content:center; color:#fff;">
                                <span class="dashicons dashicons-location"></span>
                            </div>
                            <div>
                                <h3 style="margin:0; font-weight:800; color:var(--sm-dark-color);"><?php echo esc_html($b->name); ?></h3>
                                <div style="font-size:12px; color:#64748b; margin-top:5px;"><?php echo esc_html($b->address); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div id="sm-branch-details-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(5px); z-index:100000; justify-content:center; align-items:center; padding:20px;">
            <div style="background:#fff; width:100%; max-width:600px; border-radius:24px; padding:40px; position:relative;">
                <button onclick="this.parentElement.parentElement.style.display='none'" style="position:absolute; top:20px; left:20px; border:none; background:none; font-size:24px; cursor:pointer;">&times;</button>
                <div id="sm-branch-details-body"></div>
            </div>
        </div>

        <script>
        function smFilterBranchesPublic(val) {
            const cards = document.querySelectorAll('.sm-branch-card-public');
            cards.forEach(c => {
                c.style.display = c.dataset.name.includes(val) ? 'block' : 'none';
            });
        }
        function smShowBranchDetails(b) {
            const body = document.getElementById('sm-branch-details-body');
            body.innerHTML = `
                <h2 style="font-weight:900; color:var(--sm-dark-color); margin-bottom:20px;">${b.name}</h2>
                <div style="display:grid; gap:15px;">
                    <div style="background:#f8fafc; padding:15px; border-radius:12px;"><strong>مدير الفرع:</strong> ${b.manager || 'غير محدد'}</div>
                    <div style="display:flex; align-items:center; gap:10px;"><span class="dashicons dashicons-phone"></span> <strong>الهاتف:</strong> ${b.phone}</div>
                    <div style="display:flex; align-items:center; gap:10px;"><span class="dashicons dashicons-email"></span> <strong>البريد:</strong> ${b.email}</div>
                    <div style="display:flex; align-items:center; gap:10px;"><span class="dashicons dashicons-location"></span> <strong>العنوان:</strong> ${b.address}</div>
                    <div style="margin-top:20px; line-height:1.6; color:#64748b;">${b.description || ''}</div>
                </div>
                <button class="sm-btn" style="width:100%; margin-top:30px;" onclick="this.parentElement.parentElement.parentElement.style.display='none'">إغلاق النافذة</button>
            `;
            document.getElementById('sm-branch-details-modal').style.display = 'flex';
        }
        </script>
        <?php
        return ob_get_clean();
    }

    public function shortcode_services() {
        $services = SM_DB::get_services(['status' => 'active']);
        $is_logged_in = is_user_logged_in();
        $login_url = home_url('/sm-login');

        $categories = ['الكل'];
        foreach ($services as $s) {
            $cat = $s->category ?: 'عام';
            if (!in_array($cat, $categories)) $categories[] = $cat;
        }

        ob_start();
        ?>
        <div class="sm-public-page" dir="rtl">
            <!-- Order Tracking Header (Professional UI Redesign) -->
            <div class="sm-tracking-search-box" style="background: linear-gradient(135deg, #fff 0%, #f9fafb 100%); border: 1px solid #e2e8f0; border-radius: 30px; padding: 50px 40px; margin-bottom: 60px; color: var(--sm-dark-color); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);">
                <div style="text-align: center; margin-bottom: 35px;">
                    <div style="display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 60px; background: rgba(246, 48, 73, 0.1); border-radius: 20px; margin-bottom: 20px;">
                        <span class="dashicons dashicons-search" style="font-size: 30px; width: 30px; height: 30px; color: var(--sm-primary-color);"></span>
                    </div>
                    <h2 style="margin: 0; font-weight: 900; font-size: 2.5em; letter-spacing: -1px; color: var(--sm-dark-color);">متابعة حالة الطلبات</h2>
                    <p style="margin: 12px 0 0 0; color: #64748b; font-size: 16px; font-weight: 500;">استعلم عن حالة طلبك الرقمي لحظياً باستخدام كود التتبع الموحد</p>
                </div>
                <div style="display: flex; gap: 15px; max-width: 750px; margin: 0 auto; background: #fff; padding: 12px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.02);">
                    <div style="flex: 1; position: relative; display: flex; align-items: center;">
                        <span class="dashicons dashicons-text-page" style="position: absolute; right: 20px; color: #94a3b8;"></span>
                        <input type="text" id="sm_service_tracking_input" placeholder="أدخل كود الطلب (مثال: <?php echo date('Ymd'); ?>123)"
                               style="width: 100%; padding: 18px 50px 18px 25px; border-radius: 18px; border: 1px solid transparent; background: #f8fafc; color: var(--sm-dark-color); font-family: 'Rubik', sans-serif; font-size: 16px; outline: none; transition: 0.3s; font-weight: 500;">
                    </div>
                    <button onclick="smTrackServiceRequest()"
                            style="background: var(--sm-primary-color); color: #fff; border: none; padding: 0 45px; border-radius: 18px; font-weight: 800; font-size: 16px; cursor: pointer; transition: 0.3s; font-family: 'Rubik', sans-serif; box-shadow: 0 4px 12px rgba(246, 48, 73, 0.3);">بحث وتتبع</button>
                </div>
                <div id="sm-tracking-results-area" style="margin-top: 40px; display: none; background: #fff; border-radius: 24px; padding: 35px; border: 1px solid #e2e8f0; animation: smFadeIn 0.4s ease; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);"></div>
            </div>

            <div class="sm-services-layout" style="display: flex; gap: 40px; margin-top: 50px; align-items: flex-start;">
                <!-- Right Sidebar: Filters -->
                <div class="sm-services-sidebar" style="width: 320px; flex-shrink: 0; background: #fff; border: 1px solid var(--sm-border-color); border-radius: 24px; padding: 30px; position: sticky; top: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
                    <h4 style="margin: 0 0 25px 0; font-weight: 800; color: var(--sm-dark-color); display: flex; align-items: center; gap: 12px; font-size: 1.1em;">
                        <span style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; background:var(--sm-primary-color); color:#fff; border-radius:8px;"><span class="dashicons dashicons-filter" style="font-size: 18px; width: 18px; height: 18px;"></span></span> فلترة الخدمات
                    </h4>

                    <div style="margin-bottom: 25px;">
                        <label class="sm-label" style="font-size: 13px; margin-bottom: 8px; display: block; color: #64748b;">البحث بالاسم:</label>
                        <div style="position: relative;">
                            <input type="text" id="sm_service_search_filter" placeholder="ابحث عن خدمة..."
                                   style="width: 100%; padding: 12px 15px; border-radius: 12px; border: 1px solid #e2e8f0; font-family: 'Rubik', sans-serif; outline: none;"
                                   oninput="smApplyServiceFilters()">
                            <span class="dashicons dashicons-search" style="position: absolute; left: 10px; top: 10px; color: #94a3b8; font-size: 18px;"></span>
                        </div>
                    </div>

                    <div style="margin-bottom: 25px;">
                        <label class="sm-label" style="font-size: 13px; margin-bottom: 8px; display: block; color: #64748b;">تصنيف الخدمة:</label>
                        <select id="sm_service_cat_filter" class="sm-select" onchange="smApplyServiceFilters()" style="width: 100%; border-radius: 12px;">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom: 25px;">
                        <label class="sm-label" style="font-size: 13px; margin-bottom: 8px; display: block; color: #64748b;">الفرع المتاح فيه:</label>
                        <select id="sm_service_branch_filter" class="sm-select" onchange="smApplyServiceFilters()" style="width: 100%; border-radius: 12px;">
                            <option value="all">جميع الفروع</option>
                            <option value="hq">المركز الرئيسي</option>
                            <?php foreach(SM_Settings::get_governorates() as $k=>$v) echo "<option value='$k'>$v</option>"; ?>
                        </select>
                    </div>

                    <div style="margin-bottom: 10px;">
                        <label class="sm-label" style="font-size: 13px; margin-bottom: 8px; display: block; color: #64748b;">نوع الوصول:</label>
                        <div style="display: grid; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; font-weight: 500; color: var(--sm-dark-color);">
                                <input type="checkbox" class="sm_access_filter" value="public" checked onchange="smApplyServiceFilters()"> خدمات عامة
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; font-weight: 500; color: var(--sm-dark-color);">
                                <input type="checkbox" class="sm_access_filter" value="members" checked onchange="smApplyServiceFilters()"> خدمات الأعضاء فقط
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Left Content: Service Grid -->
                <div class="sm-services-grid-wrapper" style="flex: 1;">
                    <div id="sm-services-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px;">
                        <?php if (empty($services)): ?>
                            <div style="grid-column: 1/-1; text-align: center; padding: 60px; color: #94a3b8; background: #fff; border-radius: 20px; border: 1px dashed #cbd5e0;">
                                <span class="dashicons dashicons-warning" style="font-size: 40px; width: 40px; height: 40px; margin-bottom: 15px; opacity: 0.5;"></span>
                                <p>لا توجد خدمات متاحة في هذا التصنيف حالياً.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($services as $s):
                                $s_cat = $s->category ?: 'عام';
                                $access_type = $s->requires_login ? 'members' : 'public';
                            ?>
                                <div class="sm-service-card-modern"
                                     data-category="<?php echo esc_attr($s_cat); ?>"
                                     data-name="<?php echo esc_attr($s->name); ?>"
                                     data-access="<?php echo $access_type; ?>"
                                     style="background: #fff; border: 1px solid var(--sm-border-color); border-radius: 24px; padding: 35px; display: flex; flex-direction: column; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px;">
                                        <div class="sm-service-icon" style="width: 65px; height: 65px; background: linear-gradient(135deg, var(--sm-primary-color), var(--sm-secondary-color)); border-radius: 18px; display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 10px 15px -3px rgba(246, 48, 73, 0.3);">
                                            <span class="dashicons <?php echo esc_attr($s->icon ?: 'dashicons-cloud'); ?>" style="font-size: 32px; width: 32px; height: 32px;"></span>
                                        </div>
                                        <div style="text-align: left;">
                                            <span style="display: inline-block; padding: 5px 12px; background: #f0f4f8; color: #4a5568; border-radius: 10px; font-size: 11px; font-weight: 700;">
                                                <?php echo esc_html($s_cat); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <h3 style="margin: 0 0 12px 0; font-weight: 800; color: var(--sm-dark-color); font-size: 1.5em; line-height: 1.3;"><?php echo esc_html($s->name); ?></h3>
                                    <p style="font-size: 14px; color: #64748b; line-height: 1.8; margin-bottom: 30px; flex: 1;"><?php echo esc_html($s->description); ?></p>

                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 25px; border-top: 1px solid #f1f5f9;">
                                        <div style="display: flex; flex-direction: column;">
                                            <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">رسوم الخدمة</span>
                                            <span style="font-weight: 900; color: var(--sm-primary-color); font-size: 1.3em;">
                                                <?php echo $s->fees > 0 ? number_format($s->fees, 2) . ' <span style="font-size: 0.6em;">ج.م</span>' : 'خدمة مجانية'; ?>
                                            </span>
                                        </div>
                                        <?php
                                        $btn_onclick = $s->requires_login ? "smHandleLoginService(this)" : "smOpenProgressiveForm(this, ".json_encode($s).")";
                                        $btn_label = $s->requires_login ? "دخول للأعضاء" : "تقديم طلب";
                                        ?>
                                        <button onclick='<?php echo $btn_onclick; ?>' class="sm-btn-sleek sm-service-trigger"
                                                style="background: var(--sm-dark-color); color: #fff; padding: 12px 25px; border: none; border-radius: 15px; font-weight: 700; font-size: 14px; cursor: pointer; transition: 0.3s;">
                                            <?php echo $btn_label; ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Service Access Dropdown / Modal System -->
        <div id="sm-service-dropdown-container" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(5px); z-index:100000; justify-content:center; align-items:center; padding:20px;">
            <div id="sm-service-dropdown-content" style="background:#fff; width:100%; max-width:550px; border-radius:24px; padding:40px; position:relative; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
                <button onclick="document.getElementById('sm-service-dropdown-container').style.display='none'" style="position:absolute; top:20px; left:20px; border:none; background:none; font-size:24px; cursor:pointer; color:#94a3b8;">&times;</button>
                <div id="sm-dropdown-body"></div>
            </div>
        </div>

        <style>
            .sm-category-btn:hover { background: #f8fafc; color: var(--sm-primary-color); }
            .sm-category-btn.active { background: var(--sm-primary-color); color: #fff !important; box-shadow: 0 4px 6px -1px rgba(246, 48, 73, 0.2); }

            .sm-service-card-modern:hover { transform: translateY(-8px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); border-color: var(--sm-primary-color); }
            .sm-btn-sleek:hover { opacity: 0.9; transform: scale(1.05); }

            .sm-tracking-label { font-size: 11px; opacity: 0.7; display: block; margin-bottom: 2px; }
            .sm-tracking-value { font-weight: 700; font-size: 15px; }

            .sm-form-step { display: none; }
            .sm-form-step.active { display: block; animation: smFadeIn 0.4s ease; }

            @media (max-width: 992px) {
                .sm-services-layout { flex-direction: column; }
                .sm-services-sidebar { width: 100%; position: static; }
                .sm-category-list { flex-direction: row; overflow-x: auto; padding-bottom: 10px; }
                .sm-category-btn { white-space: nowrap; }
                #sm-services-grid { grid-template-columns: 1fr; }
            }
        </style>

        <script>
            window.smTrackServiceRequest = function() {
                const code = document.getElementById('sm_service_tracking_input').value;
                const area = document.getElementById('sm-tracking-results-area');
                if(!code) return alert('يرجى إدخال كود التتبع');

                const fd = new FormData();
                fd.append('action', 'sm_track_service_request');
                fd.append('tracking_code', code);

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
                    area.style.display = 'block';
                    if(res.success) {
                        const r = res.data;
                        area.innerHTML = `
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: separate; border-spacing: 0; background: #fff; border-radius: 15px; overflow: hidden; border: 1px solid #f1f5f9;">
                                    <thead style="background: #f8fafc;">
                                        <tr>
                                            <th style="padding: 15px 20px; text-align: right; font-weight: 800; color: var(--sm-dark-color); border-bottom: 2px solid #e2e8f0;">البيان</th>
                                            <th style="padding: 15px 20px; text-align: right; font-weight: 800; color: var(--sm-dark-color); border-bottom: 2px solid #e2e8f0;">التفاصيل</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 600;">كود التتبع</td>
                                            <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9; color: var(--sm-dark-color); font-weight: 800;">${code}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 600;">نوع الخدمة</td>
                                            <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9; color: var(--sm-dark-color); font-weight: 700;">${r.service}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 600;">مقدم الطلب</td>
                                            <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9;">
                                                <div style="font-weight: 700; color: var(--sm-dark-color);">${r.member}</div>
                                                <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">${r.email} | ${r.phone}</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 600;">الفرع</td>
                                            <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9; color: var(--sm-dark-color); font-weight: 700;">${r.branch}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 600;">تاريخ التقديم</td>
                                            <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9; color: var(--sm-dark-color); font-weight: 700;">${r.date}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 600;">الحالة الحالية</td>
                                            <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9;"><span style="background: rgba(246, 48, 73, 0.1); color: var(--sm-primary-color); padding: 5px 15px; border-radius: 20px; font-weight: 800; font-size: 14px;">${r.status}</span></td>
                                        </tr>
                                        ${r.notes ? `
                                        <tr>
                                            <td style="padding: 15px 20px; color: #c53030; font-weight: 800; background: #fff5f5;">ملاحظات الإدارة</td>
                                            <td style="padding: 15px 20px; color: #c53030; font-weight: 600; background: #fff5f5;">${r.notes}</td>
                                        </tr>` : ''}
                                    </tbody>
                                </table>
                            </div>
                        `;
                    } else {
                        area.innerHTML = `<div style="text-align:center; color:#e53e3e; font-weight:700;">${res.data}</div>`;
                    }
                });
            };

            window.smHandleLoginService = function() {
                const container = document.getElementById('sm-service-dropdown-container');
                const body = document.getElementById('sm-dropdown-body');
                container.style.display = 'flex';
                body.innerHTML = `
                    <div style="text-align:center;">
                        <div style="font-size:50px; margin-bottom:20px;">🔒</div>
                        <h3 style="font-weight:900; font-size:1.8em; margin:0 0 10px 0;">هذه الخدمة للأعضاء فقط</h3>
                        <p style="color:#64748b; line-height:1.6; margin-bottom:30px;">يرجى تسجيل الدخول إلى حسابك الشخصي في المنصة للاستفادة من هذه الخدمة ورفع وثائقك.</p>
                        <a href="<?php echo $login_url; ?>" class="sm-btn" style="width:100%; height:50px; font-weight:800; text-decoration:none; display:flex; align-items:center; justify-content:center;">التوجه لصفحة تسجيل الدخول</a>
                    </div>
                `;
            };

            window.smOpenProgressiveForm = function(btn, s) {
                const container = document.getElementById('sm-service-dropdown-container');
                const body = document.getElementById('sm-dropdown-body');
                container.style.display = 'flex';

                let reqFields = [];
                try { reqFields = JSON.parse(s.required_fields); } catch(e){}

                let html = `
                    <div style="margin-bottom:30px;"><h3 style="margin:0; font-weight:900; color:var(--sm-dark-color);">طلب خدمة: ${s.name}</h3><p style="margin:5px 0 0 0; color:#64748b; font-size:13px;">يرجى استكمال البيانات المطلوبة لتقديم طلبك بنجاح</p></div>
                    <form id="sm-public-service-form">
                        <input type="hidden" name="service_id" value="${s.id}">
                        <div class="sm-form-step active" id="step-1">
                            <div class="sm-form-group"><label class="sm-label">الاسم الكامل:</label><input name="cust_name" class="sm-input" required></div>
                            <div class="sm-form-group"><label class="sm-label">البريد الإلكتروني:</label><input name="cust_email" type="email" class="sm-input" required></div>
                            <div class="sm-form-group"><label class="sm-label">رقم الهاتف:</label><input name="cust_phone" class="sm-input" required></div>
                            <div class="sm-form-group">
                                <label class="sm-label">الفرع التابع له:</label>
                                <select name="cust_branch" class="sm-select" required>
                                    <option value="">-- اختر الفرع --</option>
                                    <?php foreach(SM_Settings::get_governorates() as $k=>$v) echo "<option value='$k'>$v</option>"; ?>
                                </select>
                            </div>
                            <button type="button" onclick="smMoveStep(2)" class="sm-btn" style="width:100%; margin-top:10px;">التالي</button>
                        </div>
                        <div class="sm-form-step" id="step-2">
                            ${reqFields.map(f => `<div class="sm-form-group"><label class="sm-label">${f.label}:</label><input name="field_${f.name}" type="${f.type||'text'}" class="sm-input" required></div>`).join('')}
                            <div style="display:flex; gap:10px; margin-top:10px;">
                                <button type="button" onclick="smMoveStep(1)" class="sm-btn sm-btn-outline">السابق</button>
                                <button type="submit" class="sm-btn" style="flex:1;">تأكيد وتقديم الطلب</button>
                            </div>
                        </div>
                    </form>
                `;
                body.innerHTML = html;

                document.getElementById('sm-public-service-form').onsubmit = function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const data = {};
                    formData.forEach((value, key) => {
                        if(key.startsWith('field_')) {
                            data[key.replace('field_', '')] = value;
                        } else if(key.startsWith('cust_')) {
                            data[key] = value;
                        }
                    });

                    const fd = new FormData();
                    fd.append('action', 'sm_submit_service_request');
                    fd.append('service_id', formData.get('service_id'));
                    fd.append('member_id', '0'); // External request
                    fd.append('request_data', JSON.stringify(data));
                    fd.append('nonce', '<?php echo wp_create_nonce("sm_service_action"); ?>');

                    const submitBtn = this.querySelector('button[type="submit"]');
                    submitBtn.disabled = true; submitBtn.innerText = 'جاري المعالجة...';

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
                        if(res.success) {
                            body.innerHTML = `
                                <div style="text-align:center;">
                                    <div style="font-size:60px; margin-bottom:20px;">✅</div>
                                    <h3 style="font-weight:900; font-size:1.8em; margin:0 0 10px 0;">تم تقديم طلبك بنجاح!</h3>
                                    <p style="color:#64748b; line-height:1.6; margin-bottom:20px;">يرجى الاحتفاظ بكود التتبع التالي للاستعلام عن حالة طلبك لاحقاً:</p>
                                    <div style="background:#f8fafc; border:2px dashed var(--sm-primary-color); padding:15px; font-size:24px; font-weight:900; color:var(--sm-primary-color); border-radius:15px; margin-bottom:30px;">${res.data}</div>
                                    <button onclick="location.reload()" class="sm-btn" style="width:100%;">إغلاق</button>
                                </div>
                            `;
                        } else alert(res.data);
                    });
                };
            };

            window.smMoveStep = function(step) {
                document.querySelectorAll('.sm-form-step').forEach(s => s.classList.remove('active'));
                document.getElementById('step-' + step).classList.add('active');
            };

            window.smApplyServiceFilters = function() {
                const searchQuery = document.getElementById('sm_service_search_filter').value.toLowerCase();
                const selectedCat = document.getElementById('sm_service_cat_filter').value;
                const selectedBranch = document.getElementById('sm_service_branch_filter').value;
                const accessFilters = Array.from(document.querySelectorAll('.sm_access_filter:checked')).map(el => el.value);
                const serviceCards = document.querySelectorAll('.sm-service-card-modern');

                serviceCards.forEach(card => {
                    const name = card.dataset.name.toLowerCase();
                    const category = card.dataset.category;
                    const branch = card.dataset.branch;
                    const access = card.dataset.access;

                    const matchesSearch = name.includes(searchQuery);
                    const matchesCategory = selectedCat === 'الكل' || category === selectedCat;
                    const matchesBranch = selectedBranch === 'all' || branch === 'all' || branch === selectedBranch;
                    const matchesAccess = accessFilters.includes(access);

                    if (matchesSearch && matchesCategory && matchesBranch && matchesAccess) {
                        card.style.display = 'flex';
                        card.style.opacity = '1';
                    } else {
                        card.style.display = 'none';
                    }
                });
            };
        </script>
        <?php
        return ob_get_clean();
    }


    public function shortcode_login() {
        if (is_user_logged_in()) {
            wp_redirect(home_url('/sm-admin'));
            exit;
        }
        $syndicate = SM_Settings::get_syndicate_info();
        $output = '<div class="sm-login-container" style="display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; background: #f8fafc;">';
        $output .= '<div class="sm-login-box" style="width: 100%; max-width: 420px; background: #ffffff; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.08); overflow: hidden; border: 1px solid #f1f5f9;" dir="rtl">';

        $output .= '<div style="background: var(--sm-dark-color); padding: 35px 25px; text-align: center; color: #fff;">';
        $output .= '<h3 style="margin: 0 0 10px 0; font-size: 0.9em; opacity: 0.8; font-weight: 400;">أهلاً بك مجدداً</h3>';
        $output .= '<h2 style="margin: 0; font-weight: 900; color: #fff; font-size: 1.6em; letter-spacing: -0.5px;">'.esc_html($syndicate['syndicate_name']).'</h2>';
        $output .= '<p style="margin: 8px 0 0 0; color: #e2e8f0; font-size: 0.85em;">المنصة الرقمية للخدمات النقابية الموحدة</p>';
        $output .= '</div>';

        $output .= '<div style="padding: 30px 30px;">';
        if (isset($_GET['login']) && $_GET['login'] == 'failed') {
            $output .= '<div style="background: #fff5f5; color: #c53030; padding: 10px; border-radius: 8px; border: 1px solid #feb2b2; margin-bottom: 20px; font-size: 0.85em; text-align: center; font-weight: 600;">⚠️ بيانات الدخول غير صحيحة</div>';
        }

        $output .= '<style>
            #sm_login_form p { margin-bottom: 15px; }
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
        </style>';

        $args = array(
            'echo' => false,
            'redirect' => home_url('/sm-admin'),
            'form_id' => 'sm_login_form',
            'label_remember' => 'تذكرني',
            'label_log_in' => 'دخول النظام',
            'remember' => true
        );
        $form = wp_login_form($args);

        // Inject placeholders
        $form = str_replace('name="log"', 'name="log" placeholder="الرقم القومي أو اسم المستخدم"', $form);
        $form = str_replace('name="pwd"', 'name="pwd" placeholder="كلمة المرور"', $form);

        $output .= $form;

        $output .= '<div class="sm-login-footer-links">';
        $output .= '<a href="javascript:void(0)" onclick="smToggleRegistration()" class="sm-footer-btn sm-footer-btn-primary">حساب جديد</a>';
        $output .= '<a href="javascript:void(0)" onclick="smToggleActivation()" class="sm-footer-btn">تفعيل حساب</a>';
        $output .= '<a href="javascript:void(0)" onclick="smToggleRecovery()" style="grid-column: span 2; color: #64748b; font-size: 12px; text-decoration: none; text-align: center; margin-top: 10px;">نسيت كلمة المرور؟</a>';
        $output .= '</div>';

        // Recovery Modal
        $output .= '<div id="sm-recovery-modal" class="sm-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; justify-content:center; align-items:center; padding:20px;">';
        $output .= '<div class="sm-modal-content" style="background:white; width:100%; max-width:400px; padding:35px; border-radius:20px; position:relative;">';
        $output .= '<button onclick="smToggleRecovery()" style="position:absolute; top:20px; left:20px; border:none; background:none; font-size:24px; cursor:pointer; color:#94a3b8;">&times;</button>';
        $output .= '<h3 style="margin-top:0; margin-bottom:25px; text-align:center; font-weight:800;">استعادة كلمة المرور</h3>';
        $output .= '<div id="recovery-step-1">';
        $output .= '<p style="font-size:14px; color:#64748b; margin-bottom:20px; line-height:1.6;">أدخل الرقم القومي الخاص بك للتحقق وإرسال رمز الاستعادة.</p>';
        $output .= '<div class="sm-form-group" style="margin-bottom:20px;"><label class="sm-label">الرقم القومي:</label><input type="text" id="rec_national_id" class="sm-input" placeholder="14 رقم" maxlength="14" style="width:100%;"></div>';
        $output .= '<button onclick="smRequestOTP()" class="sm-btn" style="width:100%;">إرسال رمز التحقق</button>';
        $output .= '</div>';
        $output .= '<div id="recovery-step-2" style="display:none;">';
        $output .= '<p style="font-size:13px; color:#38a169; margin-bottom:15px;">تم إرسال الرمز بنجاح. يرجى التحقق من بريدك.</p>';
        $output .= '<input type="text" id="rec_otp" class="sm-input" placeholder="رمز التحقق (6 أرقام)" style="margin-bottom:10px; width:100%;">';
        $output .= '<input type="password" id="rec_new_pass" class="sm-input" placeholder="كلمة المرور الجديدة" style="margin-bottom:20px; width:100%;">';
        $output .= '<button onclick="smResetPassword()" class="sm-btn" style="width:100%;">تغيير كلمة المرور</button>';
        $output .= '</div>';
        $output .= '</div></div>';

        // Registration Modal (Membership Request) - Sequential 3-Step Form
        $output .= '<div id="sm-registration-modal" class="sm-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(17,31,53,0.85); z-index:10000; justify-content:center; align-items:center; padding:20px; backdrop-filter: blur(4px);">';
        $output .= '<div class="sm-modal-content" style="background:white; width:100%; max-width:600px; padding:40px; border-radius:24px; position:relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">';
        $output .= '<button onclick="smToggleRegistration()" style="position:absolute; top:20px; left:20px; border:none; background:none; font-size:24px; cursor:pointer; color:#94a3b8; transition: 0.2s;">&times;</button>';
        $output .= '<div style="text-align:center; margin-bottom:30px;"><h3 style="margin:0; font-weight:900; font-size:1.5em; color:var(--sm-dark-color);">طلب عضوية جديدة</h3><p style="color:#64748b; font-size:13px; margin-top:5px;">المرحلة الأولى: إدخال البيانات الشخصية والمهنية</p></div>';

        $output .= '<form id="sm-membership-request-form" enctype="multipart/form-data">';

        // Step Indicators
        $output .= '<div class="sm-steps-indicator" style="display:flex; justify-content:center; gap:12px; margin-bottom:30px;">';
        $output .= '<span id="reg-dot-1" style="width:32px; height:32px; background:var(--sm-primary-color); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:14px; transition:0.3s;">1</span>';
        $output .= '<span id="reg-dot-2" style="width:32px; height:32px; background:#edf2f7; color:#718096; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:14px; transition:0.3s;">2</span>';
        $output .= '<span id="reg-dot-3" style="width:32px; height:32px; background:#edf2f7; color:#718096; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:14px; transition:0.3s;">3</span>';
        $output .= '</div>';

        // Step 1: Data Entry
        $output .= '<div id="reg-step-1" class="reg-step">';
        $output .= '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">';
        $output .= '<div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">الاسم الرباعي الكامل:</label><input name="name" type="text" class="sm-input" required></div>';
        $output .= '<div class="sm-form-group"><label class="sm-label">الرقم القومي (14 رقم):</label><input name="national_id" type="text" class="sm-input" required maxlength="14"></div>';
        $output .= '<div class="sm-form-group"><label class="sm-label">الجامعة:</label><select id="reg_university" name="university" class="sm-select academic-cascading" required><option value="">-- اختر الجامعة --</option>';
        foreach(SM_Settings::get_universities() as $k=>$v) $output .= "<option value='$k'>$v</option>";
        $output .= '</select></div>';

        $output .= '<div class="sm-form-group"><label class="sm-label">الكلية:</label><select id="reg_faculty" name="faculty" class="sm-select academic-cascading" required disabled><option value="">-- اختر الكلية --</option>';
        foreach(SM_Settings::get_faculties() as $k=>$v) $output .= "<option value='$k'>$v</option>";
        $output .= '</select></div>';

        $output .= '<div class="sm-form-group"><label class="sm-label">القسم:</label><select id="reg_department" name="department" class="sm-select academic-cascading" required disabled><option value="">-- اختر القسم --</option>';
        foreach(SM_Settings::get_departments() as $k=>$v) $output .= "<option value='$k'>$v</option>";
        $output .= '</select></div>';

        $output .= '<div class="sm-form-group"><label class="sm-label">التخصص:</label><select id="reg_specialization" name="specialization" class="sm-select academic-cascading" required disabled><option value="">-- اختر التخصص --</option>';
        foreach(SM_Settings::get_specializations() as $k=>$v) $output .= "<option value='$k'>$v</option>";
        $output .= '</select></div>';

        $output .= '<div class="sm-form-group"><label class="sm-label">تاريخ التخرج:</label><input name="graduation_date" type="date" class="sm-input" required></div>';
        $output .= '<div class="sm-form-group"><label class="sm-label">الدرجة العلمية:</label><select name="academic_degree" class="sm-select" required>';
        foreach(SM_Settings::get_academic_degrees() as $k=>$v) $output .= "<option value='$k'>$v</option>";
        $output .= '</select></div>';
        $output .= '<div class="sm-form-group"><label class="sm-label">فرع الإقامة:</label><select name="residence_governorate" class="sm-select" required><option value="">-- اختر --</option>';
        foreach(SM_Settings::get_governorates() as $k=>$v) $output .= "<option value='$k'>$v</option>";
        $output .= '</select></div>';
        $output .= '<div class="sm-form-group"><label class="sm-label">مدينة الإقامة:</label><input name="residence_city" type="text" class="sm-input" required></div>';
        $output .= '<div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">الشارع / القرية:</label><input name="residence_street" type="text" class="sm-input" required></div>';
        $output .= '<div class="sm-form-group"><label class="sm-label">لجنة النقابة التابع لها:</label><select name="governorate" class="sm-select" required><option value="">-- اختر --</option>';
        foreach(SM_Settings::get_governorates() as $k=>$v) $output .= "<option value='$k'>$v</option>";
        $output .= '</select></div>';
        $output .= '<div class="sm-form-group"><label class="sm-label">رقم الهاتف الجوال:</label><input name="phone" type="text" class="sm-input" required placeholder="01xxxxxxxxx"></div>';
        $output .= '<div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">البريد الإلكتروني:</label><input name="email" type="email" class="sm-input" required placeholder="example@domain.com"></div>';
        $output .= '</div>';
        $output .= '<button type="button" onclick="smRegNext(2)" class="sm-btn" style="width:100%; margin-top:10px;">التالي: تأكيد الدفع</button>';
        $output .= '</div>';

        // Step 2: Payment Confirmation
        $output .= '<div id="reg-step-2" class="reg-step" style="display:none;">';
        $output .= '<div style="background: #fff5f5; padding: 20px; border-radius: 12px; border: 1px solid #feb2b2; margin-bottom: 25px; text-align: center;">';
        $output .= '<h4 style="margin: 0; color: #c53030;">قيمة رسوم القيد: 480 جنيه مصري</h4>';
        $output .= '<p style="font-size: 13px; color: #7b2c2c; margin-top: 5px;">يرجى سداد المبلغ عبر أحد الطرق الموضحة أدناه</p>';
        $output .= '</div>';

        $output .= '<div class="sm-form-group"><label class="sm-label">طريقة الدفع:</label><select id="reg_payment_method" name="payment_method" class="sm-select" onchange="smTogglePaymentInstructions(this.value)">';
        $output .= '<option value="wallet">تحويل محفظة إلكترونية (فودافون كاش / غيرها)</option>';
        $output .= '<option value="bank">تحويل بنكي (IBAN)</option>';
        $output .= '</select></div>';

        $output .= '<div id="pay_instr_wallet" class="sm-info-box" style="margin-bottom: 20px; padding: 15px; background: #f8fafc; border-radius: 10px; font-size: 13px; line-height: 1.6;">';
        $output .= '<strong>تعليمات دفع المحفظة الإلكترونية:</strong><br>';
        $output .= '1. قم بتحويل مبلغ <strong>480 ج.م</strong> إلى رقم المحفظة: <strong>01000000000</strong> (فودافون كاش).<br>';
        $output .= '2. احتفظ بلقطة شاشة (Screenshot) لرسالة التأكيد أو إيصال التحويل.<br>';
        $output .= '3. أدخل رقم العملية (المرجع) في الحقل أدناه وارفع الصورة.';
        $output .= '</div>';

        $output .= '<div id="pay_instr_bank" class="sm-info-box" style="display:none; margin-bottom: 20px; padding: 15px; background: #f8fafc; border-radius: 10px; font-size: 13px; line-height: 1.6;">';
        $output .= '<strong>تعليمات التحويل البنكي:</strong><br>';
        $output .= '1. قم بتحويل مبلغ <strong>480 ج.م</strong> إلى الحساب رقم: <strong>0000-000000-000</strong><br>';
        $output .= '2. IBAN: <strong>EG000000000000000000000000000</strong><br>';
        $output .= '3. بنك مصر - فرع القاهرة - باسم (النقابة العامة).<br>';
        $output .= '4. ارفع صورة إيصال الإيداع أو التحويل البنكي أدناه.';
        $output .= '</div>';

        $output .= '<div class="sm-form-group"><label class="sm-label">رقم العملية المرجعي / التسلسلي:</label><input name="payment_reference" type="text" class="sm-input" required></div>';
        $output .= '<div class="sm-form-group"><label class="sm-label">صورة إيصال التحويل / لقطة الشاشة:</label><input name="payment_screenshot" type="file" class="sm-input" required accept="image/*"></div>';

        $output .= '<div style="display:grid; grid-template-columns: 1fr 2fr; gap:10px;">';
        $output .= '<button type="button" onclick="smRegNext(1)" class="sm-btn sm-btn-outline" style="width:100%;">السابق</button>';
        $output .= '<button type="submit" class="sm-btn" style="width:100%;">إرسال الطلب للمراجعة</button>';
        $output .= '</div>';
        $output .= '</div>';

        // Step 3: Digital Documents (Accessed after admin approval of Stage 2)
        $output .= '<div id="reg-step-3" class="reg-step" style="display:none;">';
        $output .= '<div style="background: #f0fff4; padding: 20px; border-radius: 12px; border: 1px solid #c6f6d5; margin-bottom: 25px;">';
        $output .= '<h4 style="margin: 0; color: #2f855a; text-align: center;">تمت الموافقة على الدفع. يرجى رفع الوثائق الرقمية</h4>';
        $output .= '</div>';

        $output .= '<div class="sm-form-group"><label class="sm-label">شهادة المؤهل الدراسي (وجهين - PDF):</label><input name="doc_qualification" type="file" class="sm-input" required accept="application/pdf"></div>';
        $output .= '<div class="sm-form-group"><label class="sm-label">بطاقة الرقم القومي (وجهين - PDF):</label><input name="doc_id" type="file" class="sm-input" required accept="application/pdf"></div>';
        $output .= '<div class="sm-form-group"><label class="sm-label">شهادة الخدمة العسكرية (للذكور - PDF):</label><input name="doc_military" type="file" class="sm-input" accept="application/pdf"></div>';
        $output .= '<div class="sm-form-group"><label class="sm-label">صحيفة الحالة الجنائية (فيش - PDF):</label><input name="doc_criminal" type="file" class="sm-input" required accept="application/pdf"></div>';
        $output .= '<div class="sm-form-group"><label class="sm-label">صورة شخصية حديثة (Image):</label><input name="doc_photo" type="file" class="sm-input" required accept="image/*"></div>';

        $output .= '<div style="background: #fffaf0; padding: 15px; border-radius: 10px; border: 1px solid #feebc8; margin-top: 20px; font-size: 12px; line-height: 1.6;">';
        $output .= '<strong>ملاحظة هامة:</strong> بعد رفع الوثائق الرقمية، يتوجب عليك إرسال أصول المستندات عبر البريد المصري إلى مقر النقابة لإتمام التفعيل النهائي.';
        $output .= '</div>';

        $output .= '<button type="button" onclick="smSubmitStage3()" class="sm-btn" style="width:100%; margin-top:20px;">رفع الوثائق الرقمية وتأكيد الإرسال</button>';
        $output .= '</div>';

        $output .= '</form>';

        // Request Status Tracking Feature
        $output .= '<div id="sm-track-registration" style="margin-top: 40px; border-top: 1px solid #eee; padding-top: 30px;">';
        $output .= '<h4 style="text-align: center; margin-bottom: 20px; font-weight: 800;">متابعة حالة طلب القيد</h4>';
        $output .= '<div style="display: flex; gap: 10px; max-width: 400px; margin: 0 auto;">';
        $output .= '<input type="text" id="track_national_id" class="sm-input" placeholder="أدخل الرقم القومي للمتابعة" maxlength="14">';
        $output .= '<button onclick="smTrackRequest()" class="sm-btn" style="width: auto; white-space: nowrap;">متابعة</button>';
        $output .= '</div>';
        $output .= '<div id="track-result" style="margin-top: 20px; display: none;"></div>';
        $output .= '</div>';

        $output .= '</div></div>';

        // Activation Modal (3-Step Sequential Workflow)
        $output .= '<div id="sm-activation-modal" class="sm-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; justify-content:center; align-items:center; padding:20px;">';
        $output .= '<div class="sm-modal-content" style="background:white; width:100%; max-width:450px; padding:40px; border-radius:24px; position:relative;">';
        $output .= '<button onclick="smToggleActivation()" style="position:absolute; top:20px; left:20px; border:none; background:none; font-size:24px; cursor:pointer; color:#94a3b8;">&times;</button>';
        $output .= '<div style="text-align:center; margin-bottom:30px;"><h3 style="margin:0; font-weight:900;">تفعيل الحساب الرقمي</h3><p style="color:#64748b; font-size:13px; margin-top:5px;">خطوات بسيطة للوصول لخدماتك الإلكترونية</p></div>';

        // Step 1: Verification
        $output .= '<div id="activation-step-1">';
        $output .= '<div style="display:flex; justify-content:center; gap:10px; margin-bottom:20px;"><span style="width:30px; height:30px; background:var(--sm-primary-color); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">1</span><span style="width:30px; height:30px; background:#edf2f7; color:#718096; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">2</span><span style="width:30px; height:30px; background:#edf2f7; color:#718096; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">3</span></div>';
        $output .= '<p style="font-size:14px; color:#4a5568; margin-bottom:20px; text-align:center;">المرحلة الأولى: التحقق من الهوية بالسجلات</p>';
        $output .= '<div class="sm-form-group" style="margin-bottom:15px;"><input type="text" id="act_national_id" class="sm-input" placeholder="الرقم القومي (14 رقم)" style="width:100%;"></div>';
        $output .= '<div class="sm-form-group" style="margin-bottom:15px;"><input type="text" id="act_mem_no" class="sm-input" placeholder="رقم القيد النقابي" style="width:100%;"></div>';
        $output .= '<button onclick="smActivateStep1()" class="sm-btn" style="width:100%;">تحقق وانتقل للخطوة التالية</button>';
        $output .= '</div>';

        // Step 2: Contact Confirmation
        $output .= '<div id="activation-step-2" style="display:none;">';
        $output .= '<div style="display:flex; justify-content:center; gap:10px; margin-bottom:20px;"><span style="width:30px; height:30px; background:#38a169; color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">✓</span><span style="width:30px; height:30px; background:var(--sm-primary-color); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">2</span><span style="width:30px; height:30px; background:#edf2f7; color:#718096; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">3</span></div>';
        $output .= '<p style="font-size:14px; color:#4a5568; margin-bottom:20px; text-align:center;">المرحلة الثانية: تأكيد بيانات التواصل</p>';
        $output .= '<div class="sm-form-group" style="margin-bottom:15px;"><input type="email" id="act_email" class="sm-input" placeholder="البريد الإلكتروني المعتمد" style="width:100%;"></div>';
        $output .= '<div class="sm-form-group" style="margin-bottom:15px;"><input type="text" id="act_phone" class="sm-input" placeholder="رقم الهاتف الحالي" style="width:100%;"></div>';
        $output .= '<button onclick="smActivateStep2()" class="sm-btn" style="width:100%;">تأكيد البيانات</button>';
        $output .= '</div>';

        // Step 3: Account Completion
        $output .= '<div id="activation-step-3" style="display:none;">';
        $output .= '<div style="display:flex; justify-content:center; gap:10px; margin-bottom:20px;"><span style="width:30px; height:30px; background:#38a169; color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">✓</span><span style="width:30px; height:30px; background:#38a169; color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">✓</span><span style="width:30px; height:30px; background:var(--sm-primary-color); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">3</span></div>';
        $output .= '<p style="font-size:14px; color:#4a5568; margin-bottom:20px; text-align:center;">المرحلة الثالثة: تعيين كلمة المرور</p>';
        $output .= '<div class="sm-form-group" style="margin-bottom:20px;"><input type="password" id="act_pass" class="sm-input" placeholder="كلمة المرور (10 خانات على الأقل)" style="width:100%;"></div>';
        $output .= '<button onclick="smActivateFinal()" class="sm-btn" style="width:100%;">إكمال التنشيط والدخول</button>';
        $output .= '</div>';
        $output .= '</div></div>';

        $output .= '<script>
        function smToggleRecovery() {
            const m = document.getElementById("sm-recovery-modal");
            m.style.display = m.style.display === "none" ? "flex" : "none";
        }
        function smToggleActivation() {
            const m = document.getElementById("sm-activation-modal");
            m.style.display = m.style.display === "none" ? "flex" : "none";
            document.getElementById("activation-step-1").style.display = "block";
            document.getElementById("activation-step-2").style.display = "none";
        }
        function smToggleRegistration() {
            const m = document.getElementById("sm-registration-modal");
            const isClosing = m.style.display !== "none";
            m.style.display = isClosing ? "none" : "flex";
            if (!isClosing) {
                smRegNext(1);
                document.getElementById("sm-membership-request-form").reset();
            }
        }
        document.querySelectorAll(".academic-cascading").forEach((el, idx, arr) => {
            el.addEventListener("change", function() {
                if (this.value && idx < arr.length - 1) {
                    arr[idx + 1].disabled = false;
                } else if (!this.value) {
                    for (let i = idx + 1; i < arr.length; i++) {
                        arr[i].value = "";
                        arr[i].disabled = true;
                    }
                }
            });
        });

        function smRegNext(step) {
            if (step === 2) {
                const required = ["name", "national_id", "university", "faculty", "department", "specialization", "graduation_date", "residence_street", "residence_city", "residence_governorate", "governorate", "phone", "email"];
                for (const name of required) {
                    const el = document.querySelector(`#sm-membership-request-form [name="${name}"]`);
                    if (!el.value) return alert("يرجى ملء كافة الحقول المطلوبة قبل الانتقال للخطوة التالية.");
                }
                const nid = document.querySelector("#sm-membership-request-form input[name=\"national_id\"]").value;
                if (nid.length !== 14) return alert("الرقم القومي يجب أن يتكون من 14 رقم.");
            }
            document.querySelectorAll(".reg-step").forEach(s => s.style.display = "none");
            document.getElementById("reg-step-" + step).style.display = "block";
            for (let i = 1; i <= 3; i++) {
                const dot = document.getElementById("reg-dot-" + i);
                if (!dot) continue;
                if (i < step) {
                    dot.style.background = "#38a169";
                    dot.style.color = "white";
                    dot.innerText = "✓";
                } else if (i === step) {
                    dot.style.background = "var(--sm-primary-color)";
                    dot.style.color = "white";
                    dot.innerText = i;
                } else {
                    dot.style.background = "#edf2f7";
                    dot.style.color = "#718096";
                    dot.innerText = i;
                }
            }
        }
        function smTogglePaymentInstructions(val) {
            document.getElementById("pay_instr_wallet").style.display = val === "wallet" ? "block" : "none";
            document.getElementById("pay_instr_bank").style.display = val === "bank" ? "block" : "none";
        }
        function smTrackRequest() {
            const nid = document.getElementById("track_national_id").value;
            if (nid.length !== 14) return alert("يرجى إدخال رقم قومي صحيح.");
            const fd = new FormData(); fd.append("action", "sm_track_membership_request"); fd.append("national_id", nid);
            fetch("'.admin_url('admin-ajax.php').'", {method:"POST", body:fd}).then(r=>r.json()).then(res=>{
                const div = document.getElementById("track-result"); div.style.display = "block";
                if(res.success) {
                    const r = res.data;
                    let html = `<div style="padding:20px; border-radius:12px; background:#f8fafc; border:1px solid #e2e8f0;">
                        <h5 style="margin:0 0 10px 0; font-weight:800;">حالة الطلب: <span style="color:var(--sm-primary-color);">${r.status}</span></h5>
                        <p style="font-size:13px; color:#64748b; margin-bottom:15px;">المرحلة الحالية: ${r.current_stage} من 3</p>`;
                    if(r.rejection_reason) html += `<p style="color:#e53e3e; font-size:12px;"><strong>سبب الرفض:</strong> ${r.rejection_reason}</p>`;

                    if(r.status === "Payment Approved" || r.current_stage == 3) {
                        html += `<button onclick="smRegNext(3)" class="sm-btn" style="width:100%;">الانتقال لمرحلة رفع الوثائق</button>`;
                    } else if(r.status === "Rejected") {
                         html += `<p style="font-size:12px;">يرجى مراجعة البيانات والتحويل مرة أخرى أو التواصل مع الدعم.</p>`;
                    }
                    html += "</div>";
                    div.innerHTML = html;
                } else div.innerHTML = `<div style="color:#e53e3e; text-align:center; font-size:13px;">${res.data}</div>`;
            });
        }
        async function smSubmitStage3() {
            const form = document.getElementById("sm-membership-request-form");
            const fd = new FormData(form);
            fd.append("action", "sm_submit_membership_request_stage3");
            fd.append("national_id", document.getElementById("track_national_id").value || fd.get("national_id"));

            const btn = event.target; btn.disabled = true; btn.innerText = "جاري الرفع...";
            fetch("'.admin_url('admin-ajax.php').'", {method:"POST", body:fd}).then(r=>r.json()).then(res=>{
                if(res.success) { alert("تم رفع الوثائق بنجاح. يرجى إرسال الأصول عبر البريد المصري."); location.reload(); }
                else { alert(res.data); btn.disabled = false; btn.innerText = "رفع الوثائق الرقمية وتأكيد الإرسال"; }
            });
        }
        function smRequestOTP() {
            const nid = document.getElementById("rec_national_id").value;
            const fd = new FormData(); fd.append("action", "sm_forgot_password_otp"); fd.append("national_id", nid);
            fetch("'.admin_url('admin-ajax.php').'", {method:"POST", body:fd}).then(r=>r.json()).then(res=>{
                if(res.success) {
                    document.getElementById("recovery-step-1").style.display="none";
                    document.getElementById("recovery-step-2").style.display="block";
                } else alert(res.data);
            });
        }
        function smActivateStep2() {
            const email = document.getElementById("act_email").value;
            const phone = document.getElementById("act_phone").value;
            if(!/^\S+@\S+\.\S+$/.test(email)) return alert("يرجى إدخال بريد إلكتروني صحيح");
            if(phone.length < 10) return alert("يرجى إدخال رقم هاتف صحيح");
            document.getElementById("activation-step-2").style.display="none";
            document.getElementById("activation-step-3").style.display="block";
        }
        function smResetPassword() {
            const nid = document.getElementById("rec_national_id").value;
            const otp = document.getElementById("rec_otp").value;
            const pass = document.getElementById("rec_new_pass").value;
            const fd = new FormData(); fd.append("action", "sm_reset_password_otp");
            fd.append("national_id", nid); fd.append("otp", otp); fd.append("new_password", pass);
            fetch("'.admin_url('admin-ajax.php').'", {method:"POST", body:fd}).then(r=>r.json()).then(res=>{
                if(res.success) { alert(res.data); location.reload(); } else alert(res.data);
            });
        }
        function smActivateStep1() {
            const nid = document.getElementById("act_national_id").value;
            const mem = document.getElementById("act_mem_no").value;
            if(!/^[0-9]{14}$/.test(nid)) return alert("يرجى إدخال رقم قومي صحيح (14 رقم)");
            const fd = new FormData(); fd.append("action", "sm_activate_account_step1");
            fd.append("national_id", nid); fd.append("membership_number", mem);
            fetch("'.admin_url('admin-ajax.php').'", {method:"POST", body:fd}).then(r=>r.json()).then(res=>{
                if(res.success) {
                    document.getElementById("activation-step-1").style.display="none";
                    document.getElementById("activation-step-2").style.display="block";
                } else alert(res.data);
            });
        }
        function smActivateFinal() {
            const nid = document.getElementById("act_national_id").value;
            const mem = document.getElementById("act_mem_no").value;
            const email = document.getElementById("act_email").value;
            const phone = document.getElementById("act_phone").value;
            const pass = document.getElementById("act_pass").value;
            if(!/^\S+@\S+\.\S+$/.test(email)) return alert("يرجى إدخال بريد إلكتروني صحيح");
            if(pass.length < 10) return alert("كلمة المرور يجب أن تكون 10 أحرف على الأقل");
            const fd = new FormData(); fd.append("action", "sm_activate_account_final");
            fd.append("national_id", nid); fd.append("membership_number", mem);
            fd.append("email", email); fd.append("phone", phone); fd.append("password", pass);
            fetch("'.admin_url('admin-ajax.php').'", {method:"POST", body:fd}).then(r=>r.json()).then(res=>{
                if(res.success) { alert(res.data); location.reload(); } else alert(res.data);
            });
        }

        document.getElementById("sm-membership-request-form")?.addEventListener("submit", function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append("action", "sm_submit_membership_request");
            fd.append("nonce", "'.wp_create_nonce("sm_registration_nonce").'");

            const nid = fd.get("national_id");
            if(!/^[0-9]{14}$/.test(nid)) return alert("يرجى إدخال رقم قومي صحيح (14 رقم)");

            fetch("'.admin_url('admin-ajax.php').'", {method:"POST", body:fd}).then(r=>r.json()).then(res=>{
                if(res.success) {
                    alert("تم إرسال طلبك بنجاح. سيتم مراجعته من قبل الإدارة وسيتم تفعيل حسابك فور الموافقة.");
                    smToggleRegistration();
                } else alert(res.data);
            });
        });
        </script>';

        $output .= '</div>'; // End padding
        $output .= '</div>'; // End box
        $output .= '</div>'; // End container
        return $output;
    }

    public function shortcode_login_page() {
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

                <div id="sm-user-dropdown-menu" style="display: none; position: absolute; top: 110%; left: 0; background: white; border: 1px solid var(--sm-border-color); border-radius: 12px; width: 220px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); z-index: 100000; animation: smFadeIn 0.2s ease-out; padding: 8px 0; margin: 0;">
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

    public function shortcode_admin_dashboard() {
        if (!is_user_logged_in()) {
            return $this->shortcode_login();
        }

        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        $active_tab = isset($_GET['sm_tab']) ? sanitize_text_field($_GET['sm_tab']) : 'summary';

        $is_admin = in_array('administrator', $roles) || current_user_can('sm_manage_system');
        $is_sys_admin = in_array('sm_system_admin', $roles);
        $is_syndicate_admin = in_array('sm_syndicate_admin', $roles);
        $is_syndicate_member = in_array('sm_syndicate_member', $roles);

        // Fetch data
        $stats = SM_DB::get_statistics();

        ob_start();
        include SM_PLUGIN_DIR . 'templates/public-admin-panel.php';
        return ob_get_clean();
    }

    public function login_failed($username) {
        $referrer = wp_get_referer();
        if ($referrer && !strstr($referrer, 'wp-login') && !strstr($referrer, 'wp-admin')) {
            wp_redirect(add_query_arg('login', 'failed', $referrer));
            exit;
        }
    }

    public function log_successful_login($user_login, $user) {
        SM_Logger::log('تسجيل دخول', "المستخدم: $user_login");
    }

    public function ajax_get_member() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $member = SM_DB::get_member_by_national_id($national_id);
        if ($member) {
            if (!$this->can_access_member($member->id)) wp_send_json_error('Access denied');
            wp_send_json_success($member);
        } else {
            wp_send_json_error('Member not found');
        }
    }

    public function ajax_search_members() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        $query = sanitize_text_field($_POST['query']);
        $members = SM_DB::get_members(array('search' => $query));
        wp_send_json_success($members);
    }

    public function ajax_refresh_dashboard() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        wp_send_json_success(array('stats' => SM_DB::get_statistics()));
    }

    public function ajax_update_member_photo() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_photo_action', 'sm_photo_nonce');

        $member_id = intval($_POST['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('member_photo', 0);
        if (is_wp_error($attachment_id)) wp_send_json_error($attachment_id->get_error_message());

        $photo_url = wp_get_attachment_url($attachment_id);
        $member_id = intval($_POST['member_id']);
        SM_DB::update_member_photo($member_id, $photo_url);
        wp_send_json_success(array('photo_url' => $photo_url));
    }

    public function ajax_add_staff() {
        if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicateMemberAction')) wp_send_json_error('Security check failed');

        $username = sanitize_user($_POST['user_login']);
        $email = sanitize_email($_POST['user_email']);
        $display_name = sanitize_text_field($_POST['display_name']);
        $role = sanitize_text_field($_POST['role']);

        if (empty($username)) wp_send_json_error('اسم المستخدم مطلوب');
        if (empty($email)) wp_send_json_error('البريد الإلكتروني مطلوب');
        if (empty($display_name)) wp_send_json_error('الاسم الكامل مطلوب');
        if (empty($role)) wp_send_json_error('الدور مطلوب');

        if (username_exists($username)) wp_send_json_error('اسم المستخدم موجود مسبقاً');
        if (email_exists($email)) wp_send_json_error('البريد الإلكتروني مسجل لمستخدم آخر');

        if (!empty($_POST['user_pass'])) {
            $pass = $_POST['user_pass'];
        } else {
            $digits = '';
            for ($i = 0; $i < 10; $i++) {
                $digits .= mt_rand(0, 9);
            }
            $pass = 'IRS' . $digits;
        }

        // Prevent role escalation
        if ($role === 'sm_system_admin' && !current_user_can('sm_full_access') && !current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions to assign this role');
        }

        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_email' => $email,
            'display_name' => $display_name,
            'user_pass' => $pass,
            'role' => $role
        ));

        if (is_wp_error($user_id)) wp_send_json_error($user_id->get_error_message());

        update_user_meta($user_id, 'sm_temp_pass', $pass);
        update_user_meta($user_id, 'sm_syndicateMemberIdAttr', sanitize_text_field($_POST['officer_id']));
        update_user_meta($user_id, 'sm_phone', sanitize_text_field($_POST['phone']));
        update_user_meta($user_id, 'sm_rank', sanitize_text_field($_POST['rank']));
        update_user_meta($user_id, 'sm_account_status', 'active');

        $gov = sanitize_text_field($_POST['governorate'] ?? '');
        if (in_array('sm_syndicate_admin', (array)wp_get_current_user()->roles) || in_array('sm_syndicate_member', (array)wp_get_current_user()->roles)) {
            if ($role !== 'sm_system_admin') {
                $gov = get_user_meta(get_current_user_id(), 'sm_governorate', true);
            }
        }
        if (empty($gov) && $role !== 'sm_system_admin' && $role !== 'administrator') {
             wp_send_json_error('يجب تعيين فرع النقابة للمسؤول');
        }
        update_user_meta($user_id, 'sm_governorate', $gov);

        // If role is member, ensure entry in sm_members table for sync
        if ($role === 'sm_syndicate_member') {
            global $wpdb;
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE national_id = %s OR wp_user_id = %d", $_POST['officer_id'], $user_id));
            if (!$exists) {
                SM_DB::add_member([
                    'national_id' => sanitize_text_field($_POST['officer_id']),
                    'name' => sanitize_text_field($_POST['display_name']),
                    'email' => $email,
                    'phone' => sanitize_text_field($_POST['phone']),
                    'governorate' => $gov,
                    'wp_user_id' => $user_id
                ]);
            }
        }

        SM_Logger::log('إضافة مستخدم', "الاسم: {$_POST['display_name']} الرتبة: $role");
        wp_send_json_success($user_id);
    }

    public function ajax_delete_staff() {
        if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_syndicateMemberAction')) wp_send_json_error('Security check failed');

        $user_id = intval($_POST['user_id']);
        if ($user_id === get_current_user_id()) wp_send_json_error('Cannot delete yourself');
        if (!$this->can_manage_user($user_id)) wp_send_json_error('Access denied');

        wp_delete_user($user_id);
        wp_send_json_success('Deleted');
    }

    public function ajax_update_staff() {
        if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicateMemberAction')) wp_send_json_error('Security check failed');

        $user_id = intval($_POST['edit_officer_id']);
        if (!$this->can_manage_user($user_id)) wp_send_json_error('Access denied');

        $role = sanitize_text_field($_POST['role']);

        // Prevent role escalation
        if ($role === 'sm_system_admin' && !current_user_can('sm_full_access') && !current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions to assign this role');
        }

        $user_data = array('ID' => $user_id, 'display_name' => sanitize_text_field($_POST['display_name']), 'user_email' => sanitize_email($_POST['user_email']));
        if (!empty($_POST['user_pass'])) {
            $user_data['user_pass'] = $_POST['user_pass'];
            update_user_meta($user_id, 'sm_temp_pass', $_POST['user_pass']);
        }
        wp_update_user($user_data);

        $u = new WP_User($user_id);
        $u->set_role($role);

        update_user_meta($user_id, 'sm_syndicateMemberIdAttr', sanitize_text_field($_POST['officer_id']));
        update_user_meta($user_id, 'sm_phone', sanitize_text_field($_POST['phone']));
        update_user_meta($user_id, 'sm_rank', sanitize_text_field($_POST['rank']));

        $gov = sanitize_text_field($_POST['governorate'] ?? '');
        if (in_array('sm_syndicate_admin', (array)wp_get_current_user()->roles) || in_array('sm_syndicate_member', (array)wp_get_current_user()->roles)) {
            if ($role !== 'sm_system_admin') {
                $gov = get_user_meta(get_current_user_id(), 'sm_governorate', true);
            }
        }
        if (empty($gov) && $role !== 'sm_system_admin' && $role !== 'administrator') {
             wp_send_json_error('يجب تعيين فرع النقابة للمسؤول');
        }
        update_user_meta($user_id, 'sm_governorate', $gov);

        update_user_meta($user_id, 'sm_account_status', sanitize_text_field($_POST['account_status']));

        // Sync to sm_members if it's a member
        if ($role === 'sm_syndicate_member') {
            global $wpdb;
            $wpdb->update("{$wpdb->prefix}sm_members", [
                'name' => sanitize_text_field($_POST['display_name']),
                'email' => sanitize_email($_POST['user_email']),
                'phone' => sanitize_text_field($_POST['phone']),
                'governorate' => $gov
            ], ['wp_user_id' => $user_id]);
        }

        SM_Logger::log('تحديث مستخدم', "الاسم: {$_POST['display_name']}");
        wp_send_json_success('Updated');
    }

    public function ajax_add_member() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_add_member', 'sm_nonce');
        $res = SM_DB::add_member($_POST);
        if (is_wp_error($res)) wp_send_json_error($res->get_error_message());
        else wp_send_json_success($res);
    }

    public function ajax_update_member() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_add_member', 'sm_nonce');

        $member_id = intval($_POST['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');

        SM_DB::update_member($member_id, $_POST);
        wp_send_json_success('Updated');
    }

    public function ajax_delete_member() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_delete_member', 'nonce');

        $member_id = intval($_POST['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');

        SM_DB::delete_member($member_id);
        wp_send_json_success('Deleted');
    }

    public function ajax_update_license() {
        if (!current_user_can('sm_manage_licenses')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_add_member', 'nonce');
        $member_id = intval($_POST['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');
        SM_DB::update_member($member_id, [
            'license_number' => sanitize_text_field($_POST['license_number']),
            'license_issue_date' => sanitize_text_field($_POST['license_issue_date']),
            'license_expiration_date' => sanitize_text_field($_POST['license_expiration_date'])
        ]);

        // Archive License in Vault
        SM_DB::add_document([
            'member_id' => $member_id,
            'category' => 'licenses',
            'title' => "تصريح مزاولة مهنة رقم " . $_POST['license_number'],
            'file_url' => admin_url('admin-ajax.php?action=sm_print_license&member_id=' . $member_id),
            'file_type' => 'application/pdf'
        ]);

        SM_Logger::log('تحديث ترخيص مزاولة', "العضو ID: $member_id");
        wp_send_json_success();
    }

    public function ajax_update_facility() {
        if (!current_user_can('sm_manage_licenses')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_add_member', 'nonce');
        $member_id = intval($_POST['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');
        SM_DB::update_member($member_id, [
            'facility_name' => sanitize_text_field($_POST['facility_name']),
            'facility_number' => sanitize_text_field($_POST['facility_number']),
            'facility_category' => sanitize_text_field($_POST['facility_category']),
            'facility_license_issue_date' => sanitize_text_field($_POST['facility_license_issue_date']),
            'facility_license_expiration_date' => sanitize_text_field($_POST['facility_license_expiration_date']),
            'facility_address' => sanitize_textarea_field($_POST['facility_address'])
        ]);

        // Archive Facility License in Vault
        SM_DB::add_document([
            'member_id' => $member_id,
            'category' => 'licenses',
            'title' => "ترخيص منشأة: " . $_POST['facility_name'],
            'file_url' => admin_url('admin-ajax.php?action=sm_print_facility&member_id=' . $member_id),
            'file_type' => 'application/pdf'
        ]);

        SM_Logger::log('تحديث منشأة', "العضو ID: $member_id");
        wp_send_json_success();
    }

    public function ajax_record_payment() {
        if (!current_user_can('sm_manage_finance')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_finance_action', 'nonce');
        $member_id = intval($_POST['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');
        if (SM_Finance::record_payment($_POST)) wp_send_json_success();
        else wp_send_json_error('Failed to record payment');
    }

    public function ajax_delete_transaction() {
        if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        global $wpdb;
        $id = intval($_POST['transaction_id']);
        $wpdb->delete("{$wpdb->prefix}sm_payments", ['id' => $id]);
        SM_Logger::log('حذف عملية مالية', "تم حذف العملية رقم #$id بواسطة مدير النظام");
        wp_send_json_success();
    }

    public function ajax_delete_gov_data() {
        if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        global $wpdb;
        $gov = sanitize_text_field($_POST['governorate']);
        if (!$gov) wp_send_json_error('فرع غير محددة');

        // 1. Get member IDs for this gov
        $member_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE governorate = %s", $gov));
        if (empty($member_ids)) wp_send_json_success('لا توجد بيانات لهذه الفرع');

        // 2. Delete WP Users
        $wp_user_ids = $wpdb->get_col($wpdb->prepare("SELECT wp_user_id FROM {$wpdb->prefix}sm_members WHERE governorate = %s AND wp_user_id IS NOT NULL", $gov));
        if (!empty($wp_user_ids)) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            foreach ($wp_user_ids as $uid) wp_delete_user($uid);
        }

        // 3. Delete payments
        $ids_str = implode(',', array_map('intval', $member_ids));
        $wpdb->query("DELETE FROM {$wpdb->prefix}sm_payments WHERE member_id IN ($ids_str)");

        // 4. Delete members
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sm_members WHERE governorate = %s", $gov));

        SM_Logger::log('حذف بيانات فرع', "تم مسح كافة بيانات فرع: $gov");
        wp_send_json_success();
    }

    public function ajax_merge_gov_data() {
        if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        $gov = sanitize_text_field($_POST['governorate']);
        if (empty($_FILES['backup_file']['tmp_name'])) wp_send_json_error('الملف غير موجود');

        $json = file_get_contents($_FILES['backup_file']['tmp_name']);
        $data = json_decode($json, true);
        if (!$data || !isset($data['members'])) wp_send_json_error('تنسيق الملف غير صحيح');

        $success = 0; $skipped = 0;
        foreach ($data['members'] as $row) {
            // Only merge members belonging to the TARGET governorate if specified in the row,
            // OR force them to the target governorate.
            // Requirement says "data for a single governorate only"
            if ($row['governorate'] !== $gov) {
                $skipped++;
                continue;
            }

            if (SM_DB::member_exists($row['national_id'])) {
                $skipped++;
                continue;
            }

            // Clean data for insertion
            unset($row['id']);

            // Re-create WP User if needed
            $digits = ''; for ($i = 0; $i < 10; $i++) $digits .= mt_rand(0, 9);
            $temp_pass = 'IRS' . $digits;
            $wp_user_id = wp_insert_user([
                'user_login' => $row['national_id'],
                'user_email' => $row['email'] ?: $row['national_id'] . '@irseg.org',
                'display_name' => $row['name'],
                'user_pass' => $temp_pass,
                'role' => 'sm_syndicate_member'
            ]);

            if (!is_wp_error($wp_user_id)) {
                $row['wp_user_id'] = $wp_user_id;
                update_user_meta($wp_user_id, 'sm_temp_pass', $temp_pass);
                update_user_meta($wp_user_id, 'sm_governorate', $gov);
            }

            global $wpdb;
            if ($wpdb->insert("{$wpdb->prefix}sm_members", $row)) $success++;
            else $skipped++;
        }

        SM_Logger::log('دمج بيانات فرع', "تم دمج $success عضواً لفرع $gov (تخطى $skipped)");
        wp_send_json_success("تم بنجاح دمج $success عضواً وتجاهل $skipped عضواً مسجلين مسبقاً.");
    }

    public function ajax_reset_system() {
        if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        $password = $_POST['admin_password'] ?? '';
        $current_user = wp_get_current_user();
        if (!wp_check_password($password, $current_user->user_pass, $current_user->ID)) {
            wp_send_json_error('كلمة المرور غير صحيحة. يرجى إدخال كلمة مرور مدير النظام للمتابعة.');
        }

        global $wpdb;
        $tables = [
            'sm_members', 'sm_payments', 'sm_logs', 'sm_messages',
            'sm_surveys', 'sm_survey_responses', 'sm_update_requests'
        ];

        // 1. Delete WordPress Users associated with members
        $member_wp_ids = $wpdb->get_col("SELECT wp_user_id FROM {$wpdb->prefix}sm_members WHERE wp_user_id IS NOT NULL");
        if (!empty($member_wp_ids)) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            foreach ($member_wp_ids as $uid) {
                wp_delete_user($uid);
            }
        }

        // 2. Truncate Tables
        foreach ($tables as $t) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}$t");
        }

        // 3. Reset sequences
        delete_option('sm_invoice_sequence_' . date('Y'));

        SM_Logger::log('إعادة تهيئة النظام', "تم مسح كافة البيانات وتصفير النظام بالكامل");
        wp_send_json_success();
    }

    public function ajax_rollback_log() {
        if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        $log_id = intval($_POST['log_id']);
        global $wpdb;
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_logs WHERE id = %d", $log_id));

        if (!$log || strpos($log->details, 'ROLLBACK_DATA:') !== 0) {
            wp_send_json_error('لا توجد بيانات استعادة لهذه العملية');
        }

        $json = str_replace('ROLLBACK_DATA:', '', $log->details);
        $rollback_info = json_decode($json, true);

        if (!$rollback_info || !isset($rollback_info['table'])) {
            wp_send_json_error('تنسيق بيانات الاستعادة غير صحيح');
        }

        $table = $rollback_info['table'];
        $data = $rollback_info['data'];

        if ($table === 'members') {
            // Re-insert into sm_members
            $wp_user_id = $data['wp_user_id'] ?? null;

            // Check if user login already exists
            if (!empty($data['national_id']) && username_exists($data['national_id'])) {
                wp_send_json_error('لا يمكن الاستعادة: اسم المستخدم (الرقم القومي) موجود بالفعل');
            }

            // Re-create WP User if it was deleted
            if ($wp_user_id && !get_userdata($wp_user_id)) {
                $digits = ''; for ($i = 0; $i < 10; $i++) $digits .= mt_rand(0, 9);
                $temp_pass = 'IRS' . $digits;
                $wp_user_id = wp_insert_user([
                    'user_login' => $data['national_id'],
                    'user_email' => $data['email'] ?: $data['national_id'] . '@irseg.org',
                    'display_name' => $data['name'],
                    'user_pass' => $temp_pass,
                    'role' => 'sm_syndicate_member'
                ]);
                if (is_wp_error($wp_user_id)) wp_send_json_error($wp_user_id->get_error_message());
                update_user_meta($wp_user_id, 'sm_temp_pass', $temp_pass);
                if (!empty($data['governorate'])) {
                    update_user_meta($wp_user_id, 'sm_governorate', $data['governorate']);
                }
            }

            unset($data['id']);
            $data['wp_user_id'] = $wp_user_id;

            $res = $wpdb->insert("{$wpdb->prefix}sm_members", $data);
            if ($res) {
                SM_Logger::log('استعادة بيانات', "تم استعادة العضو: " . $data['name']);
                wp_send_json_success();
            } else {
                wp_send_json_error('فشل في إدراج البيانات في قاعدة البيانات: ' . $wpdb->last_error);
            }
        } elseif ($table === 'services') {
            unset($data['id']);
            $res = $wpdb->insert("{$wpdb->prefix}sm_services", $data);
            if ($res) {
                SM_Logger::log('استعادة بيانات', "تم استعادة الخدمة: " . $data['name']);
                wp_send_json_success();
            } else {
                wp_send_json_error('فشل في إدراج البيانات في قاعدة البيانات: ' . $wpdb->last_error);
            }
        }

        wp_send_json_error('نوع الاستعادة غير مدعوم حالياً');
    }

    public function ajax_add_survey() {
        if (!current_user_can('manage_options') && !current_user_can('sm_manage_system')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');
        $id = SM_DB::add_survey(
            $_POST['title'],
            $_POST['questions'],
            $_POST['recipients'],
            get_current_user_id(),
            $_POST['specialty'] ?? '',
            $_POST['test_type'] ?? 'practice'
        );
        wp_send_json_success($id);
    }

    public function ajax_cancel_survey() {
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}sm_surveys", ['status' => 'cancelled'], ['id' => intval($_POST['id'])]);
        wp_send_json_success();
    }

    public function ajax_submit_survey_response() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_survey_action', 'nonce');
        SM_DB::save_survey_response(intval($_POST['survey_id']), get_current_user_id(), json_decode(stripslashes($_POST['responses']), true));
        wp_send_json_success();
    }

    public function ajax_get_survey_results() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        wp_send_json_success(SM_DB::get_survey_results(intval($_GET['id'])));
    }

    public function ajax_delete_log() {
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}sm_logs", ['id' => intval($_POST['log_id'])]);
        wp_send_json_success();
    }

    public function ajax_clear_all_logs() {
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_logs");
        wp_send_json_success();
    }

    public function ajax_get_user_role() {
        if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $user_id = intval($_GET['user_id']);
        $user = get_userdata($user_id);
        if ($user) {
            $role = !empty($user->roles) ? $user->roles[0] : '';
            wp_send_json_success(['role' => $role]);
        }
        wp_send_json_error('User not found');
    }

    public function ajax_update_member_account() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'sm_nonce');

        $member_id = intval($_POST['member_id']);
        $wp_user_id = intval($_POST['wp_user_id']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');

        // Update email in WP User and SM Members table
        $user_data = ['ID' => $wp_user_id, 'user_email' => $email];
        if (!empty($password)) {
            $user_data['user_pass'] = $password;
        }

        $res = wp_update_user($user_data);
        if (is_wp_error($res)) wp_send_json_error($res->get_error_message());

        // Handle role change (only for full admins)
        if (!empty($role) && (current_user_can('sm_full_access') || current_user_can('manage_options'))) {
            $user = new WP_User($wp_user_id);
            $user->set_role($role);
        }

        // Sync email to members table
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}sm_members", ['email' => $email], ['id' => $member_id]);

        SM_Logger::log('تحديث حساب عضو', "تم تحديث بيانات الحساب للعضو ID: $member_id");
        wp_send_json_success();
    }

    public function ajax_add_service() {
        if (!current_user_can('sm_manage_system')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        // Validation
        if (empty($_POST['name'])) wp_send_json_error('اسم الخدمة مطلوب');
        if (isset($_POST['fees']) && !is_numeric($_POST['fees'])) wp_send_json_error('الرسوم يجب أن تكون رقماً');

        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'category' => sanitize_text_field($_POST['category'] ?? 'عام'),
            'branch' => sanitize_text_field($_POST['branch'] ?? 'all'),
            'icon' => sanitize_text_field($_POST['icon'] ?? 'dashicons-cloud'),
            'requires_login' => isset($_POST['requires_login']) ? (int)$_POST['requires_login'] : 1,
            'description' => sanitize_textarea_field($_POST['description']),
            'fees' => floatval($_POST['fees'] ?? 0),
            'status' => in_array($_POST['status'], ['active', 'suspended']) ? $_POST['status'] : 'active',
            'required_fields' => stripslashes($_POST['required_fields'] ?? '[]'),
            'selected_profile_fields' => stripslashes($_POST['selected_profile_fields'] ?? '[]')
        ];

        error_log('Attempting to add digital service: ' . print_r($data, true));
        $res = SM_DB::add_service($data);
        if ($res) {
            wp_send_json_success();
        } else {
            global $wpdb;
            error_log('Failed to add service. DB Error: ' . $wpdb->last_error);
            wp_send_json_error('Failed to add service: ' . $wpdb->last_error);
        }
    }

    public function ajax_update_service() {
        if (!current_user_can('sm_manage_system')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');
        $id = intval($_POST['id']);

        $data = [];
        if (isset($_POST['name'])) {
            if (empty($_POST['name'])) wp_send_json_error('اسم الخدمة مطلوب');
            $data['name'] = sanitize_text_field($_POST['name']);
        }
        if (isset($_POST['category'])) $data['category'] = sanitize_text_field($_POST['category']);
        if (isset($_POST['branch'])) $data['branch'] = sanitize_text_field($_POST['branch']);
        if (isset($_POST['icon'])) $data['icon'] = sanitize_text_field($_POST['icon']);
        if (isset($_POST['requires_login'])) $data['requires_login'] = (int)$_POST['requires_login'];
        if (isset($_POST['description'])) $data['description'] = sanitize_textarea_field($_POST['description']);
        if (isset($_POST['fees'])) {
            if (!is_numeric($_POST['fees'])) wp_send_json_error('الرسوم يجب أن تكون رقماً');
            $data['fees'] = floatval($_POST['fees']);
        }
        if (isset($_POST['status'])) {
            $data['status'] = in_array($_POST['status'], ['active', 'suspended']) ? $_POST['status'] : 'active';
        }
        if (isset($_POST['required_fields'])) $data['required_fields'] = stripslashes($_POST['required_fields']);
        if (isset($_POST['selected_profile_fields'])) $data['selected_profile_fields'] = stripslashes($_POST['selected_profile_fields']);

        $res = SM_DB::update_service($id, $data);
        if ($res !== false) {
            wp_send_json_success();
        } else {
            global $wpdb;
            wp_send_json_error('Failed to update service: ' . $wpdb->last_error);
        }
    }

    public function ajax_get_services_html() {
        if (!current_user_can('sm_manage_system')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        ob_start();
        include SM_PLUGIN_DIR . 'templates/admin-services.php';
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    public function ajax_verify_document() {
        global $wpdb;
        $val = trim(sanitize_text_field($_POST['search_value'] ?? ''));
        $type = sanitize_text_field($_POST['search_type'] ?? 'all');

        if (empty($val)) wp_send_json_error('يرجى إدخال قيمة للبحث');

        $member = null;
        $results = [];

        if ($type === 'all') {
            // Intelligent Search: Try exact first, then fuzzy for name
            $member = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sm_members
                 WHERE national_id = %s
                 OR membership_number = %s
                 OR license_number = %s
                 OR facility_number = %s
                 OR name = %s
                 LIMIT 1",
                $val, $val, $val, $val, $val
            ));

            if (!$member) {
                // Fuzzy match for name if 3+ chars
                if (strlen($val) >= 3) {
                    $member = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}sm_members WHERE name LIKE %s LIMIT 1",
                        '%' . $wpdb->esc_like($val) . '%'
                    ));
                }
            }

            if (!$member) {
                $user = get_user_by('login', $val);
                if ($user) {
                    $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", $user->ID));
                }
            }
        } else {
            switch ($type) {
                case 'membership':
                    $member = SM_DB::get_member_by_membership_number($val);
                    break;
                case 'license':
                    $member = SM_DB::get_member_by_facility_number($val);
                    break;
                case 'practice':
                    $member = SM_DB::get_member_by_license_number($val);
                    break;
            }
        }

        if ($member) {
            // Membership results
            if ($type === 'all' || $type === 'membership') {
                if ($member->membership_number) {
                    $results['membership'] = [
                        'label' => 'بيانات العضوية',
                        'name' => $member->name,
                        'number' => $member->membership_number,
                        'status' => $member->membership_status,
                        'expiry' => $member->membership_expiration_date,
                        'specialization' => $member->specialization,
                        'grade' => $member->professional_grade
                    ];
                }
            }

            // License results
            if ($type === 'all' || $type === 'license') {
                if ($member->facility_number) {
                    $results['license'] = [
                        'label' => 'رخصة المنشأة',
                        'facility_name' => $member->facility_name,
                        'number' => $member->facility_number,
                        'category' => $member->facility_category,
                        'expiry' => $member->facility_license_expiration_date,
                        'address' => $member->facility_address
                    ];
                }
            }

            // Practice results
            if ($type === 'all' || $type === 'practice') {
                if ($member->license_number) {
                    $results['practice'] = [
                        'label' => 'تصريح مزاولة المهنة',
                        'name' => $member->name,
                        'number' => $member->license_number,
                        'issue_date' => $member->license_issue_date,
                        'expiry' => $member->license_expiration_date
                    ];
                }
            }
        }

        if (empty($results)) {
            wp_send_json_error('عذراً، لم يتم العثور على أي بيانات مطابقة لمدخلات البحث.');
        }

        wp_send_json_success($results);
    }

    public function ajax_delete_service() {
        if (!current_user_can('sm_manage_system')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');
        $permanent = !empty($_POST['permanent']);
        if (SM_DB::delete_service(intval($_POST['id']), $permanent)) wp_send_json_success();
        else wp_send_json_error('Failed to delete service');
    }

    public function ajax_restore_service() {
        if (!current_user_can('sm_manage_system')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');
        if (SM_DB::restore_service(intval($_POST['id']))) wp_send_json_success();
        else wp_send_json_error('Failed to restore service');
    }

    public function ajax_submit_service_request() {
        // check_ajax_referer('sm_service_action', 'nonce'); // Allow guest requests

        $service_id = intval($_POST['service_id']);
        global $wpdb;
        $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_services WHERE id = %d", $service_id));

        if (!$service) wp_send_json_error('Service not found');

        $member_id = intval($_POST['member_id'] ?? 0);
        if ($service->requires_login) {
            if (!is_user_logged_in()) wp_send_json_error('هذه الخدمة تتطلب تسجيل الدخول');
            if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');
        }

        $res = SM_DB::submit_service_request($_POST);
        if ($res) {
            SM_Logger::log('طلب خدمة رقمية', "العضو ID: $member_id طلب خدمة ID: $service_id");

            // Format tracking code: YYYYMMDD{ID}
            $tracking_code = date('Ymd') . $res;
            wp_send_json_success($tracking_code);
        } else wp_send_json_error('Failed to submit request');
    }

    public function ajax_process_service_request() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        $id = intval($_POST['id']);
        $status = sanitize_text_field($_POST['status']);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        global $wpdb;
        $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_service_requests WHERE id = %d", $id));
        if (!$req) wp_send_json_error('Request not found');

        $service = $wpdb->get_row($wpdb->prepare("SELECT fees, name FROM {$wpdb->prefix}sm_services WHERE id = %d", $req->service_id));
        $res = SM_DB::update_service_request_status($id, $status, ($status === 'approved' && $service) ? $service->fees : null, $notes);

        if ($res) {
             if ($status === 'approved') {
                 // Record in finance if fees > 0
                 if ($service && $service->fees > 0) {
                      SM_Finance::record_payment([
                          'member_id' => $req->member_id,
                          'amount' => $service->fees,
                          'payment_type' => 'other',
                          'payment_date' => current_time('Y-m-d'),
                          'details_ar' => 'رسوم خدمة: ' . $service->name,
                          'notes' => 'طلب رقم #' . $id
                      ]);
                 }

                 // Archive Issued Document in Vault
                 SM_DB::add_document([
                     'member_id' => $req->member_id,
                     'category' => 'certificates',
                     'title' => $service->name . " - طلب رقم #" . $id,
                     'file_url' => admin_url('admin-ajax.php?action=sm_print_service_request&id=' . $id),
                     'file_type' => 'application/pdf'
                 ]);
             }
             wp_send_json_success();
        } else wp_send_json_error('Failed to process request');
    }

    public function ajax_export_survey_results() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
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

    public function ajax_assign_test() {
        if (!current_user_can('sm_manage_system')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        $survey_id = intval($_POST['survey_id']);
        $user_ids = array_map('intval', (array)$_POST['user_ids']);

        if (empty($user_ids)) wp_send_json_error('يرجى اختيار مستخدم واحد على الأقل');

        foreach ($user_ids as $uid) {
            SM_DB::assign_test($survey_id, $uid);
        }

        wp_send_json_success();
    }

    public function ajax_verify_suggest() {
        global $wpdb;
        $query = sanitize_text_field($_GET['query'] ?? '');
        $type = sanitize_text_field($_GET['type'] ?? 'all');

        if (strlen($query) < 3) wp_send_json_success([]);

        $suggestions = [];
        $search = '%' . $wpdb->esc_like($query) . '%';

        if ($type === 'all') {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT name, national_id FROM {$wpdb->prefix}sm_members
                 WHERE name LIKE %s OR national_id LIKE %s LIMIT 5",
                $search, $search
            ));
            foreach ($results as $r) {
                $suggestions[] = $r->name;
                $suggestions[] = $r->national_id;
            }
        } elseif ($type === 'membership') {
            $suggestions = $wpdb->get_col($wpdb->prepare(
                "SELECT membership_number FROM {$wpdb->prefix}sm_members WHERE membership_number LIKE %s LIMIT 5",
                $search
            ));
        } elseif ($type === 'license') {
            $suggestions = $wpdb->get_col($wpdb->prepare(
                "SELECT facility_number FROM {$wpdb->prefix}sm_members WHERE facility_number LIKE %s LIMIT 5",
                $search
            ));
        } elseif ($type === 'practice') {
            $suggestions = $wpdb->get_col($wpdb->prepare(
                "SELECT license_number FROM {$wpdb->prefix}sm_members WHERE license_number LIKE %s LIMIT 5",
                $search
            ));
        }

        wp_send_json_success(array_values(array_unique(array_filter($suggestions))));
    }

    public function handle_form_submission() {
        if (isset($_POST['sm_import_members_csv'])) {
            $this->handle_member_csv_import();
        }
        if (isset($_POST['sm_import_staffs_csv'])) {
            $this->handle_staff_csv_import();
        }
        if (isset($_POST['sm_save_appearance'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
            $data = SM_Settings::get_appearance();
            foreach ($data as $k => $v) {
                if (isset($_POST[$k])) $data[$k] = sanitize_text_field($_POST[$k]);
            }
            SM_Settings::save_appearance($data);
            wp_redirect(add_query_arg('sm_tab', 'global-settings', wp_get_referer()));
            exit;
        }
        if (isset($_POST['sm_save_labels'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
            $labels = SM_Settings::get_labels();
            foreach ($labels as $k => $v) {
                if (isset($_POST[$k])) $labels[$k] = sanitize_text_field($_POST[$k]);
            }
            SM_Settings::save_labels($labels);
            wp_redirect(add_query_arg('sm_tab', 'global-settings', wp_get_referer()));
            exit;
        }

        if (isset($_POST['sm_save_settings_unified'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');

            // 1. Save Syndicate Info
            $info = SM_Settings::get_syndicate_info();
            $info['syndicate_name'] = sanitize_text_field($_POST['syndicate_name']);
            $info['syndicate_officer_name'] = sanitize_text_field($_POST['syndicate_officer_name']);
            $info['phone'] = sanitize_text_field($_POST['syndicate_phone']);
            $info['email'] = sanitize_email($_POST['syndicate_email']);
            $info['syndicate_logo'] = esc_url_raw($_POST['syndicate_logo']);
            $info['address'] = sanitize_text_field($_POST['syndicate_address']);
            $info['map_link'] = esc_url_raw($_POST['syndicate_map_link'] ?? '');
            $info['extra_details'] = sanitize_textarea_field($_POST['syndicate_extra_details'] ?? '');
            $info['authority_name'] = sanitize_text_field($_POST['authority_name'] ?? '');
            $info['authority_logo'] = esc_url_raw($_POST['authority_logo'] ?? '');

            SM_Settings::save_syndicate_info($info);

            // 2. Save Section Labels
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

        if (isset($_POST['sm_save_branches_list'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
            $lines = explode("\n", str_replace("\r", "", $_POST['sm_branches_list']));
            $branches = array();
            foreach ($lines as $line) {
                $parts = explode("|", $line);
                if (count($parts) == 2) {
                    $branches[trim($parts[0])] = trim($parts[1]);
                }
            }
            if (!empty($branches)) update_option('sm_branches', $branches);
            wp_redirect(add_query_arg(['sm_tab' => 'advanced-settings', 'sub' => 'branches', 'settings_saved' => 1], wp_get_referer()));
            exit;
        }

        if (isset($_POST['sm_save_email_settings'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
            update_option('sm_support_email', sanitize_email($_POST['sm_support_email']));
            update_option('sm_noreply_email', sanitize_email($_POST['sm_noreply_email']));
            wp_redirect(add_query_arg(['sm_tab' => 'advanced-settings', 'sub' => 'emails', 'settings_saved' => 1], wp_get_referer()));
            exit;
        }

        if (isset($_POST['sm_save_professional_options'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
            $grades_raw = explode("\n", str_replace("\r", "", $_POST['professional_grades']));
            $grades = array();
            foreach ($grades_raw as $line) {
                $parts = explode("|", $line);
                if (count($parts) == 2) {
                    $grades[trim($parts[0])] = trim($parts[1]);
                }
            }
            if (!empty($grades)) SM_Settings::save_professional_grades($grades);

            $specs_raw = explode("\n", str_replace("\r", "", $_POST['specializations']));
            $specs = array();
            foreach ($specs_raw as $line) {
                $parts = explode("|", $line);
                if (count($parts) == 2) {
                    $specs[trim($parts[0])] = trim($parts[1]);
                }
            }
            if (!empty($specs)) SM_Settings::save_specializations($specs);
            wp_redirect(add_query_arg(['sm_tab' => 'global-settings', 'sub' => 'professional', 'settings_saved' => 1], wp_get_referer()));
            exit;
        }

        if (isset($_POST['sm_save_finance_settings'])) {
            check_admin_referer('sm_admin_action', 'sm_admin_nonce');
            SM_Settings::save_finance_settings(array(
                'membership_new' => floatval($_POST['membership_new']),
                'membership_renewal' => floatval($_POST['membership_renewal']),
                'membership_penalty' => floatval($_POST['membership_penalty']),
                'license_new' => floatval($_POST['license_new']),
                'license_renewal' => floatval($_POST['license_renewal']),
                'license_penalty' => floatval($_POST['license_penalty']),
                'facility_a' => floatval($_POST['facility_a']),
                'facility_b' => floatval($_POST['facility_b']),
                'facility_c' => floatval($_POST['facility_c'])
            ));
            wp_redirect(add_query_arg(['sm_tab' => 'global-settings', 'sub' => 'finance', 'settings_saved' => 1], wp_get_referer()));
            exit;
        }
    }

    private function handle_member_csv_import() {
        if (!current_user_can('sm_manage_members')) return;
        check_admin_referer('sm_admin_action', 'sm_admin_nonce');

        if (empty($_FILES['member_csv_file']['tmp_name'])) return;

        $handle = fopen($_FILES['member_csv_file']['tmp_name'], 'r');
        if (!$handle) return;

        $results = ['total' => 0, 'success' => 0, 'warning' => 0, 'error' => 0];

        // Skip header
        fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== FALSE) {
            $results['total']++;
            if (count($data) < 2) { $results['error']++; continue; }

            $member_data = [
                'national_id' => sanitize_text_field($data[0]),
                'name' => sanitize_text_field($data[1]),
                'professional_grade' => sanitize_text_field($data[2] ?? ''),
                'specialization' => sanitize_text_field($data[3] ?? ''),
                'governorate' => sanitize_text_field($data[4] ?? ''),
                'phone' => sanitize_text_field($data[5] ?? ''),
                'email' => sanitize_email($data[6] ?? '')
            ];

            $res = SM_DB::add_member($member_data);
            if (is_wp_error($res)) {
                $results['error']++;
            } else {
                $results['success']++;
            }
        }
        fclose($handle);

        set_transient('sm_import_results_' . get_current_user_id(), $results, 3600);
        wp_redirect(add_query_arg('sm_tab', 'members', wp_get_referer()));
        exit;
    }

    private function handle_staff_csv_import() {
        if (!current_user_can('sm_manage_users')) return;
        check_admin_referer('sm_admin_action', 'sm_admin_nonce');

        if (empty($_FILES['csv_file']['tmp_name'])) return;

        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) return;

        // Skip header
        fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 4) continue;

            $username = sanitize_user($data[0]);
            $email = sanitize_email($data[1]);
            $name = sanitize_text_field($data[2]);
            $officer_id = sanitize_text_field($data[3]);
            $role_label = sanitize_text_field($data[4] ?? 'عضو نقابة');
            $phone = sanitize_text_field($data[5] ?? '');
            if (!empty($data[6])) {
                $pass = $data[6];
            } else {
                $digits = '';
                for ($i = 0; $i < 10; $i++) {
                    $digits .= mt_rand(0, 9);
                }
                $pass = 'IRS' . $digits;
            }

            $role = 'sm_syndicate_member';
            if (strpos($role_label, 'مدير') !== false) $role = 'sm_system_admin';
            elseif (strpos($role_label, 'مسؤول') !== false) $role = 'sm_syndicate_admin';

            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_email' => $email ?: $username . '@irseg.org',
                'display_name' => $name,
                'user_pass' => $pass,
                'role' => $role
            ]);

            if (!is_wp_error($user_id)) {
                update_user_meta($user_id, 'sm_temp_pass', $pass);
                update_user_meta($user_id, 'sm_syndicateMemberIdAttr', $officer_id);
                update_user_meta($user_id, 'sm_phone', $phone);
            }
        }
        fclose($handle);

        wp_redirect(add_query_arg('sm_tab', 'staff', wp_get_referer()));
        exit;
    }

    public function ajax_get_counts() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        $stats = SM_DB::get_statistics();
        wp_send_json_success([
            'pending_reports' => SM_DB::get_pending_reports_count()
        ]);
    }

    public function ajax_bulk_delete_users() {
        if (!current_user_can('sm_manage_users')) wp_send_json_error('Unauthorized');
        if (!wp_verify_nonce($_POST['nonce'], 'sm_syndicateMemberAction')) wp_send_json_error('Security check failed');

        $ids = explode(',', $_POST['user_ids']);
        foreach ($ids as $id) {
            $id = intval($id);
            if ($id === get_current_user_id()) continue;
            if (!$this->can_manage_user($id)) continue;
            wp_delete_user($id);
        }
        wp_send_json_success();
    }

    public function ajax_send_message() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_message_action', 'nonce');

        $sender_id = get_current_user_id();
        $member_id = intval($_POST['member_id'] ?? 0);

        if (!$member_id) {
            // Try to find member_id from current user if they are a member
            global $wpdb;
            $member_by_wp = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", $sender_id));
            if ($member_by_wp) $member_id = $member_by_wp->id;
        }

        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');

        $member = SM_DB::get_member_by_id($member_id);
        if (!$member) wp_send_json_error('Invalid member context');

        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $receiver_id = intval($_POST['receiver_id'] ?? 0);
        $governorate = $member->governorate;

        $file_url = null;
        if (!empty($_FILES['message_file']['name'])) {
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['message_file']['type'], $allowed_types)) {
                wp_send_json_error('نوع الملف غير مسموح به. يسمح فقط بملفات PDF والصور.');
            }

            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            $attachment_id = media_handle_upload('message_file', 0);
            if (!is_wp_error($attachment_id)) {
                $file_url = wp_get_attachment_url($attachment_id);
            }
        }

        SM_DB::send_message($sender_id, $receiver_id, $message, $member_id, $file_url, $governorate);
        wp_send_json_success();
    }

    public function ajax_get_conversation() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_message_action', 'nonce');

        $member_id = intval($_POST['member_id'] ?? 0);
        if (!$member_id) {
            $sender_id = get_current_user_id();
            global $wpdb;
            $member_by_wp = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", $sender_id));
            if ($member_by_wp) $member_id = $member_by_wp->id;
        }

        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');

        wp_send_json_success(SM_DB::get_ticket_messages($member_id));
    }

    public function ajax_get_conversations() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_message_action', 'nonce');

        $user = wp_get_current_user();
        $gov = get_user_meta($user->ID, 'sm_governorate', true);
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');

        if (!$gov && !$has_full_access) wp_send_json_error('No governorate assigned');

        if (in_array('sm_syndicate_member', (array)$user->roles)) {
             // Members see officials of their governorate
             $officials = SM_DB::get_governorate_officials($gov);
             $data = [];
             foreach($officials as $o) {
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
             // Officials see members' tickets
             // If System Admin/WP Admin, pass null to see all governorates
             $target_gov = $has_full_access ? null : $gov;
             $conversations = SM_DB::get_governorate_conversations($target_gov);
             foreach($conversations as &$c) {
                 $c['member']->avatar = $c['member']->photo_url ?: get_avatar_url($c['member']->wp_user_id ?: 0);
             }
             wp_send_json_success(['type' => 'official_view', 'conversations' => $conversations]);
        }
    }

    public function ajax_mark_read() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_message_action', 'nonce');
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}sm_messages", ['is_read' => 1], ['receiver_id' => get_current_user_id(), 'sender_id' => intval($_POST['other_user_id'])]);
        wp_send_json_success();
    }

    public function ajax_get_member_finance_html() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        $member_id = intval($_GET['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');

        $dues = SM_Finance::calculate_member_dues($member_id);
        $history = SM_Finance::get_payment_history($member_id);
        ob_start();
        include SM_PLUGIN_DIR . 'templates/modal-finance-details.php';
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

    public function ajax_print_license() {
        if (!current_user_can('sm_print_reports')) wp_die('Unauthorized');
        $member_id = intval($_GET['member_id'] ?? 0);
        if (!$this->can_access_member($member_id)) wp_die('Access denied');
        include SM_PLUGIN_DIR . 'templates/print-practice-license.php';
        exit;
    }

    public function ajax_print_facility() {
        if (!current_user_can('sm_print_reports')) wp_die('Unauthorized');
        $member_id = intval($_GET['member_id'] ?? 0);
        if (!$this->can_access_member($member_id)) wp_die('Access denied');
        include SM_PLUGIN_DIR . 'templates/print-facility-license.php';
        exit;
    }

    public function ajax_print_invoice() {
        if (!current_user_can('sm_manage_finance')) {
            // Check if member is viewing their own invoice
            $payment_id = intval($_GET['payment_id'] ?? 0);
            global $wpdb;
            $pmt = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$wpdb->prefix}sm_payments WHERE id = %d", $payment_id));
            if (!$pmt || !$this->can_access_member($pmt->member_id)) wp_die('Unauthorized');
        }
        include SM_PLUGIN_DIR . 'templates/print-invoice.php';
        exit;
    }

    public function ajax_print_service_request() {
        $id = intval($_GET['id']);
        global $wpdb;
        $req = $wpdb->get_row($wpdb->prepare("SELECT member_id, status FROM {$wpdb->prefix}sm_service_requests WHERE id = %d", $id));
        if (!$req) wp_die('Request not found');

        if (!$this->can_access_member($req->member_id)) wp_die('Unauthorized');
        if ($req->status !== 'approved' && !current_user_can('sm_manage_members')) wp_die('Access denied');

        include SM_PLUGIN_DIR . 'templates/print-service-request.php';
        exit;
    }

    public function handle_print() {
        if (!current_user_can('sm_print_reports')) wp_die('Unauthorized');

        $type = sanitize_text_field($_GET['print_type'] ?? '');
        $member_id = intval($_GET['member_id'] ?? 0);

        if ($member_id && !$this->can_access_member($member_id)) wp_die('Access denied');

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

    public function ajax_submit_update_request_ajax() {
        if (!is_user_logged_in()) wp_send_json_error('يجب تسجيل الدخول');
        check_ajax_referer('sm_update_request', 'nonce');

        $member_id = intval($_POST['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('لا تملك صلاحية تعديل هذا العضو');

        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'national_id' => sanitize_text_field($_POST['national_id']),
            'university' => sanitize_text_field($_POST['university']),
            'faculty' => sanitize_text_field($_POST['faculty']),
            'department' => sanitize_text_field($_POST['department']),
            'graduation_date' => sanitize_text_field($_POST['graduation_date']),
            'academic_degree' => sanitize_text_field($_POST['academic_degree']),
            'specialization' => sanitize_text_field($_POST['specialization']),
            'residence_governorate' => sanitize_text_field($_POST['residence_governorate']),
            'residence_city' => sanitize_text_field($_POST['residence_city']),
            'residence_street' => sanitize_textarea_field($_POST['residence_street']),
            'governorate' => sanitize_text_field($_POST['governorate']),
            'phone' => sanitize_text_field($_POST['phone']),
            'email' => sanitize_email($_POST['email']),
            'notes' => sanitize_textarea_field($_POST['notes'])
        );

        $res = SM_DB::add_update_request($member_id, $data);
        if ($res) {
            wp_send_json_success();
        } else {
            wp_send_json_error('فشل في إرسال الطلب');
        }
    }

    public function ajax_process_update_request_ajax() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_update_request', 'nonce');

        $request_id = intval($_POST['request_id']);
        $status = sanitize_text_field($_POST['status']); // 'approved' or 'rejected'

        if (SM_DB::process_update_request($request_id, $status)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('فشل في معالجة الطلب');
        }
    }

    public function ajax_submit_membership_request() {
        check_ajax_referer('sm_registration_nonce', 'nonce');

        global $wpdb;
        $nid = sanitize_text_field($_POST['national_id']);

        // Check if already exists in members or requests
        if (SM_DB::member_exists($nid)) {
            wp_send_json_error('عذراً، هذا الرقم القومي مسجل مسبقاً في النظام.');
        }

        $exists_request = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_membership_requests WHERE national_id = %s", $nid));
        if ($exists_request) {
            wp_send_json_error('عذراً، يوجد طلب عضوية قيد المراجعة بهذا الرقم القومي.');
        }

        $insert_data = [
            'national_id' => $nid,
            'name' => sanitize_text_field($_POST['name']),
            'university' => sanitize_text_field($_POST['university']),
            'faculty' => sanitize_text_field($_POST['faculty']),
            'department' => sanitize_text_field($_POST['department']),
            'graduation_date' => sanitize_text_field($_POST['graduation_date']),
            'residence_street' => sanitize_text_field($_POST['residence_street']),
            'residence_city' => sanitize_text_field($_POST['residence_city']),
            'residence_governorate' => sanitize_text_field($_POST['residence_governorate']),
            'governorate' => sanitize_text_field($_POST['governorate']),
            'phone' => sanitize_text_field($_POST['phone']),
            'email' => sanitize_email($_POST['email']),
            'payment_method' => sanitize_text_field($_POST['payment_method']),
            'payment_reference' => sanitize_text_field($_POST['payment_reference']),
            'status' => 'Payment Under Review',
            'current_stage' => 2,
            'created_at' => current_time('mysql')
        ];

        if (!empty($_FILES['payment_screenshot'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $upload = wp_handle_upload($_FILES['payment_screenshot'], ['test_form' => false]);
            if (isset($upload['url'])) {
                $insert_data['payment_screenshot_url'] = $upload['url'];
            }
        }

        $res = $wpdb->insert("{$wpdb->prefix}sm_membership_requests", $insert_data);

        if ($res) wp_send_json_success();
        else wp_send_json_error('فشل في إرسال الطلب، يرجى المحاولة لاحقاً.');
    }

    public function ajax_process_membership_request() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        $request_id = intval($_POST['request_id']);
        $status = sanitize_text_field($_POST['status']);
        $reason = sanitize_text_field($_POST['reason'] ?? '');

        global $wpdb;
        $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_membership_requests WHERE id = %d", $request_id));
        if (!$req) wp_send_json_error('Request not found');

        if ($status === 'approved') {
            $member_data = (array)$req;

            // Set Membership Validity
            $member_data['membership_start_date'] = current_time('Y-m-d');
            $member_data['membership_expiration_date'] = date('Y-12-31');
            $member_data['membership_status'] = 'Active – New Member';

            // Clean up non-member fields
            $exclude = [
                'id', 'status', 'processed_by', 'created_at', 'current_stage',
                'payment_method', 'payment_reference', 'payment_screenshot_url',
                'doc_qualification_url', 'doc_id_url', 'doc_military_url',
                'doc_criminal_url', 'doc_photo_url', 'rejection_reason', 'notes'
            ];
            foreach ($exclude as $key) unset($member_data[$key]);

            $member_id = SM_DB::add_member($member_data);
            if (is_wp_error($member_id)) wp_send_json_error($member_id->get_error_message());

            // Update photo url from request to member
            if ($req->doc_photo_url) {
                SM_DB::update_member_photo($member_id, $req->doc_photo_url);
            }

            // Move uploaded documents to Archive (Document Vault)
            $docs_to_archive = [
                'doc_qualification_url' => 'شهادة المؤهل الدراسي',
                'doc_id_url' => 'بطاقة الرقم القومي',
                'doc_military_url' => 'شهادة الخدمة العسكرية',
                'doc_criminal_url' => 'صحيفة الحالة الجنائية',
                'payment_screenshot_url' => 'إيصال سداد رسوم العضوية'
            ];
            foreach ($docs_to_archive as $field => $title) {
                if ($req->$field) {
                    SM_DB::add_document([
                        'member_id' => $member_id,
                        'category' => 'other',
                        'title' => $title,
                        'file_url' => $req->$field,
                        'file_type' => 'application/pdf'
                    ]);
                }
            }

            // Log to Finance
            SM_Finance::record_payment([
                'member_id' => $member_id,
                'amount' => 480,
                'payment_type' => 'membership_fee',
                'payment_date' => current_time('mysql'),
                'details_ar' => 'رسوم اشتراك عضوية جديدة - طلب رقم ' . $request_id,
                'notes' => 'طريقة الدفع: ' . ($req->payment_method ?: 'manual') . ' - مرجع: ' . ($req->payment_reference ?: 'N/A')
            ]);
        }

        $update_data = [
            'status' => $status,
            'processed_by' => get_current_user_id()
        ];
        if ($reason) $update_data['notes'] = $reason;

        $wpdb->update("{$wpdb->prefix}sm_membership_requests", $update_data, ['id' => $request_id]);

        SM_Logger::log('معالجة طلب عضوية', "تم {$status} طلب العضوية للرقم القومي: {$req->national_id}");
        wp_send_json_success();
    }

    public function ajax_forgot_password_otp() {
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member || !$member->wp_user_id) {
            wp_send_json_error('الرقم القومي غير مسجل في النظام');
        }

        $user = get_userdata($member->wp_user_id);
        if (!$user) wp_send_json_error('بيانات الحساب غير موجودة');

        $otp = sprintf("%06d", mt_rand(1, 999999));

        update_user_meta($user->ID, 'sm_recovery_otp', $otp);
        update_user_meta($user->ID, 'sm_recovery_otp_time', time());
        update_user_meta($user->ID, 'sm_recovery_otp_used', 0);

        $syndicate = SM_Settings::get_syndicate_info();
        $subject = "رمز استعادة كلمة المرور - " . $syndicate['syndicate_name'];
        $message = "عزيزي العضو " . $member->name . ",\n\n";
        $message .= "رمز التحقق الخاص بك هو: " . $otp . "\n";
        $message .= "هذا الرمز صالح لمدة 10 دقائق فقط ولمرة واحدة.\n\n";
        $message .= "إذا لم تطلب هذا الرمز، يرجى تجاهل هذه الرسالة.\n";

        wp_mail($member->email, $subject, $message);

        wp_send_json_success('تم إرسال رمز التحقق إلى بريدك الإلكتروني المسجل');
    }

    public function ajax_reset_password_otp() {
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $otp = sanitize_text_field($_POST['otp'] ?? '');
        $new_pass = $_POST['new_password'] ?? '';

        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member || !$member->wp_user_id) wp_send_json_error('بيانات غير صحيحة');

        $user_id = $member->wp_user_id;
        $saved_otp = get_user_meta($user_id, 'sm_recovery_otp', true);
        $otp_time = get_user_meta($user_id, 'sm_recovery_otp_time', true);
        $otp_used = get_user_meta($user_id, 'sm_recovery_otp_used', true);

        if ($otp_used || $saved_otp !== $otp || (time() - $otp_time) > 600) {
            update_user_meta($user_id, 'sm_recovery_otp_used', 1); // Mark as attempt made
            wp_send_json_error('رمز التحقق غير صحيح أو منتهي الصلاحية');
        }

        if (strlen($new_pass) < 10 || !preg_match('/^[a-zA-Z0-9]+$/', $new_pass)) {
            wp_send_json_error('كلمة المرور يجب أن تكون 10 أحرف على الأقل وتتكون من حروف وأرقام فقط بدون رموز');
        }

        wp_set_password($new_pass, $user_id);
        update_user_meta($user_id, 'sm_recovery_otp_used', 1);

        wp_send_json_success('تمت إعادة تعيين كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول');
    }

    public function ajax_activate_account_step1() {
        $national_id = sanitize_text_field($_POST['national_id'] ?? '');
        $membership_number = sanitize_text_field($_POST['membership_number'] ?? '');

        $member = SM_DB::get_member_by_national_id($national_id);
        if (!$member) wp_send_json_error('الرقم القومي غير موجود في السجلات.');

        if ($member->membership_number !== $membership_number) {
            wp_send_json_error('بيانات التحقق غير صحيحة، يرجى مراجعة رقم العضوية.');
        }

        wp_send_json_success('تم التحقق بنجاح. يرجى إكمال بيانات الحساب');
    }

    public function ajax_get_template_ajax() {
        if (!current_user_can('sm_manage_system')) wp_send_json_error('Unauthorized');
        $type = sanitize_text_field($_POST['type']);
        $template = SM_Notifications::get_template($type);
        if ($template) wp_send_json_success($template);
        else wp_send_json_error('Template not found');
    }

    public function ajax_activate_account_final() {
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

        if (!is_email($new_email)) wp_send_json_error('بريد إلكتروني غير صحيح');

        // Update member record
        SM_DB::update_member($member->id, ['email' => $new_email, 'phone' => $new_phone]);

        // Update WP User
        if ($member->wp_user_id) {
            wp_update_user([
                'ID' => $member->wp_user_id,
                'user_email' => $new_email,
                'user_pass' => $new_pass
            ]);
            update_user_meta($member->wp_user_id, 'sm_phone', $new_phone);
            delete_user_meta($member->wp_user_id, 'sm_temp_pass');
        }

        wp_send_json_success('تم تفعيل الحساب بنجاح. يمكنك الآن تسجيل الدخول');

        // Send Welcome Notification
        SM_Notifications::send_template_notification($member->id, 'welcome_activation');
    }

    public function ajax_upload_document() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_document_action', 'nonce');

        $member_id = intval($_POST['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');

        if (empty($_FILES['document_file']['name'])) wp_send_json_error('No file uploaded');

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('document_file', 0);
        if (is_wp_error($attachment_id)) wp_send_json_error($attachment_id->get_error_message());

        $file_url = wp_get_attachment_url($attachment_id);
        $file_type = get_post_mime_type($attachment_id);

        $doc_id = SM_DB::add_document([
            'member_id' => $member_id,
            'category' => sanitize_text_field($_POST['category']),
            'title' => sanitize_text_field($_POST['title']),
            'file_url' => $file_url,
            'file_type' => $file_type
        ]);

        if ($doc_id) {
            wp_send_json_success(['doc_id' => $doc_id]);
        } else {
            global $wpdb;
            wp_send_json_error('Failed to save document info: ' . $wpdb->last_error);
        }
    }

    public function ajax_get_documents() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        $member_id = intval($_GET['member_id']);
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');

        $args = [
            'category' => $_GET['category'] ?? '',
            'search' => $_GET['search'] ?? ''
        ];

        wp_send_json_success(SM_DB::get_member_documents($member_id, $args));
    }

    public function ajax_delete_document() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_document_action', 'nonce');

        $doc_id = intval($_POST['doc_id']);
        global $wpdb;
        $doc = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$wpdb->prefix}sm_documents WHERE id = %d", $doc_id));
        if (!$doc || !$this->can_access_member($doc->member_id)) wp_send_json_error('Access denied');

        if (SM_DB::delete_document($doc_id)) wp_send_json_success();
        else wp_send_json_error('Delete failed');
    }

    public function ajax_get_document_logs() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        $doc_id = intval($_GET['doc_id']);

        global $wpdb;
        $doc = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$wpdb->prefix}sm_documents WHERE id = %d", $doc_id));
        if (!$doc || !$this->can_access_member($doc->member_id)) wp_send_json_error('Access denied');

        wp_send_json_success(SM_DB::get_document_logs($doc_id));
    }

    public function ajax_log_document_view() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        $doc_id = intval($_POST['doc_id']);

        global $wpdb;
        $doc = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$wpdb->prefix}sm_documents WHERE id = %d", $doc_id));
        if (!$doc || !$this->can_access_member($doc->member_id)) wp_send_json_error('Access denied');

        SM_DB::log_document_action($doc_id, 'view');
        wp_send_json_success();
    }

    // Publishing Center
    public function ajax_get_pub_template() {
        if (!current_user_can('sm_manage_system')) wp_send_json_error('Unauthorized');
        $id = intval($_GET['id']);
        global $wpdb;
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_pub_templates WHERE id = %d", $id));
        if ($template) wp_send_json_success($template);
        else wp_send_json_error('Template not found');
    }

    public function ajax_save_pub_template() {
        if (!current_user_can('sm_manage_system')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_pub_action', 'nonce');
        $id = SM_DB::save_pub_template($_POST);
        if ($id) wp_send_json_success($id);
        else wp_send_json_error('Failed to save template');
    }

    public function ajax_save_alert() {
        if (!current_user_can('sm_manage_system')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        $data = [
            'id' => !empty($_POST['id']) ? intval($_POST['id']) : null,
            'title' => sanitize_text_field($_POST['title']),
            'message' => wp_kses_post($_POST['message']),
            'severity' => sanitize_text_field($_POST['severity']),
            'must_acknowledge' => !empty($_POST['must_acknowledge']) ? 1 : 0,
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
            'target_roles' => $_POST['target_roles'] ?? [],
            'target_ranks' => $_POST['target_ranks'] ?? [],
            'target_users' => sanitize_text_field($_POST['target_users'] ?? '')
        ];

        if (SM_DB::save_alert($data)) wp_send_json_success();
        else wp_send_json_error('Failed to save alert');
    }

    public function ajax_delete_alert() {
        if (!current_user_can('sm_manage_system')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');
        if (SM_DB::delete_alert(intval($_POST['id']))) wp_send_json_success();
        else wp_send_json_error('Failed to delete alert');
    }

    public function ajax_acknowledge_alert() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        $alert_id = intval($_POST['alert_id']);
        if (SM_DB::acknowledge_alert($alert_id, get_current_user_id())) wp_send_json_success();
        else wp_send_json_error('Failed to acknowledge alert');
    }

    public function ajax_save_branch() {
        if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');
        $res = SM_DB::save_branch($_POST);
        if ($res !== false) wp_send_json_success();
        else wp_send_json_error('Failed to save branch');
    }

    public function ajax_delete_branch() {
        if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');
        if (SM_DB::delete_branch(intval($_POST['id']))) wp_send_json_success();
        else wp_send_json_error('Delete failed');
    }

    public function ajax_export_branches() {
        if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) wp_die('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        $branches = SM_DB::get_branches_data();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=branches_export.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Slug', 'Name', 'Phone', 'Email', 'Address', 'Manager', 'Description']);
        foreach ($branches as $b) {
            fputcsv($output, [$b->id, $b->slug, $b->name, $b->phone, $b->email, $b->address, $b->manager, $b->description]);
        }
        fclose($output);
        exit;
    }

    public function ajax_export_finance_report() {
        if (!current_user_can('sm_manage_finance')) wp_die('Unauthorized');
        $type = sanitize_text_field($_GET['type']);

        global $wpdb;
        $title = "تقرير مالي";
        $data = [];

        $members = SM_DB::get_members(['limit' => -1]);

        foreach ($members as $m) {
            $dues = SM_Finance::calculate_member_dues($m->id);
            if ($type === 'overdue_membership' && $dues['membership_balance'] > 0) {
                $data[] = ['name' => $m->name, 'nid' => $m->national_id, 'amount' => $dues['membership_balance'], 'details' => 'متأخرات اشتراك'];
            } elseif ($type === 'unpaid_fines' && $dues['penalty_balance'] > 0) {
                $data[] = ['name' => $m->name, 'nid' => $m->national_id, 'amount' => $dues['penalty_balance'], 'details' => 'غرامات غير مسددة'];
            } elseif ($type === 'full_liabilities' && $dues['balance'] > 0) {
                $data[] = ['name' => $m->name, 'nid' => $m->national_id, 'amount' => $dues['balance'], 'details' => 'إجمالي المديونية'];
            }
        }

        $title_map = [
            'overdue_membership' => 'تقرير متأخرات اشتراكات العضوية',
            'unpaid_fines' => 'تقرير الغرامات المالية غير المسددة',
            'full_liabilities' => 'تقرير المديونيات المالية الشامل'
        ];
        $title = $title_map[$type] ?? $title;

        include SM_PLUGIN_DIR . 'templates/print-finance-report.php';
        exit;
    }

    public function ajax_generate_pub_doc() {
        if (!current_user_can('sm_manage_system')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_pub_action', 'nonce');

        $data = [
            'title' => sanitize_text_field($_POST['title']),
            'content' => wp_kses_post($_POST['content']),
            'member_id' => intval($_POST['member_id'] ?? 0),
            'options' => [
                'doc_type' => sanitize_text_field($_POST['doc_type'] ?? 'report'),
                'fees' => floatval($_POST['fees'] ?? 0),
                'header' => isset($_POST['header']) && $_POST['header'] === 'on',
                'footer' => isset($_POST['footer']) && $_POST['footer'] === 'on',
                'qr' => isset($_POST['qr']) && $_POST['qr'] === 'on',
                'barcode' => isset($_POST['barcode']) && $_POST['barcode'] === 'on',
                'frame_type' => sanitize_text_field($_POST['frame_type'] ?? 'none')
            ]
        ];

        $doc_id = SM_DB::generate_pub_document($data);
        if ($doc_id) {
            $format = sanitize_text_field($_POST['format'] ?? 'pdf');
            wp_send_json_success(['url' => admin_url('admin-ajax.php?action=sm_print_pub_doc&id=' . $doc_id . '&format=' . $format)]);
        } else {
            wp_send_json_error('Failed to generate document');
        }
    }

    public function ajax_print_pub_doc() {
        $id = intval($_GET['id']);
        $format = $_GET['format'] ?? 'pdf';

        global $wpdb;
        $doc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_pub_documents WHERE id = %d", $id));
        if (!$doc) wp_die('Document not found');

        // Increment download count
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sm_pub_documents SET download_count = download_count + 1 WHERE id = %d", $id));

        if ($format === 'image') {
            // Simplified image output for demo (would normally use a renderer)
            header('Content-Type: text/html; charset=UTF-8');
            echo "<html><body style='margin:0; padding:40px; background:#f0f0f0; display:flex; justify-content:center;'>";
            echo "<div id='doc-capture' style='background:white; width:800px; min-height:1000px; padding:60px; box-shadow:0 0 20px rgba(0,0,0,0.1); font-family:Arial;'>";
            echo $doc->content;
            echo "</div></body></html>";
            exit;
        }

        // PDF Output
        include SM_PLUGIN_DIR . 'templates/print-pub-document.php';
        exit;
    }

    public function ajax_save_pub_identity() {
        if (!current_user_can('sm_manage_system')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_pub_action', 'nonce');

        $syndicate = SM_Settings::get_syndicate_info();
        $syndicate['syndicate_name'] = sanitize_text_field($_POST['syndicate_name']);
        $syndicate['authority_name'] = sanitize_text_field($_POST['authority_name']);
        $syndicate['syndicate_officer_name'] = sanitize_text_field($_POST['syndicate_officer_name']);
        $syndicate['phone'] = sanitize_text_field($_POST['phone']);
        $syndicate['email'] = sanitize_email($_POST['email']);
        $syndicate['website_url'] = esc_url_raw($_POST['website_url'] ?? '');
        $syndicate['address'] = sanitize_text_field($_POST['address']);
        $syndicate['syndicate_logo'] = esc_url_raw($_POST['syndicate_logo']);
        $syndicate['authority_logo'] = esc_url_raw($_POST['authority_logo']);

        SM_Settings::save_syndicate_info($syndicate);

        wp_send_json_success();
    }

    // Ticketing System AJAX Handlers
    public function ajax_get_tickets() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_ticket_action', 'nonce');
        $args = array(
            'status' => $_GET['status'] ?? '',
            'category' => $_GET['category'] ?? '',
            'priority' => $_GET['priority'] ?? '',
            'province' => $_GET['province'] ?? '',
            'search' => $_GET['search'] ?? ''
        );
        $tickets = SM_DB::get_tickets($args);
        wp_send_json_success($tickets);
    }

    public function ajax_create_ticket() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_ticket_action', 'nonce');

        $user = wp_get_current_user();
        global $wpdb;
        $member = $wpdb->get_row($wpdb->prepare("SELECT id, governorate FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", $user->ID));

        if (!$member) wp_send_json_error('Member profile not found');

        $file_url = null;
        if (!empty($_FILES['attachment']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            $attachment_id = media_handle_upload('attachment', 0);
            if (!is_wp_error($attachment_id)) {
                $file_url = wp_get_attachment_url($attachment_id);
            }
        }

        $data = array(
            'member_id' => $member->id,
            'subject' => sanitize_text_field($_POST['subject']),
            'category' => sanitize_text_field($_POST['category']),
            'priority' => sanitize_text_field($_POST['priority'] ?? 'medium'),
            'message' => sanitize_textarea_field($_POST['message']),
            'province' => $member->governorate,
            'file_url' => $file_url
        );

        $ticket_id = SM_DB::create_ticket($data);
        if ($ticket_id) wp_send_json_success($ticket_id);
        else wp_send_json_error('Failed to create ticket');
    }

    public function ajax_get_ticket_details() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_ticket_action', 'nonce');
        $id = intval($_GET['id']);
        $ticket = SM_DB::get_ticket($id);

        if (!$ticket) wp_send_json_error('Ticket not found');

        // Check permission
        $user = wp_get_current_user();
        $is_sys_admin = in_array('sm_system_admin', $user->roles) || in_array('administrator', $user->roles);
        $is_officer = in_array('sm_syndicate_admin', $user->roles);

        if (!$is_sys_admin) {
             if ($is_officer) {
                 $gov = get_user_meta($user->ID, 'sm_governorate', true);
                 if ($gov && $ticket->province !== $gov) wp_send_json_error('Access denied');
             } else {
                 global $wpdb;
                 $member_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", $user->ID));
                 if ($ticket->member_id != $member_id) wp_send_json_error('Access denied');
             }
        }

        $thread = SM_DB::get_ticket_thread($id);
        wp_send_json_success(array('ticket' => $ticket, 'thread' => $thread));
    }

    public function ajax_add_ticket_reply() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_ticket_action', 'nonce');

        $ticket_id = intval($_POST['ticket_id']);

        $file_url = null;
        if (!empty($_FILES['attachment']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            $attachment_id = media_handle_upload('attachment', 0);
            if (!is_wp_error($attachment_id)) {
                $file_url = wp_get_attachment_url($attachment_id);
            }
        }

        $data = array(
            'ticket_id' => $ticket_id,
            'sender_id' => get_current_user_id(),
            'message' => sanitize_textarea_field($_POST['message']),
            'file_url' => $file_url
        );

        $reply_id = SM_DB::add_ticket_reply($data);
        if ($reply_id) {
            // If officer replies, set status to in-progress
            if (!in_array('sm_syndicate_member', wp_get_current_user()->roles)) {
                SM_DB::update_ticket_status($ticket_id, 'in-progress');
            }
            wp_send_json_success($reply_id);
        } else wp_send_json_error('Failed to add reply');
    }

    public function ajax_close_ticket() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_ticket_action', 'nonce');

        $id = intval($_POST['id']);
        if (SM_DB::update_ticket_status($id, 'closed')) wp_send_json_success();
        else wp_send_json_error('Failed to close ticket');
    }

    public function ajax_track_membership_request() {
        $nid = sanitize_text_field($_POST['national_id']);
        global $wpdb;
        $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_membership_requests WHERE national_id = %s", $nid));
        if (!$req) wp_send_json_error('لا يوجد طلب بهذا الرقم القومي.');

        $status_map = [
            'Pending Payment Verification' => 'قيد مراجعة الدفع',
            'Payment Approved' => 'تم قبول الدفع - بانتظار الوثائق',
            'Pending Document Verification' => 'قيد مراجعة الوثائق',
            'approved' => 'تم القبول والتحويل لعضوية مفعلة',
            'rejected' => 'تم رفض الطلب',
            'pending' => 'قيد المراجعة'
        ];

        wp_send_json_success([
            'status' => $status_map[$req->status] ?? $req->status,
            'current_stage' => $req->current_stage,
            'rejection_reason' => $req->notes ?? ''
        ]);
    }

    public function ajax_track_service_request() {
        $code = sanitize_text_field($_POST['tracking_code'] ?? '');
        if (empty($code)) wp_send_json_error('يرجى إدخال كود التتبع');

        // New format: YYYYMMDD{ID}. Extract ID from the end (all chars after index 8)
        $id = substr($code, 8);
        if (empty($id) || !is_numeric($id)) {
            // Fallback for old SR- format
            $id = str_replace('SR-', '', $code);
        }

        if (!is_numeric($id)) wp_send_json_error('كود تتبع غير صحيح');

        global $wpdb;
        $req = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, s.name as service_name, m.name as member_name, m.email as member_email, m.phone as member_phone, m.governorate as member_branch
             FROM {$wpdb->prefix}sm_service_requests r
             JOIN {$wpdb->prefix}sm_services s ON r.service_id = s.id
             LEFT JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
             WHERE r.id = %d",
            (int)$id
        ));

        if (!$req) wp_send_json_error('لم يتم العثور على طلب بهذا الكود');

        $contact_info = [
            'email' => $req->member_email ?: 'N/A',
            'phone' => $req->member_phone ?: 'N/A',
            'branch' => $req->member_branch ?: 'المركز الرئيسي'
        ];

        if ($req->member_id == 0) {
            $data = json_decode($req->request_data, true);
            $contact_info['email'] = $data['cust_email'] ?? 'N/A';
            $contact_info['phone'] = $data['cust_phone'] ?? 'N/A';
            $contact_info['branch'] = $data['cust_branch'] ?? 'طلب خارجي';
        }

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

        wp_send_json_success([
            'id' => $req->id,
            'service' => $req->service_name,
            'status' => $union_statuses[$req->status] ?? $req->status,
            'notes' => $req->admin_notes ?? '',
            'date' => date('Y-m-d', strtotime($req->created_at)),
            'member' => $req->member_name ?: 'طلب خارجي',
            'email' => $contact_info['email'],
            'phone' => $contact_info['phone'],
            'branch' => $contact_info['branch']
        ]);
    }

    public function inject_global_alerts() {
        if (!is_user_logged_in()) return;

        $user_id = get_current_user_id();
        $alerts = SM_DB::get_active_alerts_for_user($user_id);

        if (empty($alerts)) return;

        foreach ($alerts as $alert) {
            $severity_class = 'sm-alert-' . $alert->severity;
            $bg_color = '#fff';
            $border_color = '#e2e8f0';
            $text_color = '#1a202c';

            if ($alert->severity === 'warning') {
                $bg_color = '#fffaf0';
                $border_color = '#f6ad55';
            } elseif ($alert->severity === 'critical') {
                $bg_color = '#fff5f5';
                $border_color = '#feb2b2';
            }

            ?>
            <div id="sm-global-alert-<?php echo $alert->id; ?>" class="sm-alert-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter:blur(3px); z-index:99999; display:flex; align-items:center; justify-content:center; animation: smFadeIn 0.3s ease-out;">
                <div class="sm-alert-modal" style="background:<?php echo $bg_color; ?>; border:2px solid <?php echo $border_color; ?>; border-radius:15px; width:90%; max-width:500px; padding:30px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); position:relative; text-align:center; direction:rtl; font-family:'Rubik', sans-serif;">
                    <div style="font-size:40px; margin-bottom:15px;">
                        <?php
                        if ($alert->severity === 'info') echo 'ℹ️';
                        elseif ($alert->severity === 'warning') echo '⚠️';
                        elseif ($alert->severity === 'critical') echo '🚨';
                        ?>
                    </div>
                    <h2 style="margin:0 0 15px 0; color:#2d3748; font-weight:800; font-size:1.5em;"><?php echo esc_html($alert->title); ?></h2>
                    <div style="color:#4a5568; line-height:1.6; margin-bottom:25px; font-size:1.1em;"><?php echo wp_kses_post($alert->message); ?></div>
                    <div style="font-size:11px; color:#a0aec0; margin-bottom:20px;"><?php echo date_i18n('j F Y, H:i', strtotime($alert->created_at)); ?></div>

                    <button onclick="smAcknowledgeAlert(<?php echo $alert->id; ?>, <?php echo $alert->must_acknowledge ? 'true' : 'false'; ?>)" class="sm-btn" style="width:100%; height:45px; font-weight:800; background:<?php echo ($alert->severity === 'critical' ? '#e53e3e' : ($alert->severity === 'warning' ? '#dd6b20' : 'var(--sm-primary-color)')); ?>;">
                        <?php echo $alert->must_acknowledge ? 'إقرار واستمرار' : 'إغلاق'; ?>
                    </button>
                </div>
            </div>
            <?php
        }
        ?>
        <script>
        function smAcknowledgeAlert(alertId, mustAck) {
            const fd = new FormData();
            fd.append('action', 'sm_acknowledge_alert');
            fd.append('alert_id', alertId);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    document.getElementById('sm-global-alert-' + alertId).remove();
                } else if (!mustAck) {
                    document.getElementById('sm-global-alert-' + alertId).remove();
                }
            });
        }
        </script>
        <?php
    }

    public function ajax_submit_professional_request() {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_professional_action', 'nonce');

        $member_id = intval($_POST['member_id']);
        $type = sanitize_text_field($_POST['request_type']);

        // Basic verification
        if (!$this->can_access_member($member_id)) wp_send_json_error('Access denied');

        $res = SM_DB::add_professional_request($member_id, $type);
        if ($res) {
            $type_labels = [
                'permit_test' => 'طلب اختبار تصريح مزاولة',
                'permit_renewal' => 'طلب تجديد تصريح مزاولة',
                'facility_new' => 'طلب ترخيص منشأة جديدة',
                'facility_renewal' => 'طلب تجديد ترخيص منشأة'
            ];
            SM_Logger::log($type_labels[$type] ?? 'طلب مهني جديد', "العضو ID: $member_id");
            wp_send_json_success();
        } else {
            wp_send_json_error('فشل في تقديم الطلب');
        }
    }

    public function ajax_process_professional_request() {
        if (!current_user_can('sm_manage_members')) wp_send_json_error('Unauthorized');
        check_ajax_referer('sm_admin_action', 'nonce');

        $id = intval($_POST['request_id']);
        $status = sanitize_text_field($_POST['status']);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        if (SM_DB::process_professional_request($id, $status, $notes)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('فشل في معالجة الطلب');
        }
    }

    public function ajax_submit_membership_request_stage3() {
        $nid = sanitize_text_field($_POST['national_id']);
        global $wpdb;

        if (!empty($_FILES)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $urls = [];
            $mapping = [
                'doc_qualification' => 'doc_qualification_url',
                'doc_id'            => 'doc_id_url',
                'doc_military'      => 'doc_military_url',
                'doc_criminal'      => 'doc_criminal_url',
                'doc_photo'         => 'doc_photo_url'
            ];

            $update_data = [
                'status' => 'Awaiting Physical Documents',
                'current_stage' => 3
            ];

            foreach ($mapping as $form_field => $db_column) {
                if (!empty($_FILES[$form_field])) {
                    $upload = wp_handle_upload($_FILES[$form_field], ['test_form' => false]);
                    if (isset($upload['url'])) {
                        $update_data[$db_column] = $upload['url'];
                    }
                }
            }

            $wpdb->update("{$wpdb->prefix}sm_membership_requests", $update_data, ['national_id' => $nid]);
            wp_send_json_success();
        }
        wp_send_json_error('لم يتم رفع أي ملفات.');
    }
}

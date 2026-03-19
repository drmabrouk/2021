<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Admin {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function add_menu_pages() {
        add_menu_page(
            'إدارة النقابة',
            'إدارة النقابة',
            'read',
            'sm-dashboard',
            array($this, 'display_dashboard'),
            'dashicons-welcome-learn-more',
            6
        );

        add_submenu_page(
            'sm-dashboard',
            'لوحة التحكم',
            'لوحة التحكم',
            'read',
            'sm-dashboard',
            array($this, 'display_dashboard')
        );

        add_submenu_page(
            'sm-dashboard',
            'إدارة الأعضاء',
            'إدارة الأعضاء',
            'sm_manage_members',
            'sm-members',
            array($this, 'display_members')
        );

        add_submenu_page(
            'sm-dashboard',
            'أعضاء النقابة',
            'أعضاء النقابة',
            'sm_full_access',
            'sm-staff',
            array($this, 'display_staff_page')
        );

        add_submenu_page(
            'sm-dashboard',
            'إدارة الفروع',
            'إدارة الفروع',
            'sm_full_access',
            'sm-branches',
            array($this, 'display_branches_page')
        );

        add_submenu_page(
            'sm-dashboard',
            'إعدادات النظام',
            'إعدادات النظام',
            'sm_manage_system',
            'sm-settings',
            array($this, 'display_settings')
        );

        add_submenu_page(
            'sm-dashboard',
            'الإعدادات المتقدمة',
            'الإعدادات المتقدمة',
            'sm_full_access',
            'sm-advanced',
            array($this, 'display_advanced_settings')
        );
    }

    public function display_advanced_settings() {
        $_GET['sm_tab'] = 'advanced-settings';
        $this->display_settings();
    }

    public function display_dashboard() {
        $_GET['sm_tab'] = 'summary';
        $this->display_settings();
    }

    public function display_staff_page() {
        $_GET['sm_tab'] = 'advanced-settings';
        $_GET['sub'] = 'staff';
        $this->display_settings();
    }

    public function display_members() {
        $_GET['sm_tab'] = 'members';
        $this->display_settings();
    }

    public function display_branches_page() {
        $_GET['sm_tab'] = 'branches';
        $this->display_settings();
    }

    public function enqueue_styles() {
        wp_enqueue_style('google-font-rubik', 'https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;700;800;900&display=swap', array(), null);
        wp_add_inline_script('jquery', 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";', 'before');
        wp_enqueue_style($this->plugin_name, SM_PLUGIN_URL . 'assets/css/sm-admin.css', array(), $this->version, 'all');

        $app = SM_Settings::get_appearance();
        $css = "
            :root {
                --sm-primary-color: {$app['primary_color']};
                --sm-secondary-color: {$app['secondary_color']};
                --sm-accent-color: {$app['accent_color']};
                --sm-dark-color: {$app['dark_color']};
                --sm-radius: {$app['border_radius']};
            }
            .sm-content-wrapper, .sm-admin-dashboard, .sm-container,
            .sm-content-wrapper *:not(.dashicons), .sm-admin-dashboard *:not(.dashicons), .sm-container *:not(.dashicons) {
                font-family: 'Rubik', sans-serif !important;
            }
            .sm-content-wrapper { font-size: {$app['font_size']}; }
        ";
        wp_add_inline_style($this->plugin_name, $css);
    }

    public function display_settings() {
        $this->handle_admin_submissions();

        if (isset($_GET['settings_saved'])) {
            echo '<div class="updated notice is-dismissible"><p>تم حفظ الإعدادات بنجاح.</p></div>';
        }

        $stats = SM_DB::get_statistics();
        $members = SM_DB::get_members();

        include SM_PLUGIN_DIR . 'templates/public-admin-panel.php';
    }

    private function handle_admin_submissions() {
        if (!isset($_POST['sm_admin_action']) &&
            !isset($_POST['sm_save_settings_unified']) &&
            !isset($_POST['sm_save_appearance']) &&
            !isset($_POST['sm_save_finance_settings']) &&
            !isset($_POST['sm_save_professional_options']) &&
            !isset($_POST['sm_save_academic_options'])) {
            return;
        }

        check_admin_referer('sm_admin_action', 'sm_admin_nonce');

        if (isset($_POST['sm_save_settings_unified'])) {
            $info = SM_Settings::get_syndicate_info();
            $fields = [
                'syndicate_name' => 'syndicate_name',
                'syndicate_officer_name' => 'syndicate_officer_name',
                'syndicate_phone' => 'phone',
                'syndicate_email' => 'email',
                'syndicate_logo' => 'syndicate_logo',
                'syndicate_address' => 'address',
                'syndicate_map_link' => 'map_link',
                'syndicate_extra_details' => 'extra_details',
                'authority_name' => 'authority_name',
                'authority_logo' => 'authority_logo'
            ];

            foreach ($fields as $post_key => $info_key) {
                if (isset($_POST[$post_key])) {
                    $info[$info_key] = sanitize_text_field($_POST[$post_key]);
                }
            }
            SM_Settings::save_syndicate_info($info);

            $labels = SM_Settings::get_labels();
            foreach ($labels as $key => $val) {
                if (isset($_POST[$key])) {
                    $labels[$key] = sanitize_text_field($_POST[$key]);
                }
            }
            SM_Settings::save_labels($labels);

            wp_redirect(add_query_arg(['sm_tab' => 'global-settings', 'sub' => 'init', 'settings_saved' => 1], wp_get_referer()));
            exit;
        }

        if (isset($_POST['sm_save_appearance'])) {
            SM_Settings::save_appearance([
                'primary_color' => sanitize_hex_color($_POST['primary_color']),
                'secondary_color' => sanitize_hex_color($_POST['secondary_color']),
                'accent_color' => sanitize_hex_color($_POST['accent_color']),
                'dark_color' => sanitize_hex_color($_POST['dark_color']),
                'font_size' => sanitize_text_field($_POST['font_size']),
                'border_radius' => sanitize_text_field($_POST['border_radius']),
                'table_style' => sanitize_text_field($_POST['table_style']),
                'button_style' => sanitize_text_field($_POST['button_style'])
            ]);
            wp_redirect(add_query_arg(['sm_tab' => 'global-settings', 'sub' => 'design', 'settings_saved' => 1], wp_get_referer()));
            exit;
        }

        if (isset($_POST['sm_save_finance_settings'])) {
            SM_Settings::save_finance_settings([
                'membership_new' => floatval($_POST['membership_new']),
                'membership_renewal' => floatval($_POST['membership_renewal']),
                'membership_penalty' => floatval($_POST['membership_penalty']),
                'license_new' => floatval($_POST['license_new']),
                'license_renewal' => floatval($_POST['license_renewal']),
                'license_penalty' => floatval($_POST['license_penalty']),
                'facility_a' => floatval($_POST['facility_a']),
                'facility_b' => floatval($_POST['facility_b']),
                'facility_c' => floatval($_POST['facility_c'])
            ]);
            wp_redirect(add_query_arg(['sm_tab' => 'global-settings', 'sub' => 'finance', 'settings_saved' => 1], wp_get_referer()));
            exit;
        }

        if (isset($_POST['sm_save_academic_options'])) {
            $fields = [
                'universities' => 'save_universities',
                'faculties' => 'save_faculties',
                'departments' => 'save_departments',
                'specializations' => 'save_specializations'
            ];

            foreach ($fields as $post_key => $method) {
                $raw = explode("\n", str_replace("\r", "", $_POST[$post_key] ?? ''));
                $data = array();
                foreach ($raw as $line) {
                    $parts = explode("|", $line);
                    if (count($parts) == 2) {
                        $data[trim($parts[0])] = trim($parts[1]);
                    }
                }
                if (!empty($data)) {
                    SM_Settings::$method($data);
                }
            }
            wp_redirect(add_query_arg(['sm_tab' => 'global-settings', 'sub' => 'academic', 'settings_saved' => 1], wp_get_referer()));
            exit;
        }
    }
}

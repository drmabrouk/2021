<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_License_Manager {
    public static function ajax_update_license() {
        if (!current_user_can('sm_manage_licenses')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_add_member', 'nonce');
        $mid = intval($_POST['member_id']);
        if (!SM_Member_Manager::can_access_member($mid)) {
            wp_send_json_error('Access denied');
        }
        SM_DB::update_member($mid, [
            'license_number' => sanitize_text_field($_POST['license_number']),
            'license_issue_date' => sanitize_text_field($_POST['license_issue_date']),
            'license_expiration_date' => sanitize_text_field($_POST['license_expiration_date'])
        ]);
        SM_DB::add_document([
            'member_id' => $mid,
            'category' => 'licenses',
            'title' => "تصريح مزاولة مهنة رقم " . $_POST['license_number'],
            'file_url' => admin_url('admin-ajax.php?action=sm_print_license&member_id=' . $mid),
            'file_type' => 'application/pdf'
        ]);
        SM_Logger::log('تحديث ترخيص مزاولة', "العضو ID: $mid");
        wp_send_json_success();
    }

    public static function ajax_update_facility() {
        if (!current_user_can('sm_manage_licenses')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_add_member', 'nonce');
        $mid = intval($_POST['member_id']);
        if (!SM_Member_Manager::can_access_member($mid)) {
            wp_send_json_error('Access denied');
        }
        SM_DB::update_member($mid, [
            'facility_name' => sanitize_text_field($_POST['facility_name']),
            'facility_number' => sanitize_text_field($_POST['facility_number']),
            'facility_category' => sanitize_text_field($_POST['facility_category']),
            'facility_license_issue_date' => sanitize_text_field($_POST['facility_license_issue_date']),
            'facility_license_expiration_date' => sanitize_text_field($_POST['facility_license_expiration_date']),
            'facility_address' => sanitize_textarea_field($_POST['facility_address'])
        ]);
        SM_DB::add_document([
            'member_id' => $mid,
            'category' => 'licenses',
            'title' => "ترخيص منشأة: " . $_POST['facility_name'],
            'file_url' => admin_url('admin-ajax.php?action=sm_print_facility&member_id=' . $mid),
            'file_type' => 'application/pdf'
        ]);
        SM_Logger::log('تحديث منشأة', "العضو ID: $mid");
        wp_send_json_success();
    }

    public static function ajax_verify_document() {
        global $wpdb;
        $val = trim(sanitize_text_field($_POST['search_value'] ?? ''));
        $type = sanitize_text_field($_POST['search_type'] ?? 'all');
        if (empty($val)) {
            wp_send_json_error('يرجى إدخال قيمة للبحث');
        }

        $member = null;
        $results = [];
        if ($type === 'all') {
            $member = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sm_members
                 WHERE national_id = %s OR membership_number = %s OR license_number = %s OR facility_number = %s OR name = %s
                 LIMIT 1",
                $val, $val, $val, $val, $val
            ));
            if (!$member && strlen($val) >= 3) {
                $member = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}sm_members WHERE name LIKE %s LIMIT 1",
                    '%' . $wpdb->esc_like($val) . '%'
                ));
            }
        } else {
            switch ($type) {
                case 'membership': $member = SM_DB::get_member_by_membership_number($val); break;
                case 'license': $member = SM_DB::get_member_by_facility_number($val); break;
                case 'practice': $member = SM_DB::get_member_by_license_number($val); break;
            }
        }

        if ($member) {
            if ($member->membership_number) {
                $results['membership'] = [
                    'label' => 'بيانات العضوية',
                    'name' => $member->name,
                    'number' => $member->membership_number,
                    'status' => $member->membership_status,
                    'expiry' => $member->membership_expiration_date
                ];
            }
            if ($member->facility_number) {
                $results['license'] = [
                    'label' => 'رخصة المنشأة',
                    'facility_name' => $member->facility_name,
                    'number' => $member->facility_number,
                    'category' => $member->facility_category,
                    'expiry' => $member->facility_license_expiration_date
                ];
            }
            if ($member->license_number) {
                $results['practice'] = [
                    'label' => 'تصريح مزاولة المهنة',
                    'name' => $member->name,
                    'number' => $member->license_number,
                    'expiry' => $member->license_expiration_date
                ];
            }
        }

        if (empty($results)) {
            wp_send_json_error('عذراً، لم يتم العثور على بيانات.');
        }
        wp_send_json_success($results);
    }

    public static function ajax_print_license() {
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

    public static function ajax_print_facility() {
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
}

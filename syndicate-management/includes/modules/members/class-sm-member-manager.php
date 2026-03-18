<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Member_Manager {
    public static function ajax_get_member() {
        if (!current_user_can('sm_manage_members')) {
            wp_send_json_error('Unauthorized');
        }
        $nid = sanitize_text_field($_POST['national_id'] ?? '');
        $member = SM_DB::get_member_by_national_id($nid);
        if ($member) {
            if (!self::can_access_member($member->id)) {
                wp_send_json_error('Access denied');
            }
            wp_send_json_success($member);
        } else {
            wp_send_json_error('Member not found');
        }
    }

    public static function ajax_search_members() {
        if (!current_user_can('sm_manage_members')) {
            wp_send_json_error('Unauthorized');
        }
        $query = sanitize_text_field($_POST['query']);
        wp_send_json_success(SM_DB::get_members(['search' => $query]));
    }

    public static function ajax_add_member() {
        if (!current_user_can('sm_manage_members')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_add_member', 'sm_nonce');
        $res = SM_DB::add_member($_POST);
        if (is_wp_error($res)) {
            wp_send_json_error($res->get_error_message());
        } else {
            wp_send_json_success($res);
        }
    }

    public static function ajax_update_member() {
        if (!current_user_can('sm_manage_members')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_add_member', 'sm_nonce');
        $id = intval($_POST['member_id']);
        if (!self::can_access_member($id)) {
            wp_send_json_error('Access denied');
        }
        SM_DB::update_member($id, $_POST);
        wp_send_json_success('Updated');
    }

    public static function ajax_delete_member() {
        if (!current_user_can('sm_manage_members')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_delete_member', 'nonce');
        $id = intval($_POST['member_id']);
        if (!self::can_access_member($id)) {
            wp_send_json_error('Access denied');
        }
        SM_DB::delete_member($id);
        wp_send_json_success('Deleted');
    }

    public static function ajax_update_member_account() {
        if (!current_user_can('sm_manage_members')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        $mid = intval($_POST['member_id']);
        $uid = intval($_POST['wp_user_id']);
        $email = sanitize_email($_POST['email']);
        $pass = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        if (!self::can_access_member($mid)) {
            wp_send_json_error('Access denied');
        }

        $data = ['ID' => $uid, 'user_email' => $email];
        if (!empty($pass)) { $data['user_pass'] = $pass; }

        $res = wp_update_user($data);
        if (is_wp_error($res)) {
            wp_send_json_error($res->get_error_message());
        }

        if (!empty($role) && (current_user_can('sm_full_access') || current_user_can('manage_options'))) {
            $u = new WP_User($uid);
            $u->set_role($role);
        }

        global $wpdb;
        $wpdb->update("{$wpdb->prefix}sm_members", ['email' => $email], ['id' => $mid]);

        SM_Logger::log('تحديث حساب عضو', "تم تحديث بيانات الحساب للعضو ID: $mid");
        wp_send_json_success();
    }

    public static function can_access_member($member_id) {
        if (current_user_can('sm_full_access') || current_user_can('manage_options')) {
            return true;
        }
        $member = SM_DB::get_member_by_id($member_id);
        if (!$member) {
            return false;
        }
        $user = wp_get_current_user();
        if (in_array('sm_syndicate_member', (array)$user->roles) && $member->wp_user_id == $user->ID) {
            return true;
        }
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);
        if (in_array('sm_syndicate_admin', (array)$user->roles) || in_array('sm_syndicate_member', (array)$user->roles)) {
            if ($my_gov && $member->governorate !== $my_gov) {
                return false;
            }
            return true;
        }
        return false;
    }

    public static function ajax_update_member_photo() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_photo_action', 'sm_photo_nonce');
        $mid = intval($_POST['member_id']);
        if (!self::can_access_member($mid)) {
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

    public static function ajax_add_staff() {
        if (!current_user_can('sm_manage_users') && !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        if (!wp_verify_nonce($_POST['sm_nonce'], 'sm_syndicateMemberAction')) {
            wp_send_json_error('Security check failed');
        }
        $user_login = sanitize_user($_POST['user_login']);
        $email = sanitize_email($_POST['user_email']);
        $display_name = sanitize_text_field($_POST['display_name']);
        $role = sanitize_text_field($_POST['role']);

        if (username_exists($user_login) || email_exists($email)) {
            wp_send_json_error('User or Email already exists');
        }
        $pass = !empty($_POST['user_pass']) ? $_POST['user_pass'] : 'IRS' . mt_rand(1000000000, 9999999999);
        $uid = wp_insert_user([
            'user_login' => $user_login,
            'user_email' => $email,
            'display_name' => $display_name,
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
        SM_Logger::log('إضافة مستخدم', "الاسم: $display_name الدور: $role");
        wp_send_json_success($uid);
    }

    public static function ajax_update_staff() {
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

    public static function ajax_delete_staff() {
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

    public static function ajax_bulk_delete_users() {
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

    public static function ajax_submit_update_request_ajax() {
        if (!is_user_logged_in()) {
            wp_send_json_error('يجب تسجيل الدخول');
        }
        check_ajax_referer('sm_update_request', 'nonce');
        $mid = intval($_POST['member_id']);
        if (!self::can_access_member($mid)) {
            wp_send_json_error('Access denied');
        }
        if (SM_DB::add_update_request($mid, $_POST)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed');
        }
    }

    public static function ajax_process_update_request_ajax() {
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

    public static function ajax_submit_membership_request_stage3() {
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

    public static function ajax_process_membership_request() {
        if (!current_user_can('sm_manage_members')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');

        $rid = intval($_POST['request_id']);
        $status = sanitize_text_field($_POST['status']);
        $reason = sanitize_text_field($_POST['reason'] ?? '');

        global $wpdb;
        $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_membership_requests WHERE id = %d", $rid));
        if (!$req) {
            wp_send_json_error('Request not found');
        }

        if ($status === 'approved') {
            $data = (array)$req;
            $data['membership_start_date'] = current_time('Y-m-d');
            $data['membership_expiration_date'] = date('Y-12-31');
            $data['membership_status'] = 'Active – New Member';

            $exclude = [
                'id', 'status', 'processed_by', 'created_at', 'current_stage',
                'payment_method', 'payment_reference', 'payment_screenshot_url',
                'doc_qualification_url', 'doc_id_url', 'doc_military_url',
                'doc_criminal_url', 'doc_photo_url', 'rejection_reason', 'notes'
            ];
            foreach ($exclude as $key) {
                unset($data[$key]);
            }

            $mid = SM_DB::add_member($data);
            if (is_wp_error($mid)) {
                wp_send_json_error($mid->get_error_message());
            }

            if ($req->doc_photo_url) {
                SM_DB::update_member_photo($mid, $req->doc_photo_url);
            }

            $docs = [
                'doc_qualification_url' => 'شهادة المؤهل الدراسي',
                'doc_id_url' => 'بطاقة الرقم القومي',
                'doc_military_url' => 'شهادة الخدمة العسكرية',
                'doc_criminal_url' => 'صحيفة الحالة الجنائية',
                'payment_screenshot_url' => 'إيصال سداد رسوم العضوية'
            ];
            foreach ($docs as $f => $t) {
                if ($req->$f) {
                    SM_DB::add_document([
                        'member_id' => $mid,
                        'category' => 'other',
                        'title' => $t,
                        'file_url' => $req->$f,
                        'file_type' => 'application/pdf'
                    ]);
                }
            }

            SM_Finance::record_payment([
                'member_id' => $mid,
                'amount' => 480,
                'payment_type' => 'membership_fee',
                'payment_date' => current_time('mysql'),
                'details_ar' => 'رسوم اشتراك عضوية جديدة - طلب رقم ' . $rid,
                'notes' => 'طريقة الدفع: ' . ($req->payment_method ?: 'manual')
            ]);
        }

        $upd = [
            'status' => $status,
            'processed_by' => get_current_user_id()
        ];
        if ($reason) {
            $upd['notes'] = $reason;
        }

        $wpdb->update("{$wpdb->prefix}sm_membership_requests", $upd, ['id' => $rid]);

        SM_Logger::log('معالجة طلب عضوية', "تم {$status} طلب العضوية للرقم القومي: {$req->national_id}");
        wp_send_json_success();
    }

    public static function ajax_upload_document() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_document_action', 'nonce');
        $mid = intval($_POST['member_id']);
        if (!self::can_access_member($mid)) {
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

    public static function ajax_get_documents() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        $mid = intval($_GET['member_id']);
        if (!self::can_access_member($mid)) {
            wp_send_json_error('Access denied');
        }
        wp_send_json_success(SM_DB::get_member_documents($mid, $_GET));
    }

    public static function ajax_delete_document() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_document_action', 'nonce');
        global $wpdb;
        $doc = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$wpdb->prefix}sm_documents WHERE id = %d", intval($_POST['doc_id'])));
        if (!$doc || !self::can_access_member($doc->member_id)) {
            wp_send_json_error('Access denied');
        }
        if (SM_DB::delete_document(intval($_POST['doc_id']))) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed');
        }
    }

    public static function ajax_get_document_logs() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        global $wpdb;
        $doc = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$wpdb->prefix}sm_documents WHERE id = %d", intval($_GET['doc_id'])));
        if (!$doc || !self::can_access_member($doc->member_id)) {
            wp_send_json_error('Access denied');
        }
        wp_send_json_success(SM_DB::get_document_logs(intval($_GET['doc_id'])));
    }

    public static function ajax_log_document_view() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        global $wpdb;
        $doc = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$wpdb->prefix}sm_documents WHERE id = %d", intval($_POST['doc_id'])));
        if (!$doc || !self::can_access_member($doc->member_id)) {
            wp_send_json_error('Access denied');
        }
        SM_DB::log_document_action(intval($_POST['doc_id']), 'view');
        wp_send_json_success();
    }

    public static function handle_print() {
        if (!current_user_can('sm_print_reports')) {
            wp_die('Unauthorized');
        }
        $type = sanitize_text_field($_GET['print_type'] ?? '');
        $mid = intval($_GET['member_id'] ?? 0);
        if ($mid && !self::can_access_member($mid)) {
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
}

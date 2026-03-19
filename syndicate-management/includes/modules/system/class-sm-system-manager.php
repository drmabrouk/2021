<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_System_Manager {
    public static function ajax_save_branch() {
        if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        if (SM_DB::save_branch($_POST) !== false) {
            SM_Logger::log('حفظ بيانات فرع', "تم حفظ بيانات الفرع: " . sanitize_text_field($_POST['name'] ?? ''));
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to save branch');
        }
    }

    public static function ajax_delete_branch() {
        if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        $id = intval($_POST['id']);
        if (SM_DB::delete_branch($id)) {
            SM_Logger::log('حذف فرع', "تم حذف الفرع رقم #$id");
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to delete branch');
        }
    }

    public static function ajax_save_alert() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
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

        if (SM_DB::save_alert($data)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to save alert');
        }
    }

    public static function ajax_reset_system() {
        if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');

        $pass = $_POST['admin_password'] ?? '';
        $user = wp_get_current_user();
        if (!wp_check_password($pass, $user->user_pass, $user->ID)) {
            wp_send_json_error('كلمة المرور غير صحيحة.');
        }

        global $wpdb;
        $tables = ['sm_members', 'sm_payments', 'sm_logs', 'sm_messages', 'sm_surveys', 'sm_survey_responses', 'sm_update_requests'];
        $uids = $wpdb->get_col("SELECT wp_user_id FROM {$wpdb->prefix}sm_members WHERE wp_user_id IS NOT NULL");

        if (!empty($uids)) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            foreach ($uids as $uid) {
                wp_delete_user($uid);
            }
        }

        foreach ($tables as $t) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}$t");
        }
        delete_option('sm_invoice_sequence_' . date('Y'));

        SM_Logger::log('إعادة تهيئة النظام', "تم مسح كافة البيانات وتصفير النظام بالكامل");
        wp_send_json_success();
    }

    public static function ajax_rollback_log() {
        if (!current_user_can('manage_options') && !current_user_can('sm_full_access')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');

        $lid = intval($_POST['log_id']);
        global $wpdb;
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_logs WHERE id = %d", $lid));

        if (!$log || strpos($log->details, 'ROLLBACK_DATA:') !== 0) {
            wp_send_json_error('لا توجد بيانات استعادة');
        }

        $info = json_decode(str_replace('ROLLBACK_DATA:', '', $log->details), true);
        if (!$info || !isset($info['table'])) {
            wp_send_json_error('تنسيق غير صحيح');
        }

        $table = $info['table'];
        $data = $info['data'];

        if ($table === 'members') {
            $uid = $data['wp_user_id'] ?? null;
            if (!empty($data['national_id']) && username_exists($data['national_id'])) {
                wp_send_json_error('اسم المستخدم موجود بالفعل');
            }

            if ($uid && !get_userdata($uid)) {
                $digits = '';
                for ($i = 0; $i < 10; $i++) {
                    $digits .= mt_rand(0, 9);
                }
                $tp = 'IRS' . $digits;
                $uid = wp_insert_user([
                    'user_login' => $data['national_id'],
                    'user_email' => $data['email'] ?: $data['national_id'] . '@irseg.org',
                    'display_name' => $data['name'],
                    'user_pass' => $tp,
                    'role' => 'sm_syndicate_member'
                ]);
                if (is_wp_error($uid)) {
                    wp_send_json_error($uid->get_error_message());
                }
                update_user_meta($uid, 'sm_temp_pass', $tp);
                if (!empty($data['governorate'])) {
                    update_user_meta($uid, 'sm_governorate', $data['governorate']);
                }
            }

            unset($data['id']);
            $data['wp_user_id'] = $uid;
            if ($wpdb->insert("{$wpdb->prefix}sm_members", $data)) {
                SM_Logger::log('استعادة بيانات', "تم استعادة العضو: " . $data['name']);
                wp_send_json_success();
            } else {
                wp_send_json_error('فشل في إدراج البيانات: ' . $wpdb->last_error);
            }
        } elseif ($table === 'services') {
            unset($data['id']);
            if ($wpdb->insert("{$wpdb->prefix}sm_services", $data)) {
                SM_Logger::log('استعادة بيانات', "تم استعادة الخدمة: " . $data['name']);
                wp_send_json_success();
            } else {
                wp_send_json_error('فشل في إدراج البيانات: ' . $wpdb->last_error);
            }
        }
        wp_send_json_error('نوع الاستعادة غير مدعوم');
    }

    public static function ajax_get_counts() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        wp_send_json_success(['pending_reports' => SM_DB::get_pending_reports_count()]);
    }

    public static function ajax_delete_gov_data() {
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

    public static function ajax_merge_gov_data() {
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

    public static function ajax_delete_log() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}sm_logs", ['id' => intval($_POST['log_id'])]);
        wp_send_json_success();
    }

    public static function ajax_clear_all_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sm_logs");
        wp_send_json_success();
    }

    public static function ajax_get_pub_template() {
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

    public static function ajax_generate_pub_doc() {
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

    public static function ajax_save_pub_identity() {
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

    public static function ajax_save_pub_template() {
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

    public static function ajax_export_branches() {
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
}

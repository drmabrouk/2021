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
}

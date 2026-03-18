<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Education_Manager {
    public static function ajax_add_survey() {
        if (!current_user_can('manage_options') && !current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
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

    public static function ajax_assign_test() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');

        $sid = intval($_POST['survey_id']);
        $uids = array_map('intval', (array)$_POST['user_ids']);

        if (empty($uids)) {
            wp_send_json_error('يرجى اختيار مستخدم واحد على الأقل');
        }

        foreach ($uids as $uid) {
            SM_DB::assign_test($sid, $uid);
        }
        wp_send_json_success();
    }

    public static function ajax_submit_survey_response() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_survey_action', 'nonce');

        SM_DB::save_survey_response(
            intval($_POST['survey_id']),
            get_current_user_id(),
            json_decode(stripslashes($_POST['responses']), true)
        );
        wp_send_json_success();
    }
}

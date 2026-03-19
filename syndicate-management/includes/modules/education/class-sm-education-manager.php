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

        $id = SM_DB_Education::add_survey($_POST);
        if ($id) {
            wp_send_json_success($id);
        } else {
            wp_send_json_error('Failed to create test');
        }
    }

    public static function ajax_update_survey() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');

        $id = intval($_POST['id']);
        if (SM_DB_Education::update_survey($id, $_POST)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to update test');
        }
    }

    public static function ajax_add_test_question() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');

        $id = SM_DB_Education::add_question($_POST);
        if ($id) {
            wp_send_json_success($id);
        } else {
            wp_send_json_error('Failed to add question');
        }
    }

    public static function ajax_delete_test_question() {
        if (!current_user_can('sm_manage_system')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');

        $id = intval($_POST['id']);
        if (SM_DB_Education::delete_question($id)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to delete question');
        }
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

        $sid = intval($_POST['survey_id']);
        $user_id = get_current_user_id();
        $responses = json_decode(stripslashes($_POST['responses'] ?? '[]'), true);
        $questions = SM_DB_Education::get_test_questions($sid);
        $survey = SM_DB_Education::get_survey($sid);

        if (!$survey) {
            wp_send_json_error('Test not found');
        }

        // Security: Check attempt limits
        $attempts_made = SM_DB_Education::get_user_attempts_count($sid, $user_id);
        if ($attempts_made >= $survey->max_attempts) {
            wp_send_json_error('لقد استنفدت كافة المحاولات المتاحة لهذا الاختبار.');
        }

        $score = 0;
        $total_points = 0;

        if (!empty($questions)) {
            foreach ($questions as $q) {
                $total_points += $q->points;
                $user_ans = $responses[$q->id] ?? '';
                if (trim((string)$user_ans) === trim((string)$q->correct_answer)) {
                    $score += $q->points;
                }
            }
        }

        $percent = $total_points > 0 ? ($score / $total_points) * 100 : 0;
        $passed = ($percent >= $survey->pass_score);

        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}sm_survey_responses", array(
            'survey_id' => $sid,
            'user_id' => get_current_user_id(),
            'responses' => json_encode($responses),
            'score' => $percent,
            'status' => $passed ? 'passed' : 'failed',
            'created_at' => current_time('mysql')
        ));

        // Notify member of result
        $user = wp_get_current_user();
        $msg = "لقد أكملت اختبار: {$survey->title}\nالنتيجة: " . round($percent) . "%\nالحالة: " . ($passed ? 'ناجح ✅' : 'لم تجتز ❌');

        SM_DB_Communications::send_message(
            0, // System
            $user->ID,
            $msg,
            null,
            null,
            get_user_meta($user->ID, 'sm_governorate', true)
        );

        wp_send_json_success([
            'score' => $percent,
            'passed' => $passed
        ]);
    }

    public static function ajax_cancel_survey() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}sm_surveys", ['status' => 'cancelled'], ['id' => intval($_POST['id'])]);
        wp_send_json_success();
    }

    public static function ajax_get_survey_results() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        wp_send_json_success(SM_DB::get_survey_results(intval($_GET['id'])));
    }

    public static function ajax_export_survey_results() {
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
}

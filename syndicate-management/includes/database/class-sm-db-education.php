<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_DB_Education {
    public static function add_survey($title, $questions, $recipients, $user_id, $specialty = '', $test_type = 'practice') {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}sm_surveys", array(
            'title' => $title,
            'questions' => json_encode($questions),
            'recipients' => $recipients,
            'specialty' => sanitize_text_field($specialty),
            'test_type' => sanitize_text_field($test_type),
            'status' => 'active',
            'created_by' => $user_id,
            'created_at' => current_time('mysql')
        ));
        return $wpdb->insert_id;
    }

    public static function get_surveys($user_id, $role, $specialty = '') {
        global $wpdb;
        $roles = [$role, 'all'];
        if ($role === 'sm_syndicate_member') $roles[] = 'sm_member';

        $placeholders = implode(',', array_fill(0, count($roles), '%s'));

        // Get surveys targeted by role/specialty OR specifically assigned to this user
        $query = "SELECT s.* FROM {$wpdb->prefix}sm_surveys s
                  LEFT JOIN {$wpdb->prefix}sm_test_assignments a ON s.id = a.test_id AND a.user_id = %d
                  WHERE s.status = 'active'
                  AND (
                      s.recipients IN ($placeholders)
                      OR a.id IS NOT NULL
                  )";

        $params = array_merge([$user_id], $roles);

        if (!empty($specialty)) {
            $query .= " AND (s.specialty = %s OR s.specialty = '' OR a.id IS NOT NULL)";
            $params[] = $specialty;
        }

        $query .= " ORDER BY s.created_at DESC";
        return $wpdb->get_results($wpdb->prepare($query, ...$params));
    }

    public static function save_survey_response($survey_id, $user_id, $responses) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_survey_responses", array(
            'survey_id' => $survey_id,
            'user_id' => $user_id,
            'responses' => json_encode($responses),
            'created_at' => current_time('mysql')
        ));
    }

    public static function get_survey($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_surveys WHERE id = %d", $id));
    }

    public static function get_survey_results($survey_id) {
        global $wpdb;
        $survey = self::get_survey($survey_id);
        if (!$survey) return array();

        $user = wp_get_current_user();
        $is_officer = in_array('sm_syndicate_admin', (array)$user->roles) || in_array('sm_syndicate_member', (array)$user->roles);
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $where = $wpdb->prepare("survey_id = %d", $survey_id);
        if ($is_officer && !$has_full_access && $my_gov) {
            $where .= $wpdb->prepare(" AND (
                EXISTS (SELECT 1 FROM {$wpdb->prefix}usermeta um WHERE um.user_id = user_id AND um.meta_key = 'sm_governorate' AND um.meta_value = %s)
                OR EXISTS (SELECT 1 FROM {$wpdb->prefix}sm_members m WHERE m.wp_user_id = user_id AND m.governorate = %s)
            )", $my_gov, $my_gov);
        }

        $questions = json_decode($survey->questions, true);
        $responses = $wpdb->get_results("SELECT responses FROM {$wpdb->prefix}sm_survey_responses WHERE $where");

        $results = array();
        foreach ($questions as $index => $q) {
            $results[$index] = array('question' => $q, 'answers' => array());
            foreach ($responses as $r) {
                $res_data = json_decode($r->responses, true);
                $ans = $res_data[$index] ?? 'No Answer';
                $results[$index]['answers'][$ans] = ($results[$index]['answers'][$ans] ?? 0) + 1;
            }
        }
        return $results;
    }

    public static function get_survey_responses($survey_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_survey_responses WHERE survey_id = %d", $survey_id));
    }

    public static function assign_test($test_id, $user_id) {
        global $wpdb;
        $assigned_by = get_current_user_id();
        return $wpdb->insert("{$wpdb->prefix}sm_test_assignments", [
            'test_id' => intval($test_id),
            'user_id' => intval($user_id),
            'assigned_by' => intval($assigned_by),
            'status' => 'assigned',
            'created_at' => current_time('mysql')
        ]);
    }

    public static function get_test_assignments($test_id = null) {
        global $wpdb;
        $query = "SELECT a.*, u.display_name as user_name, u2.display_name as assigner_name
                  FROM {$wpdb->prefix}sm_test_assignments a
                  JOIN {$wpdb->prefix}users u ON a.user_id = u.ID
                  LEFT JOIN {$wpdb->prefix}users u2 ON a.assigned_by = u2.ID";
        if ($test_id) {
            return $wpdb->get_results($wpdb->prepare($query . " WHERE a.test_id = %d", $test_id));
        }
        return $wpdb->get_results($query);
    }
}

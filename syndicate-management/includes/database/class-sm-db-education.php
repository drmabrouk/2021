<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_DB_Education {
    public static function add_survey($data) {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}sm_surveys", array(
            'title' => sanitize_text_field($data['title']),
            'questions' => json_encode($data['questions'] ?? []),
            'recipients' => sanitize_text_field($data['recipients'] ?? 'all'),
            'specialty' => sanitize_text_field($data['specialty'] ?? ''),
            'test_type' => sanitize_text_field($data['test_type'] ?? 'practice'),
            'time_limit' => intval($data['time_limit'] ?? 30),
            'max_attempts' => intval($data['max_attempts'] ?? 1),
            'pass_score' => intval($data['pass_score'] ?? 50),
            'status' => 'active',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ));
        return $wpdb->insert_id;
    }

    public static function update_survey($id, $data) {
        global $wpdb;
        return $wpdb->update("{$wpdb->prefix}sm_surveys", array(
            'title' => sanitize_text_field($data['title']),
            'recipients' => sanitize_text_field($data['recipients']),
            'specialty' => sanitize_text_field($data['specialty']),
            'test_type' => sanitize_text_field($data['test_type']),
            'time_limit' => intval($data['time_limit']),
            'max_attempts' => intval($data['max_attempts']),
            'pass_score' => intval($data['pass_score']),
            'status' => sanitize_text_field($data['status'] ?? 'active')
        ), ['id' => intval($id)]);
    }

    public static function add_question($data) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_test_questions", array(
            'test_id' => intval($data['test_id']),
            'question_text' => sanitize_textarea_field($data['question_text']),
            'question_type' => sanitize_text_field($data['question_type'] ?? 'mcq'),
            'options' => json_encode($data['options'] ?? []),
            'correct_answer' => sanitize_text_field($data['correct_answer']),
            'points' => intval($data['points'] ?? 1),
            'topic' => sanitize_text_field($data['topic'] ?? ''),
            'difficulty' => sanitize_text_field($data['difficulty'] ?? 'medium'),
            'sort_order' => intval($data['sort_order'] ?? 0)
        ));
    }

    public static function get_test_questions($test_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_test_questions WHERE test_id = %d ORDER BY sort_order ASC", $test_id));
    }

    public static function delete_question($id) {
        global $wpdb;
        return $wpdb->delete("{$wpdb->prefix}sm_test_questions", ['id' => intval($id)]);
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

        $questions = self::get_test_questions($survey_id);
        $responses = $wpdb->get_results("SELECT responses, score FROM {$wpdb->prefix}sm_survey_responses WHERE $where");

        $results = [
            'stats' => [
                'total_responses' => count($responses),
                'avg_score' => count($responses) > 0 ? array_sum(array_column($responses, 'score')) / count($responses) : 0,
                'pass_count' => 0
            ],
            'questions' => []
        ];

        foreach ($responses as $r) {
            if ($r->score >= $survey->pass_score) $results['stats']['pass_count']++;
        }

        foreach ($questions as $q) {
            $q_res = ['question' => $q->question_text, 'type' => $q->question_type, 'answers' => []];
            foreach ($responses as $r) {
                $res_data = json_decode($r->responses, true);
                $ans = $res_data[$q->id] ?? 'No Answer';
                $q_res['answers'][$ans] = ($q_res['answers'][$ans] ?? 0) + 1;
            }
            $results['questions'][] = $q_res;
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

    public static function get_user_attempts_count($test_id, $user_id) {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sm_survey_responses WHERE survey_id = %d AND user_id = %d", $test_id, $user_id));
    }

    public static function get_user_best_score($test_id, $user_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT MAX(score) FROM {$wpdb->prefix}sm_survey_responses WHERE survey_id = %d AND user_id = %d", $test_id, $user_id));
    }
}

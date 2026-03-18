<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_DB_System {
    public static function add_document($data) {
        global $wpdb;
        $res = $wpdb->insert("{$wpdb->prefix}sm_documents", array(
            'member_id' => intval($data['member_id']),
            'category' => sanitize_text_field($data['category']),
            'title' => sanitize_text_field($data['title']),
            'file_url' => esc_url_raw($data['file_url']),
            'file_type' => sanitize_text_field($data['file_type']),
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ));
        if ($res) {
            $doc_id = $wpdb->insert_id;
            self::log_document_action($doc_id, 'upload');
            return $doc_id;
        }
        return false;
    }

    public static function get_member_documents($member_id, $args = []) {
        global $wpdb;
        $query = "SELECT * FROM {$wpdb->prefix}sm_documents WHERE member_id = %d";
        $params = [intval($member_id)];

        if (!empty($args['category'])) {
            $query .= " AND category = %s";
            $params[] = sanitize_text_field($args['category']);
        }

        if (!empty($args['search'])) {
            $query .= " AND title LIKE %s";
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        $query .= " ORDER BY created_at DESC";
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    public static function delete_document($doc_id) {
        global $wpdb;
        self::log_document_action($doc_id, 'delete');
        return $wpdb->delete("{$wpdb->prefix}sm_documents", array('id' => intval($doc_id)));
    }

    public static function log_document_action($doc_id, $action) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_document_logs", array(
            'document_id' => intval($doc_id),
            'action' => sanitize_text_field($action),
            'user_id' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ));
    }

    public static function get_document_logs($doc_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name as user_name
             FROM {$wpdb->prefix}sm_document_logs l
             LEFT JOIN {$wpdb->prefix}users u ON l.user_id = u.ID
             WHERE l.document_id = %d
             ORDER BY l.created_at DESC",
            intval($doc_id)
        ));
    }

    public static function save_pub_template($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sm_pub_templates';
        if (!empty($data['id'])) {
            return $wpdb->update($table, [
                'title' => sanitize_text_field($data['title']),
                'content' => $data['content'],
                'doc_type' => sanitize_text_field($data['doc_type']),
                'settings' => $data['settings']
            ], ['id' => intval($data['id'])]);
        } else {
            return $wpdb->insert($table, [
                'title' => sanitize_text_field($data['title']),
                'content' => $data['content'],
                'doc_type' => sanitize_text_field($data['doc_type']),
                'settings' => $data['settings']
            ]);
        }
    }

    public static function get_pub_templates() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sm_pub_templates ORDER BY created_at DESC");
    }

    public static function get_pub_template($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_pub_templates WHERE id = %d", $id));
    }

    public static function generate_pub_document($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sm_pub_documents';

        // Generate Unique Serial: PUB-YYYY-XXXXX
        $year = date('Y');
        $last_id = $wpdb->get_var("SELECT MAX(id) FROM $table");
        $serial = 'PUB-' . $year . '-' . str_pad(($last_id + 1), 5, '0', STR_PAD_LEFT);

        $res = $wpdb->insert($table, [
            'template_id' => intval($data['template_id'] ?? 0),
            'member_id' => intval($data['member_id'] ?? 0),
            'serial_number' => $serial,
            'title' => sanitize_text_field($data['title']),
            'content' => $data['content'],
            'options' => json_encode($data['options'] ?? []),
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ]);

        return $res ? $wpdb->insert_id : false;
    }

    public static function get_pub_documents($args = []) {
        global $wpdb;
        $where = "1=1";
        if (!empty($args['search'])) {
            $where .= $wpdb->prepare(" AND (d.title LIKE %s OR d.serial_number LIKE %s)", '%' . $wpdb->esc_like($args['search']) . '%', '%' . $wpdb->esc_like($args['search']) . '%');
        }
        return $wpdb->get_results("
            SELECT d.*, u.display_name as creator_name
            FROM {$wpdb->prefix}sm_pub_documents d
            LEFT JOIN {$wpdb->prefix}users u ON d.created_by = u.ID
            WHERE $where
            ORDER BY d.created_at DESC
        ");
    }

    public static function get_pub_document_by_serial($serial) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_pub_documents WHERE serial_number = %s", $serial));
    }

    public static function increment_pub_download($id, $format) {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}sm_pub_documents SET download_count = download_count + 1, last_format = %s WHERE id = %d",
            $format, $id
        ));
    }

    public static function save_alert($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sm_alerts';
        $insert_data = [
            'title' => sanitize_text_field($data['title']),
            'message' => wp_kses_post($data['message']),
            'severity' => sanitize_text_field($data['severity']),
            'must_acknowledge' => !empty($data['must_acknowledge']) ? 1 : 0,
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'target_roles' => !empty($data['target_roles']) ? json_encode((array)$data['target_roles']) : '',
            'target_ranks' => !empty($data['target_ranks']) ? json_encode((array)$data['target_ranks']) : '',
            'target_users' => sanitize_text_field($data['target_users'] ?? '')
        ];

        if (!empty($data['id'])) {
            return $wpdb->update($table, $insert_data, ['id' => intval($data['id'])]);
        }
        return $wpdb->insert($table, $insert_data);
    }

    public static function get_alerts($args = []) {
        global $wpdb;
        $where = "1=1";
        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(" AND status = %s", $args['status']);
        }
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sm_alerts WHERE $where ORDER BY created_at DESC");
    }

    public static function delete_alert($id) {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}sm_alert_views", ['alert_id' => intval($id)]);
        return $wpdb->delete("{$wpdb->prefix}sm_alerts", ['id' => intval($id)]);
    }

    public static function get_active_alerts_for_user($user_id) {
        global $wpdb;
        $user = get_userdata($user_id);
        if (!$user) return [];

        $roles = (array)$user->roles;
        $rank = get_user_meta($user_id, 'sm_rank', true);

        $alerts = $wpdb->get_results($wpdb->prepare("
            SELECT a.*
            FROM {$wpdb->prefix}sm_alerts a
            LEFT JOIN {$wpdb->prefix}sm_alert_views v ON a.id = v.alert_id AND v.user_id = %d
            WHERE a.status = 'active'
            AND v.id IS NULL
        ", $user_id));

        $filtered = [];
        foreach ($alerts as $a) {
            $pass = true;
            if (!empty($a->target_roles)) {
                $target_roles = json_decode($a->target_roles, true);
                if (!empty($target_roles) && empty(array_intersect($roles, $target_roles))) $pass = false;
            }
            if ($pass && !empty($a->target_ranks)) {
                $target_ranks = json_decode($a->target_ranks, true);
                if (!empty($target_ranks) && !in_array($rank, $target_ranks)) $pass = false;
            }
            if ($pass && !empty($a->target_users)) {
                $target_users = array_map('trim', explode(',', $a->target_users));
                if (!in_array($user->user_login, $target_users)) {
                    $member = SM_DB_Members::get_member_by_username($user->user_login);
                    if (!$member || !in_array($member->national_id, $target_users)) $pass = false;
                }
            }
            if ($pass) $filtered[] = $a;
        }
        return $filtered;
    }

    public static function acknowledge_alert($alert_id, $user_id) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_alert_views", [
            'alert_id' => intval($alert_id),
            'user_id' => intval($user_id),
            'acknowledged' => 1,
            'created_at' => current_time('mysql')
        ]);
    }

    public static function get_branches_data() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sm_branches_data ORDER BY name ASC");
    }

    public static function save_branch($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sm_branches_data';
        $branch_data = [
            'slug' => sanitize_title($data['slug']),
            'name' => sanitize_text_field($data['name']),
            'phone' => sanitize_text_field($data['phone']),
            'email' => sanitize_email($data['email']),
            'address' => sanitize_text_field($data['address']),
            'manager' => sanitize_text_field($data['manager']),
            'description' => sanitize_textarea_field($data['description'])
        ];
        if (!empty($data['id'])) return $wpdb->update($table, $branch_data, ['id' => intval($data['id'])]);
        else return $wpdb->insert($table, $branch_data);
    }

    public static function delete_branch($id) {
        global $wpdb;
        return $wpdb->delete("{$wpdb->prefix}sm_branches_data", ['id' => intval($id)]);
    }

    public static function get_backup_data() {
        global $wpdb;
        $data = array();
        $tables = array('members', 'messages');
        foreach ($tables as $t) {
            $data[$t] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sm_$t", ARRAY_A);
        }
        return json_encode($data);
    }

    public static function restore_backup($json) {
        global $wpdb;
        $data = json_decode($json, true);
        if (!$data) return false;

        foreach ($data as $table => $rows) {
            $table_name = $wpdb->prefix . 'sm_' . $table;
            $wpdb->query("TRUNCATE TABLE $table_name");
            foreach ($rows as $row) {
                $wpdb->insert($table_name, $row);
            }
        }
        return true;
    }
}

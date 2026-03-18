<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_DB_Communications {
    public static function send_message($sender_id, $receiver_id, $message, $member_id = null, $file_url = null, $governorate = null) {
        global $wpdb;
        return $wpdb->insert($wpdb->prefix . 'sm_messages', array(
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'member_id' => $member_id,
            'message' => $message,
            'file_url' => $file_url,
            'governorate' => $governorate,
            'created_at' => current_time('mysql')
        ));
    }

    public static function get_ticket_messages($member_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.display_name as sender_name
             FROM {$wpdb->prefix}sm_messages m
             LEFT JOIN {$wpdb->prefix}users u ON m.sender_id = u.ID
             WHERE m.member_id = %d
             ORDER BY m.created_at ASC",
            $member_id
        ));
    }

    public static function get_governorate_officials($governorate) {
        return get_users(array(
            'role__in' => array('sm_system_admin', 'sm_syndicate_admin', 'administrator'),
            'meta_query' => array(
                array(
                    'key' => 'sm_governorate',
                    'value' => $governorate,
                    'compare' => '='
                )
            )
        ));
    }

    public static function get_governorate_conversations($governorate = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'sm_messages';

        $where = "1=1";
        $params = [];
        if (!empty($governorate)) {
            $where = "governorate = %s";
            $params[] = $governorate;
        }

        $query = "SELECT member_id, MAX(created_at) as last_activity
                  FROM $table
                  WHERE $where
                  GROUP BY member_id
                  ORDER BY last_activity DESC";

        if (!empty($params)) {
            $results = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $results = $wpdb->get_results($query);
        }

        $conversations = [];
        foreach ($results as $row) {
            $member = SM_DB_Members::get_member_by_id($row->member_id);
            if (!$member) continue;

            $last_msg = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sm_messages
                 WHERE member_id = %d
                 ORDER BY created_at DESC LIMIT 1",
                $row->member_id
            ));

            $conversations[] = [
                'member' => $member,
                'last_message' => $last_msg
            ];
        }
        return $conversations;
    }

    public static function get_conversation_messages($user1, $user2) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.display_name as sender_name
             FROM {$wpdb->prefix}sm_messages m
             JOIN {$wpdb->prefix}users u ON m.sender_id = u.ID
             WHERE (sender_id = %d AND receiver_id = %d)
                OR (sender_id = %d AND receiver_id = %d)
             ORDER BY created_at ASC",
            $user1, $user2, $user2, $user1
        ));
    }

    public static function get_sent_messages($user_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.display_name as receiver_name
             FROM {$wpdb->prefix}sm_messages m
             JOIN {$wpdb->prefix}users u ON m.receiver_id = u.ID
             WHERE m.sender_id = %d
             ORDER BY m.created_at DESC",
            $user_id
        ));
    }

    public static function delete_expired_messages() {
        global $wpdb;
        return $wpdb->query("DELETE FROM {$wpdb->prefix}sm_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
    }

    public static function get_conversations($user_id) {
        global $wpdb;
        $other_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT CASE WHEN sender_id = %d THEN receiver_id ELSE sender_id END
             FROM {$wpdb->prefix}sm_messages
             WHERE sender_id = %d OR receiver_id = %d",
            $user_id, $user_id, $user_id
        ));

        $conversations = [];
        foreach ($other_ids as $oid) {
            $last_msg = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sm_messages
                 WHERE (sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d)
                 ORDER BY created_at DESC LIMIT 1",
                $user_id, $oid, $oid, $user_id
            ));
            $u = get_userdata($oid);
            if (!$u) continue;
            $conversations[] = [
                'user' => $u,
                'last_message' => $last_msg
            ];
        }
        return $conversations;
    }

    public static function create_ticket($data) {
        global $wpdb;
        $res = $wpdb->insert("{$wpdb->prefix}sm_tickets", array(
            'member_id' => intval($data['member_id']),
            'subject' => sanitize_text_field($data['subject']),
            'category' => sanitize_text_field($data['category']),
            'priority' => sanitize_text_field($data['priority'] ?? 'medium'),
            'status' => 'open',
            'province' => sanitize_text_field($data['province']),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ));
        if ($res) {
            $ticket_id = $wpdb->insert_id;
            // Add initial message to thread
            self::add_ticket_reply(array(
                'ticket_id' => $ticket_id,
                'sender_id' => get_current_user_id(),
                'message' => $data['message'],
                'file_url' => $data['file_url'] ?? null
            ));
            return $ticket_id;
        }
        return false;
    }

    public static function add_ticket_reply($data) {
        global $wpdb;
        $res = $wpdb->insert("{$wpdb->prefix}sm_ticket_thread", array(
            'ticket_id' => intval($data['ticket_id']),
            'sender_id' => intval($data['sender_id']),
            'message' => sanitize_textarea_field($data['message']),
            'file_url' => $data['file_url'] ?? null,
            'created_at' => current_time('mysql')
        ));
        if ($res) {
            $wpdb->update("{$wpdb->prefix}sm_tickets", array('updated_at' => current_time('mysql')), array('id' => intval($data['ticket_id'])));
            return $wpdb->insert_id;
        }
        return false;
    }

    public static function get_tickets($args = array()) {
        global $wpdb;
        $user = wp_get_current_user();
        $is_sys_admin = in_array('sm_system_admin', $user->roles) || in_array('administrator', $user->roles);
        $is_officer = in_array('sm_syndicate_admin', $user->roles);
        $is_member = in_array('sm_syndicate_member', $user->roles);

        $where = "1=1";
        $params = array();

        if ($is_officer && !$is_sys_admin) {
            $gov = get_user_meta($user->ID, 'sm_governorate', true);
            if ($gov) {
                $where .= " AND t.province = %s";
                $params[] = $gov;
            }
        } elseif ($is_member) {
            // Find member_id from wp_user_id
            $member_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", $user->ID));
            $where .= " AND t.member_id = %d";
            $params[] = intval($member_id);
        }

        if (!empty($args['status'])) {
            $where .= " AND t.status = %s";
            $params[] = sanitize_text_field($args['status']);
        }

        if (!empty($args['category'])) {
            $where .= " AND t.category = %s";
            $params[] = sanitize_text_field($args['category']);
        }

        if (!empty($args['priority'])) {
            $where .= " AND t.priority = %s";
            $params[] = sanitize_text_field($args['priority']);
        }

        if (!empty($args['province'])) {
            $where .= " AND t.province = %s";
            $params[] = sanitize_text_field($args['province']);
        }

        if (!empty($args['search'])) {
            $s = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= " AND (t.subject LIKE %s OR m.name LIKE %s)";
            $params[] = $s;
            $params[] = $s;
        }

        $query = "SELECT t.*, IFNULL(m.name, 'زائر / خارجي') as member_name, m.photo_url as member_photo
                  FROM {$wpdb->prefix}sm_tickets t
                  LEFT JOIN {$wpdb->prefix}sm_members m ON t.member_id = m.id
                  WHERE $where
                  ORDER BY t.updated_at DESC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        }
        return $wpdb->get_results($query);
    }

    public static function get_ticket($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, IFNULL(m.name, 'زائر / خارجي') as member_name, IFNULL(m.governorate, 'HQ') as member_province, IFNULL(m.phone, 'N/A') as member_phone
             FROM {$wpdb->prefix}sm_tickets t
             LEFT JOIN {$wpdb->prefix}sm_members m ON t.member_id = m.id
             WHERE t.id = %d",
            $id
        ));
    }

    public static function get_ticket_thread($ticket_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT tr.*, u.display_name as sender_name
             FROM {$wpdb->prefix}sm_ticket_thread tr
             LEFT JOIN {$wpdb->base_prefix}users u ON tr.sender_id = u.ID
             WHERE tr.ticket_id = %d
             ORDER BY tr.created_at ASC",
            $ticket_id
        ));
    }

    public static function update_ticket_status($id, $status) {
        global $wpdb;
        return $wpdb->update("{$wpdb->prefix}sm_tickets", array('status' => $status), array('id' => $id));
    }
}

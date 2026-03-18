<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_DB_Services {

    public static function get_services($args = array()) {
        global $wpdb;
        $where = "1=1";
        $params = [];

        if (isset($args['is_deleted'])) {
            $where .= " AND is_deleted = %d";
            $params[] = (int)$args['is_deleted'];
        } else {
            $where .= " AND is_deleted = 0";
        }

        if (!empty($args['status']) && $args['status'] !== 'any' && $args['status'] !== 'all') {
            $where .= " AND status = %s";
            $params[] = sanitize_text_field($args['status']);
        }

        if (!empty($args['category'])) {
            $where .= " AND category = %s";
            $params[] = sanitize_text_field($args['category']);
        }

        $query = "SELECT * FROM {$wpdb->prefix}sm_services WHERE $where ORDER BY created_at DESC";
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        }
        return $wpdb->get_results($query);
    }

    public static function add_service($data) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_services", array(
            'name' => sanitize_text_field($data['name']),
            'category' => sanitize_text_field($data['category'] ?? 'عام'),
            'branch' => sanitize_text_field($data['branch'] ?? 'all'),
            'icon' => sanitize_text_field($data['icon'] ?? 'dashicons-cloud'),
            'requires_login' => isset($data['requires_login']) ? (int)$data['requires_login'] : 1,
            'description' => sanitize_textarea_field($data['description']),
            'fees' => floatval($data['fees']),
            'required_fields' => $data['required_fields'] ?? '[]',
            'selected_profile_fields' => $data['selected_profile_fields'] ?? '[]',
            'status' => $data['status'] ?? 'active',
            'created_at' => current_time('mysql')
        ));
    }

    public static function update_service($id, $data) {
        global $wpdb;
        $update_data = [];
        if (isset($data['name'])) $update_data['name'] = sanitize_text_field($data['name']);
        if (isset($data['category'])) $update_data['category'] = sanitize_text_field($data['category']);
        if (isset($data['branch'])) $update_data['branch'] = sanitize_text_field($data['branch']);
        if (isset($data['icon'])) $update_data['icon'] = sanitize_text_field($data['icon']);
        if (isset($data['requires_login'])) $update_data['requires_login'] = (int)$data['requires_login'];
        if (isset($data['description'])) $update_data['description'] = sanitize_textarea_field($data['description']);
        if (isset($data['fees'])) $update_data['fees'] = floatval($data['fees']);
        if (isset($data['status'])) $update_data['status'] = sanitize_text_field($data['status']);
        if (isset($data['required_fields'])) $update_data['required_fields'] = $data['required_fields'];
        if (isset($data['selected_profile_fields'])) $update_data['selected_profile_fields'] = $data['selected_profile_fields'];

        return $wpdb->update("{$wpdb->prefix}sm_services", $update_data, array('id' => intval($id)));
    }

    public static function delete_service($id, $permanent = false) {
        global $wpdb;
        if ($permanent) {
            $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_services WHERE id = %d", $id));
            if ($service) {
                 SM_Logger::log('حذف خدمة رقمية نهائياً', 'ROLLBACK_DATA:' . json_encode(['table' => 'services', 'data' => (array)$service]));
            }
            return $wpdb->delete("{$wpdb->prefix}sm_services", array('id' => $id));
        } else {
            return $wpdb->update("{$wpdb->prefix}sm_services", array('is_deleted' => 1), array('id' => intval($id)));
        }
    }

    public static function restore_service($id) {
        global $wpdb;
        return $wpdb->update("{$wpdb->prefix}sm_services", array('is_deleted' => 0), array('id' => intval($id)));
    }

    public static function submit_service_request($data) {
        global $wpdb;
        $res = $wpdb->insert("{$wpdb->prefix}sm_service_requests", array(
            'service_id' => intval($data['service_id']),
            'member_id' => intval($data['member_id']),
            'request_data' => $data['request_data'], // JSON string
            'fees_paid' => 0,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ));
        return $res ? $wpdb->insert_id : false;
    }

    public static function get_service_requests($args = array()) {
        global $wpdb;
        $where = "1=1";
        $params = [];

        $user = wp_get_current_user();
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        if (!$has_full_access && $my_gov) {
            $where .= " AND m.governorate = %s";
            $params[] = $my_gov;
        }

        if (!empty($args['status'])) {
            $where .= " AND r.status = %s";
            $params[] = $args['status'];
        }

        if (!empty($args['member_id'])) {
            $where .= " AND r.member_id = %d";
            $params[] = intval($args['member_id']);
        }

        $query = "SELECT r.*, s.name as service_name, s.required_fields as service_fields, m.name as member_name, m.governorate, m.national_id, m.phone, m.email
                  FROM {$wpdb->prefix}sm_service_requests r
                  JOIN {$wpdb->prefix}sm_services s ON r.service_id = s.id
                  LEFT JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
                  WHERE $where
                  ORDER BY r.created_at DESC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        }
        return $wpdb->get_results($query);
    }

    public static function update_service_request_status($request_id, $status, $fees_paid = null, $notes = '') {
        global $wpdb;
        $data = array(
            'status' => $status,
            'admin_notes' => sanitize_textarea_field($notes),
            'processed_by' => get_current_user_id(),
            'updated_at' => current_time('mysql')
        );
        if ($fees_paid !== null) $data['fees_paid'] = floatval($fees_paid);

        return $wpdb->update("{$wpdb->prefix}sm_service_requests", $data, array('id' => $request_id));
    }

    public static function add_professional_request($member_id, $type) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_professional_requests", array(
            'member_id' => intval($member_id),
            'request_type' => $type,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ));
    }

    public static function get_professional_requests($args = []) {
        global $wpdb;
        $query = "SELECT r.*, m.name as member_name, m.national_id, m.governorate
                 FROM {$wpdb->prefix}sm_professional_requests r
                 JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id WHERE 1=1";
        $params = [];

        if (!empty($args['status'])) {
            $query .= " AND r.status = %s";
            $params[] = $args['status'];
        }
        if (!empty($args['type'])) {
            $query .= " AND r.request_type = %s";
            $params[] = $args['type'];
        }
        if (!empty($args['governorate'])) {
            $query .= " AND m.governorate = %s";
            $params[] = $args['governorate'];
        }

        $query .= " ORDER BY r.created_at DESC";
        if (!empty($params)) return $wpdb->get_results($wpdb->prepare($query, $params));
        return $wpdb->get_results($query);
    }

    public static function process_professional_request($id, $status, $notes = '') {
        global $wpdb;
        return $wpdb->update(
            "{$wpdb->prefix}sm_professional_requests",
            array(
                'status' => $status,
                'admin_notes' => sanitize_textarea_field($notes),
                'processed_at' => current_time('mysql'),
                'processed_by' => get_current_user_id()
            ),
            array('id' => intval($id))
        );
    }
}

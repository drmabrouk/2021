<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_DB_Members {

    public static function get_staff($args = array()) {
        $user = wp_get_current_user();
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $default_args = array(
            'number' => 20,
            'offset' => 0,
            'orderby' => 'display_name',
            'order' => 'ASC'
        );

        // If not a full admin, restricted to branch
        if (!$has_full_access) {
            if ($my_gov) {
                $default_args['meta_query'] = array(
                    array(
                        'key' => 'sm_governorate',
                        'value' => $my_gov,
                        'compare' => '='
                    )
                );
            } else {
                // Non-admin with no governorate cannot see staff
                $default_args['include'] = array($user->ID);
            }
        }

        $args = wp_parse_args($args, $default_args);
        return get_users($args);
    }

    public static function get_members($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';
        $query = "SELECT * FROM $table_name WHERE 1=1";
        $params = array();

        $limit = isset($args['limit']) ? intval($args['limit']) : 20;
        $offset = isset($args['offset']) ? intval($args['offset']) : 0;

        // Ensure we don't have negative limits unless specifically -1
        if ($limit < -1) {
            $limit = 20;
        }

        // Role-based filtering (Governorate)
        $user = wp_get_current_user();
        $has_full_access = current_user_can('manage_options') || current_user_can('sm_full_access');
        if (!$has_full_access) {
            $gov = get_user_meta($user->ID, 'sm_governorate', true);
            if ($gov) {
                $query .= " AND governorate = %s";
                $params[] = $gov;
            } else {
                $query .= " AND 1=0"; // No access if no governorate assigned
            }
        }

        if (isset($args['professional_grade']) && !empty($args['professional_grade'])) {
            $query .= " AND professional_grade = %s";
            $params[] = $args['professional_grade'];
        }

        if (isset($args['specialization']) && !empty($args['specialization'])) {
            $query .= " AND specialization = %s";
            $params[] = $args['specialization'];
        }

        if (isset($args['membership_status']) && !empty($args['membership_status'])) {
            $query .= " AND membership_status = %s";
            $params[] = $args['membership_status'];
        }

        if (isset($args['search']) && !empty($args['search'])) {
            $query .= " AND (name LIKE %s OR national_id LIKE %s OR membership_number LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        $query .= " ORDER BY sort_order ASC, name ASC";

        if ($limit != -1) {
            $query .= " LIMIT %d OFFSET %d";
            $params[] = $limit;
            $params[] = $offset;
        }

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        }
        return $wpdb->get_results($query);
    }

    public static function get_member_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE id = %d", $id));
    }

    public static function get_member_by_national_id($national_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE national_id = %s", $national_id));
    }

    public static function get_member_by_membership_number($membership_number) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE membership_number = %s", $membership_number));
    }

    public static function get_member_by_license_number($license_number) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE license_number = %s", $license_number));
    }

    public static function get_member_by_facility_number($facility_number) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE facility_number = %s", $facility_number));
    }

    public static function get_member_by_username($username) {
        $user = get_user_by('login', $username);
        if (!$user) {
            return null;
        }
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", $user->ID));
    }

    public static function add_member($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';

        $national_id = sanitize_text_field($data['national_id'] ?? '');
        if (!preg_match('/^[0-9]{14}$/', $national_id)) {
            return new WP_Error('invalid_national_id', 'الرقم القومي يجب أن يتكون من 14 رقم بالضبط وبدون حروف.');
        }

        // Check if national_id already exists
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE national_id = %s", $national_id));
        if ($exists) {
            return new WP_Error('duplicate_national_id', 'الرقم القومي مسجل مسبقاً.');
        }

        $name = sanitize_text_field($data['name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');

        // Auto-create WordPress User for the Member
        $wp_user_id = null;
        $digits = '';
        for ($i = 0; $i < 10; $i++) {
            $digits .= mt_rand(0, 9);
        }
        $temp_pass = 'IRS' . $digits;

        if (!function_exists('wp_insert_user')) {
            require_once(ABSPATH . 'wp-includes/user.php');
        }

        $wp_user_id = wp_insert_user(array(
            'user_login' => $national_id,
            'user_email' => $email ?: $national_id . '@irseg.org',
            'display_name' => $name,
            'user_pass' => $temp_pass,
            'role' => 'sm_syndicate_member'
        ));

        if (!is_wp_error($wp_user_id)) {
            update_user_meta($wp_user_id, 'sm_temp_pass', $temp_pass);
            if (!empty($data['governorate'])) {
                update_user_meta($wp_user_id, 'sm_governorate', sanitize_text_field($data['governorate']));
            }
        } else {
            return $wp_user_id; // Return WP_Error
        }

        $insert_data = array(
            'national_id' => $national_id,
            'name' => $name,
            'gender' => sanitize_text_field($data['gender'] ?? 'male'),
            'professional_grade' => sanitize_text_field($data['professional_grade'] ?? ''),
            'specialization' => sanitize_text_field($data['specialization'] ?? ''),
            'academic_degree' => sanitize_text_field($data['academic_degree'] ?? ''),
            'university' => sanitize_text_field($data['university'] ?? ''),
            'faculty' => sanitize_text_field($data['faculty'] ?? ''),
            'department' => sanitize_text_field($data['department'] ?? ''),
            'graduation_date' => sanitize_text_field($data['graduation_date'] ?? null),
            'residence_street' => sanitize_textarea_field($data['residence_street'] ?? ''),
            'residence_city' => sanitize_text_field($data['residence_city'] ?? ''),
            'residence_governorate' => sanitize_text_field($data['residence_governorate'] ?? ''),
            'governorate' => sanitize_text_field($data['governorate'] ?? ''),
            'membership_number' => sanitize_text_field($data['membership_number'] ?? ''),
            'membership_start_date' => sanitize_text_field($data['membership_start_date'] ?? null),
            'membership_expiration_date' => sanitize_text_field($data['membership_expiration_date'] ?? null),
            'membership_status' => sanitize_text_field($data['membership_status'] ?? ''),
            'license_number' => sanitize_text_field($data['license_number'] ?? ''),
            'license_issue_date' => sanitize_text_field($data['license_issue_date'] ?? null),
            'license_expiration_date' => sanitize_text_field($data['license_expiration_date'] ?? null),
            'facility_number' => sanitize_text_field($data['facility_number'] ?? ''),
            'facility_name' => sanitize_text_field($data['facility_name'] ?? ''),
            'facility_license_issue_date' => sanitize_text_field($data['facility_license_issue_date'] ?? null),
            'facility_license_expiration_date' => sanitize_text_field($data['facility_license_expiration_date'] ?? null),
            'facility_address' => sanitize_textarea_field($data['facility_address'] ?? ''),
            'sub_syndicate' => sanitize_text_field($data['sub_syndicate'] ?? ''),
            'facility_category' => sanitize_text_field($data['facility_category'] ?? 'C'),
            'last_paid_membership_year' => intval($data['last_paid_membership_year'] ?? 0),
            'last_paid_license_year' => intval($data['last_paid_license_year'] ?? 0),
            'email' => $email ?: $national_id . '@irseg.org',
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'alt_phone' => sanitize_text_field($data['alt_phone'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'province_of_birth' => sanitize_text_field($data['province_of_birth'] ?? ''),
            'wp_user_id' => $wp_user_id,
            'registration_date' => current_time('Y-m-d'),
            'sort_order' => self::get_next_sort_order()
        );

        $wpdb->insert($table_name, $insert_data);
        $id = $wpdb->insert_id;

        if ($id) {
            SM_Logger::log('إضافة عضو جديد', "تمت إضافة العضو: $name بنجاح (الرقم القومي: $national_id)");
        }

        return $id;
    }

    public static function update_member($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sm_members';

        $update_data = array();
        $fields = [
            'national_id', 'name', 'gender', 'professional_grade', 'specialization',
            'academic_degree', 'university', 'faculty', 'department', 'graduation_date',
            'residence_street', 'residence_city', 'residence_governorate',
            'governorate', 'membership_number', 'membership_start_date',
            'membership_expiration_date', 'membership_status', 'license_number',
            'license_issue_date', 'license_expiration_date', 'facility_number',
            'facility_name', 'facility_license_issue_date', 'facility_license_expiration_date',
            'facility_address', 'sub_syndicate', 'facility_category', 'last_paid_membership_year',
            'last_paid_license_year', 'email', 'phone', 'alt_phone', 'notes', 'province_of_birth'
        ];

        foreach ($fields as $f) {
            if (isset($data[$f])) {
                if (in_array($f, ['facility_address', 'notes', 'residence_street'])) {
                    $update_data[$f] = sanitize_textarea_field($data[$f]);
                } elseif ($f === 'email') {
                    $update_data[$f] = sanitize_email($data[$f]);
                } else {
                    $update_data[$f] = sanitize_text_field($data[$f]);
                }
            }
        }

        if (isset($data['wp_user_id'])) $update_data['wp_user_id'] = intval($data['wp_user_id']);
        if (isset($data['registration_date'])) $update_data['registration_date'] = sanitize_text_field($data['registration_date']);
        if (isset($data['sort_order'])) $update_data['sort_order'] = intval($data['sort_order']);

        $res = $wpdb->update($table_name, $update_data, array('id' => $id));

        // Sync to WP User
        $member = self::get_member_by_id($id);
        if ($member && $member->wp_user_id) {
            $user_data = ['ID' => $member->wp_user_id];
            if (isset($data['name'])) $user_data['display_name'] = $data['name'];
            if (isset($data['email'])) $user_data['user_email'] = $data['email'];
            if (count($user_data) > 1) {
                wp_update_user($user_data);
            }
            if (isset($data['governorate'])) {
                update_user_meta($member->wp_user_id, 'sm_governorate', sanitize_text_field($data['governorate']));
            }
        }

        return $res;
    }

    public static function update_member_photo($id, $photo_url) {
        global $wpdb;
        return $wpdb->update($wpdb->prefix . 'sm_members', array('photo_url' => $photo_url), array('id' => $id));
    }

    public static function delete_member($id) {
        global $wpdb;

        $member = self::get_member_by_id($id);
        if ($member) {
            SM_Logger::log('حذف عضو (مع إمكانية الاستعادة)', 'ROLLBACK_DATA:' . json_encode(['table' => 'members', 'data' => (array)$member]));
            if ($member->wp_user_id) {
                if (!function_exists('wp_delete_user')) {
                    require_once(ABSPATH . 'wp-admin/includes/user.php');
                }
                wp_delete_user($member->wp_user_id);
            }
        }

        return $wpdb->delete($wpdb->prefix . 'sm_members', array('id' => $id));
    }

    public static function member_exists($national_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sm_members WHERE national_id = %s",
            $national_id
        ));
    }

    public static function get_next_sort_order() {
        global $wpdb;
        $max = $wpdb->get_var("SELECT MAX(sort_order) FROM {$wpdb->prefix}sm_members");
        return ($max ? intval($max) : 0) + 1;
    }

    public static function add_membership_request($data) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_membership_requests", array(
            'national_id' => sanitize_text_field($data['national_id']),
            'name' => sanitize_text_field($data['name']),
            'gender' => sanitize_text_field($data['gender'] ?? 'male'),
            'professional_grade' => sanitize_text_field($data['professional_grade'] ?? ''),
            'specialization' => sanitize_text_field($data['specialization'] ?? ''),
            'academic_degree' => sanitize_text_field($data['academic_degree'] ?? ''),
            'university' => sanitize_text_field($data['university'] ?? ''),
            'faculty' => sanitize_text_field($data['faculty'] ?? ''),
            'department' => sanitize_text_field($data['department'] ?? ''),
            'graduation_date' => sanitize_text_field($data['graduation_date'] ?? null),
            'residence_street' => sanitize_textarea_field($data['residence_street'] ?? ''),
            'residence_city' => sanitize_text_field($data['residence_city'] ?? ''),
            'residence_governorate' => sanitize_text_field($data['residence_governorate'] ?? ''),
            'governorate' => sanitize_text_field($data['governorate'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'current_stage' => 1,
            'status' => 'Pending Payment',
            'created_at' => current_time('mysql')
        ));
    }

    public static function update_membership_request($id, $data) {
        global $wpdb;
        $update_data = array();
        $fields = [
            'name', 'gender', 'professional_grade', 'specialization', 'academic_degree',
            'university', 'faculty', 'department', 'graduation_date',
            'residence_street', 'residence_city', 'residence_governorate', 'governorate',
            'phone', 'email', 'notes', 'payment_method', 'payment_reference', 'payment_screenshot_url',
            'doc_qualification_url', 'doc_id_url', 'doc_military_url', 'doc_criminal_url', 'doc_photo_url',
            'current_stage', 'status', 'rejection_reason', 'processed_by'
        ];

        foreach ($fields as $f) {
            if (isset($data[$f])) {
                if (in_array($f, ['notes', 'residence_street', 'rejection_reason'])) {
                    $update_data[$f] = sanitize_textarea_field($data[$f]);
                } elseif (strpos($f, '_url') !== false) {
                    $update_data[$f] = esc_url_raw($data[$f]);
                } elseif ($f === 'email') {
                    $update_data[$f] = sanitize_email($data[$f]);
                } elseif ($f === 'current_stage') {
                    $update_data[$f] = intval($data[$f]);
                } else {
                    $update_data[$f] = sanitize_text_field($data[$f]);
                }
            }
        }

        return $wpdb->update("{$wpdb->prefix}sm_membership_requests", $update_data, array('id' => intval($id)));
    }

    public static function get_membership_request($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_membership_requests WHERE id = %d", $id));
    }

    public static function get_membership_request_by_national_id($national_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_membership_requests WHERE national_id = %s", $national_id));
    }

    public static function get_membership_requests($status = null) {
        global $wpdb;
        $query = "SELECT * FROM {$wpdb->prefix}sm_membership_requests";
        if ($status) {
            return $wpdb->get_results($wpdb->prepare($query . " WHERE status = %s ORDER BY created_at DESC", $status));
        }
        return $wpdb->get_results($query . " ORDER BY created_at DESC");
    }

    public static function add_update_request($member_id, $data) {
        global $wpdb;
        return $wpdb->insert("{$wpdb->prefix}sm_update_requests", array(
            'member_id' => $member_id,
            'requested_data' => json_encode($data),
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ));
    }

    public static function get_update_requests($status = 'pending') {
        global $wpdb;
        $user = wp_get_current_user();
        $is_officer = in_array('sm_syndicate_admin', (array)$user->roles) || in_array('sm_syndicate_member', (array)$user->roles);
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $where = $wpdb->prepare("r.status = %s", $status);
        if ($is_officer && !$has_full_access && $my_gov) {
            $where .= $wpdb->prepare(" AND m.governorate = %s", $my_gov);
        }

        return $wpdb->get_results("
            SELECT r.*, m.name as member_name, m.national_id
            FROM {$wpdb->prefix}sm_update_requests r
            JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
            WHERE $where
            ORDER BY r.created_at DESC
        ");
    }

    public static function process_update_request($request_id, $status) {
        global $wpdb;
        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_update_requests WHERE id = %d", $request_id));
        if (!$request) return false;

        if ($status === 'approved') {
            $data = json_decode($request->requested_data, true);
            self::update_member($request->member_id, $data);
            SM_Logger::log('اعتماد طلب تحديث بيانات', "تم تحديث بيانات العضو ID: {$request->member_id}");
        }

        return $wpdb->update(
            "{$wpdb->prefix}sm_update_requests",
            array(
                'status' => $status,
                'processed_at' => current_time('mysql'),
                'processed_by' => get_current_user_id()
            ),
            array('id' => $request_id)
        );
    }
}

<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_DB_Finance {
    public static function get_statistics($filters = array()) {
        global $wpdb;
        $stats = array();

        $user = wp_get_current_user();
        $has_full_access = current_user_can('sm_full_access') || current_user_can('manage_options');
        $is_officer = in_array('sm_syndicate_admin', (array)$user->roles);
        $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

        $where_member = "1=1";
        // Union Officer (sm_syndicate_admin) must be restricted to their branch
        if ($is_officer || !$has_full_access) {
            if ($my_gov) {
                $where_member = $wpdb->prepare("governorate = %s", $my_gov);
            } else {
                $where_member = "1=0";
            }
        }

        $stats['total_members'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sm_members WHERE $where_member");
        $stats['total_officers'] = count(SM_DB_Members::get_staff(['number' => -1]));

        // Total Board Members
        $stats['total_board'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}users u
             JOIN {$wpdb->prefix}usermeta um1 ON u.ID = um1.user_id AND um1.meta_key = '{$wpdb->prefix}capabilities'
             JOIN {$wpdb->prefix}usermeta um2 ON u.ID = um2.user_id AND um2.meta_key = 'sm_rank'
             WHERE um1.meta_value LIKE %s AND um2.meta_value != ''",
            '%"sm_syndicate_admin"%'
        ));

        // Total Revenue
        $join_member_rev = "";
        $where_rev = "1=1";
        if (!$has_full_access) {
            if ($my_gov) {
                $join_member_rev = "JOIN {$wpdb->prefix}sm_members m ON p.member_id = m.id";
                $where_rev = $wpdb->prepare("m.governorate = %s", $my_gov);
            } else {
                $where_rev = "1=0";
            }
        }
        $stats['total_revenue'] = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}sm_payments p $join_member_rev WHERE $where_rev") ?: 0;

        // Financial Trends (Last 30 Days)
        $join_member = "";
        $where_finance = "payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        if (!$has_full_access) {
            if ($my_gov) {
                $join_member = "JOIN {$wpdb->prefix}sm_members m ON p.member_id = m.id";
                $where_finance .= $wpdb->prepare(" AND m.governorate = %s", $my_gov);
            } else {
                $where_finance .= " AND 1=0";
            }
        }

        $stats['financial_trends'] = $wpdb->get_results("
            SELECT DATE(payment_date) as date, SUM(amount) as total
            FROM {$wpdb->prefix}sm_payments p
            $join_member
            WHERE $where_finance
            GROUP BY DATE(payment_date)
            ORDER BY date ASC
        ");

        // Specialization Distribution
        $stats['specializations'] = $wpdb->get_results("
            SELECT specialization, COUNT(*) as count
            FROM {$wpdb->prefix}sm_members
            WHERE specialization != '' AND $where_member
            GROUP BY specialization
        ");

        // Advanced Stats
        $stats['total_service_requests'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_service_requests r
            JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
            WHERE $where_member
        ") ?: 0;

        $stats['total_executed_requests'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_service_requests r
            JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
            WHERE r.status = 'approved' AND $where_member
        ") ?: 0;

        $stats['total_update_requests'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_update_requests r
            JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
            WHERE $where_member
        ") ?: 0;

        $stats['total_membership_requests'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_membership_requests
            WHERE $where_member
        ") ?: 0;

        $stats['total_requests'] = intval($stats['total_service_requests']) + intval($stats['total_update_requests']) + intval($stats['total_membership_requests']);

        $stats['total_practice_licenses'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_members
            WHERE license_number != '' AND $where_member
        ") ?: 0;

        $stats['total_facility_licenses'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}sm_members
            WHERE facility_number != '' AND $where_member
        ") ?: 0;

        // Work permits (assumed same as practice licenses in this context)
        $stats['total_work_permits'] = $stats['total_practice_licenses'];

        return $stats;
    }
}

<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Finance_Manager {
    public static function ajax_record_payment() {
        if (!current_user_can('sm_manage_finance')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_finance_action', 'nonce');
        $mid = intval($_POST['member_id']);
        if (!SM_Member_Manager::can_access_member($mid)) {
            wp_send_json_error('Access denied');
        }
        if (SM_Finance::record_payment($_POST)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to record payment');
        }
    }

    public static function ajax_delete_transaction() {
        if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_admin_action', 'nonce');
        global $wpdb;
        $id = intval($_POST['transaction_id']);
        $wpdb->delete("{$wpdb->prefix}sm_payments", ['id' => $id]);
        SM_Logger::log('حذف عملية مالية', "تم حذف العملية رقم #$id");
        wp_send_json_success();
    }

    public static function ajax_get_member_finance_html() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        $mid = intval($_GET['member_id']);
        if (!SM_Member_Manager::can_access_member($mid)) {
            wp_send_json_error('Access denied');
        }
        $dues = SM_Finance::calculate_member_dues($mid);
        $history = SM_Finance::get_payment_history($mid);
        ob_start();
        include SM_PLUGIN_DIR . 'templates/modal-finance-details.php';
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

    public static function ajax_export_finance_report() {
        if (!current_user_can('sm_manage_finance')) {
            wp_die('Unauthorized');
        }
        $type = sanitize_text_field($_GET['type']);
        $members = SM_DB::get_members(['limit' => -1]);
        $data = [];
        foreach ($members as $m) {
            $dues = SM_Finance::calculate_member_dues($m->id);
            if ($type === 'overdue_membership' && $dues['membership_balance'] > 0) {
                $data[] = [
                    'name' => $m->name,
                    'nid' => $m->national_id,
                    'amount' => $dues['membership_balance'],
                    'details' => 'متأخرات اشتراك'
                ];
            } elseif ($type === 'unpaid_fines' && $dues['penalty_balance'] > 0) {
                $data[] = [
                    'name' => $m->name,
                    'nid' => $m->national_id,
                    'amount' => $dues['penalty_balance'],
                    'details' => 'غرامات غير مسددة'
                ];
            } elseif ($type === 'full_liabilities' && $dues['balance'] > 0) {
                $data[] = [
                    'name' => $m->name,
                    'nid' => $m->national_id,
                    'amount' => $dues['balance'],
                    'details' => 'إجمالي المديونية'
                ];
            }
        }
        $titles = [
            'overdue_membership' => 'تقرير متأخرات اشتراكات العضوية',
            'unpaid_fines' => 'تقرير الغرامات المالية غير المسددة',
            'full_liabilities' => 'تقرير المديونيات المالية الشامل'
        ];
        $title = $titles[$type] ?? "تقرير مالي";
        include SM_PLUGIN_DIR . 'templates/print-finance-report.php';
        exit;
    }

    public static function ajax_print_invoice() {
        $pid = intval($_GET['payment_id'] ?? 0);
        global $wpdb;
        $pmt = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$wpdb->prefix}sm_payments WHERE id = %d", $pid));
        if (!$pmt || !SM_Member_Manager::can_access_member($pmt->member_id)) {
            wp_die('Unauthorized');
        }
        include SM_PLUGIN_DIR . 'templates/print-invoice.php';
        exit;
    }
}

<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Messaging_Manager {
    public static function ajax_send_message() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_message_action', 'nonce');

        $sid = get_current_user_id();
        $mid = intval($_POST['member_id'] ?? 0);

        if (!$mid) {
            global $wpdb;
            $m_wp = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", $sid));
            if ($m_wp) {
                $mid = $m_wp->id;
            }
        }

        if (!SM_Member_Manager::can_access_member($mid)) {
            wp_send_json_error('Access denied');
        }

        $member = SM_DB::get_member_by_id($mid);
        if (!$member) {
            wp_send_json_error('Invalid member context');
        }

        $msg = sanitize_textarea_field($_POST['message'] ?? '');
        $rid = intval($_POST['receiver_id'] ?? 0);

        $url = null;
        if (!empty($_FILES['message_file']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            $att_id = media_handle_upload('message_file', 0);
            if (!is_wp_error($att_id)) {
                $url = wp_get_attachment_url($att_id);
            }
        }

        SM_DB::send_message($sid, $rid, $msg, $mid, $url, $member->governorate);
        wp_send_json_success();
    }

    public static function ajax_get_conversation() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_message_action', 'nonce');

        $mid = intval($_POST['member_id'] ?? 0);
        if (!$mid) {
            global $wpdb;
            $m_wp = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", get_current_user_id()));
            if ($m_wp) {
                $mid = $m_wp->id;
            }
        }

        if (!SM_Member_Manager::can_access_member($mid)) {
            wp_send_json_error('Access denied');
        }

        wp_send_json_success(SM_DB::get_ticket_messages($mid));
    }

    public static function ajax_submit_contact_form() {
        check_ajax_referer('sm_contact_action', 'nonce');

        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $subj = sanitize_text_field($_POST['subject']);
        $msg = sanitize_textarea_field($_POST['message']);

        global $wpdb;
        $member = $wpdb->get_row($wpdb->prepare("SELECT id, governorate FROM {$wpdb->prefix}sm_members WHERE email = %s", $email));
        $mid = $member ? $member->id : 0;
        $prov = $member ? $member->governorate : 'HQ';

        $ticket_data = [
            'member_id' => $mid,
            'subject' => $subj,
            'category' => 'inquiry',
            'priority' => 'medium',
            'status' => 'open',
            'province' => $prov,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        if ($wpdb->insert("{$wpdb->prefix}sm_tickets", $ticket_data)) {
            $tid = $wpdb->insert_id;
            $wpdb->insert("{$wpdb->prefix}sm_ticket_thread", [
                'ticket_id' => $tid,
                'sender_id' => is_user_logged_in() ? get_current_user_id() : 0,
                'message' => "رسالة من نموذج التواصل:\n\nالاسم: $name\nالهاتف: $phone\nالبريد: $email\n\nالرسالة:\n$msg",
                'created_at' => current_time('mysql')
            ]);
            wp_send_json_success();
        } else {
            wp_send_json_error('فشل تقديم تذكرة الدعم');
        }
    }
}

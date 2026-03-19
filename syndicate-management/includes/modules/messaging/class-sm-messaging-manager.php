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

    public static function ajax_get_conversations() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_message_action', 'nonce');
        $user = wp_get_current_user();
        $gov = get_user_meta($user->ID, 'sm_governorate', true);
        $has_full = current_user_can('sm_full_access') || current_user_can('manage_options');

        if (!$gov && !$has_full) {
            wp_send_json_error('No governorate assigned');
        }

        if (in_array('sm_syndicate_member', (array)$user->roles)) {
            $offs = SM_DB::get_governorate_officials($gov);
            $data = [];
            foreach($offs as $o) {
                $data[] = [
                    'official' => [
                        'ID' => $o->ID,
                        'display_name' => $o->display_name,
                        'avatar' => get_avatar_url($o->ID)
                    ]
                ];
            }
            wp_send_json_success(['type' => 'member_view', 'officials' => $data]);
        } else {
            $t_gov = $has_full ? null : $gov;
            $convs = SM_DB::get_governorate_conversations($t_gov);
            foreach($convs as &$c) {
                $c['member']->avatar = $c['member']->photo_url ?: get_avatar_url($c['member']->wp_user_id ?: 0);
            }
            wp_send_json_success(['type' => 'official_view', 'conversations' => $convs]);
        }
    }

    public static function ajax_mark_read() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_message_action', 'nonce');
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}sm_messages", ['is_read' => 1], ['receiver_id' => get_current_user_id(), 'sender_id' => intval($_POST['other_user_id'])]);
        wp_send_json_success();
    }

    public static function ajax_get_tickets() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_ticket_action', 'nonce');
        wp_send_json_success(SM_DB::get_tickets($_GET));
    }

    public static function ajax_create_ticket() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_ticket_action', 'nonce');
        global $wpdb;
        $member = $wpdb->get_row($wpdb->prepare("SELECT id, governorate FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", get_current_user_id()));
        if (!$member) {
            wp_send_json_error('Member profile not found');
        }
        $url = null;
        if (!empty($_FILES['attachment']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            $att_id = media_handle_upload('attachment', 0);
            if (!is_wp_error($att_id)) {
                $url = wp_get_attachment_url($att_id);
            }
        }
        $tid = SM_DB::create_ticket([
            'member_id' => $member->id,
            'subject' => sanitize_text_field($_POST['subject']),
            'category' => sanitize_text_field($_POST['category']),
            'priority' => sanitize_text_field($_POST['priority'] ?? 'medium'),
            'message' => sanitize_textarea_field($_POST['message']),
            'province' => $member->governorate,
            'file_url' => $url
        ]);
        if ($tid) {
            wp_send_json_success($tid);
        } else {
            wp_send_json_error('Failed to create ticket');
        }
    }

    public static function ajax_get_ticket_details() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_ticket_action', 'nonce');
        $id = intval($_GET['id']);
        $ticket = SM_DB::get_ticket($id);
        if (!$ticket) {
            wp_send_json_error('Ticket not found');
        }
        $user = wp_get_current_user();
        if (!current_user_can('sm_full_access') && !current_user_can('manage_options')) {
            if (in_array('sm_syndicate_admin', $user->roles)) {
                $gov = get_user_meta($user->ID, 'sm_governorate', true);
                if ($gov && $ticket->province !== $gov) {
                    wp_send_json_error('Access denied');
                }
            } else {
                global $wpdb;
                $mid = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", $user->ID));
                if ($ticket->member_id != $mid) {
                    wp_send_json_error('Access denied');
                }
            }
        }
        wp_send_json_success(array('ticket' => $ticket, 'thread' => SM_DB::get_ticket_thread($id)));
    }

    public static function ajax_add_ticket_reply() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_ticket_action', 'nonce');
        $tid = intval($_POST['ticket_id']);
        $url = null;
        if (!empty($_FILES['attachment']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            $att_id = media_handle_upload('attachment', 0);
            if (!is_wp_error($att_id)) {
                $url = wp_get_attachment_url($att_id);
            }
        }
        $rid = SM_DB::add_ticket_reply([
            'ticket_id' => $tid,
            'sender_id' => get_current_user_id(),
            'message' => sanitize_textarea_field($_POST['message']),
            'file_url' => $url
        ]);
        if ($rid) {
            if (!in_array('sm_syndicate_member', wp_get_current_user()->roles)) {
                SM_DB::update_ticket_status($tid, 'in-progress');
            }
            wp_send_json_success($rid);
        } else {
            wp_send_json_error('Failed to add reply');
        }
    }

    public static function ajax_close_ticket() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('sm_ticket_action', 'nonce');
        if (SM_DB::update_ticket_status(intval($_POST['id']), 'closed')) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to close ticket');
        }
    }
}

<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Notifications {
    public static function get_template($type) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_notification_templates WHERE template_type = %s", $type));
    }

    public static function save_template($data) {
        global $wpdb;
        return $wpdb->replace("{$wpdb->prefix}sm_notification_templates", [
            'template_type' => sanitize_text_field($data['template_type']),
            'subject' => sanitize_text_field($data['subject']),
            'body' => sanitize_textarea_field($data['body']),
            'days_before' => intval($data['days_before']),
            'is_enabled' => isset($data['is_enabled']) ? 1 : 0
        ]);
    }

    public static function send_template_notification($mid, $type, $extra = []) {
        $t = self::get_template($type);
        if (!$t || !$t->is_enabled) {
            return false;
        }

        $m = SM_DB::get_member_by_id($mid);
        if (!$m || empty($m->email)) {
            return false;
        }

        $subj = $t->subject;
        $body = $t->body;
        $pls = array_merge([
            '{member_name}' => $m->name,
            '{national_id}' => $m->national_id,
            '{membership_number}' => $m->membership_number,
            '{governorate}' => SM_Settings::get_governorates()[$m->governorate] ?? $m->governorate,
            '{year}' => date('Y')
        ], $extra);

        foreach ($pls as $s => $r) {
            $subj = str_replace($s, $r, $subj);
            $body = str_replace($s, $r, $body);
        }

        $dsgn = get_option('sm_email_design_settings', [
            'header_bg' => '#111F35',
            'header_text' => '#ffffff',
            'footer_text' => '#64748b',
            'accent_color' => '#F63049'
        ]);

        $synd = SM_Settings::get_syndicate_info();
        $html = self::wrap_in_template($subj, $body, $dsgn, $synd);
        $from = get_option('sm_noreply_email', 'noreply@irseg.org');

        add_filter('wp_mail_from', function() use ($from) { return $from; });
        add_filter('wp_mail_from_name', function() use ($synd) { return $synd['syndicate_name']; });

        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($m->email, $subj, $html, $headers);
        self::log_notification($mid, $type, $m->email, $subj, $sent ? 'success' : 'failed');

        return $sent;
    }

    private static function wrap_in_template($subj, $body, $d, $s) {
        $logo = !empty($s['syndicate_logo']) ? '<img src="'.esc_url($s['syndicate_logo']).'" style="max-height:80px; margin-bottom:15px;">' : '';
        ob_start();
        ?>
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <meta charset="UTF-8">
            <style>
                body { background:#f6f9fc; margin:0; }
                .container { max-width:600px; margin:20px auto; background:#fff; border-radius:15px; overflow:hidden; border:1px solid #e1e8ed; }
                .header { background:<?php echo $d['header_bg']; ?>; color:<?php echo $d['header_text']; ?>; padding:40px 20px; text-align:center; }
                .content { padding:40px; text-align:right; font-size:16px; line-height:1.7; }
                .footer { background:#f8fafc; padding:25px; text-align:center; font-size:12px; color:<?php echo $d['footer_text']; ?>; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <?php echo $logo; ?>
                    <h1><?php echo esc_html($s['syndicate_name']); ?></h1>
                </div>
                <div class="content">
                    <h2 style="color:<?php echo $d['accent_color']; ?>;"><?php echo esc_html($subj); ?></h2>
                    <div style="white-space:pre-line;"><?php echo esc_html($body); ?></div>
                </div>
                <div class="footer">
                    <p><?php echo esc_html($s['syndicate_name']); ?></p>
                    <p><?php echo esc_html($s['address']); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    private static function log_notification($mid, $type, $email, $subj, $status) {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}sm_notification_logs", [
            'member_id' => $mid,
            'notification_type' => $type,
            'recipient_email' => $email,
            'subject' => $subj,
            'status' => $status,
            'sent_at' => current_time('mysql')
        ]);
    }

    /**
     * run_daily_checks
     * Main entry point for cron maintenance
     */
    public static function run_daily_checks() {
        self::check_membership_renewals();
        self::check_license_expirations();
        self::check_payment_dues();
    }

    private static function check_membership_renewals() {
        $t = self::get_template('membership_renewal');
        if (!$t || !$t->is_enabled) {
            return;
        }
        global $wpdb;
        $cy = date('Y');
        $ms = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_members WHERE last_paid_membership_year < %d", $cy));
        foreach ($ms as $m) {
            if (!self::already_notified($m->id, 'membership_renewal', 25)) {
                self::send_template_notification($m->id, 'membership_renewal', ['{year}' => $cy]);
            }
        }
    }

    private static function check_license_expirations() {
        $types = ['license_practice', 'license_facility'];
        global $wpdb;
        foreach ($types as $type) {
            $t = self::get_template($type);
            if (!$t || !$t->is_enabled) {
                continue;
            }
            $tar = date('Y-m-d', strtotime("+{$t->days_before} days"));
            $f = ($type === 'license_practice') ? 'license_expiration_date' : 'facility_license_expiration_date';
            $ms = $wpdb->get_results($wpdb->prepare("SELECT id, $f as exp, facility_name FROM {$wpdb->prefix}sm_members WHERE $f = %s", $tar));
            foreach ($ms as $m) {
                if (!self::already_notified($m->id, $type, 5)) {
                    self::send_template_notification($m->id, $type, ['{expiry_date}' => $m->exp, '{facility_name}' => $m->facility_name ?? '']);
                }
            }
        }
    }

    private static function check_payment_dues() {
        $t = self::get_template('payment_reminder');
        if (!$t || !$t->is_enabled) {
            return;
        }
        $ms = SM_DB::get_members(['limit' => -1]);
        foreach ($ms as $m) {
            $dues = SM_Finance::calculate_member_dues($m->id);
            if ($dues['balance'] > 500 && !self::already_notified($m->id, 'payment_reminder', 30)) {
                self::send_template_notification($m->id, 'payment_reminder', ['{balance}' => $dues['balance']]);
            }
        }
    }

    private static function already_notified($mid, $type, $limit) {
        global $wpdb;
        $last = $wpdb->get_var($wpdb->prepare("SELECT sent_at FROM {$wpdb->prefix}sm_notification_logs WHERE member_id = %d AND notification_type = %s ORDER BY sent_at DESC LIMIT 1", $mid, $type));
        if (!$last) {
            return false;
        }
        return (strtotime($last) > strtotime("-$limit days"));
    }

    public static function get_logs($limit = 100, $offset = 0) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT l.*, m.name as member_name FROM {$wpdb->prefix}sm_notification_logs l LEFT JOIN {$wpdb->prefix}sm_members m ON l.member_id = m.id ORDER BY l.sent_at DESC LIMIT %d OFFSET %d", $limit, $offset));
    }
}

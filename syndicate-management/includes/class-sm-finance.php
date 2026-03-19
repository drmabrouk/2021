<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_Finance {

    public static function calculate_member_dues($member_id) {
        global $wpdb;
        $member = SM_DB::get_member_by_id($member_id);
        if (!$member) {
            return array(
                'total_owed' => 0,
                'total_paid' => 0,
                'balance' => 0,
                'membership_balance' => 0,
                'penalty_balance' => 0,
                'breakdown' => []
            );
        }

        $settings = SM_Settings::get_finance_settings();
        $current_year = (int)date('Y');
        $current_date = date('Y-m-d');

        $total_owed = 0;
        $membership_owed = 0;
        $penalty_owed = 0;
        $breakdown = [];

        // 1. Membership Dues
        $p_start = $member->membership_start_date ? strtotime($member->membership_start_date) : false;
        $start_year = ($p_start !== false && $p_start > 0) ? (int)date('Y', $p_start) : $current_year;

        if ($start_year < 1980 || $start_year > $current_year) {
            $start_year = $current_year;
        }

        $last_paid_year = (int)$member->last_paid_membership_year;

        for ($year = $start_year; $year <= $current_year; $year++) {
            if ($year > $last_paid_year) {
                $is_reg = ($year === $start_year && $last_paid_year == 0);
                $base = $is_reg ? (float)$settings['membership_new'] : (float)$settings['membership_renewal'];
                $penalty = 0;

                if (!$is_reg && $current_date >= $year . '-04-01') {
                    $penalty = (float)$settings['membership_penalty'];
                }

                $year_total = $base + $penalty;
                $total_owed += $year_total;
                $membership_owed += $base;
                $penalty_owed += $penalty;

                $breakdown[] = [
                    'item' => ($year === $start_year) ? "رسوم انضمام وعضوية لعام $year" : "تجديد عضوية لعام $year",
                    'amount' => $base,
                    'penalty' => $penalty,
                    'total' => $year_total
                ];
            }
        }

        // 2. Professional Practice License Dues
        if (!empty($member->license_number) && !empty($member->license_expiration_date)) {
            $exp = $member->license_expiration_date;
            $has_paid = ((int)$member->last_paid_license_year > 0);

            if ($current_date > $exp || !$has_paid) {
                $base = $has_paid ? (float)$settings['license_renewal'] : (float)$settings['license_new'];
                $penalty = 0;

                if ($current_date >= date('Y-m-d', strtotime($exp . ' +1 year'))) {
                    try {
                        $d1 = new DateTime($exp);
                        $d2 = new DateTime($current_date);
                        $diff = $d1->diff($d2);
                        if ($diff->y >= 1) {
                            $penalty = $diff->y * (float)$settings['license_penalty'];
                        }
                    } catch (Exception $e) {}
                }

                $license_total = $base + $penalty;
                $total_owed += $license_total;
                $penalty_owed += $penalty; // Adding license penalty to total penalty balance

                $breakdown[] = [
                    'item' => "تجديد تصريح مزاولة المهنة",
                    'amount' => $base,
                    'penalty' => $penalty,
                    'total' => $license_total
                ];
            }
        }

        // 4. Digital Services Fees
        $svc_fees = $wpdb->get_results($wpdb->prepare(
            "SELECT r.fees_paid, s.name
             FROM {$wpdb->prefix}sm_service_requests r
             JOIN {$wpdb->prefix}sm_services s ON r.service_id = s.id
             WHERE r.member_id = %d AND r.status = 'approved' AND r.fees_paid > 0",
            $member_id
        ));

        foreach ($svc_fees as $sf) {
            $total_owed += (float)$sf->fees_paid;
            $breakdown[] = [
                'item' => "رسوم خدمة: " . $sf->name,
                'amount' => (float)$sf->fees_paid,
                'penalty' => 0,
                'total' => (float)$sf->fees_paid
            ];
        }

        $total_paid = self::get_total_paid($member_id);
        $balance = $total_owed - $total_paid;

        // Pro-rate sub-balances if partially paid
        $membership_balance = $membership_owed;
        $penalty_balance = $penalty_owed;

        // Simple logic: payments cover penalties first, then membership
        $remaining_paid = $total_paid;
        if ($remaining_paid > 0) {
            $deduct_penalty = min($remaining_paid, $penalty_balance);
            $penalty_balance -= $deduct_penalty;
            $remaining_paid -= $deduct_penalty;
        }
        if ($remaining_paid > 0) {
            $deduct_membership = min($remaining_paid, $membership_balance);
            $membership_balance -= $deduct_membership;
        }

        return [
            'total_owed' => (float)$total_owed,
            'total_paid' => (float)$total_paid,
            'balance' => (float)$balance,
            'membership_balance' => (float)$membership_balance,
            'penalty_balance' => (float)$penalty_balance,
            'breakdown' => $breakdown
        ];
    }

    public static function get_total_paid($member_id) {
        global $wpdb;
        $sum = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}sm_payments WHERE member_id = %d",
            $member_id
        ));
        return (float)$sum;
    }

    public static function get_payment_history($member_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sm_payments WHERE member_id = %d ORDER BY payment_date DESC",
            $member_id
        ));
    }

    public static function record_payment($data) {
        global $wpdb;
        $cur_uid = get_current_user_id();
        $member = SM_DB::get_member_by_id($data['member_id']);
        $gov = $member->governorate ?? 'generic';
        $prefix = SM_Settings::get_governorate_prefix($gov);

        $cy = date('Y');
        $lseq = (int)get_option('sm_invoice_sequence_' . $cy, 0);
        $nseq = $lseq + 1;
        update_option('sm_invoice_sequence_' . $cy, $nseq);

        $dcode = $prefix . '-' . $cy . str_pad($nseq, 5, '0', STR_PAD_LEFT);

        $ins = $wpdb->insert($wpdb->prefix . 'sm_payments', [
            'member_id' => intval($data['member_id']),
            'amount' => floatval($data['amount']),
            'payment_type' => sanitize_text_field($data['payment_type']),
            'payment_date' => sanitize_text_field($data['payment_date']),
            'target_year' => isset($data['target_year']) ? intval($data['target_year']) : null,
            'digital_invoice_code' => $dcode,
            'paper_invoice_code' => sanitize_text_field($data['paper_invoice_code'] ?? ''),
            'details_ar' => sanitize_text_field($data['details_ar'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_by' => $cur_uid,
            'created_at' => current_time('mysql')
        ]);

        if ($ins) {
            $pid = $wpdb->insert_id;
            if ($data['payment_type'] === 'membership' && !empty($data['target_year'])) {
                if (intval($data['target_year']) > intval($member->last_paid_membership_year)) {
                    SM_DB::update_member($member->id, ['last_paid_membership_year' => intval($data['target_year'])]);
                }
            }
            if ($data['payment_type'] === 'license' && !empty($data['target_year'])) {
                if (intval($data['target_year']) > intval($member->last_paid_license_year)) {
                    SM_DB::update_member($member->id, ['last_paid_license_year' => intval($data['target_year'])]);
                }
            }

            SM_Logger::log('عملية مالية', "تحصيل مبلغ " . $data['amount'] . " ج.م مقابل " . $data['details_ar'] . " للعضو: " . $member->name);
            self::deliver_invoice($pid);

            SM_DB::add_document([
                'member_id' => $data['member_id'],
                'category' => 'receipts',
                'title' => "إيصال سداد رقم " . $dcode,
                'file_url' => admin_url('admin-ajax.php?action=sm_print_invoice&payment_id=' . $pid),
                'file_type' => 'application/pdf'
            ]);
        }
        return $ins;
    }

    public static function deliver_invoice($pid) {
        global $wpdb;
        $pmt = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_payments WHERE id = %d", $pid));
        if (!$pmt) {
            return;
        }

        $m = SM_DB::get_member_by_id($pmt->member_id);
        if (!$m || empty($m->email)) {
            return;
        }

        $synd = SM_Settings::get_syndicate_info();
        $url = admin_url('admin-ajax.php?action=sm_print_invoice&payment_id=' . $pid);

        $subject = "فاتورة سداد إلكترونية - " . $synd['syndicate_name'];
        $message = "عزيزي " . $m->name . ",\n\n";
        $message .= "تم استلام مبلغ " . $pmt->amount . " ج.م بنجاح.\n";
        $message .= "نوع العملية: " . $pmt->payment_type . "\n";
        $message .= "يمكنك استعراض الفاتورة من: " . $url . "\n\n";
        $message .= "شكراً لتعاونكم.\n";
        $message .= $synd['syndicate_name'];

        wp_mail($m->email, $subject, $message);
    }

    public static function get_member_status($mid) {
        $m = SM_DB::get_member_by_id($mid);
        if (!$m) {
            return 'unknown';
        }
        $cy = (int)date('Y');
        $cd = date('Y-m-d');
        $lp = (int)$m->last_paid_membership_year;

        if ($lp >= $cy) {
            return 'نشط (مسدد لعام ' . $cy . ')';
        }
        if ($cd <= $cy . '-03-31') {
            return 'في فترة السماح (يجب التجديد لعام ' . $cy . ')';
        }
        return 'منتهي (متأخر عن سداد عام ' . $cy . ')';
    }

    public static function get_financial_stats() {
        global $wpdb;
        $u = wp_get_current_user();
        $has_full = current_user_can('sm_full_access') || current_user_can('manage_options');
        $gov = get_user_meta($u->ID, 'sm_governorate', true);

        $w_m = "1=1";
        if (!$has_full && $gov) {
            $w_m = $wpdb->prepare("governorate = %s", $gov);
        }

        $j_p = "";
        $w_p = "1=1";
        if (!$has_full && $gov) {
            $j_p = "JOIN {$wpdb->prefix}sm_members m ON p.member_id = m.id";
            $w_p = $wpdb->prepare("m.governorate = %s", $gov);
        }

        $paid = $wpdb->get_var("SELECT SUM(p.amount) FROM {$wpdb->prefix}sm_payments p $j_p WHERE $w_p") ?: 0;
        $members = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}sm_members WHERE $w_m LIMIT 250");

        $owed = 0;
        $penalty = 0;
        foreach ($members as $m) {
            $dues = self::calculate_member_dues($m->id);
            $owed += $dues['total_owed'];
            foreach ($dues['breakdown'] as $i) {
                if (!empty($i['penalty'])) {
                    $penalty += $i['penalty'];
                }
            }
        }
        return [
            'total_owed' => (float)$owed,
            'total_paid' => (float)$paid,
            'total_balance' => max(0, (float)$owed - (float)$paid),
            'total_penalty' => (float)$penalty
        ];
    }

    public static function get_top_delayed_members($limit = 10) {
        global $wpdb;
        $cy = (int)date('Y');
        $ms = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_members WHERE last_paid_membership_year < %d LIMIT 200", $cy));
        $delayed = [];

        foreach ($ms as $m) {
            $dues = self::calculate_member_dues($m->id);
            if ($dues['balance'] > 0) {
                $lp = (int)$m->last_paid_membership_year ?: ((int)date('Y', strtotime($m->registration_date)) - 1);
                $delayed[] = [
                    'id' => $m->id,
                    'name' => $m->name,
                    'governorate' => $m->governorate,
                    'balance' => $dues['balance'],
                    'delay_years' => $cy - $lp
                ];
            }
        }

        usort($delayed, function($a, $b) {
            if ($b['balance'] == $a['balance']) {
                return $b['delay_years'] <=> $a['delay_years'];
            }
            return $b['balance'] <=> $a['balance'];
        });

        return array_slice($delayed, 0, $limit);
    }
}

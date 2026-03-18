<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Legacy Compatibility Layer for SM_DB
 * This class delegates all calls to the new specialized database classes.
 * Maintaining this class ensures that existing template files and third-party
 * integrations (if any) continue to work without modification.
 */
class SM_DB {

    // Member & Staff Delegation
    public static function get_staff($args = []) {
        return SM_DB_Members::get_staff($args);
    }

    public static function get_members($args = []) {
        return SM_DB_Members::get_members($args);
    }

    public static function get_member_by_id($id) {
        return SM_DB_Members::get_member_by_id($id);
    }

    public static function get_member_by_national_id($nid) {
        return SM_DB_Members::get_member_by_national_id($nid);
    }

    public static function get_member_by_membership_number($num) {
        return SM_DB_Members::get_member_by_membership_number($num);
    }

    public static function get_member_by_license_number($num) {
        return SM_DB_Members::get_member_by_license_number($num);
    }

    public static function get_member_by_facility_number($num) {
        return SM_DB_Members::get_member_by_facility_number($num);
    }

    public static function get_member_by_username($user) {
        return SM_DB_Members::get_member_by_username($user);
    }

    public static function add_member($data) {
        return SM_DB_Members::add_member($data);
    }

    public static function update_member($id, $data) {
        return SM_DB_Members::update_member($id, $data);
    }

    public static function update_member_photo($id, $url) {
        return SM_DB_Members::update_member_photo($id, $url);
    }

    public static function delete_member($id) {
        return SM_DB_Members::delete_member($id);
    }

    public static function member_exists($nid) {
        return SM_DB_Members::member_exists($nid);
    }

    public static function get_next_sort_order() {
        return SM_DB_Members::get_next_sort_order();
    }

    public static function add_membership_request($data) {
        return SM_DB_Members::add_membership_request($data);
    }

    public static function update_membership_request($id, $data) {
        return SM_DB_Members::update_membership_request($id, $data);
    }

    public static function get_membership_request($id) {
        return SM_DB_Members::get_membership_request($id);
    }

    public static function get_membership_requests($status = null) {
        return SM_DB_Members::get_membership_requests($status);
    }

    public static function add_update_request($mid, $data) {
        return SM_DB_Members::add_update_request($mid, $data);
    }

    public static function get_update_requests($status = 'pending') {
        return SM_DB_Members::get_update_requests($status);
    }

    public static function process_update_request($id, $status) {
        return SM_DB_Members::process_update_request($id, $status);
    }

    // Service & Professional Delegation
    public static function get_services($args = []) {
        return SM_DB_Services::get_services($args);
    }

    public static function add_service($data) {
        return SM_DB_Services::add_service($data);
    }

    public static function update_service($id, $data) {
        return SM_DB_Services::update_service($id, $data);
    }

    public static function delete_service($id, $perm = false) {
        return SM_DB_Services::delete_service($id, $perm);
    }

    public static function restore_service($id) {
        return SM_DB_Services::restore_service($id);
    }

    public static function submit_service_request($data) {
        return SM_DB_Services::submit_service_request($data);
    }

    public static function get_service_requests($args = []) {
        return SM_DB_Services::get_service_requests($args);
    }

    public static function update_service_request_status($id, $status, $fees = null, $notes = '') {
        return SM_DB_Services::update_service_request_status($id, $status, $fees, $notes);
    }

    public static function add_professional_request($mid, $type) {
        return SM_DB_Services::add_professional_request($mid, $type);
    }

    public static function get_professional_requests($args = []) {
        return SM_DB_Services::get_professional_requests($args);
    }

    public static function process_professional_request($id, $status, $notes = '') {
        return SM_DB_Services::process_professional_request($id, $status, $notes);
    }

    // Finance Delegation
    public static function get_statistics($filters = []) {
        return SM_DB_Finance::get_statistics($filters);
    }

    // Communications Delegation
    public static function send_message($sid, $rid, $msg, $mid = null, $url = null, $gov = null) {
        return SM_DB_Communications::send_message($sid, $rid, $msg, $mid, $url, $gov);
    }

    public static function get_ticket_messages($mid) {
        return SM_DB_Communications::get_ticket_messages($mid);
    }

    public static function get_governorate_officials($gov) {
        return SM_DB_Communications::get_governorate_officials($gov);
    }

    public static function get_governorate_conversations($gov = null) {
        return SM_DB_Communications::get_governorate_conversations($gov);
    }

    public static function get_conversation_messages($u1, $u2) {
        return SM_DB_Communications::get_conversation_messages($u1, $u2);
    }

    public static function get_sent_messages($uid) {
        return SM_DB_Communications::get_sent_messages($uid);
    }

    public static function get_conversations($uid) {
        return SM_DB_Communications::get_conversations($uid);
    }

    public static function delete_expired_messages() {
        return SM_DB_Communications::delete_expired_messages();
    }

    public static function create_ticket($data) {
        return SM_DB_Communications::create_ticket($data);
    }

    public static function add_ticket_reply($data) {
        return SM_DB_Communications::add_ticket_reply($data);
    }

    public static function get_tickets($args = []) {
        return SM_DB_Communications::get_tickets($args);
    }

    public static function get_ticket($id) {
        return SM_DB_Communications::get_ticket($id);
    }

    public static function get_ticket_thread($tid) {
        return SM_DB_Communications::get_ticket_thread($tid);
    }

    public static function update_ticket_status($id, $status) {
        return SM_DB_Communications::update_ticket_status($id, $status);
    }

    // Education Delegation
    public static function add_survey($title, $q, $rec, $uid, $spec = '', $type = 'practice') {
        return SM_DB_Education::add_survey($title, $q, $rec, $uid, $spec, $type);
    }

    public static function get_surveys($uid, $role, $spec = '') {
        return SM_DB_Education::get_surveys($uid, $role, $spec);
    }

    public static function save_survey_response($sid, $uid, $res) {
        return SM_DB_Education::save_survey_response($sid, $uid, $res);
    }

    public static function get_survey($id) {
        return SM_DB_Education::get_survey($id);
    }

    public static function get_survey_results($sid) {
        return SM_DB_Education::get_survey_results($sid);
    }

    public static function get_survey_responses($sid) {
        return SM_DB_Education::get_survey_responses($sid);
    }

    public static function assign_test($tid, $uid) {
        return SM_DB_Education::assign_test($tid, $uid);
    }

    public static function get_test_assignments($tid = null) {
        return SM_DB_Education::get_test_assignments($tid);
    }

    // System & Docs Delegation
    public static function add_document($data) {
        return SM_DB_System::add_document($data);
    }

    public static function get_member_documents($mid, $args = []) {
        return SM_DB_System::get_member_documents($mid, $args);
    }

    public static function delete_document($id) {
        return SM_DB_System::delete_document($id);
    }

    public static function log_document_action($id, $action) {
        return SM_DB_System::log_document_action($id, $action);
    }

    public static function get_document_logs($id) {
        return SM_DB_System::get_document_logs($id);
    }

    public static function save_pub_template($data) {
        return SM_DB_System::save_pub_template($data);
    }

    public static function get_pub_templates() {
        return SM_DB_System::get_pub_templates();
    }

    public static function get_pub_template($id) {
        return SM_DB_System::get_pub_template($id);
    }

    public static function generate_pub_document($data) {
        return SM_DB_System::generate_pub_document($data);
    }

    public static function get_pub_documents($args = []) {
        return SM_DB_System::get_pub_documents($args);
    }

    public static function get_pub_document_by_serial($s) {
        return SM_DB_System::get_pub_document_by_serial($s);
    }

    public static function increment_pub_download($id, $f) {
        return SM_DB_System::increment_pub_download($id, $f);
    }

    public static function save_alert($data) {
        return SM_DB_System::save_alert($data);
    }

    public static function get_alerts($args = []) {
        return SM_DB_System::get_alerts($args);
    }

    public static function get_alert($id) {
        return SM_DB_System::get_alert($id);
    }

    public static function delete_alert($id) {
        return SM_DB_System::delete_alert($id);
    }

    public static function get_active_alerts_for_user($uid) {
        return SM_DB_System::get_active_alerts_for_user($uid);
    }

    public static function acknowledge_alert($aid, $uid) {
        return SM_DB_System::acknowledge_alert($aid, $uid);
    }

    public static function get_branches_data() {
        return SM_DB_System::get_branches_data();
    }

    public static function save_branch($data) {
        return SM_DB_System::save_branch($data);
    }

    public static function delete_branch($id) {
        return SM_DB_System::delete_branch($id);
    }

    public static function get_backup_data() {
        return SM_DB_System::get_backup_data();
    }

    public static function restore_backup($j) {
        return SM_DB_System::restore_backup($j);
    }

    /**
     * get_pending_reports_count
     * Placeholder for dashboard stats
     */
    public static function get_pending_reports_count() {
        return 0;
    }
}

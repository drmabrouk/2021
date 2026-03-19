<?php

class Syndicate_Management {
    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->plugin_name = 'syndicate-management';
        $this->version = SM_VERSION;
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        // Core Framework
        require_once SM_PLUGIN_DIR . 'includes/core/class-sm-loader.php';
        require_once SM_PLUGIN_DIR . 'includes/core/class-sm-settings.php';
        require_once SM_PLUGIN_DIR . 'includes/core/class-sm-logger.php';

        // Database Layer
        require_once SM_PLUGIN_DIR . 'includes/database/class-sm-db-members.php';
        require_once SM_PLUGIN_DIR . 'includes/database/class-sm-db-services.php';
        require_once SM_PLUGIN_DIR . 'includes/database/class-sm-db-finance.php';
        require_once SM_PLUGIN_DIR . 'includes/database/class-sm-db-communications.php';
        require_once SM_PLUGIN_DIR . 'includes/database/class-sm-db-education.php';
        require_once SM_PLUGIN_DIR . 'includes/database/class-sm-db-system.php';
        require_once SM_PLUGIN_DIR . 'includes/class-sm-db.php'; // Legacy Wrapper

        // Utilities & Shared logic
        require_once SM_PLUGIN_DIR . 'includes/class-sm-finance.php';
        require_once SM_PLUGIN_DIR . 'includes/class-sm-notifications.php';

        // Functional Modules
        require_once SM_PLUGIN_DIR . 'includes/modules/auth/class-sm-auth.php';
        require_once SM_PLUGIN_DIR . 'includes/modules/members/class-sm-member-manager.php';
        require_once SM_PLUGIN_DIR . 'includes/modules/services/class-sm-service-manager.php';
        require_once SM_PLUGIN_DIR . 'includes/modules/finance/class-sm-finance-manager.php';
        require_once SM_PLUGIN_DIR . 'includes/modules/licenses/class-sm-license-manager.php';
        require_once SM_PLUGIN_DIR . 'includes/modules/messaging/class-sm-messaging-manager.php';
        require_once SM_PLUGIN_DIR . 'includes/modules/education/class-sm-education-manager.php';
        require_once SM_PLUGIN_DIR . 'includes/modules/system/class-sm-system-manager.php';

        // Controllers
        require_once SM_PLUGIN_DIR . 'admin/class-sm-admin.php';
        require_once SM_PLUGIN_DIR . 'public/class-sm-public.php';

        $this->loader = new SM_Loader();
    }

    private function define_admin_hooks() {
        $plugin_admin = new SM_Admin($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_menu_pages');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
    }

    private function define_public_hooks() {
        $plugin_public = new SM_Public($this->get_plugin_name(), $this->get_version());
        $this->loader->add_filter('show_admin_bar', $plugin_public, 'hide_admin_bar_for_non_admins');
        $this->loader->add_action('admin_init', $plugin_public, 'restrict_admin_access');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_footer', $plugin_public, 'inject_global_alerts');
        $this->loader->add_action('init', $plugin_public, 'register_shortcodes');
        $this->loader->add_action('template_redirect', $plugin_public, 'handle_form_submission');
        $this->loader->add_action('wp_login_failed', $plugin_public, 'login_failed');
        $this->loader->add_action('wp_login', $plugin_public, 'log_successful_login', 10, 2);

        // Map AJAX Hooks to Modules
        $ajax_map = [
            'sm_get_member' => ['SM_Member_Manager', 'ajax_get_member'],
            'sm_search_members' => ['SM_Member_Manager', 'ajax_search_members'],
            'sm_add_member_ajax' => ['SM_Member_Manager', 'ajax_add_member'],
            'sm_update_member_ajax' => ['SM_Member_Manager', 'ajax_update_member'],
            'sm_delete_member_ajax' => ['SM_Member_Manager', 'ajax_delete_member'],
            'sm_update_member_account_ajax' => ['SM_Member_Manager', 'ajax_update_member_account'],
            'sm_process_membership_request' => ['SM_Member_Manager', 'ajax_process_membership_request'],

            'sm_add_service' => ['SM_Service_Manager', 'ajax_add_service'],
            'sm_submit_service_request' => ['SM_Service_Manager', 'ajax_submit_service_request'],
            'sm_process_service_request' => ['SM_Service_Manager', 'ajax_process_service_request'],
            'sm_track_service_request' => ['SM_Service_Manager', 'ajax_track_service_request'],

            'sm_record_payment_ajax' => ['SM_Finance_Manager', 'ajax_record_payment'],
            'sm_delete_transaction_ajax' => ['SM_Finance_Manager', 'ajax_delete_transaction'],
            'sm_get_member_finance_html' => ['SM_Finance_Manager', 'ajax_get_member_finance_html'],
            'sm_export_finance_report' => ['SM_Finance_Manager', 'ajax_export_finance_report'],

            'sm_update_license_ajax' => ['SM_License_Manager', 'ajax_update_license'],
            'sm_update_facility_ajax' => ['SM_License_Manager', 'ajax_update_facility'],
            'sm_verify_document' => ['SM_License_Manager', 'ajax_verify_document'],

            'sm_send_message_ajax' => ['SM_Messaging_Manager', 'ajax_send_message'],
            'sm_get_conversation_ajax' => ['SM_Messaging_Manager', 'ajax_get_conversation'],
            'sm_submit_contact_form' => ['SM_Messaging_Manager', 'ajax_submit_contact_form'],

            'sm_add_survey' => ['SM_Education_Manager', 'ajax_add_survey'],
            'sm_update_survey' => ['SM_Education_Manager', 'ajax_update_survey'],
            'sm_add_test_question' => ['SM_Education_Manager', 'ajax_add_test_question'],
            'sm_delete_test_question' => ['SM_Education_Manager', 'ajax_delete_test_question'],
            'sm_assign_test' => ['SM_Education_Manager', 'ajax_assign_test'],
            'sm_submit_survey_response' => ['SM_Education_Manager', 'ajax_submit_survey_response'],

            'sm_save_branch' => ['SM_System_Manager', 'ajax_save_branch'],
            'sm_delete_branch' => ['SM_System_Manager', 'ajax_delete_branch'],
            'sm_save_alert' => ['SM_System_Manager', 'ajax_save_alert'],
            'sm_reset_system_ajax' => ['SM_System_Manager', 'ajax_reset_system'],
            'sm_rollback_log_ajax' => ['SM_System_Manager', 'ajax_rollback_log'],

            'sm_forgot_password_otp' => ['SM_Auth', 'ajax_forgot_password_otp'],
            'sm_reset_password_otp' => ['SM_Auth', 'ajax_reset_password_otp'],
            'sm_activate_account_step1' => ['SM_Auth', 'ajax_activate_account_step1'],
            'sm_activate_account_final' => ['SM_Auth', 'ajax_activate_account_final'],
            'sm_submit_membership_request' => ['SM_Auth', 'ajax_submit_membership_request'],
        ];

        foreach ($ajax_map as $action => $callback) {
            $this->loader->add_action('wp_ajax_' . $action, $callback[0], $callback[1]);
            if (in_array($action, ['sm_submit_contact_form', 'sm_verify_document', 'sm_submit_service_request', 'sm_track_service_request', 'sm_forgot_password_otp', 'sm_reset_password_otp', 'sm_activate_account_step1', 'sm_activate_account_final', 'sm_submit_membership_request'])) {
                $this->loader->add_action('wp_ajax_nopriv_' . $action, $callback[0], $callback[1]);
            }
        }

        // Remaining hooks to Public controller for now (Rendering logic)
        $this->loader->add_action('wp_ajax_sm_refresh_dashboard', $plugin_public, 'ajax_refresh_dashboard');
        $this->loader->add_action('wp_ajax_sm_update_member_photo', 'SM_Member_Manager', 'ajax_update_member_photo');
        $this->loader->add_action('wp_ajax_sm_get_conversations_ajax', 'SM_Messaging_Manager', 'ajax_get_conversations');
        $this->loader->add_action('wp_ajax_sm_mark_read', 'SM_Messaging_Manager', 'ajax_mark_read');
        $this->loader->add_action('wp_ajax_sm_get_tickets', 'SM_Messaging_Manager', 'ajax_get_tickets');
        $this->loader->add_action('wp_ajax_sm_create_ticket', 'SM_Messaging_Manager', 'ajax_create_ticket');
        $this->loader->add_action('wp_ajax_sm_get_ticket_details', 'SM_Messaging_Manager', 'ajax_get_ticket_details');
        $this->loader->add_action('wp_ajax_sm_add_ticket_reply', 'SM_Messaging_Manager', 'ajax_add_ticket_reply');
        $this->loader->add_action('wp_ajax_sm_close_ticket', 'SM_Messaging_Manager', 'ajax_close_ticket');
        $this->loader->add_action('wp_ajax_sm_print', 'SM_Member_Manager', 'handle_print');
        $this->loader->add_action('wp_ajax_sm_get_counts_ajax', 'SM_System_Manager', 'ajax_get_counts');
        $this->loader->add_action('wp_ajax_sm_add_staff_ajax', 'SM_Member_Manager', 'ajax_add_staff');
        $this->loader->add_action('wp_ajax_sm_update_staff_ajax', 'SM_Member_Manager', 'ajax_update_staff');
        $this->loader->add_action('wp_ajax_sm_delete_staff_ajax', 'SM_Member_Manager', 'ajax_delete_staff');
        $this->loader->add_action('wp_ajax_sm_bulk_delete_users_ajax', 'SM_Member_Manager', 'ajax_bulk_delete_users');
        $this->loader->add_action('wp_ajax_sm_cancel_survey', 'SM_Education_Manager', 'ajax_cancel_survey');
        $this->loader->add_action('wp_ajax_sm_get_survey_results', 'SM_Education_Manager', 'ajax_get_survey_results');
        $this->loader->add_action('wp_ajax_sm_export_survey_results', 'SM_Education_Manager', 'ajax_export_survey_results');
        $this->loader->add_action('wp_ajax_sm_delete_gov_data_ajax', 'SM_System_Manager', 'ajax_delete_gov_data');
        $this->loader->add_action('wp_ajax_sm_merge_gov_data_ajax', 'SM_System_Manager', 'ajax_merge_gov_data');
        $this->loader->add_action('wp_ajax_sm_delete_log', 'SM_System_Manager', 'ajax_delete_log');
        $this->loader->add_action('wp_ajax_sm_clear_all_logs', 'SM_System_Manager', 'ajax_clear_all_logs');
        $this->loader->add_action('wp_ajax_sm_get_user_role', $plugin_public, 'ajax_get_user_role');
        $this->loader->add_action('wp_ajax_sm_update_service', 'SM_Service_Manager', 'ajax_update_service');
        $this->loader->add_action('wp_ajax_sm_get_services_html', 'SM_Service_Manager', 'ajax_get_services_html');
        $this->loader->add_action('wp_ajax_sm_delete_service', 'SM_Service_Manager', 'ajax_delete_service');
        $this->loader->add_action('wp_ajax_sm_restore_service', 'SM_Service_Manager', 'ajax_restore_service');
        $this->loader->add_action('wp_ajax_sm_print_license', 'SM_License_Manager', 'ajax_print_license');
        $this->loader->add_action('wp_ajax_sm_print_facility', 'SM_License_Manager', 'ajax_print_facility');
        $this->loader->add_action('wp_ajax_sm_print_invoice', 'SM_Finance_Manager', 'ajax_print_invoice');
        $this->loader->add_action('wp_ajax_sm_print_service_request', 'SM_Service_Manager', 'ajax_print_service_request');
        $this->loader->add_action('wp_ajax_sm_submit_update_request_ajax', 'SM_Member_Manager', 'ajax_submit_update_request_ajax');
        $this->loader->add_action('wp_ajax_sm_process_update_request_ajax', 'SM_Member_Manager', 'ajax_process_update_request_ajax');
        $this->loader->add_action('wp_ajax_sm_submit_professional_request', 'SM_Member_Manager', 'ajax_submit_professional_request');
        $this->loader->add_action('wp_ajax_sm_process_professional_request', 'SM_Member_Manager', 'ajax_process_professional_request');
        $this->loader->add_action('wp_ajax_nopriv_sm_track_membership_request', 'SM_Member_Manager', 'ajax_track_membership_request');
        $this->loader->add_action('wp_ajax_sm_track_membership_request', 'SM_Member_Manager', 'ajax_track_membership_request');
        $this->loader->add_action('wp_ajax_nopriv_sm_submit_membership_request_stage3', 'SM_Member_Manager', 'ajax_submit_membership_request_stage3');
        $this->loader->add_action('wp_ajax_sm_submit_membership_request_stage3', 'SM_Member_Manager', 'ajax_submit_membership_request_stage3');
        $this->loader->add_action('wp_ajax_sm_get_template_ajax', 'SM_Notifications', 'ajax_get_template_ajax');
        $this->loader->add_action('wp_ajax_sm_upload_document', 'SM_Member_Manager', 'ajax_upload_document');
        $this->loader->add_action('wp_ajax_sm_get_documents', 'SM_Member_Manager', 'ajax_get_documents');
        $this->loader->add_action('wp_ajax_sm_delete_document', 'SM_Member_Manager', 'ajax_delete_document');
        $this->loader->add_action('wp_ajax_sm_get_document_logs', 'SM_Member_Manager', 'ajax_get_document_logs');
        $this->loader->add_action('wp_ajax_sm_log_document_view', 'SM_Member_Manager', 'ajax_log_document_view');
        $this->loader->add_action('wp_ajax_sm_get_pub_template', 'SM_System_Manager', 'ajax_get_pub_template');
        $this->loader->add_action('wp_ajax_sm_generate_pub_doc', 'SM_System_Manager', 'ajax_generate_pub_doc');
        $this->loader->add_action('wp_ajax_sm_print_pub_doc', 'SM_System_Manager', 'ajax_print_pub_doc');
        $this->loader->add_action('wp_ajax_sm_save_pub_identity', 'SM_System_Manager', 'ajax_save_pub_identity');
        $this->loader->add_action('wp_ajax_sm_save_pub_template', 'SM_System_Manager', 'ajax_save_pub_template');
        $this->loader->add_action('wp_ajax_sm_delete_alert', 'SM_System_Manager', 'ajax_delete_alert');
        $this->loader->add_action('wp_ajax_sm_acknowledge_alert', 'SM_System_Manager', 'ajax_acknowledge_alert');
        $this->loader->add_action('wp_ajax_sm_delete_branch', 'SM_System_Manager', 'ajax_delete_branch');
        $this->loader->add_action('wp_ajax_sm_export_branches', 'SM_System_Manager', 'ajax_export_branches');
        $this->loader->add_action('wp_ajax_sm_verify_suggest', 'SM_License_Manager', 'ajax_verify_suggest');
        $this->loader->add_action('wp_ajax_nopriv_sm_verify_suggest', 'SM_License_Manager', 'ajax_verify_suggest');
        $this->loader->add_action('wp_ajax_sm_get_test_questions', 'SM_Education_Manager', 'ajax_get_test_questions');
        $this->loader->add_action('wp_ajax_nopriv_sm_get_test_questions', 'SM_Education_Manager', 'ajax_get_test_questions');

        $this->loader->add_action('sm_daily_maintenance', 'SM_DB', 'delete_expired_messages');
        $this->loader->add_action('sm_daily_maintenance', 'SM_Notifications', 'run_daily_checks');
    }

    public function run() {
        add_action('plugins_loaded', array($this, 'check_version_updates'));
        $this->loader->add_action('init', $this, 'schedule_maintenance_cron');
        set_error_handler(array($this, 'handle_errors'));
        register_shutdown_function(array($this, 'handle_fatal_errors'));
        $this->loader->run();
    }

    public function handle_errors($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) return false;
        if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) { $this->send_error_report("Error [$errno]: $errstr", $errfile, $errline); }
        return false;
    }

    public function handle_fatal_errors() {
        $error = error_get_last();
        if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) { $this->send_error_report("Fatal Error: " . $error['message'], $error['file'], $error['line']); }
    }

    private static $is_reporting = false;
    private function send_error_report($message, $file, $line) {
        if (self::$is_reporting) return;
        self::$is_reporting = true;
        try {
            $support_email = get_option('sm_support_email', 'support@irseg.org');
            $subject = "Syndicate Management - Technical Error Report";
            $body = "A technical error has occurred.\n\nMessage: $message\nFile: $file\nLine: $line\nURL: " . (isset($_SERVER['REQUEST_URI']) ? home_url($_SERVER['REQUEST_URI']) : 'N/A') . "\nTime: " . current_time('mysql') . "\n";
            if (function_exists('wp_mail')) { wp_mail($support_email, $subject, $body); }
        } catch (Exception $e) {}
        self::$is_reporting = false;
    }

    public function schedule_maintenance_cron() { if (function_exists('wp_next_scheduled') && !wp_next_scheduled('sm_daily_maintenance')) { wp_schedule_event(time(), 'daily', 'sm_daily_maintenance'); } }

    public function check_version_updates() {
        $db_version = get_option('sm_db_version', '1.0.0');
        if (version_compare($db_version, SM_VERSION, '<')) { require_once SM_PLUGIN_DIR . 'includes/core/class-sm-activator.php'; SM_Activator::activate(); }
    }

    public function get_plugin_name() { return $this->plugin_name; }
    public function get_version() { return $this->version; }
}

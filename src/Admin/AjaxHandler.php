<?php
/**
 * AJAX Handler Class
 * 
 * Handles all AJAX requests for the admin interface,
 * including test notifications and self tests.
 * 
 * @package KissPlugins\WooOrderMonitor\Admin
 * @since 1.5.0
 */

namespace KissPlugins\WooOrderMonitor\Admin;

use KissPlugins\WooOrderMonitor\Core\Settings;

/**
 * AJAX Handler Class
 * 
 * Manages AJAX endpoints for admin functionality including
 * test notifications and self-testing system.
 */
class AjaxHandler {
    
    /**
     * Settings manager
     * 
     * @var Settings
     */
    private $settings;
    
    /**
     * Self tests handler
     * 
     * @var SelfTests|null
     */
    private $self_tests;
    
    /**
     * Constructor
     * 
     * @param Settings $settings Settings manager instance
     * @param SelfTests|null $self_tests Self tests handler instance
     */
    public function __construct(Settings $settings, ?SelfTests $self_tests = null) {
        $this->settings = $settings;
        $this->self_tests = $self_tests;
    }
    
    /**
     * Initialize WordPress hooks
     * 
     * @return void
     */
    public function initializeHooks(): void {
        // Test notification AJAX handler
        add_action('wp_ajax_woom_test_notification', [$this, 'handleTestNotification']);
        
        // Self tests AJAX handler
        add_action('wp_ajax_woom_run_self_tests', [$this, 'handleSelfTests']);
    }
    
    /**
     * Handle test notification AJAX request
     * 
     * @return void
     */
    public function handleTestNotification(): void {
        try {
            // Verify nonce
            if (!check_ajax_referer('woom_test_notification', 'security', false)) {
                wp_send_json_error(__('Invalid security token', 'woo-order-monitor'));
                return;
            }
            
            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(__('Insufficient permissions', 'woo-order-monitor'));
                return;
            }
            
            // Get notification emails
            $notification_emails = $this->getNotificationEmails();
            
            // Validate email addresses
            if (empty($notification_emails) || !is_array($notification_emails)) {
                wp_send_json_error(__('No valid email addresses configured. Please check your notification email settings.', 'woo-order-monitor'));
                return;
            }
            
            // Prepare test email
            $subject = __('[Test] WooCommerce Order Monitor', 'woo-order-monitor');
            $body = $this->buildTestEmailBody();
            
            if (empty($body)) {
                wp_send_json_error(__('Failed to generate test email content', 'woo-order-monitor'));
                return;
            }
            
            // Send test email
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent = wp_mail($notification_emails, $subject, $body, $headers);
            
            if ($sent) {
                wp_send_json_success(sprintf(
                    __('Test notification sent successfully to %d recipient(s): %s', 'woo-order-monitor'),
                    count($notification_emails),
                    implode(', ', $notification_emails)
                ));
            } else {
                wp_send_json_error(__('Failed to send test notification. Please check your email configuration.', 'woo-order-monitor'));
            }
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in handleTestNotification: ' . $e->getMessage());
            wp_send_json_error(sprintf(__('An unexpected error occurred: %s', 'woo-order-monitor'), $e->getMessage()));
        }
    }
    
    /**
     * Handle self tests AJAX request
     * 
     * @return void
     */
    public function handleSelfTests(): void {
        try {
            // Verify nonce
            if (!check_ajax_referer('woom_self_tests', 'security', false)) {
                wp_send_json_error(__('Invalid security token', 'woo-order-monitor'));
                return;
            }
            
            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(__('Insufficient permissions', 'woo-order-monitor'));
                return;
            }
            
            // Check if self tests handler is available
            if (!$this->self_tests) {
                wp_send_json_error(__('Self tests functionality not available', 'woo-order-monitor'));
                return;
            }
            
            // Get tests to run
            $tests_to_run = isset($_POST['tests']) ? $_POST['tests'] : 'all';
            
            if ($tests_to_run === 'all') {
                $tests_to_run = ['database_query', 'threshold_logic', 'email_system', 'cron_scheduling'];
            } elseif (!is_array($tests_to_run)) {
                $tests_to_run = [$tests_to_run];
            }
            
            // Sanitize test names
            $tests_to_run = array_map('sanitize_text_field', $tests_to_run);
            
            // Run tests
            $results = $this->self_tests->runTests($tests_to_run);
            
            if (empty($results)) {
                wp_send_json_error(__('No test results returned', 'woo-order-monitor'));
                return;
            }
            
            wp_send_json_success($results);
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in handleSelfTests: ' . $e->getMessage());
            wp_send_json_error(sprintf(__('An unexpected error occurred: %s', 'woo-order-monitor'), $e->getMessage()));
        }
    }
    
    /**
     * Get notification email addresses
     * 
     * @return array Array of valid email addresses
     */
    private function getNotificationEmails(): array {
        $emails = $this->settings->get('notification_emails', '');
        
        // Convert comma-separated string to array
        if (is_string($emails)) {
            $emails = array_map('trim', explode(',', $emails));
            $emails = array_filter($emails, 'is_email');
        }
        
        // Fallback to admin email if no valid emails
        if (empty($emails)) {
            $admin_email = get_option('admin_email');
            if (is_email($admin_email)) {
                $emails = [$admin_email];
            }
        }
        
        return $emails;
    }
    
    /**
     * Build test email body
     * 
     * @return string HTML email body
     */
    private function buildTestEmailBody(): string {
        $current_time = current_time('mysql');
        $site_name = get_bloginfo('name');
        $admin_url = admin_url('admin.php?page=wc-settings&tab=order_monitor');
        
        ob_start();
        ?>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Test Email - WooCommerce Order Monitor</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">
                    ✅ Test Email - WooCommerce Order Monitor
                </h2>
                
                <div style="background: #f0f8f0; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #46b450;">
                    <h3 style="margin-top: 0; color: #46b450;">Test Successful!</h3>
                    <p>This is a test email to verify that your WooCommerce Order Monitor notification system is working correctly.</p>
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0;">System Information</h3>
                    <p><strong>Site:</strong> <?php echo esc_html($site_name); ?></p>
                    <p><strong>Plugin Version:</strong> <?php echo esc_html(WOOM_VERSION); ?></p>
                    <p><strong>Test Time:</strong> <?php echo esc_html($current_time); ?></p>
                    <p><strong>WordPress Version:</strong> <?php echo esc_html(get_bloginfo('version')); ?></p>
                    <?php if (class_exists('WooCommerce')): ?>
                        <p><strong>WooCommerce Version:</strong> <?php echo esc_html(WC()->version); ?></p>
                    <?php endif; ?>
                </div>
                
                <div style="background: #e7f3ff; padding: 20px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #0073aa;">Current Settings</h3>
                    <p><strong>Monitoring Status:</strong> 
                        <span style="color: <?php echo $this->settings->isEnabled() ? '#46b450' : '#dc3232'; ?>;">
                            <?php echo $this->settings->isEnabled() ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </p>
                    <p><strong>Peak Hours:</strong> <?php echo esc_html($this->settings->get('peak_start', '09:00')); ?> - <?php echo esc_html($this->settings->get('peak_end', '17:00')); ?></p>
                    <p><strong>Peak Threshold:</strong> <?php echo esc_html($this->settings->get('peak_threshold', 3)); ?> orders</p>
                    <p><strong>Off-Peak Threshold:</strong> <?php echo esc_html($this->settings->get('off_peak_threshold', 1)); ?> orders</p>
                </div>
                
                <div style="margin: 20px 0;">
                    <a href="<?php echo esc_url($admin_url); ?>" 
                       style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block;">
                        View Settings
                    </a>
                </div>
                
                <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
                
                <p style="font-size: 12px; color: #666;">
                    This test email was sent from WooCommerce Order Monitor v<?php echo esc_html(WOOM_VERSION); ?> at <?php echo esc_html($current_time); ?>.
                    <br>
                    If you received this email unexpectedly, please check your Order Monitor settings.
                </p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Validate AJAX request permissions
     * 
     * @param string $action AJAX action name
     * @param string $nonce_action Nonce action name
     * @return bool True if valid
     */
    private function validateAjaxRequest(string $action, string $nonce_action): bool {
        // Verify nonce
        if (!check_ajax_referer($nonce_action, 'security', false)) {
            return false;
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Send JSON error response with logging
     * 
     * @param string $message Error message
     * @param string $context Context for logging
     * @return void
     */
    private function sendJsonError(string $message, string $context = ''): void {
        if (!empty($context)) {
            error_log("[WooCommerce Order Monitor] AJAX Error in {$context}: {$message}");
        }
        
        wp_send_json_error($message);
    }
    
    /**
     * Send JSON success response with logging
     * 
     * @param mixed $data Success data
     * @param string $context Context for logging
     * @return void
     */
    private function sendJsonSuccess($data, string $context = ''): void {
        if (!empty($context) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[WooCommerce Order Monitor] AJAX Success in {$context}");
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Get settings manager
     * 
     * @return Settings Settings manager instance
     */
    public function getSettings(): Settings {
        return $this->settings;
    }
    
    /**
     * Get self tests handler
     * 
     * @return SelfTests|null Self tests handler instance
     */
    public function getSelfTests(): ?SelfTests {
        return $this->self_tests;
    }
    
    /**
     * Set self tests handler
     * 
     * @param SelfTests $self_tests Self tests handler instance
     * @return void
     */
    public function setSelfTests(SelfTests $self_tests): void {
        $this->self_tests = $self_tests;
    }
}

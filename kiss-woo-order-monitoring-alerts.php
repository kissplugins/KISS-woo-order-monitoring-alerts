<?php
/**
 * Plugin Name: WooCommerce Order Monitor
 * Plugin URI: https://example.com/woo-order-monitor
 * Description: Monitors WooCommerce order volume and sends alerts when orders fall below configured thresholds
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WOOM_VERSION', '1.0.0');
define('WOOM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOOM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOOM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class WooCommerce_Order_Monitor {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Plugin settings
     */
    private $settings = [];
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check dependencies
        add_action('admin_init', [$this, 'check_dependencies']);
        
        // Setup plugin
        add_action('init', [$this, 'init']);
        
        // Admin hooks
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_order_monitor', [$this, 'settings_tab']);
        add_action('woocommerce_update_options_order_monitor', [$this, 'update_settings']);
        
        // AJAX handlers
        add_action('wp_ajax_woom_test_notification', [$this, 'handle_test_notification']);
        
        // Cron hooks
        add_action('woom_check_orders', [$this, 'check_order_threshold']);
        
        // Activation/Deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Check plugin dependencies
     */
    public function check_dependencies() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php _e('WooCommerce Order Monitor requires WooCommerce to be installed and active.', 'woo-order-monitor'); ?></p>
                </div>
                <?php
            });
            deactivate_plugins(WOOM_PLUGIN_BASENAME);
        }
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        $this->load_settings();
        $this->ensure_cron_scheduled();
    }
    
    /**
     * Load plugin settings
     */
    private function load_settings() {
        $this->settings = [
            'enabled' => get_option('woom_enabled', 'no'),
            'peak_start' => get_option('woom_peak_start', '09:00'),
            'peak_end' => get_option('woom_peak_end', '21:00'),
            'threshold_peak' => intval(get_option('woom_threshold_peak', 10)),
            'threshold_offpeak' => intval(get_option('woom_threshold_offpeak', 2)),
            'notification_emails' => get_option('woom_notification_emails', get_option('admin_email')),
            'last_check' => get_option('woom_last_check', 0),
            'last_alert' => get_option('woom_last_alert', 0)
        ];
    }
    
    /**
     * Ensure cron job is scheduled
     */
    private function ensure_cron_scheduled() {
        if ('yes' === $this->settings['enabled'] && !wp_next_scheduled('woom_check_orders')) {
            wp_schedule_event(time(), 'woom_15min', 'woom_check_orders');
        } elseif ('yes' !== $this->settings['enabled'] && wp_next_scheduled('woom_check_orders')) {
            wp_clear_scheduled_hook('woom_check_orders');
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Add custom cron schedule
        add_filter('cron_schedules', [$this, 'add_cron_interval']);
        
        // Set default options
        add_option('woom_enabled', 'no');
        add_option('woom_peak_start', '09:00');
        add_option('woom_peak_end', '21:00');
        add_option('woom_threshold_peak', 10);
        add_option('woom_threshold_offpeak', 2);
        add_option('woom_notification_emails', get_option('admin_email'));
        
        // Schedule cron if enabled
        if ('yes' === get_option('woom_enabled')) {
            wp_schedule_event(time(), 'woom_15min', 'woom_check_orders');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron
        wp_clear_scheduled_hook('woom_check_orders');
    }
    
    /**
     * Add custom cron interval
     */
    public function add_cron_interval($schedules) {
        $schedules['woom_15min'] = [
            'interval' => 900, // 15 minutes in seconds
            'display' => __('Every 15 minutes', 'woo-order-monitor')
        ];
        return $schedules;
    }
    
    /**
     * Check order threshold
     */
    public function check_order_threshold() {
        // Update last check time
        update_option('woom_last_check', current_time('timestamp'));
        
        // Get order count for last 15 minutes
        $order_count = $this->get_recent_order_count();
        
        // Determine if we're in peak hours
        $is_peak = $this->is_peak_hours();
        
        // Get appropriate threshold
        $threshold = $is_peak ? $this->settings['threshold_peak'] : $this->settings['threshold_offpeak'];
        
        // Check if below threshold
        if ($order_count < $threshold) {
            $this->send_alert($order_count, $threshold, $is_peak);
        }
        
        // Log for debugging (optional)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[WooCommerce Order Monitor] Check complete - Orders: %d, Threshold: %d, Peak: %s',
                $order_count,
                $threshold,
                $is_peak ? 'Yes' : 'No'
            ));
        }
    }
    
    /**
     * Get count of recent successful orders
     */
    private function get_recent_order_count() {
        global $wpdb;
        
        // Calculate time 15 minutes ago
        $minutes_ago = 15;
        $start_time = date('Y-m-d H:i:s', strtotime("-{$minutes_ago} minutes"));
        
        // Query for successful orders
        // Using direct query for performance
        $query = $wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID) as order_count
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND p.post_date >= %s
        ", $start_time);
        
        $result = $wpdb->get_var($query);
        
        return intval($result);
    }
    
    /**
     * Check if current time is in peak hours
     */
    private function is_peak_hours() {
        $current_time = current_time('H:i');
        $peak_start = $this->settings['peak_start'];
        $peak_end = $this->settings['peak_end'];
        
        // Handle cases where peak hours span midnight
        if ($peak_end < $peak_start) {
            // Peak hours span midnight
            return ($current_time >= $peak_start || $current_time < $peak_end);
        } else {
            // Normal peak hours
            return ($current_time >= $peak_start && $current_time < $peak_end);
        }
    }
    
    /**
     * Send alert notification
     */
    private function send_alert($order_count, $threshold, $is_peak) {
        // Update last alert time
        update_option('woom_last_alert', current_time('timestamp'));
        
        // Prepare email data
        $to = $this->get_notification_emails();
        $subject = __('[Alert] WooCommerce Orders Below Threshold', 'woo-order-monitor');
        
        // Calculate time period
        $end_time = current_time('H:i');
        $start_time = date('H:i', strtotime('-15 minutes'));
        
        // Build email body
        $body = $this->build_alert_email_body([
            'start_time' => $start_time,
            'end_time' => $end_time,
            'threshold' => $threshold,
            'order_count' => $order_count,
            'period_type' => $is_peak ? __('Peak Hours', 'woo-order-monitor') : __('Off-Peak Hours', 'woo-order-monitor'),
            'admin_url' => admin_url('edit.php?post_type=shop_order')
        ]);
        
        // Set HTML headers
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        // Send email
        $sent = wp_mail($to, $subject, $body, $headers);
        
        // Log if sending failed
        if (!$sent && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WooCommerce Order Monitor] Failed to send alert email');
        }
        
        return $sent;
    }
    
    /**
     * Build alert email body
     */
    private function build_alert_email_body($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .alert-header { background-color: #dc3545; color: white; padding: 15px; border-radius: 5px 5px 0 0; }
                .alert-body { background-color: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-top: none; }
                .details-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .details-table td { padding: 10px; border-bottom: 1px solid #dee2e6; }
                .details-table td:first-child { font-weight: bold; width: 40%; }
                .action-button { display: inline-block; padding: 10px 20px; background-color: #007cba; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .warning-list { background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="alert-header">
                    <h2 style="margin: 0;">⚠️ WooCommerce Order Alert</h2>
                </div>
                <div class="alert-body">
                    <p><strong>Alert:</strong> Order volume has fallen below the configured threshold.</p>
                    
                    <table class="details-table">
                        <tr>
                            <td>Time Period:</td>
                            <td><?php echo esc_html($data['start_time']); ?> to <?php echo esc_html($data['end_time']); ?></td>
                        </tr>
                        <tr>
                            <td>Threshold Type:</td>
                            <td><?php echo esc_html($data['period_type']); ?></td>
                        </tr>
                        <tr>
                            <td>Expected Orders:</td>
                            <td><?php echo esc_html($data['threshold']); ?> or more</td>
                        </tr>
                        <tr>
                            <td>Actual Orders:</td>
                            <td><strong style="color: #dc3545;"><?php echo esc_html($data['order_count']); ?></strong></td>
                        </tr>
                    </table>
                    
                    <div class="warning-list">
                        <p><strong>This could indicate:</strong></p>
                        <ul>
                            <li>Website downtime or errors</li>
                            <li>Payment gateway issues</li>
                            <li>Potential DDoS attack</li>
                            <li>Cart/checkout problems</li>
                            <li>Server performance issues</li>
                        </ul>
                    </div>
                    
                    <p><strong>Action Required:</strong> Please check your WooCommerce store immediately.</p>
                    
                    <a href="<?php echo esc_url($data['admin_url']); ?>" class="action-button">View Orders →</a>
                    
                    <hr style="margin-top: 40px; border: none; border-top: 1px solid #dee2e6;">
                    <p style="font-size: 12px; color: #6c757d;">
                        This is an automated alert from WooCommerce Order Monitor<br>
                        Generated at: <?php echo current_time('Y-m-d H:i:s'); ?>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get notification email addresses
     */
    private function get_notification_emails() {
        $emails = $this->settings['notification_emails'];
        
        // Convert comma-separated string to array
        if (is_string($emails)) {
            $emails = array_map('trim', explode(',', $emails));
            $emails = array_filter($emails, 'is_email');
        }
        
        // Fallback to admin email if no valid emails
        if (empty($emails)) {
            $emails = [get_option('admin_email')];
        }
        
        return $emails;
    }
    
    /**
     * Add settings tab
     */
    public function add_settings_tab($settings_tabs) {
        $settings_tabs['order_monitor'] = __('Order Monitor', 'woo-order-monitor');
        return $settings_tabs;
    }
    
    /**
     * Settings tab content
     */
    public function settings_tab() {
        woocommerce_admin_fields($this->get_settings());
        
        // Add custom JavaScript for test notification
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#woom_test_notification').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                $button.prop('disabled', true).text('Sending...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'woom_test_notification',
                        security: '<?php echo wp_create_nonce('woom_test_notification'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Test notification sent successfully!');
                        } else {
                            alert('Failed to send test notification: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('An error occurred while sending the test notification.');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Send Test Notification');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get settings fields
     */
    private function get_settings() {
        $last_check = get_option('woom_last_check', 0);
        $last_alert = get_option('woom_last_alert', 0);
        
        $settings = [
            'section_title' => [
                'name' => __('WooCommerce Order Monitor Settings', 'woo-order-monitor'),
                'type' => 'title',
                'desc' => __('Configure order monitoring thresholds and notifications.', 'woo-order-monitor'),
                'id' => 'woom_section_title'
            ],
            'enabled' => [
                'name' => __('Enable Monitoring', 'woo-order-monitor'),
                'type' => 'checkbox',
                'desc' => __('Enable order volume monitoring', 'woo-order-monitor'),
                'id' => 'woom_enabled',
                'default' => 'no'
            ],
            'peak_start' => [
                'name' => __('Peak Hours Start', 'woo-order-monitor'),
                'type' => 'time',
                'desc' => __('Start time for peak hours (24-hour format)', 'woo-order-monitor'),
                'id' => 'woom_peak_start',
                'default' => '09:00',
                'custom_attributes' => [
                    'pattern' => '[0-9]{2}:[0-9]{2}'
                ]
            ],
            'peak_end' => [
                'name' => __('Peak Hours End', 'woo-order-monitor'),
                'type' => 'time',
                'desc' => __('End time for peak hours (24-hour format)', 'woo-order-monitor'),
                'id' => 'woom_peak_end',
                'default' => '21:00',
                'custom_attributes' => [
                    'pattern' => '[0-9]{2}:[0-9]{2}'
                ]
            ],
            'threshold_peak' => [
                'name' => __('Peak Hours Threshold', 'woo-order-monitor'),
                'type' => 'number',
                'desc' => __('Minimum orders expected in 15 minutes during peak hours', 'woo-order-monitor'),
                'id' => 'woom_threshold_peak',
                'default' => '10',
                'custom_attributes' => [
                    'min' => '0',
                    'step' => '1'
                ]
            ],
            'threshold_offpeak' => [
                'name' => __('Off-Peak Hours Threshold', 'woo-order-monitor'),
                'type' => 'number',
                'desc' => __('Minimum orders expected in 15 minutes during off-peak hours', 'woo-order-monitor'),
                'id' => 'woom_threshold_offpeak',
                'default' => '2',
                'custom_attributes' => [
                    'min' => '0',
                    'step' => '1'
                ]
            ],
            'notification_emails' => [
                'name' => __('Notification Emails', 'woo-order-monitor'),
                'type' => 'textarea',
                'desc' => __('Comma-separated email addresses to receive alerts', 'woo-order-monitor'),
                'id' => 'woom_notification_emails',
                'default' => get_option('admin_email'),
                'css' => 'width: 400px; height: 75px;'
            ],
            'test_notification' => [
                'name' => __('Test Notification', 'woo-order-monitor'),
                'type' => 'button',
                'desc' => __('Send a test notification to verify email delivery', 'woo-order-monitor'),
                'id' => 'woom_test_notification',
                'custom_attributes' => [
                    'class' => 'button-secondary'
                ]
            ],
            'monitoring_status' => [
                'name' => __('Monitoring Status', 'woo-order-monitor'),
                'type' => 'info',
                'desc' => sprintf(
                    __('Last check: %s<br>Last alert: %s', 'woo-order-monitor'),
                    $last_check ? date('Y-m-d H:i:s', $last_check) : __('Never', 'woo-order-monitor'),
                    $last_alert ? date('Y-m-d H:i:s', $last_alert) : __('Never', 'woo-order-monitor')
                ),
                'id' => 'woom_monitoring_status'
            ],
            'section_end' => [
                'type' => 'sectionend',
                'id' => 'woom_section_end'
            ]
        ];
        
        return apply_filters('woocommerce_order_monitor_settings', $settings);
    }
    
    /**
     * Update settings
     */
    public function update_settings() {
        woocommerce_update_options($this->get_settings());
        
        // Reload settings and update cron
        $this->load_settings();
        $this->ensure_cron_scheduled();
    }
    
    /**
     * Handle test notification AJAX request
     */
    public function handle_test_notification() {
        // Verify nonce
        if (!check_ajax_referer('woom_test_notification', 'security', false)) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Send test email
        $to = $this->get_notification_emails();
        $subject = __('[Test] WooCommerce Order Monitor - Test Notification', 'woo-order-monitor');
        
        $body = $this->build_test_email_body();
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        $sent = wp_mail($to, $subject, $body, $headers);
        
        if ($sent) {
            wp_send_json_success('Test notification sent to: ' . implode(', ', $to));
        } else {
            wp_send_json_error('Failed to send test notification');
        }
    }
    
    /**
     * Build test email body
     */
    private function build_test_email_body() {
        $current_orders = $this->get_recent_order_count();
        $is_peak = $this->is_peak_hours();
        $threshold = $is_peak ? $this->settings['threshold_peak'] : $this->settings['threshold_offpeak'];
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .test-header { background-color: #28a745; color: white; padding: 15px; border-radius: 5px 5px 0 0; }
                .test-body { background-color: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-top: none; }
                .status-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .status-table td { padding: 10px; border-bottom: 1px solid #dee2e6; }
                .status-table td:first-child { font-weight: bold; width: 40%; }
                .success-msg { background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px; color: #155724; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="test-header">
                    <h2 style="margin: 0;">✓ Test Notification - Order Monitor Active</h2>
                </div>
                <div class="test-body">
                    <div class="success-msg">
                        <strong>Success!</strong> Your WooCommerce Order Monitor notifications are working correctly.
                    </div>
                    
                    <h3>Current Configuration:</h3>
                    <table class="status-table">
                        <tr>
                            <td>Monitoring Status:</td>
                            <td><?php echo ('yes' === $this->settings['enabled']) ? '✓ Enabled' : '✗ Disabled'; ?></td>
                        </tr>
                        <tr>
                            <td>Current Time:</td>
                            <td><?php echo current_time('H:i'); ?></td>
                        </tr>
                        <tr>
                            <td>Period Type:</td>
                            <td><?php echo $is_peak ? 'Peak Hours' : 'Off-Peak Hours'; ?></td>
                        </tr>
                        <tr>
                            <td>Peak Hours:</td>
                            <td><?php echo esc_html($this->settings['peak_start']); ?> - <?php echo esc_html($this->settings['peak_end']); ?></td>
                        </tr>
                        <tr>
                            <td>Current Threshold:</td>
                            <td><?php echo $threshold; ?> orders per 15 minutes</td>
                        </tr>
                        <tr>
                            <td>Orders (Last 15 min):</td>
                            <td><?php echo $current_orders; ?></td>
                        </tr>
                        <tr>
                            <td>Alert Recipients:</td>
                            <td><?php echo esc_html(implode(', ', $this->get_notification_emails())); ?></td>
                        </tr>
                    </table>
                    
                    <p><strong>Next Steps:</strong></p>
                    <ul>
                        <li>Monitoring will check order volumes every 15 minutes</li>
                        <li>You'll receive an alert if orders fall below the configured threshold</li>
                        <li>Adjust thresholds based on your typical order patterns</li>
                    </ul>
                    
                    <hr style="margin-top: 40px; border: none; border-top: 1px solid #dee2e6;">
                    <p style="font-size: 12px; color: #6c757d;">
                        WooCommerce Order Monitor v<?php echo WOOM_VERSION; ?><br>
                        Test sent at: <?php echo current_time('Y-m-d H:i:s'); ?>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        WooCommerce_Order_Monitor::get_instance();
    }
});

/**
 * Alternative monitoring using Action Scheduler (if available)
 * This provides more reliable execution than WP-Cron
 */
if (function_exists('as_schedule_recurring_action')) {
    
    class WOOM_Action_Scheduler {
        
        /**
         * Initialize Action Scheduler integration
         */
        public static function init() {
            add_action('init', [__CLASS__, 'schedule_monitoring']);
            add_action('woom_as_check_orders', [__CLASS__, 'run_check']);
        }
        
        /**
         * Schedule monitoring with Action Scheduler
         */
        public static function schedule_monitoring() {
            $enabled = get_option('woom_enabled', 'no');
            
            if ('yes' === $enabled && !as_next_scheduled_action('woom_as_check_orders')) {
                as_schedule_recurring_action(
                    time(),
                    900, // 15 minutes
                    'woom_as_check_orders',
                    [],
                    'woo-order-monitor'
                );
            } elseif ('yes' !== $enabled) {
                as_unschedule_all_actions('woom_as_check_orders', [], 'woo-order-monitor');
            }
        }
        
        /**
         * Run the order check
         */
        public static function run_check() {
            $monitor = WooCommerce_Order_Monitor::get_instance();
            $monitor->check_order_threshold();
        }
    }
    
    // Initialize Action Scheduler integration
    WOOM_Action_Scheduler::init();
}

/**
 * Performance optimized order query using custom SQL
 * This is an alternative implementation for high-volume stores
 */
class WOOM_Optimized_Query {
    
    /**
     * Get order count using optimized query with caching
     */
    public static function get_cached_order_count($minutes = 15) {
        $cache_key = 'woom_order_count_' . $minutes;
        $cached = wp_cache_get($cache_key, 'woo-order-monitor');
        
        if (false !== $cached) {
            return $cached;
        }
        
        global $wpdb;
        
        // Use indexed columns for better performance
        $start_time = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        
        // Query using order stats table if available (HPOS)
        if (class_exists('Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
            $query = $wpdb->prepare("
                SELECT COUNT(*) as order_count
                FROM {$wpdb->prefix}wc_orders
                WHERE status IN ('wc-completed', 'wc-processing')
                AND date_created_gmt >= %s
            ", $start_time);
        } else {
            // Fallback to posts table
            $query = $wpdb->prepare("
                SELECT COUNT(*) as order_count
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing')
                AND p.post_date_gmt >= %s
                GROUP BY p.ID
            ", $start_time);
        }
        
        $count = intval($wpdb->get_var($query));
        
        // Cache for 1 minute
        wp_cache_set($cache_key, $count, 'woo-order-monitor', 60);
        
        return $count;
    }
    
    /**
     * Get detailed order statistics
     */
    public static function get_order_stats($minutes = 15) {
        global $wpdb;
        
        $start_time = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        
        $query = $wpdb->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN post_status = 'wc-completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN post_status = 'wc-processing' THEN 1 ELSE 0 END) as processing_orders,
                MAX(post_date) as last_order_time
            FROM {$wpdb->posts}
            WHERE post_type = 'shop_order'
            AND post_status IN ('wc-completed', 'wc-processing')
            AND post_date >= %s
        ", $start_time);
        
        return $wpdb->get_row($query, ARRAY_A);
    }
}

/**
 * CLI Commands for WP-CLI support
 */
if (defined('WP_CLI') && WP_CLI) {
    
    class WOOM_CLI_Commands {
        
        /**
         * Check order threshold manually
         * 
         * ## EXAMPLES
         * 
         *     wp woom check
         */
        public function check() {
            $monitor = WooCommerce_Order_Monitor::get_instance();
            $monitor->check_order_threshold();
            
            WP_CLI::success('Order threshold check completed.');
        }
        
        /**
         * Get current order count
         * 
         * ## OPTIONS
         * 
         * [--minutes=<minutes>]
         * : Number of minutes to look back. Default: 15
         * 
         * ## EXAMPLES
         * 
         *     wp woom count
         *     wp woom count --minutes=30
         */
        public function count($args, $assoc_args) {
            $minutes = isset($assoc_args['minutes']) ? intval($assoc_args['minutes']) : 15;
            
            $monitor = WooCommerce_Order_Monitor::get_instance();
            $count = WOOM_Optimized_Query::get_cached_order_count($minutes);
            
            WP_CLI::line(sprintf('Orders in last %d minutes: %d', $minutes, $count));
        }
        
        /**
         * Send test notification
         * 
         * ## EXAMPLES
         * 
         *     wp woom test
         */
        public function test() {
            $monitor = WooCommerce_Order_Monitor::get_instance();
            
            $to = $monitor->get_notification_emails();
            $subject = '[Test] WooCommerce Order Monitor';
            $body = 'This is a test notification from WP-CLI.';
            
            if (wp_mail($to, $subject, $body)) {
                WP_CLI::success('Test notification sent to: ' . implode(', ', $to));
            } else {
                WP_CLI::error('Failed to send test notification.');
            }
        }
        
        /**
         * Show current configuration
         * 
         * ## EXAMPLES
         * 
         *     wp woom config
         */
        public function config() {
            $settings = [
                'enabled' => get_option('woom_enabled', 'no'),
                'peak_start' => get_option('woom_peak_start', '09:00'),
                'peak_end' => get_option('woom_peak_end', '21:00'),
                'threshold_peak' => get_option('woom_threshold_peak', 10),
                'threshold_offpeak' => get_option('woom_threshold_offpeak', 2),
                'notification_emails' => get_option('woom_notification_emails', ''),
                'last_check' => get_option('woom_last_check', 0),
                'last_alert' => get_option('woom_last_alert', 0)
            ];
            
            WP_CLI::line('WooCommerce Order Monitor Configuration:');
            foreach ($settings as $key => $value) {
                if ($key === 'last_check' || $key === 'last_alert') {
                    $value = $value ? date('Y-m-d H:i:s', $value) : 'Never';
                }
                WP_CLI::line(sprintf('  %s: %s', $key, $value));
            }
        }
    }
    
    WP_CLI::add_command('woom', 'WOOM_CLI_Commands');
}

// Add custom health check for Site Health
add_filter('site_status_tests', function($tests) {
    $tests['direct']['woom_monitoring'] = [
        'label' => __('WooCommerce Order Monitoring', 'woo-order-monitor'),
        'test' => 'woom_health_check'
    ];
    return $tests;
});

function woom_health_check() {
    $result = [
        'label' => __('WooCommerce Order Monitoring is configured', 'woo-order-monitor'),
        'status' => 'good',
        'badge' => [
            'label' => __('E-Commerce', 'woo-order-monitor'),
            'color' => 'blue'
        ],
        'description' => sprintf(
            '<p>%s</p>',
            __('Order monitoring is properly configured and running.', 'woo-order-monitor')
        ),
        'actions' => '',
        'test' => 'woom_monitoring'
    ];
    
    $enabled = get_option('woom_enabled', 'no');
    $last_check = get_option('woom_last_check', 0);
    
    if ('yes' !== $enabled) {
        $result['status'] = 'recommended';
        $result['label'] = __('WooCommerce Order Monitoring is disabled', 'woo-order-monitor');
        $result['description'] = sprintf(
            '<p>%s</p>',
            __('Order monitoring is currently disabled. Enable it to receive alerts about order volume issues.', 'woo-order-monitor')
        );
    } elseif ($last_check && (time() - $last_check) > 1800) { // 30 minutes
        $result['status'] = 'critical';
        $result['label'] = __('WooCommerce Order Monitoring may not be running', 'woo-order-monitor');
        $result['description'] = sprintf(
            '<p>%s</p>',
            __('Order monitoring hasn\'t run in over 30 minutes. Check your cron configuration.', 'woo-order-monitor')
        );
    }
    
    return $result;
}

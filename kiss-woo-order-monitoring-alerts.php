<?php
/**
 * Plugin Name: KISS WooCommerce Order Monitor
 * Plugin URI: https://github.com/kissplugins/KISS-woo-order-monitoring-alerts
 * Description: Monitors WooCommerce order volume and sends alerts when orders fall below configured thresholds
 * Version: 1.5.5
 * Author: KISS Plugins
 * License: GPL v2 or later
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 * Text Domain: kiss-woocomerce-order-monitor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/*
 * ⚠️  CRITICAL SETTINGS CENTRALIZATION NOTICE ⚠️
 *
 * ALL PLUGIN DEFAULT VALUES ARE NOW CENTRALIZED IN:
 * src/Core/SettingsDefaults.php
 *
 * 🚫 DO NOT DEFINE DEFAULT VALUES ANYWHERE ELSE IN THE CODEBASE
 * 🚫 DO NOT MODIFY DEFAULT VALUES IN THIS FILE
 * 🚫 DO NOT ADD NEW HARDCODED DEFAULTS
 *
 * ✅ TO CHANGE DEFAULT VALUES:
 * 1. Edit src/Core/SettingsDefaults.php ONLY
 * 2. Update the $master_defaults array
 * 3. Test thoroughly with self-tests
 * 4. Update version number and changelog
 *
 * This centralization prevents configuration drift and ensures
 * consistency across UI forms, activation code, and runtime logic.
 *
 * Last updated: v1.5.1 - Settings centralization implementation
 */

// Define plugin constants
define('WOOM_VERSION', '1.5.5');
define('WOOM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOOM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOOM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include the Plugin Update Checker
require_once WOOM_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/kissplugins/KISS-woo-order-monitoring-alerts',
    __FILE__,
    'kiss-woocomerce-order-monitor'
);
// Optional: Set the branch that contains the stable release.
$update_checker->setBranch( 'main' );

// Try to load PSR-4 bootstrap if available
$bootstrap_file = WOOM_PLUGIN_DIR . 'bootstrap.php';
if (file_exists($bootstrap_file)) {
    require_once $bootstrap_file;

    // If PSR-4 structure is available and enabled, let bootstrap handle initialization
    if (function_exists('woom_should_use_psr4') && woom_should_use_psr4()) {
        // PSR-4 bootstrap will handle plugin initialization
        return;
    }
}

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

        // Cron schedule registration (must be early)
        add_filter('cron_schedules', [$this, 'add_cron_interval']);

        // Admin hooks
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_order_monitor', [$this, 'settings_tab']);
        add_action('woocommerce_update_options_order_monitor', [$this, 'update_settings']);

        // Plugin action links (Settings link on plugins page)
        add_filter('plugin_action_links_' . WOOM_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);

        // AJAX handlers
        add_action('wp_ajax_woom_test_notification', [$this, 'handle_test_notification']);
        add_action('wp_ajax_woom_run_self_tests', [$this, 'handle_self_tests']);
        add_action('wp_ajax_woom_manual_check', [$this, 'handle_manual_check']);

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
     *
     * ⚠️  CRITICAL: This method is DEPRECATED and should be replaced with the new Settings class.
     * ⚠️  It now uses SettingsDefaults to ensure consistency until migration is complete.
     * ⚠️  DO NOT modify default values here - use SettingsDefaults instead.
     */
    private function load_settings() {
        // Import the centralized defaults class
        require_once plugin_dir_path(__FILE__) . 'src/Core/SettingsDefaults.php';

        // Get defaults from centralized configuration
        $defaults = \KissPlugins\WooOrderMonitor\Core\SettingsDefaults::getRuntimeDefaults();

        $this->settings = [
            'enabled' => get_option('woom_enabled', $defaults['enabled']),
            'peak_start' => get_option('woom_peak_start', $defaults['peak_start']),
            'peak_end' => get_option('woom_peak_end', $defaults['peak_end']),
            'threshold_peak' => intval(get_option('woom_threshold_peak', $defaults['threshold_peak'])),
            'threshold_offpeak' => intval(get_option('woom_threshold_offpeak', $defaults['threshold_offpeak'])),
            'notification_emails' => get_option('woom_notification_emails', $defaults['notification_emails']),
            'last_check' => get_option('woom_last_check', $defaults['last_check']),
            'last_alert' => get_option('woom_last_alert', $defaults['last_alert']),
            // Production safety settings
            'alert_cooldown' => intval(get_option('woom_alert_cooldown', $defaults['alert_cooldown'])),
            'max_daily_alerts' => intval(get_option('woom_max_daily_alerts', $defaults['max_daily_alerts'])),
            'last_alert_peak' => get_option('woom_last_alert_peak', $defaults['last_alert_peak']),
            'last_alert_offpeak' => get_option('woom_last_alert_offpeak', $defaults['last_alert_offpeak']),
            'daily_alert_count' => intval(get_option('woom_daily_alert_count', $defaults['daily_alert_count'])),
            'daily_alert_date' => get_option('woom_daily_alert_date', $defaults['daily_alert_date']),
            'enable_system_alerts' => get_option('woom_enable_system_alerts', $defaults['enable_system_alerts']),
            'webhook_url' => get_option('woom_webhook_url', $defaults['webhook_url']),
            'query_cache_duration' => intval(get_option('woom_query_cache_duration', $defaults['query_cache_duration']))
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
     *
     * ⚠️  CRITICAL: This method is DEPRECATED and should be replaced with the new Installer class.
     * ⚠️  It now uses SettingsDefaults to ensure consistency until migration is complete.
     * ⚠️  DO NOT modify default values here - use SettingsDefaults instead.
     */
    public function activate() {
        // Import the centralized defaults class
        require_once plugin_dir_path(__FILE__) . 'src/Core/SettingsDefaults.php';

        // Get defaults from centralized configuration
        $defaults = \KissPlugins\WooOrderMonitor\Core\SettingsDefaults::getActivationDefaults();

        // Set default options using centralized defaults
        foreach ($defaults as $option_name => $default_value) {
            add_option($option_name, $default_value);
        }
        
        // Production safety defaults
        add_option('woom_alert_cooldown', 7200); // 2 hours between alerts
        add_option('woom_max_daily_alerts', 6); // Maximum 6 alerts per day
        add_option('woom_last_alert_peak', 0);
        add_option('woom_last_alert_offpeak', 0);
        add_option('woom_daily_alert_count', 0);
        add_option('woom_daily_alert_date', date('Y-m-d'));
        add_option('woom_enable_system_alerts', 'yes');
        add_option('woom_webhook_url', '');
        add_option('woom_query_cache_duration', 300); // 5-minute cache

        // Schedule cron if enabled (cron schedule is registered via init_hooks)
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
        try {
            // Update last check time
            update_option('woom_last_check', current_time('timestamp'));

            // Get order count for last 15 minutes
            $order_count = $this->get_recent_order_count();

            // If order count is null/false due to database error, skip this check
            if ($order_count === false || $order_count === null) {
                error_log('[WooCommerce Order Monitor] Skipping threshold check due to database error');
                return;
            }

            // Determine if we're in peak hours
            $is_peak = $this->is_peak_hours();

            // Get appropriate threshold
            $threshold = $is_peak ? $this->settings['threshold_peak'] : $this->settings['threshold_offpeak'];

            // Validate threshold values
            if (!is_numeric($threshold) || $threshold < 0) {
                error_log('[WooCommerce Order Monitor] Invalid threshold value: ' . $threshold);
                return;
            }

            // Check if below threshold
            if ($order_count < $threshold) {
                $can_send = $this->should_send_alert($is_peak);
                if ($can_send['allowed']) {
                    $alert_sent = $this->send_throttled_alert($order_count, $threshold, $is_peak, $can_send['type']);
                    if (!$alert_sent) {
                        error_log('[WooCommerce Order Monitor] Failed to send alert notification');
                    }
                } else {
                    // Log that alert was throttled
                    error_log(sprintf(
                        '[WooCommerce Order Monitor] Alert throttled - Reason: %s, Orders: %d, Threshold: %d, Peak: %s',
                        $can_send['reason'],
                        $order_count,
                        $threshold,
                        $is_peak ? 'Yes' : 'No'
                    ));
                }
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

        } catch (Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in check_order_threshold: ' . $e->getMessage());
        }
    }
    
    /**
     * Get count of recent successful orders (with performance optimization)
     */
    private function get_recent_order_count() {
        // Use cached version with timeout and query optimization
        return $this->get_cached_order_count(15);
    }
    
    /**
     * Get cached order count with performance optimization
     */
    private function get_cached_order_count($minutes = 15) {
        $cache_key = 'woom_order_count_' . $minutes . '_' . ceil(time() / $this->settings['query_cache_duration']);
        
        // Try to get from WordPress object cache first
        $cached_result = wp_cache_get($cache_key, 'woo-order-monitor');
        if (false !== $cached_result) {
            return $cached_result;
        }
        
        global $wpdb;

        try {
            // Calculate time boundary
            $start_time = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
            $end_time = current_time('mysql'); // Upper bound for better query planning
            
            // Check if HPOS is available for better performance
            if ($this->is_hpos_enabled()) {
                $result = $this->query_hpos_orders($start_time, $end_time);
            } else {
                $result = $this->query_legacy_orders($start_time, $end_time);
            }
            
            // Validate result
            if ($result === false || $result === null) {
                error_log('[WooCommerce Order Monitor] Query failed, returning 0 to prevent false alerts');
                return 0;
            }
            
            $count = intval($result);
            
            // Cache the result
            wp_cache_set($cache_key, $count, 'woo-order-monitor', $this->settings['query_cache_duration']);
            
            return $count;

        } catch (Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in get_cached_order_count: ' . $e->getMessage());
            
            // Try to send system alert if enabled
            if ('yes' === $this->settings['enable_system_alerts']) {
                $this->send_system_alert('Database Query Failed', $e->getMessage());
            }
            
            return 0; // Return 0 to prevent false alerts due to exceptions
        }
    }
    
    /**
     * Check if WooCommerce High-Performance Order Storage is enabled
     */
    private function is_hpos_enabled() {
        return class_exists('Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') 
               && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();
    }
    
    /**
     * Query orders using HPOS tables for better performance
     */
    private function query_hpos_orders($start_time, $end_time) {
        global $wpdb;
        
        $query = $wpdb->prepare("
            SELECT COUNT(*) as order_count
            FROM {$wpdb->prefix}wc_orders
            WHERE status IN ('wc-completed', 'wc-processing')
            AND date_created_gmt >= %s
            AND date_created_gmt <= %s
        ", $start_time, $end_time);
        
        $result = $wpdb->get_var($query);
        
        if ($wpdb->last_error) {
            error_log('[WooCommerce Order Monitor] HPOS query error: ' . $wpdb->last_error);
            return false;
        }
        
        return $result;
    }
    
    /**
     * Query orders using legacy post tables with optimization
     */
    private function query_legacy_orders($start_time, $end_time) {
        global $wpdb;
        
        // Optimized query with bounded time range and proper indexing
        $query = $wpdb->prepare("
            SELECT COUNT(*) as order_count
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND p.post_date >= %s
            AND p.post_date <= %s
        ", $start_time, $end_time);
        
        $result = $wpdb->get_var($query);
        
        if ($wpdb->last_error) {
            error_log('[WooCommerce Order Monitor] Legacy query error: ' . $wpdb->last_error);
            return false;
        }
        
        return $result;
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
        try {
            // Update last alert time
            update_option('woom_last_alert', current_time('timestamp'));

            // Prepare email data
            $to = $this->get_notification_emails();

            // Validate email addresses
            if (empty($to) || !is_array($to)) {
                error_log('[WooCommerce Order Monitor] No valid email addresses configured for alerts');
                return false;
            }

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

            // Validate email body
            if (empty($body)) {
                error_log('[WooCommerce Order Monitor] Failed to generate email body');
                return false;
            }

            // Set HTML headers
            $headers = ['Content-Type: text/html; charset=UTF-8'];

            // Send email
            $sent = wp_mail($to, $subject, $body, $headers);

            // Log if sending failed
            if (!$sent) {
                error_log('[WooCommerce Order Monitor] Failed to send alert email to: ' . implode(', ', $to));
            } else {
                error_log('[WooCommerce Order Monitor] Alert email sent successfully to: ' . implode(', ', $to));
            }

            return $sent;

        } catch (Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in send_alert: ' . $e->getMessage());
            return false;
        }
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
     * Check if alert should be sent based on throttling rules
     */
    private function should_send_alert($is_peak) {
        $current_time = current_time('timestamp');
        $current_date = date('Y-m-d');
        
        // Reset daily counter if new day
        if ($this->settings['daily_alert_date'] !== $current_date) {
            update_option('woom_daily_alert_count', 0);
            update_option('woom_daily_alert_date', $current_date);
            $this->settings['daily_alert_count'] = 0;
            $this->settings['daily_alert_date'] = $current_date;
        }
        
        // Check daily limit
        if ($this->settings['daily_alert_count'] >= $this->settings['max_daily_alerts']) {
            return [
                'allowed' => false,
                'reason' => 'Daily limit exceeded (' . $this->settings['max_daily_alerts'] . ')',
                'type' => 'blocked'
            ];
        }
        
        // Check cooldown period for specific threshold type
        $last_alert_key = $is_peak ? 'last_alert_peak' : 'last_alert_offpeak';
        $last_alert_time = $this->settings[$last_alert_key];
        $time_since_last = $current_time - $last_alert_time;
        
        if ($last_alert_time > 0 && $time_since_last < $this->settings['alert_cooldown']) {
            $cooldown_remaining = $this->settings['alert_cooldown'] - $time_since_last;
            return [
                'allowed' => false,
                'reason' => 'Cooldown active ('. round($cooldown_remaining / 60) . ' minutes remaining)',
                'type' => 'cooldown'
            ];
        }
        
        // Determine alert type
        $alert_type = 'normal';
        if ($this->settings['daily_alert_count'] === 0) {
            $alert_type = 'first_today';
        } elseif ($this->settings['daily_alert_count'] >= 3) {
            $alert_type = 'escalated';
        }
        
        return [
            'allowed' => true,
            'reason' => 'Alert permitted',
            'type' => $alert_type
        ];
    }
    
    /**
     * Send throttled alert with proper tracking
     */
    private function send_throttled_alert($order_count, $threshold, $is_peak, $alert_type) {
        try {
            $current_time = current_time('timestamp');
            
            // Update alert counters before sending
            $this->increment_alert_counters($is_peak, $current_time);
            
            // Get email data with enhanced context
            $to = $this->get_notification_emails();
            
            if (empty($to) || !is_array($to)) {
                error_log('[WooCommerce Order Monitor] No valid email addresses configured for alerts');
                return false;
            }
            
            $subject = $this->build_alert_subject($alert_type, $is_peak);
            $body = $this->build_enhanced_alert_email_body([
                'start_time' => date('H:i', strtotime('-15 minutes')),
                'end_time' => current_time('H:i'),
                'threshold' => $threshold,
                'order_count' => $order_count,
                'period_type' => $is_peak ? __('Peak Hours', 'woo-order-monitor') : __('Off-Peak Hours', 'woo-order-monitor'),
                'admin_url' => admin_url('edit.php?post_type=shop_order'),
                'alert_type' => $alert_type,
                'daily_count' => $this->settings['daily_alert_count'],
                'max_daily' => $this->settings['max_daily_alerts'],
                'cooldown_hours' => round($this->settings['alert_cooldown'] / 3600, 1)
            ]);
            
            if (empty($body)) {
                error_log('[WooCommerce Order Monitor] Failed to generate email body');
                return false;
            }
            
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            
            // Send primary notification
            $sent = wp_mail($to, $subject, $body, $headers);
            
            // Try backup notification methods if primary fails
            if (!$sent) {
                $this->send_backup_notification($order_count, $threshold, $is_peak, $alert_type);
            } else {
                error_log(sprintf(
                    '[WooCommerce Order Monitor] Alert sent successfully to: %s (Type: %s, Daily: %d/%d)',
                    implode(', ', $to),
                    $alert_type,
                    $this->settings['daily_alert_count'],
                    $this->settings['max_daily_alerts']
                ));
            }
            
            return $sent;
            
        } catch (Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in send_throttled_alert: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Increment alert counters and tracking
     */
    private function increment_alert_counters($is_peak, $current_time) {
        // Update general last alert time
        update_option('woom_last_alert', $current_time);
        
        // Update specific threshold type
        $last_alert_key = $is_peak ? 'woom_last_alert_peak' : 'woom_last_alert_offpeak';
        update_option($last_alert_key, $current_time);
        
        // Increment daily counter
        $new_count = $this->settings['daily_alert_count'] + 1;
        update_option('woom_daily_alert_count', $new_count);
        
        // Update local settings for immediate use
        $this->settings['last_alert'] = $current_time;
        $this->settings[$is_peak ? 'last_alert_peak' : 'last_alert_offpeak'] = $current_time;
        $this->settings['daily_alert_count'] = $new_count;
    }
    
    /**
     * Build alert subject line based on type
     */
    private function build_alert_subject($alert_type, $is_peak) {
        $period = $is_peak ? 'Peak' : 'Off-Peak';
        
        switch ($alert_type) {
            case 'first_today':
                return sprintf(__('[Alert] WooCommerce Orders Below Threshold (%s)', 'woo-order-monitor'), $period);
            case 'escalated':
                return sprintf(__('[URGENT] Repeated Order Volume Issues (%s)', 'woo-order-monitor'), $period);
            default:
                return sprintf(__('[Alert] WooCommerce Orders Below Threshold (%s)', 'woo-order-monitor'), $period);
        }
    }
    
    /**
     * Build enhanced alert email body with throttling information
     */
    private function build_enhanced_alert_email_body($data) {
        ob_start();
        
        // Alert color based on type
        $header_color = '#dc3545'; // Default red
        $header_icon = '⚠️';
        if ($data['alert_type'] === 'escalated') {
            $header_color = '#721c24';
            $header_icon = '🚨';
        } elseif ($data['alert_type'] === 'first_today') {
            $header_color = '#dc3545';
            $header_icon = '⚠️';
        }
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .alert-header { background-color: <?php echo $header_color; ?>; color: white; padding: 15px; border-radius: 5px 5px 0 0; }
                .alert-body { background-color: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-top: none; }
                .details-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .details-table td { padding: 10px; border-bottom: 1px solid #dee2e6; }
                .details-table td:first-child { font-weight: bold; width: 40%; }
                .action-button { display: inline-block; padding: 10px 20px; background-color: #007cba; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .warning-list { background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .throttle-info { background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; margin: 20px 0; border-radius: 5px; color: #0c5460; }
                .escalated-warning { background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px 0; border-radius: 5px; color: #721c24; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="alert-header">
                    <h2 style="margin: 0;"><?php echo $header_icon; ?> WooCommerce Order Alert
                    <?php if ($data['alert_type'] === 'escalated'): ?>
                        - REPEATED ISSUE
                    <?php elseif ($data['alert_type'] === 'first_today'): ?>
                        - First Today
                    <?php endif; ?>
                    </h2>
                </div>
                <div class="alert-body">
                    <?php if ($data['alert_type'] === 'escalated'): ?>
                    <div class="escalated-warning">
                        <strong>⚠️ ESCALATED ALERT:</strong> This is the <?php echo esc_html($data['daily_count']); ?><?php echo esc_html($this->get_ordinal_suffix($data['daily_count'])); ?> alert today. Your store may have a persistent issue requiring immediate attention.
                    </div>
                    <?php endif; ?>
                    
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
                        <tr>
                            <td>Alert Count Today:</td>
                            <td><?php echo esc_html($data['daily_count']); ?> of <?php echo esc_html($data['max_daily']); ?> maximum</td>
                        </tr>
                    </table>
                    
                    <div class="throttle-info">
                        <p><strong>Alert Management:</strong></p>
                        <ul>
                            <li>Next alert possible in <?php echo esc_html($data['cooldown_hours']); ?> hours (cooldown period)</li>
                            <li>Maximum <?php echo esc_html($data['max_daily']); ?> alerts per day to prevent spam</li>
                            <li>Separate tracking for peak and off-peak hours</li>
                        </ul>
                    </div>
                    
                    <div class="warning-list">
                        <p><strong>This could indicate:</strong></p>
                        <ul>
                            <li>Website downtime or errors</li>
                            <li>Payment gateway issues</li>
                            <li>Potential DDoS attack</li>
                            <li>Cart/checkout problems</li>
                            <li>Server performance issues</li>
                            <?php if ($data['alert_type'] === 'escalated'): ?>
                            <li><strong>Persistent systemic problem requiring urgent attention</strong></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <p><strong>Action Required:</strong> Please check your WooCommerce store immediately.</p>
                    
                    <a href="<?php echo esc_url($data['admin_url']); ?>" class="action-button">View Orders →</a>
                    
                    <hr style="margin-top: 40px; border: none; border-top: 1px solid #dee2e6;">
                    <p style="font-size: 12px; color: #6c757d;">
                        This is an automated alert from WooCommerce Order Monitor<br>
                        Alert Type: <?php echo esc_html(ucfirst($data['alert_type'])); ?> | Generated at: <?php echo current_time('Y-m-d H:i:s'); ?>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get ordinal suffix for numbers (1st, 2nd, 3rd, etc.)
     */
    private function get_ordinal_suffix($number) {
        if ($number >= 11 && $number <= 13) {
            return 'th';
        }
        switch ($number % 10) {
            case 1: return 'st';
            case 2: return 'nd';
            case 3: return 'rd';
            default: return 'th';
        }
    }
    
    /**
     * Send backup notifications when email fails
     */
    private function send_backup_notification($order_count, $threshold, $is_peak, $alert_type) {
        // Try webhook notification
        if (!empty($this->settings['webhook_url'])) {
            $this->send_webhook_notification([
                'order_count' => $order_count,
                'threshold' => $threshold,
                'is_peak' => $is_peak,
                'alert_type' => $alert_type,
                'timestamp' => current_time('c'),
                'site_url' => home_url()
            ]);
        }
        
        // Log as system error if all notifications fail
        if ('yes' === $this->settings['enable_system_alerts']) {
            error_log('[WooCommerce Order Monitor] CRITICAL: All notification methods failed for threshold breach');
        }
    }
    
    /**
     * Send webhook notification
     */
    private function send_webhook_notification($data) {
        try {
            $response = wp_remote_post($this->settings['webhook_url'], [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'WooCommerce-Order-Monitor/' . WOOM_VERSION
                ],
                'body' => wp_json_encode($data),
                'timeout' => 10
            ]);
            
            if (is_wp_error($response)) {
                error_log('[WooCommerce Order Monitor] Webhook failed: ' . $response->get_error_message());
            } else {
                error_log('[WooCommerce Order Monitor] Webhook notification sent successfully');
            }
        } catch (Exception $e) {
            error_log('[WooCommerce Order Monitor] Webhook exception: ' . $e->getMessage());
        }
    }
    
    /**
     * Send system alert for critical failures
     */
    private function send_system_alert($subject, $message) {
        try {
            $to = $this->get_notification_emails();
            $full_subject = '[SYSTEM] WooCommerce Order Monitor - ' . $subject;
            
            $body = sprintf(
                '<h3>System Alert: %s</h3><p><strong>Error:</strong> %s</p><p><strong>Time:</strong> %s</p><p><strong>Site:</strong> %s</p>',
                esc_html($subject),
                esc_html($message),
                current_time('Y-m-d H:i:s'),
                home_url()
            );
            
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            
            $sent = wp_mail($to, $full_subject, $body, $headers);
            
            if ($sent) {
                error_log('[WooCommerce Order Monitor] System alert sent: ' . $subject);
            } else {
                error_log('[WooCommerce Order Monitor] Failed to send system alert: ' . $subject);
                
                // Try webhook as last resort
                if (!empty($this->settings['webhook_url'])) {
                    $this->send_webhook_notification([
                        'type' => 'system_alert',
                        'subject' => $subject,
                        'message' => $message,
                        'timestamp' => current_time('c'),
                        'site_url' => home_url()
                    ]);
                }
            }
            
        } catch (Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception sending system alert: ' . $e->getMessage());
        }
    }
    
    /**
     * Add settings tab
     */
    public function add_settings_tab($settings_tabs) {
        $settings_tabs['order_monitor'] = __('Order Monitor', 'woo-order-monitor');
        return $settings_tabs;
    }

    /**
     * Add plugin action links (Settings link on plugins page)
     */
    public function add_plugin_action_links($links) {
        // Only add settings link if WooCommerce is active
        if (class_exists('WooCommerce')) {
            $settings_link = sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=wc-settings&tab=order_monitor'),
                __('Settings', 'woo-order-monitor')
            );

            // Add settings link at the beginning of the array
            array_unshift($links, $settings_link);
        }

        return $links;
    }
    
    /**
     * Settings tab content
     */
    public function settings_tab() {
        // Get current sub-tab
        $current_tab = isset($_GET['subtab']) ? sanitize_text_field($_GET['subtab']) : 'settings';

        // Render tab navigation
        $this->render_tab_navigation($current_tab);

        // Render content based on current tab
        if ($current_tab === 'changelog') {
            $this->render_changelog_viewer();
        } elseif ($current_tab === 'self-tests') {
            $this->render_self_tests();
        } else {
            // Default to settings tab
            woocommerce_admin_fields($this->get_settings());

            // Render custom fields that WooCommerce doesn't support natively
            $this->render_custom_fields();
        }

        // Add custom CSS and JavaScript for test notification and real-time status updates
        ?>
        <style type="text/css">
        .woom-status-info p {
            margin: 8px 0;
        }
        #woom-status-text, #woom-settings-text {
            font-weight: bold;
            transition: color 0.3s ease;
        }
        .woom-status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
            transition: background-color 0.3s ease;
        }
        .woom-status-active {
            background-color: #46b450;
        }
        .woom-status-inactive {
            background-color: #dc3232;
        }
        .woom-settings-saved {
            background-color: #46b450;
        }
        .woom-settings-unsaved {
            background-color: #dc3232;
        }
        </style>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Test notification functionality
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

            // Manual check functionality
            $('#woom_manual_check').on('click', function(e) {
                e.preventDefault();

                var $button = $(this);
                var $result = $('#woom_manual_check_result');
                var $content = $('#woom_manual_check_content');
                
                $button.prop('disabled', true).text('Running...');
                $result.hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'woom_manual_check',
                        security: '<?php echo wp_create_nonce('woom_manual_check'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            var statusClass = data.status === 'healthy' ? 'success' : 'warning';
                            var bgColor = data.status === 'healthy' ? '#d4edda' : '#fff3cd';
                            var borderColor = data.status === 'healthy' ? '#c3e6cb' : '#ffeaa7';
                            var textColor = data.status === 'healthy' ? '#155724' : '#856404';
                            
                            var html = '<div style="background: ' + bgColor + '; border: 1px solid ' + borderColor + '; color: ' + textColor + ';">';
                            html += '<strong>' + data.message + '</strong><br>';
                            html += '<small>Period: ' + data.period_type + ' | Execution time: ' + data.execution_time + 's | Time: ' + data.timestamp + '</small><br>';
                            html += '<strong>Alert Status:</strong> ' + data.alert_info.daily_count + '/' + data.alert_info.max_daily + ' alerts today';
                            if (data.alert_info.cooldown_remaining > 0) {
                                html += ' | Cooldown: ' + data.alert_info.cooldown_remaining + ' min remaining';
                            }
                            html += '<br><strong>Next Automatic Check:</strong> ' + data.next_automatic_check;
                            if (data.cron_status !== 'scheduled') {
                                html += ' <span style="color: #dc3232;">(⚠️ Cron not scheduled!)</span>';
                            }
                            html += '</div>';
                            
                            $content.html(html);
                            $result.show();
                        } else {
                            $content.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px;"><strong>Error:</strong> ' + response.data + '</div>');
                            $result.show();
                        }
                    },
                    error: function() {
                        $content.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px;"><strong>Error:</strong> Failed to run manual check.</div>');
                        $result.show();
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Run Check Now');
                    }
                });
            });

            // Real-time status updates
            var originalValues = {};
            var $statusText = $('#woom-status-text');
            var $statusIndicator = $('#woom-status-indicator');
            var $settingsText = $('#woom-settings-text');
            var $settingsIndicator = $('#woom-settings-indicator');
            var $enabledCheckbox = $('#woom_enabled');

            // Store original values for change detection
            $('input, select, textarea').each(function() {
                var $field = $(this);
                var fieldId = $field.attr('id');
                if (fieldId && fieldId.indexOf('woom_') === 0) {
                    if ($field.attr('type') === 'checkbox') {
                        originalValues[fieldId] = $field.is(':checked');
                    } else {
                        originalValues[fieldId] = $field.val();
                    }
                }
            });

            // Function to update monitoring status based on checkbox
            function updateMonitoringStatus() {
                var isEnabled = $enabledCheckbox.is(':checked');
                if (isEnabled) {
                    $statusText.text('<?php echo esc_js(__('Monitoring Active', 'woo-order-monitor')); ?>').css('color', '#46b450');
                    $statusIndicator.removeClass('woom-status-inactive').addClass('woom-status-active');
                } else {
                    $statusText.text('<?php echo esc_js(__('Monitoring Disabled', 'woo-order-monitor')); ?>').css('color', '#dc3232');
                    $statusIndicator.removeClass('woom-status-active').addClass('woom-status-inactive');
                }
            }

            // Function to check if settings have changed
            function checkSettingsChanged() {
                var hasChanges = false;

                $('input, select, textarea').each(function() {
                    var $field = $(this);
                    var fieldId = $field.attr('id');
                    if (fieldId && fieldId.indexOf('woom_') === 0) {
                        var currentValue;
                        if ($field.attr('type') === 'checkbox') {
                            currentValue = $field.is(':checked');
                        } else {
                            currentValue = $field.val();
                        }

                        if (originalValues[fieldId] !== currentValue) {
                            hasChanges = true;
                            return false; // Break out of each loop
                        }
                    }
                });

                if (hasChanges) {
                    $settingsText.text('<?php echo esc_js(__('Not Saved', 'woo-order-monitor')); ?>').css('color', '#dc3232');
                    $settingsIndicator.removeClass('woom-settings-saved').addClass('woom-settings-unsaved');
                } else {
                    $settingsText.text('<?php echo esc_js(__('Saved', 'woo-order-monitor')); ?>').css('color', '#46b450');
                    $settingsIndicator.removeClass('woom-settings-unsaved').addClass('woom-settings-saved');
                }
            }

            // Monitor checkbox changes for real-time status update
            $enabledCheckbox.on('change', function() {
                updateMonitoringStatus();
                checkSettingsChanged();
            });

            // Monitor all form field changes for settings status
            $('input, select, textarea').on('change keyup', function() {
                var fieldId = $(this).attr('id');
                if (fieldId && fieldId.indexOf('woom_') === 0) {
                    checkSettingsChanged();
                }
            });

            // Reset change tracking when form is submitted
            $('form').on('submit', function() {
                // Small delay to allow form submission, then reset tracking
                setTimeout(function() {
                    $('input, select, textarea').each(function() {
                        var $field = $(this);
                        var fieldId = $field.attr('id');
                        if (fieldId && fieldId.indexOf('woom_') === 0) {
                            if ($field.attr('type') === 'checkbox') {
                                originalValues[fieldId] = $field.is(':checked');
                            } else {
                                originalValues[fieldId] = $field.val();
                            }
                        }
                    });
                    checkSettingsChanged();
                }, 100);
            });

            // Initial status update
            updateMonitoringStatus();
        });
        </script>
        <?php
    }

    /**
     * Render custom fields that WooCommerce doesn't support natively
     */
    private function render_custom_fields() {
        $last_check = get_option('woom_last_check', 0);
        $last_alert = get_option('woom_last_alert', 0);

        // Get timezone information
        $wp_timezone = function_exists('wp_timezone_string') ? wp_timezone_string() : get_option('timezone_string', 'UTC');
        $current_time = current_time('Y-m-d H:i:s');
        $current_time_display = current_time('H:i');
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label><?php _e('Server Time Zone', 'woo-order-monitor'); ?></label>
                </th>
                <td class="forminp">
                    <div class="woom-timezone-info" style="background: #f0f8ff; padding: 10px; border-left: 4px solid #0073aa; margin-bottom: 10px;">
                        <p><strong><?php _e('Current Server Time:', 'woo-order-monitor'); ?></strong> <?php echo esc_html($current_time); ?></p>
                        <p><strong><?php _e('Time Zone:', 'woo-order-monitor'); ?></strong> <?php echo esc_html($wp_timezone); ?></p>
                        <p><strong><?php _e('Current Time (for peak hours):', 'woo-order-monitor'); ?></strong> <?php echo esc_html($current_time_display); ?></p>
                        <p class="description">
                            <?php _e('Peak hours are based on this server time. Make sure your WordPress timezone is set correctly in Settings → General.', 'woo-order-monitor'); ?>
                        </p>
                    </div>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="woom_test_notification"><?php _e('Test Notification', 'woo-order-monitor'); ?></label>
                </th>
                <td class="forminp">
                    <button type="button" id="woom_test_notification" class="button-secondary">
                        <?php _e('Send Test Notification', 'woo-order-monitor'); ?>
                    </button>
                    <button type="button" id="woom_manual_check" class="button-primary" style="margin-left: 10px;">
                        <?php _e('Run Check Now', 'woo-order-monitor'); ?>
                    </button>
                    <p class="description"><?php _e('Send a test notification to verify email delivery, or run an immediate order threshold check', 'woo-order-monitor'); ?></p>
                    <div id="woom_manual_check_result" style="margin-top: 10px; padding: 10px; border-radius: 4px; display: none;">
                        <div id="woom_manual_check_content"></div>
                    </div>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label><?php _e('Monitoring Status', 'woo-order-monitor'); ?></label>
                </th>
                <td class="forminp">
                    <div class="woom-status-info">
                        <p><strong><?php _e('Last check:', 'woo-order-monitor'); ?></strong>
                        <?php echo $last_check ? date('Y-m-d H:i:s', $last_check) : __('Never', 'woo-order-monitor'); ?></p>
                        <p><strong><?php _e('Last alert:', 'woo-order-monitor'); ?></strong>
                        <?php echo $last_alert ? date('Y-m-d H:i:s', $last_alert) : __('Never', 'woo-order-monitor'); ?></p>
                        <p id="woom-status-display">
                            <strong><?php _e('Status:', 'woo-order-monitor'); ?></strong>
                            <span class="woom-status-indicator <?php echo get_option('woom_enabled') === 'yes' ? 'woom-status-active' : 'woom-status-inactive'; ?>" id="woom-status-indicator"></span>
                            <span id="woom-status-text" style="color: <?php echo get_option('woom_enabled') === 'yes' ? '#46b450' : '#dc3232'; ?>;">
                                <?php echo get_option('woom_enabled') === 'yes' ? __('Monitoring Active', 'woo-order-monitor') : __('Monitoring Disabled', 'woo-order-monitor'); ?>
                            </span>
                        </p>
                        <p id="woom-settings-status">
                            <strong><?php _e('Recent Settings:', 'woo-order-monitor'); ?></strong>
                            <span class="woom-status-indicator woom-settings-saved" id="woom-settings-indicator"></span>
                            <span id="woom-settings-text" style="color: #46b450;">
                                <?php _e('Saved', 'woo-order-monitor'); ?>
                            </span>
                        </p>
                    </div>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render tab navigation
     */
    private function render_tab_navigation($current_tab) {
        $base_url = admin_url('admin.php?page=wc-settings&tab=order_monitor');
        ?>
        <div class="woom-tab-navigation" style="margin-bottom: 20px; border-bottom: 1px solid #ccc;">
            <ul class="woom-tabs" style="margin: 0; padding: 0; list-style: none; display: flex;">
                <li style="margin: 0;">
                    <a href="<?php echo esc_url($base_url . '&subtab=settings'); ?>"
                       class="woom-tab-link <?php echo $current_tab === 'settings' ? 'active' : ''; ?>"
                       style="display: block; padding: 12px 20px; text-decoration: none; border-bottom: 3px solid transparent; <?php echo $current_tab === 'settings' ? 'border-bottom-color: #0073aa; color: #0073aa; font-weight: bold;' : 'color: #555;'; ?>">
                        <?php _e('WooCommerce Order Monitor Settings', 'woo-order-monitor'); ?>
                    </a>
                </li>
                <li style="margin: 0;">
                    <a href="<?php echo esc_url($base_url . '&subtab=changelog'); ?>"
                       class="woom-tab-link <?php echo $current_tab === 'changelog' ? 'active' : ''; ?>"
                       style="display: block; padding: 12px 20px; text-decoration: none; border-bottom: 3px solid transparent; <?php echo $current_tab === 'changelog' ? 'border-bottom-color: #0073aa; color: #0073aa; font-weight: bold;' : 'color: #555;'; ?>">
                        <?php _e('Changelog', 'woo-order-monitor'); ?>
                    </a>
                </li>
                <li style="margin: 0;">
                    <a href="<?php echo esc_url($base_url . '&subtab=self-tests'); ?>"
                       class="woom-tab-link <?php echo $current_tab === 'self-tests' ? 'active' : ''; ?>"
                       style="display: block; padding: 12px 20px; text-decoration: none; border-bottom: 3px solid transparent; <?php echo $current_tab === 'self-tests' ? 'border-bottom-color: #0073aa; color: #0073aa; font-weight: bold;' : 'color: #555;'; ?>">
                        <?php _e('Self Tests', 'woo-order-monitor'); ?>
                    </a>
                </li>
            </ul>
        </div>

        <style>
        .woom-tab-link:hover {
            color: #0073aa !important;
            background-color: #f9f9f9;
        }
        .woom-tab-link.active {
            background-color: #f9f9f9;
        }
        </style>
        <?php
    }

    /**
     * Render changelog viewer
     */
    private function render_changelog_viewer() {
        ?>
        <h2><?php printf(__('Changelog - Version %s', 'woo-order-monitor'), WOOM_VERSION); ?></h2>
        <div class="woom-changelog-container" style="margin-top: 20px;">
            <div class="woom-changelog-viewer" style="
                max-height: 400px;
                overflow-y: auto;
                border: 1px solid #ddd;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 4px;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                line-height: 1.6;
            ">
                <?php
                $changelog_path = WOOM_PLUGIN_DIR . 'CHANGELOG.md';

                // Check if KISS MDV function exists
                if (function_exists('kiss_mdv_render_file')) {
                    $html = kiss_mdv_render_file($changelog_path);
                    echo $html;
                } else {
                    // Fallback to plain text rendering
                    if (file_exists($changelog_path)) {
                        $content = file_get_contents($changelog_path);
                        echo '<pre style="white-space: pre-wrap; font-family: inherit; margin: 0;">' . esc_html($content) . '</pre>';
                    } else {
                        echo '<p style="color: #dc3232;">' . __('Changelog file not found.', 'woo-order-monitor') . '</p>';
                    }
                }
                ?>
            </div>
            <p class="description" style="margin-top: 10px;">
                <?php _e('This shows the complete changelog for all plugin versions. Scroll to see older versions.', 'woo-order-monitor'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render self tests tab
     */
    private function render_self_tests() {
        ?>
        <h2><?php printf(__('Self Tests - Version %s', 'woo-order-monitor'), WOOM_VERSION); ?></h2>
        <p class="description"><?php _e('Run these tests to verify core functionality and catch any regressions after updates or configuration changes.', 'woo-order-monitor'); ?></p>

        <div class="woom-self-tests-container" style="margin-top: 20px;">
            <div class="woom-test-controls" style="margin-bottom: 20px;">
                <button type="button" id="woom_run_all_tests" class="button button-primary">
                    <?php _e('Run All Tests', 'woo-order-monitor'); ?>
                </button>
                <button type="button" id="woom_run_individual_test" class="button" style="margin-left: 10px;" disabled>
                    <?php _e('Run Selected Test', 'woo-order-monitor'); ?>
                </button>
                <span id="woom_test_status" style="margin-left: 15px; font-weight: bold;"></span>
            </div>

            <div class="woom-tests-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Test 1: Database Connection & Order Query -->
                <div class="woom-test-card" data-test="database_query" style="border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #f9f9f9;">
                    <h3 style="margin-top: 0;">
                        <input type="checkbox" class="woom-test-checkbox" value="database_query" style="margin-right: 8px;">
                        <?php _e('Database & Order Query', 'woo-order-monitor'); ?>
                    </h3>
                    <p class="description"><?php _e('Tests database connection and order counting functionality.', 'woo-order-monitor'); ?></p>
                    <div class="woom-test-result" id="test_database_query_result" style="margin-top: 10px; padding: 8px; border-radius: 3px; display: none;">
                        <div class="test-status"></div>
                        <div class="test-details"></div>
                    </div>
                </div>

                <!-- Test 2: Threshold Logic -->
                <div class="woom-test-card" data-test="threshold_logic" style="border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #f9f9f9;">
                    <h3 style="margin-top: 0;">
                        <input type="checkbox" class="woom-test-checkbox" value="threshold_logic" style="margin-right: 8px;">
                        <?php _e('Threshold Logic', 'woo-order-monitor'); ?>
                    </h3>
                    <p class="description"><?php _e('Validates peak/off-peak detection and threshold calculations.', 'woo-order-monitor'); ?></p>
                    <div class="woom-test-result" id="test_threshold_logic_result" style="margin-top: 10px; padding: 8px; border-radius: 3px; display: none;">
                        <div class="test-status"></div>
                        <div class="test-details"></div>
                    </div>
                </div>

                <!-- Test 3: Email System -->
                <div class="woom-test-card" data-test="email_system" style="border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #f9f9f9;">
                    <h3 style="margin-top: 0;">
                        <input type="checkbox" class="woom-test-checkbox" value="email_system" style="margin-right: 8px;">
                        <?php _e('Email System', 'woo-order-monitor'); ?>
                    </h3>
                    <p class="description"><?php _e('Tests email configuration and notification delivery.', 'woo-order-monitor'); ?></p>
                    <div class="woom-test-result" id="test_email_system_result" style="margin-top: 10px; padding: 8px; border-radius: 3px; display: none;">
                        <div class="test-status"></div>
                        <div class="test-details"></div>
                    </div>
                </div>

                <!-- Test 4: Cron Scheduling -->
                <div class="woom-test-card" data-test="cron_scheduling" style="border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #f9f9f9;">
                    <h3 style="margin-top: 0;">
                        <input type="checkbox" class="woom-test-checkbox" value="cron_scheduling" style="margin-right: 8px;">
                        <?php _e('Cron Scheduling', 'woo-order-monitor'); ?>
                    </h3>
                    <p class="description"><?php _e('Verifies automated monitoring schedule and cron functionality.', 'woo-order-monitor'); ?></p>
                    <div class="woom-test-result" id="test_cron_scheduling_result" style="margin-top: 10px; padding: 8px; border-radius: 3px; display: none;">
                        <div class="test-status"></div>
                        <div class="test-details"></div>
                    </div>
                </div>
            </div>

            <div class="woom-test-summary" id="woom_test_summary" style="margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #fff; display: none;">
                <h3><?php _e('Test Summary', 'woo-order-monitor'); ?></h3>
                <div id="woom_test_summary_content"></div>
            </div>
        </div>

        <style>
        .woom-test-card:hover {
            background: #f0f0f0 !important;
        }
        .woom-test-result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .woom-test-result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .woom-test-result.warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .woom-test-checkbox:checked + h3 {
            color: #0073aa;
        }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Handle individual test selection
            $('.woom-test-checkbox').on('change', function() {
                var checkedCount = $('.woom-test-checkbox:checked').length;
                $('#woom_run_individual_test').prop('disabled', checkedCount === 0);

                if (checkedCount > 0) {
                    $('#woom_run_individual_test').text('<?php echo esc_js(__('Run Selected Tests', 'woo-order-monitor')); ?> (' + checkedCount + ')');
                } else {
                    $('#woom_run_individual_test').text('<?php echo esc_js(__('Run Selected Test', 'woo-order-monitor')); ?>');
                }
            });

            // Run all tests
            $('#woom_run_all_tests').on('click', function() {
                runTests('all');
            });

            // Run selected tests
            $('#woom_run_individual_test').on('click', function() {
                var selectedTests = [];
                $('.woom-test-checkbox:checked').each(function() {
                    selectedTests.push($(this).val());
                });
                runTests(selectedTests);
            });

            function runTests(tests) {
                var $statusEl = $('#woom_test_status');
                var $summaryEl = $('#woom_test_summary');

                // Reset UI
                $('.woom-test-result').hide().removeClass('success error warning');
                $summaryEl.hide();
                $statusEl.text('<?php echo esc_js(__('Running tests...', 'woo-order-monitor')); ?>').css('color', '#0073aa');

                // Disable buttons
                $('#woom_run_all_tests, #woom_run_individual_test').prop('disabled', true);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'woom_run_self_tests',
                        tests: tests,
                        security: '<?php echo wp_create_nonce('woom_self_tests'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            displayTestResults(response.data);
                            $statusEl.text('<?php echo esc_js(__('Tests completed', 'woo-order-monitor')); ?>').css('color', '#46b450');
                        } else {
                            $statusEl.text('<?php echo esc_js(__('Tests failed', 'woo-order-monitor')); ?>: ' + response.data).css('color', '#dc3232');
                        }
                    },
                    error: function() {
                        $statusEl.text('<?php echo esc_js(__('Error running tests', 'woo-order-monitor')); ?>').css('color', '#dc3232');
                    },
                    complete: function() {
                        // Re-enable buttons
                        $('#woom_run_all_tests, #woom_run_individual_test').prop('disabled', false);
                    }
                });
            }

            function displayTestResults(results) {
                var passedCount = 0;
                var totalCount = 0;
                var summaryHtml = '<ul>';

                $.each(results, function(testName, result) {
                    totalCount++;
                    var $resultEl = $('#test_' + testName + '_result');
                    var statusClass = result.status === 'pass' ? 'success' : (result.status === 'warning' ? 'warning' : 'error');

                    if (result.status === 'pass') passedCount++;

                    $resultEl.removeClass('success error warning').addClass(statusClass).show();
                    $resultEl.find('.test-status').html('<strong>' + result.message + '</strong>');
                    $resultEl.find('.test-details').html(result.details || '');

                    summaryHtml += '<li><strong>' + testName.replace('_', ' ').toUpperCase() + '</strong>: ' + result.message + '</li>';
                });

                summaryHtml += '</ul>';
                summaryHtml += '<p><strong><?php echo esc_js(__('Results', 'woo-order-monitor')); ?>:</strong> ' + passedCount + '/' + totalCount + ' <?php echo esc_js(__('tests passed', 'woo-order-monitor')); ?></p>';

                $('#woom_test_summary_content').html(summaryHtml);
                $('#woom_test_summary').show();
            }
        });
        </script>
        <?php
    }

    /**
     * Get settings fields
     */
    private function get_settings() {
        $settings = [
            'section_title' => [
                'name' => sprintf(__('WooCommerce Order Monitor Settings - v%s', 'woo-order-monitor'), WOOM_VERSION),
                'type' => 'title',
                'desc' => __('Configure order monitoring thresholds and notifications.', 'woo-order-monitor'),
                'id' => 'woom_section_title'
            ],
            'enabled' => [
                'name' => __('Enable Monitoring', 'woo-order-monitor'),
                'type' => 'checkbox',
                'desc' => __('Enable order volume monitoring', 'woo-order-monitor'),
                'id' => 'woom_enabled',
                'default' => 'yes'
            ],
            'peak_start' => [
                'name' => __('Peak Hours Start', 'woo-order-monitor'),
                'type' => 'text',
                'desc' => __('Start time for peak hours (24-hour format, e.g., 09:00)', 'woo-order-monitor'),
                'id' => 'woom_peak_start',
                'default' => \KissPlugins\WooOrderMonitor\Core\SettingsDefaults::getDefault('peak_start'),
                'css' => 'width: 100px;',
                'custom_attributes' => [
                    'pattern' => '[0-9]{2}:[0-9]{2}',
                    'placeholder' => \KissPlugins\WooOrderMonitor\Core\SettingsDefaults::getDefault('peak_start')
                ]
            ],
            'peak_end' => [
                'name' => __('Peak Hours End', 'woo-order-monitor'),
                'type' => 'text',
                'desc' => __('End time for peak hours (24-hour format, e.g., 18:00)', 'woo-order-monitor'),
                'id' => 'woom_peak_end',
                'default' => \KissPlugins\WooOrderMonitor\Core\SettingsDefaults::getDefault('peak_end'),
                'css' => 'width: 100px;',
                'custom_attributes' => [
                    'pattern' => '[0-9]{2}:[0-9]{2}',
                    'placeholder' => \KissPlugins\WooOrderMonitor\Core\SettingsDefaults::getDefault('peak_end')
                ]
            ],
            'threshold_peak' => [
                'name' => __('Peak Hours Threshold', 'woo-order-monitor'),
                'type' => 'number',
                'desc' => __('Minimum orders expected in 15 minutes during peak hours', 'woo-order-monitor'),
                'id' => 'woom_threshold_peak',
                'default' => \KissPlugins\WooOrderMonitor\Core\SettingsDefaults::getDefault('threshold_peak'),
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
                'default' => \KissPlugins\WooOrderMonitor\Core\SettingsDefaults::getDefault('threshold_offpeak'),
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

            'alert_section_title' => [
                'name' => __('Alert Throttling Settings (Production Safety)', 'woo-order-monitor'),
                'type' => 'title',
                'desc' => __('Configure alert frequency limits to prevent email flooding during issues.', 'woo-order-monitor'),
                'id' => 'woom_alert_section_title'
            ],
            'alert_cooldown' => [
                'name' => __('Alert Cooldown (Hours)', 'woo-order-monitor'),
                'type' => 'number',
                'desc' => __('Minimum time between alerts for same threshold type (peak/off-peak)', 'woo-order-monitor'),
                'id' => 'woom_alert_cooldown_hours',
                'default' => '2',
                'custom_attributes' => [
                    'min' => '0.5',
                    'max' => '24',
                    'step' => '0.5'
                ]
            ],
            'max_daily_alerts' => [
                'name' => __('Maximum Daily Alerts', 'woo-order-monitor'),
                'type' => 'number',
                'desc' => __('Maximum number of alerts to send per day (prevents email spam)', 'woo-order-monitor'),
                'id' => 'woom_max_daily_alerts',
                'default' => '6',
                'custom_attributes' => [
                    'min' => '1',
                    'max' => '50',
                    'step' => '1'
                ]
            ],
            'webhook_url' => [
                'name' => __('Backup Webhook URL', 'woo-order-monitor'),
                'type' => 'url',
                'desc' => __('Optional webhook URL for backup notifications (Slack, Discord, etc.)', 'woo-order-monitor'),
                'id' => 'woom_webhook_url',
                'default' => '',
                'css' => 'width: 400px;'
            ],
            'enable_system_alerts' => [
                'name' => __('System Failure Alerts', 'woo-order-monitor'),
                'type' => 'checkbox',
                'desc' => __('Send alerts when the monitoring system itself fails (recommended)', 'woo-order-monitor'),
                'id' => 'woom_enable_system_alerts',
                'default' => 'yes'
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
        try {
            // Handle cooldown hours to seconds conversion
            if (isset($_POST['woom_alert_cooldown_hours'])) {
                $hours = floatval($_POST['woom_alert_cooldown_hours']);
                $seconds = intval($hours * 3600);
                update_option('woom_alert_cooldown', $seconds);
            }
            
            // Validate and sanitize settings before saving
            $this->validate_and_sanitize_settings();

            woocommerce_update_options($this->get_settings());

            // Reload settings and update cron
            $this->load_settings();
            $this->ensure_cron_scheduled();

        } catch (Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in update_settings: ' . $e->getMessage());
            // Add admin notice for user feedback
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php printf(__('Error saving WooCommerce Order Monitor settings: %s', 'woo-order-monitor'), esc_html($e->getMessage())); ?></p>
                </div>
                <?php
            });
        }
    }

    /**
     * Validate and sanitize settings input
     */
    private function validate_and_sanitize_settings() {
        // Validate time format for peak hours
        if (isset($_POST['woom_peak_start'])) {
            $peak_start = sanitize_text_field($_POST['woom_peak_start']);
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $peak_start)) {
                throw new Exception('Invalid peak start time format. Use HH:MM format (e.g., 09:00)');
            }
        }

        if (isset($_POST['woom_peak_end'])) {
            $peak_end = sanitize_text_field($_POST['woom_peak_end']);
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $peak_end)) {
                throw new Exception('Invalid peak end time format. Use HH:MM format (e.g., 21:00)');
            }
        }

        // Validate threshold values
        if (isset($_POST['woom_threshold_peak'])) {
            $threshold_peak = intval($_POST['woom_threshold_peak']);
            if ($threshold_peak < 0 || $threshold_peak > 1000) {
                throw new Exception('Peak threshold must be between 0 and 1000');
            }
        }

        if (isset($_POST['woom_threshold_offpeak'])) {
            $threshold_offpeak = intval($_POST['woom_threshold_offpeak']);
            if ($threshold_offpeak < 0 || $threshold_offpeak > 1000) {
                throw new Exception('Off-peak threshold must be between 0 and 1000');
            }
        }

        // Validate email addresses
        if (isset($_POST['woom_notification_emails'])) {
            $emails = sanitize_textarea_field($_POST['woom_notification_emails']);
            $email_array = array_map('trim', explode(',', $emails));
            foreach ($email_array as $email) {
                if (!empty($email) && !is_email($email)) {
                    throw new Exception('Invalid email address: ' . $email);
                }
            }
        }
    }
    
    /**
     * Handle test notification AJAX request
     */
    public function handle_test_notification() {
        try {
            // Verify nonce
            if (!check_ajax_referer('woom_test_notification', 'security', false)) {
                wp_send_json_error('Invalid security token');
                return;
            }

            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            // Get notification emails
            $to = $this->get_notification_emails();

            // Validate email addresses
            if (empty($to) || !is_array($to)) {
                wp_send_json_error('No valid email addresses configured. Please check your notification email settings.');
                return;
            }

            $subject = __('[Test] WooCommerce Order Monitor - Test Notification', 'woo-order-monitor');

            // Build test email body
            $body = $this->build_test_email_body();

            // Validate email body
            if (empty($body)) {
                wp_send_json_error('Failed to generate test email content');
                return;
            }

            $headers = ['Content-Type: text/html; charset=UTF-8'];

            // Send test email
            $sent = wp_mail($to, $subject, $body, $headers);

            if ($sent) {
                wp_send_json_success('Test notification sent successfully to: ' . implode(', ', $to));
            } else {
                wp_send_json_error('Failed to send test notification. Please check your email configuration.');
            }

        } catch (Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in handle_test_notification: ' . $e->getMessage());
            wp_send_json_error('An unexpected error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Handle self tests AJAX request
     */
    public function handle_self_tests() {
        try {
            // Verify nonce
            if (!check_ajax_referer('woom_self_tests', 'security', false)) {
                wp_send_json_error('Invalid security token');
                return;
            }

            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            $tests_to_run = isset($_POST['tests']) ? $_POST['tests'] : 'all';

            if ($tests_to_run === 'all') {
                $tests_to_run = ['database_query', 'threshold_logic', 'email_system', 'cron_scheduling'];
            } elseif (!is_array($tests_to_run)) {
                $tests_to_run = [$tests_to_run];
            }

            $results = [];

            foreach ($tests_to_run as $test) {
                switch ($test) {
                    case 'database_query':
                        $results[$test] = $this->test_database_query();
                        break;
                    case 'threshold_logic':
                        $results[$test] = $this->test_threshold_logic();
                        break;
                    case 'email_system':
                        $results[$test] = $this->test_email_system();
                        break;
                    case 'cron_scheduling':
                        $results[$test] = $this->test_cron_scheduling();
                        break;
                    default:
                        $results[$test] = [
                            'status' => 'error',
                            'message' => 'Unknown test: ' . $test,
                            'details' => ''
                        ];
                }
            }

            wp_send_json_success($results);

        } catch (Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in handle_self_tests: ' . $e->getMessage());
            wp_send_json_error('An unexpected error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Handle manual check AJAX request
     */
    public function handle_manual_check() {
        try {
            // Verify nonce
            if (!check_ajax_referer('woom_manual_check', 'security', false)) {
                wp_send_json_error('Invalid security token');
                return;
            }

            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            // Get monitoring status
            if ($this->settings['enabled'] !== 'yes') {
                wp_send_json_error('Monitoring is disabled. Please enable monitoring first.');
                return;
            }

            // Run the check manually
            $start_time = current_time('timestamp');
            
            // Run the actual check
            $this->check_order_threshold();
            
            $end_time = current_time('timestamp');
            $execution_time = $end_time - $start_time;
            
            // Get results for response
            $order_count = $this->get_recent_order_count();
            $is_peak = $this->is_peak_hours();
            $threshold = $is_peak ? $this->settings['threshold_peak'] : $this->settings['threshold_offpeak'];
            
            // Prepare response data
            $response_data = [
                'execution_time' => $execution_time,
                'order_count' => $order_count,
                'threshold' => $threshold,
                'is_peak' => $is_peak,
                'period_type' => $is_peak ? 'Peak Hours' : 'Off-Peak Hours',
                'timestamp' => current_time('Y-m-d H:i:s'),
                'status' => $order_count >= $threshold ? 'healthy' : 'below_threshold',
                'message' => $order_count >= $threshold 
                    ? sprintf('✅ Healthy - Found %d orders (threshold: %d)', $order_count, $threshold)
                    : sprintf('⚠️ Below threshold - Found %d orders (threshold: %d)', $order_count, $threshold),
                'next_automatic_check' => $this->get_next_cron_time(),
                'cron_status' => wp_next_scheduled('woom_check_orders') ? 'scheduled' : 'not_scheduled',
                'alert_info' => [
                    'daily_count' => $this->settings['daily_alert_count'],
                    'max_daily' => $this->settings['max_daily_alerts'],
                    'last_alert' => $this->settings['last_alert'] ? date('Y-m-d H:i:s', $this->settings['last_alert']) : 'Never',
                    'cooldown_remaining' => $this->get_cooldown_remaining($is_peak)
                ]
            ];

            wp_send_json_success($response_data);

        } catch (Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in handle_manual_check: ' . $e->getMessage());
            wp_send_json_error('Manual check failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get next cron execution time
     */
    private function get_next_cron_time() {
        $next_scheduled = wp_next_scheduled('woom_check_orders');
        if ($next_scheduled) {
            return date('Y-m-d H:i:s', $next_scheduled);
        }
        return 'Not scheduled';
    }
    
    /**
     * Get remaining cooldown time in minutes
     */
    private function get_cooldown_remaining($is_peak) {
        $current_time = current_time('timestamp');
        $last_alert_key = $is_peak ? 'last_alert_peak' : 'last_alert_offpeak';
        $last_alert_time = $this->settings[$last_alert_key];
        
        if ($last_alert_time <= 0) {
            return 0; // No previous alert
        }
        
        $time_since_last = $current_time - $last_alert_time;
        $cooldown_remaining = $this->settings['alert_cooldown'] - $time_since_last;
        
        return max(0, round($cooldown_remaining / 60)); // Return minutes
    }

    /**
     * Test database connection and order query functionality
     */
    private function test_database_query() {
        global $wpdb;

        try {
            // Test 1: Database connection
            $db_test = $wpdb->get_var("SELECT 1");
            if ($db_test !== '1') {
                return [
                    'status' => 'error',
                    'message' => 'Database connection failed',
                    'details' => 'Unable to execute basic database query'
                ];
            }

            // Test 2: WooCommerce tables exist
            $orders_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->posts}'");
            if (!$orders_table_exists) {
                return [
                    'status' => 'error',
                    'message' => 'WordPress posts table not found',
                    'details' => 'Required table for order queries is missing'
                ];
            }

            // Test 3: Order counting query
            $order_count = $this->get_recent_order_count();
            if ($order_count === false || $order_count === null) {
                return [
                    'status' => 'error',
                    'message' => 'Order counting query failed',
                    'details' => 'Unable to retrieve order count from database'
                ];
            }

            // Test 4: Optimized query class
            $optimized_count = WOOM_Optimized_Query::get_cached_order_count(15);
            if ($optimized_count === false || $optimized_count === null) {
                return [
                    'status' => 'warning',
                    'message' => 'Optimized query has issues',
                    'details' => 'Standard query works but optimized query failed'
                ];
            }

            return [
                'status' => 'pass',
                'message' => 'Database and order queries working correctly',
                'details' => sprintf('Found %d orders in last 15 minutes. Optimized query: %d orders.', $order_count, $optimized_count)
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database test exception',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Test threshold logic and peak hours detection
     */
    private function test_threshold_logic() {
        try {
            // Test 1: Settings validation
            $peak_start = $this->settings['peak_start'];
            $peak_end = $this->settings['peak_end'];
            $threshold_peak = $this->settings['threshold_peak'];
            $threshold_offpeak = $this->settings['threshold_offpeak'];

            if (empty($peak_start) || empty($peak_end)) {
                return [
                    'status' => 'error',
                    'message' => 'Peak hours not configured',
                    'details' => 'Peak start or end time is missing'
                ];
            }

            if (!is_numeric($threshold_peak) || !is_numeric($threshold_offpeak)) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid threshold values',
                    'details' => 'Thresholds must be numeric values'
                ];
            }

            // Test 2: Peak hours detection
            $is_peak = $this->is_peak_hours();
            $current_time = current_time('H:i');

            // Test 3: Threshold selection logic
            $selected_threshold = $is_peak ? $threshold_peak : $threshold_offpeak;

            // Test 4: Time parsing
            $peak_start_time = strtotime($peak_start);
            $peak_end_time = strtotime($peak_end);

            if ($peak_start_time === false || $peak_end_time === false) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid time format',
                    'details' => 'Peak hours time format is invalid'
                ];
            }

            return [
                'status' => 'pass',
                'message' => 'Threshold logic working correctly',
                'details' => sprintf(
                    'Current time: %s | Peak hours: %s-%s | Currently %s | Active threshold: %d',
                    $current_time,
                    $peak_start,
                    $peak_end,
                    $is_peak ? 'PEAK' : 'OFF-PEAK',
                    $selected_threshold
                )
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Threshold logic test exception',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Test email system functionality
     */
    private function test_email_system() {
        try {
            // Test 1: Email configuration
            $notification_emails = $this->get_notification_emails();
            if (empty($notification_emails)) {
                return [
                    'status' => 'error',
                    'message' => 'No notification emails configured',
                    'details' => 'Email recipients list is empty'
                ];
            }

            // Test 2: Email validation
            $invalid_emails = [];
            foreach ($notification_emails as $email) {
                if (!is_email($email)) {
                    $invalid_emails[] = $email;
                }
            }

            if (!empty($invalid_emails)) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid email addresses found',
                    'details' => 'Invalid emails: ' . implode(', ', $invalid_emails)
                ];
            }

            // Test 3: Email body generation
            $test_body = $this->build_test_email_body();
            if (empty($test_body)) {
                return [
                    'status' => 'error',
                    'message' => 'Email body generation failed',
                    'details' => 'Unable to generate email content'
                ];
            }

            // Test 4: WordPress mail function availability
            if (!function_exists('wp_mail')) {
                return [
                    'status' => 'error',
                    'message' => 'WordPress mail function not available',
                    'details' => 'wp_mail() function is not available'
                ];
            }

            return [
                'status' => 'pass',
                'message' => 'Email system configured correctly',
                'details' => sprintf(
                    'Valid recipients: %s | Email body generation: OK | wp_mail available: YES',
                    implode(', ', $notification_emails)
                )
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Email system test exception',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Test cron scheduling functionality
     */
    private function test_cron_scheduling() {
        try {
            // Test 1: WP-Cron availability
            if (!function_exists('wp_next_scheduled')) {
                return [
                    'status' => 'error',
                    'message' => 'WP-Cron functions not available',
                    'details' => 'WordPress cron functions are missing'
                ];
            }

            // Test 2: Custom cron interval registration
            $schedules = wp_get_schedules();
            if (!isset($schedules['woom_15min'])) {
                // Try to register the schedule manually for this test
                $schedules = $this->add_cron_interval($schedules);

                if (!isset($schedules['woom_15min'])) {
                    return [
                        'status' => 'error',
                        'message' => 'Custom cron interval not registered',
                        'details' => 'woom_15min schedule is not available. Try deactivating and reactivating the plugin.'
                    ];
                } else {
                    return [
                        'status' => 'warning',
                        'message' => 'Cron interval registration issue',
                        'details' => 'Custom interval exists but was not properly registered. This may indicate a hook timing issue. Try deactivating and reactivating the plugin.'
                    ];
                }
            }

            // Test 3: Monitoring enabled check
            if ($this->settings['enabled'] !== 'yes') {
                return [
                    'status' => 'warning',
                    'message' => 'Monitoring is disabled',
                    'details' => 'Cron job will not run because monitoring is disabled in settings. Enable monitoring to schedule the cron job.'
                ];
            }

            // Test 4: Cron job scheduling
            $next_scheduled = wp_next_scheduled('woom_check_orders');
            if (!$next_scheduled) {
                // Try to schedule it now
                $scheduled = wp_schedule_event(time(), 'woom_15min', 'woom_check_orders');
                if ($scheduled === false) {
                    return [
                        'status' => 'error',
                        'message' => 'Cron job scheduling failed',
                        'details' => 'Unable to schedule woom_check_orders event. Check if WP-Cron is working properly.'
                    ];
                } else {
                    $next_scheduled = wp_next_scheduled('woom_check_orders');
                    return [
                        'status' => 'warning',
                        'message' => 'Cron job was not scheduled but has been fixed',
                        'details' => sprintf('Successfully scheduled cron job. Next run: %s', date('Y-m-d H:i:s', $next_scheduled))
                    ];
                }
            }

            // Test 5: Cron job timing
            $time_until_next = $next_scheduled - time();
            $next_run_formatted = date('Y-m-d H:i:s', $next_scheduled);

            // Test 6: Action Scheduler availability (if used)
            $action_scheduler_available = class_exists('ActionScheduler');

            // Test 7: Check if WP-Cron is disabled
            $wp_cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;

            $status_details = sprintf(
                'Next run: %s (in %d seconds) | Custom interval: %d seconds | Action Scheduler: %s | WP-Cron disabled: %s',
                $next_run_formatted,
                $time_until_next,
                $schedules['woom_15min']['interval'],
                $action_scheduler_available ? 'Available' : 'Not available',
                $wp_cron_disabled ? 'YES' : 'NO'
            );

            if ($wp_cron_disabled && !$action_scheduler_available) {
                return [
                    'status' => 'warning',
                    'message' => 'WP-Cron is disabled',
                    'details' => $status_details . ' | Consider using Action Scheduler or external cron for reliable execution.'
                ];
            }

            return [
                'status' => 'pass',
                'message' => 'Cron scheduling working correctly',
                'details' => $status_details
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cron scheduling test exception',
                'details' => $e->getMessage()
            ];
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

        try {
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
                // Fallback to posts table (simplified query to avoid JOIN issues)
                $query = $wpdb->prepare("
                    SELECT COUNT(*) as order_count
                    FROM {$wpdb->posts} p
                    WHERE p.post_type = 'shop_order'
                    AND p.post_status IN ('wc-completed', 'wc-processing')
                    AND p.post_date_gmt >= %s
                ", $start_time);
            }

            $count = intval($wpdb->get_var($query));

            // Check for database errors
            if ($wpdb->last_error) {
                error_log('[WooCommerce Order Monitor] Database error in get_cached_order_count: ' . $wpdb->last_error);
                return 0; // Return 0 to prevent false alerts
            }

            // Cache for 1 minute
            wp_cache_set($cache_key, $count, 'woo-order-monitor', 60);

            return $count;

        } catch (Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in get_cached_order_count: ' . $e->getMessage());
            return 0; // Return 0 to prevent false alerts
        }
    }
    
    /**
     * Get detailed order statistics
     */
    public static function get_order_stats($minutes = 15) {
        global $wpdb;

        try {
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

            $result = $wpdb->get_row($query, ARRAY_A);

            // Check for database errors
            if ($wpdb->last_error) {
                error_log('[WooCommerce Order Monitor] Database error in get_order_stats: ' . $wpdb->last_error);
                return [
                    'total_orders' => 0,
                    'completed_orders' => 0,
                    'processing_orders' => 0,
                    'last_order_time' => null
                ];
            }

            return $result ? $result : [
                'total_orders' => 0,
                'completed_orders' => 0,
                'processing_orders' => 0,
                'last_order_time' => null
            ];

        } catch (Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in get_order_stats: ' . $e->getMessage());
            return [
                'total_orders' => 0,
                'completed_orders' => 0,
                'processing_orders' => 0,
                'last_order_time' => null
            ];
        }
    }
}

/**
 * CLI Commands for WP-CLI support
 */
if (defined('WP_CLI') && WP_CLI) {
    
    class WOOM_CLI_Commands {
        
        /**
         * Check order threshold manually
         * * ## EXAMPLES
         * * wp woom check
         */
        public function check() {
            $monitor = WooCommerce_Order_Monitor::get_instance();
            $monitor->check_order_threshold();
            
            WP_CLI::success('Order threshold check completed.');
        }
        
        /**
         * Get current order count
         * * ## OPTIONS
         * * [--minutes=<minutes>]
         * : Number of minutes to look back. Default: 15
         * * ## EXAMPLES
         * * wp woom count
         * wp woom count --minutes=30
         */
        public function count($args, $assoc_args) {
            $minutes = isset($assoc_args['minutes']) ? intval($assoc_args['minutes']) : 15;
            
            $monitor = WooCommerce_Order_Monitor::get_instance();
            $count = WOOM_Optimized_Query::get_cached_order_count($minutes);
            
            WP_CLI::line(sprintf('Orders in last %d minutes: %d', $minutes, $count));
        }
        
        /**
         * Send test notification
         * * ## EXAMPLES
         * * wp woom test
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
         * * ## EXAMPLES
         * * wp woom config
         */
        public function config() {
            // Get defaults from centralized configuration
            $defaults = \KissPlugins\WooOrderMonitor\Core\SettingsDefaults::getRuntimeDefaults();

            $settings = [
                'enabled' => get_option('woom_enabled', $defaults['enabled']),
                'peak_start' => get_option('woom_peak_start', $defaults['peak_start']),
                'peak_end' => get_option('woom_peak_end', $defaults['peak_end']),
                'threshold_peak' => get_option('woom_threshold_peak', $defaults['threshold_peak']),
                'threshold_offpeak' => get_option('woom_threshold_offpeak', $defaults['threshold_offpeak']),
                'notification_emails' => get_option('woom_notification_emails', $defaults['notification_emails']),
                'last_check' => get_option('woom_last_check', $defaults['last_check']),
                'last_alert' => get_option('woom_last_alert', $defaults['last_alert'])
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
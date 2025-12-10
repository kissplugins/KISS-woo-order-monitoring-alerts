<?php
/**
 * WP-CLI Commands
 *
 * @package KissPlugins\WooOrderMonitor
 * @since 1.0.0
 */

namespace KissPlugins\WooOrderMonitor\CLI;

use KissPlugins\WooOrderMonitor\Core\Settings;
use KissPlugins\WooOrderMonitor\Monitoring\OrderMonitor;
use KissPlugins\WooOrderMonitor\Monitoring\OptimizedQuery;
use KissPlugins\WooOrderMonitor\Notifications\EmailNotification;

/**
 * Class Commands
 *
 * WP-CLI commands for WooCommerce Order Monitor plugin.
 */
class Commands {
    
    /**
     * Settings instance
     *
     * @var Settings
     */
    private $settings;
    
    /**
     * Order monitor instance
     *
     * @var OrderMonitor
     */
    private $monitor;
    
    /**
     * Email notification instance
     *
     * @var EmailNotification
     */
    private $email;
    
    /**
     * Constructor
     *
     * @param Settings $settings Settings instance
     * @param OrderMonitor $monitor Order monitor instance
     * @param EmailNotification $email Email notification instance
     */
    public function __construct(Settings $settings, OrderMonitor $monitor, EmailNotification $email) {
        $this->settings = $settings;
        $this->monitor = $monitor;
        $this->email = $email;
    }
    
    /**
     * Check order threshold manually
     *
     * ## EXAMPLES
     *
     *     wp woom check
     *
     * @when after_wp_load
     */
    public function check() {
        $this->monitor->checkOrderThreshold();
        \WP_CLI::success('Order threshold check completed.');
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
     *
     * @when after_wp_load
     */
    public function count($args, $assoc_args) {
        $minutes = isset($assoc_args['minutes']) ? intval($assoc_args['minutes']) : 15;
        
        $count = OptimizedQuery::getCachedOrderCount($minutes);
        
        \WP_CLI::line(sprintf('Orders in last %d minutes: %d', $minutes, $count));
    }
    
    /**
     * Send test notification
     *
     * ## EXAMPLES
     *
     *     wp woom test
     *
     * @when after_wp_load
     */
    public function test() {
        $result = $this->email->sendTestNotification();
        
        if ($result['success']) {
            \WP_CLI::success($result['message']);
        } else {
            \WP_CLI::error($result['message']);
        }
    }
    
    /**
     * Show current configuration
     *
     * ## EXAMPLES
     *
     *     wp woom config
     *
     * @when after_wp_load
     */
    public function config() {
        $enabled = $this->settings->isEnabled() ? 'Yes' : 'No';
        $peak_threshold = $this->settings->get('peak_threshold');
        $offpeak_threshold = $this->settings->get('offpeak_threshold');
        $peak_start = $this->settings->get('peak_start');
        $peak_end = $this->settings->get('peak_end');
        $notification_emails = $this->settings->get('notification_emails');
        
        \WP_CLI::line('=== WooCommerce Order Monitor Configuration ===');
        \WP_CLI::line('');
        \WP_CLI::line(sprintf('Monitoring Enabled: %s', $enabled));
        \WP_CLI::line(sprintf('Peak Threshold: %d orders', $peak_threshold));
        \WP_CLI::line(sprintf('Off-Peak Threshold: %d orders', $offpeak_threshold));
        \WP_CLI::line(sprintf('Peak Hours: %s to %s', $peak_start, $peak_end));
        \WP_CLI::line(sprintf('Notification Emails: %s', $notification_emails));
        \WP_CLI::line('');
    }
    
    /**
     * Clear all cached data
     *
     * ## EXAMPLES
     *
     *     wp woom clear-cache
     *
     * @when after_wp_load
     */
    public function clear_cache() {
        OptimizedQuery::clearAllCaches();
        \WP_CLI::success('All caches cleared successfully.');
    }
}


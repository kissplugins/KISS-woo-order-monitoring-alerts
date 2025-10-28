<?php
/**
 * Cron Scheduler Class
 * 
 * Manages WordPress cron scheduling for order monitoring,
 * including custom intervals and schedule management.
 * 
 * @package KissPlugins\WooOrderMonitor\Monitoring
 * @since 1.5.0
 */

namespace KissPlugins\WooOrderMonitor\Monitoring;

use KissPlugins\WooOrderMonitor\Core\Settings;

/**
 * Cron Scheduler Class
 * 
 * Handles all cron-related functionality including scheduling,
 * unscheduling, and monitoring of cron jobs.
 */
class CronScheduler {
    
    /**
     * Settings manager
     * 
     * @var Settings
     */
    private $settings;
    
    /**
     * Cron hook name
     * 
     * @var string
     */
    private $cron_hook = 'woom_check_orders';
    
    /**
     * Custom cron interval name
     * 
     * @var string
     */
    private $cron_interval = 'woom_15min';
    
    /**
     * Constructor
     * 
     * @param Settings $settings Settings manager instance
     */
    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }
    
    /**
     * Initialize cron scheduler hooks
     * 
     * @return void
     */
    public function initializeHooks(): void {
        // Register custom cron interval
        add_filter('cron_schedules', [$this, 'addCustomInterval']);
        
        // Handle cron execution
        add_action($this->cron_hook, [$this, 'executeCronJob']);
    }
    
    /**
     * Add custom cron interval
     * 
     * @param array $schedules Existing cron schedules
     * @return array Modified schedules array
     */
    public function addCustomInterval(array $schedules): array {
        $schedules[$this->cron_interval] = [
            'interval' => 900, // 15 minutes in seconds
            'display' => __('Every 15 minutes', 'woo-order-monitor')
        ];
        
        return $schedules;
    }
    
    /**
     * Schedule the monitoring cron job
     *
     * Creates a recurring WordPress cron job to monitor order thresholds.
     * Automatically clears any existing schedules to prevent duplicates and
     * ensures the job is scheduled with the correct interval.
     *
     * @return bool True if the cron job was scheduled successfully, false on failure.
     *              Failures are logged for debugging purposes.
     */
    public function schedule(): bool {
        try {
            // Clear any existing scheduled events first
            $this->unschedule();
            
            // Only schedule if monitoring is enabled
            if (!$this->settings->isEnabled()) {
                return false;
            }
            
            // Schedule the event
            $scheduled = wp_schedule_event(time(), $this->cron_interval, $this->cron_hook);
            
            if ($scheduled === false) {
                error_log('[WooCommerce Order Monitor] Failed to schedule cron job');
                return false;
            }
            
            // Log successful scheduling
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WooCommerce Order Monitor] Cron job scheduled successfully');
            }
            
            return true;
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception scheduling cron: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Unschedule the monitoring cron job
     * 
     * @return bool True if unscheduled successfully
     */
    public function unschedule(): bool {
        try {
            // Clear all scheduled instances of our hook
            wp_clear_scheduled_hook($this->cron_hook);
            
            // Log successful unscheduling
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WooCommerce Order Monitor] Cron job unscheduled successfully');
            }
            
            return true;
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception unscheduling cron: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reschedule the cron job (unschedule then schedule)
     * 
     * @return bool True if rescheduled successfully
     */
    public function reschedule(): bool {
        $this->unschedule();
        return $this->schedule();
    }
    
    /**
     * Check if cron job is scheduled
     * 
     * @return bool True if scheduled
     */
    public function isScheduled(): bool {
        return wp_next_scheduled($this->cron_hook) !== false;
    }
    
    /**
     * Get next scheduled run time
     * 
     * @return int|false Timestamp of next run, or false if not scheduled
     */
    public function getNextRunTime() {
        return wp_next_scheduled($this->cron_hook);
    }
    
    /**
     * Get cron status information
     * 
     * @return array Cron status details
     */
    public function getStatus(): array {
        $next_run = $this->getNextRunTime();
        $is_scheduled = $this->isScheduled();
        
        return [
            'is_scheduled' => $is_scheduled,
            'next_run_timestamp' => $next_run,
            'next_run_formatted' => $next_run ? date('Y-m-d H:i:s', $next_run) : null,
            'time_until_next_run' => $next_run ? $next_run - time() : null,
            'cron_hook' => $this->cron_hook,
            'cron_interval' => $this->cron_interval,
            'monitoring_enabled' => $this->settings->isEnabled(),
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'custom_interval_registered' => $this->isCustomIntervalRegistered()
        ];
    }
    
    /**
     * Check if custom cron interval is registered
     * 
     * @return bool True if custom interval is available
     */
    public function isCustomIntervalRegistered(): bool {
        $schedules = wp_get_schedules();
        return isset($schedules[$this->cron_interval]);
    }
    
    /**
     * Ensure cron job is scheduled if monitoring is enabled
     * 
     * @return bool True if cron is properly scheduled or monitoring is disabled
     */
    public function ensureScheduled(): bool {
        if (!$this->settings->isEnabled()) {
            // If monitoring is disabled, ensure cron is unscheduled
            if ($this->isScheduled()) {
                return $this->unschedule();
            }
            return true;
        }
        
        // If monitoring is enabled, ensure cron is scheduled
        if (!$this->isScheduled()) {
            return $this->schedule();
        }
        
        return true;
    }
    
    /**
     * Execute the cron job (this method will be called by WordPress cron)
     *
     * This is a placeholder method. The actual monitoring logic should be
     * handled by setting a callback using setCronCallback().
     *
     * @return void
     */
    public function executeCronJob(): void {
        // Update last check time
        $this->settings->set('last_check', current_time('timestamp'));

        // Log cron execution
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WooCommerce Order Monitor] Cron job executed at ' . current_time('mysql'));
        }
    }
    
    /**
     * Test cron functionality
     * 
     * @return array Test results
     */
    public function testCronFunctionality(): array {
        try {
            $status = $this->getStatus();
            $issues = [];
            $warnings = [];
            
            // Check if WP-Cron is disabled
            if ($status['wp_cron_disabled']) {
                $warnings[] = 'WP-Cron is disabled (DISABLE_WP_CRON = true)';
            }
            
            // Check if custom interval is registered
            if (!$status['custom_interval_registered']) {
                $issues[] = 'Custom cron interval not registered';
            }
            
            // Check if monitoring is enabled but cron not scheduled
            if ($status['monitoring_enabled'] && !$status['is_scheduled']) {
                $issues[] = 'Monitoring enabled but cron job not scheduled';
            }
            
            // Check if monitoring is disabled but cron is scheduled
            if (!$status['monitoring_enabled'] && $status['is_scheduled']) {
                $warnings[] = 'Monitoring disabled but cron job still scheduled';
            }
            
            // Check if next run time is reasonable
            if ($status['is_scheduled'] && $status['time_until_next_run']) {
                if ($status['time_until_next_run'] > 1800) { // More than 30 minutes
                    $warnings[] = 'Next cron run is more than 30 minutes away';
                }
            }
            
            // Determine overall status
            if (!empty($issues)) {
                $test_status = 'error';
                $message = 'Cron functionality has issues';
            } elseif (!empty($warnings)) {
                $test_status = 'warning';
                $message = 'Cron functionality working with warnings';
            } else {
                $test_status = 'pass';
                $message = 'Cron functionality working correctly';
            }
            
            return [
                'status' => $test_status,
                'message' => $message,
                'details' => array_merge($status, [
                    'issues' => $issues,
                    'warnings' => $warnings
                ])
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cron test failed',
                'details' => [
                    'error' => $e->getMessage()
                ]
            ];
        }
    }
    
    /**
     * Force run the cron job immediately
     * 
     * @return bool True if executed successfully
     */
    public function forceRun(): bool {
        try {
            // Execute the cron job action
            do_action($this->cron_hook);
            
            return true;
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in force run: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get cron hook name
     * 
     * @return string Cron hook name
     */
    public function getCronHook(): string {
        return $this->cron_hook;
    }
    
    /**
     * Get cron interval name
     * 
     * @return string Cron interval name
     */
    public function getCronInterval(): string {
        return $this->cron_interval;
    }
    
    /**
     * Set a callback for cron execution
     * 
     * @param callable $callback Callback function to execute
     * @return void
     */
    public function setCronCallback(callable $callback): void {
        remove_action($this->cron_hook, [$this, 'executeCronJob']);
        add_action($this->cron_hook, $callback);
    }
}

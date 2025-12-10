<?php
/**
 * Action Scheduler Integration
 *
 * @package KissPlugins\WooOrderMonitor
 * @since 1.7.0
 */

namespace KissPlugins\WooOrderMonitor\Integration;

use KissPlugins\WooOrderMonitor\Core\Settings;
use KissPlugins\WooOrderMonitor\Monitoring\OrderMonitor;

/**
 * Action Scheduler Integration Class
 * 
 * Provides integration with WooCommerce Action Scheduler for reliable
 * background task execution as an alternative to WP-Cron.
 * 
 * @since 1.7.0
 */
class ActionScheduler {
    
    /**
     * Settings manager
     *
     * @var Settings
     */
    private $settings;
    
    /**
     * Order monitor instance
     *
     * @var OrderMonitor
     */
    private $order_monitor;
    
    /**
     * Action hook name for scheduled checks
     *
     * @var string
     */
    private const ACTION_HOOK = 'woom_as_check_orders';
    
    /**
     * Action group name
     *
     * @var string
     */
    private const ACTION_GROUP = 'woo-order-monitor';
    
    /**
     * Check interval in seconds (15 minutes)
     *
     * @var int
     */
    private const CHECK_INTERVAL = 900;
    
    /**
     * Constructor
     *
     * @param Settings $settings Settings manager instance
     * @param OrderMonitor $order_monitor Order monitor instance
     */
    public function __construct(Settings $settings, OrderMonitor $order_monitor) {
        $this->settings = $settings;
        $this->order_monitor = $order_monitor;
    }
    
    /**
     * Initialize Action Scheduler integration
     *
     * Sets up WordPress hooks for Action Scheduler.
     *
     * @return void
     */
    public function initializeHooks(): void {
        \add_action('init', [$this, 'scheduleMonitoring']);
        \add_action(self::ACTION_HOOK, [$this, 'runCheck']);
    }
    
    /**
     * Schedule monitoring with Action Scheduler
     *
     * Schedules or unschedules the recurring action based on
     * whether monitoring is enabled.
     *
     * @return void
     */
    public function scheduleMonitoring(): void {
        $enabled = $this->settings->get('enabled');
        
        if ('yes' === $enabled && !\as_next_scheduled_action(self::ACTION_HOOK)) {
            // Schedule recurring action if not already scheduled
            \as_schedule_recurring_action(
                \time(),
                self::CHECK_INTERVAL,
                self::ACTION_HOOK,
                [],
                self::ACTION_GROUP
            );
            
            if (defined('WP_DEBUG') && \WP_DEBUG) {
                \error_log('[WooCommerce Order Monitor] Action Scheduler: Scheduled recurring action');
            }
        } elseif ('yes' !== $enabled) {
            // Unschedule all actions if monitoring is disabled
            \as_unschedule_all_actions(self::ACTION_HOOK, [], self::ACTION_GROUP);
            
            if (defined('WP_DEBUG') && \WP_DEBUG) {
                \error_log('[WooCommerce Order Monitor] Action Scheduler: Unscheduled all actions');
            }
        }
    }
    
    /**
     * Run the order threshold check
     *
     * This is the callback executed by Action Scheduler.
     *
     * @return void
     */
    public function runCheck(): void {
        try {
            $result = $this->order_monitor->checkThreshold();
            
            if (defined('WP_DEBUG') && \WP_DEBUG) {
                \error_log(sprintf(
                    '[WooCommerce Order Monitor] Action Scheduler check complete - Status: %s',
                    $result['status'] ?? 'unknown'
                ));
            }
        } catch (\Exception $e) {
            \error_log('[WooCommerce Order Monitor] Action Scheduler check failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if Action Scheduler is available
     *
     * @return bool True if Action Scheduler is available
     */
    public static function isAvailable(): bool {
        return \function_exists('as_schedule_recurring_action');
    }
    
    /**
     * Get next scheduled action time
     *
     * @return int|null Timestamp of next scheduled action, or null if not scheduled
     */
    public function getNextScheduledTime(): ?int {
        $next = \as_next_scheduled_action(self::ACTION_HOOK);
        return $next ? (int) $next : null;
    }
    
    /**
     * Manually trigger a check
     *
     * Useful for testing or manual execution.
     *
     * @return void
     */
    public function triggerCheck(): void {
        \as_enqueue_async_action(self::ACTION_HOOK, [], self::ACTION_GROUP);
    }
}


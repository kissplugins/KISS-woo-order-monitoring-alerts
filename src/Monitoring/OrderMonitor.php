<?php
/**
 * Order Monitor Class
 * 
 * Main monitoring class that coordinates order checking,
 * threshold validation, and alert sending.
 * 
 * @package KissPlugins\WooOrderMonitor\Monitoring
 * @since 1.5.0
 */

namespace KissPlugins\WooOrderMonitor\Monitoring;

use KissPlugins\WooOrderMonitor\Core\Settings;
use KissPlugins\WooOrderMonitor\Monitoring\Query\QueryInterface;
use KissPlugins\WooOrderMonitor\Monitoring\Query\OrderQuery;
use KissPlugins\WooOrderMonitor\Monitoring\Query\OptimizedQuery;
use KissPlugins\WooOrderMonitor\Notifications\EmailNotifier;

/**
 * Order Monitor Class
 * 
 * Coordinates the entire monitoring process including order counting,
 * threshold checking, and alert notifications.
 */
class OrderMonitor {
    
    /**
     * Settings manager
     * 
     * @var Settings
     */
    private $settings;
    
    /**
     * Threshold checker
     * 
     * @var ThresholdChecker
     */
    private $threshold_checker;
    
    /**
     * Cron scheduler
     * 
     * @var CronScheduler
     */
    private $cron_scheduler;
    
    /**
     * Email notifier
     * 
     * @var EmailNotifier
     */
    private $email_notifier;
    
    /**
     * Query implementation
     * 
     * @var QueryInterface
     */
    private $query;
    
    /**
     * Monitoring time period in minutes
     * 
     * @var int
     */
    private $monitoring_period = 15;
    
    /**
     * Constructor
     * 
     * @param Settings $settings Settings manager instance
     */
    public function __construct(Settings $settings) {
        $this->settings = $settings;
        $this->threshold_checker = new ThresholdChecker($settings);
        $this->cron_scheduler = new CronScheduler($settings);
        
        // Initialize query implementation (will be set in initializeComponents)
        $this->initializeComponents();
    }
    
    /**
     * Initialize monitoring components
     * 
     * @return void
     */
    private function initializeComponents(): void {
        // Choose query implementation based on availability and performance
        $optimized_query = new OptimizedQuery();
        $standard_query = new OrderQuery();
        
        if ($optimized_query->isAvailable()) {
            $this->query = $optimized_query;
        } else {
            $this->query = $standard_query;
        }
        
        // Initialize email notifier (will be created in Notifications phase)
        // For now, we'll handle email sending directly
    }
    
    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    public function initializeHooks(): void {
        // Initialize cron scheduler hooks
        $this->cron_scheduler->initializeHooks();

        // Set this class as the cron callback
        $this->cron_scheduler->setCronCallback([$this, 'checkOrderThreshold']);

        // Initialize Rolling Average Detection hooks (v1.6.0)
        if ($this->settings->get('rolling_enabled', 'no') === 'yes') {
            add_action('woocommerce_order_status_changed', [$this, 'onOrderStatusChanged'], 10, 4);
        }
    }
    
    /**
     * Main monitoring method - checks order threshold
     * 
     * This is the core method that gets called by the cron job.
     * 
     * @return array Check result with status and details
     */
    public function checkOrderThreshold(): array {
        try {
            // Update last check time
            $this->settings->set('last_check', current_time('timestamp'));
            
            // Get order count for monitoring period
            $order_count = $this->query->getOrderCount($this->monitoring_period);
            
            // Handle query errors
            if ($order_count === false || $order_count === null) {
                error_log('[WooCommerce Order Monitor] Skipping threshold check due to query error');
                return [
                    'status' => 'error',
                    'message' => 'Order count query failed',
                    'details' => ['query_type' => $this->query->getName()]
                ];
            }
            
            // Check threshold
            $threshold_result = $this->threshold_checker->checkThreshold($order_count, $this->monitoring_period);
            
            if ($threshold_result['status'] !== 'success') {
                error_log('[WooCommerce Order Monitor] Threshold check failed: ' . $threshold_result['message']);
                return $threshold_result;
            }
            
            // Send alert if below threshold
            if ($threshold_result['below_threshold']) {
                $alert_result = $this->sendAlert($threshold_result['details']);
                
                if (!$alert_result['success']) {
                    error_log('[WooCommerce Order Monitor] Failed to send alert: ' . $alert_result['message']);
                }
                
                // Add alert info to result
                $threshold_result['alert_sent'] = $alert_result['success'];
                $threshold_result['alert_message'] = $alert_result['message'];
            }
            
            // Log successful check
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[WooCommerce Order Monitor] Check complete - Orders: %d, Threshold: %d, Peak: %s, Alert: %s',
                    $order_count,
                    $threshold_result['details']['threshold'],
                    $threshold_result['details']['is_peak'] ? 'Yes' : 'No',
                    $threshold_result['below_threshold'] ? 'Sent' : 'Not needed'
                ));
            }
            
            return $threshold_result;
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in checkOrderThreshold: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Monitoring check failed',
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }
    
    /**
     * Send alert notification
     * 
     * @param array $threshold_details Threshold check details
     * @return array Alert result with success status and message
     */
    private function sendAlert(array $threshold_details): array {
        try {
            // Update last alert time
            $this->settings->set('last_alert', current_time('timestamp'));
            
            // Get notification emails
            $notification_emails = $this->settings->getNotificationEmails();
            
            if (empty($notification_emails)) {
                return [
                    'success' => false,
                    'message' => 'No notification emails configured'
                ];
            }
            
            // Prepare email data
            $subject = __('[Alert] WooCommerce Orders Below Threshold', 'woo-order-monitor');
            
            // Calculate time period for email
            $end_time = current_time('H:i');
            $start_time = date('H:i', strtotime("-{$this->monitoring_period} minutes"));
            
            // Build email body
            $email_data = [
                'start_time' => $start_time,
                'end_time' => $end_time,
                'threshold' => $threshold_details['threshold'],
                'order_count' => $threshold_details['order_count'],
                'period_type' => $threshold_details['period_type'],
                'severity' => $threshold_details['severity'],
                'threshold_percentage' => $threshold_details['threshold_percentage'],
                'admin_url' => admin_url('edit.php?post_type=shop_order')
            ];
            
            $body = $this->buildAlertEmailBody($email_data);
            
            if (empty($body)) {
                return [
                    'success' => false,
                    'message' => 'Failed to generate email content'
                ];
            }
            
            // Send email
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent = wp_mail($notification_emails, $subject, $body, $headers);
            
            if ($sent) {
                error_log('[WooCommerce Order Monitor] Alert email sent to: ' . implode(', ', $notification_emails));
                return [
                    'success' => true,
                    'message' => 'Alert sent successfully to ' . count($notification_emails) . ' recipients'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send email via wp_mail()'
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception sending alert: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Build alert email body
     *
     * Generates a professional HTML email template for order threshold alerts
     * with severity-based styling and comprehensive system information.
     *
     * @param array $data Email data containing:
     *                    - start_time: Alert period start time (H:i format)
     *                    - end_time: Alert period end time (H:i format)
     *                    - threshold: Expected order threshold
     *                    - order_count: Actual order count
     *                    - period_type: 'peak' or 'off-peak'
     *                    - severity: Alert severity level
     *                    - threshold_percentage: Percentage of threshold achieved
     *                    - admin_url: WordPress admin URL for order management
     * @return string Fully formatted HTML email body
     */
    private function buildAlertEmailBody(array $data): string {
        $severity_colors = [
            'low' => '#ffc107',
            'medium' => '#fd7e14',
            'high' => '#dc3545',
            'critical' => '#721c24'
        ];
        
        $severity_color = $severity_colors[$data['severity']] ?? '#6c757d';
        
        ob_start();
        ?>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>WooCommerce Order Alert</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #dc3545; border-bottom: 2px solid #dc3545; padding-bottom: 10px;">
                    ⚠️ Order Volume Alert
                </h2>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: <?php echo esc_attr($severity_color); ?>;">
                        Severity: <?php echo esc_html(ucfirst($data['severity'])); ?>
                    </h3>
                    
                    <p><strong>Time Period:</strong> <?php echo esc_html($data['start_time']); ?> - <?php echo esc_html($data['end_time']); ?> (<?php echo esc_html($data['period_type']); ?>)</p>
                    <p><strong>Order Count:</strong> <?php echo esc_html($data['order_count']); ?></p>
                    <p><strong>Expected Threshold:</strong> <?php echo esc_html($data['threshold']); ?></p>
                    <p><strong>Threshold Achievement:</strong> <?php echo esc_html($data['threshold_percentage']); ?>%</p>
                </div>
                
                <div style="margin: 20px 0;">
                    <a href="<?php echo esc_url($data['admin_url']); ?>" 
                       style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block;">
                        View Orders in Admin
                    </a>
                </div>
                
                <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
                
                <p style="font-size: 12px; color: #666;">
                    This alert was generated by WooCommerce Order Monitor at <?php echo esc_html(current_time('Y-m-d H:i:s')); ?>.
                    <br>
                    To modify alert settings, visit your WooCommerce settings page.
                </p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get recent order count
     * 
     * @param int|null $minutes Number of minutes to look back
     * @return int|false Order count or false on error
     */
    public function getRecentOrderCount(?int $minutes = null): int {
        $minutes = $minutes ?? $this->monitoring_period;
        return $this->query->getOrderCount($minutes);
    }
    
    /**
     * Get order statistics
     * 
     * @param int|null $minutes Number of minutes to look back
     * @return array Order statistics
     */
    public function getOrderStats(?int $minutes = null): array {
        $minutes = $minutes ?? $this->monitoring_period;
        return $this->query->getOrderStats($minutes);
    }
    
    /**
     * Ensure cron is scheduled if monitoring is enabled
     * 
     * @return bool True if cron is properly scheduled
     */
    public function ensureCronScheduled(): bool {
        return $this->cron_scheduler->ensureScheduled();
    }
    
    /**
     * Get monitoring status
     * 
     * @return array Monitoring status information
     */
    public function getMonitoringStatus(): array {
        $cron_status = $this->cron_scheduler->getStatus();
        $threshold_status = $this->threshold_checker->getThresholdStatus();
        
        return [
            'monitoring_enabled' => $this->settings->isEnabled(),
            'query_implementation' => $this->query->getName(),
            'query_available' => $this->query->isAvailable(),
            'monitoring_period_minutes' => $this->monitoring_period,
            'cron_status' => $cron_status,
            'threshold_status' => $threshold_status,
            'last_check' => $this->settings->get('last_check'),
            'last_alert' => $this->settings->get('last_alert')
        ];
    }
    
    /**
     * Test monitoring functionality
     * 
     * @return array Test results
     */
    public function testMonitoring(): array {
        try {
            // Test order count query
            $order_count = $this->getRecentOrderCount();
            if ($order_count === false) {
                return [
                    'status' => 'error',
                    'message' => 'Order count query failed',
                    'details' => ['query_type' => $this->query->getName()]
                ];
            }
            
            // Test threshold logic
            $threshold_test = $this->threshold_checker->testThresholdLogic();
            if ($threshold_test['status'] !== 'pass') {
                return $threshold_test;
            }
            
            // Test cron functionality
            $cron_test = $this->cron_scheduler->testCronFunctionality();
            if ($cron_test['status'] === 'error') {
                return $cron_test;
            }
            
            return [
                'status' => 'pass',
                'message' => 'Monitoring system working correctly',
                'details' => [
                    'order_count' => $order_count,
                    'query_type' => $this->query->getName(),
                    'threshold_test' => $threshold_test,
                    'cron_test' => $cron_test
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Monitoring test failed',
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }
    
    /**
     * Get query implementation
     * 
     * @return QueryInterface Current query implementation
     */
    public function getQuery(): QueryInterface {
        return $this->query;
    }
    
    /**
     * Set query implementation
     * 
     * @param QueryInterface $query Query implementation
     * @return void
     */
    public function setQuery(QueryInterface $query): void {
        $this->query = $query;
    }
    
    /**
     * Get threshold checker
     * 
     * @return ThresholdChecker Threshold checker instance
     */
    public function getThresholdChecker(): ThresholdChecker {
        return $this->threshold_checker;
    }
    
    /**
     * Get cron scheduler
     *
     * @return CronScheduler Cron scheduler instance
     */
    public function getCronScheduler(): CronScheduler {
        return $this->cron_scheduler;
    }

    // ========================================================================
    // Rolling Average Detection (RAD) Methods - v1.6.0
    // ========================================================================

    /**
     * Handle order status change for Rolling Average Detection
     *
     * Invalidates the order history cache when an order status changes,
     * triggering a rebuild on next read and checking failure rate.
     *
     * @param int $order_id Order ID
     * @param string $old_status Previous order status
     * @param string $new_status New order status
     * @param \WC_Order $order Order object
     * @return void
     * @since 1.6.0
     */
    public function onOrderStatusChanged(int $order_id, string $old_status, string $new_status, $order): void {
        // Invalidate cache to trigger rebuild on next read
        delete_transient('woom_order_history_cache');

        // Check failure rate and potentially send alert
        $this->checkRollingFailureRate();
    }

    /**
     * Get order history from cache or rebuild from WooCommerce
     *
     * Uses transient cache with smart invalidation to avoid permanent
     * data redundancy while maintaining fast array-based calculations.
     *
     * @return array Order history array with structure: [{id, status, time}, ...]
     * @since 1.6.0
     */
    public function getOrderHistory(): array {
        $cached = get_transient('woom_order_history_cache');

        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        // Cache miss - rebuild from WooCommerce
        return $this->rebuildOrderHistory();
    }

    /**
     * Rebuild order history from WooCommerce orders
     *
     * Queries the last N orders from WooCommerce and caches the result.
     * This is the source of truth - no permanent redundant storage.
     *
     * @return array Order history array
     * @since 1.6.0
     */
    private function rebuildOrderHistory(): array {
        $window_size = (int) $this->settings->get('rolling_window_size', 10);
        $cache_duration = (int) $this->settings->get('rolling_cache_duration', 300);

        // Query recent orders from WooCommerce
        $orders = wc_get_orders([
            'limit' => $window_size,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['completed', 'processing', 'failed', 'cancelled', 'refunded']
        ]);

        $history = [];
        foreach ($orders as $order) {
            $order_status = $order->get_status();

            // Determine if order is success or failure
            $is_success = in_array($order_status, ['completed', 'processing'], true);

            $history[] = [
                'id' => $order->get_id(),
                'status' => $is_success ? 'success' : 'failed',
                'time' => $order->get_date_created()->getTimestamp()
            ];
        }

        // Cache the result
        set_transient('woom_order_history_cache', $history, $cache_duration);

        return $history;
    }

    /**
     * Calculate failure rate from order history
     *
     * Returns the percentage of failed orders in the rolling window.
     * Returns null if insufficient data for meaningful calculation.
     *
     * @return float|null Failure rate percentage (0-100) or null if insufficient data
     * @since 1.6.0
     */
    public function calculateFailureRate(): ?float {
        $history = $this->getOrderHistory();
        $min_orders = (int) $this->settings->get('rolling_min_orders', 3);

        // Not enough data for meaningful calculation
        if (count($history) < $min_orders) {
            return null;
        }

        $failed_count = 0;
        foreach ($history as $order_data) {
            if ($order_data['status'] === 'failed') {
                $failed_count++;
            }
        }

        return ($failed_count / count($history)) * 100;
    }

    /**
     * Check rolling failure rate and send alert if threshold exceeded
     *
     * This is called after each order status change to check if the
     * failure rate has exceeded the configured threshold.
     *
     * @return array Check result with status and details
     * @since 1.6.0
     */
    public function checkRollingFailureRate(): array {
        try {
            // Check if RAD is enabled
            if ($this->settings->get('rolling_enabled', 'no') !== 'yes') {
                return [
                    'status' => 'disabled',
                    'message' => 'Rolling Average Detection is disabled'
                ];
            }

            $failure_rate = $this->calculateFailureRate();

            // Insufficient data
            if ($failure_rate === null) {
                return [
                    'status' => 'insufficient_data',
                    'message' => 'Not enough orders for failure rate calculation',
                    'details' => [
                        'order_count' => count($this->getOrderHistory()),
                        'min_required' => $this->settings->get('rolling_min_orders', 3)
                    ]
                ];
            }

            $threshold = (float) $this->settings->get('rolling_failure_threshold', 70);
            $history = $this->getOrderHistory();

            // Check if failure rate exceeds threshold
            if ($failure_rate >= $threshold) {
                // Check cooldown to prevent alert spam
                $last_alert = (int) $this->settings->get('last_alert', 0);
                $cooldown = (int) $this->settings->get('alert_cooldown', 7200);
                $time_since_alert = time() - $last_alert;

                if ($time_since_alert < $cooldown) {
                    return [
                        'status' => 'cooldown',
                        'message' => 'Alert suppressed due to cooldown period',
                        'details' => [
                            'failure_rate' => round($failure_rate, 2),
                            'threshold' => $threshold,
                            'cooldown_remaining' => $cooldown - $time_since_alert
                        ]
                    ];
                }

                // Send RAD alert
                $alert_result = $this->sendRollingAverageAlert($failure_rate, $threshold, $history);

                return [
                    'status' => 'alert_sent',
                    'message' => 'Failure rate alert sent',
                    'details' => [
                        'failure_rate' => round($failure_rate, 2),
                        'threshold' => $threshold,
                        'order_count' => count($history),
                        'alert_result' => $alert_result
                    ]
                ];
            }

            // All good - failure rate below threshold
            return [
                'status' => 'ok',
                'message' => 'Failure rate within acceptable range',
                'details' => [
                    'failure_rate' => round($failure_rate, 2),
                    'threshold' => $threshold,
                    'order_count' => count($history)
                ]
            ];

        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] RAD check failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Rolling average check failed',
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Send Rolling Average Detection alert email
     *
     * Sends a specialized email alert for RAD-based detection,
     * different from time-based threshold alerts.
     *
     * @param float $failure_rate Current failure rate percentage
     * @param float $threshold Configured threshold percentage
     * @param array $history Order history data
     * @return array Alert result with success status and message
     * @since 1.6.0
     */
    private function sendRollingAverageAlert(float $failure_rate, float $threshold, array $history): array {
        try {
            // Update last alert time
            $this->settings->set('last_alert', current_time('timestamp'));

            // Get notification emails
            $notification_emails = $this->settings->getNotificationEmails();

            if (empty($notification_emails)) {
                return [
                    'success' => false,
                    'message' => 'No notification emails configured'
                ];
            }

            // Count failed orders
            $failed_count = 0;
            foreach ($history as $order_data) {
                if ($order_data['status'] === 'failed') {
                    $failed_count++;
                }
            }

            // Prepare email data
            $subject = __('[Alert] High Order Failure Rate Detected', 'woo-order-monitor');

            $email_data = [
                'failure_rate' => round($failure_rate, 2),
                'threshold' => $threshold,
                'total_orders' => count($history),
                'failed_orders' => $failed_count,
                'success_orders' => count($history) - $failed_count,
                'window_size' => $this->settings->get('rolling_window_size', 10),
                'admin_url' => admin_url('edit.php?post_type=shop_order&post_status=wc-failed')
            ];

            $body = $this->buildRollingAverageAlertEmail($email_data);

            if (empty($body)) {
                return [
                    'success' => false,
                    'message' => 'Failed to generate email content'
                ];
            }

            // Send email
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent = wp_mail($notification_emails, $subject, $body, $headers);

            if ($sent) {
                error_log(sprintf(
                    '[WooCommerce Order Monitor] RAD alert sent - Failure rate: %.2f%% (threshold: %.2f%%)',
                    $failure_rate,
                    $threshold
                ));
                return [
                    'success' => true,
                    'message' => 'RAD alert sent successfully to ' . count($notification_emails) . ' recipients'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send email via wp_mail()'
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception sending RAD alert: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build Rolling Average Detection alert email body
     *
     * Generates HTML email template specifically for RAD alerts,
     * different from time-based threshold alerts.
     *
     * @param array $data Email data
     * @return string HTML email body
     * @since 1.6.0
     */
    private function buildRollingAverageAlertEmail(array $data): string {
        ob_start();
        ?>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>WooCommerce Order Failure Alert</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #dc3545; border-bottom: 2px solid #dc3545; padding-bottom: 10px;">
                    ⚠️ High Order Failure Rate Detected
                </h2>

                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
                    <p style="margin: 0; font-weight: bold;">
                        Rolling Average Detection has identified an unusually high failure rate in recent orders.
                    </p>
                </div>

                <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #dc3545;">Failure Rate Analysis</h3>

                    <p><strong>Current Failure Rate:</strong> <?php echo esc_html($data['failure_rate']); ?>%</p>
                    <p><strong>Alert Threshold:</strong> <?php echo esc_html($data['threshold']); ?>%</p>
                    <p><strong>Orders Analyzed:</strong> Last <?php echo esc_html($data['total_orders']); ?> orders</p>
                    <p><strong>Failed Orders:</strong> <?php echo esc_html($data['failed_orders']); ?> of <?php echo esc_html($data['total_orders']); ?></p>
                    <p><strong>Successful Orders:</strong> <?php echo esc_html($data['success_orders']); ?> of <?php echo esc_html($data['total_orders']); ?></p>
                </div>

                <div style="background: #e7f3ff; border-left: 4px solid #007cba; padding: 15px; margin: 20px 0;">
                    <h4 style="margin-top: 0;">What This Means</h4>
                    <p style="margin-bottom: 0;">
                        This alert is based on the <strong>percentage of failed orders</strong>, not time-based thresholds.
                        It works for both high-volume and low-volume stores by tracking order success/failure patterns.
                    </p>
                </div>

                <div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin: 20px 0;">
                    <h4 style="margin-top: 0;">Possible Causes</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li>Payment gateway issues</li>
                        <li>Inventory/stock problems</li>
                        <li>Checkout errors or bugs</li>
                        <li>Shipping calculation failures</li>
                        <li>Plugin conflicts</li>
                    </ul>
                </div>

                <div style="margin: 20px 0;">
                    <a href="<?php echo esc_url($data['admin_url']); ?>"
                       style="background: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 3px; display: inline-block; font-weight: bold;">
                        View Failed Orders
                    </a>
                </div>

                <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">

                <p style="font-size: 12px; color: #666;">
                    This alert was generated by WooCommerce Order Monitor (Rolling Average Detection) at <?php echo esc_html(current_time('Y-m-d H:i:s')); ?>.
                    <br>
                    Detection Method: Rolling Average (last <?php echo esc_html($data['window_size']); ?> orders)
                    <br>
                    To modify RAD settings, visit your WooCommerce Order Monitor settings page.
                </p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

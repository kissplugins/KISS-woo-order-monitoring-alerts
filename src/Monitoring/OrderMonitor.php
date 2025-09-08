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
}

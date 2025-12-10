<?php
/**
 * Email Notification Handler
 *
 * @package KissPlugins\WooOrderMonitor
 * @since 1.0.0
 */

namespace KissPlugins\WooOrderMonitor\Notifications;

use KissPlugins\WooOrderMonitor\Core\Settings;

/**
 * Class EmailNotification
 *
 * Handles all email notification functionality for order monitoring alerts.
 * Manages email sending, template rendering, and notification tracking.
 */
class EmailNotification {
    
    /**
     * Settings instance
     *
     * @var Settings
     */
    private $settings;
    
    /**
     * Template renderer
     *
     * @var NotificationTemplate
     */
    private $template;
    
    /**
     * Constructor
     *
     * @param Settings $settings Settings instance
     */
    public function __construct(Settings $settings) {
        $this->settings = $settings;
        $this->template = new NotificationTemplate();
    }
    
    /**
     * Send alert notification
     *
     * @param int $order_count Current order count
     * @param int $threshold Threshold value
     * @param bool $is_peak Whether it's peak hours
     * @return bool True if sent successfully
     */
    public function sendAlert(int $order_count, int $threshold, bool $is_peak): bool {
        try {
            // Update last alert time
            \update_option('woom_last_alert', \current_time('timestamp'));
            
            // Get email recipients
            $to = $this->getNotificationEmails();
            
            // Validate email addresses
            if (empty($to) || !is_array($to)) {
                \error_log('[WooCommerce Order Monitor] No valid email addresses configured for alerts');
                return false;
            }
            
            $subject = \__('[Alert] WooCommerce Orders Below Threshold', 'woo-order-monitor');
            
            // Calculate time period
            $end_time = \current_time('H:i');
            $start_time = date('H:i', strtotime('-15 minutes'));
            
            // Build email body using template
            $body = $this->template->renderAlertEmail([
                'start_time' => $start_time,
                'end_time' => $end_time,
                'threshold' => $threshold,
                'order_count' => $order_count,
                'period_type' => $is_peak ? \__('Peak Hours', 'woo-order-monitor') : \__('Off-Peak Hours', 'woo-order-monitor'),
                'admin_url' => \admin_url('edit.php?post_type=shop_order')
            ]);
            
            // Validate email body
            if (empty($body)) {
                \error_log('[WooCommerce Order Monitor] Failed to generate email body');
                return false;
            }
            
            // Set HTML headers
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            
            // Send email
            $sent = \wp_mail($to, $subject, $body, $headers);
            
            // Log result
            if (!$sent) {
                \error_log('[WooCommerce Order Monitor] Failed to send alert email to: ' . implode(', ', $to));
            } else {
                \error_log('[WooCommerce Order Monitor] Alert email sent successfully to: ' . implode(', ', $to));
            }
            
            return $sent;
            
        } catch (\Exception $e) {
            \error_log('[WooCommerce Order Monitor] Exception in sendAlert: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send throttled alert with enhanced tracking
     *
     * @param int $order_count Current order count
     * @param int $threshold Threshold value
     * @param bool $is_peak Whether it's peak hours
     * @param string $alert_type Alert type (normal, first_today, escalated)
     * @return bool True if sent successfully
     */
    public function sendThrottledAlert(int $order_count, int $threshold, bool $is_peak, string $alert_type): bool {
        try {
            $current_time = \current_time('timestamp');

            // Update alert counters before sending
            $this->incrementAlertCounters($is_peak, $current_time);

            // Get email recipients
            $to = $this->getNotificationEmails();

            if (empty($to) || !is_array($to)) {
                \error_log('[WooCommerce Order Monitor] No valid email addresses configured for alerts');
                return false;
            }

            $subject = $this->buildAlertSubject($alert_type, $is_peak);
            $body = $this->template->renderEnhancedAlertEmail([
                'start_time' => date('H:i', strtotime('-15 minutes')),
                'end_time' => \current_time('H:i'),
                'threshold' => $threshold,
                'order_count' => $order_count,
                'period_type' => $is_peak ? \__('Peak Hours', 'woo-order-monitor') : \__('Off-Peak Hours', 'woo-order-monitor'),
                'admin_url' => \admin_url('edit.php?post_type=shop_order'),
                'alert_type' => $alert_type,
                'daily_count' => $this->settings->get('daily_alert_count'),
                'max_daily' => $this->settings->get('max_daily_alerts'),
                'cooldown_hours' => round($this->settings->get('alert_cooldown') / 3600, 1)
            ]);

            if (empty($body)) {
                \error_log('[WooCommerce Order Monitor] Failed to generate email body');
                return false;
            }

            $headers = ['Content-Type: text/html; charset=UTF-8'];

            // Send email
            $sent = \wp_mail($to, $subject, $body, $headers);

            // Log result
            if ($sent) {
                \error_log(sprintf(
                    '[WooCommerce Order Monitor] Alert sent successfully to: %s (Type: %s, Daily: %d/%d)',
                    implode(', ', $to),
                    $alert_type,
                    $this->settings->get('daily_alert_count'),
                    $this->settings->get('max_daily_alerts')
                ));
            }

            return $sent;

        } catch (\Exception $e) {
            \error_log('[WooCommerce Order Monitor] Exception in sendThrottledAlert: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send test notification
     *
     * @return array Result array with success/error message
     */
    public function sendTestNotification(): array {
        try {
            $to = $this->getNotificationEmails();

            if (empty($to)) {
                return [
                    'success' => false,
                    'message' => \__('No valid email addresses configured', 'woo-order-monitor')
                ];
            }

            $subject = \__('[Test] WooCommerce Order Monitor', 'woo-order-monitor');
            $body = $this->template->renderTestEmail();
            $headers = ['Content-Type: text/html; charset=UTF-8'];

            $sent = \wp_mail($to, $subject, $body, $headers);

            if ($sent) {
                return [
                    'success' => true,
                    'message' => sprintf(
                        \__('Test notification sent successfully to: %s', 'woo-order-monitor'),
                        implode(', ', $to)
                    )
                ];
            } else {
                return [
                    'success' => false,
                    'message' => \__('Failed to send test notification. Please check your email configuration.', 'woo-order-monitor')
                ];
            }

        } catch (\Exception $e) {
            \error_log('[WooCommerce Order Monitor] Exception in sendTestNotification: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => sprintf(\__('An unexpected error occurred: %s', 'woo-order-monitor'), $e->getMessage())
            ];
        }
    }

    /**
     * Build alert subject line based on type
     *
     * @param string $alert_type Alert type
     * @param bool $is_peak Whether it's peak hours
     * @return string Email subject
     */
    private function buildAlertSubject(string $alert_type, bool $is_peak): string {
        $period = $is_peak ? \__('Peak', 'woo-order-monitor') : \__('Off-Peak', 'woo-order-monitor');

        switch ($alert_type) {
            case 'first_today':
                return sprintf(\__('[Alert] First %s Order Threshold Alert Today', 'woo-order-monitor'), $period);
            case 'escalated':
                return sprintf(\__('[URGENT] Repeated %s Order Threshold Alert', 'woo-order-monitor'), $period);
            default:
                return sprintf(\__('[Alert] %s Orders Below Threshold', 'woo-order-monitor'), $period);
        }
    }

    /**
     * Increment alert counters and tracking
     *
     * @param bool $is_peak Whether it's peak hours
     * @param int $current_time Current timestamp
     * @return void
     */
    private function incrementAlertCounters(bool $is_peak, int $current_time): void {
        // Update general last alert time
        \update_option('woom_last_alert', $current_time);

        // Update specific threshold type
        $last_alert_key = $is_peak ? 'woom_last_alert_peak' : 'woom_last_alert_offpeak';
        \update_option($last_alert_key, $current_time);

        // Increment daily counter
        $new_count = $this->settings->get('daily_alert_count') + 1;
        \update_option('woom_daily_alert_count', $new_count);

        // Update settings cache
        $this->settings->set('last_alert', $current_time);
        $this->settings->set($is_peak ? 'last_alert_peak' : 'last_alert_offpeak', $current_time);
        $this->settings->set('daily_alert_count', $new_count);
    }

    /**
     * Get notification email addresses
     *
     * @return array Array of email addresses
     */
    private function getNotificationEmails(): array {
        $emails = $this->settings->get('notification_emails');

        // Convert comma-separated string to array
        if (is_string($emails)) {
            $emails = array_map('trim', explode(',', $emails));
            $emails = array_filter($emails, '\is_email');
        }

        // Fallback to admin email if no valid emails
        if (empty($emails)) {
            $emails = [\get_option('admin_email')];
        }

        return $emails;
    }
}


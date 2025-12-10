<?php
/**
 * Notification Template Renderer
 *
 * @package KissPlugins\WooOrderMonitor
 * @since 1.0.0
 */

namespace KissPlugins\WooOrderMonitor\Notifications;

/**
 * Class NotificationTemplate
 *
 * Renders HTML email templates for various notification types.
 */
class NotificationTemplate {
    
    /**
     * Render alert email template
     *
     * @param array $data Template data
     * @return string HTML email body
     */
    public function renderAlertEmail(array $data): string {
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
                            <td><?php echo \esc_html__('Time Period:', 'woo-order-monitor'); ?></td>
                            <td><?php echo \esc_html($data['start_time']); ?> to <?php echo \esc_html($data['end_time']); ?></td>
                        </tr>
                        <tr>
                            <td><?php echo \esc_html__('Period Type:', 'woo-order-monitor'); ?></td>
                            <td><?php echo \esc_html($data['period_type']); ?></td>
                        </tr>
                        <tr>
                            <td><?php echo \esc_html__('Expected Orders:', 'woo-order-monitor'); ?></td>
                            <td><?php echo \esc_html($data['threshold']); ?></td>
                        </tr>
                        <tr>
                            <td><?php echo \esc_html__('Actual Orders:', 'woo-order-monitor'); ?></td>
                            <td><strong style="color: #dc3545;"><?php echo \esc_html($data['order_count']); ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php echo \esc_html__('Shortfall:', 'woo-order-monitor'); ?></td>
                            <td><strong style="color: #dc3545;"><?php echo \esc_html($data['threshold'] - $data['order_count']); ?> orders</strong></td>
                        </tr>
                    </table>
                    
                    <div class="warning-list">
                        <h3 style="margin-top: 0;"><?php echo \esc_html__('Recommended Actions:', 'woo-order-monitor'); ?></h3>
                        <ul>
                            <li><?php echo \esc_html__('Check if there are any technical issues with your store', 'woo-order-monitor'); ?></li>
                            <li><?php echo \esc_html__('Verify payment gateways are functioning correctly', 'woo-order-monitor'); ?></li>
                            <li><?php echo \esc_html__('Review recent marketing campaigns or promotions', 'woo-order-monitor'); ?></li>
                            <li><?php echo \esc_html__('Check for any unusual traffic patterns', 'woo-order-monitor'); ?></li>
                        </ul>
                    </div>
                    
                    <a href="<?php echo \esc_url($data['admin_url']); ?>" class="action-button">
                        <?php echo \esc_html__('View Orders in Dashboard', 'woo-order-monitor'); ?>
                    </a>
                    
                    <p style="margin-top: 30px; font-size: 12px; color: #666;">
                        <?php echo \esc_html__('This is an automated notification from WooCommerce Order Monitor.', 'woo-order-monitor'); ?>
                        <br>
                        <?php echo \esc_html__('Site:', 'woo-order-monitor'); ?> <?php echo \esc_html(\home_url()); ?>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render enhanced alert email template with throttling info
     *
     * @param array $data Template data
     * @return string HTML email body
     */
    public function renderEnhancedAlertEmail(array $data): string {
        $alert_type = $data['alert_type'] ?? 'normal';
        $daily_count = $data['daily_count'] ?? 0;
        $max_daily = $data['max_daily'] ?? 10;
        $cooldown_hours = $data['cooldown_hours'] ?? 2;

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .alert-header { background-color: <?php echo $alert_type === 'escalated' ? '#dc3545' : '#ff6b6b'; ?>; color: white; padding: 15px; border-radius: 5px 5px 0 0; }
                .alert-body { background-color: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-top: none; }
                .details-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .details-table td { padding: 10px; border-bottom: 1px solid #dee2e6; }
                .details-table td:first-child { font-weight: bold; width: 40%; }
                .action-button { display: inline-block; padding: 10px 20px; background-color: #007cba; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .warning-list { background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .info-box { background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .escalated-warning { background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px 0; border-radius: 5px; color: #721c24; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="alert-header">
                    <h2 style="margin: 0;">
                        <?php if ($alert_type === 'escalated'): ?>
                            🚨 <?php echo \esc_html__('URGENT: Repeated Order Alert', 'woo-order-monitor'); ?>
                        <?php elseif ($alert_type === 'first_today'): ?>
                            ⚠️ <?php echo \esc_html__('First Order Alert Today', 'woo-order-monitor'); ?>
                        <?php else: ?>
                            ⚠️ <?php echo \esc_html__('WooCommerce Order Alert', 'woo-order-monitor'); ?>
                        <?php endif; ?>
                    </h2>
                </div>
                <div class="alert-body">
                    <?php if ($alert_type === 'escalated'): ?>
                        <div class="escalated-warning">
                            <strong><?php echo \esc_html__('⚠️ This is an escalated alert!', 'woo-order-monitor'); ?></strong>
                            <p><?php echo \esc_html__('You have received multiple alerts today. This indicates a persistent issue that requires immediate attention.', 'woo-order-monitor'); ?></p>
                        </div>
                    <?php endif; ?>

                    <p><strong><?php echo \esc_html__('Alert:', 'woo-order-monitor'); ?></strong> <?php echo \esc_html__('Order volume has fallen below the configured threshold.', 'woo-order-monitor'); ?></p>

                    <table class="details-table">
                        <tr>
                            <td><?php echo \esc_html__('Time Period:', 'woo-order-monitor'); ?></td>
                            <td><?php echo \esc_html($data['start_time']); ?> to <?php echo \esc_html($data['end_time']); ?></td>
                        </tr>
                        <tr>
                            <td><?php echo \esc_html__('Period Type:', 'woo-order-monitor'); ?></td>
                            <td><?php echo \esc_html($data['period_type']); ?></td>
                        </tr>
                        <tr>
                            <td><?php echo \esc_html__('Expected Orders:', 'woo-order-monitor'); ?></td>
                            <td><?php echo \esc_html($data['threshold']); ?></td>
                        </tr>
                        <tr>
                            <td><?php echo \esc_html__('Actual Orders:', 'woo-order-monitor'); ?></td>
                            <td><strong style="color: #dc3545;"><?php echo \esc_html($data['order_count']); ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php echo \esc_html__('Shortfall:', 'woo-order-monitor'); ?></td>
                            <td><strong style="color: #dc3545;"><?php echo \esc_html($data['threshold'] - $data['order_count']); ?> <?php echo \esc_html__('orders', 'woo-order-monitor'); ?></strong></td>
                        </tr>
                    </table>

                    <div class="info-box">
                        <strong><?php echo \esc_html__('Alert Tracking:', 'woo-order-monitor'); ?></strong>
                        <p style="margin: 5px 0;">
                            <?php echo \esc_html__('Alerts today:', 'woo-order-monitor'); ?> <strong><?php echo \esc_html($daily_count); ?> / <?php echo \esc_html($max_daily); ?></strong><br>
                            <?php echo \esc_html__('Cooldown period:', 'woo-order-monitor'); ?> <strong><?php echo \esc_html($cooldown_hours); ?> <?php echo \esc_html__('hours', 'woo-order-monitor'); ?></strong>
                        </p>
                    </div>

                    <div class="warning-list">
                        <h3 style="margin-top: 0;"><?php echo \esc_html__('Recommended Actions:', 'woo-order-monitor'); ?></h3>
                        <ul>
                            <li><?php echo \esc_html__('Check if there are any technical issues with your store', 'woo-order-monitor'); ?></li>
                            <li><?php echo \esc_html__('Verify payment gateways are functioning correctly', 'woo-order-monitor'); ?></li>
                            <li><?php echo \esc_html__('Review recent marketing campaigns or promotions', 'woo-order-monitor'); ?></li>
                            <li><?php echo \esc_html__('Check for any unusual traffic patterns', 'woo-order-monitor'); ?></li>
                        </ul>
                    </div>

                    <a href="<?php echo \esc_url($data['admin_url']); ?>" class="action-button">
                        <?php echo \esc_html__('View Orders in Dashboard', 'woo-order-monitor'); ?>
                    </a>

                    <p style="margin-top: 30px; font-size: 12px; color: #666;">
                        <?php echo \esc_html__('This is an automated notification from WooCommerce Order Monitor.', 'woo-order-monitor'); ?>
                        <br>
                        <?php echo \esc_html__('Site:', 'woo-order-monitor'); ?> <?php echo \esc_html(\home_url()); ?>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Render test email template
     *
     * @param array $data Template data
     * @return string HTML email body
     */
    public function renderTestEmail(array $data = []): string {
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
            </style>
        </head>
        <body>
            <div class="container">
                <div class="test-header">
                    <h2 style="margin: 0;">✅ Test Notification</h2>
                </div>
                <div class="test-body">
                    <p><?php echo \esc_html__('This is a test notification from WooCommerce Order Monitor.', 'woo-order-monitor'); ?></p>
                    <p><?php echo \esc_html__('If you received this email, your notification system is configured correctly.', 'woo-order-monitor'); ?></p>
                    <p style="margin-top: 30px; font-size: 12px; color: #666;">
                        <?php echo \esc_html__('Sent at:', 'woo-order-monitor'); ?> <?php echo \esc_html(\current_time('Y-m-d H:i:s')); ?>
                        <br>
                        <?php echo \esc_html__('Site:', 'woo-order-monitor'); ?> <?php echo \esc_html(\home_url()); ?>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}


<?php
/**
 * Logger Utilities
 *
 * @package KissPlugins\WooOrderMonitor
 * @since 1.7.0
 */

namespace KissPlugins\WooOrderMonitor\Utils;

/**
 * Logger Class
 * 
 * Provides centralized logging functionality with different log levels
 * and conditional logging based on WP_DEBUG settings.
 * 
 * @since 1.7.0
 */
class Logger {
    
    /**
     * Log prefix for all messages
     *
     * @var string
     */
    private const LOG_PREFIX = '[WooCommerce Order Monitor]';
    
    /**
     * Log levels
     */
    private const LEVEL_DEBUG = 'DEBUG';
    private const LEVEL_INFO = 'INFO';
    private const LEVEL_WARNING = 'WARNING';
    private const LEVEL_ERROR = 'ERROR';
    
    /**
     * Log a debug message
     *
     * Only logs if WP_DEBUG is enabled.
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public static function debug(string $message, array $context = []): void {
        if (!\defined('WP_DEBUG') || !\WP_DEBUG) {
            return;
        }
        
        self::log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log an info message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public static function info(string $message, array $context = []): void {
        self::log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log a warning message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public static function warning(string $message, array $context = []): void {
        self::log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log an error message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public static function error(string $message, array $context = []): void {
        self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Log an exception
     *
     * @param \Exception|\Throwable $exception Exception to log
     * @param string $context_message Additional context message
     * @return void
     */
    public static function exception($exception, string $context_message = ''): void {
        $message = $context_message ? $context_message . ': ' : '';
        $message .= $exception->getMessage();
        
        $context = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
        
        self::error($message, $context);
    }
    
    /**
     * Log a message with level
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    private static function log(string $level, string $message, array $context = []): void {
        $formatted_message = self::formatMessage($level, $message, $context);
        \error_log($formatted_message);
    }
    
    /**
     * Format log message
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     * @return string Formatted log message
     */
    private static function formatMessage(string $level, string $message, array $context = []): string {
        $formatted = \sprintf('%s [%s] %s', self::LOG_PREFIX, $level, $message);
        
        if (!empty($context)) {
            $formatted .= ' | Context: ' . \wp_json_encode($context);
        }
        
        return $formatted;
    }
    
    /**
     * Log threshold check result
     *
     * Convenience method for logging threshold check results.
     *
     * @param int $order_count Order count
     * @param int $threshold Threshold value
     * @param bool $is_peak Whether it's peak hours
     * @param bool $below_threshold Whether below threshold
     * @return void
     */
    public static function logThresholdCheck(int $order_count, int $threshold, bool $is_peak, bool $below_threshold): void {
        self::debug(\sprintf(
            'Threshold check - Orders: %d, Threshold: %d, Peak: %s, Below: %s',
            $order_count,
            $threshold,
            $is_peak ? 'Yes' : 'No',
            $below_threshold ? 'Yes' : 'No'
        ));
    }
    
    /**
     * Log email sent result
     *
     * Convenience method for logging email sending results.
     *
     * @param bool $success Whether email was sent successfully
     * @param array $recipients Email recipients
     * @param string $subject Email subject
     * @return void
     */
    public static function logEmailSent(bool $success, array $recipients, string $subject): void {
        if ($success) {
            self::info(\sprintf(
                'Email sent successfully - Recipients: %s, Subject: %s',
                \implode(', ', $recipients),
                $subject
            ));
        } else {
            self::error(\sprintf(
                'Failed to send email - Recipients: %s, Subject: %s',
                \implode(', ', $recipients),
                $subject
            ));
        }
    }
    
    /**
     * Log cron execution
     *
     * Convenience method for logging cron job execution.
     *
     * @param string $hook Cron hook name
     * @param string $status Execution status
     * @return void
     */
    public static function logCronExecution(string $hook, string $status): void {
        self::debug(\sprintf('Cron execution - Hook: %s, Status: %s', $hook, $status));
    }
}


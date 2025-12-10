<?php
/**
 * Time Helper Utilities
 *
 * @package KissPlugins\WooOrderMonitor
 * @since 1.7.0
 */

namespace KissPlugins\WooOrderMonitor\Utils;

/**
 * Time Helper Class
 * 
 * Provides utility functions for time-related operations including
 * timezone handling, time formatting, and time calculations.
 * 
 * @since 1.7.0
 */
class TimeHelper {
    
    /**
     * Get WordPress timezone string
     *
     * @return string Timezone string (e.g., 'America/New_York')
     */
    public static function getTimezone(): string {
        if (\function_exists('wp_timezone_string')) {
            return \wp_timezone_string();
        }
        
        $timezone = \get_option('timezone_string', 'UTC');
        return !empty($timezone) ? $timezone : 'UTC';
    }
    
    /**
     * Get current time in WordPress timezone
     *
     * @param string $format Time format (default: 'Y-m-d H:i:s')
     * @return string Formatted current time
     */
    public static function getCurrentTime(string $format = 'Y-m-d H:i:s'): string {
        return \current_time($format);
    }
    
    /**
     * Get current timestamp in WordPress timezone
     *
     * @return int Current timestamp
     */
    public static function getCurrentTimestamp(): int {
        return (int) \current_time('timestamp');
    }
    
    /**
     * Format timestamp to readable string
     *
     * @param int $timestamp Unix timestamp
     * @param string $format Time format (default: 'Y-m-d H:i:s')
     * @return string Formatted time string
     */
    public static function formatTimestamp(int $timestamp, string $format = 'Y-m-d H:i:s'): string {
        return \date($format, $timestamp);
    }
    
    /**
     * Get time difference in human-readable format
     *
     * @param int $timestamp Unix timestamp
     * @return string Human-readable time difference (e.g., '5 minutes ago')
     */
    public static function getTimeAgo(int $timestamp): string {
        return \human_time_diff($timestamp, self::getCurrentTimestamp()) . ' ' . \__('ago', 'woo-order-monitor');
    }
    
    /**
     * Check if current time is within a time range
     *
     * @param string $start_time Start time in H:i format (e.g., '09:00')
     * @param string $end_time End time in H:i format (e.g., '17:00')
     * @return bool True if current time is within range
     */
    public static function isWithinTimeRange(string $start_time, string $end_time): bool {
        $current = self::getCurrentTime('H:i');
        
        // Handle overnight ranges (e.g., 22:00 to 06:00)
        if ($start_time > $end_time) {
            return $current >= $start_time || $current < $end_time;
        }
        
        return $current >= $start_time && $current < $end_time;
    }
    
    /**
     * Validate time format (H:i)
     *
     * @param string $time Time string to validate
     * @return bool True if valid time format
     */
    public static function isValidTimeFormat(string $time): bool {
        if (!\preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            return false;
        }
        
        $parts = \explode(':', $time);
        $hours = (int) $parts[0];
        $minutes = (int) $parts[1];
        
        return $hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59;
    }
    
    /**
     * Convert minutes to seconds
     *
     * @param int $minutes Number of minutes
     * @return int Number of seconds
     */
    public static function minutesToSeconds(int $minutes): int {
        return $minutes * 60;
    }
    
    /**
     * Convert hours to seconds
     *
     * @param int $hours Number of hours
     * @return int Number of seconds
     */
    public static function hoursToSeconds(int $hours): int {
        return $hours * 3600;
    }
    
    /**
     * Get start and end timestamps for a time period
     *
     * @param int $minutes Number of minutes to look back
     * @return array Array with 'start' and 'end' timestamps
     */
    public static function getTimePeriod(int $minutes): array {
        $end_time = self::getCurrentTimestamp();
        $start_time = $end_time - self::minutesToSeconds($minutes);
        
        return [
            'start' => $start_time,
            'end' => $end_time,
            'start_formatted' => self::formatTimestamp($start_time),
            'end_formatted' => self::formatTimestamp($end_time)
        ];
    }
    
    /**
     * Get seconds until next occurrence of a specific time
     *
     * @param string $time Time in H:i format
     * @return int Seconds until next occurrence
     */
    public static function getSecondsUntil(string $time): int {
        $current = self::getCurrentTimestamp();
        $target = \strtotime($time);
        
        // If target time has passed today, get tomorrow's occurrence
        if ($target < $current) {
            $target = \strtotime('tomorrow ' . $time);
        }
        
        return $target - $current;
    }
    
    /**
     * Format duration in seconds to human-readable string
     *
     * @param int $seconds Duration in seconds
     * @return string Human-readable duration (e.g., '2 hours 30 minutes')
     */
    public static function formatDuration(int $seconds): string {
        if ($seconds < 60) {
            return \sprintf(\__('%d seconds', 'woo-order-monitor'), $seconds);
        }
        
        if ($seconds < 3600) {
            $minutes = \floor($seconds / 60);
            return \sprintf(\__('%d minutes', 'woo-order-monitor'), $minutes);
        }
        
        $hours = \floor($seconds / 3600);
        $minutes = \floor(($seconds % 3600) / 60);
        
        if ($minutes === 0) {
            return \sprintf(\__('%d hours', 'woo-order-monitor'), $hours);
        }
        
        return \sprintf(\__('%d hours %d minutes', 'woo-order-monitor'), $hours, $minutes);
    }
}


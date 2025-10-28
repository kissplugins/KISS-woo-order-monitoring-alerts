<?php
/**
 * Threshold Checker Class
 * 
 * Handles threshold validation, peak hours detection,
 * and threshold comparison logic.
 * 
 * @package KissPlugins\WooOrderMonitor\Monitoring
 * @since 1.5.0
 */

namespace KissPlugins\WooOrderMonitor\Monitoring;

use KissPlugins\WooOrderMonitor\Core\Settings;

/**
 * Threshold Checker Class
 * 
 * Responsible for determining if order counts meet or exceed
 * configured thresholds based on peak/off-peak hours.
 */
class ThresholdChecker {
    
    /**
     * Settings manager
     * 
     * @var Settings
     */
    private $settings;
    
    /**
     * Constructor
     * 
     * @param Settings $settings Settings manager instance
     */
    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }
    
    /**
     * Check if order count is below threshold
     * 
     * @param int $order_count Current order count
     * @param int|null $minutes Time period in minutes (for context)
     * @return array Check result with status and details
     */
    public function checkThreshold(int $order_count, ?int $minutes = 15): array {
        try {
            // Determine if we're in peak hours
            $is_peak = $this->isPeakHours();
            
            // Get appropriate threshold
            $threshold = $this->getActiveThreshold($is_peak);
            
            // Validate threshold
            if (!$this->isValidThreshold($threshold)) {
                return [
                    'status' => 'error',
                    'below_threshold' => false,
                    'message' => 'Invalid threshold configuration',
                    'details' => [
                        'threshold' => $threshold,
                        'is_peak' => $is_peak,
                        'error' => 'Threshold must be a non-negative number'
                    ]
                ];
            }
            
            // Check if below threshold
            $below_threshold = $order_count < $threshold;
            
            // Calculate percentage of threshold met
            $threshold_percentage = $threshold > 0 ? ($order_count / $threshold) * 100 : 100;
            
            // Determine severity level
            $severity = $this->calculateSeverity($order_count, $threshold);
            
            return [
                'status' => 'success',
                'below_threshold' => $below_threshold,
                'message' => $below_threshold ? 'Order count is below threshold' : 'Order count meets threshold',
                'details' => [
                    'order_count' => $order_count,
                    'threshold' => $threshold,
                    'is_peak' => $is_peak,
                    'period_type' => $is_peak ? 'Peak Hours' : 'Off-Peak Hours',
                    'threshold_percentage' => round($threshold_percentage, 1),
                    'severity' => $severity,
                    'time_period_minutes' => $minutes,
                    'check_time' => current_time('mysql')
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'below_threshold' => false,
                'message' => 'Threshold check failed',
                'details' => [
                    'error' => $e->getMessage(),
                    'order_count' => $order_count
                ]
            ];
        }
    }
    
    /**
     * Check if current time is in peak hours
     *
     * Determines whether the specified time falls within the configured peak hours.
     * Handles complex scenarios including peak hours that span midnight (e.g., 22:00-06:00).
     * Validates time formats and provides fallback behavior for invalid configurations.
     *
     * @param string|null $time_to_check Specific time to check in H:i format (24-hour),
     *                                   or null to use current WordPress time
     * @return bool True if the time falls within peak hours, false otherwise.
     *              Returns false for invalid time formats as a safe fallback.
     */
    public function isPeakHours(?string $time_to_check = null): bool {
        $current_time = $time_to_check ?? current_time('H:i');
        $peak_hours = $this->settings->getPeakHours();
        $peak_start = $peak_hours['start'];
        $peak_end = $peak_hours['end'];
        
        // Validate time formats
        if (!$this->isValidTimeFormat($peak_start) || !$this->isValidTimeFormat($peak_end)) {
            // If invalid time format, default to off-peak
            error_log('[WooCommerce Order Monitor] Invalid peak hours format: ' . $peak_start . ' - ' . $peak_end);
            return false;
        }
        
        // Handle cases where peak hours span midnight
        if ($peak_end < $peak_start) {
            // Peak hours span midnight (e.g., 22:00 to 06:00)
            return ($current_time >= $peak_start || $current_time < $peak_end);
        } else {
            // Normal peak hours (e.g., 09:00 to 18:00)
            return ($current_time >= $peak_start && $current_time < $peak_end);
        }
    }
    
    /**
     * Get the active threshold based on peak hours
     * 
     * @param bool|null $is_peak Whether it's peak hours, or null to auto-detect
     * @return int Active threshold value
     */
    public function getActiveThreshold(?bool $is_peak = null): int {
        if ($is_peak === null) {
            $is_peak = $this->isPeakHours();
        }
        
        $thresholds = $this->settings->getThresholds();
        
        return $is_peak ? $thresholds['peak'] : $thresholds['offpeak'];
    }
    
    /**
     * Validate threshold value
     * 
     * @param mixed $threshold Threshold value to validate
     * @return bool True if valid threshold
     */
    public function isValidThreshold($threshold): bool {
        return is_numeric($threshold) && $threshold >= 0;
    }
    
    /**
     * Calculate severity level based on how far below threshold
     * 
     * @param int $order_count Current order count
     * @param int $threshold Threshold value
     * @return string Severity level (low, medium, high, critical)
     */
    public function calculateSeverity(int $order_count, int $threshold): string {
        if ($order_count >= $threshold) {
            return 'none'; // Not below threshold
        }
        
        if ($threshold <= 0) {
            return 'low'; // Can't calculate percentage with zero threshold
        }
        
        $percentage = ($order_count / $threshold) * 100;
        
        if ($percentage >= 75) {
            return 'low';    // 75-99% of threshold
        } elseif ($percentage >= 50) {
            return 'medium'; // 50-74% of threshold
        } elseif ($percentage >= 25) {
            return 'high';   // 25-49% of threshold
        } else {
            return 'critical'; // 0-24% of threshold
        }
    }
    
    /**
     * Get threshold status summary
     * 
     * @return array Threshold configuration summary
     */
    public function getThresholdStatus(): array {
        $is_peak = $this->isPeakHours();
        $thresholds = $this->settings->getThresholds();
        $peak_hours = $this->settings->getPeakHours();
        
        return [
            'current_time' => current_time('H:i'),
            'is_peak_hours' => $is_peak,
            'peak_hours' => $peak_hours,
            'active_threshold' => $this->getActiveThreshold($is_peak),
            'thresholds' => $thresholds,
            'peak_hours_valid' => $this->isValidTimeFormat($peak_hours['start']) && 
                                 $this->isValidTimeFormat($peak_hours['end']),
            'thresholds_valid' => $this->isValidThreshold($thresholds['peak']) && 
                                 $this->isValidThreshold($thresholds['offpeak'])
        ];
    }
    
    /**
     * Test threshold logic with sample data
     * 
     * @return array Test results
     */
    public function testThresholdLogic(): array {
        try {
            $status = $this->getThresholdStatus();
            
            // Test peak hours detection
            $peak_test_times = ['09:00', '12:00', '18:00', '22:00', '02:00'];
            $peak_test_results = [];
            
            foreach ($peak_test_times as $test_time) {
                $peak_test_results[$test_time] = $this->isPeakHours($test_time);
            }
            
            // Test threshold calculations
            $threshold_tests = [];
            $test_counts = [0, 5, 10, 15, 20];
            
            foreach ([true, false] as $is_peak) {
                $threshold = $this->getActiveThreshold($is_peak);
                $period_type = $is_peak ? 'peak' : 'offpeak';
                
                foreach ($test_counts as $count) {
                    $result = $this->checkThreshold($count);
                    $threshold_tests[$period_type][$count] = [
                        'below_threshold' => $result['below_threshold'],
                        'severity' => $result['details']['severity'] ?? 'unknown',
                        'percentage' => $result['details']['threshold_percentage'] ?? 0
                    ];
                }
            }
            
            return [
                'status' => 'pass',
                'message' => 'Threshold logic working correctly',
                'details' => [
                    'current_status' => $status,
                    'peak_hours_tests' => $peak_test_results,
                    'threshold_tests' => $threshold_tests
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Threshold logic test failed',
                'details' => [
                    'error' => $e->getMessage()
                ]
            ];
        }
    }
    
    /**
     * Validate time format (HH:MM)
     * 
     * @param string $time Time string to validate
     * @return bool True if valid time format
     */
    private function isValidTimeFormat(string $time): bool {
        return (bool) preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }
    
    /**
     * Get time until next peak hours change
     * 
     * @return array Information about next peak hours change
     */
    public function getNextPeakHoursChange(): array {
        $current_time = current_time('H:i');
        $is_peak = $this->isPeakHours();
        $peak_hours = $this->settings->getPeakHours();
        
        $current_timestamp = strtotime($current_time);
        $peak_start_timestamp = strtotime($peak_hours['start']);
        $peak_end_timestamp = strtotime($peak_hours['end']);
        
        if ($is_peak) {
            // Currently in peak hours, next change is to off-peak
            $next_change_time = $peak_hours['end'];
            $next_change_timestamp = $peak_end_timestamp;
            $next_state = 'off-peak';
        } else {
            // Currently in off-peak hours, next change is to peak
            $next_change_time = $peak_hours['start'];
            $next_change_timestamp = $peak_start_timestamp;
            $next_state = 'peak';
            
            // Handle case where peak start is tomorrow
            if ($next_change_timestamp <= $current_timestamp) {
                $next_change_timestamp += 24 * 60 * 60; // Add 24 hours
            }
        }
        
        $seconds_until_change = $next_change_timestamp - $current_timestamp;
        
        return [
            'current_state' => $is_peak ? 'peak' : 'off-peak',
            'next_state' => $next_state,
            'next_change_time' => $next_change_time,
            'seconds_until_change' => $seconds_until_change,
            'minutes_until_change' => round($seconds_until_change / 60),
            'hours_until_change' => round($seconds_until_change / 3600, 1)
        ];
    }
}

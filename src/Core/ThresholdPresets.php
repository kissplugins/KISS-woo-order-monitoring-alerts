<?php
/**
 * Threshold Presets
 * 
 * Provides preset configurations for different store types
 * 
 * @package KISS_WooCommerce_Order_Monitor
 * @since 1.7.1
 */

namespace KissPlugins\WooOrderMonitor\Core;

/**
 * Threshold Presets Class
 * 
 * Manages preset threshold block configurations for different store types
 */
class ThresholdPresets {
    
    /**
     * Get all available presets
     * 
     * @return array Array of preset configurations
     */
    public static function getAllPresets(): array {
        return [
            'binoid' => self::getBinoidPreset(),
            'standard_retail' => self::getStandardRetailPreset(),
            'twenty_four_seven' => self::getTwentyFourSevenPreset(),
            'custom' => self::getCustomPreset()
        ];
    }
    
    /**
     * Get preset by key
     * 
     * @param string $preset_key Preset key (binoid, standard_retail, twenty_four_seven, custom)
     * @return array|null Preset configuration or null if not found
     */
    public static function getPreset(string $preset_key): ?array {
        $presets = self::getAllPresets();
        return $presets[$preset_key] ?? null;
    }
    
    /**
     * Get preset names for dropdown
     * 
     * @return array Array of preset keys and labels
     */
    public static function getPresetOptions(): array {
        return [
            'binoid' => __('BINOID (High-Volume E-Commerce)', 'woo-order-monitor'),
            'standard_retail' => __('Standard Retail (9-5 Business)', 'woo-order-monitor'),
            'twenty_four_seven' => __('24/7 Store (Always Open)', 'woo-order-monitor'),
            'custom' => __('Custom Configuration', 'woo-order-monitor')
        ];
    }
    
    /**
     * BINOID Preset - High-volume e-commerce with distinct traffic patterns
     * Based on actual sales data: 2,273 orders over 48 hours
     * 
     * @return array Preset configuration
     */
    public static function getBinoidPreset(): array {
        return [
            'name' => 'BINOID (High-Volume E-Commerce)',
            'description' => 'Optimized for high-volume e-commerce with 5 distinct daily traffic phases. Based on BINOID sales data (2,273 orders/48hrs).',
            'blocks' => [
                [
                    'name' => 'overnight',
                    'enabled' => true,
                    'time_ranges' => [['start' => '00:00', 'end' => '04:59']],
                    'threshold' => 0,
                    'expected_range' => ['min' => 0, 'max' => 1],
                    'critical_threshold' => 0,
                    'alert_on_any_activity' => false
                ],
                [
                    'name' => 'morning_surge',
                    'enabled' => true,
                    'time_ranges' => [['start' => '05:00', 'end' => '07:59']],
                    'threshold' => 8,
                    'expected_range' => ['min' => 8, 'max' => 12],
                    'critical_threshold' => 4
                ],
                [
                    'name' => 'morning_steady',
                    'enabled' => true,
                    'time_ranges' => [['start' => '08:00', 'end' => '10:59']],
                    'threshold' => 10,
                    'expected_range' => ['min' => 9, 'max' => 12],
                    'critical_threshold' => 5
                ],
                [
                    'name' => 'lunch_peak',
                    'enabled' => true,
                    'time_ranges' => [['start' => '11:00', 'end' => '13:59']],
                    'threshold' => 20,
                    'expected_range' => ['min' => 17, 'max' => 25],
                    'critical_threshold' => 10
                ],
                [
                    'name' => 'afternoon_decline',
                    'enabled' => true,
                    'time_ranges' => [['start' => '14:00', 'end' => '17:59']],
                    'threshold' => 15,
                    'expected_range' => ['min' => 12, 'max' => 18],
                    'critical_threshold' => 8
                ],
                [
                    'name' => 'evening_plateau',
                    'enabled' => true,
                    'time_ranges' => [['start' => '18:00', 'end' => '19:59']],
                    'threshold' => 15,
                    'expected_range' => ['min' => 13, 'max' => 17],
                    'critical_threshold' => 8
                ],
                [
                    'name' => 'evening_decline',
                    'enabled' => true,
                    'time_ranges' => [['start' => '20:00', 'end' => '21:59']],
                    'threshold' => 5,
                    'expected_range' => ['min' => 3, 'max' => 8],
                    'critical_threshold' => 2
                ],
                [
                    'name' => 'late_night',
                    'enabled' => true,
                    'time_ranges' => [['start' => '22:00', 'end' => '23:59']],
                    'threshold' => 0,
                    'expected_range' => ['min' => 0, 'max' => 2],
                    'critical_threshold' => 0,
                    'alert_on_any_activity' => false
                ]
            ]
        ];
    }
    
    /**
     * Standard Retail Preset - Traditional 9-5 business hours
     * 
     * @return array Preset configuration
     */
    public static function getStandardRetailPreset(): array {
        return [
            'name' => 'Standard Retail (9-5 Business)',
            'description' => 'Optimized for traditional retail with business hours 9 AM - 5 PM. Minimal activity outside business hours.',
            'blocks' => [
                [
                    'name' => 'overnight',
                    'enabled' => true,
                    'time_ranges' => [['start' => '00:00', 'end' => '07:59']],
                    'threshold' => 0,
                    'expected_range' => ['min' => 0, 'max' => 1],
                    'critical_threshold' => 0,
                    'alert_on_any_activity' => false
                ],
                [
                    'name' => 'morning_opening',
                    'enabled' => true,
                    'time_ranges' => [['start' => '08:00', 'end' => '09:59']],
                    'threshold' => 3,
                    'expected_range' => ['min' => 2, 'max' => 5],
                    'critical_threshold' => 1
                ],
                [
                    'name' => 'morning_business',
                    'enabled' => true,
                    'time_ranges' => [['start' => '10:00', 'end' => '11:59']],
                    'threshold' => 5,
                    'expected_range' => ['min' => 4, 'max' => 8],
                    'critical_threshold' => 2
                ],
                [
                    'name' => 'lunch_hour',
                    'enabled' => true,
                    'time_ranges' => [['start' => '12:00', 'end' => '13:59']],
                    'threshold' => 8,
                    'expected_range' => ['min' => 6, 'max' => 12],
                    'critical_threshold' => 4
                ],
                [
                    'name' => 'afternoon_business',
                    'enabled' => true,
                    'time_ranges' => [['start' => '14:00', 'end' => '16:59']],
                    'threshold' => 5,
                    'expected_range' => ['min' => 4, 'max' => 8],
                    'critical_threshold' => 2
                ],
                [
                    'name' => 'closing_time',
                    'enabled' => true,
                    'time_ranges' => [['start' => '17:00', 'end' => '18:59']],
                    'threshold' => 2,
                    'expected_range' => ['min' => 1, 'max' => 4],
                    'critical_threshold' => 0
                ],
                [
                    'name' => 'evening',
                    'enabled' => true,
                    'time_ranges' => [['start' => '19:00', 'end' => '21:59']],
                    'threshold' => 0,
                    'expected_range' => ['min' => 0, 'max' => 2],
                    'critical_threshold' => 0,
                    'alert_on_any_activity' => false
                ],
                [
                    'name' => 'late_night',
                    'enabled' => true,
                    'time_ranges' => [['start' => '22:00', 'end' => '23:59']],
                    'threshold' => 0,
                    'expected_range' => ['min' => 0, 'max' => 1],
                    'critical_threshold' => 0,
                    'alert_on_any_activity' => false
                ]
            ]
        ];
    }
    
    /**
     * 24/7 Store Preset - Always-open store with consistent traffic
     * 
     * @return array Preset configuration
     */
    public static function getTwentyFourSevenPreset(): array {
        return [
            'name' => '24/7 Store (Always Open)',
            'description' => 'Optimized for stores that operate 24/7 with relatively consistent traffic throughout the day.',
            'blocks' => [
                [
                    'name' => 'late_night',
                    'enabled' => true,
                    'time_ranges' => [['start' => '00:00', 'end' => '05:59']],
                    'threshold' => 3,
                    'expected_range' => ['min' => 2, 'max' => 5],
                    'critical_threshold' => 1
                ],
                [
                    'name' => 'early_morning',
                    'enabled' => true,
                    'time_ranges' => [['start' => '06:00', 'end' => '08:59']],
                    'threshold' => 5,
                    'expected_range' => ['min' => 4, 'max' => 8],
                    'critical_threshold' => 2
                ],
                [
                    'name' => 'morning',
                    'enabled' => true,
                    'time_ranges' => [['start' => '09:00', 'end' => '11:59']],
                    'threshold' => 8,
                    'expected_range' => ['min' => 6, 'max' => 12],
                    'critical_threshold' => 4
                ],
                [
                    'name' => 'midday',
                    'enabled' => true,
                    'time_ranges' => [['start' => '12:00', 'end' => '14:59']],
                    'threshold' => 10,
                    'expected_range' => ['min' => 8, 'max' => 15],
                    'critical_threshold' => 5
                ],
                [
                    'name' => 'afternoon',
                    'enabled' => true,
                    'time_ranges' => [['start' => '15:00', 'end' => '17:59']],
                    'threshold' => 8,
                    'expected_range' => ['min' => 6, 'max' => 12],
                    'critical_threshold' => 4
                ],
                [
                    'name' => 'evening',
                    'enabled' => true,
                    'time_ranges' => [['start' => '18:00', 'end' => '20:59']],
                    'threshold' => 6,
                    'expected_range' => ['min' => 4, 'max' => 10],
                    'critical_threshold' => 3
                ],
                [
                    'name' => 'night',
                    'enabled' => true,
                    'time_ranges' => [['start' => '21:00', 'end' => '23:59']],
                    'threshold' => 4,
                    'expected_range' => ['min' => 3, 'max' => 6],
                    'critical_threshold' => 2
                ],
                [
                    'name' => 'placeholder',
                    'enabled' => false,
                    'time_ranges' => [['start' => '00:00', 'end' => '00:00']],
                    'threshold' => 0,
                    'expected_range' => ['min' => 0, 'max' => 0],
                    'critical_threshold' => 0
                ]
            ]
        ];
    }
    
    /**
     * Custom Preset - Empty template for user customization
     * 
     * @return array Preset configuration
     */
    public static function getCustomPreset(): array {
        return [
            'name' => 'Custom Configuration',
            'description' => 'Create your own custom threshold blocks based on your store\'s unique traffic patterns.',
            'blocks' => SettingsDefaults::getDefaultThresholdBlocks()
        ];
    }
}


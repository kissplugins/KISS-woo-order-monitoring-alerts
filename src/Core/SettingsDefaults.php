<?php
/**
 * Centralized Settings Defaults Configuration
 * 
 * ⚠️  CRITICAL: This is the SINGLE SOURCE OF TRUTH for all plugin default values.
 * ⚠️  DO NOT define default values anywhere else in the codebase.
 * ⚠️  All UI forms, activation code, and runtime code MUST reference this class.
 * 
 * @package KissPlugins\WooOrderMonitor\Core
 * @since 1.5.1
 */

namespace KissPlugins\WooOrderMonitor\Core;

/**
 * Centralized Settings Defaults Configuration
 * 
 * This class serves as the single source of truth for all plugin settings
 * and their default values. This prevents drift and inconsistencies that
 * can occur when defaults are scattered across multiple files.
 * 
 * ⚠️  IMPORTANT RULES:
 * 1. ALL default values MUST be defined here and ONLY here
 * 2. UI forms MUST call getUIDefaults() for form field defaults
 * 3. Activation code MUST call getActivationDefaults() for initial setup
 * 4. Runtime code MUST call getRuntimeDefaults() for fallback values
 * 5. Any changes to defaults MUST be made here and tested thoroughly
 * 
 * @since 1.5.1
 */
class SettingsDefaults {
    
    /**
     * Master default values configuration
     * 
     * This is the authoritative source for all default values.
     * Changes here will affect the entire plugin.
     * 
     * @var array
     */
    private static $master_defaults = [
        // Core monitoring settings
        'enabled' => 'yes',
        'peak_start' => '09:00',
        'peak_end' => '18:00',
        'threshold_peak' => 10,
        'threshold_offpeak' => 2,
        'notification_emails' => '', // Will be set to admin_email dynamically
        
        // System tracking
        'last_check' => 0,
        'last_alert' => 0,
        
        // Advanced settings
        'alert_cooldown' => 7200, // 2 hours in seconds
        'max_daily_alerts' => 6,
        'last_alert_peak' => 0,
        'last_alert_offpeak' => 0,
        'daily_alert_count' => 0,
        'daily_alert_date' => '', // Will be set to current date dynamically
        'enable_system_alerts' => 'yes',
        'webhook_url' => '',
        'query_cache_duration' => 300, // 5 minutes in seconds

        // Rolling Average Detection (RAD) settings - v1.6.0
        'rolling_enabled' => 'no', // Opt-in for Phase 1
        'rolling_window_size' => 10, // Track last N orders
        'rolling_failure_threshold' => 70, // Alert if X% of orders fail
        'rolling_min_orders' => 3, // Minimum orders before alerting
        'rolling_cache_duration' => 300, // 5 minutes in seconds

        // Plugin metadata
        'plugin_version' => '',
        'activated_at' => 0,
        'deactivated_at' => 0
    ];
    
    /**
     * Get all default values for runtime use
     * 
     * Used by Settings class and other runtime components.
     * Includes dynamic defaults like admin_email and current date.
     * 
     * @return array Complete default values with dynamic values resolved
     */
    public static function getRuntimeDefaults(): array {
        $defaults = self::$master_defaults;
        
        // Set dynamic defaults
        if (empty($defaults['notification_emails'])) {
            $defaults['notification_emails'] = get_option('admin_email', '');
        }
        
        if (empty($defaults['daily_alert_date'])) {
            $defaults['daily_alert_date'] = date('Y-m-d');
        }
        
        return $defaults;
    }
    
    /**
     * Get default values for plugin activation
     * 
     * Used during plugin activation to set initial option values.
     * Only includes settings that should be stored in wp_options.
     * 
     * @return array Default values for activation with wp_options keys
     */
    public static function getActivationDefaults(): array {
        $defaults = self::getRuntimeDefaults();
        
        // Convert to wp_options format (add woom_ prefix)
        $activation_defaults = [];
        foreach ($defaults as $key => $value) {
            // Skip metadata that shouldn't be set during activation
            if (in_array($key, ['plugin_version', 'activated_at', 'deactivated_at'])) {
                continue;
            }
            
            $activation_defaults['woom_' . $key] = $value;
        }
        
        return $activation_defaults;
    }
    
    /**
     * Get default values for UI forms
     * 
     * Used by admin settings pages to populate form field defaults.
     * Returns values in the format expected by WooCommerce admin fields.
     * 
     * @return array Default values formatted for UI forms
     */
    public static function getUIDefaults(): array {
        return self::getRuntimeDefaults();
    }
    
    /**
     * Get a specific default value
     * 
     * @param string $key Setting key (without woom_ prefix)
     * @param mixed $fallback Fallback value if key not found
     * @return mixed Default value or fallback
     */
    public static function getDefault(string $key, $fallback = null) {
        $defaults = self::getRuntimeDefaults();
        return $defaults[$key] ?? $fallback;
    }
    
    /**
     * Get validation rules for settings
     * 
     * @return array Validation rules for each setting
     */
    public static function getValidationRules(): array {
        return [
            'enabled' => ['type' => 'string', 'values' => ['yes', 'no']],
            'peak_start' => ['type' => 'time'],
            'peak_end' => ['type' => 'time'],
            'threshold_peak' => ['type' => 'int', 'min' => 0, 'max' => 1000],
            'threshold_offpeak' => ['type' => 'int', 'min' => 0, 'max' => 1000],
            'notification_emails' => ['type' => 'email_list'],
            'last_check' => ['type' => 'int', 'min' => 0],
            'last_alert' => ['type' => 'int', 'min' => 0],
            'alert_cooldown' => ['type' => 'int', 'min' => 300, 'max' => 86400], // 5 min to 24 hours
            'max_daily_alerts' => ['type' => 'int', 'min' => 1, 'max' => 50],
            'last_alert_peak' => ['type' => 'int', 'min' => 0],
            'last_alert_offpeak' => ['type' => 'int', 'min' => 0],
            'daily_alert_count' => ['type' => 'int', 'min' => 0],
            'daily_alert_date' => ['type' => 'date'],
            'enable_system_alerts' => ['type' => 'string', 'values' => ['yes', 'no']],
            'webhook_url' => ['type' => 'url'],
            'query_cache_duration' => ['type' => 'int', 'min' => 60, 'max' => 3600], // 1 min to 1 hour

            // Rolling Average Detection validation rules
            'rolling_enabled' => ['type' => 'string', 'values' => ['yes', 'no']],
            'rolling_window_size' => ['type' => 'int', 'min' => 3, 'max' => 50],
            'rolling_failure_threshold' => ['type' => 'int', 'min' => 1, 'max' => 100],
            'rolling_min_orders' => ['type' => 'int', 'min' => 1, 'max' => 20],
            'rolling_cache_duration' => ['type' => 'int', 'min' => 60, 'max' => 3600]
        ];
    }
    
    /**
     * Get all option keys that should be removed during uninstall
     * 
     * @return array Array of wp_options keys to remove
     */
    public static function getAllOptionKeys(): array {
        $keys = [];
        foreach (array_keys(self::$master_defaults) as $key) {
            $keys[] = 'woom_' . $key;
        }
        return $keys;
    }
    
    /**
     * Validate that no other files define conflicting defaults
     *
     * This method can be called during self-tests to ensure
     * no other parts of the codebase define default values.
     *
     * @return array Validation result with status and details
     */
    public static function validateCentralization(): array {
        $issues = [];

        // Define patterns that indicate hardcoded defaults
        $forbidden_patterns = [
            "get_option.*'woom_.*',.*[0-9]" => 'Hardcoded fallback values in get_option calls',
            "'default'.*=>.*[0-9]" => 'Hardcoded default values in form fields',
            "add_option.*'woom_.*',.*[0-9]" => 'Hardcoded values in add_option calls'
        ];

        // Files to scan (excluding this file)
        $files_to_scan = [
            'kiss-woo-order-monitoring-alerts.php',
            'src/Core/Settings.php',
            'src/Core/Installer.php',
            'src/Admin/SettingsPage.php'
        ];

        $plugin_dir = dirname(dirname(__DIR__));

        foreach ($files_to_scan as $file) {
            $file_path = $plugin_dir . '/' . $file;
            if (file_exists($file_path)) {
                $content = file_get_contents($file_path);

                foreach ($forbidden_patterns as $pattern => $description) {
                    if (preg_match('/' . $pattern . '/i', $content)) {
                        $issues[] = [
                            'file' => $file,
                            'pattern' => $pattern,
                            'description' => $description
                        ];
                    }
                }
            }
        }

        if (empty($issues)) {
            return [
                'status' => 'pass',
                'message' => 'No hardcoded defaults found - centralization is working correctly',
                'details' => [
                    'files_scanned' => count($files_to_scan),
                    'patterns_checked' => count($forbidden_patterns)
                ]
            ];
        } else {
            return [
                'status' => 'fail',
                'message' => 'Found hardcoded default values that should use SettingsDefaults',
                'details' => [
                    'issues_found' => count($issues),
                    'issues' => $issues,
                    'fix_instructions' => 'Replace hardcoded values with SettingsDefaults::getDefault() calls'
                ]
            ];
        }
    }
}

<?php
/**
 * Settings Management Class
 *
 * Handles all plugin settings including loading, validation,
 * caching, and default values.
 *
 * ⚠️  IMPORTANT: This class now uses SettingsDefaults for all default values.
 * ⚠️  DO NOT define default values in this class - use SettingsDefaults instead.
 *
 * @package KissPlugins\WooOrderMonitor\Core
 * @since 1.5.0
 * @updated 1.5.1 - Centralized defaults to prevent drift
 */

namespace KissPlugins\WooOrderMonitor\Core;

/**
 * Settings Management Class
 *
 * Centralized settings management with validation, caching,
 * and type safety. All default values are now sourced from
 * SettingsDefaults to prevent configuration drift.
 */
class Settings {

    /**
     * Settings cache
     *
     * @var array
     */
    private $settings = [];

    /**
     * Settings loaded flag
     *
     * @var bool
     */
    private $loaded = false;

    /**
     * Constructor
     */
    public function __construct() {
        // Settings will be loaded on demand
    }

    /**
     * Load all settings from WordPress options
     *
     * Uses SettingsDefaults for all default values to ensure consistency.
     *
     * @return void
     */
    public function load(): void {
        if ($this->loaded) {
            return;
        }

        // Get defaults from centralized configuration
        $defaults = SettingsDefaults::getRuntimeDefaults();

        // Load each setting with its default value
        foreach ($defaults as $key => $default) {
            $option_key = 'woom_' . $key;
            $value = get_option($option_key, $default);

            // Apply validation
            $this->settings[$key] = $this->validateSetting($key, $value);
        }

        $this->loaded = true;
    }
    
    /**
     * Get a setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value if setting not found
     * @return mixed Setting value
     */
    public function get(string $key, $default = null) {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->settings[$key] ?? $default ?? SettingsDefaults::getDefault($key) ?? null;
    }
    
    /**
     * Set a setting value
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool True if setting was saved successfully
     */
    public function set(string $key, $value): bool {
        // Validate the value
        $validated_value = $this->validateSetting($key, $value);
        
        if ($validated_value === false) {
            return false;
        }
        
        // Update in cache
        $this->settings[$key] = $validated_value;
        
        // Save to WordPress options
        $option_key = 'woom_' . $key;
        return update_option($option_key, $validated_value);
    }
    
    /**
     * Get all settings
     * 
     * @return array All settings
     */
    public function getAll(): array {
        if (!$this->loaded) {
            $this->load();
        }
        
        return $this->settings;
    }
    
    /**
     * Update multiple settings at once
     * 
     * @param array $settings Associative array of settings
     * @return bool True if all settings were saved successfully
     */
    public function updateMultiple(array $settings): bool {
        $success = true;
        
        foreach ($settings as $key => $value) {
            if (!$this->set($key, $value)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Validate a setting value
     *
     * Validates and sanitizes setting values according to predefined rules.
     * Supports type validation, range checking, and format validation.
     *
     * @param string $key Setting key to validate against validation rules
     * @param mixed $value Raw value to validate and sanitize
     * @return mixed Validated and sanitized value, or false if validation fails.
     *               Supported validation types:
     *               - string: Validates against allowed values list
     *               - int: Validates numeric range (min/max)
     *               - time: Validates HH:MM format (24-hour)
     *               - email_list: Validates comma-separated email addresses
     */
    private function validateSetting(string $key, $value) {
        $validation_rules = SettingsDefaults::getValidationRules();

        if (!isset($validation_rules[$key])) {
            return $value; // No validation rule, return as-is
        }

        $rule = $validation_rules[$key];
        
        switch ($rule['type']) {
            case 'string':
                $value = (string) $value;
                if (isset($rule['values']) && !in_array($value, $rule['values'], true)) {
                    return false;
                }
                break;
                
            case 'int':
                $value = (int) $value;
                if (isset($rule['min']) && $value < $rule['min']) {
                    return false;
                }
                if (isset($rule['max']) && $value > $rule['max']) {
                    return false;
                }
                break;
                
            case 'time':
                if (!$this->isValidTimeFormat($value)) {
                    return false;
                }
                break;
                
            case 'email_list':
                if (!$this->isValidEmailList($value)) {
                    return false;
                }
                break;
        }
        
        return $value;
    }
    
    /**
     * Check if a time string is in valid HH:MM format
     * 
     * @param string $time Time string to validate
     * @return bool True if valid time format
     */
    private function isValidTimeFormat(string $time): bool {
        return (bool) preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }
    
    /**
     * Validate email list (comma-separated emails)
     * 
     * @param string $email_list Comma-separated email list
     * @return bool True if all emails are valid
     */
    private function isValidEmailList(string $email_list): bool {
        if (empty($email_list)) {
            return true; // Empty list is valid
        }
        
        $emails = array_map('trim', explode(',', $email_list));
        
        foreach ($emails as $email) {
            if (!empty($email) && !is_email($email)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get default value for a setting
     *
     * @param string $key Setting key
     * @return mixed Default value
     */
    public function getDefault(string $key) {
        return SettingsDefaults::getDefault($key);
    }

    /**
     * Reset a setting to its default value
     *
     * @param string $key Setting key
     * @return bool True if reset was successful
     */
    public function resetToDefault(string $key): bool {
        $default = SettingsDefaults::getDefault($key);
        if ($default === null) {
            return false;
        }

        return $this->set($key, $default);
    }

    /**
     * Reset all settings to defaults
     *
     * @return bool True if all settings were reset successfully
     */
    public function resetAllToDefaults(): bool {
        return $this->updateMultiple(SettingsDefaults::getRuntimeDefaults());
    }
    
    /**
     * Check if monitoring is enabled
     * 
     * @return bool True if monitoring is enabled
     */
    public function isEnabled(): bool {
        return $this->get('enabled') === 'yes';
    }
    
    /**
     * Get notification email addresses as array
     * 
     * @return array Array of email addresses
     */
    public function getNotificationEmails(): array {
        $emails = $this->get('notification_emails', '');
        
        if (empty($emails)) {
            return [get_option('admin_email', '')];
        }
        
        return array_filter(array_map('trim', explode(',', $emails)));
    }
    
    /**
     * Get peak hours as array
     * 
     * @return array Array with 'start' and 'end' keys
     */
    public function getPeakHours(): array {
        return [
            'start' => $this->get('peak_start'),
            'end' => $this->get('peak_end')
        ];
    }
    
    /**
     * Get thresholds as array
     * 
     * @return array Array with 'peak' and 'offpeak' keys
     */
    public function getThresholds(): array {
        return [
            'peak' => $this->get('threshold_peak'),
            'offpeak' => $this->get('threshold_offpeak')
        ];
    }
    
    /**
     * Clear settings cache
     * 
     * Forces settings to be reloaded from database on next access.
     * 
     * @return void
     */
    public function clearCache(): void {
        $this->settings = [];
        $this->loaded = false;
    }
}

<?php
/**
 * Plugin Installer Class
 * 
 * Handles plugin activation, deactivation, and uninstallation
 * including database setup, option initialization, and cleanup.
 * 
 * @package KissPlugins\WooOrderMonitor\Core
 * @since 1.5.0
 */

namespace KissPlugins\WooOrderMonitor\Core;

/**
 * Plugin Installer Class
 * 
 * Manages plugin lifecycle events including activation,
 * deactivation, and uninstallation procedures.
 */
class Installer {
    
    /**
     * Settings manager
     * 
     * @var Settings
     */
    private $settings;
    
    /**
     * Plugin version for database migrations
     * 
     * @var string
     */
    private $plugin_version;
    
    /**
     * Constructor
     * 
     * @param Settings $settings Settings manager instance
     */
    public function __construct(Settings $settings) {
        $this->settings = $settings;
        $this->plugin_version = WOOM_VERSION;
    }
    
    /**
     * Plugin activation
     *
     * Sets up default options, schedules cron jobs, and performs
     * any necessary database setup.
     *
     * @return void
     * @throws \Exception When activation fails due to system errors
     */
    public function activate(): void {
        try {
            // Set up default options
            $this->setupDefaultOptions();
            
            // Schedule cron job if monitoring is enabled
            $this->scheduleCronJob();
            
            // Set plugin version
            \update_option('woom_plugin_version', $this->plugin_version);

            // Set activation timestamp
            \update_option('woom_activated_at', \current_time('timestamp'));

            // Flush rewrite rules
            \flush_rewrite_rules();

            // Log successful activation
            if (defined('WP_DEBUG') && \WP_DEBUG) {
                \error_log('[WooCommerce Order Monitor] Plugin activated successfully.');
            }
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Activation error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Plugin deactivation
     *
     * Cleans up scheduled cron jobs and performs cleanup tasks.
     *
     * @return void
     * @throws \Exception When deactivation fails due to system errors
     */
    public function deactivate(): void {
        try {
            // Clear scheduled cron jobs
            $this->clearCronJobs();
            
            // Clear any cached data
            $this->clearCaches();
            
            // Set deactivation timestamp
            \update_option('woom_deactivated_at', \current_time('timestamp'));

            // Log successful deactivation
            if (defined('WP_DEBUG') && \WP_DEBUG) {
                \error_log('[WooCommerce Order Monitor] Plugin deactivated successfully.');
            }
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Deactivation error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Plugin uninstallation
     *
     * Removes all plugin data including options, scheduled jobs,
     * and any custom database tables.
     *
     * @return void
     * @throws \Exception When uninstallation fails due to system errors
     */
    public function uninstall(): void {
        try {
            // Clear all scheduled cron jobs
            $this->clearCronJobs();
            
            // Remove all plugin options
            $this->removeAllOptions();
            
            // Clear caches
            $this->clearCaches();
            
            // Log successful uninstallation
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WooCommerce Order Monitor] Plugin uninstalled successfully.');
            }
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Uninstallation error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Set up default options
     *
     * ⚠️  IMPORTANT: Uses SettingsDefaults for all default values.
     * ⚠️  DO NOT define default values here - use SettingsDefaults instead.
     *
     * @return void
     */
    private function setupDefaultOptions(): void {
        // Get default settings from centralized configuration
        $defaults = SettingsDefaults::getActivationDefaults();

        // Only add options that don't already exist
        foreach ($defaults as $option_name => $default_value) {
            \add_option($option_name, $default_value);
        }
    }
    
    /**
     * Schedule cron job if monitoring is enabled
     * 
     * @return void
     */
    private function scheduleCronJob(): void {
        $enabled = \get_option('woom_enabled', 'yes');

        if ('yes' === $enabled) {
            // Clear any existing scheduled events first
            \wp_clear_scheduled_hook('woom_check_orders');

            // Schedule new event
            if (!\wp_next_scheduled('woom_check_orders')) {
                \wp_schedule_event(\time(), 'woom_15min', 'woom_check_orders');
            }
        }
    }
    
    /**
     * Clear all scheduled cron jobs
     * 
     * @return void
     */
    private function clearCronJobs(): void {
        // Clear WP-Cron scheduled events
        \wp_clear_scheduled_hook('woom_check_orders');

        // Clear Action Scheduler events if available
        if (\function_exists('as_unschedule_all_actions')) {
            \as_unschedule_all_actions('woom_as_check_orders', [], 'woo-order-monitor');
        }
    }
    
    /**
     * Remove all plugin options
     * 
     * @return void
     */
    private function removeAllOptions(): void {
        $options_to_remove = [
            'woom_enabled',
            'woom_peak_start',
            'woom_peak_end',
            'woom_threshold_peak',
            'woom_threshold_offpeak',
            'woom_notification_emails',
            'woom_last_check',
            'woom_last_alert',
            'woom_plugin_version',
            'woom_activated_at',
            'woom_deactivated_at'
        ];
        
        foreach ($options_to_remove as $option) {
            \delete_option($option);
        }
    }
    
    /**
     * Clear all caches
     * 
     * @return void
     */
    private function clearCaches(): void {
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear plugin-specific caches
        wp_cache_delete_group('woo-order-monitor');
        
        // Clear settings cache
        $this->settings->clearCache();
    }
    
    /**
     * Check if plugin needs database migration
     * 
     * @return bool True if migration is needed
     */
    public function needsMigration(): bool {
        $installed_version = get_option('woom_plugin_version', '0.0.0');
        return version_compare($installed_version, $this->plugin_version, '<');
    }
    
    /**
     * Perform database migration
     * 
     * @return bool True if migration was successful
     */
    public function migrate(): bool {
        try {
            $installed_version = get_option('woom_plugin_version', '0.0.0');
            
            // Perform version-specific migrations
            if (version_compare($installed_version, '1.5.0', '<')) {
                $this->migrateToV150();
            }
            
            // Update plugin version
            update_option('woom_plugin_version', $this->plugin_version);
            
            return true;
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Migration error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Migration to version 1.5.0
     * 
     * @return void
     */
    private function migrateToV150(): void {
        // Ensure all new default options exist
        $this->setupDefaultOptions();
        
        // Clear any legacy caches
        wp_cache_delete('woom_settings', 'options');
        
        // Log migration
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WooCommerce Order Monitor] Migrated to version 1.5.0');
        }
    }
    
    /**
     * Get installation status
     * 
     * @return array Installation status information
     */
    public function getInstallationStatus(): array {
        return [
            'installed_version' => get_option('woom_plugin_version', 'Not installed'),
            'current_version' => $this->plugin_version,
            'needs_migration' => $this->needsMigration(),
            'activated_at' => get_option('woom_activated_at', 0),
            'deactivated_at' => get_option('woom_deactivated_at', 0),
            'cron_scheduled' => wp_next_scheduled('woom_check_orders') !== false,
            'action_scheduler_scheduled' => function_exists('as_next_scheduled_action') ? 
                as_next_scheduled_action('woom_as_check_orders') !== false : false
        ];
    }
    
    /**
     * Repair installation
     * 
     * Attempts to fix common installation issues.
     * 
     * @return bool True if repair was successful
     */
    public function repair(): bool {
        try {
            // Reset default options
            $this->setupDefaultOptions();
            
            // Reschedule cron job
            $this->scheduleCronJob();
            
            // Clear caches
            $this->clearCaches();
            
            // Update version
            update_option('woom_plugin_version', $this->plugin_version);
            
            return true;
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Repair error: ' . $e->getMessage());
            return false;
        }
    }
}

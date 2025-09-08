<?php
/**
 * Main Plugin Class
 * 
 * This is the core plugin class that handles initialization,
 * dependency injection, and coordination of all plugin components.
 * 
 * @package KissPlugins\WooOrderMonitor\Core
 * @since 1.5.0
 */

namespace KissPlugins\WooOrderMonitor\Core;

use KissPlugins\WooOrderMonitor\Monitoring\OrderMonitor;

/**
 * Main Plugin Class
 * 
 * Handles plugin initialization, dependency management, and coordination
 * of all plugin components using proper dependency injection.
 */
class Plugin {
    
    /**
     * Plugin instance
     * 
     * @var Plugin|null
     */
    private static $instance = null;
    
    /**
     * Settings manager
     * 
     * @var Settings
     */
    private $settings;
    
    /**
     * Dependencies checker
     * 
     * @var Dependencies
     */
    private $dependencies;
    
    /**
     * Order monitor
     * 
     * @var OrderMonitor
     */
    private $order_monitor;
    
    /**
     * Settings page
     *
     * @var object|null
     */
    private $settings_page;

    /**
     * Action Scheduler integration
     *
     * @var object|null
     */
    private $action_scheduler;
    
    /**
     * Plugin initialization status
     * 
     * @var bool
     */
    private $initialized = false;
    
    /**
     * Get singleton instance
     * 
     * @return Plugin
     */
    public static function getInstance(): Plugin {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     * 
     * Private constructor to enforce singleton pattern.
     * Use getInstance() to get the plugin instance.
     */
    private function __construct() {
        // Constructor is intentionally minimal
        // Actual initialization happens in init() method
    }
    
    /**
     * Initialize the plugin
     * 
     * This method sets up all plugin components and hooks.
     * It should be called after WordPress is fully loaded.
     * 
     * @return bool True if initialization was successful, false otherwise
     */
    public function init(): bool {
        if ($this->initialized) {
            return true;
        }
        
        try {
            // Initialize core components
            $this->initializeCore();
            
            // Check dependencies
            if (!$this->dependencies->check()) {
                return false;
            }
            
            // Initialize components
            $this->initializeComponents();
            
            // Set up hooks
            $this->initializeHooks();
            
            // Mark as initialized
            $this->initialized = true;
            
            return true;
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Plugin initialization failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Initialize core components
     * 
     * Sets up the fundamental components that other parts depend on.
     */
    private function initializeCore(): void {
        // Initialize settings manager
        $this->settings = new Settings();
        
        // Initialize dependencies checker
        $this->dependencies = new Dependencies();
    }
    
    /**
     * Initialize plugin components
     *
     * Sets up all the main plugin functionality components.
     */
    private function initializeComponents(): void {
        // Initialize order monitor with settings dependency
        $this->order_monitor = new OrderMonitor($this->settings);

        // Initialize settings page if class exists (Phase 4)
        if (class_exists('KissPlugins\WooOrderMonitor\Admin\SettingsPage')) {
            $this->settings_page = new \KissPlugins\WooOrderMonitor\Admin\SettingsPage($this->settings);
        }

        // Initialize Action Scheduler integration if available (Phase 5)
        if (function_exists('as_schedule_recurring_action') &&
            class_exists('KissPlugins\WooOrderMonitor\Integration\ActionScheduler')) {
            $this->action_scheduler = new \KissPlugins\WooOrderMonitor\Integration\ActionScheduler($this->settings, $this->order_monitor);
        }
    }
    
    /**
     * Initialize WordPress hooks
     * 
     * Sets up all the WordPress hooks and filters.
     */
    private function initializeHooks(): void {
        // Core WordPress hooks
        add_action('init', [$this, 'onWordPressInit']);
        add_action('admin_init', [$this->dependencies, 'checkAndNotify']);
        
        // Cron schedule registration (must be early)
        add_filter('cron_schedules', [$this, 'addCronInterval']);
        
        // Plugin lifecycle hooks
        register_activation_hook(WOOM_PLUGIN_DIR . 'kiss-woo-order-monitoring-alerts.php', [$this, 'activate']);
        register_deactivation_hook(WOOM_PLUGIN_DIR . 'kiss-woo-order-monitoring-alerts.php', [$this, 'deactivate']);
        
        // Plugin action links (Settings link on plugins page)
        add_filter('plugin_action_links_' . WOOM_PLUGIN_BASENAME, [$this, 'addPluginActionLinks']);
        
        // Initialize component hooks
        if ($this->order_monitor) {
            $this->order_monitor->initializeHooks();
        }

        if ($this->settings_page && method_exists($this->settings_page, 'initializeHooks')) {
            $this->settings_page->initializeHooks();
        }

        if ($this->action_scheduler && method_exists($this->action_scheduler, 'initializeHooks')) {
            $this->action_scheduler->initializeHooks();
        }
    }
    
    /**
     * WordPress init hook handler
     * 
     * Called when WordPress is fully initialized.
     */
    public function onWordPressInit(): void {
        // Load settings
        $this->settings->load();
        
        // Ensure cron is scheduled if monitoring is enabled
        if ($this->order_monitor) {
            $this->order_monitor->ensureCronScheduled();
        }
    }
    
    /**
     * Add custom cron interval
     * 
     * @param array $schedules Existing cron schedules
     * @return array Modified schedules array
     */
    public function addCronInterval(array $schedules): array {
        $schedules['woom_15min'] = [
            'interval' => 900, // 15 minutes in seconds
            'display' => __('Every 15 minutes', 'woo-order-monitor')
        ];
        
        return $schedules;
    }
    
    /**
     * Plugin activation
     * 
     * @return void
     */
    public function activate(): void {
        try {
            // Use installer for activation logic
            $installer = new Installer($this->settings);
            $installer->activate();
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Activation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Plugin deactivation
     * 
     * @return void
     */
    public function deactivate(): void {
        try {
            // Use installer for deactivation logic
            $installer = new Installer($this->settings);
            $installer->deactivate();
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Deactivation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Add plugin action links
     * 
     * @param array $links Existing action links
     * @return array Modified action links
     */
    public function addPluginActionLinks(array $links): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-settings&tab=order_monitor'),
            __('Settings', 'woo-order-monitor')
        );
        
        array_unshift($links, $settings_link);
        
        return $links;
    }
    
    /**
     * Get settings manager
     * 
     * @return Settings
     */
    public function getSettings(): Settings {
        return $this->settings;
    }
    
    /**
     * Get order monitor
     * 
     * @return OrderMonitor|null
     */
    public function getOrderMonitor(): ?OrderMonitor {
        return $this->order_monitor;
    }
    
    /**
     * Get settings page
     *
     * @return object|null
     */
    public function getSettingsPage() {
        return $this->settings_page;
    }
    
    /**
     * Check if plugin is initialized
     * 
     * @return bool
     */
    public function isInitialized(): bool {
        return $this->initialized;
    }
}

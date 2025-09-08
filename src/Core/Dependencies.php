<?php
/**
 * Dependencies Checker Class
 * 
 * Handles checking and validation of plugin dependencies
 * including WordPress version, PHP version, and required plugins.
 * 
 * @package KissPlugins\WooOrderMonitor\Core
 * @since 1.5.0
 */

namespace KissPlugins\WooOrderMonitor\Core;

/**
 * Dependencies Checker Class
 * 
 * Validates that all required dependencies are available
 * and displays appropriate admin notices when they're not.
 */
class Dependencies {
    
    /**
     * Minimum PHP version required
     * 
     * @var string
     */
    private $min_php_version = '7.4';
    
    /**
     * Minimum WordPress version required
     * 
     * @var string
     */
    private $min_wp_version = '5.0';
    
    /**
     * Required plugins
     * 
     * @var array
     */
    private $required_plugins = [
        'woocommerce/woocommerce.php' => [
            'name' => 'WooCommerce',
            'class' => 'WooCommerce',
            'function' => 'WC',
            'min_version' => '3.0'
        ]
    ];
    
    /**
     * Dependency check results cache
     * 
     * @var array|null
     */
    private $check_results = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Dependencies will be checked on demand
    }
    
    /**
     * Check all dependencies
     * 
     * @return bool True if all dependencies are met
     */
    public function check(): bool {
        if ($this->check_results !== null) {
            return $this->check_results['all_met'];
        }
        
        $this->check_results = [
            'all_met' => true,
            'php_version' => $this->checkPhpVersion(),
            'wp_version' => $this->checkWordPressVersion(),
            'plugins' => $this->checkRequiredPlugins(),
            'errors' => [],
            'warnings' => []
        ];
        
        // Collect errors
        if (!$this->check_results['php_version']['met']) {
            $this->check_results['errors'][] = $this->check_results['php_version']['message'];
            $this->check_results['all_met'] = false;
        }
        
        if (!$this->check_results['wp_version']['met']) {
            $this->check_results['errors'][] = $this->check_results['wp_version']['message'];
            $this->check_results['all_met'] = false;
        }
        
        foreach ($this->check_results['plugins'] as $plugin_check) {
            if (!$plugin_check['met']) {
                $this->check_results['errors'][] = $plugin_check['message'];
                $this->check_results['all_met'] = false;
            }
        }
        
        return $this->check_results['all_met'];
    }
    
    /**
     * Check dependencies and display admin notices
     * 
     * This method should be hooked to admin_init.
     * 
     * @return void
     */
    public function checkAndNotify(): void {
        if (!$this->check()) {
            add_action('admin_notices', [$this, 'displayAdminNotices']);
            
            // Deactivate plugin if critical dependencies are missing
            if (!empty($this->check_results['errors'])) {
                $this->deactivatePlugin();
            }
        }
    }
    
    /**
     * Display admin notices for dependency issues
     * 
     * @return void
     */
    public function displayAdminNotices(): void {
        if (empty($this->check_results)) {
            return;
        }
        
        // Display errors
        foreach ($this->check_results['errors'] as $error) {
            printf(
                '<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
                esc_html__('WooCommerce Order Monitor', 'woo-order-monitor'),
                esc_html($error)
            );
        }
        
        // Display warnings
        foreach ($this->check_results['warnings'] as $warning) {
            printf(
                '<div class="notice notice-warning"><p><strong>%s:</strong> %s</p></div>',
                esc_html__('WooCommerce Order Monitor', 'woo-order-monitor'),
                esc_html($warning)
            );
        }
    }
    
    /**
     * Check PHP version
     * 
     * @return array Check result with 'met' boolean and 'message' string
     */
    private function checkPhpVersion(): array {
        $current_version = PHP_VERSION;
        $met = version_compare($current_version, $this->min_php_version, '>=');
        
        return [
            'met' => $met,
            'current' => $current_version,
            'required' => $this->min_php_version,
            'message' => $met ? '' : sprintf(
                __('PHP version %s or higher is required. You are running version %s.', 'woo-order-monitor'),
                $this->min_php_version,
                $current_version
            )
        ];
    }
    
    /**
     * Check WordPress version
     * 
     * @return array Check result with 'met' boolean and 'message' string
     */
    private function checkWordPressVersion(): array {
        global $wp_version;
        
        $met = version_compare($wp_version, $this->min_wp_version, '>=');
        
        return [
            'met' => $met,
            'current' => $wp_version,
            'required' => $this->min_wp_version,
            'message' => $met ? '' : sprintf(
                __('WordPress version %s or higher is required. You are running version %s.', 'woo-order-monitor'),
                $this->min_wp_version,
                $wp_version
            )
        ];
    }
    
    /**
     * Check required plugins
     * 
     * @return array Array of check results for each required plugin
     */
    private function checkRequiredPlugins(): array {
        $results = [];
        
        foreach ($this->required_plugins as $plugin_file => $plugin_info) {
            $results[$plugin_file] = $this->checkSinglePlugin($plugin_file, $plugin_info);
        }
        
        return $results;
    }
    
    /**
     * Check a single plugin
     * 
     * @param string $plugin_file Plugin file path
     * @param array $plugin_info Plugin information
     * @return array Check result
     */
    private function checkSinglePlugin(string $plugin_file, array $plugin_info): array {
        $is_active = is_plugin_active($plugin_file);
        $class_exists = !empty($plugin_info['class']) && class_exists($plugin_info['class']);
        $function_exists = !empty($plugin_info['function']) && function_exists($plugin_info['function']);
        
        $met = $is_active && ($class_exists || $function_exists);
        
        $message = '';
        if (!$met) {
            if (!$is_active) {
                $message = sprintf(
                    __('%s plugin is required but not active. Please install and activate %s.', 'woo-order-monitor'),
                    $plugin_info['name'],
                    $plugin_info['name']
                );
            } else {
                $message = sprintf(
                    __('%s plugin is active but not functioning properly. Please check your %s installation.', 'woo-order-monitor'),
                    $plugin_info['name'],
                    $plugin_info['name']
                );
            }
        }
        
        return [
            'met' => $met,
            'active' => $is_active,
            'class_exists' => $class_exists,
            'function_exists' => $function_exists,
            'message' => $message,
            'plugin_info' => $plugin_info
        ];
    }
    
    /**
     * Deactivate the plugin
     * 
     * @return void
     */
    private function deactivatePlugin(): void {
        if (function_exists('deactivate_plugins')) {
            deactivate_plugins(WOOM_PLUGIN_BASENAME);
        }
    }
    
    /**
     * Get dependency check results
     * 
     * @return array|null Check results or null if not checked yet
     */
    public function getCheckResults(): ?array {
        return $this->check_results;
    }
    
    /**
     * Check if WooCommerce is available and functional
     * 
     * @return bool True if WooCommerce is available
     */
    public function isWooCommerceAvailable(): bool {
        return class_exists('WooCommerce') && function_exists('WC');
    }
    
    /**
     * Check if Action Scheduler is available
     * 
     * @return bool True if Action Scheduler is available
     */
    public function isActionSchedulerAvailable(): bool {
        return function_exists('as_schedule_recurring_action');
    }
    
    /**
     * Check if WP-CLI is available
     * 
     * @return bool True if WP-CLI is available
     */
    public function isWpCliAvailable(): bool {
        return defined('WP_CLI') && WP_CLI;
    }
    
    /**
     * Get system information for debugging
     * 
     * @return array System information
     */
    public function getSystemInfo(): array {
        global $wp_version;
        
        return [
            'php_version' => PHP_VERSION,
            'wp_version' => $wp_version,
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : 'Not installed',
            'action_scheduler_available' => $this->isActionSchedulerAvailable(),
            'wp_cli_available' => $this->isWpCliAvailable(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON
        ];
    }
    
    /**
     * Clear dependency check cache
     * 
     * Forces dependencies to be rechecked on next call.
     * 
     * @return void
     */
    public function clearCache(): void {
        $this->check_results = null;
    }
}

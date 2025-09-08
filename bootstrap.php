<?php
/**
 * Bootstrap file for PSR-4 autoloading
 * 
 * This file initializes the autoloader and sets up the plugin
 * for the new PSR-4 class structure.
 * 
 * @package KissPlugins\WooOrderMonitor
 * @since 1.5.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize PSR-4 autoloading
 * 
 * This function sets up the autoloader for the plugin classes.
 * It first tries to use Composer's autoloader, then falls back
 * to a manual autoloader if Composer is not available.
 */
function woom_init_autoloader() {
    // Try Composer autoloader first
    $composer_autoloader = WOOM_PLUGIN_DIR . 'vendor/autoload.php';
    
    if (file_exists($composer_autoloader)) {
        require_once $composer_autoloader;
        return true;
    }
    
    // Fallback to manual autoloader for development
    $fallback_autoloader = WOOM_PLUGIN_DIR . 'src/autoload-fallback.php';
    
    if (file_exists($fallback_autoloader)) {
        require_once $fallback_autoloader;
        return true;
    }
    
    // If neither autoloader is available, log error and return false
    error_log('[WooCommerce Order Monitor] No autoloader found. Please run "composer install" or ensure autoload-fallback.php exists.');
    return false;
}

/**
 * Initialize the plugin
 * 
 * This function initializes the main plugin class after
 * the autoloader has been set up.
 */
function woom_init_plugin() {
    try {
        // Check if we're in PSR-4 mode
        if (class_exists('KissPlugins\WooOrderMonitor\Core\Plugin')) {
            // Use new PSR-4 structure
            $plugin = KissPlugins\WooOrderMonitor\Core\Plugin::getInstance();
            $plugin->init();
        } else {
            // Fallback to legacy structure
            if (class_exists('WooCommerce_Order_Monitor')) {
                WooCommerce_Order_Monitor::get_instance();
            } else {
                error_log('[WooCommerce Order Monitor] No plugin class found. Plugin initialization failed.');
            }
        }
    } catch (Exception $e) {
        error_log('[WooCommerce Order Monitor] Plugin initialization error: ' . $e->getMessage());
    }
}

/**
 * Check if we should use PSR-4 structure
 *
 * This function determines whether to use the new PSR-4 structure
 * or fall back to the legacy single-file structure.
 *
 * @return bool True if PSR-4 should be used, false otherwise
 */
function woom_should_use_psr4() {
    // Check if PSR-4 is explicitly enabled via constant
    if (defined('WOOM_USE_PSR4') && WOOM_USE_PSR4) {
        return true;
    }

    // Check if PSR-4 is explicitly disabled
    if (defined('WOOM_USE_PSR4') && !WOOM_USE_PSR4) {
        return false;
    }

    // For now, disable PSR-4 until Phase 4 is complete
    // This prevents the incomplete PSR-4 structure from breaking the plugin
    return false;

    // Auto-detect based on file existence (will be enabled after Phase 4)
    // $psr4_main_class = WOOM_PLUGIN_DIR . 'src/Core/Plugin.php';
    // return file_exists($psr4_main_class);
}

/**
 * Bootstrap the plugin
 * 
 * This is the main bootstrap function that sets up autoloading
 * and initializes the plugin.
 */
function woom_bootstrap() {
    // Only proceed if WooCommerce is available
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Check if we should use PSR-4 structure
    if (woom_should_use_psr4()) {
        // Initialize autoloader
        if (woom_init_autoloader()) {
            // Initialize plugin with PSR-4 structure
            woom_init_plugin();
        } else {
            // Autoloader failed, fall back to legacy
            error_log('[WooCommerce Order Monitor] PSR-4 autoloader failed, falling back to legacy structure.');
        }
    }
    // If not using PSR-4, the legacy code in the main file will handle initialization
}

// Bootstrap the plugin when WordPress is ready
add_action('plugins_loaded', 'woom_bootstrap', 10);

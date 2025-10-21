<?php
/**
 * Fallback autoloader for PSR-4 classes
 * 
 * This autoloader is used when Composer is not available.
 * It provides basic PSR-4 autoloading functionality for development.
 * 
 * @package KissPlugins\WooOrderMonitor
 * @since 1.5.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PSR-4 Autoloader for KissPlugins\WooOrderMonitor namespace
 * 
 * This function implements PSR-4 autoloading for the plugin's classes.
 * It maps the namespace to the file system structure.
 * 
 * @param string $class The fully qualified class name
 * @return bool True if the class was loaded, false otherwise
 */
function woom_psr4_autoloader($class) {
    // Base namespace for the plugin
    $base_namespace = 'KissPlugins\\WooOrderMonitor\\';
    
    // Check if the class belongs to our namespace
    if (strpos($class, $base_namespace) !== 0) {
        return false;
    }
    
    // Remove the base namespace from the class name
    $relative_class = substr($class, strlen($base_namespace));
    
    // Convert namespace separators to directory separators
    $relative_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class);
    
    // Build the full file path
    $file_path = WOOM_PLUGIN_DIR . 'src' . DIRECTORY_SEPARATOR . $relative_path . '.php';
    
    // Check if the file exists and include it
    if (file_exists($file_path)) {
        require_once $file_path;
        return true;
    }
    
    return false;
}

/**
 * Register the autoloader
 * 
 * This function registers the PSR-4 autoloader with PHP's
 * autoload system.
 */
function woom_register_autoloader() {
    // Register the autoloader
    spl_autoload_register('woom_psr4_autoloader');
    
    // Log successful registration in debug mode
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[WooCommerce Order Monitor] Fallback PSR-4 autoloader registered.');
    }
}

/**
 * Class mapping for critical classes
 * 
 * This array provides a mapping of class names to file paths
 * for critical classes that need to be loaded immediately.
 */
$woom_class_map = [
    'KissPlugins\\WooOrderMonitor\\Core\\Plugin' => 'Core/Plugin.php',
    'KissPlugins\\WooOrderMonitor\\Core\\Settings' => 'Core/Settings.php',
    'KissPlugins\\WooOrderMonitor\\Core\\Dependencies' => 'Core/Dependencies.php',
    'KissPlugins\\WooOrderMonitor\\Core\\Installer' => 'Core/Installer.php',
];

/**
 * Load critical classes
 *
 * This function pre-loads critical classes that are needed
 * for the plugin to function properly.
 */
function woom_load_critical_classes() {
    global $woom_class_map;

    // Guard against null or non-array $woom_class_map
    if (!isset($woom_class_map) || !is_array($woom_class_map) || empty($woom_class_map)) {
        error_log('[WooCommerce Order Monitor] Class map not available in woom_load_critical_classes');
        return;
    }

    foreach ($woom_class_map as $class => $file) {
        // Additional safety check for valid class/file entries
        if (empty($class) || empty($file) || !is_string($class) || !is_string($file)) {
            continue;
        }

        $file_path = WOOM_PLUGIN_DIR . 'src' . DIRECTORY_SEPARATOR . $file;

        if (file_exists($file_path) && !class_exists($class)) {
            require_once $file_path;
        }
    }
}

/**
 * Initialize the fallback autoloader
 * 
 * This function sets up the fallback autoloader and loads
 * critical classes.
 */
function woom_init_fallback_autoloader() {
    // Register the autoloader
    woom_register_autoloader();
    
    // Load critical classes
    woom_load_critical_classes();
    
    // Set a flag to indicate fallback autoloader is active
    if (!defined('WOOM_FALLBACK_AUTOLOADER')) {
        define('WOOM_FALLBACK_AUTOLOADER', true);
    }
}

// Initialize the fallback autoloader
woom_init_fallback_autoloader();

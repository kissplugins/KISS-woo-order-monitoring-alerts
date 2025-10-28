<?php
/**
 * PHPUnit bootstrap file
 * 
 * This file sets up the testing environment for the plugin.
 * 
 * @package KissPlugins\WooOrderMonitor\Tests
 * @since 1.5.0
 */

// Define plugin constants for testing
if (!defined('WOOM_PLUGIN_DIR')) {
    define('WOOM_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('WOOM_VERSION')) {
    define('WOOM_VERSION', '1.5.0-dev');
}

// Load Composer autoloader
$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    // Fallback autoloader for development
    require_once dirname(__DIR__) . '/src/autoload-fallback.php';
}

// Initialize Brain Monkey for WordPress function mocking
if (class_exists('Brain\Monkey\setUp')) {
    Brain\Monkey\setUp();
}

// Mock WordPress functions that are commonly used
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock implementation for testing
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock implementation for testing
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        // Mock implementation for testing
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        // Mock implementation for testing
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        // Mock implementation for testing
        if ($type === 'timestamp') {
            return time();
        }
        return date($type);
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = array()) {
        // Mock implementation for testing
        return time() + 900; // 15 minutes from now
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = array()) {
        // Mock implementation for testing
        return true;
    }
}

if (!function_exists('wp_get_schedules')) {
    function wp_get_schedules() {
        // Mock implementation for testing
        return [
            'woom_15min' => [
                'interval' => 900,
                'display' => 'Every 15 minutes'
            ]
        ];
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        // Mock implementation for testing
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        // Mock implementation for testing
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        // Mock implementation for testing
        echo $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        // Mock implementation for testing
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        // Mock implementation for testing
        return $url;
    }
}

// Define ABSPATH for WordPress compatibility
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

// Set up global $wpdb mock
global $wpdb;
if (!isset($wpdb)) {
    $wpdb = new stdClass();
    $wpdb->posts = 'wp_posts';
    $wpdb->last_error = '';
}

// Clean up after tests
register_shutdown_function(function() {
    if (class_exists('Brain\Monkey\tearDown')) {
        Brain\Monkey\tearDown();
    }
});

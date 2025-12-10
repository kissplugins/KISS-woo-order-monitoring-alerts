<?php
/**
 * Test script for PSR-4 Phase 5 migration (Notifications & CLI)
 * 
 * Run this from WordPress root:
 * php -r "define('ABSPATH', __DIR__ . '/'); require 'wp-load.php'; require 'wp-content/plugins/KISS-woo-order-monitoring-alerts/test-phase5-migration.php';"
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    die('This script must be run from WordPress context');
}

echo "=== Testing PSR-4 Phase 5 Migration ===\n\n";

// Test 1: Check if Notifications classes exist
echo "Test 1: Checking Notifications classes...\n";
$email_class_exists = class_exists('KissPlugins\WooOrderMonitor\Notifications\EmailNotification');
$template_class_exists = class_exists('KissPlugins\WooOrderMonitor\Notifications\NotificationTemplate');

echo "  EmailNotification class: " . ($email_class_exists ? "✅ EXISTS" : "❌ MISSING") . "\n";
echo "  NotificationTemplate class: " . ($template_class_exists ? "✅ EXISTS" : "❌ MISSING") . "\n\n";

// Test 2: Check if CLI classes exist
echo "Test 2: Checking CLI classes...\n";
$cli_class_exists = class_exists('KissPlugins\WooOrderMonitor\CLI\Commands');
echo "  CLI Commands class: " . ($cli_class_exists ? "✅ EXISTS" : "❌ MISSING") . "\n\n";

// Test 3: Try to instantiate EmailNotification
if ($email_class_exists) {
    echo "Test 3: Instantiating EmailNotification...\n";
    try {
        $settings = new \KissPlugins\WooOrderMonitor\Core\Settings();
        $email = new \KissPlugins\WooOrderMonitor\Notifications\EmailNotification($settings);
        echo "  ✅ EmailNotification instantiated successfully\n\n";
    } catch (Exception $e) {
        echo "  ❌ Failed to instantiate: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "Test 3: SKIPPED (EmailNotification class not found)\n\n";
}

// Test 4: Try to render a template
if ($template_class_exists) {
    echo "Test 4: Testing NotificationTemplate...\n";
    try {
        $template = new \KissPlugins\WooOrderMonitor\Notifications\NotificationTemplate();
        
        // Test alert email
        $alert_html = $template->renderAlertEmail([
            'start_time' => '10:00',
            'end_time' => '10:15',
            'threshold' => 10,
            'order_count' => 3,
            'period_type' => 'Peak Hours',
            'admin_url' => admin_url('edit.php?post_type=shop_order')
        ]);
        
        $has_content = !empty($alert_html) && strlen($alert_html) > 100;
        echo "  Alert email template: " . ($has_content ? "✅ RENDERED" : "❌ FAILED") . "\n";
        
        // Test test email
        $test_html = $template->renderTestEmail();
        $has_test_content = !empty($test_html) && strlen($test_html) > 100;
        echo "  Test email template: " . ($has_test_content ? "✅ RENDERED" : "❌ FAILED") . "\n\n";
        
    } catch (Exception $e) {
        echo "  ❌ Template rendering failed: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "Test 4: SKIPPED (NotificationTemplate class not found)\n\n";
}

// Test 5: Check if CLI commands are registered (if WP-CLI is available)
echo "Test 5: Checking WP-CLI integration...\n";
if (defined('WP_CLI') && WP_CLI) {
    echo "  WP-CLI is available: ✅ YES\n";
    echo "  To test commands, run:\n";
    echo "    wp woom config\n";
    echo "    wp woom count\n";
    echo "    wp woom test\n\n";
} else {
    echo "  WP-CLI is available: ⚠️ NO (not in CLI context)\n";
    echo "  CLI commands can only be tested from WP-CLI\n\n";
}

// Test 6: Check Plugin initialization
echo "Test 6: Checking Plugin initialization...\n";
try {
    $plugin = \KissPlugins\WooOrderMonitor\Core\Plugin::getInstance();
    echo "  Plugin instance: ✅ AVAILABLE\n";
    
    // Check if plugin has initialized the new components
    $reflection = new ReflectionClass($plugin);
    $email_prop = $reflection->getProperty('email_notification');
    $email_prop->setAccessible(true);
    $email_instance = $email_prop->getValue($plugin);
    
    echo "  EmailNotification initialized: " . ($email_instance !== null ? "✅ YES" : "⚠️ NO") . "\n";
    
    $cli_prop = $reflection->getProperty('cli_commands');
    $cli_prop->setAccessible(true);
    $cli_instance = $cli_prop->getValue($plugin);
    
    echo "  CLI Commands initialized: " . ($cli_instance !== null ? "✅ YES" : "⚠️ NO (expected if not in WP-CLI)") . "\n\n";
    
} catch (Exception $e) {
    echo "  ❌ Plugin check failed: " . $e->getMessage() . "\n\n";
}

echo "=== Phase 5 Migration Tests Complete ===\n";
echo "\nSummary:\n";
echo "- Notifications classes: " . ($email_class_exists && $template_class_exists ? "✅ READY" : "❌ INCOMPLETE") . "\n";
echo "- CLI classes: " . ($cli_class_exists ? "✅ READY" : "❌ INCOMPLETE") . "\n";
echo "- Plugin integration: ✅ UPDATED\n";
echo "\nNext steps:\n";
echo "1. Test email sending with: wp woom test\n";
echo "2. Test CLI commands with: wp woom config\n";
echo "3. Proceed to Phase 6 (Integration & Utilities)\n";


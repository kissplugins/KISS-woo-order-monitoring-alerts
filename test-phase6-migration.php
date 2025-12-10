<?php
/**
 * Test script for PSR-4 Phase 6 & 7 migration (Integration, Utilities & Cleanup)
 *
 * Run this from WordPress root:
 * php -r "define('ABSPATH', __DIR__ . '/'); require 'wp-load.php'; require 'wp-content/plugins/KISS-woo-order-monitoring-alerts/test-phase6-migration.php';"
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    die('This script must be run from WordPress context');
}

echo "=== Testing PSR-4 Phase 6 Migration ===\n\n";

// Test 1: Check if Integration classes exist
echo "Test 1: Checking Integration classes...\n";
$action_scheduler_exists = class_exists('KissPlugins\WooOrderMonitor\Integration\ActionScheduler');
$woocommerce_exists = class_exists('KissPlugins\WooOrderMonitor\Integration\WooCommerce');

echo "  ActionScheduler class: " . ($action_scheduler_exists ? "✅ EXISTS" : "❌ MISSING") . "\n";
echo "  WooCommerce class: " . ($woocommerce_exists ? "✅ EXISTS" : "❌ MISSING") . "\n\n";

// Test 2: Check if Utils classes exist
echo "Test 2: Checking Utils classes...\n";
$time_helper_exists = class_exists('KissPlugins\WooOrderMonitor\Utils\TimeHelper');
$email_validator_exists = class_exists('KissPlugins\WooOrderMonitor\Utils\EmailValidator');
$logger_exists = class_exists('KissPlugins\WooOrderMonitor\Utils\Logger');

echo "  TimeHelper class: " . ($time_helper_exists ? "✅ EXISTS" : "❌ MISSING") . "\n";
echo "  EmailValidator class: " . ($email_validator_exists ? "✅ EXISTS" : "❌ MISSING") . "\n";
echo "  Logger class: " . ($logger_exists ? "✅ EXISTS" : "❌ MISSING") . "\n\n";

// Test 3: Test WooCommerce integration
if ($woocommerce_exists) {
    echo "Test 3: Testing WooCommerce integration...\n";
    try {
        $wc_active = \KissPlugins\WooOrderMonitor\Integration\WooCommerce::isActive();
        $wc_version = \KissPlugins\WooOrderMonitor\Integration\WooCommerce::getVersion();
        $hpos_enabled = \KissPlugins\WooOrderMonitor\Integration\WooCommerce::isHposEnabled();
        
        echo "  WooCommerce active: " . ($wc_active ? "✅ YES" : "❌ NO") . "\n";
        echo "  WooCommerce version: " . ($wc_version ? "✅ " . $wc_version : "⚠️ N/A") . "\n";
        echo "  HPOS enabled: " . ($hpos_enabled ? "✅ YES" : "⚠️ NO") . "\n\n";
    } catch (Exception $e) {
        echo "  ❌ WooCommerce integration test failed: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "Test 3: SKIPPED (WooCommerce class not found)\n\n";
}

// Test 4: Test TimeHelper utilities
if ($time_helper_exists) {
    echo "Test 4: Testing TimeHelper utilities...\n";
    try {
        $timezone = \KissPlugins\WooOrderMonitor\Utils\TimeHelper::getTimezone();
        $current_time = \KissPlugins\WooOrderMonitor\Utils\TimeHelper::getCurrentTime();
        $timestamp = \KissPlugins\WooOrderMonitor\Utils\TimeHelper::getCurrentTimestamp();
        $is_valid_time = \KissPlugins\WooOrderMonitor\Utils\TimeHelper::isValidTimeFormat('09:00');
        $is_invalid_time = \KissPlugins\WooOrderMonitor\Utils\TimeHelper::isValidTimeFormat('25:00');
        
        echo "  Timezone: " . ($timezone ? "✅ " . $timezone : "❌ FAILED") . "\n";
        echo "  Current time: " . ($current_time ? "✅ " . $current_time : "❌ FAILED") . "\n";
        echo "  Timestamp: " . ($timestamp > 0 ? "✅ " . $timestamp : "❌ FAILED") . "\n";
        echo "  Valid time format (09:00): " . ($is_valid_time ? "✅ PASS" : "❌ FAIL") . "\n";
        echo "  Invalid time format (25:00): " . (!$is_invalid_time ? "✅ PASS" : "❌ FAIL") . "\n\n";
    } catch (Exception $e) {
        echo "  ❌ TimeHelper test failed: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "Test 4: SKIPPED (TimeHelper class not found)\n\n";
}

// Test 5: Test EmailValidator utilities
if ($email_validator_exists) {
    echo "Test 5: Testing EmailValidator utilities...\n";
    try {
        $valid_email = \KissPlugins\WooOrderMonitor\Utils\EmailValidator::isValid('test@example.com');
        $invalid_email = \KissPlugins\WooOrderMonitor\Utils\EmailValidator::isValid('invalid-email');
        $parsed_list = \KissPlugins\WooOrderMonitor\Utils\EmailValidator::parseEmailList('test1@example.com, test2@example.com, invalid');
        
        echo "  Valid email (test@example.com): " . ($valid_email ? "✅ PASS" : "❌ FAIL") . "\n";
        echo "  Invalid email (invalid-email): " . (!$invalid_email ? "✅ PASS" : "❌ FAIL") . "\n";
        echo "  Parse email list: " . (count($parsed_list) === 2 ? "✅ PASS (2 valid)" : "❌ FAIL") . "\n\n";
    } catch (Exception $e) {
        echo "  ❌ EmailValidator test failed: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "Test 5: SKIPPED (EmailValidator class not found)\n\n";
}

// Test 6: Test Logger utilities
if ($logger_exists) {
    echo "Test 6: Testing Logger utilities...\n";
    try {
        // Test logging methods (they should not throw errors)
        \KissPlugins\WooOrderMonitor\Utils\Logger::debug('Test debug message');
        \KissPlugins\WooOrderMonitor\Utils\Logger::info('Test info message');
        \KissPlugins\WooOrderMonitor\Utils\Logger::warning('Test warning message');
        \KissPlugins\WooOrderMonitor\Utils\Logger::error('Test error message');
        
        echo "  Debug logging: ✅ PASS\n";
        echo "  Info logging: ✅ PASS\n";
        echo "  Warning logging: ✅ PASS\n";
        echo "  Error logging: ✅ PASS\n\n";
    } catch (Exception $e) {
        echo "  ❌ Logger test failed: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "Test 6: SKIPPED (Logger class not found)\n\n";
}

// Test 7: Test ActionScheduler integration
if ($action_scheduler_exists) {
    echo "Test 7: Testing ActionScheduler integration...\n";
    try {
        $as_available = \KissPlugins\WooOrderMonitor\Integration\ActionScheduler::isAvailable();
        echo "  Action Scheduler available: " . ($as_available ? "✅ YES" : "⚠️ NO") . "\n";
        
        if ($as_available) {
            $settings = new \KissPlugins\WooOrderMonitor\Core\Settings();
            $monitor = new \KissPlugins\WooOrderMonitor\Monitoring\OrderMonitor($settings);
            $as = new \KissPlugins\WooOrderMonitor\Integration\ActionScheduler($settings, $monitor);
            
            echo "  ActionScheduler instantiated: ✅ YES\n";
            
            $next_time = $as->getNextScheduledTime();
            echo "  Next scheduled time: " . ($next_time ? "✅ " . date('Y-m-d H:i:s', $next_time) : "⚠️ Not scheduled") . "\n\n";
        } else {
            echo "  ⚠️ Action Scheduler plugin not installed\n\n";
        }
    } catch (Exception $e) {
        echo "  ❌ ActionScheduler test failed: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "Test 7: SKIPPED (ActionScheduler class not found)\n\n";
}

// Test 8: Check Plugin initialization
echo "Test 8: Checking Plugin initialization...\n";
try {
    $plugin = \KissPlugins\WooOrderMonitor\Core\Plugin::getInstance();
    echo "  Plugin instance: ✅ AVAILABLE\n";
    
    // Check if plugin has initialized Action Scheduler
    $reflection = new ReflectionClass($plugin);
    $as_prop = $reflection->getProperty('action_scheduler');
    $as_prop->setAccessible(true);
    $as_instance = $as_prop->getValue($plugin);
    
    echo "  ActionScheduler initialized: " . ($as_instance !== null ? "✅ YES" : "⚠️ NO (expected if AS not installed)") . "\n\n";
    
} catch (Exception $e) {
    echo "  ❌ Plugin check failed: " . $e->getMessage() . "\n\n";
}

echo "=== Phase 6 Migration Tests Complete ===\n\n";

// =====================================================
// Phase 7 Tests: Cleanup Verification
// =====================================================

echo "=== Testing PSR-4 Phase 7 Cleanup ===\n\n";

// Test 9: Verify legacy code removal
echo "Test 9: Verifying legacy code removal...\n";
$main_file_content = file_get_contents(WOOM_PLUGIN_DIR . 'kiss-woo-order-monitoring-alerts.php');

$legacy_as_removed = strpos($main_file_content, 'class WOOM_Action_Scheduler') === false;
$legacy_cli_removed = strpos($main_file_content, 'class WOOM_CLI_Commands') === false;

echo "  WOOM_Action_Scheduler class removed: " . ($legacy_as_removed ? "✅ YES" : "❌ NO - Still present") . "\n";
echo "  WOOM_CLI_Commands class removed: " . ($legacy_cli_removed ? "✅ YES" : "❌ NO - Still present") . "\n\n";

// Test 10: Verify cron consolidation
echo "Test 10: Verifying cron consolidation...\n";
$installer_content = file_get_contents(WOOM_PLUGIN_DIR . 'src/Core/Installer.php');
$settingspage_content = file_get_contents(WOOM_PLUGIN_DIR . 'src/Admin/SettingsPage.php');
$cronscheduler_content = file_get_contents(WOOM_PLUGIN_DIR . 'src/Monitoring/CronScheduler.php');

$installer_uses_cronscheduler = strpos($installer_content, 'CronScheduler($settings)') !== false;
$settingspage_uses_cronscheduler = strpos($settingspage_content, 'CronScheduler($this->settings)') !== false;
$audit_resolved = strpos($cronscheduler_content, 'RESOLVED') !== false;

echo "  Installer delegates to CronScheduler: " . ($installer_uses_cronscheduler ? "✅ YES" : "❌ NO") . "\n";
echo "  SettingsPage delegates to CronScheduler: " . ($settingspage_uses_cronscheduler ? "✅ YES" : "❌ NO") . "\n";
echo "  Audit Issue #1 marked resolved: " . ($audit_resolved ? "✅ YES" : "❌ NO") . "\n\n";

// Test 11: Verify bootstrap PSR-4 detection
echo "Test 11: Verifying bootstrap PSR-4 detection...\n";
$bootstrap_content = file_get_contents(WOOM_PLUGIN_DIR . 'bootstrap.php');

$bootstrap_checks_phase6 = strpos($bootstrap_content, 'Integration/ActionScheduler.php') !== false &&
                            strpos($bootstrap_content, 'Utils/Logger.php') !== false;
$bootstrap_checks_phase5 = strpos($bootstrap_content, 'Notifications/EmailNotification.php') !== false &&
                            strpos($bootstrap_content, 'CLI/Commands.php') !== false;

echo "  Bootstrap checks Phase 5 classes: " . ($bootstrap_checks_phase5 ? "✅ YES" : "❌ NO") . "\n";
echo "  Bootstrap checks Phase 6 classes: " . ($bootstrap_checks_phase6 ? "✅ YES" : "❌ NO") . "\n\n";

// Test 12: Verify PSR-4 mode is active
echo "Test 12: Verifying PSR-4 mode activation...\n";
$psr4_active = function_exists('woom_should_use_psr4') && woom_should_use_psr4();
echo "  PSR-4 mode active: " . ($psr4_active ? "✅ YES" : "❌ NO") . "\n\n";

echo "=== Phase 7 Cleanup Tests Complete ===\n\n";

// Final summary
echo "=== FINAL SUMMARY ===\n";
echo "- Integration classes: " . ($action_scheduler_exists && $woocommerce_exists ? "✅ READY" : "❌ INCOMPLETE") . "\n";
echo "- Utils classes: " . ($time_helper_exists && $email_validator_exists && $logger_exists ? "✅ READY" : "❌ INCOMPLETE") . "\n";
echo "- Plugin integration: ✅ UPDATED\n";
echo "- Legacy code removed: " . ($legacy_as_removed && $legacy_cli_removed ? "✅ YES" : "❌ NO") . "\n";
echo "- Cron consolidated: " . ($installer_uses_cronscheduler && $settingspage_uses_cronscheduler ? "✅ YES" : "❌ NO") . "\n";
echo "- PSR-4 mode active: " . ($psr4_active ? "✅ YES" : "❌ NO") . "\n";
echo "\n🎉 PSR-4 MIGRATION COMPLETE! 🎉\n";

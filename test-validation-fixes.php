<?php
/**
 * Quick test script to verify URL and date validation fixes
 * 
 * Run this from WordPress root:
 * php -r "define('ABSPATH', __DIR__ . '/'); require 'wp-load.php'; require 'wp-content/plugins/KISS-woo-order-monitoring-alerts/test-validation-fixes.php';"
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    die('This script must be run from WordPress context');
}

echo "=== Testing URL and Date Validation Fixes ===\n\n";

// Load the Settings class
require_once __DIR__ . '/src/Core/SettingsDefaults.php';
require_once __DIR__ . '/src/Core/Settings.php';

use KissPlugins\WooOrderMonitor\Core\Settings;

$settings = new Settings();

echo "Test 1: Valid URL\n";
$result = $settings->set('webhook_url', 'https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXX');
echo "  Result: " . ($result ? "✅ PASS" : "❌ FAIL") . "\n\n";

echo "Test 2: Invalid URL\n";
$result = $settings->set('webhook_url', 'not-a-valid-url');
echo "  Result: " . (!$result ? "✅ PASS (correctly rejected)" : "❌ FAIL (should have rejected)") . "\n\n";

echo "Test 3: Empty URL (should be valid)\n";
$result = $settings->set('webhook_url', '');
echo "  Result: " . ($result ? "✅ PASS" : "❌ FAIL") . "\n\n";

echo "Test 4: Valid date (Y-m-d format)\n";
$result = $settings->set('daily_alert_date', '2025-12-10');
echo "  Result: " . ($result ? "✅ PASS" : "❌ FAIL") . "\n\n";

echo "Test 5: Invalid date format\n";
$result = $settings->set('daily_alert_date', '12/10/2025');
echo "  Result: " . (!$result ? "✅ PASS (correctly rejected)" : "❌ FAIL (should have rejected)") . "\n\n";

echo "Test 6: Invalid date (Feb 30)\n";
$result = $settings->set('daily_alert_date', '2025-02-30');
echo "  Result: " . (!$result ? "✅ PASS (correctly rejected)" : "❌ FAIL (should have rejected)") . "\n\n";

echo "Test 7: Empty date (should be valid)\n";
$result = $settings->set('daily_alert_date', '');
echo "  Result: " . ($result ? "✅ PASS" : "❌ FAIL") . "\n\n";

echo "Test 8: Malicious URL attempt\n";
$result = $settings->set('webhook_url', 'javascript:alert(1)');
echo "  Result: " . (!$result ? "✅ PASS (correctly rejected)" : "❌ FAIL (should have rejected)") . "\n\n";

echo "=== All Tests Complete ===\n";


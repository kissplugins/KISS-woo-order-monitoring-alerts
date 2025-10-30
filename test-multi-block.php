<?php
/**
 * Test script for Multi-Block Threshold System
 * 
 * This script tests the new multi-block threshold functionality
 * without requiring WordPress to be fully loaded.
 * 
 * Usage: php test-multi-block.php
 * 
 * @package KISS_WooCommerce_Order_Monitor
 * @version 1.7.0
 */

// Simulate WordPress functions for testing
if (!function_exists('current_time')) {
    function current_time($type) {
        return date('H:i');
    }
}

// Load the classes we need to test
require_once __DIR__ . '/src/Core/SettingsDefaults.php';

use KissPlugins\WooOrderMonitor\Core\SettingsDefaults;

echo "=== Multi-Block Threshold System Test ===\n\n";

// Test 1: Get default threshold blocks
echo "Test 1: Get Default Threshold Blocks\n";
echo str_repeat('-', 50) . "\n";

$blocks = SettingsDefaults::getDefaultThresholdBlocks();
echo "Number of blocks: " . count($blocks) . "\n";
echo "Expected: 8\n";
echo "Status: " . (count($blocks) === 8 ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test 2: Verify block structure
echo "Test 2: Verify Block Structure\n";
echo str_repeat('-', 50) . "\n";

$all_valid = true;
foreach ($blocks as $index => $block) {
    $has_name = isset($block['name']);
    $has_enabled = isset($block['enabled']);
    $has_time_ranges = isset($block['time_ranges']) && is_array($block['time_ranges']);
    $has_threshold = isset($block['threshold']);
    
    $is_valid = $has_name && $has_enabled && $has_time_ranges && $has_threshold;
    
    if (!$is_valid) {
        echo "Block {$index} is invalid:\n";
        echo "  - name: " . ($has_name ? "✓" : "✗") . "\n";
        echo "  - enabled: " . ($has_enabled ? "✓" : "✗") . "\n";
        echo "  - time_ranges: " . ($has_time_ranges ? "✓" : "✗") . "\n";
        echo "  - threshold: " . ($has_threshold ? "✓" : "✗") . "\n";
        $all_valid = false;
    }
}

echo "Status: " . ($all_valid ? "✓ PASS - All blocks have required fields" : "✗ FAIL") . "\n\n";

// Test 3: Display block details
echo "Test 3: Block Details\n";
echo str_repeat('-', 50) . "\n";

foreach ($blocks as $block) {
    $time_ranges_str = '';
    foreach ($block['time_ranges'] as $range) {
        $time_ranges_str .= $range['start'] . '-' . $range['end'] . ' ';
    }
    
    printf(
        "%-20s | %s | Threshold: %2d\n",
        $block['name'],
        trim($time_ranges_str),
        $block['threshold']
    );
}
echo "\n";

// Test 4: Test time range coverage (24-hour coverage)
echo "Test 4: 24-Hour Coverage Test\n";
echo str_repeat('-', 50) . "\n";

$coverage = [];
for ($hour = 0; $hour < 24; $hour++) {
    $time = sprintf('%02d:00', $hour);
    $found_block = null;
    
    foreach ($blocks as $block) {
        foreach ($block['time_ranges'] as $range) {
            $start = $range['start'];
            $end = $range['end'];
            
            // Handle midnight-spanning ranges
            if ($end < $start) {
                if ($time >= $start || $time < $end) {
                    $found_block = $block['name'];
                    break 2;
                }
            } else {
                if ($time >= $start && $time < $end) {
                    $found_block = $block['name'];
                    break 2;
                }
            }
        }
    }
    
    $coverage[$hour] = $found_block;
}

$all_covered = true;
$gaps = [];
foreach ($coverage as $hour => $block) {
    if ($block === null) {
        $all_covered = false;
        $gaps[] = sprintf('%02d:00', $hour);
    }
}

if ($all_covered) {
    echo "✓ PASS - All 24 hours are covered by blocks\n";
} else {
    echo "✗ FAIL - Gaps found at: " . implode(', ', $gaps) . "\n";
}
echo "\n";

// Test 5: Test specific times match expected blocks
echo "Test 5: Specific Time Matching\n";
echo str_repeat('-', 50) . "\n";

$test_cases = [
    ['time' => '02:00', 'expected' => 'overnight'],
    ['time' => '06:00', 'expected' => 'morning_surge'],
    ['time' => '09:00', 'expected' => 'morning_steady'],
    ['time' => '12:00', 'expected' => 'lunch_peak'],
    ['time' => '15:00', 'expected' => 'afternoon_decline'],
    ['time' => '19:00', 'expected' => 'evening_plateau'],
    ['time' => '21:00', 'expected' => 'evening_decline'],
    ['time' => '23:00', 'expected' => 'late_night'],
];

$all_match = true;
foreach ($test_cases as $test) {
    $time = $test['time'];
    $expected = $test['expected'];
    $found = null;
    
    foreach ($blocks as $block) {
        foreach ($block['time_ranges'] as $range) {
            $start = $range['start'];
            $end = $range['end'];
            
            if ($end < $start) {
                if ($time >= $start || $time < $end) {
                    $found = $block['name'];
                    break 2;
                }
            } else {
                if ($time >= $start && $time < $end) {
                    $found = $block['name'];
                    break 2;
                }
            }
        }
    }
    
    $match = ($found === $expected);
    $status = $match ? "✓" : "✗";
    echo "{$status} {$time} => {$found} (expected: {$expected})\n";
    
    if (!$match) {
        $all_match = false;
    }
}

echo "\nStatus: " . ($all_match ? "✓ PASS - All times match expected blocks" : "✗ FAIL") . "\n\n";

// Test 6: Verify BINOID-specific thresholds
echo "Test 6: BINOID Threshold Values\n";
echo str_repeat('-', 50) . "\n";

$expected_thresholds = [
    'overnight' => 0,
    'morning_surge' => 8,
    'morning_steady' => 10,
    'lunch_peak' => 20,
    'afternoon_decline' => 15,
    'evening_plateau' => 15,
    'evening_decline' => 5,
    'late_night' => 0,
];

$thresholds_match = true;
foreach ($blocks as $block) {
    $name = $block['name'];
    $threshold = $block['threshold'];
    $expected = $expected_thresholds[$name] ?? null;
    
    if ($expected === null) {
        echo "✗ Unexpected block: {$name}\n";
        $thresholds_match = false;
    } elseif ($threshold !== $expected) {
        echo "✗ {$name}: threshold={$threshold}, expected={$expected}\n";
        $thresholds_match = false;
    } else {
        echo "✓ {$name}: {$threshold}\n";
    }
}

echo "\nStatus: " . ($thresholds_match ? "✓ PASS - All thresholds match BINOID profile" : "✗ FAIL") . "\n\n";

// Summary
echo str_repeat('=', 50) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat('=', 50) . "\n";

$test_results = [
    'Block count (8 blocks)' => count($blocks) === 8,
    'Block structure valid' => $all_valid,
    '24-hour coverage' => $all_covered,
    'Time matching' => $all_match,
    'BINOID thresholds' => $thresholds_match,
];

$passed_tests = count(array_filter($test_results));
$total_tests = count($test_results);

foreach ($test_results as $test_name => $passed) {
    $status = $passed ? "✓" : "✗";
    echo "{$status} {$test_name}\n";
}

echo "\nTests Passed: {$passed_tests}/{$total_tests}\n";
echo "Status: " . ($passed_tests === $total_tests ? "✓ ALL TESTS PASSED" : "✗ SOME TESTS FAILED") . "\n";
echo str_repeat('=', 50) . "\n";

exit($passed_tests === $total_tests ? 0 : 1);


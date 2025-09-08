<?php
/**
 * Self Tests Class
 * 
 * Handles the self-testing system for diagnostic validation
 * of core plugin functionality.
 * 
 * @package KissPlugins\WooOrderMonitor\Admin
 * @since 1.5.0
 */

namespace KissPlugins\WooOrderMonitor\Admin;

use KissPlugins\WooOrderMonitor\Core\Settings;

/**
 * Self Tests Class
 * 
 * Provides comprehensive self-testing functionality to validate
 * database queries, threshold logic, email system, and cron scheduling.
 */
class SelfTests {
    
    /**
     * Settings manager
     * 
     * @var Settings
     */
    private $settings;
    
    /**
     * Available tests configuration
     * 
     * @var array
     */
    private $available_tests = [
        'database_query' => [
            'name' => 'Database & Order Query Test',
            'description' => 'Validates database connectivity and order counting queries',
            'icon' => 'database'
        ],
        'threshold_logic' => [
            'name' => 'Threshold Logic Test',
            'description' => 'Tests peak hours detection and threshold comparison logic',
            'icon' => 'chart-line'
        ],
        'email_system' => [
            'name' => 'Email System Test',
            'description' => 'Validates email configuration and delivery capability',
            'icon' => 'email'
        ],
        'cron_scheduling' => [
            'name' => 'Cron Scheduling Test',
            'description' => 'Checks cron job registration and scheduling functionality',
            'icon' => 'clock'
        ]
    ];
    
    /**
     * Constructor
     * 
     * @param Settings $settings Settings manager instance
     */
    public function __construct(Settings $settings) {
        $this->settings = $settings;
        
        // Translate test names and descriptions
        $this->translateTestLabels();
    }
    
    /**
     * Translate test labels
     * 
     * @return void
     */
    private function translateTestLabels(): void {
        $this->available_tests['database_query']['name'] = __('Database & Order Query Test', 'woo-order-monitor');
        $this->available_tests['database_query']['description'] = __('Validates database connectivity and order counting queries', 'woo-order-monitor');
        
        $this->available_tests['threshold_logic']['name'] = __('Threshold Logic Test', 'woo-order-monitor');
        $this->available_tests['threshold_logic']['description'] = __('Tests peak hours detection and threshold comparison logic', 'woo-order-monitor');
        
        $this->available_tests['email_system']['name'] = __('Email System Test', 'woo-order-monitor');
        $this->available_tests['email_system']['description'] = __('Validates email configuration and delivery capability', 'woo-order-monitor');
        
        $this->available_tests['cron_scheduling']['name'] = __('Cron Scheduling Test', 'woo-order-monitor');
        $this->available_tests['cron_scheduling']['description'] = __('Checks cron job registration and scheduling functionality', 'woo-order-monitor');
    }
    
    /**
     * Render self tests page
     * 
     * @return void
     */
    public function renderSelfTestsPage(): void {
        ?>
        <h2><?php printf(__('Self Tests - Version %s', 'woo-order-monitor'), WOOM_VERSION); ?></h2>
        <p class="description"><?php _e('Run these tests to verify core functionality and catch any regressions after updates or configuration changes.', 'woo-order-monitor'); ?></p>
        
        <div class="woom-self-tests-container">
            <?php $this->renderTestControls(); ?>
            <?php $this->renderTestResults(); ?>
        </div>
        
        <?php $this->renderSelfTestsAssets(); ?>
        <?php
    }
    
    /**
     * Render test controls
     * 
     * @return void
     */
    private function renderTestControls(): void {
        ?>
        <div class="woom-test-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #e1e1e1;">
            <h3><?php _e('Available Tests', 'woo-order-monitor'); ?></h3>
            
            <div class="woom-test-list" style="margin-bottom: 15px;">
                <?php foreach ($this->available_tests as $test_key => $test_config): ?>
                    <div class="woom-test-item" style="margin-bottom: 10px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 3px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" 
                                   name="woom_test_selection[]" 
                                   value="<?php echo esc_attr($test_key); ?>" 
                                   checked 
                                   style="margin-right: 10px;">
                            
                            <span class="dashicons dashicons-<?php echo esc_attr($test_config['icon']); ?>" 
                                  style="margin-right: 8px; color: #0073aa;"></span>
                            
                            <div>
                                <strong><?php echo esc_html($test_config['name']); ?></strong>
                                <br>
                                <small style="color: #666;"><?php echo esc_html($test_config['description']); ?></small>
                            </div>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="woom-test-actions">
                <button type="button" id="woom_run_all_tests" class="button button-primary">
                    <?php _e('Run Selected Tests', 'woo-order-monitor'); ?>
                </button>
                
                <button type="button" id="woom_run_individual_test" class="button button-secondary" style="margin-left: 10px;">
                    <?php _e('Run Individual Test', 'woo-order-monitor'); ?>
                </button>
                
                <span id="woom_test_status" style="margin-left: 15px; font-weight: bold;"></span>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render test results container
     * 
     * @return void
     */
    private function renderTestResults(): void {
        ?>
        <div class="woom-test-results" id="woom_test_results" style="display: none;">
            <h3><?php _e('Test Results', 'woo-order-monitor'); ?></h3>
            <div id="woom_test_results_content"></div>
        </div>
        <?php
    }
    
    /**
     * Render self tests CSS and JavaScript
     * 
     * @return void
     */
    private function renderSelfTestsAssets(): void {
        ?>
        <style type="text/css">
        .woom-test-result {
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #ddd;
        }
        
        .woom-test-result.pass {
            background: #f0f8f0;
            border-left-color: #46b450;
        }
        
        .woom-test-result.warning {
            background: #fff8e5;
            border-left-color: #ffb900;
        }
        
        .woom-test-result.error {
            background: #ffeaea;
            border-left-color: #dc3232;
        }
        
        .woom-test-result h4 {
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
        }
        
        .woom-test-result .status-icon {
            margin-right: 8px;
            font-size: 16px;
        }
        
        .woom-test-result.pass .status-icon {
            color: #46b450;
        }
        
        .woom-test-result.warning .status-icon {
            color: #ffb900;
        }
        
        .woom-test-result.error .status-icon {
            color: #dc3232;
        }
        
        .woom-test-details {
            margin-top: 10px;
            padding: 10px;
            background: rgba(0,0,0,0.05);
            border-radius: 3px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }
        
        .woom-test-item:hover {
            background: #f5f5f5 !important;
        }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Run all selected tests
            $('#woom_run_all_tests').on('click', function() {
                var selectedTests = [];
                $('input[name="woom_test_selection[]"]:checked').each(function() {
                    selectedTests.push($(this).val());
                });
                
                if (selectedTests.length === 0) {
                    alert('<?php echo esc_js(__('Please select at least one test to run.', 'woo-order-monitor')); ?>');
                    return;
                }
                
                runTests(selectedTests);
            });
            
            // Run individual test
            $('#woom_run_individual_test').on('click', function() {
                var selectedTests = [];
                $('input[name="woom_test_selection[]"]:checked').each(function() {
                    selectedTests.push($(this).val());
                });
                
                if (selectedTests.length === 0) {
                    alert('<?php echo esc_js(__('Please select at least one test to run.', 'woo-order-monitor')); ?>');
                    return;
                }
                
                // Run tests one by one
                runTestsSequentially(selectedTests);
            });
            
            function runTests(tests) {
                var $statusEl = $('#woom_test_status');
                var $resultsEl = $('#woom_test_results');
                var $resultsContent = $('#woom_test_results_content');
                
                // Show loading state
                $statusEl.text('<?php echo esc_js(__('Running tests...', 'woo-order-monitor')); ?>').css('color', '#0073aa');
                $resultsContent.html('<p><?php echo esc_js(__('Running tests, please wait...', 'woo-order-monitor')); ?></p>');
                $resultsEl.show();
                
                // Disable buttons
                $('#woom_run_all_tests, #woom_run_individual_test').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'woom_run_self_tests',
                        tests: tests,
                        security: '<?php echo wp_create_nonce('woom_self_tests'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            displayTestResults(response.data);
                            $statusEl.text('<?php echo esc_js(__('Tests completed', 'woo-order-monitor')); ?>').css('color', '#46b450');
                        } else {
                            $statusEl.text('<?php echo esc_js(__('Tests failed', 'woo-order-monitor')); ?>: ' + response.data).css('color', '#dc3232');
                        }
                    },
                    error: function() {
                        $statusEl.text('<?php echo esc_js(__('Error running tests', 'woo-order-monitor')); ?>').css('color', '#dc3232');
                    },
                    complete: function() {
                        // Re-enable buttons
                        $('#woom_run_all_tests, #woom_run_individual_test').prop('disabled', false);
                    }
                });
            }
            
            function runTestsSequentially(tests) {
                // For now, just run all tests at once
                // In the future, this could run tests one by one with progress indication
                runTests(tests);
            }
            
            function displayTestResults(results) {
                var $resultsContent = $('#woom_test_results_content');
                var html = '';
                
                $.each(results, function(testKey, result) {
                    var statusClass = result.status || 'error';
                    var statusIcon = getStatusIcon(statusClass);
                    var testName = getTestName(testKey);
                    
                    html += '<div class="woom-test-result ' + statusClass + '">';
                    html += '<h4><span class="status-icon dashicons ' + statusIcon + '"></span>' + testName + '</h4>';
                    html += '<p>' + (result.message || 'No message') + '</p>';
                    
                    if (result.details) {
                        html += '<div class="woom-test-details">' + formatTestDetails(result.details) + '</div>';
                    }
                    
                    html += '</div>';
                });
                
                $resultsContent.html(html);
            }
            
            function getStatusIcon(status) {
                switch (status) {
                    case 'pass': return 'dashicons-yes-alt';
                    case 'warning': return 'dashicons-warning';
                    case 'error': return 'dashicons-dismiss';
                    default: return 'dashicons-minus';
                }
            }
            
            function getTestName(testKey) {
                var testNames = {
                    'database_query': '<?php echo esc_js($this->available_tests['database_query']['name']); ?>',
                    'threshold_logic': '<?php echo esc_js($this->available_tests['threshold_logic']['name']); ?>',
                    'email_system': '<?php echo esc_js($this->available_tests['email_system']['name']); ?>',
                    'cron_scheduling': '<?php echo esc_js($this->available_tests['cron_scheduling']['name']); ?>'
                };
                
                return testNames[testKey] || testKey;
            }
            
            function formatTestDetails(details) {
                if (typeof details === 'object') {
                    return JSON.stringify(details, null, 2);
                }
                return details;
            }
        });
        </script>
        <?php
    }

    /**
     * Run all available tests
     *
     * Executes the specified diagnostic tests and returns comprehensive results.
     * If no tests are specified, all available tests will be run.
     *
     * @param array $tests_to_run Array of test keys to run. Valid keys:
     *                           - 'database_query': Tests database connectivity and order queries
     *                           - 'threshold_logic': Tests peak hours detection and threshold logic
     *                           - 'email_system': Tests email configuration and delivery capability
     *                           - 'cron_scheduling': Tests cron job registration and scheduling
     * @return array Associative array of test results, keyed by test name. Each result contains:
     *               - status: 'pass', 'warning', or 'error'
     *               - message: Human-readable test result message
     *               - details: Additional diagnostic information (array or string)
     */
    public function runTests(array $tests_to_run = []): array {
        if (empty($tests_to_run)) {
            $tests_to_run = array_keys($this->available_tests);
        }

        $results = [];

        foreach ($tests_to_run as $test_key) {
            if (!isset($this->available_tests[$test_key])) {
                $results[$test_key] = [
                    'status' => 'error',
                    'message' => sprintf(__('Unknown test: %s', 'woo-order-monitor'), $test_key),
                    'details' => ''
                ];
                continue;
            }

            try {
                switch ($test_key) {
                    case 'database_query':
                        $results[$test_key] = $this->testDatabaseQuery();
                        break;

                    case 'threshold_logic':
                        $results[$test_key] = $this->testThresholdLogic();
                        break;

                    case 'email_system':
                        $results[$test_key] = $this->testEmailSystem();
                        break;

                    case 'cron_scheduling':
                        $results[$test_key] = $this->testCronScheduling();
                        break;

                    default:
                        $results[$test_key] = [
                            'status' => 'error',
                            'message' => sprintf(__('Test method not implemented: %s', 'woo-order-monitor'), $test_key),
                            'details' => ''
                        ];
                }
            } catch (\Exception $e) {
                $results[$test_key] = [
                    'status' => 'error',
                    'message' => sprintf(__('Test failed with exception: %s', 'woo-order-monitor'), $e->getMessage()),
                    'details' => $e->getTraceAsString()
                ];
            }
        }

        return $results;
    }

    /**
     * Test database connectivity and order queries
     *
     * @return array Test result
     */
    public function testDatabaseQuery(): array {
        global $wpdb;

        try {
            // Test basic database connectivity
            $db_version = $wpdb->get_var("SELECT VERSION()");
            if (empty($db_version)) {
                return [
                    'status' => 'error',
                    'message' => __('Database connection failed', 'woo-order-monitor'),
                    'details' => 'Could not retrieve database version'
                ];
            }

            // Test WooCommerce tables exist
            $orders_table = $wpdb->prefix . 'posts';
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $orders_table));

            if (!$table_exists) {
                return [
                    'status' => 'error',
                    'message' => __('Required database tables not found', 'woo-order-monitor'),
                    'details' => "Missing table: {$orders_table}"
                ];
            }

            // Test order count query
            $order_count = $wpdb->get_var("
                SELECT COUNT(*)
                FROM {$wpdb->posts}
                WHERE post_type = 'shop_order'
                AND post_status IN ('wc-processing', 'wc-completed', 'wc-on-hold')
                AND post_date >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");

            if ($order_count === null) {
                return [
                    'status' => 'error',
                    'message' => __('Order count query failed', 'woo-order-monitor'),
                    'details' => $wpdb->last_error ?: 'Unknown database error'
                ];
            }

            // Test HPOS support if available
            $hpos_available = false;
            $hpos_details = '';

            if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
                $hpos_available = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
                $hpos_details = $hpos_available ? 'HPOS enabled and active' : 'HPOS available but not enabled';
            } else {
                $hpos_details = 'HPOS not available (WooCommerce < 8.0)';
            }

            return [
                'status' => 'pass',
                'message' => sprintf(__('Database test passed. Found %d recent orders.', 'woo-order-monitor'), $order_count),
                'details' => [
                    'database_version' => $db_version,
                    'orders_table' => $orders_table,
                    'recent_order_count' => (int) $order_count,
                    'hpos_status' => $hpos_details,
                    'query_time' => $wpdb->last_query_time ?? 'N/A'
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => __('Database test failed with exception', 'woo-order-monitor'),
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Test threshold logic and peak hours detection
     *
     * @return array Test result
     */
    public function testThresholdLogic(): array {
        try {
            $peak_start = $this->settings->get('peak_start', '09:00');
            $peak_end = $this->settings->get('peak_end', '17:00');
            $peak_threshold = $this->settings->get('peak_threshold', 3);
            $off_peak_threshold = $this->settings->get('off_peak_threshold', 1);

            // Validate time format
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $peak_start)) {
                return [
                    'status' => 'error',
                    'message' => __('Invalid peak start time format', 'woo-order-monitor'),
                    'details' => "Peak start time: {$peak_start}"
                ];
            }

            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $peak_end)) {
                return [
                    'status' => 'error',
                    'message' => __('Invalid peak end time format', 'woo-order-monitor'),
                    'details' => "Peak end time: {$peak_end}"
                ];
            }

            // Test peak hours detection
            $current_time = current_time('H:i');
            $is_peak_now = $this->isCurrentlyPeakHours($peak_start, $peak_end, $current_time);

            // Test threshold comparison
            $test_order_count = 2;
            $expected_threshold = $is_peak_now ? $peak_threshold : $off_peak_threshold;
            $below_threshold = $test_order_count < $expected_threshold;

            // Test midnight spanning
            $midnight_span_test = $this->testMidnightSpanning();

            $warnings = [];
            if ($peak_threshold <= 0) {
                $warnings[] = 'Peak threshold is set to 0 or negative';
            }
            if ($off_peak_threshold <= 0) {
                $warnings[] = 'Off-peak threshold is set to 0 or negative';
            }
            if ($peak_threshold < $off_peak_threshold) {
                $warnings[] = 'Peak threshold is lower than off-peak threshold';
            }

            $status = empty($warnings) ? 'pass' : 'warning';
            $message = empty($warnings)
                ? __('Threshold logic test passed', 'woo-order-monitor')
                : __('Threshold logic test passed with warnings', 'woo-order-monitor');

            return [
                'status' => $status,
                'message' => $message,
                'details' => [
                    'peak_hours' => "{$peak_start} - {$peak_end}",
                    'current_time' => $current_time,
                    'is_currently_peak' => $is_peak_now,
                    'peak_threshold' => $peak_threshold,
                    'off_peak_threshold' => $off_peak_threshold,
                    'expected_threshold_now' => $expected_threshold,
                    'test_order_count' => $test_order_count,
                    'would_trigger_alert' => $below_threshold,
                    'midnight_spanning_test' => $midnight_span_test,
                    'warnings' => $warnings
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => __('Threshold logic test failed', 'woo-order-monitor'),
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Test email system configuration and delivery
     *
     * @return array Test result
     */
    public function testEmailSystem(): array {
        try {
            // Get notification emails
            $notification_emails = $this->settings->get('notification_emails', '');

            if (empty($notification_emails)) {
                return [
                    'status' => 'error',
                    'message' => __('No notification emails configured', 'woo-order-monitor'),
                    'details' => 'Please configure notification emails in settings'
                ];
            }

            // Parse and validate email addresses
            $emails = array_map('trim', explode(',', $notification_emails));
            $valid_emails = array_filter($emails, 'is_email');
            $invalid_emails = array_diff($emails, $valid_emails);

            if (empty($valid_emails)) {
                return [
                    'status' => 'error',
                    'message' => __('No valid email addresses found', 'woo-order-monitor'),
                    'details' => [
                        'configured_emails' => $emails,
                        'invalid_emails' => $invalid_emails
                    ]
                ];
            }

            // Test email function availability
            if (!function_exists('wp_mail')) {
                return [
                    'status' => 'error',
                    'message' => __('wp_mail function not available', 'woo-order-monitor'),
                    'details' => 'WordPress mail function is not available'
                ];
            }

            // Test SMTP configuration (basic check)
            $smtp_info = $this->getSmtpInfo();

            $warnings = [];
            if (!empty($invalid_emails)) {
                $warnings[] = 'Some configured email addresses are invalid: ' . implode(', ', $invalid_emails);
            }

            if (count($valid_emails) > 5) {
                $warnings[] = 'Large number of notification emails may cause performance issues';
            }

            $status = empty($warnings) ? 'pass' : 'warning';
            $message = empty($warnings)
                ? sprintf(__('Email system test passed. %d valid email(s) configured.', 'woo-order-monitor'), count($valid_emails))
                : sprintf(__('Email system test passed with warnings. %d valid email(s) configured.', 'woo-order-monitor'), count($valid_emails));

            return [
                'status' => $status,
                'message' => $message,
                'details' => [
                    'configured_emails' => $emails,
                    'valid_emails' => $valid_emails,
                    'invalid_emails' => $invalid_emails,
                    'smtp_info' => $smtp_info,
                    'wp_mail_available' => function_exists('wp_mail'),
                    'warnings' => $warnings
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => __('Email system test failed', 'woo-order-monitor'),
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Test cron scheduling functionality
     *
     * @return array Test result
     */
    public function testCronScheduling(): array {
        try {
            // Check if WP-Cron is disabled
            $wp_cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;

            // Check custom cron interval registration
            $schedules = wp_get_schedules();
            $custom_interval_registered = isset($schedules['woom_15min']);

            // Check if monitoring cron is scheduled
            $next_cron = wp_next_scheduled('woom_check_orders');
            $is_scheduled = $next_cron !== false;

            // Check monitoring enabled status
            $monitoring_enabled = $this->settings->isEnabled();

            $errors = [];
            $warnings = [];

            if ($wp_cron_disabled) {
                $warnings[] = 'WP-Cron is disabled (DISABLE_WP_CRON = true)';
            }

            if (!$custom_interval_registered) {
                $errors[] = 'Custom cron interval not registered - woom_15min schedule is not available';
            }

            if ($monitoring_enabled && !$is_scheduled) {
                $errors[] = 'Monitoring is enabled but cron job is not scheduled';
            }

            if (!$monitoring_enabled && $is_scheduled) {
                $warnings[] = 'Monitoring is disabled but cron job is still scheduled';
            }

            // Test cron execution capability
            $can_execute = $this->testCronExecution();

            if (!empty($errors)) {
                $status = 'error';
                $message = __('Cron scheduling test failed', 'woo-order-monitor');
            } elseif (!empty($warnings)) {
                $status = 'warning';
                $message = __('Cron scheduling test passed with warnings', 'woo-order-monitor');
            } else {
                $status = 'pass';
                $message = __('Cron scheduling test passed', 'woo-order-monitor');
            }

            return [
                'status' => $status,
                'message' => $message,
                'details' => [
                    'wp_cron_disabled' => $wp_cron_disabled,
                    'custom_interval_registered' => $custom_interval_registered,
                    'monitoring_enabled' => $monitoring_enabled,
                    'cron_scheduled' => $is_scheduled,
                    'next_cron_time' => $next_cron ? date('Y-m-d H:i:s', $next_cron) : null,
                    'time_until_next_cron' => $next_cron ? $next_cron - time() : null,
                    'can_execute_cron' => $can_execute,
                    'available_schedules' => array_keys($schedules),
                    'errors' => $errors,
                    'warnings' => $warnings
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => __('Cron scheduling test failed', 'woo-order-monitor'),
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Helper method to check if current time is in peak hours
     *
     * @param string $peak_start Peak start time
     * @param string $peak_end Peak end time
     * @param string $current_time Current time to check
     * @return bool True if in peak hours
     */
    private function isCurrentlyPeakHours(string $peak_start, string $peak_end, string $current_time): bool {
        $start_minutes = $this->timeToMinutes($peak_start);
        $end_minutes = $this->timeToMinutes($peak_end);
        $current_minutes = $this->timeToMinutes($current_time);

        if ($start_minutes <= $end_minutes) {
            // Normal case: peak hours don't span midnight
            return $current_minutes >= $start_minutes && $current_minutes <= $end_minutes;
        } else {
            // Peak hours span midnight
            return $current_minutes >= $start_minutes || $current_minutes <= $end_minutes;
        }
    }

    /**
     * Convert time string to minutes since midnight
     *
     * @param string $time Time in HH:MM format
     * @return int Minutes since midnight
     */
    private function timeToMinutes(string $time): int {
        list($hours, $minutes) = explode(':', $time);
        return (int) $hours * 60 + (int) $minutes;
    }

    /**
     * Test midnight spanning logic
     *
     * @return array Test results
     */
    private function testMidnightSpanning(): array {
        // Test case: peak hours from 22:00 to 06:00
        $test_cases = [
            ['22:00', '06:00', '23:30', true],  // Should be peak
            ['22:00', '06:00', '03:00', true],  // Should be peak
            ['22:00', '06:00', '12:00', false], // Should not be peak
            ['09:00', '17:00', '14:00', true],  // Normal case - should be peak
            ['09:00', '17:00', '20:00', false], // Normal case - should not be peak
        ];

        $results = [];
        foreach ($test_cases as $i => $case) {
            list($start, $end, $test_time, $expected) = $case;
            $actual = $this->isCurrentlyPeakHours($start, $end, $test_time);
            $results["test_case_" . ($i + 1)] = [
                'peak_hours' => "{$start} - {$end}",
                'test_time' => $test_time,
                'expected' => $expected,
                'actual' => $actual,
                'passed' => $actual === $expected
            ];
        }

        return $results;
    }

    /**
     * Get SMTP configuration information
     *
     * @return array SMTP info
     */
    private function getSmtpInfo(): array {
        $info = [
            'smtp_configured' => false,
            'smtp_host' => 'default',
            'smtp_port' => 'default',
            'smtp_auth' => 'unknown'
        ];

        // Check for common SMTP plugins
        if (defined('WPMS_ON') || class_exists('PHPMailer\PHPMailer\SMTP')) {
            $info['smtp_configured'] = true;
        }

        // Check for WP Mail SMTP plugin
        if (function_exists('wp_mail_smtp')) {
            $info['smtp_configured'] = true;
            $info['smtp_plugin'] = 'WP Mail SMTP';
        }

        return $info;
    }

    /**
     * Test cron execution capability
     *
     * @return bool True if cron can be executed
     */
    private function testCronExecution(): bool {
        try {
            // Try to get cron array
            $crons = _get_cron_array();
            return is_array($crons);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get available tests
     *
     * @return array Available tests configuration
     */
    public function getAvailableTests(): array {
        return $this->available_tests;
    }

    /**
     * Get test configuration
     *
     * @param string $test_key Test key
     * @return array|null Test configuration or null if not found
     */
    public function getTest(string $test_key): ?array {
        return $this->available_tests[$test_key] ?? null;
    }
}

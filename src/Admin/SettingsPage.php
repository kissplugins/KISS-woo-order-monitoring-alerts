<?php
/**
 * Settings Page Class
 * 
 * Handles the WooCommerce settings tab for Order Monitor,
 * including tab navigation and settings rendering.
 * 
 * @package KissPlugins\WooOrderMonitor\Admin
 * @since 1.5.0
 */

namespace KissPlugins\WooOrderMonitor\Admin;

use KissPlugins\WooOrderMonitor\Core\Settings;
use KissPlugins\WooOrderMonitor\Core\SettingsDefaults;
use KissPlugins\WooOrderMonitor\Core\ThresholdPresets;

/**
 * Settings Page Class
 * 
 * Manages the admin interface for the Order Monitor plugin
 * within the WooCommerce settings area.
 */
class SettingsPage {
    
    /**
     * Settings manager
     * 
     * @var Settings
     */
    private $settings;
    
    /**
     * Tab renderer
     * 
     * @var TabRenderer
     */
    private $tab_renderer;
    
    /**
     * Self tests handler
     * 
     * @var SelfTests
     */
    private $self_tests;
    
    /**
     * AJAX handler
     * 
     * @var AjaxHandler
     */
    private $ajax_handler;
    
    /**
     * Constructor
     * 
     * @param Settings $settings Settings manager instance
     */
    public function __construct(Settings $settings) {
        $this->settings = $settings;
        
        // Initialize components
        $this->initializeComponents();
    }
    
    /**
     * Initialize components
     * 
     * @return void
     */
    private function initializeComponents(): void {
        // Initialize tab renderer
        if (class_exists('KissPlugins\WooOrderMonitor\Admin\TabRenderer')) {
            $this->tab_renderer = new TabRenderer();
        }
        
        // Initialize self tests
        if (class_exists('KissPlugins\WooOrderMonitor\Admin\SelfTests')) {
            $this->self_tests = new SelfTests($this->settings);
        }
        
        // Initialize AJAX handler
        if (class_exists('KissPlugins\WooOrderMonitor\Admin\AjaxHandler')) {
            $this->ajax_handler = new AjaxHandler($this->settings, $this->self_tests);
        }
    }
    
    /**
     * Initialize WordPress hooks
     * 
     * @return void
     */
    public function initializeHooks(): void {
        // WooCommerce settings integration
        add_filter('woocommerce_settings_tabs_array', [$this, 'addSettingsTab'], 50);
        add_action('woocommerce_settings_tabs_order_monitor', [$this, 'renderSettingsTab']);
        add_action('woocommerce_update_options_order_monitor', [$this, 'updateSettings']);
        
        // Plugin action links (Settings link on plugins page)
        //add_filter('plugin_action_links_' . WOOM_PLUGIN_BASENAME, [$this, 'addPluginActionLinks']);
        
        // Initialize AJAX handler hooks
        if ($this->ajax_handler) {
            $this->ajax_handler->initializeHooks();
        }
    }
    
    /**
     * Add settings tab to WooCommerce
     * 
     * @param array $settings_tabs Existing settings tabs
     * @return array Modified settings tabs
     */
    public function addSettingsTab(array $settings_tabs): array {
        $settings_tabs['order_monitor'] = __('Order Monitor', 'woo-order-monitor');
        return $settings_tabs;
    }
    
    /**
     * Add plugin action links (Settings link on plugins page)
     * 
     * @param array $links Existing plugin action links
     * @return array Modified plugin action links
     */
    public function addPluginActionLinks(array $links): array {
        // Only add settings link if WooCommerce is active
        if (class_exists('WooCommerce')) {
            $settings_link = sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=wc-settings&tab=order_monitor'),
                __('Settings', 'woo-order-monitor')
            );
            
            // Add settings link at the beginning of the array
            array_unshift($links, $settings_link);
        }
        
        return $links;
    }
    
    /**
     * Render settings tab content
     * 
     * @return void
     */
    public function renderSettingsTab(): void {
        // Get current sub-tab
        $current_tab = isset($_GET['subtab']) ? sanitize_text_field($_GET['subtab']) : 'settings';
        
        // Render tab navigation
        if ($this->tab_renderer) {
            $this->tab_renderer->renderNavigation($current_tab);
        } else {
            $this->renderFallbackNavigation($current_tab);
        }
        
        // Render content based on current tab
        switch ($current_tab) {
            case 'changelog':
                $this->renderChangelogViewer();
                break;
                
            case 'self-tests':
                if ($this->self_tests) {
                    $this->self_tests->renderSelfTestsPage();
                } else {
                    $this->renderFallbackSelfTests();
                }
                break;
                
            default:
                // Default to settings tab
                $this->renderMainSettings();
                break;
        }
        
        // Add custom CSS and JavaScript
        $this->renderAssets();
    }
    
    /**
     * Render main settings
     * 
     * @return void
     */
    private function renderMainSettings(): void {
        // Use WooCommerce admin fields for standard settings
        woocommerce_admin_fields($this->getSettingsFields());
        
        // Render custom fields that WooCommerce doesn't support natively
        $this->renderCustomFields();
    }
    
    /**
     * Get settings fields configuration
     *
     * ⚠️  IMPORTANT: Uses SettingsDefaults for all default values.
     * ⚠️  DO NOT define default values here - use SettingsDefaults instead.
     *
     * @return array Settings fields array
     */
    private function getSettingsFields(): array {
        // Get defaults from centralized configuration
        $defaults = SettingsDefaults::getUIDefaults();

        return [
            'section_title' => [
                'name' => sprintf(__('WooCommerce Order Monitor Settings - v%s', 'woo-order-monitor'), WOOM_VERSION),
                'type' => 'title',
                'desc' => __('Configure order monitoring thresholds and notifications.', 'woo-order-monitor'),
                'id' => 'woom_section_title'
            ],
            'enabled' => [
                'name' => __('Enable Monitoring', 'woo-order-monitor'),
                'type' => 'checkbox',
                'desc' => __('Enable order volume monitoring', 'woo-order-monitor'),
                'id' => 'woom_enabled',
                'default' => $defaults['enabled']
            ],
            'notification_emails' => [
                'name' => __('Notification Emails', 'woo-order-monitor'),
                'type' => 'textarea',
                'desc' => __('Email addresses to notify when order volume is below threshold (comma-separated)', 'woo-order-monitor'),
                'id' => 'woom_notification_emails',
                'default' => $defaults['notification_emails'],
                'css' => 'width: 400px; height: 100px;'
            ],
            'alert_cooldown' => [
                'name' => __('Alert Cooldown', 'woo-order-monitor'),
                'type' => 'number',
                'desc' => __('Minimum seconds between alerts (prevents alert spam)', 'woo-order-monitor'),
                'id' => 'woom_alert_cooldown',
                'default' => $defaults['alert_cooldown'],
                'custom_attributes' => [
                    'min' => 0,
                    'step' => 60
                ]
            ],
            'max_daily_alerts' => [
                'name' => __('Max Daily Alerts', 'woo-order-monitor'),
                'type' => 'number',
                'desc' => __('Maximum number of alerts per day (0 = unlimited)', 'woo-order-monitor'),
                'id' => 'woom_max_daily_alerts',
                'default' => $defaults['max_daily_alerts'],
                'custom_attributes' => [
                    'min' => 0,
                    'step' => 1
                ]
            ],
            'section_end' => [
                'type' => 'sectionend',
                'id' => 'woom_section_end'
            ],

            // Rolling Average Detection (RAD) Section - v1.6.0
            'rad_section_title' => [
                'name' => __('Rolling Average Detection (RAD)', 'woo-order-monitor'),
                'type' => 'title',
                'desc' => __('Failure-rate based monitoring that works for both high-volume and low-volume stores. Tracks order success/failure patterns instead of time-based thresholds.', 'woo-order-monitor'),
                'id' => 'woom_rad_section_title'
            ],
            'rolling_enabled' => [
                'name' => __('Enable RAD', 'woo-order-monitor'),
                'type' => 'checkbox',
                'desc' => __('Enable Rolling Average Detection (monitors failure rate instead of order count)', 'woo-order-monitor'),
                'id' => 'woom_rolling_enabled',
                'default' => $defaults['rolling_enabled']
            ],
            'rolling_window_size' => [
                'name' => __('Window Size', 'woo-order-monitor'),
                'type' => 'number',
                'desc' => __('Number of recent orders to track (default: 10)', 'woo-order-monitor'),
                'id' => 'woom_rolling_window_size',
                'default' => $defaults['rolling_window_size'],
                'custom_attributes' => [
                    'min' => 3,
                    'max' => 50,
                    'step' => 1
                ]
            ],
            'rolling_failure_threshold' => [
                'name' => __('Failure Threshold (%)', 'woo-order-monitor'),
                'type' => 'number',
                'desc' => __('Alert when this percentage of orders fail (default: 70%). <strong>Lower values = more sensitive</strong> (more alerts for smaller issues). <strong>Higher values = less sensitive</strong> (fewer alerts, only for major problems). Example: 50% alerts if half your orders fail, 90% only alerts if nearly all orders fail. Recommended: 60-80% for most stores.', 'woo-order-monitor'),
                'id' => 'woom_rolling_failure_threshold',
                'default' => $defaults['rolling_failure_threshold'],
                'custom_attributes' => [
                    'min' => 1,
                    'max' => 100,
                    'step' => 1
                ]
            ],
            'rolling_min_orders' => [
                'name' => __('Minimum Orders', 'woo-order-monitor'),
                'type' => 'number',
                'desc' => __('Minimum orders required before alerting (prevents false positives)', 'woo-order-monitor'),
                'id' => 'woom_rolling_min_orders',
                'default' => $defaults['rolling_min_orders'],
                'custom_attributes' => [
                    'min' => 1,
                    'max' => 20,
                    'step' => 1
                ]
            ],
            'rad_section_end' => [
                'type' => 'sectionend',
                'id' => 'woom_rad_section_end'
            ]
        ];
    }
    
    /**
     * Render custom fields
     * 
     * @return void
     */
    private function renderCustomFields(): void {
        ?>
        <table class="form-table">
            <!-- Multi-Block Threshold Configuration -->
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="woom_threshold_preset"><?php _e('Threshold Configuration', 'woo-order-monitor'); ?></label>
                </th>
                <td class="forminp">
                    <?php $this->renderMultiBlockThresholdEditor(); ?>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="woom_test_notification"><?php _e('Test Notification', 'woo-order-monitor'); ?></label>
                </th>
                <td class="forminp">
                    <button type="button" id="woom_test_notification" class="button-secondary">
                        <?php _e('Send Test Notification', 'woo-order-monitor'); ?>
                    </button>
                    <p class="description">
                        <?php _e('Send a test email to verify notification settings are working correctly.', 'woo-order-monitor'); ?>
                    </p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label><?php _e('Monitoring Status', 'woo-order-monitor'); ?></label>
                </th>
                <td class="forminp">
                    <div class="woom-status-info">
                        <?php $this->renderMonitoringStatus(); ?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render monitoring status information
     * 
     * @return void
     */
    private function renderMonitoringStatus(): void {
        $last_check = $this->settings->get('last_check');
        $last_alert = $this->settings->get('last_alert');
        $is_enabled = $this->settings->isEnabled();
        
        ?>
        <p><strong><?php _e('Status:', 'woo-order-monitor'); ?></strong> 
            <span style="color: <?php echo $is_enabled ? '#46b450' : '#dc3232'; ?>;">
                <?php echo $is_enabled ? __('Enabled', 'woo-order-monitor') : __('Disabled', 'woo-order-monitor'); ?>
            </span>
        </p>
        
        <?php if ($last_check): ?>
            <p><strong><?php _e('Last Check:', 'woo-order-monitor'); ?></strong> 
                <?php echo esc_html(date('Y-m-d H:i:s', $last_check)); ?>
            </p>
        <?php endif; ?>
        
        <?php if ($last_alert): ?>
            <p><strong><?php _e('Last Alert:', 'woo-order-monitor'); ?></strong> 
                <?php echo esc_html(date('Y-m-d H:i:s', $last_alert)); ?>
            </p>
        <?php endif; ?>
        
        <?php
        // Show next cron run if available
        $next_cron = wp_next_scheduled('woom_check_orders');
        if ($next_cron): ?>
            <p><strong><?php _e('Next Check:', 'woo-order-monitor'); ?></strong>
                <?php echo esc_html(date('Y-m-d H:i:s', $next_cron)); ?>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render multi-block threshold editor
     *
     * @return void
     */
    private function renderMultiBlockThresholdEditor(): void {
        // Get current blocks or defaults
        $current_blocks = $this->settings->get('threshold_blocks', []);
        if (empty($current_blocks)) {
            $current_blocks = SettingsDefaults::getDefaultThresholdBlocks();
        }

        // Get preset options
        $preset_options = ThresholdPresets::getPresetOptions();

        // Determine current preset (default to BINOID)
        $current_preset = 'binoid';

        ?>
        <div class="woom-multiblock-editor">
            <!-- Preset Selector -->
            <div class="woom-preset-selector" style="margin-bottom: 20px;">
                <label for="woom_threshold_preset" style="font-weight: bold; display: block; margin-bottom: 8px;">
                    <?php _e('Select Preset:', 'woo-order-monitor'); ?>
                </label>
                <select id="woom_threshold_preset" name="woom_threshold_preset" style="width: 400px;">
                    <?php foreach ($preset_options as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($current_preset, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="woom_load_preset" class="button-secondary" style="margin-left: 10px;">
                    <?php _e('Load Preset', 'woo-order-monitor'); ?>
                </button>
                <p class="description">
                    <?php _e('Choose a preset configuration or create your own custom blocks.', 'woo-order-monitor'); ?>
                </p>
            </div>

            <!-- Threshold Blocks Table -->
            <div class="woom-blocks-table-wrapper" style="margin-top: 20px;">
                <h4><?php _e('Time Blocks Configuration', 'woo-order-monitor'); ?></h4>
                <p class="description" style="margin-bottom: 15px;">
                    <?php _e('Configure threshold values for each time block. Thresholds represent the minimum expected orders in a 15-minute period.', 'woo-order-monitor'); ?>
                </p>

                <table class="widefat woom-blocks-table" style="max-width: 900px;">
                    <thead>
                        <tr>
                            <th style="width: 60px;"><?php _e('Enabled', 'woo-order-monitor'); ?></th>
                            <th style="width: 150px;"><?php _e('Block Name', 'woo-order-monitor'); ?></th>
                            <th style="width: 120px;"><?php _e('Start Time', 'woo-order-monitor'); ?></th>
                            <th style="width: 120px;"><?php _e('End Time', 'woo-order-monitor'); ?></th>
                            <th style="width: 100px;"><?php _e('Threshold', 'woo-order-monitor'); ?></th>
                            <th style="width: 100px;"><?php _e('Critical', 'woo-order-monitor'); ?></th>
                            <th><?php _e('Expected Range', 'woo-order-monitor'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="woom_blocks_tbody">
                        <?php $this->renderBlockRows($current_blocks); ?>
                    </tbody>
                </table>

                <input type="hidden" name="woom_threshold_blocks_json" id="woom_threshold_blocks_json" value="<?php echo esc_attr(json_encode($current_blocks)); ?>" />
                <input type="hidden" name="woom_use_threshold_blocks" value="yes" />
            </div>
        </div>
        <?php
    }

    /**
     * Render block rows for the threshold table
     *
     * @param array $blocks Array of threshold blocks
     * @return void
     */
    private function renderBlockRows(array $blocks): void {
        foreach ($blocks as $index => $block) {
            $enabled = isset($block['enabled']) ? $block['enabled'] : true;
            $name = isset($block['name']) ? $block['name'] : '';
            $start_time = isset($block['time_ranges'][0]['start']) ? $block['time_ranges'][0]['start'] : '00:00';
            $end_time = isset($block['time_ranges'][0]['end']) ? $block['time_ranges'][0]['end'] : '00:00';
            $threshold = isset($block['threshold']) ? $block['threshold'] : 0;
            $critical = isset($block['critical_threshold']) ? $block['critical_threshold'] : 0;
            $min_range = isset($block['expected_range']['min']) ? $block['expected_range']['min'] : 0;
            $max_range = isset($block['expected_range']['max']) ? $block['expected_range']['max'] : 0;

            ?>
            <tr class="woom-block-row" data-index="<?php echo esc_attr($index); ?>">
                <td style="text-align: center;">
                    <input type="checkbox"
                           class="woom-block-enabled"
                           data-index="<?php echo esc_attr($index); ?>"
                           <?php checked($enabled, true); ?> />
                </td>
                <td>
                    <input type="text"
                           class="woom-block-name"
                           data-index="<?php echo esc_attr($index); ?>"
                           value="<?php echo esc_attr($name); ?>"
                           style="width: 100%;"
                           readonly />
                </td>
                <td>
                    <input type="time"
                           class="woom-block-start"
                           data-index="<?php echo esc_attr($index); ?>"
                           value="<?php echo esc_attr($start_time); ?>"
                           style="width: 100%;" />
                </td>
                <td>
                    <input type="time"
                           class="woom-block-end"
                           data-index="<?php echo esc_attr($index); ?>"
                           value="<?php echo esc_attr($end_time); ?>"
                           style="width: 100%;" />
                </td>
                <td>
                    <input type="number"
                           class="woom-block-threshold"
                           data-index="<?php echo esc_attr($index); ?>"
                           value="<?php echo esc_attr($threshold); ?>"
                           min="0"
                           step="1"
                           style="width: 100%;" />
                </td>
                <td>
                    <input type="number"
                           class="woom-block-critical"
                           data-index="<?php echo esc_attr($index); ?>"
                           value="<?php echo esc_attr($critical); ?>"
                           min="0"
                           step="1"
                           style="width: 100%;" />
                </td>
                <td>
                    <input type="number"
                           class="woom-block-min"
                           data-index="<?php echo esc_attr($index); ?>"
                           value="<?php echo esc_attr($min_range); ?>"
                           min="0"
                           step="1"
                           style="width: 60px;"
                           placeholder="Min" />
                    -
                    <input type="number"
                           class="woom-block-max"
                           data-index="<?php echo esc_attr($index); ?>"
                           value="<?php echo esc_attr($max_range); ?>"
                           min="0"
                           step="1"
                           style="width: 60px;"
                           placeholder="Max" />
                </td>
            </tr>
            <?php
        }
    }

    /**
     * Update settings
     *
     * @return void
     */
    public function updateSettings(): void {
        // Use WooCommerce's built-in settings update
        woocommerce_update_options($this->getSettingsFields());

        // Handle multi-block threshold blocks
        $this->handleMultiBlockUpdate();

        // Handle any custom post-update logic
        $this->handleSettingsUpdate();
    }

    /**
     * Handle multi-block threshold blocks update
     *
     * @return void
     */
    private function handleMultiBlockUpdate(): void {
        // Check if multi-block data was submitted
        if (isset($_POST['woom_threshold_blocks_json'])) {
            $blocks_json = sanitize_text_field(wp_unslash($_POST['woom_threshold_blocks_json']));
            $blocks = json_decode($blocks_json, true);

            if (is_array($blocks) && !empty($blocks)) {
                // Validate blocks
                if ($this->settings->validateThresholdBlocks($blocks)) {
                    // Save blocks
                    update_option('woom_threshold_blocks', $blocks);
                }
            }
        }

        // Enable multi-block mode if submitted
        if (isset($_POST['woom_use_threshold_blocks'])) {
            update_option('woom_use_threshold_blocks', 'yes');
        }
    }
    
    /**
     * Handle settings update logic
     * 
     * @return void
     */
    private function handleSettingsUpdate(): void {
        // Reschedule cron if monitoring settings changed
        if ($this->settings->isEnabled()) {
            // Clear existing cron
            wp_clear_scheduled_hook('woom_check_orders');
            
            // Schedule new cron
            if (!wp_next_scheduled('woom_check_orders')) {
                wp_schedule_event(time(), 'woom_15min', 'woom_check_orders');
            }
        } else {
            // Clear cron if monitoring is disabled
            wp_clear_scheduled_hook('woom_check_orders');
        }
    }
    
    /**
     * Render fallback navigation (when TabRenderer not available)
     * 
     * @param string $current_tab Current active tab
     * @return void
     */
    private function renderFallbackNavigation(string $current_tab): void {
        $base_url = admin_url('admin.php?page=wc-settings&tab=order_monitor');
        ?>
        <div class="woom-tab-navigation" style="margin-bottom: 20px; border-bottom: 1px solid #ccc;">
            <ul class="woom-tabs" style="margin: 0; padding: 0; list-style: none; display: flex;">
                <li style="margin: 0;">
                    <a href="<?php echo esc_url($base_url . '&subtab=settings'); ?>"
                       class="woom-tab-link <?php echo $current_tab === 'settings' ? 'active' : ''; ?>"
                       style="display: block; padding: 12px 20px; text-decoration: none; border-bottom: 3px solid transparent; <?php echo $current_tab === 'settings' ? 'border-bottom-color: #0073aa; color: #0073aa; font-weight: bold;' : 'color: #555;'; ?>">
                        <?php _e('Settings', 'woo-order-monitor'); ?>
                    </a>
                </li>
                <li style="margin: 0;">
                    <a href="<?php echo esc_url($base_url . '&subtab=changelog'); ?>"
                       class="woom-tab-link <?php echo $current_tab === 'changelog' ? 'active' : ''; ?>"
                       style="display: block; padding: 12px 20px; text-decoration: none; border-bottom: 3px solid transparent; <?php echo $current_tab === 'changelog' ? 'border-bottom-color: #0073aa; color: #0073aa; font-weight: bold;' : 'color: #555;'; ?>">
                        <?php _e('Changelog', 'woo-order-monitor'); ?>
                    </a>
                </li>
                <li style="margin: 0;">
                    <a href="<?php echo esc_url($base_url . '&subtab=self-tests'); ?>"
                       class="woom-tab-link <?php echo $current_tab === 'self-tests' ? 'active' : ''; ?>"
                       style="display: block; padding: 12px 20px; text-decoration: none; border-bottom: 3px solid transparent; <?php echo $current_tab === 'self-tests' ? 'border-bottom-color: #0073aa; color: #0073aa; font-weight: bold;' : 'color: #555;'; ?>">
                        <?php _e('Self Tests', 'woo-order-monitor'); ?>
                    </a>
                </li>
            </ul>
        </div>
        <?php
    }

    /**
     * Render changelog viewer
     *
     * @return void
     */
    private function renderChangelogViewer(): void {
        $changelog_file = WOOM_PLUGIN_DIR . 'CHANGELOG.md';

        ?>
        <h2><?php printf(__('Changelog - Version %s', 'woo-order-monitor'), WOOM_VERSION); ?></h2>

        <?php if (file_exists($changelog_file)): ?>
            <div class="woom-changelog-viewer" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; max-height: 600px; overflow-y: auto;">
                <?php
                $changelog_content = file_get_contents($changelog_file);

                // Convert markdown to basic HTML
                $changelog_html = $this->convertMarkdownToHtml($changelog_content);
                echo wp_kses_post($changelog_html);
                ?>
            </div>
        <?php else: ?>
            <div class="notice notice-warning">
                <p><?php _e('Changelog file not found.', 'woo-order-monitor'); ?></p>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Convert basic markdown to HTML
     *
     * @param string $markdown Markdown content
     * @return string HTML content
     */
    private function convertMarkdownToHtml(string $markdown): string {
        // Basic markdown conversion
        $html = $markdown;

        // Headers
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

        // Bold
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);

        // Lists
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);

        // Line breaks
        $html = nl2br($html);

        return $html;
    }

    /**
     * Render fallback self tests (when SelfTests class not available)
     *
     * @return void
     */
    private function renderFallbackSelfTests(): void {
        ?>
        <h2><?php printf(__('Self Tests - Version %s', 'woo-order-monitor'), WOOM_VERSION); ?></h2>
        <div class="notice notice-info">
            <p><?php _e('Self tests functionality is being migrated to PSR-4 structure. Please complete the migration to access this feature.', 'woo-order-monitor'); ?></p>
        </div>
        <?php
    }

    /**
     * Render CSS and JavaScript assets
     *
     * @return void
     */
    private function renderAssets(): void {
        ?>
        <style type="text/css">
        .woom-status-info p {
            margin: 8px 0;
        }
        .woom-tab-navigation .woom-tab-link:hover {
            color: #0073aa !important;
        }
        .woom-changelog-viewer h1,
        .woom-changelog-viewer h2,
        .woom-changelog-viewer h3 {
            margin-top: 20px;
            margin-bottom: 10px;
        }
        .woom-changelog-viewer ul {
            margin-left: 20px;
        }
        .woom-multiblock-editor {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 4px;
        }
        .woom-blocks-table {
            background: #fff;
        }
        .woom-blocks-table th {
            background: #f1f1f1;
            font-weight: 600;
            padding: 10px;
        }
        .woom-blocks-table td {
            padding: 8px;
        }
        .woom-block-row input[type="time"],
        .woom-block-row input[type="number"] {
            padding: 4px;
        }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Preset configurations
            var presets = <?php echo json_encode(ThresholdPresets::getAllPresets()); ?>;

            // Load preset button handler
            $('#woom_load_preset').on('click', function(e) {
                e.preventDefault();

                var presetKey = $('#woom_threshold_preset').val();
                var preset = presets[presetKey];

                if (!preset || !preset.blocks) {
                    alert('<?php echo esc_js(__('Invalid preset selected', 'woo-order-monitor')); ?>');
                    return;
                }

                // Update table with preset blocks
                updateBlocksTable(preset.blocks);

                // Update hidden JSON field
                updateBlocksJSON();

                alert('<?php echo esc_js(__('Preset loaded successfully! Don\'t forget to save your changes.', 'woo-order-monitor')); ?>');
            });

            // Update blocks table with new data
            function updateBlocksTable(blocks) {
                var $tbody = $('#woom_blocks_tbody');
                $tbody.empty();

                $.each(blocks, function(index, block) {
                    var enabled = block.enabled !== false;
                    var name = block.name || '';
                    var startTime = block.time_ranges && block.time_ranges[0] ? block.time_ranges[0].start : '00:00';
                    var endTime = block.time_ranges && block.time_ranges[0] ? block.time_ranges[0].end : '00:00';
                    var threshold = block.threshold || 0;
                    var critical = block.critical_threshold || 0;
                    var minRange = block.expected_range ? block.expected_range.min : 0;
                    var maxRange = block.expected_range ? block.expected_range.max : 0;

                    var row = '<tr class="woom-block-row" data-index="' + index + '">' +
                        '<td style="text-align: center;"><input type="checkbox" class="woom-block-enabled" data-index="' + index + '" ' + (enabled ? 'checked' : '') + ' /></td>' +
                        '<td><input type="text" class="woom-block-name" data-index="' + index + '" value="' + name + '" style="width: 100%;" readonly /></td>' +
                        '<td><input type="time" class="woom-block-start" data-index="' + index + '" value="' + startTime + '" style="width: 100%;" /></td>' +
                        '<td><input type="time" class="woom-block-end" data-index="' + index + '" value="' + endTime + '" style="width: 100%;" /></td>' +
                        '<td><input type="number" class="woom-block-threshold" data-index="' + index + '" value="' + threshold + '" min="0" step="1" style="width: 100%;" /></td>' +
                        '<td><input type="number" class="woom-block-critical" data-index="' + index + '" value="' + critical + '" min="0" step="1" style="width: 100%;" /></td>' +
                        '<td>' +
                            '<input type="number" class="woom-block-min" data-index="' + index + '" value="' + minRange + '" min="0" step="1" style="width: 60px;" placeholder="Min" /> - ' +
                            '<input type="number" class="woom-block-max" data-index="' + index + '" value="' + maxRange + '" min="0" step="1" style="width: 60px;" placeholder="Max" />' +
                        '</td>' +
                    '</tr>';

                    $tbody.append(row);
                });
            }

            // Update hidden JSON field with current table data
            function updateBlocksJSON() {
                var blocks = [];

                $('.woom-block-row').each(function() {
                    var $row = $(this);
                    var index = $row.data('index');

                    var block = {
                        name: $row.find('.woom-block-name').val(),
                        enabled: $row.find('.woom-block-enabled').is(':checked'),
                        time_ranges: [{
                            start: $row.find('.woom-block-start').val(),
                            end: $row.find('.woom-block-end').val()
                        }],
                        threshold: parseInt($row.find('.woom-block-threshold').val()) || 0,
                        critical_threshold: parseInt($row.find('.woom-block-critical').val()) || 0,
                        expected_range: {
                            min: parseInt($row.find('.woom-block-min').val()) || 0,
                            max: parseInt($row.find('.woom-block-max').val()) || 0
                        }
                    };

                    blocks.push(block);
                });

                $('#woom_threshold_blocks_json').val(JSON.stringify(blocks));
            }

            // Update JSON when any field changes
            $(document).on('change', '.woom-block-row input', function() {
                updateBlocksJSON();
            });

            // Initialize JSON on page load
            updateBlocksJSON();

            // Test notification button handler
            $('#woom_test_notification').on('click', function(e) {
                e.preventDefault();

                var $button = $(this);
                $button.prop('disabled', true).text('<?php echo esc_js(__('Sending...', 'woo-order-monitor')); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'woom_test_notification',
                        security: '<?php echo wp_create_nonce('woom_test_notification'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php echo esc_js(__('Test notification sent successfully!', 'woo-order-monitor')); ?>');
                        } else {
                            alert('<?php echo esc_js(__('Failed to send test notification:', 'woo-order-monitor')); ?> ' + response.data);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('An error occurred while sending the test notification.', 'woo-order-monitor')); ?>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php echo esc_js(__('Send Test Notification', 'woo-order-monitor')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Get settings manager
     *
     * @return Settings Settings manager instance
     */
    public function getSettings(): Settings {
        return $this->settings;
    }

    /**
     * Get tab renderer
     *
     * @return TabRenderer|null Tab renderer instance
     */
    public function getTabRenderer(): ?TabRenderer {
        return $this->tab_renderer;
    }

    /**
     * Get self tests handler
     *
     * @return SelfTests|null Self tests handler instance
     */
    public function getSelfTests(): ?SelfTests {
        return $this->self_tests;
    }

    /**
     * Get AJAX handler
     *
     * @return AjaxHandler|null AJAX handler instance
     */
    public function getAjaxHandler(): ?AjaxHandler {
        return $this->ajax_handler;
    }
}

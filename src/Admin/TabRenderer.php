<?php
/**
 * Tab Renderer Class
 * 
 * Handles rendering of tab navigation for the admin interface.
 * 
 * @package KissPlugins\WooOrderMonitor\Admin
 * @since 1.5.0
 */

namespace KissPlugins\WooOrderMonitor\Admin;

/**
 * Tab Renderer Class
 * 
 * Manages the rendering of tab navigation and tab-specific styling
 * for the Order Monitor admin interface.
 */
class TabRenderer {
    
    /**
     * Available tabs configuration
     * 
     * @var array
     */
    private $tabs = [
        'settings' => [
            'label' => 'Settings',
            'icon' => 'admin-settings',
            'description' => 'Configure monitoring settings'
        ],
        'changelog' => [
            'label' => 'Changelog',
            'icon' => 'admin-page',
            'description' => 'View version history'
        ],
        'self-tests' => [
            'label' => 'Self Tests',
            'icon' => 'admin-tools',
            'description' => 'Run diagnostic tests'
        ]
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        // Translate tab labels
        $this->tabs['settings']['label'] = __('Settings', 'woo-order-monitor');
        $this->tabs['changelog']['label'] = __('Changelog', 'woo-order-monitor');
        $this->tabs['self-tests']['label'] = __('Self Tests', 'woo-order-monitor');
        
        $this->tabs['settings']['description'] = __('Configure monitoring settings', 'woo-order-monitor');
        $this->tabs['changelog']['description'] = __('View version history', 'woo-order-monitor');
        $this->tabs['self-tests']['description'] = __('Run diagnostic tests', 'woo-order-monitor');
    }
    
    /**
     * Render tab navigation
     * 
     * @param string $current_tab Currently active tab
     * @return void
     */
    public function renderNavigation(string $current_tab = 'settings'): void {
        $base_url = admin_url('admin.php?page=wc-settings&tab=order_monitor');
        
        ?>
        <div class="woom-tab-navigation">
            <?php $this->renderNavigationStyles(); ?>
            
            <ul class="woom-tabs">
                <?php foreach ($this->tabs as $tab_key => $tab_config): ?>
                    <li class="woom-tab-item">
                        <a href="<?php echo esc_url($base_url . '&subtab=' . $tab_key); ?>"
                           class="woom-tab-link <?php echo $current_tab === $tab_key ? 'active' : ''; ?>"
                           title="<?php echo esc_attr($tab_config['description']); ?>">
                            
                            <?php if (!empty($tab_config['icon'])): ?>
                                <span class="dashicons dashicons-<?php echo esc_attr($tab_config['icon']); ?>"></span>
                            <?php endif; ?>
                            
                            <span class="tab-label"><?php echo esc_html($tab_config['label']); ?></span>
                            
                            <?php if ($current_tab === $tab_key): ?>
                                <span class="active-indicator"></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <div class="woom-tab-info">
                <?php if (isset($this->tabs[$current_tab])): ?>
                    <span class="current-tab-description">
                        <?php echo esc_html($this->tabs[$current_tab]['description']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render navigation styles
     * 
     * @return void
     */
    private function renderNavigationStyles(): void {
        ?>
        <style type="text/css">
        .woom-tab-navigation {
            margin-bottom: 20px;
            border-bottom: 1px solid #ccd0d4;
            background: #fff;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .woom-tabs {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            background: linear-gradient(to bottom, #f9f9f9, #ececec);
            border-bottom: 1px solid #ccd0d4;
        }
        
        .woom-tab-item {
            margin: 0;
            position: relative;
        }
        
        .woom-tab-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            text-decoration: none;
            color: #555;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
            position: relative;
            background: transparent;
        }
        
        .woom-tab-link:hover {
            color: #0073aa;
            background: rgba(0, 115, 170, 0.05);
        }
        
        .woom-tab-link.active {
            color: #0073aa;
            font-weight: 600;
            border-bottom-color: #0073aa;
            background: #fff;
        }
        
        .woom-tab-link .dashicons {
            margin-right: 6px;
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        
        .woom-tab-link .tab-label {
            font-size: 14px;
        }
        
        .woom-tab-link .active-indicator {
            position: absolute;
            bottom: -3px;
            left: 0;
            right: 0;
            height: 3px;
            background: #0073aa;
        }
        
        .woom-tab-info {
            padding: 10px 20px;
            background: #f9f9f9;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .current-tab-description {
            font-size: 13px;
            color: #666;
            font-style: italic;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .woom-tabs {
                flex-direction: column;
            }
            
            .woom-tab-link {
                padding: 10px 15px;
                border-bottom: 1px solid #e1e1e1;
                border-right: none;
            }
            
            .woom-tab-link.active {
                border-bottom-color: #0073aa;
                border-left: 3px solid #0073aa;
            }
            
            .woom-tab-info {
                padding: 8px 15px;
            }
        }
        
        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .woom-tab-link {
                border: 1px solid #000;
                margin-right: 1px;
            }
            
            .woom-tab-link.active {
                background: #000;
                color: #fff;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Add a new tab to the navigation
     * 
     * @param string $key Tab key/slug
     * @param array $config Tab configuration
     * @return void
     */
    public function addTab(string $key, array $config): void {
        $default_config = [
            'label' => ucfirst($key),
            'icon' => '',
            'description' => ''
        ];
        
        $this->tabs[$key] = array_merge($default_config, $config);
    }
    
    /**
     * Remove a tab from the navigation
     * 
     * @param string $key Tab key to remove
     * @return void
     */
    public function removeTab(string $key): void {
        unset($this->tabs[$key]);
    }
    
    /**
     * Get all available tabs
     * 
     * @return array Available tabs configuration
     */
    public function getTabs(): array {
        return $this->tabs;
    }
    
    /**
     * Check if a tab exists
     * 
     * @param string $key Tab key to check
     * @return bool True if tab exists
     */
    public function hasTab(string $key): bool {
        return isset($this->tabs[$key]);
    }
    
    /**
     * Get tab configuration
     * 
     * @param string $key Tab key
     * @return array|null Tab configuration or null if not found
     */
    public function getTab(string $key): ?array {
        return $this->tabs[$key] ?? null;
    }
    
    /**
     * Render a simple breadcrumb for the current tab
     * 
     * @param string $current_tab Current active tab
     * @return void
     */
    public function renderBreadcrumb(string $current_tab): void {
        if (!isset($this->tabs[$current_tab])) {
            return;
        }
        
        $tab_config = $this->tabs[$current_tab];
        
        ?>
        <div class="woom-breadcrumb" style="margin-bottom: 15px; font-size: 13px; color: #666;">
            <span class="dashicons dashicons-admin-home" style="font-size: 13px; margin-right: 5px;"></span>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=order_monitor')); ?>" style="color: #0073aa; text-decoration: none;">
                <?php _e('Order Monitor', 'woo-order-monitor'); ?>
            </a>
            <span style="margin: 0 8px;">&raquo;</span>
            <span><?php echo esc_html($tab_config['label']); ?></span>
        </div>
        <?php
    }
    
    /**
     * Render tab content wrapper
     * 
     * @param string $current_tab Current active tab
     * @param callable $content_callback Callback to render tab content
     * @return void
     */
    public function renderTabContent(string $current_tab, callable $content_callback): void {
        ?>
        <div class="woom-tab-content" data-tab="<?php echo esc_attr($current_tab); ?>">
            <?php
            if (is_callable($content_callback)) {
                call_user_func($content_callback, $current_tab);
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Get the default tab
     * 
     * @return string Default tab key
     */
    public function getDefaultTab(): string {
        return 'settings';
    }
    
    /**
     * Validate tab key
     * 
     * @param string $tab_key Tab key to validate
     * @return string Valid tab key (falls back to default if invalid)
     */
    public function validateTab(string $tab_key): string {
        return $this->hasTab($tab_key) ? $tab_key : $this->getDefaultTab();
    }
}

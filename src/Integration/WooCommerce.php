<?php
/**
 * WooCommerce Integration
 *
 * @package KissPlugins\WooOrderMonitor
 * @since 1.7.0
 */

namespace KissPlugins\WooOrderMonitor\Integration;

/**
 * WooCommerce Integration Class
 * 
 * Handles WooCommerce-specific functionality including HPOS detection,
 * order status management, and WooCommerce compatibility checks.
 * 
 * @since 1.7.0
 */
class WooCommerce {
    
    /**
     * Check if WooCommerce is active
     *
     * @return bool True if WooCommerce is active
     */
    public static function isActive(): bool {
        return \class_exists('WooCommerce');
    }
    
    /**
     * Check if High-Performance Order Storage (HPOS) is enabled
     *
     * @return bool True if HPOS is enabled
     */
    public static function isHposEnabled(): bool {
        if (!\class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            return false;
        }
        
        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }
    
    /**
     * Get WooCommerce version
     *
     * @return string|null WooCommerce version or null if not available
     */
    public static function getVersion(): ?string {
        if (!self::isActive()) {
            return null;
        }
        
        return defined('WC_VERSION') ? \WC_VERSION : null;
    }
    
    /**
     * Check if WooCommerce version meets minimum requirement
     *
     * @param string $min_version Minimum required version
     * @return bool True if version meets requirement
     */
    public static function meetsMinimumVersion(string $min_version): bool {
        $current_version = self::getVersion();
        
        if (!$current_version) {
            return false;
        }
        
        return \version_compare($current_version, $min_version, '>=');
    }
    
    /**
     * Get valid order statuses for monitoring
     *
     * Returns the order statuses that should be counted as successful orders.
     *
     * @return array Array of order status slugs
     */
    public static function getValidOrderStatuses(): array {
        return ['wc-completed', 'wc-processing'];
    }
    
    /**
     * Get failed order statuses
     *
     * Returns the order statuses that should be counted as failed orders.
     *
     * @return array Array of order status slugs
     */
    public static function getFailedOrderStatuses(): array {
        return ['wc-failed'];
    }
    
    /**
     * Get all order statuses
     *
     * @return array Array of all WooCommerce order statuses
     */
    public static function getAllOrderStatuses(): array {
        if (!self::isActive()) {
            return [];
        }
        
        return \wc_get_order_statuses();
    }
    
    /**
     * Get orders admin URL
     *
     * @return string URL to WooCommerce orders admin page
     */
    public static function getOrdersAdminUrl(): string {
        return \admin_url('edit.php?post_type=shop_order');
    }
    
    /**
     * Get WooCommerce settings URL
     *
     * @return string URL to WooCommerce settings page
     */
    public static function getSettingsUrl(): string {
        return \admin_url('admin.php?page=wc-settings');
    }
    
    /**
     * Check if WooCommerce admin order page
     *
     * @return bool True if on WooCommerce orders admin page
     */
    public static function isOrdersAdminPage(): bool {
        global $pagenow;
        
        if ($pagenow !== 'edit.php') {
            return false;
        }
        
        return isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order';
    }
    
    /**
     * Get database table prefix for orders
     *
     * Returns the appropriate table prefix based on whether HPOS is enabled.
     *
     * @return string Table prefix
     */
    public static function getOrdersTablePrefix(): string {
        global $wpdb;
        
        if (self::isHposEnabled()) {
            return $wpdb->prefix . 'wc_orders';
        }
        
        return $wpdb->prefix . 'posts';
    }
    
    /**
     * Get order count query field name
     *
     * Returns the appropriate field name for order queries based on HPOS status.
     *
     * @return string Field name for status column
     */
    public static function getOrderStatusField(): string {
        return self::isHposEnabled() ? 'status' : 'post_status';
    }
    
    /**
     * Get order date field name
     *
     * Returns the appropriate field name for order date queries based on HPOS status.
     *
     * @return string Field name for date column
     */
    public static function getOrderDateField(): string {
        return self::isHposEnabled() ? 'date_created_gmt' : 'post_date';
    }
}


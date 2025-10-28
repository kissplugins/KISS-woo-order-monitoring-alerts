<?php
/**
 * Standard Order Query Class
 * 
 * Standard implementation for querying WooCommerce orders
 * with basic caching and error handling.
 * 
 * @package KissPlugins\WooOrderMonitor\Monitoring\Query
 * @since 1.5.0
 */

namespace KissPlugins\WooOrderMonitor\Monitoring\Query;

/**
 * Standard Order Query Class
 * 
 * Provides reliable order counting with basic performance optimizations.
 * This is the default query implementation.
 */
class OrderQuery implements QueryInterface {
    
    /**
     * Cache group for order queries
     * 
     * @var string
     */
    private $cache_group = 'woo-order-monitor';
    
    /**
     * Cache duration in seconds
     * 
     * @var int
     */
    private $cache_duration = 60; // 1 minute
    
    /**
     * Valid order statuses to count
     * 
     * @var array
     */
    private $valid_statuses = ['wc-completed', 'wc-processing'];
    
    /**
     * Get order count for a specific time period
     * 
     * @param int $minutes Number of minutes to look back
     * @return int|false Order count or false on error
     */
    public function getOrderCount(int $minutes) {
        // Validate input
        if ($minutes <= 0) {
            return false;
        }
        
        // Check cache first
        $cache_key = $this->getCacheKey('count', $minutes);
        $cached = wp_cache_get($cache_key, $this->cache_group);
        
        if (false !== $cached) {
            return $cached;
        }
        
        // Execute query
        $count = $this->executeCountQuery($minutes);
        
        // Cache result if valid
        if (false !== $count) {
            wp_cache_set($cache_key, $count, $this->cache_group, $this->cache_duration);
        }
        
        return $count;
    }
    
    /**
     * Get detailed order statistics
     * 
     * @param int $minutes Number of minutes to look back
     * @return array Order statistics
     */
    public function getOrderStats(int $minutes): array {
        // Validate input
        if ($minutes <= 0) {
            return [];
        }
        
        // Check cache first
        $cache_key = $this->getCacheKey('stats', $minutes);
        $cached = wp_cache_get($cache_key, $this->cache_group);
        
        if (false !== $cached) {
            return $cached;
        }
        
        // Execute stats query
        $stats = $this->executeStatsQuery($minutes);
        
        // Cache result
        wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_duration);
        
        return $stats;
    }
    
    /**
     * Execute the count query
     * 
     * @param int $minutes Number of minutes to look back
     * @return int|false Order count or false on error
     */
    private function executeCountQuery(int $minutes) {
        global $wpdb;
        
        try {
            // Calculate start time
            $start_time = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
            
            // Prepare query
            $query = $wpdb->prepare("
                SELECT COUNT(DISTINCT p.ID) as order_count
                FROM {$wpdb->posts} p
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN (" . $this->getStatusPlaceholders() . ")
                AND p.post_date >= %s
            ", array_merge($this->valid_statuses, [$start_time]));
            
            // Execute query
            $result = $wpdb->get_var($query);
            
            // Check for database errors
            if ($wpdb->last_error) {
                error_log('[WooCommerce Order Monitor] Database error in OrderQuery: ' . $wpdb->last_error);
                return false;
            }
            
            return intval($result);
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in OrderQuery::executeCountQuery: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Execute the statistics query
     * 
     * @param int $minutes Number of minutes to look back
     * @return array Order statistics
     */
    private function executeStatsQuery(int $minutes): array {
        global $wpdb;
        
        try {
            // Calculate start time
            $start_time = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
            
            // Prepare query for detailed stats
            $query = $wpdb->prepare("
                SELECT 
                    COUNT(DISTINCT p.ID) as total_orders,
                    COUNT(CASE WHEN p.post_status = 'wc-completed' THEN 1 END) as completed_orders,
                    COUNT(CASE WHEN p.post_status = 'wc-processing' THEN 1 END) as processing_orders,
                    MIN(p.post_date) as first_order_time,
                    MAX(p.post_date) as last_order_time
                FROM {$wpdb->posts} p
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN (" . $this->getStatusPlaceholders() . ")
                AND p.post_date >= %s
            ", array_merge($this->valid_statuses, [$start_time]));
            
            // Execute query
            $result = $wpdb->get_row($query, ARRAY_A);
            
            // Check for database errors
            if ($wpdb->last_error) {
                error_log('[WooCommerce Order Monitor] Database error in OrderQuery stats: ' . $wpdb->last_error);
                return [];
            }
            
            // Format results
            return [
                'total_orders' => intval($result['total_orders'] ?? 0),
                'completed_orders' => intval($result['completed_orders'] ?? 0),
                'processing_orders' => intval($result['processing_orders'] ?? 0),
                'first_order_time' => $result['first_order_time'] ?? null,
                'last_order_time' => $result['last_order_time'] ?? null,
                'time_period_minutes' => $minutes,
                'query_time' => current_time('mysql'),
                'cache_enabled' => true
            ];
            
        } catch (\Exception $e) {
            error_log('[WooCommerce Order Monitor] Exception in OrderQuery::executeStatsQuery: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get placeholders for status values in prepared statements
     * 
     * @return string Comma-separated placeholders
     */
    private function getStatusPlaceholders(): string {
        return implode(',', array_fill(0, count($this->valid_statuses), '%s'));
    }
    
    /**
     * Generate cache key
     * 
     * @param string $type Query type (count, stats)
     * @param int $minutes Time period
     * @return string Cache key
     */
    private function getCacheKey(string $type, int $minutes): string {
        return "woom_order_{$type}_{$minutes}";
    }
    
    /**
     * Check if the query implementation is available
     * 
     * @return bool True if the query can be executed
     */
    public function isAvailable(): bool {
        global $wpdb;
        
        // Check if database is available
        if (!$wpdb) {
            return false;
        }
        
        // Check if posts table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->posts}'") === $wpdb->posts;
        
        return $table_exists;
    }
    
    /**
     * Get the name of this query implementation
     * 
     * @return string Query implementation name
     */
    public function getName(): string {
        return 'Standard Order Query';
    }
    
    /**
     * Get performance characteristics of this query
     * 
     * @return array Performance info
     */
    public function getPerformanceInfo(): array {
        return [
            'cache_enabled' => true,
            'cache_duration' => $this->cache_duration,
            'estimated_speed' => 'medium',
            'memory_usage' => 'low',
            'database_load' => 'medium',
            'supports_hpos' => false,
            'recommended_for' => 'Standard WooCommerce installations'
        ];
    }
    
    /**
     * Clear query cache
     * 
     * @param int|null $minutes Specific time period to clear, or null for all
     * @return void
     */
    public function clearCache(?int $minutes = null): void {
        if ($minutes !== null) {
            // Clear specific cache entries
            wp_cache_delete($this->getCacheKey('count', $minutes), $this->cache_group);
            wp_cache_delete($this->getCacheKey('stats', $minutes), $this->cache_group);
        } else {
            // Clear entire cache group
            wp_cache_flush_group($this->cache_group);
        }
    }
    
    /**
     * Set valid order statuses
     * 
     * @param array $statuses Array of valid order statuses
     * @return void
     */
    public function setValidStatuses(array $statuses): void {
        $this->valid_statuses = $statuses;
    }
    
    /**
     * Get valid order statuses
     * 
     * @return array Valid order statuses
     */
    public function getValidStatuses(): array {
        return $this->valid_statuses;
    }
}

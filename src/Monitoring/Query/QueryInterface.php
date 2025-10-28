<?php
/**
 * Query Interface
 * 
 * Defines the contract for order query implementations.
 * 
 * @package KissPlugins\WooOrderMonitor\Monitoring\Query
 * @since 1.5.0
 */

namespace KissPlugins\WooOrderMonitor\Monitoring\Query;

/**
 * Query Interface
 * 
 * Contract for all order query implementations to ensure
 * consistent behavior and easy testing/mocking.
 */
interface QueryInterface {
    
    /**
     * Get order count for a specific time period
     * 
     * @param int $minutes Number of minutes to look back
     * @return int|false Order count or false on error
     */
    public function getOrderCount(int $minutes);
    
    /**
     * Get detailed order statistics
     * 
     * @param int $minutes Number of minutes to look back
     * @return array Order statistics or empty array on error
     */
    public function getOrderStats(int $minutes): array;
    
    /**
     * Check if the query implementation is available
     * 
     * @return bool True if the query can be executed
     */
    public function isAvailable(): bool;
    
    /**
     * Get the name of this query implementation
     * 
     * @return string Query implementation name
     */
    public function getName(): string;
    
    /**
     * Get performance characteristics of this query
     * 
     * @return array Performance info (cache_enabled, estimated_speed, etc.)
     */
    public function getPerformanceInfo(): array;
}

<?php
/**
 * Event System for FSM
 * 
 * Provides publish/subscribe pattern for FSM state changes and validation events.
 * 
 * @package KissPlugins\WooOrderMonitor\Core
 * @since 1.6.0
 */

namespace KissPlugins\WooOrderMonitor\Core;

/**
 * Event System Class
 * 
 * Manages event listeners and dispatching for the FSM system.
 * Provides a centralized way to handle state change notifications,
 * validation events, and other FSM-related events.
 */
class EventSystem {
    
    /**
     * Event listeners registry
     * 
     * @var array
     */
    private static $listeners = [];
    
    /**
     * Event history for debugging
     * 
     * @var array
     */
    private static $event_history = [];
    
    /**
     * Maximum events to keep in history
     * 
     * @var int
     */
    private static $max_history = 100;
    
    /**
     * Add event listener
     * 
     * @param string $event Event name
     * @param callable $callback Callback function
     * @param int $priority Priority (lower = earlier execution)
     * @return bool Success
     */
    public static function addEventListener(string $event, callable $callback, int $priority = 10): bool {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }
        
        if (!isset(self::$listeners[$event][$priority])) {
            self::$listeners[$event][$priority] = [];
        }
        
        self::$listeners[$event][$priority][] = $callback;
        
        // Sort by priority
        ksort(self::$listeners[$event]);
        
        return true;
    }
    
    /**
     * Remove event listener
     * 
     * @param string $event Event name
     * @param callable $callback Callback to remove
     * @return bool Success
     */
    public static function removeEventListener(string $event, callable $callback): bool {
        if (!isset(self::$listeners[$event])) {
            return false;
        }
        
        foreach (self::$listeners[$event] as $priority => $callbacks) {
            $key = array_search($callback, $callbacks, true);
            if ($key !== false) {
                unset(self::$listeners[$event][$priority][$key]);
                
                // Clean up empty priority levels
                if (empty(self::$listeners[$event][$priority])) {
                    unset(self::$listeners[$event][$priority]);
                }
                
                // Clean up empty events
                if (empty(self::$listeners[$event])) {
                    unset(self::$listeners[$event]);
                }
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Dispatch event to all listeners
     * 
     * @param string $event Event name
     * @param array $data Event data
     * @return array Results from all listeners
     */
    public static function dispatch(string $event, array $data = []): array {
        $results = [];
        
        // Add to event history
        self::addToHistory($event, $data);
        
        if (!isset(self::$listeners[$event])) {
            return $results;
        }
        
        // Execute listeners in priority order
        foreach (self::$listeners[$event] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $result = call_user_func($callback, $data);
                    $results[] = [
                        'priority' => $priority,
                        'result' => $result,
                        'success' => true
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'priority' => $priority,
                        'result' => null,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    
                    // Log the error
                    error_log("[FSM Event] Error in {$event} listener: " . $e->getMessage());
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Check if event has listeners
     * 
     * @param string $event Event name
     * @return bool Has listeners
     */
    public static function hasListeners(string $event): bool {
        return isset(self::$listeners[$event]) && !empty(self::$listeners[$event]);
    }
    
    /**
     * Get all registered events
     * 
     * @return array Event names
     */
    public static function getRegisteredEvents(): array {
        return array_keys(self::$listeners);
    }
    
    /**
     * Get listener count for event
     * 
     * @param string $event Event name
     * @return int Listener count
     */
    public static function getListenerCount(string $event): int {
        if (!isset(self::$listeners[$event])) {
            return 0;
        }
        
        $count = 0;
        foreach (self::$listeners[$event] as $callbacks) {
            $count += count($callbacks);
        }
        
        return $count;
    }
    
    /**
     * Add event to history
     * 
     * @param string $event Event name
     * @param array $data Event data
     */
    private static function addToHistory(string $event, array $data): void {
        self::$event_history[] = [
            'event' => $event,
            'data' => $data,
            'timestamp' => time(),
            'microtime' => microtime(true)
        ];
        
        // Trim history if too long
        if (count(self::$event_history) > self::$max_history) {
            self::$event_history = array_slice(self::$event_history, -self::$max_history);
        }
    }
    
    /**
     * Get event history
     * 
     * @param int $limit Maximum events to return
     * @return array Event history
     */
    public static function getEventHistory(int $limit = 50): array {
        return array_slice(self::$event_history, -$limit);
    }
    
    /**
     * Clear event history
     */
    public static function clearEventHistory(): void {
        self::$event_history = [];
    }
    
    /**
     * Get event statistics
     * 
     * @return array Event statistics
     */
    public static function getEventStatistics(): array {
        $stats = [
            'total_events' => count(self::$event_history),
            'registered_event_types' => count(self::$listeners),
            'total_listeners' => 0,
            'event_counts' => [],
            'recent_events' => []
        ];
        
        // Count total listeners
        foreach (self::$listeners as $event => $priorities) {
            foreach ($priorities as $callbacks) {
                $stats['total_listeners'] += count($callbacks);
            }
        }
        
        // Count events by type
        foreach (self::$event_history as $event_record) {
            $event_name = $event_record['event'];
            if (!isset($stats['event_counts'][$event_name])) {
                $stats['event_counts'][$event_name] = 0;
            }
            $stats['event_counts'][$event_name]++;
        }
        
        // Get recent events (last 10)
        $stats['recent_events'] = array_slice(self::$event_history, -10);
        
        return $stats;
    }
    
    /**
     * Register default FSM event listeners
     */
    public static function registerDefaultListeners(): void {
        // Log state changes
        self::addEventListener('state_changed', function($data) {
            error_log("[FSM] State changed from {$data['from']} to {$data['to']}");
        }, 5);
        
        // Log validation errors
        self::addEventListener('validation_error', function($data) {
            error_log("[FSM] Validation error for {$data['key']}: {$data['message']}");
        }, 5);
        
        // Log rollbacks
        self::addEventListener('state_rollback', function($data) {
            error_log("[FSM] State rolled back from {$data['from']} to {$data['to']}");
        }, 5);
        
        // Log forced transitions
        self::addEventListener('state_forced', function($data) {
            error_log("[FSM] Forced transition from {$data['from']} to {$data['to']}");
        }, 5);
        
        // Update WordPress options when state changes
        self::addEventListener('state_changed', function($data) {
            \update_option('woom_fsm_last_state_change', [
                'from' => $data['from'],
                'to' => $data['to'],
                'timestamp' => time()
            ]);
        }, 10);
    }
    
    /**
     * Clear all listeners (for testing)
     */
    public static function clearAllListeners(): void {
        self::$listeners = [];
    }
}

<?php
/**
 * Settings State Machine
 * 
 * Manages plugin settings through a finite state machine pattern
 * to prevent configuration drift and ensure atomic updates.
 * 
 * @package KissPlugins\WooOrderMonitor\Core
 * @since 1.6.0
 */

namespace KissPlugins\WooOrderMonitor\Core;

/**
 * Settings State Machine Class
 * 
 * Implements FSM pattern for settings management:
 * - Single source of truth for all settings
 * - Atomic state transitions
 * - Validation gates between states
 * - Rollback capabilities
 * - Event-driven updates
 */
class SettingsStateMachine {
    
    /**
     * Possible states for the settings system
     */
    const STATE_UNINITIALIZED = 'uninitialized';
    const STATE_LOADING = 'loading';
    const STATE_VALIDATION_PENDING = 'validation_pending';
    const STATE_VALID = 'valid';
    const STATE_INVALID = 'invalid';
    const STATE_UPDATING = 'updating';
    const STATE_MONITORING = 'monitoring';
    
    /**
     * Current state
     * 
     * @var string
     */
    private $current_state = self::STATE_UNINITIALIZED;
    
    /**
     * Previous state (for rollback)
     * 
     * @var string
     */
    private $previous_state = null;
    
    /**
     * Current settings data
     * 
     * @var array
     */
    private $settings_data = [];
    
    /**
     * Settings backup (for rollback)
     * 
     * @var array
     */
    private $settings_backup = [];
    
    /**
     * State transition rules
     * 
     * @var array
     */
    private $allowed_transitions = [
        self::STATE_UNINITIALIZED => [self::STATE_LOADING],
        self::STATE_LOADING => [self::STATE_VALIDATION_PENDING],
        self::STATE_VALIDATION_PENDING => [self::STATE_VALID, self::STATE_INVALID],
        self::STATE_VALID => [self::STATE_UPDATING, self::STATE_MONITORING],
        self::STATE_INVALID => [self::STATE_UPDATING],
        self::STATE_UPDATING => [self::STATE_VALIDATION_PENDING],
        self::STATE_MONITORING => [self::STATE_VALID, self::STATE_UPDATING]
    ];
    
    /**
     * Event listeners
     * 
     * @var array
     */
    private $listeners = [];
    
    /**
     * Singleton instance
     * 
     * @var SettingsStateMachine
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return SettingsStateMachine
     */
    public static function getInstance(): SettingsStateMachine {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor for singleton
     */
    private function __construct() {
        // Initialize with defaults from SettingsDefaults
        $this->settings_data = SettingsDefaults::getRuntimeDefaults();
    }
    
    /**
     * Get current state
     * 
     * @return string
     */
    public function getCurrentState(): string {
        return $this->current_state;
    }
    
    /**
     * Transition to new state
     * 
     * @param string $new_state Target state
     * @param array $data Optional data for the transition
     * @return bool Success
     * @throws \InvalidArgumentException If transition is not allowed
     */
    public function transitionTo(string $new_state, array $data = []): bool {
        // Check if transition is allowed
        if (!$this->isTransitionAllowed($this->current_state, $new_state)) {
            throw new \InvalidArgumentException(
                "Transition from {$this->current_state} to {$new_state} is not allowed"
            );
        }
        
        // Store previous state for rollback
        $this->previous_state = $this->current_state;
        
        // Execute state-specific logic
        $success = $this->executeStateTransition($new_state, $data);
        
        if ($success) {
            $this->current_state = $new_state;
            $this->notifyListeners('state_changed', [
                'from' => $this->previous_state,
                'to' => $new_state,
                'data' => $data
            ]);
        }
        
        return $success;
    }
    
    /**
     * Check if transition is allowed
     * 
     * @param string $from Current state
     * @param string $to Target state
     * @return bool
     */
    private function isTransitionAllowed(string $from, string $to): bool {
        return isset($this->allowed_transitions[$from]) && 
               in_array($to, $this->allowed_transitions[$from]);
    }
    
    /**
     * Execute state-specific transition logic
     * 
     * @param string $new_state Target state
     * @param array $data Transition data
     * @return bool Success
     */
    private function executeStateTransition(string $new_state, array $data): bool {
        switch ($new_state) {
            case self::STATE_LOADING:
                return $this->loadSettings();
                
            case self::STATE_VALIDATION_PENDING:
                return $this->prepareValidation($data);
                
            case self::STATE_VALID:
                return $this->validateSettings();
                
            case self::STATE_INVALID:
                return $this->handleInvalidSettings();
                
            case self::STATE_UPDATING:
                return $this->prepareUpdate($data);
                
            case self::STATE_MONITORING:
                return $this->enableMonitoring();
                
            default:
                return false;
        }
    }
    
    /**
     * Load settings from database
     * 
     * @return bool Success
     */
    private function loadSettings(): bool {
        try {
            // Backup current settings
            $this->settings_backup = $this->settings_data;
            
            // Load from database with defaults fallback
            $defaults = SettingsDefaults::getRuntimeDefaults();
            foreach ($defaults as $key => $default_value) {
                $this->settings_data[$key] = \get_option("woom_{$key}", $default_value);
            }
            
            return true;
        } catch (\Exception $e) {
            // Rollback on failure
            $this->settings_data = $this->settings_backup;
            return false;
        }
    }
    
    /**
     * Prepare for validation
     * 
     * @param array $data New settings data
     * @return bool Success
     */
    private function prepareValidation(array $data): bool {
        if (!empty($data)) {
            // Backup current settings
            $this->settings_backup = $this->settings_data;
            
            // Merge new data
            $this->settings_data = array_merge($this->settings_data, $data);
        }
        
        return true;
    }
    
    /**
     * Validate current settings
     * 
     * @return bool Valid
     */
    private function validateSettings(): bool {
        $validation_rules = SettingsDefaults::getValidationRules();
        
        foreach ($this->settings_data as $key => $value) {
            if (isset($validation_rules[$key])) {
                if (!$this->validateSetting($key, $value, $validation_rules[$key])) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Validate individual setting
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param array $rules Validation rules
     * @return bool Valid
     */
    private function validateSetting(string $key, $value, array $rules): bool {
        // Implementation would include type checking, range validation, etc.
        // This is a simplified version
        
        if (isset($rules['type'])) {
            switch ($rules['type']) {
                case 'int':
                    if (!is_numeric($value)) return false;
                    $int_value = intval($value);
                    if (isset($rules['min']) && $int_value < $rules['min']) return false;
                    if (isset($rules['max']) && $int_value > $rules['max']) return false;
                    break;
                    
                case 'string':
                    if (isset($rules['values']) && !in_array($value, $rules['values'])) return false;
                    break;
                    
                case 'time':
                    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value)) return false;
                    break;
            }
        }
        
        return true;
    }
    
    /**
     * Handle invalid settings
     * 
     * @return bool Success
     */
    private function handleInvalidSettings(): bool {
        // Rollback to previous valid state
        if (!empty($this->settings_backup)) {
            $this->settings_data = $this->settings_backup;
        }
        
        return true;
    }
    
    /**
     * Prepare for update
     * 
     * @param array $data New settings
     * @return bool Success
     */
    private function prepareUpdate(array $data): bool {
        // This would prepare the update transaction
        return !empty($data);
    }
    
    /**
     * Enable monitoring
     * 
     * @return bool Success
     */
    private function enableMonitoring(): bool {
        return $this->settings_data['enabled'] === 'yes';
    }
    
    /**
     * Get setting value (READ-ONLY access)
     * 
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get(string $key, $default = null) {
        return $this->settings_data[$key] ?? $default;
    }
    
    /**
     * Get all settings (READ-ONLY access)
     * 
     * @return array All settings
     */
    public function getAll(): array {
        return $this->settings_data;
    }
    
    /**
     * Add event listener
     * 
     * @param string $event Event name
     * @param callable $callback Callback function
     */
    public function addEventListener(string $event, callable $callback): void {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = $callback;
    }
    
    /**
     * Notify event listeners
     * 
     * @param string $event Event name
     * @param array $data Event data
     */
    private function notifyListeners(string $event, array $data): void {
        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $callback) {
                call_user_func($callback, $data);
            }
        }
    }
    
    /**
     * Rollback to previous state
     * 
     * @return bool Success
     */
    public function rollback(): bool {
        if ($this->previous_state !== null) {
            $this->current_state = $this->previous_state;
            $this->settings_data = $this->settings_backup;
            return true;
        }
        return false;
    }
}

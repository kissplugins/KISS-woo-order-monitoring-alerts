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

use KissPlugins\WooOrderMonitor\Core\EventSystem;

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
     * Validation errors
     *
     * @var array
     */
    private $validation_errors = [];
    
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

            // Persist the state change
            $this->persistState();

            // Notify via event system
            EventSystem::dispatch('state_changed', [
                'from' => $this->previous_state,
                'to' => $new_state,
                'data' => $data,
                'timestamp' => time()
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
        try {
            // Type validation
            if (isset($rules['type'])) {
                switch ($rules['type']) {
                    case 'int':
                        if (!is_numeric($value)) {
                            $this->logValidationError($key, "Value must be numeric, got: " . gettype($value));
                            return false;
                        }
                        $int_value = intval($value);
                        if (isset($rules['min']) && $int_value < $rules['min']) {
                            $this->logValidationError($key, "Value {$int_value} is below minimum {$rules['min']}");
                            return false;
                        }
                        if (isset($rules['max']) && $int_value > $rules['max']) {
                            $this->logValidationError($key, "Value {$int_value} is above maximum {$rules['max']}");
                            return false;
                        }
                        break;

                    case 'string':
                        if (!is_string($value)) {
                            $this->logValidationError($key, "Value must be string, got: " . gettype($value));
                            return false;
                        }
                        if (isset($rules['values']) && !in_array($value, $rules['values'])) {
                            $this->logValidationError($key, "Value '{$value}' not in allowed values: " . implode(', ', $rules['values']));
                            return false;
                        }
                        break;

                    case 'time':
                        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
                            $this->logValidationError($key, "Invalid time format '{$value}', expected HH:MM");
                            return false;
                        }
                        break;

                    case 'email_list':
                        $emails = array_map('trim', explode(',', $value));
                        foreach ($emails as $email) {
                            if (!empty($email) && !is_email($email)) {
                                $this->logValidationError($key, "Invalid email address: {$email}");
                                return false;
                            }
                        }
                        break;

                    case 'url':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                            $this->logValidationError($key, "Invalid URL format: {$value}");
                            return false;
                        }
                        break;

                    case 'date':
                        if (!empty($value) && !strtotime($value)) {
                            $this->logValidationError($key, "Invalid date format: {$value}");
                            return false;
                        }
                        break;
                }
            }

            // Business logic validation
            return $this->validateBusinessLogic($key, $value);

        } catch (\Exception $e) {
            $this->logValidationError($key, "Validation exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate business logic rules
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Valid
     */
    private function validateBusinessLogic(string $key, $value): bool {
        switch ($key) {
            case 'peak_end':
                // Ensure peak_end is after peak_start
                if (isset($this->settings_data['peak_start'])) {
                    $start_time = strtotime($this->settings_data['peak_start']);
                    $end_time = strtotime($value);
                    if ($end_time <= $start_time) {
                        $this->logValidationError($key, "Peak end time must be after peak start time");
                        return false;
                    }
                }
                break;

            case 'threshold_peak':
            case 'threshold_offpeak':
                // Ensure thresholds are reasonable
                if (intval($value) < 0) {
                    $this->logValidationError($key, "Threshold cannot be negative");
                    return false;
                }
                break;
        }

        return true;
    }

    /**
     * Log validation error
     *
     * @param string $key Setting key
     * @param string $message Error message
     */
    private function logValidationError(string $key, string $message): void {
        $log_message = "[FSM Validation] {$key}: {$message}";
        error_log($log_message);

        // Store validation error for retrieval
        $errors = \get_option('woom_fsm_validation_errors', []);
        if (!is_array($errors)) {
            $errors = [];
        }

        $errors[] = [
            'key' => $key,
            'message' => $message,
            'timestamp' => time()
        ];

        \update_option('woom_fsm_validation_errors', $errors);

        // Notify via event system
        EventSystem::dispatch('validation_error', [
            'key' => $key,
            'message' => $message,
            'timestamp' => time()
        ]);
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
     * Update multiple settings atomically
     *
     * @param array $new_settings Settings to update
     * @return bool Success
     */
    public function updateSettings(array $new_settings): bool {
        try {
            // Transition to updating state
            if (!$this->transitionTo('updating', $new_settings)) {
                return false;
            }

            // Update each setting in the settings data
            foreach ($new_settings as $key => $value) {
                $this->settings_data[$key] = $value;

                // Also update WordPress option for backward compatibility
                $option_key = 'woom_' . $key;
                update_option($option_key, $value);
            }

            // Transition to monitoring state if all successful
            return $this->transitionTo('monitoring');

        } catch (\Exception $e) {
            error_log("[FSM] updateSettings failed: " . $e->getMessage());
            $this->validation_errors[] = "Update failed: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Add event listener (delegates to EventSystem)
     *
     * @param string $event Event name
     * @param callable $callback Callback function
     * @param int $priority Priority level
     */
    public function addEventListener(string $event, callable $callback, int $priority = 10): void {
        EventSystem::addEventListener($event, $callback, $priority);
    }
    
    /**
     * Rollback to previous state
     *
     * @return bool Success
     */
    public function rollback(): bool {
        if ($this->previous_state !== null) {
            $old_state = $this->current_state;
            $this->current_state = $this->previous_state;
            $this->settings_data = $this->settings_backup;

            // Persist the rollback
            $this->persistState();

            // Notify via event system
            EventSystem::dispatch('state_rollback', [
                'from' => $old_state,
                'to' => $this->current_state,
                'timestamp' => time()
            ]);

            return true;
        }
        return false;
    }

    /**
     * Persist current state to database
     *
     * @return bool Success
     */
    private function persistState(): bool {
        try {
            // Save current state
            \update_option('woom_fsm_state', $this->current_state);

            // Save settings data
            foreach ($this->settings_data as $key => $value) {
                \update_option("woom_{$key}", $value);
            }

            // Save state metadata
            \update_option('woom_fsm_metadata', [
                'last_transition' => time(),
                'previous_state' => $this->previous_state,
                'transition_count' => \get_option('woom_fsm_transition_count', 0) + 1
            ]);

            return true;
        } catch (\Exception $e) {
            error_log("[FSM] Failed to persist state: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Load state from database
     *
     * @return bool Success
     */
    private function loadStateFromDatabase(): bool {
        try {
            // Load FSM state
            $saved_state = \get_option('woom_fsm_state', self::STATE_UNINITIALIZED);
            if ($this->isValidState($saved_state)) {
                $this->current_state = $saved_state;
            }

            // Load metadata
            $metadata = \get_option('woom_fsm_metadata', []);
            if (isset($metadata['previous_state'])) {
                $this->previous_state = $metadata['previous_state'];
            }

            return true;
        } catch (\Exception $e) {
            error_log("[FSM] Failed to load state: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if state is valid
     *
     * @param string $state State to check
     * @return bool Valid
     */
    private function isValidState(string $state): bool {
        $valid_states = [
            self::STATE_UNINITIALIZED,
            self::STATE_LOADING,
            self::STATE_VALIDATION_PENDING,
            self::STATE_VALID,
            self::STATE_INVALID,
            self::STATE_UPDATING,
            self::STATE_MONITORING
        ];

        return in_array($state, $valid_states);
    }

    /**
     * Initialize FSM from database or defaults
     *
     * @return bool Success
     */
    public function initialize(): bool {
        try {
            // Load previous state if exists
            $this->loadStateFromDatabase();

            // If uninitialized, start the initialization process
            if ($this->current_state === self::STATE_UNINITIALIZED) {
                return $this->transitionTo(self::STATE_LOADING);
            }

            // Validate current state is still valid
            if ($this->current_state === self::STATE_VALID) {
                // Re-validate settings to ensure they're still valid
                return $this->transitionTo(self::STATE_VALIDATION_PENDING);
            }

            return true;
        } catch (\Exception $e) {
            error_log("[FSM] Initialization failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get state metadata
     *
     * @return array State metadata
     */
    public function getStateMetadata(): array {
        $metadata = \get_option('woom_fsm_metadata', []);

        return array_merge([
            'current_state' => $this->current_state,
            'previous_state' => $this->previous_state,
            'last_transition' => 0,
            'transition_count' => 0,
            'settings_count' => count($this->settings_data)
        ], $metadata);
    }

    /**
     * Force state transition (for emergency recovery)
     *
     * @param string $new_state Target state
     * @param bool $skip_validation Skip validation checks
     * @return bool Success
     */
    public function forceTransition(string $new_state, bool $skip_validation = false): bool {
        if (!$skip_validation && !$this->isValidState($new_state)) {
            return false;
        }

        $old_state = $this->current_state;
        $this->previous_state = $old_state;
        $this->current_state = $new_state;

        // Persist the forced transition
        $this->persistState();

        // Notify via event system
        EventSystem::dispatch('state_forced', [
            'from' => $old_state,
            'to' => $new_state,
            'timestamp' => time(),
            'skip_validation' => $skip_validation
        ]);

        return true;
    }

    /**
     * Get validation errors from last validation attempt
     *
     * @return array Validation errors
     */
    public function getValidationErrors(): array {
        $errors = \get_option('woom_fsm_validation_errors', []);
        return is_array($errors) ? $errors : [];
    }

    /**
     * Clear validation errors
     */
    public function clearValidationErrors(): void {
        \update_option('woom_fsm_validation_errors', []);
    }
}

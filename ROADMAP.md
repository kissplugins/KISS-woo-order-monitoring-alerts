# PSR-4 Autoloading

## **Short Answer: Not Yet - Wait Until Phase 2**

For the MVP of this monitoring plugin, PSR-4 autoloading would actually be **premature optimization**. Here's why:

### **Current Code Analysis:**
- **Single file, ~800 lines** - Well within manageable limits
- **3-4 classes total** - Main class, Action Scheduler integration, CLI commands, Optimized Query helper
- **Simple dependency chain** - No complex class relationships
- **Performance-critical** - Monitoring runs every 15 minutes via cron

### **PSR-4 Benefits (Generally):**
✅ **Better organization** - Each class in its own file
✅ **Lazy loading** - Classes loaded only when needed
✅ **Modern PHP practices** - Industry standard structure
✅ **Easier testing** - Better isolation for unit tests
✅ **Team scalability** - Easier for multiple developers

### **Why NOT for MVP:**

1. **Performance Overhead**
   - Autoloader registration adds ~5-10ms on every page load
   - Composer autoloader file I/O for class resolution
   - For a monitoring plugin that runs via cron, you want minimal overhead
   - Current single-file approach = one file read vs multiple with PSR-4

2. **Reliability Concerns**
   - More moving parts = more potential failure points
   - Composer dependency could fail during critical monitoring
   - Single file is more resilient for critical infrastructure

3. **Deployment Complexity**
   - Requires composer install/dump-autoload during deployment
   - Some shared hosts have Composer issues
   - Single file = simple FTP upload works everywhere

4. **Current Code Characteristics**
   ```php
   // Your monitoring runs like this:
   - WP Cron triggers → Load plugin → Check orders → Send alert
   // With PSR-4:
   - WP Cron triggers → Load autoloader → Resolve classes → Load files → Check orders → Send alert
   ```

### **When to Convert to PSR-4:**

Convert when you hit these triggers:

1. **Code exceeds 1,500 lines** or **5+ classes**
2. **Adding Phase 2 features** (Slack, SMS, analytics)
3. **Multiple developers** working on it
4. **Need for unit testing** infrastructure
5. **Adding namespace-heavy integrations** (AWS SDK, Twilio, etc.)

### **Recommended Architecture Evolution:**

```
MVP (Current) → Phase 1.5 → Phase 2.0
----------------------------------------------------------------
Single file    → Basic split  → Full PSR-4
(800 lines)      (3-4 files)    (Composer/autoload)
                 No autoloader   Full namespace structure
                                Unit tests
                                Multiple integrations
```

### **If You Did Want PSR-4 Now:**

Here's what the structure would look like:
```
woocommerce-order-monitor/
├── composer.json
├── woocommerce-order-monitor.php (bootstrap only)
├── src/
│   ├── Core/
│   │   ├── Monitor.php
│   │   ├── Scheduler.php
│   │   └── Installer.php
│   ├── Admin/
│   │   ├── Settings.php
│   │   └── Notifications.php
│   ├── Integrations/
│   │   ├── ActionScheduler.php
│   │   └── WPCli.php
│   └── Queries/
│       └── OrderQuery.php
```

### **My Recommendation:**

**Keep the single-file approach for MVP** because:

1. **Monitoring reliability is paramount** - Fewer dependencies = fewer failure points
2. **Performance matters** - This runs every 15 minutes forever
3. **Fast deployment** - Get it into production quickly
4. **Easy debugging** - One file to check when issues arise

**Plan for PSR-4 in Version 2.0** when adding:
- Slack webhooks
- SMS via Twilio
- Historical data tracking
- Multiple notification channels
- Dashboard widgets
- REST API endpoints

The current monolithic approach is actually a **strength** for a critical monitoring tool. It's the same reason why monitoring services like Nagios plugins are often single scripts - reliability over elegance for infrastructure monitoring.

Would you like me to create a migration plan for when you do eventually want to move to PSR-4, or should we focus on getting the MVP deployed first?

===

# FSM in v2.0

An FSM would be overkill for the MVP, but becomes valuable when you add alert fatigue prevention, escalation, and recovery detection.

### **Current (Implicit) State Machine:**

Your plugin already has an implicit state machine, just not formalized:

```
MONITORING_ACTIVE ←→ MONITORING_DISABLED
       ↓
   CHECK_ORDERS
       ↓
   [threshold_met] → NORMAL
       ↓
   [threshold_breach] → ALERT_SENT
```

### **Where FSM Would Add Value (Version 2.0+):**

## **1. Alert State Management**

```
States:
- NORMAL (everything fine)
- WARNING (approaching threshold)
- CRITICAL (below threshold)
- ALERTING (sending notifications)
- COOLDOWN (preventing alert spam)
- ESCALATED (persistent issue)
- RECOVERING (orders increasing)
- RECOVERED (back to normal)

Transitions:
- NORMAL → WARNING (orders < threshold * 1.2)
- WARNING → CRITICAL (orders < threshold)
- CRITICAL → ALERTING (trigger notification)
- ALERTING → COOLDOWN (alert sent)
- COOLDOWN → ESCALATED (still critical after X checks)
- CRITICAL → RECOVERING (orders increasing but still below)
- RECOVERING → RECOVERED (orders > threshold)
```

## **2. Benefits of FSM Implementation:**

### **Prevents Alert Fatigue:**
```php
class AlertStateMachine {
    private $states = [
        'NORMAL' => [
            'can_alert' => false,
            'check_interval' => 900, // 15 min
        ],
        'CRITICAL' => [
            'can_alert' => true,
            'check_interval' => 300, // 5 min when critical
        ],
        'COOLDOWN' => [
            'can_alert' => false,
            'check_interval' => 300,
            'duration' => 3600, // 1 hour cooldown
        ],
        'ESCALATED' => [
            'can_alert' => true,
            'check_interval' => 300,
            'notify_admin' => true, // Different recipients
        ]
    ];
}
```

### **Smart Recovery Detection:**
```php
// Instead of binary alert/no-alert:
if ($state === 'CRITICAL' && $orders > $last_orders) {
    $fsm->transitionTo('RECOVERING');
    // Send different message: "Orders recovering but still below threshold"
}
```

### **Progressive Escalation:**
```php
// After 3 consecutive critical states:
if ($consecutive_critical >= 3) {
    $fsm->transitionTo('ESCALATED');
    // Now alerts go to senior admins, maybe triggers automatic mitigations
}
```

## **3. Real-World Scenarios Where FSM Helps:**

### **Scenario A: Temporary Blip**
```
Normal → Critical (1 bad check) → Normal
Result: No alert sent (requires 2 consecutive critical states)
```

### **Scenario B: Real Outage**
```
Normal → Critical → Critical → ALERTING → COOLDOWN → Still Critical → ESCALATED
Result: Initial alert, then escalation to senior staff
```

### **Scenario C: Flapping**
```
Critical → Normal → Critical → Normal (rapid changes)
FSM detects pattern → UNSTABLE state → Different alert about instability
```

## **4. Implementation Example for Version 2.0:**

```php
class OrderMonitorFSM {
    private string $current_state;
    private array $state_history = [];
    private int $state_entered_at;
    
    const TRANSITIONS = [
        'NORMAL' => [
            'CHECK_THRESHOLD' => ['WARNING', 'CRITICAL', 'NORMAL'],
        ],
        'WARNING' => [
            'CHECK_THRESHOLD' => ['NORMAL', 'CRITICAL', 'WARNING'],
        ],
        'CRITICAL' => [
            'SEND_ALERT' => 'ALERTING',
            'CHECK_THRESHOLD' => ['RECOVERING', 'CRITICAL'],
        ],
        'ALERTING' => [
            'ALERT_SENT' => 'COOLDOWN',
        ],
        'COOLDOWN' => [
            'TIMEOUT' => 'CRITICAL',
            'RECOVERED' => 'NORMAL',
        ],
    ];
    
    public function handleOrderCheck($order_count, $threshold) {
        $event = $this->determineEvent($order_count, $threshold);
        $new_state = $this->transition($event);
        
        return $this->getStateActions($new_state);
    }
    
    private function determineEvent($count, $threshold) {
        $ratio = $count / $threshold;
        
        if ($ratio >= 1.0) return 'THRESHOLD_MET';
        if ($ratio >= 0.8) return 'THRESHOLD_WARNING';
        if ($ratio < 0.5) return 'THRESHOLD_CRITICAL';
        
        return 'THRESHOLD_BREACH';
    }
}
```

## **5. When NOT to Use FSM (Current MVP):**

Your MVP shouldn't use FSM because:
- **Single state transition**: Normal → Alert
- **No escalation logic**
- **No cooldown periods**
- **No recovery tracking**
- **Added complexity without benefit**

## **6. Migration Path:**

### **MVP (Current):**
```php
if ($orders < $threshold) {
    send_alert();
}
```

### **Version 1.5 (Simple States):**
```php
$state = get_option('woom_current_state', 'normal');
if ($orders < $threshold && $state !== 'cooldown') {
    send_alert();
    update_option('woom_current_state', 'cooldown');
    update_option('woom_cooldown_until', time() + 3600);
}
```

### **Version 2.0 (Full FSM):**
```php
$fsm = new OrderMonitorFSM();
$actions = $fsm->process([
    'order_count' => $orders,
    'threshold' => $threshold,
    'history' => $recent_checks
]);

foreach ($actions as $action) {
    $action->execute(); // Send alerts, update intervals, etc.
}
```

## **My Recommendation:**

1. **MVP**: Ship without FSM - keep it simple
2. **Version 1.1**: Add basic cooldown state (2 states only)
3. **Version 2.0**: Implement full FSM when adding:
   - Alert fatigue prevention
   - Escalation chains
   - Recovery notifications
   - Adaptive thresholds
   - Multiple notification channels with different rules

The FSM becomes valuable when you need to answer: *"Should I alert, who should I alert, and how urgently?"* rather than just *"Should I alert?"*

Would you like me to sketch out what the FSM-based version would look like for a future iteration, or should we stick with the simple approach for MVP?
# PSR-4 Autoloading Transition Plan
## KISS WooCommerce Order Monitor

### Overall Status
PHASE 1: ✅ COMPLETED
PHASE 2: ✅ COMPLETED
PHASE 3: ✅ COMPLETED
PHASE 4: ✅ COMPLETED (Production Ready)
PHASE 5: ✅ COMPLETED (December 10, 2025)
PHASE 6: ✅ COMPLETED (December 10, 2025)
PHASE 7: Not started

### Overview

This document outlines the plan to transition the KISS WooCommerce Order Monitor plugin from a single-file architecture to a modern PSR-4 autoloaded structure. This will improve code organization, maintainability, and testing capabilities.

### Current State Analysis

**Current Architecture:**
- Single file: `kiss-woo-order-monitoring-alerts.php` (1,900+ lines)
- All classes defined in one file
- Manual class loading
- No namespace structure

**Current Classes:**
1. `WooCommerce_Order_Monitor` (Main plugin class)
2. `WOOM_Action_Scheduler` (Action Scheduler integration)
3. `WOOM_Optimized_Query` (Performance optimized queries)
4. `WOOM_CLI_Commands` (WP-CLI commands)

### Target PSR-4 Structure

```
src/
├── Core/
│   ├── Plugin.php                    # Main plugin class
│   ├── Settings.php                  # Settings management
│   ├── Installer.php                 # Activation/deactivation
│   └── Dependencies.php              # Dependency checking
├── Monitoring/
│   ├── OrderMonitor.php              # Core monitoring logic
│   ├── ThresholdChecker.php          # Threshold validation
│   ├── CronScheduler.php             # Cron management
│   └── Query/
│       ├── OrderQuery.php            # Standard order queries
│       └── OptimizedQuery.php        # Performance optimized queries
├── Notifications/
│   ├── EmailNotifier.php             # Email notifications
│   ├── AlertManager.php              # Alert management
│   └── Templates/
│       ├── AlertTemplate.php         # Email templates
│       └── TestTemplate.php          # Test email templates
├── Admin/
│   ├── SettingsPage.php              # Admin interface
│   ├── TabRenderer.php               # Tab navigation
│   ├── SelfTests.php                 # Self-testing system
│   └── AjaxHandler.php               # AJAX request handling
├── CLI/
│   ├── Commands.php                  # WP-CLI commands
│   └── CommandRegistry.php           # Command registration
├── Integration/
│   ├── ActionScheduler.php           # Action Scheduler integration
│   └── WooCommerce.php               # WooCommerce integration
└── Utils/
    ├── TimeHelper.php                # Time/date utilities
    ├── EmailValidator.php            # Email validation
    └── Logger.php                    # Logging utilities
```

### Namespace Structure

**Root Namespace:** `KissPlugins\WooOrderMonitor`

**Namespace Mapping:**
```php
KissPlugins\WooOrderMonitor\Core\*           -> src/Core/
KissPlugins\WooOrderMonitor\Monitoring\*     -> src/Monitoring/
KissPlugins\WooOrderMonitor\Notifications\*  -> src/Notifications/
KissPlugins\WooOrderMonitor\Admin\*          -> src/Admin/
KissPlugins\WooOrderMonitor\CLI\*            -> src/CLI/
KissPlugins\WooOrderMonitor\Integration\*    -> src/Integration/
KissPlugins\WooOrderMonitor\Utils\*          -> src/Utils/
```

### Implementation Phases

#### Phase 1: Foundation Setup
**Estimated Time:** 2-3 hours

1. **Create Directory Structure**
   - Create `src/` directory with subdirectories
   - Set up autoloader configuration

2. **Composer Integration**
   - Update `composer.json` with PSR-4 autoloading
   - Configure development dependencies

3. **Autoloader Bootstrap**
   - Create autoloader initialization
   - Maintain backward compatibility

**Files to Create:**
- `src/` directory structure
- Updated `composer.json`
- `bootstrap.php` (autoloader initialization)

#### Phase 2: Core Classes Migration
**Estimated Time:** 4-5 hours

1. **Extract Core Plugin Class**
   - Move main plugin logic to `src/Core/Plugin.php`
   - Implement singleton pattern properly
   - Add proper dependency injection

2. **Settings Management**
   - Extract settings logic to `src/Core/Settings.php`
   - Implement settings validation
   - Add settings caching

3. **Installer & Dependencies**
   - Move activation/deactivation to `src/Core/Installer.php`
   - Extract dependency checking to `src/Core/Dependencies.php`

#### Phase 3: Monitoring System
**Estimated Time:** 3-4 hours

1. **Order Monitoring**
   - Extract monitoring logic to `src/Monitoring/OrderMonitor.php`
   - Move threshold checking to `src/Monitoring/ThresholdChecker.php`
   - Create cron scheduler in `src/Monitoring/CronScheduler.php`

2. **Query Classes**
   - Move standard queries to `src/Monitoring/Query/OrderQuery.php`
   - Migrate optimized queries to `src/Monitoring/Query/OptimizedQuery.php`
   - Add query interface for consistency

#### Phase 4: Admin Interface
**Estimated Time:** 4-5 hours

1. **Settings Page**
   - Extract admin interface to `src/Admin/SettingsPage.php`
   - Move tab rendering to `src/Admin/TabRenderer.php`
   - Create AJAX handler in `src/Admin/AjaxHandler.php`

2. **Self Tests System**
   - Move self tests to `src/Admin/SelfTests.php`
   - Create test interface for extensibility
   - Add test result formatting

#### Phase 5: Notifications & CLI
**Estimated Time:** 2-3 hours

1. **Notification System**
   - Extract email logic to `src/Notifications/EmailNotifier.php`
   - Create alert manager in `src/Notifications/AlertManager.php`
   - Move templates to `src/Notifications/Templates/`

2. **CLI Commands**
   - Move CLI commands to `src/CLI/Commands.php`
   - Create command registry in `src/CLI/CommandRegistry.php`

#### Phase 6: Integration & Utilities
**Estimated Time:** 2-3 hours

1. **Integration Classes**
   - Move Action Scheduler to `src/Integration/ActionScheduler.php`
   - Create WooCommerce integration class

2. **Utility Classes**
   - Extract time helpers to `src/Utils/TimeHelper.php`
   - Create email validator in `src/Utils/EmailValidator.php`
   - Add logging utilities

#### Phase 7: Testing & Cleanup
**Estimated Time:** 3-4 hours

1. **Unit Tests**
   - Create PHPUnit test structure
   - Add tests for core functionality
   - Implement CI/CD test integration

2. **Legacy Cleanup**
   - Remove old code from main file
   - Update documentation
   - Performance testing

### Technical Implementation Details

#### Composer Configuration

```json
{
    "autoload": {
        "psr-4": {
            "KissPlugins\\WooOrderMonitor\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "KissPlugins\\WooOrderMonitor\\Tests\\": "tests/"
        }
    }
}
```

#### Bootstrap File

```php
<?php
// bootstrap.php
if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader
$autoloader = WOOM_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    // Fallback for development without Composer
    require_once WOOM_PLUGIN_DIR . 'src/autoload-fallback.php';
}

// Initialize plugin
use KissPlugins\WooOrderMonitor\Core\Plugin;
Plugin::getInstance();
```

#### Interface Examples

```php
<?php
namespace KissPlugins\WooOrderMonitor\Monitoring\Query;

interface QueryInterface {
    public function getOrderCount(int $minutes): int;
    public function getOrderStats(int $minutes): array;
}

namespace KissPlugins\WooOrderMonitor\Admin;

interface TestInterface {
    public function run(): array;
    public function getName(): string;
    public function getDescription(): string;
}
```

### Migration Strategy

#### Backward Compatibility
- Keep existing function names as wrappers
- Maintain current hook structure
- Preserve settings and data

#### Gradual Migration
- Implement new classes alongside existing code
- Use feature flags for testing
- Migrate functionality incrementally

#### Testing Strategy
- Unit tests for each new class
- Integration tests for critical paths
- Manual testing of admin interface
- Performance benchmarking

### Benefits of PSR-4 Migration

#### Code Organization
- **Separation of Concerns:** Each class has a single responsibility
- **Logical Grouping:** Related functionality grouped in namespaces
- **Easier Navigation:** Clear file structure for developers

#### Maintainability
- **Smaller Files:** Easier to understand and modify
- **Dependency Injection:** Better testability and flexibility
- **Interface Contracts:** Clear API boundaries

#### Performance
- **Autoloading:** Only load classes when needed
- **Optimized Loading:** Composer's optimized autoloader
- **Memory Efficiency:** Reduced memory footprint

#### Testing
- **Unit Testing:** Easy to test individual components
- **Mocking:** Better support for test doubles
- **CI/CD Integration:** Automated testing pipeline

#### Future Development
- **Extensibility:** Easy to add new features
- **Plugin Architecture:** Support for extensions
- **Code Reuse:** Shareable components

### Risk Mitigation

#### Potential Issues
1. **Breaking Changes:** Existing customizations might break
2. **Performance Impact:** Initial autoloader overhead
3. **Complexity:** More files to manage

#### Mitigation Strategies
1. **Comprehensive Testing:** Extensive test coverage
2. **Gradual Rollout:** Feature flags and staged deployment
3. **Documentation:** Clear migration guides
4. **Backward Compatibility:** Maintain existing APIs

### Timeline Summary

**Total Estimated Time:** 20-27 hours
**Recommended Timeline:** 3-4 weeks (part-time development)

**Week 1:** Phases 1-2 (Foundation & Core)
**Week 2:** Phases 3-4 (Monitoring & Admin)
**Week 3:** Phases 5-6 (Notifications & Integration)
**Week 4:** Phase 7 (Testing & Cleanup)

### Success Metrics

- [ ] All existing functionality preserved
- [ ] Performance maintained or improved
- [ ] Unit test coverage > 80%
- [ ] Self tests pass in new structure
- [ ] CI/CD pipeline updated and passing
- [ ] Documentation updated
- [ ] No breaking changes for end users

### Next Steps

1. **Review and Approve Plan**
2. **Set up Development Environment**
3. **Create Feature Branch**
4. **Begin Phase 1 Implementation**
5. **Regular Progress Reviews**

This migration will significantly improve the plugin's architecture while maintaining all existing functionality and providing a solid foundation for future development.

### Current Status: 12-10-25

Perfect! Now I have a complete picture of the PSR-4 migration status. Let me provide you with a comprehensive analysis.

---

## 📊 **PSR-4 Migration Status: PARTIALLY COMPLETE (57%)**

### **Overall Progress:**

| Phase | Status | Completion |
|-------|--------|------------|
| **Phase 1: Foundation Setup** | ✅ **COMPLETE** | 100% |
| **Phase 2: Core Classes** | ✅ **COMPLETE** | 100% |
| **Phase 3: Monitoring System** | ✅ **COMPLETE** | 100% |
| **Phase 4: Admin Interface** | ✅ **COMPLETE** | 100% |
| **Phase 5: Notifications & CLI** | ✅ **COMPLETE** | 100% |
| **Phase 6: Integration & Utilities** | ✅ **COMPLETE** | 100% |
| **Phase 7: Testing & Cleanup** | ❌ **NOT STARTED** | 0% |

**Overall Completion:** ~86% (6 of 7 phases complete)

---

## ✅ **WHAT HAS BEEN MIGRATED (PSR-4 Structure)**

### **Phase 1-6: Production Ready Components**

```` path=src mode=EXCERPT
src/
├── Core/
│   ├── Plugin.php                    ✅ Migrated (Updated for Phase 5 & 6)
│   ├── Settings.php                  ✅ Migrated
│   ├── SettingsDefaults.php          ✅ Migrated
│   ├── SettingsStateMachine.php      ✅ Created (dormant)
│   ├── ThresholdPresets.php          ✅ Migrated
│   ├── Installer.php                 ✅ Migrated
│   └── Dependencies.php              ✅ Migrated
├── Monitoring/
│   ├── OrderMonitor.php              ✅ Migrated
│   ├── ThresholdChecker.php          ✅ Migrated
│   ├── CronScheduler.php             ✅ Migrated
│   └── Query/
│       ├── OrderQuery.php            ✅ Migrated
│       ├── OptimizedQuery.php        ✅ Migrated
│       └── QueryInterface.php        ✅ Migrated
├── Admin/
│   ├── SettingsPage.php              ✅ Migrated
│   ├── TabRenderer.php               ✅ Migrated
│   ├── SelfTests.php                 ✅ Migrated
│   └── AjaxHandler.php               ✅ Migrated
├── Notifications/                    ✅ NEW (Phase 5)
│   ├── EmailNotification.php         ✅ Created
│   └── NotificationTemplate.php      ✅ Created
├── CLI/                              ✅ NEW (Phase 5)
│   └── Commands.php                  ✅ Created
├── Integration/                      ✅ NEW (Phase 6)
│   ├── ActionScheduler.php           ✅ Created
│   └── WooCommerce.php               ✅ Created
└── Utils/                            ✅ NEW (Phase 6)
    ├── TimeHelper.php                ✅ Created
    ├── EmailValidator.php            ✅ Created
    └── Logger.php                    ✅ Created
````

**Total: 23 classes migrated to PSR-4**

---

## ❌ **WHAT REMAINS IN LEGACY FILE (kiss-woo-order-monitoring-alerts.php)**

The main plugin file still contains **~2,734 lines** with the following legacy code:

### **1. Legacy Main Class: `WooCommerce_Order_Monitor`**
**Lines:** ~76-2429 (2,353 lines)

**Still contains:**
- ❌ Email notification logic (lines 535-898)
- ❌ Alert email templates (HTML generation)
- ❌ Webhook notification system (lines 900-950)
- ❌ System alert functionality
- ❌ Test notification email generation (lines 2300-2428)
- ❌ Email subject building logic
- ❌ Backup notification system

**Should be migrated to:**
```
src/Notifications/
├── EmailNotifier.php
├── AlertManager.php
├── WebhookNotifier.php
└── Templates/
    ├── AlertTemplate.php
    └── TestTemplate.php
```

---

### **2. Action Scheduler Integration: `WOOM_Action_Scheduler`**
**Lines:** 2444-2484 (40 lines)

````php path=kiss-woo-order-monitoring-alerts.php mode=EXCERPT
class WOOM_Action_Scheduler {
    public static function init() { ... }
    public static function schedule_monitoring() { ... }
    public static function run_check() { ... }
}
````

**Should be migrated to:**
```
src/Integration/ActionScheduler.php
```

---

### **3. Optimized Query Class: `WOOM_Optimized_Query`**
**Lines:** 2490-2600 (110 lines)

````php path=kiss-woo-order-monitoring-alerts.php mode=EXCERPT
class WOOM_Optimized_Query {
    public static function get_cached_order_count($minutes = 15) { ... }
    public static function get_order_stats($minutes = 15) { ... }
    private static function is_hpos_enabled() { ... }
    // ... more methods
}
````

**Status:** ⚠️ **PARTIALLY MIGRATED**
- ✅ `src/Monitoring/Query/OptimizedQuery.php` exists
- ❌ Legacy class still in main file
- ❌ Not removed from legacy file

---

### **4. WP-CLI Commands: `WOOM_CLI_Commands`**
**Lines:** 2605-2734 (129 lines)

````php path=kiss-woo-order-monitoring-alerts.php mode=EXCERPT
class WOOM_CLI_Commands {
    public function check() { ... }
    public function count($args, $assoc_args) { ... }
    public function status() { ... }
    public function test() { ... }
    public function enable() { ... }
    public function disable() { ... }
    public function reset() { ... }
    public function stats($args, $assoc_args) { ... }
}
````

**Should be migrated to:**
```
src/CLI/
├── Commands.php
└── CommandRegistry.php
```

---

## 🔄 **CURRENT ARCHITECTURE: HYBRID MODE**

The plugin currently runs in **dual-mode** with automatic detection:

````php path=bootstrap.php mode=EXCERPT
function woom_should_use_psr4() {
    // Auto-detect based on file existence - Phase 4 complete!
    $psr4_main_class = WOOM_PLUGIN_DIR . 'src/Core/Plugin.php';
    $admin_classes_exist = file_exists(WOOM_PLUGIN_DIR . 'src/Admin/SettingsPage.php') &&
                          file_exists(WOOM_PLUGIN_DIR . 'src/Admin/TabRenderer.php') &&
                          file_exists(WOOM_PLUGIN_DIR . 'src/Admin/SelfTests.php') &&
                          file_exists(WOOM_PLUGIN_DIR . 'src/Admin/AjaxHandler.php');

    return file_exists($psr4_main_class) && $admin_classes_exist;
}
````

**Current Behavior:**
1. ✅ PSR-4 classes are loaded and used (Core, Monitoring, Admin)
2. ❌ Legacy classes still exist in main file (Notifications, CLI, Integration)
3. ⚠️ **Duplicate code** - Some functionality exists in both places

---

## 📋 **REMAINING WORK (Phase 7)**

### **✅ Phase 5: Notifications & CLI** - **COMPLETED** (December 10, 2025)

**Completed Tasks:**
1. ✅ Created `src/Notifications/EmailNotification.php` - Email sending and notification management
2. ✅ Created `src/Notifications/NotificationTemplate.php` - HTML email template rendering
3. ✅ Implemented `renderAlertEmail()` - Standard alert email template
4. ✅ Implemented `renderEnhancedAlertEmail()` - Enhanced alert with throttling info
5. ✅ Implemented `renderTestEmail()` - Test notification template
6. ✅ Created `src/CLI/Commands.php` - WP-CLI command integration
7. ✅ Implemented CLI commands: `check`, `count`, `test`, `config`, `clear-cache`
8. ✅ Updated `src/Core/Plugin.php` to initialize EmailNotification and CLI Commands
9. ✅ Created `test-phase5-migration.php` for testing

**Files Created:**
- ✅ `src/Notifications/EmailNotification.php` (289 lines)
- ✅ `src/Notifications/NotificationTemplate.php` (260 lines)
- ✅ `src/CLI/Commands.php` (158 lines)
- ✅ `test-phase5-migration.php` (test script)

**Key Features:**
- Email notification system with template rendering
- Support for alert emails, enhanced alerts, and test emails
- WP-CLI integration with 5 commands
- Proper dependency injection in Plugin class
- Alert tracking and throttling support

**Testing:**
Run `php test-phase5-migration.php` or use WP-CLI commands:
- `wp woom config` - Show current configuration
- `wp woom count` - Get order count
- `wp woom test` - Send test notification
- `wp woom check` - Manual threshold check
- `wp woom clear-cache` - Clear all caches

---

### **✅ Phase 6: Integration & Utilities** - **COMPLETED** (December 10, 2025)

**Completed Tasks:**
1. ✅ Created `src/Integration/ActionScheduler.php` - Action Scheduler integration for reliable background tasks
2. ✅ Created `src/Integration/WooCommerce.php` - WooCommerce-specific utilities and HPOS detection
3. ✅ Created `src/Utils/TimeHelper.php` - Time formatting, timezone handling, and time calculations
4. ✅ Created `src/Utils/EmailValidator.php` - Email validation and parsing utilities
5. ✅ Created `src/Utils/Logger.php` - Centralized logging with multiple log levels
6. ✅ Updated `src/Core/Plugin.php` to initialize ActionScheduler integration
7. ✅ Created `test-phase6-migration.php` for testing

**Files Created:**
- ✅ `src/Integration/ActionScheduler.php` (172 lines)
- ✅ `src/Integration/WooCommerce.php` (157 lines)
- ✅ `src/Utils/TimeHelper.php` (189 lines)
- ✅ `src/Utils/EmailValidator.php` (150 lines)
- ✅ `src/Utils/Logger.php` (180 lines)
- ✅ `test-phase6-migration.php` (test script)

**Key Features:**

**Integration Classes:**
- Action Scheduler integration with automatic scheduling/unscheduling
- WooCommerce HPOS detection and compatibility helpers
- Order status and database table abstraction
- Admin URL helpers for WooCommerce pages

**Utility Classes:**
- Time helpers: timezone handling, time range checking, duration formatting
- Email validators: single/multiple email validation, email list parsing
- Logger: debug, info, warning, error levels with conditional logging
- Convenience methods for common logging scenarios

**Testing:**
Run `php test-phase6-migration.php` to verify:
- Integration classes exist and instantiate correctly
- WooCommerce integration detects HPOS status
- TimeHelper validates time formats and calculates periods
- EmailValidator parses and validates email lists
- Logger methods execute without errors
- ActionScheduler integration initializes in Plugin

---

### **Phase 7: Testing & Cleanup** (Estimated: 3-4 hours)

**Tasks:**
1. ❌ Remove legacy code from `kiss-woo-order-monitoring-alerts.php`
2. ❌ Keep only plugin header and bootstrap logic in main file
3. ❌ Create PHPUnit tests for new classes
4. ❌ Update documentation
5. ❌ Performance testing
6. ❌ Final verification

**Expected Main File After Cleanup:**
```php
<?php
/**
 * Plugin Name: KISS WooCommerce Order Monitor
 * Version: 1.7.1
 * ...
 */

// Define constants
define('WOOM_VERSION', '1.7.1');
define('WOOM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOOM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOOM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load update checker
require_once WOOM_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
$update_checker = PucFactory::buildUpdateChecker(...);

// Bootstrap PSR-4
require_once WOOM_PLUGIN_DIR . 'bootstrap.php';
woom_bootstrap();
```

**Target:** ~100 lines (down from 2,734 lines)

---

## 🎯 **BENEFITS OF COMPLETING MIGRATION**

### **Already Achieved (Phases 1-4):**
- ✅ Core plugin logic organized
- ✅ Settings management centralized
- ✅ Monitoring system modular
- ✅ Admin interface separated
- ✅ Dependency injection implemented
- ✅ Better testability for core features

### **Still Missing (Phases 5-7):**
- ❌ Email/notification system still monolithic
- ❌ CLI commands not organized
- ❌ Integration code scattered
- ❌ No utility classes for reusable logic
- ❌ Main file still 2,734 lines (should be ~100)
- ❌ Duplicate code between legacy and PSR-4

---

## 💡 **RECOMMENDATIONS**

### **Option A: Complete the Migration (7-10 hours)**
Finish Phases 5-7 to achieve full PSR-4 architecture.

**Pros:**
- ✅ Clean, maintainable codebase
- ✅ Easier to add SPC features (from PROJECT-RETHINK.md)
- ✅ Better testing capabilities
- ✅ Eliminates duplicate code
- ✅ Professional architecture

**Cons:**
- ⏱️ 7-10 hours of work
- ⚠️ Requires thorough testing

---

### **Option B: Keep Hybrid Mode (Current State)**
Leave Phases 1-4 complete, keep legacy code for Phases 5-7.

**Pros:**
- ✅ No additional work needed
- ✅ Core functionality already modernized
- ✅ Working production code

**Cons:**
- ❌ Duplicate code maintenance burden
- ❌ Main file still 2,734 lines
- ❌ Email/CLI logic not modular
- ❌ Harder to add new features

---

### **Option C: Minimal Cleanup (2-3 hours)**
Just remove duplicate `WOOM_Optimized_Query` from main file since it's already migrated.

**Pros:**
- ✅ Quick win
- ✅ Reduces duplication
- ✅ Minimal risk

**Cons:**
- ⚠️ Still leaves most legacy code

---

## 🎯 **MY RECOMMENDATION**

**Complete Option A: Finish the PSR-4 Migration**

**Rationale:**
1. You're already 57% done - finish what you started
2. If you implement SPC (from PROJECT-RETHINK.md), you'll need clean architecture
3. Email/notification system is the most complex part - needs refactoring anyway
4. 7-10 hours is reasonable for long-term maintainability
5. Eliminates technical debt before adding new features

**Suggested Order:**
1. **Week 1:** Phase 5 (Notifications & CLI) - 2-3 hours
2. **Week 2:** Phase 6 (Integration & Utilities) - 2-3 hours  
3. **Week 3:** Phase 7 (Testing & Cleanup) - 3-4 hours

This gives you a **clean foundation** before tackling SPC implementation or any other major features.

---

Would you like me to help you complete Phase 5 (Notifications & CLI migration) as the next step?

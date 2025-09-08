# PSR-4 Autoloading Transition Plan
## KISS WooCommerce Order Monitor

### Overall Status
PHASE 1: ✅ COMPLETED
PHASE 2: ✅ COMPLETED
PHASE 3: Not started
PHASE 4: Not started (Production Ready)
PHASE 5: Not started
PHASE 6: Not started
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

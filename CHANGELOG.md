## Changelog

### Version 1.6.0
October 16, 2025

**🎯 NEW FEATURE: Rolling Average Detection (RAD)**

**Phase 1: Core RAD Foundation - Complete**

**What is RAD?**
- Failure-rate based monitoring that works for both high-volume and low-volume stores
- Tracks order success/failure patterns instead of time-based thresholds
- Solves the problem: "If 70% of last 10 orders fail, that's a problem" - regardless of time

**New Features:**
- **Order History Tracking** - Transient cache approach (no permanent redundancy)
  - Tracks last N orders (configurable, default: 10)
  - Uses WordPress transients with smart invalidation
  - Rebuilds from WooCommerce on demand (source of truth)
  - Auto-expires cache (5 minutes) - no data drift
- **Failure Rate Calculation** - Percentage-based detection
  - Calculates % of failed orders in rolling window
  - Minimum order requirement prevents false positives
  - Works for low-volume stores (can go hours without orders)
- **WooCommerce Hook Integration** - Real-time tracking
  - Hooks into `woocommerce_order_status_changed`
  - Invalidates cache on order status changes
  - Automatically checks failure rate after each order
- **RAD-Specific Alerts** - Different from time-based alerts
  - Custom email template for failure rate alerts
  - Shows failure rate, threshold, and order breakdown
  - Includes diagnostic hints (payment gateway, inventory, etc.)
  - Respects existing cooldown/throttling settings
- **Settings UI** - New "Rolling Average Detection" section
  - Enable/disable RAD (opt-in for Phase 1)
  - Configure window size (3-50 orders)
  - Set failure threshold (1-100%)
  - Set minimum orders before alerting (1-20)
- **Self-Tests** - Comprehensive RAD testing
  - Tests order history retrieval
  - Tests failure rate calculation
  - Tests cache functionality
  - Tests hook registration
  - Validates all RAD methods exist

**Technical Implementation:**
- **Transient Cache Design** - Best of both worlds
  - No permanent data redundancy (WooCommerce already stores orders)
  - Fast array-based calculations (when cached)
  - Always accurate (rebuilds from WooCommerce)
  - Self-healing (auto-expires and rebuilds)
- **Settings Centralization** - Added to `SettingsDefaults`
  - `rolling_enabled` - Enable/disable RAD (default: no)
  - `rolling_window_size` - Orders to track (default: 10)
  - `rolling_failure_threshold` - Alert threshold % (default: 70)
  - `rolling_min_orders` - Minimum before alerting (default: 3)
  - `rolling_cache_duration` - Cache expiration (default: 300s)
- **New Methods in OrderMonitor** - v1.6.0
  - `getOrderHistory()` - Get cached or rebuild order history
  - `rebuildOrderHistory()` - Query WooCommerce for recent orders
  - `calculateFailureRate()` - Calculate % of failed orders
  - `checkRollingFailureRate()` - Check threshold and send alerts
  - `onOrderStatusChanged()` - Hook handler for cache invalidation
  - `sendRollingAverageAlert()` - Send RAD-specific email
  - `buildRollingAverageAlertEmail()` - Generate RAD email template

**Design Evolution:**
- Original plan: Permanent `woom_order_history` option
- Concern: Data redundancy (WooCommerce already stores orders)
- Solution: Transient cache with smart invalidation
- Result: No redundancy + performance + accuracy

**Use Cases:**
- **High-volume stores** Hybrid mode (time-based + RAD)
- **Low-volume stores** RAD-only mode (works with <1 order/hour)
- **All stores**: Better detection of payment gateway issues, checkout errors

**🐛 Bug Fixes:**
- **Fixed foreach() null error in RAD** - Added guard against null return from `wc_get_orders()`
  - Error: `PHP Warning: foreach() argument must be of type array|object null given`
  - Location: `src/Monitoring/OrderMonitor.php:542` (rebuildOrderHistory method)
  - Root cause: `wc_get_orders()` can return null if WooCommerce not fully loaded or database error
  - Solution: Added `is_array()` check before foreach loop, returns empty array on error
  - Impact: Prevents PHP warnings and gracefully handles edge cases
- **Fixed foreach() null error in autoloader** - Enhanced type checking in fallback autoloader
  - Error: `PHP Warning: foreach() argument must be of type array|object, null given`
  - Location: `src/autoload-fallback.php:91` (woom_load_critical_classes function)
  - Root cause: `$woom_class_map` global variable could be null in edge cases during plugin initialization
  - Solution: Added `isset()` check before `is_array()` check, added validation for class/file entries
  - Impact: Prevents PHP warnings during plugin activation/initialization edge cases

**Next Steps (Future Phases):**
- Phase 2: Dual-mode monitoring (hybrid time-based + RAD)
- Phase 3: Advanced analytics (trend analysis, adaptive thresholds)

---

### Version 1.5.5
October 16, 2025

**Added remote update feature**
 - Added Plugin Update checker library.

### Version 1.5.4
October 10, 2025

**🐛 CRITICAL BUG FIX: Plugin Deactivation Fatal Error**

**Bug Fixed:**
- **Fatal Error on Deactivation** - Fixed fatal error when deactivating plugin
  - Error: `Call to undefined function wp_cache_delete_group()`
  - Location: `src/Core/Installer.php:231`
  - Root cause: Non-existent WordPress function `wp_cache_delete_group()` was being called
  - Solution: Removed invalid function call from `clearCaches()` method
  - Impact: Plugin can now be safely deactivated without fatal errors

**Technical Details:**
- The `wp_cache_delete_group()` function does not exist in WordPress core
- WordPress provides `wp_cache_flush()` for clearing all caches
- Removed the invalid function call while keeping `wp_cache_flush()` and settings cache clearing
- Plugin deactivation now works correctly on all WordPress installations

### Version 1.5.3
September 09, 2025

**🔧 CRITICAL: Settings Centralization & Configuration Drift Prevention**

**🛡️ Settings Architecture Overhaul:**
- **Centralized Settings Configuration** - All default values now managed in single source of truth
  - New `SettingsDefaults` class centralizes ALL plugin default values
  - Prevents configuration drift between UI forms, activation code, and runtime logic
  - Eliminates conflicting default values that caused email alert discrepancies
- **Settings Consistency Validation** - Self-test system to prevent future drift
  - New self-test validates no hardcoded defaults exist in codebase
  - Automated scanning for forbidden patterns and conflicting values
  - Comprehensive validation of settings centralization compliance
- **Enhanced Documentation & Safeguards** - Extensive comments to prevent regression
  - Critical warning comments in all files that previously had hardcoded defaults
  - Clear instructions on where and how to modify default values
  - Safeguard documentation to prevent future configuration drift

**🐛 Bug Fixes:**
- **Fixed Email Alert Threshold Discrepancy** - Resolved issue where email alerts showed "Expected Threshold: 10" while UI showed "2 per 15 minute window"
  - Root cause: Multiple conflicting default values scattered across codebase
  - Solution: Centralized all defaults in `SettingsDefaults` class
  - All UI forms, activation code, and runtime logic now use same source
- **Improved Settings Loading** - All settings loading now uses centralized configuration
  - Updated main plugin file to use `SettingsDefaults`
  - Updated `Settings` class to use centralized defaults
  - Updated `Installer` class to use centralized defaults
  - Updated admin settings forms to use centralized defaults

**🔧 Technical Improvements:**
- **Code Architecture** - Better separation of concerns and maintainability
  - Settings defaults separated from business logic
  - Validation rules centralized with defaults
  - Clear API for accessing default values across plugin
- **Self-Test Enhancement** - Added settings centralization validation
  - New test scans codebase for hardcoded defaults
  - Validates all settings use centralized configuration
  - Provides detailed reporting of any configuration drift

**⚠️ Important Notes:**
- This update resolves critical configuration inconsistencies
- All default values are now managed in `src/Core/SettingsDefaults.php`
- Future default value changes must be made ONLY in SettingsDefaults class
- Self-tests will catch any attempts to add hardcoded defaults elsewhere

### Version 1.5.2
September 08, 2025

**🛡️ PRODUCTION SAFETY ENHANCEMENT: Comprehensive Alert Throttling & Performance Optimization**

**🔧 Production-Ready Features:**
- **Alert Throttling System** - Comprehensive throttling to prevent email flooding during production issues
  - Configurable cooldown periods between alerts (2 hours default)
  - Maximum daily alert limits (6 alerts/day default)  
  - Separate tracking for peak and off-peak alert types
  - Escalation alerts when maximum limits reached
- **Database Performance Optimization** - Enhanced query performance with intelligent caching
  - Object caching for order count queries with 60-second TTL
  - HPOS (High-Performance Order Storage) support for WooCommerce 8.0+
  - Query-level caching to prevent redundant database hits
- **Enhanced Email System** - Professional alert templates with throttling information
  - Rich HTML templates with alert type identification
  - Throttling status display and next alert availability
  - Escalation notifications for maximum alert limits
- **Manual Check Functionality** - Real-time order monitoring with AJAX interface
  - Instant order count verification without waiting for cron
  - Real-time threshold testing and validation
  - Production-safe manual testing capabilities

**🎯 Bloomz Production Optimization:**
- **Conservative Defaults** - Production-safe configuration for high-volume stores
  - Peak hours: 10:00 AM - 8:00 PM (business hours)
  - Peak threshold: 3 orders per 15 minutes
  - Off-peak threshold: 1 order per 15 minutes
  - Alert cooldown: 2 hours between similar alerts
- **Enhanced Error Handling** - Comprehensive error handling and graceful degradation
  - Database error recovery with fallback mechanisms
  - Graceful handling of email delivery failures
  - Detailed error logging for production debugging
- **Resource Management** - Optimized for high-traffic production environments
  - Minimal database impact with smart caching
  - Efficient query patterns for large order volumes
  - Memory-efficient processing for continuous monitoring

**🔧 Technical Improvements:**
- **PSR-4 Compatibility** - All production safety features integrated with PSR-4 architecture
- **WordPress Standards** - Full compliance with WordPress coding and security standards
- **Backward Compatibility** - 100% compatible with existing plugin installations
- **Performance Monitoring** - Built-in performance metrics and monitoring capabilities

### Version 1.5.1
September 08, 2025

**📚 DOCUMENTATION ENHANCEMENT: Comprehensive PHPDoc Coverage**

**🔧 Enhanced Documentation:**
- **Complete PHPDoc Coverage** - Added comprehensive PHPDoc comments to all PSR-4 classes
- **Detailed Parameter Documentation** - Enhanced method documentation with detailed parameter descriptions
- **Exception Documentation** - Added @throws documentation for all exception-handling methods
- **Return Value Documentation** - Improved return value documentation with detailed structure descriptions
- **Class-Level Documentation** - Added comprehensive class-level documentation with usage examples

**🛠️ Technical Improvements:**
- **WordPress-Docs Standards** - All PSR-4 classes now meet WordPress-Docs coding standards
- **IDE Support** - Enhanced IDE support with detailed type hints and parameter descriptions
- **Developer Experience** - Improved code readability and maintainability
- **Code Quality** - Enhanced inline documentation for complex array structures and method behaviors

### Version 1.5.0
September 08, 2024

**🎉 MAJOR RELEASE: PSR-4 Architecture Migration (Production Ready)**

**🏗️ Complete Architecture Overhaul:**
- **PSR-4 Structure** - Migrated entire plugin to modern autoloading architecture
- **Modular Design** - Separated concerns into dedicated, testable classes
- **Dependency Injection** - Proper DI container pattern throughout the system
- **Interface-Based Design** - Pluggable components for easy testing and extension

**📁 New Class Structure:**
- `src/Core/` - Plugin foundation (Plugin, Settings, Dependencies, Installer)
- `src/Monitoring/` - Order monitoring system with query optimization
- `src/Admin/` - Professional admin interface with tab navigation
- `src/Utils/` - Shared utilities and helper functions

**🚀 Enhanced Admin Interface:**
- **SettingsPage** - Complete admin interface rewrite with modern UX
- **TabRenderer** - Professional tab navigation with responsive design
- **SelfTests** - Comprehensive diagnostic system with 4 detailed tests
- **AjaxHandler** - Secure AJAX handling with proper nonce validation

**⚡ High-Performance Monitoring:**
- **OrderMonitor** - Main coordinator with intelligent component management
- **ThresholdChecker** - Advanced peak hours detection with midnight spanning
- **CronScheduler** - Smart cron management with diagnostics and auto-repair
- **OptimizedQuery** - HPOS support for WooCommerce 8.0+ performance
- **QueryInterface** - Pluggable query system with automatic fallback

**🔧 Production-Grade Features:**
- **Backward Compatibility** - 100% compatible with existing installations
- **Feature Flags** - Safe deployment with `WOOM_USE_PSR4` constant
- **Error Handling** - Comprehensive exception handling and logging
- **Performance Optimization** - Multi-level caching and query optimization
- **Testing Ready** - PHPUnit configuration and CI/CD pipeline support

**📊 Enhanced Self-Testing:**
- **Database Test** - Validates connectivity, tables, and HPOS support
- **Threshold Test** - Validates peak hours logic and midnight spanning
- **Email Test** - Checks configuration, SMTP settings, and delivery
- **Cron Test** - Comprehensive cron diagnostics with auto-repair

**🎯 Migration Benefits:**
- **Maintainability** - Clean, organized code structure
- **Testability** - Interface-based design for easy unit testing
- **Extensibility** - Plugin architecture for future enhancements
- **Performance** - Optimized queries and intelligent caching
- **Reliability** - Comprehensive error handling and diagnostics

### Version 1.4.1
September 08, 2025

**🔧 CRITICAL FIX: Cron Scheduling**
- **Fixed Custom Cron Interval Registration** - Moved cron schedule registration to init_hooks for proper timing
- **Enhanced Self Test Diagnostics** - Improved cron scheduling test with auto-repair functionality
- **Better Error Detection** - Added checks for WP-Cron disabled state and Action Scheduler availability
- **Auto-Recovery** - Self test can now automatically schedule missing cron jobs

**Technical Improvements:**
- Removed duplicate cron schedule registration from activation hook
- Added comprehensive cron diagnostics with actionable error messages
- Enhanced test feedback with specific troubleshooting guidance
- Improved hook timing to ensure cron schedules are always available

**User Experience:**
- Self Tests now provide clear guidance when cron issues are detected
- Automatic detection and notification of WP-Cron disabled state
- Better error messages with specific next steps for resolution

### Version 1.4.0
September 08, 2025

**🧪 NEW FEATURE: Self Tests Tab**
- **On-Screen Diagnostics** - Added comprehensive self-testing system with 4 critical tests
- **Tab Structure Enhanced** - Now includes: Settings | Changelog | Self Tests
- **Real-Time Validation** - Test core functions directly in production environment
- **Regression Detection** - Catch accidental issues after updates or configuration changes

**Self Test Coverage:**
1. **Database & Order Query** - Tests database connection and order counting functionality
2. **Threshold Logic** - Validates peak/off-peak detection and threshold calculations
3. **Email System** - Tests email configuration and notification delivery
4. **Cron Scheduling** - Verifies automated monitoring schedule and cron functionality

**User Experience:**
- **Interactive Interface** - Run all tests or select individual tests
- **Visual Results** - Color-coded success/warning/error indicators
- **Detailed Feedback** - Comprehensive test results with specific diagnostic information
- **Test Summary** - Overview of passed/failed tests with actionable insights

**Technical Implementation:**
- AJAX-powered test execution for responsive UI
- Comprehensive error handling and exception management
- Production-safe testing that doesn't interfere with live monitoring
- Detailed logging and diagnostic information

### Version 1.3.3
September 08, 2025

**DevOps & CI/CD Improvements:**
- **Composer Integration** - Added proper composer.json for dependency management
- **Local Dependencies** - Switched from global to local Composer dependencies in CI
- **Package Management** - Configured allow-plugins for dealerdirect/phpcodesniffer-composer-installer
- **CI Optimization** - Streamlined GitHub Actions workflow to use composer scripts
- **Tool Configuration** - Removed redundant tool installations from setup-php action

**Technical Enhancements:**
- Fixed CI pipeline composer plugin conflicts
- Improved dependency resolution and caching
- Enhanced code quality tool integration
- Better error handling in CI environment

### Version 1.3.2
September 08, 2025

**DevOps & CI/CD Fixes:**
- **GitHub Actions Fix** - Fixed PHP CodeSniffer and WordPress Coding Standards installation
- **Dependency Management** - Properly configured global Composer packages for CI pipeline
- **PHPMD Integration** - Fixed PHP Mess Detector installation and execution
- **Path Configuration** - Corrected PATH and installed_paths for coding standards tools

**Technical Improvements:**
- Fixed CI pipeline failures related to missing composer.json
- Enhanced error handling in GitHub Actions workflow
- Improved tool installation reliability in CI environment

### Version 1.3.1
September 07, 2025

**DevOps & Quality Assurance:**
- **GitHub Actions CI/CD** - Comprehensive automated testing pipeline
- **PHP Lint Automation** - Multi-version PHP syntax validation (8.0-8.3)
- **Security Audit Automation** - SQL injection, XSS, and file inclusion vulnerability scanning
- **WordPress Coding Standards** - Automated PHPCS checks with WordPress-Extra ruleset
- **Code Quality Analysis** - PHP Mess Detector integration for complexity analysis
- **Plugin-Specific Validations** - WooCommerce dependency and database security checks
- **Performance Monitoring** - Automated query optimization and resource usage analysis

**Documentation:**
- **AUDIT.md** - Comprehensive security and performance audit documentation
- **phpcs.xml** - WordPress coding standards configuration
- **CI/CD Pipeline** - Automated checks on main and development branches

**Quality Improvements:**
- Branch protection with required status checks
- Multi-PHP version compatibility testing
- WordPress compatibility validation
- Version consistency verification
- Internationalization compliance checking

### Version 1.3.0
September 07, 2025

**Major UI Improvement:**
- **Simplified tab navigation** - Added clean two-tab interface at top of settings page
- **Tab structure**: "WooCommerce Order Monitor Settings" | "Changelog"
- **URL-based navigation** - Uses subtab parameter for proper browser back/forward support
- **Professional styling** - WordPress admin-style tabs with hover effects and active states
- **Clean separation** - Settings and changelog now completely separate views
- **Improved UX** - No more positioning issues, intuitive navigation

**Technical Improvements:**
- Removed complex hook-based changelog positioning
- Simplified settings rendering logic
- Added proper tab state management
- Enhanced URL structure for better navigation

### Version 1.2.1
September 07, 2025

**Bug Fix:**
- **Fixed changelog positioning** - Changelog viewer now appears AFTER the "Save Changes" button instead of before it
- **Improved hook implementation** - Uses proper WooCommerce settings hook for better integration

### Version 1.2.0
September 07, 2025

**User Interface Enhancements:**
- **Version display in page title** - Settings page now shows "WooCommerce Order Monitor Settings - v1.2.0"
- **Integrated changelog viewer** - Added scrollable changelog display at bottom of settings page
- **KISS MDV integration** - Supports kiss_mdv_render_file() for enhanced markdown rendering with fallback to plain text
- **Professional styling** - Changelog viewer with 400px max height, scrollable area, and clean formatting

**Technical Improvements:**
- Automatic version number display using WOOM_VERSION constant
- Responsive changelog container with proper styling
- Fallback rendering for environments without KISS MDV plugin
- Enhanced settings page layout and organization

### Version 1.1.0
September 07, 2025

**Major Enhancements:**
- **Real-time status updates** - Monitoring status changes instantly when checkbox is toggled
- **Settings save indicator** - Shows "Saved" or "Not Saved" status with visual indicators
- **Plugin settings link** - Added "Settings" link on WordPress plugins page for quick access
- **Improved defaults** - Monitoring enabled by default, peak hours end at 6 PM (18:00)
- **Server timezone display** - Shows current server time and timezone for peak hours reference
- **Enhanced error handling** - Comprehensive try-catch blocks around all database queries
- **Fixed custom settings fields** - Replaced unsupported WooCommerce field types with working alternatives
- **Visual improvements** - Added colored status indicators and smooth transitions

**Technical Improvements:**
- Database query error handling with fallbacks
- Input validation and sanitization for all settings
- Real-time JavaScript change detection
- Enhanced AJAX error handling for test notifications
- Backward compatibility for timezone functions

**User Experience:**
- Monitoring enabled by default (clear user intent)
- Direct settings access from plugins page
- Real-time feedback on setting changes
- Clear timezone context for peak hours
- Professional visual status indicators

### Version 1.0.0
September 07, 2025

- Initial release
- Core monitoring functionality
- Email notifications
- WooCommerce settings integration
- WP-CLI commands
- Site Health integration
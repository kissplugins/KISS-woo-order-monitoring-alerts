## Changelog

### Version 1.5.1
December 19, 2024

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
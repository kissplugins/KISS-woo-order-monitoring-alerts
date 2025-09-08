## Changelog

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
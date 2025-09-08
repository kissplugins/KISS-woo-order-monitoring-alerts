## Changelog

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
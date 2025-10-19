# KISS WooCommerce Order Monitor

A lightweight WordPress plugin that monitors WooCommerce order volume and sends email alerts when orders fall below configured thresholds. Designed for early detection of site issues, outages, or attacks affecting order processing.

## Features

- **Real-time Order Monitoring** - Checks order volume every 15 minutes
- **Peak/Off-Peak Thresholds** - Different alert thresholds for business hours vs off-hours
- **Email Notifications** - HTML email alerts with detailed information
- **WooCommerce Integration** - Seamless integration with WooCommerce settings
- **Performance Optimized** - Efficient database queries with caching
- **Action Scheduler Support** - More reliable than WP-Cron for critical monitoring
- **WP-CLI Commands** - Command-line management and testing
- **Site Health Integration** - WordPress Site Health dashboard integration

## Requirements

- WordPress 5.8 or higher
- WooCommerce 6.0 or higher
- PHP 7.4 or higher
- MySQL 5.7+ / MariaDB 10.3+

## Installation

1. Download the plugin files
2. Upload the `KISS-woo-order-monitoring-alerts` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin
4. Navigate to **WooCommerce → Settings → Order Monitor** (or click "Settings" on the plugins page)
5. Review and adjust the pre-configured settings for your store
6. Monitoring is enabled by default - you're ready to go!

## Configuration

### Basic Settings

- **Enable Monitoring** - Turn monitoring on/off
- **Peak Hours** - Define your business hours (e.g., 9:00 AM to 9:00 PM)
- **Peak Threshold** - Minimum orders expected per 15 minutes during peak hours
- **Off-Peak Threshold** - Minimum orders expected per 15 minutes during off-peak hours
- **Notification Emails** - Comma-separated list of email addresses for alerts

### Default Settings

The plugin comes pre-configured with sensible defaults:

- **Monitoring**: Enabled (since your intent is clear by installing the plugin)
- **Peak Hours**: 09:00 - 18:00 (9 AM to 6 PM)
- **Peak Threshold**: 10 orders per 15 minutes
- **Off-Peak Threshold**: 2 orders per 15 minutes
- **Notification Emails**: Your WordPress admin email

### Recommended Adjustments

- **Peak Hours**: Adjust for your actual business hours and timezone
- **Peak Threshold**: Set to 80% of your typical 15-minute order volume during busy hours
- **Off-Peak Threshold**: Set to 50% of your typical 15-minute order volume during quiet hours

## How It Works

1. **Monitoring Cycle**: Every 15 minutes, the plugin counts successful orders (completed/processing status)
2. **Threshold Check**: Compares order count against peak or off-peak threshold based on current time
3. **Alert Trigger**: If orders fall below threshold, sends email alert to configured recipients
4. **Smart Logging**: Logs all monitoring activity for debugging and analysis

## Email Alerts

When order volume drops below thresholds, you'll receive detailed email alerts including:

- Time period analyzed
- Expected vs actual order count
- Peak/off-peak status
- Potential causes checklist
- Direct link to WooCommerce orders page

## WP-CLI Commands

```bash
# Check order threshold manually
wp woom check

# Get current order count
wp woom count
wp woom count --minutes=30

# Send test notification
wp woom test

# View current configuration
wp woom config
```

## Troubleshooting

### Common Issues

**Monitoring Not Running**
- Check that monitoring is enabled in settings
- Verify WP-Cron is working on your site
- Check error logs for database connection issues

**No Email Alerts**
- Test email delivery using the "Send Test Notification" button
- Verify email addresses are valid
- Check spam/junk folders
- Ensure wp_mail() function is working

**False Alerts**
- Adjust thresholds based on your actual order patterns
- Consider seasonal variations and marketing campaigns
- Review peak hours settings for your timezone

### Debug Mode

Enable WordPress debug mode to see detailed logging:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for monitoring activity.

## Performance

- **Minimal Impact**: Optimized queries with caching
- **Efficient Scheduling**: Uses Action Scheduler when available
- **Smart Fallbacks**: Graceful degradation on errors
- **Resource Conscious**: Single file architecture for reliability

## Security

- **Capability Checks**: Requires `manage_woocommerce` permission
- **Input Validation**: All settings are validated and sanitized
- **Nonce Protection**: CSRF protection on all forms
- **SQL Injection Prevention**: Prepared statements for all queries
- **Automated Security Scanning**: WPScan GitHub Action runs on every push/PR
  - Validates WordPress security best practices
  - Checks for common vulnerabilities (SQL injection, XSS, CSRF, etc.)
  - Weekly scheduled scans
  - Optional vulnerability database integration

## License

This plugin is licensed under the **GNU General Public License v2 (GPL-2.0)**.

You can find the full license text in the `LICENSE` file included with this plugin, or at:
https://www.gnu.org/licenses/gpl-2.0.html

## Disclaimer

**USE AT YOUR OWN RISK**

This plugin is provided "as-is" without any warranty of any kind, either expressed or implied. The authors and contributors are not responsible for any damages, data loss, or issues that may arise from the use of this plugin.

**Important Notes:**
- Always test thoroughly in a staging environment before deploying to production
- Backup your website before installation
- Monitor plugin performance and adjust settings as needed
- This plugin is designed to detect issues, not prevent them

## Support

This is a community-supported plugin. For issues and feature requests:

1. Check the troubleshooting section above
2. Review WordPress and WooCommerce error logs
3. Test with default WordPress and WooCommerce themes
4. Create detailed bug reports with steps to reproduce

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes with proper documentation
4. Test thoroughly
5. Submit a pull request

---

**KISS Plugins** - Keep It Simple (Stupid) :-)

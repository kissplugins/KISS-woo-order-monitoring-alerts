# WooCommerce Order Monitor Plugin
## Project Plan & Requirements Document

### 1. Executive Summary

A lightweight WooCommerce plugin that monitors successful order completion rates and sends email alerts when orders fall below configured thresholds during 15-minute intervals. The system differentiates between peak and non-peak hours to reduce false positives.

**Primary Goal**: Early detection of site issues, outages, or attacks affecting order processing.

### 2. Core Features (MVP)

#### 2.1 Order Monitoring
- Monitor successful WooCommerce orders (status: completed or processing)
- Check order counts every 15 minutes
- Separate thresholds for peak and non-peak hours
- Email notifications when thresholds are breached

#### 2.2 Admin Configuration
- Define peak hours (start/end times)
- Set minimum order thresholds for peak hours
- Set minimum order thresholds for non-peak hours
- Configure notification email recipients
- Enable/disable monitoring
- Test notification system

### 3. Technical Requirements

#### 3.1 System Requirements
- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+

#### 3.2 Architecture Overview
```
┌─────────────────────────────────────┐
│     WooCommerce Order Monitor       │
├─────────────────────────────────────┤
│  Admin Interface (Settings Page)    │
├─────────────────────────────────────┤
│     Monitoring Engine               │
│  ├── Order Counter                  │
│  ├── Threshold Checker              │
│  └── Alert Dispatcher               │
├─────────────────────────────────────┤
│     Data Layer                      │
│  ├── Settings Storage (wp_options)  │
│  └── Last Check Tracking            │
└─────────────────────────────────────┘
```

#### 3.3 Database Schema

**Settings Storage (wp_options table)**:
- `woom_enabled`: boolean
- `woom_peak_start`: time (HH:MM)
- `woom_peak_end`: time (HH:MM)
- `woom_threshold_peak`: integer
- `woom_threshold_offpeak`: integer
- `woom_notification_emails`: serialized array
- `woom_last_check`: timestamp
- `woom_last_alert`: timestamp

### 4. Functional Specifications

#### 4.1 Monitoring Logic
1. Cron job runs every 15 minutes
2. Query successful orders from last 15 minutes
3. Determine if current time is peak or off-peak
4. Compare order count against appropriate threshold
5. Send alert if count < threshold
6. Log monitoring event

#### 4.2 Alert System
- **Email Format**: 
  - Subject: `[Alert] WooCommerce Orders Below Threshold`
  - Include: timestamp, expected orders, actual orders, time period
  - Include: direct link to WooCommerce orders page
  
#### 4.3 Admin Interface
Location: WooCommerce → Settings → Order Monitor

**Settings Fields**:
1. Enable/Disable monitoring (checkbox)
2. Peak hours start time (time picker)
3. Peak hours end time (time picker)
4. Peak hours minimum orders (number input)
5. Off-peak hours minimum orders (number input)
6. Notification emails (textarea, comma-separated)
7. Send test notification (button)
8. Monitoring status (display last check time)

### 5. Code Implementation

See accompanying code snippets for:
- Main plugin class structure
- Cron scheduling and monitoring logic
- Order counting queries
- Email notification system
- Admin settings interface
- Installation/activation routines

### 6. Performance Considerations

#### 6.1 Query Optimization
- Use direct database queries with proper indexes
- Cache threshold values in memory during execution
- Limit query to only necessary order fields

#### 6.2 Cron Reliability
- Use Action Scheduler (included with WooCommerce) for more reliable scheduling
- Fallback to WP-Cron if Action Scheduler unavailable
- Include manual trigger option in admin

### 7. Security Considerations

- Capability checks for admin settings (manage_woocommerce)
- Sanitize all input data
- Validate email addresses
- Escape output in admin interface
- Nonce verification for test notifications

### 8. Installation & Setup

#### 8.1 Installation Steps
1. Upload plugin to `/wp-content/plugins/`
2. Activate through WordPress admin
3. Navigate to WooCommerce → Settings → Order Monitor
4. Configure thresholds and peak hours
5. Add notification email addresses
6. Enable monitoring

#### 8.2 Initial Configuration Recommendations
- **Peak Hours**: Based on typical business hours (e.g., 9 AM - 9 PM)
- **Peak Threshold**: 80% of typical 15-minute order volume
- **Off-Peak Threshold**: 50% of typical off-peak 15-minute volume
- Start with single admin email for notifications

### 9. Testing Plan

#### 9.1 Unit Tests
- Threshold calculation logic
- Peak/off-peak time determination
- Order counting query accuracy

#### 9.2 Integration Tests
- Cron job execution
- Email delivery
- Settings save/retrieve
- WooCommerce order status changes

#### 9.3 Manual Testing Checklist
- [ ] Plugin activation/deactivation
- [ ] Settings save correctly
- [ ] Test notification sends
- [ ] Cron job executes on schedule
- [ ] Correct threshold detection
- [ ] Peak/off-peak logic works across timezones
- [ ] Email formatting is correct

### 10. Future Enhancements (Phase 2)

1. **Slack Integration** (v1.1)
   - Webhook configuration
   - Rich message formatting
   - Channel selection

2. **SMS Notifications** (v1.2)
   - Twilio integration
   - Phone number management
   - SMS rate limiting

3. **Advanced Features** (v2.0)
   - Historical data tracking
   - Trend analysis
   - Auto-threshold suggestions
   - Cooldown periods
   - Multiple threshold levels
   - Day-of-week variations
   - Holiday schedule support

### 11. Support & Maintenance

#### 11.1 Error Handling
- Graceful degradation if WooCommerce inactive
- Clear error messages in logs
- Admin notices for configuration issues

#### 11.2 Debugging
- Optional debug mode
- Detailed logging to custom log file
- Monitoring status widget in admin

### 12. Project Timeline

**Week 1-2**: Core Development
- Plugin structure and activation
- Settings page implementation
- Monitoring engine

**Week 3**: Integration & Testing
- Cron job setup
- Email notifications
- Admin interface polish

**Week 4**: QA & Documentation
- Comprehensive testing
- Bug fixes
- User documentation
- Deployment preparation

### 13. Success Metrics

- Successfully detects 100% of threshold breaches
- Zero false negatives (missed legitimate issues)
- Email delivery rate > 99%
- Cron execution reliability > 95%
- Performance impact < 50ms per check

### 14. Risk Mitigation

| Risk | Impact | Mitigation |
|------|--------|------------|
| Cron failures | Missed monitoring | Action Scheduler + manual trigger |
| Email delivery issues | Missed alerts | Multiple recipients + delivery logs |
| Performance impact | Site slowdown | Optimized queries + caching |
| False positives | Alert fatigue | Peak/off-peak thresholds |
| Timezone issues | Wrong thresholds | Store timezone handling |

### 15. Documentation Requirements

- README.md with installation instructions
- Inline code documentation (PHPDoc)
- Admin help text and tooltips
- Troubleshooting guide
- FAQ section

---

## Appendix A: Data Flow Diagram

```
[WP Cron / Action Scheduler]
         ↓
[Every 15 minutes trigger]
         ↓
[Query successful orders (15 min window)]
         ↓
[Count orders]
         ↓
[Check current time → Peak or Off-peak?]
         ↓
[Compare count vs threshold]
         ↓
[Below threshold?] → Yes → [Send email alert]
         ↓ No
[Log check complete]
```

## Appendix B: Email Template Example

**Subject**: ⚠️ WooCommerce Order Alert - Orders Below Threshold

**Body**:
```
Alert: Order volume has fallen below the configured threshold.

Details:
- Time Period: [START_TIME] to [END_TIME]
- Expected Orders: [THRESHOLD] or more
- Actual Orders: [COUNT]
- Threshold Type: [PEAK/OFF-PEAK]

This could indicate:
• Website downtime or errors
• Payment gateway issues
• Potential DDoS attack
• Cart/checkout problems

Action Required:
Please check your WooCommerce store immediately.

View Orders: [ADMIN_URL]

---
This is an automated alert from WooCommerce Order Monitor
```

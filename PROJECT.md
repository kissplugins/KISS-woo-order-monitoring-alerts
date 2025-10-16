# WooCommerce Order Monitor Plugin
## Project Plan & Requirements Document

---

## 🎯 **CURRENT PRIORITY: Rolling Average Detection (RAD)**
**Status**: Active Development
**Started**: October 16, 2025
**Target**: v1.6.0

**Quick Links**:
- [RAD Project Details](#project-rad-rolling-average-detection) ← Jump to full specification
- [Original Time-Based Monitoring](#1-executive-summary) ← Current implementation

---

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

---
---

# PROJECT-RAD: Rolling Average Detection

## 🎯 Project Overview

**Goal**: Implement failure-rate based monitoring that works for both high-volume and low-volume stores by tracking order success/failure patterns instead of time-based thresholds.

**Problem Being Solved**:
- **Bloomzhelm** (high-volume): Current time-based monitoring works well
- **Bloomzhemp** (low-volume): Can go an hour without orders, making time-based monitoring ineffective
- **Need**: Detect when a high percentage of orders are failing, regardless of order frequency

**Solution**: Track the last N orders in a rolling window and alert when failure rate exceeds threshold.

---

## 📊 High-Level Phase Overview

- [ ] **Phase 1: Core RAD Foundation** - Basic rolling window tracking with immediate value ⏳ NEXT
- [ ] **Phase 2: Dual-Mode Monitoring** - Run RAD alongside time-based monitoring
- [ ] **Phase 3: Advanced Analytics** - Trend analysis and intelligent alerting

---

## 📋 Detailed Implementation Plan

### Phase 1: Core RAD Foundation (Week 1-2) ⏳ IMMEDIATE PRIORITY

**Goal**: Get basic rolling average detection working with immediate practical value.

#### 🏗️ Core Components

**1.1 Order History Tracking**
- [ ] Add `woom_order_history` option to store recent orders
- [ ] Create data structure: `[{id, status, time}, ...]`
- [ ] Implement array size limiting (keep last N orders)
- [ ] Add helper methods: `addOrderToHistory()`, `getOrderHistory()`, `pruneHistory()`

**1.2 WooCommerce Hook Integration**
- [ ] Hook into `woocommerce_order_status_changed` action
- [ ] Detect order success (completed, processing) vs failure (failed, cancelled)
- [ ] Automatically track orders in real-time (no cron needed for tracking)
- [ ] Handle edge cases (refunds, manual status changes)

**1.3 Settings & Configuration**
- [ ] Add RAD settings to `SettingsDefaults.php`:
  - `rolling_window_size` (default: 10 orders)
  - `rolling_failure_threshold` (default: 70%)
  - `rolling_min_orders` (default: 3 orders before alerting)
  - `rolling_enabled` (default: no - opt-in for Phase 1)
- [ ] Add UI fields to settings page (new "Rolling Average" section)
- [ ] Add help text explaining how RAD works

**1.4 Failure Rate Calculation**
- [ ] Create `calculateFailureRate()` method
- [ ] Return percentage of failed orders in window
- [ ] Handle edge cases (empty history, insufficient data)
- [ ] Add debug logging for troubleshooting

**1.5 Basic Alerting**
- [ ] Check failure rate after each order is added to history
- [ ] Send alert if:
  - Failure rate > threshold AND
  - Minimum order count met AND
  - Not in cooldown period (reuse existing throttling)
- [ ] Email template for RAD alerts (different from time-based)

**Phase 1 Success Criteria**:
- ✅ Orders automatically tracked in rolling window
- ✅ Failure rate calculated correctly
- ✅ Alerts sent when threshold breached
- ✅ Works on low-volume stores (Bloomzhemp)
- ✅ No performance impact on order processing
- ✅ Can be enabled/disabled independently

**Phase 1 Deliverables**:
- Working RAD system that can be enabled via settings
- Self-tests for RAD functionality
- Documentation for RAD settings
- Email alerts specific to RAD detection

---

### Phase 2: Dual-Mode Monitoring (Week 3-4)

**Goal**: Run both time-based and RAD monitoring simultaneously for comprehensive coverage.

#### 🔄 Hybrid Monitoring System

**2.1 Monitoring Mode Selection**
- [ ] Add `monitoring_mode` setting:
  - `time_based` - Original 15-minute threshold monitoring
  - `rolling_average` - RAD only
  - `hybrid` - Both systems active (recommended)
- [ ] UI to select monitoring mode with recommendations
- [ ] Help text explaining when to use each mode

**2.2 Alert Coordination**
- [ ] Prevent duplicate alerts from both systems
- [ ] Unified alert history tracking
- [ ] Smart cooldown that works across both systems
- [ ] Alert source identification (time-based vs RAD)

**2.3 Per-Store Configuration**
- [ ] Store-specific recommendations:
  - High-volume stores: Hybrid mode
  - Low-volume stores: RAD-only mode
  - Medium-volume: Time-based with RAD backup
- [ ] Auto-detection of store volume
- [ ] Suggested settings based on order history

**2.4 Enhanced Reporting**
- [ ] Show both metrics in manual check:
  - Time-based: "2 orders in last 15 min"
  - RAD: "70% failure rate (7 of 10 orders failed)"
- [ ] Combined health status indicator
- [ ] Historical comparison (both metrics)

**Phase 2 Success Criteria**:
- ✅ Both monitoring systems work independently
- ✅ No duplicate alerts
- ✅ Clear indication of which system triggered alert
- ✅ Easy to configure for different store types
- ✅ Comprehensive coverage (catches issues either system would miss)

**Phase 2 Deliverables**:
- Hybrid monitoring mode
- Enhanced manual check with both metrics
- Store-type recommendations
- Updated documentation

---

### Phase 3: Advanced Analytics (Week 5-6)

**Goal**: Add intelligence and trend analysis for proactive issue detection.

#### 📈 Intelligent Monitoring

**3.1 Trend Analysis**
- [ ] Track failure rate trends over time
- [ ] Detect gradual degradation (failure rate increasing)
- [ ] Early warning alerts (before threshold breach)
- [ ] Historical pattern recognition

**3.2 Adaptive Thresholds**
- [ ] Learn normal failure rates for the store
- [ ] Suggest threshold adjustments based on history
- [ ] Seasonal/day-of-week pattern detection
- [ ] Auto-adjust window size based on order volume

**3.3 Enhanced Diagnostics**
- [ ] Identify common failure patterns:
  - Payment gateway issues (all payment failures)
  - Inventory issues (out-of-stock failures)
  - Checkout errors (abandoned at specific step)
- [ ] Include diagnostic hints in alerts
- [ ] Link to relevant WooCommerce logs

**3.4 Visualization & Reporting**
- [ ] Admin dashboard widget showing:
  - Current failure rate
  - Trend graph (last 24 hours)
  - Recent failed orders
  - Alert history
- [ ] Export reports (CSV/PDF)
- [ ] Weekly summary emails

**3.5 Integration Enhancements**
- [ ] Slack notifications with rich formatting
- [ ] Webhook support for external monitoring
- [ ] REST API endpoints for failure rate data
- [ ] WP-CLI commands for monitoring status

**Phase 3 Success Criteria**:
- ✅ Proactive issue detection (before major problems)
- ✅ Actionable diagnostic information in alerts
- ✅ Visual dashboard for at-a-glance monitoring
- ✅ Historical data for trend analysis
- ✅ Integration with external tools

**Phase 3 Deliverables**:
- Trend analysis engine
- Admin dashboard widget
- Enhanced alert templates with diagnostics
- API endpoints and integrations
- Comprehensive reporting system

---

## 🎯 Success Metrics

### Phase 1 Metrics:
- [ ] Detects 100% of high-failure-rate scenarios
- [ ] Works on stores with <1 order/hour
- [ ] Zero false positives from insufficient data
- [ ] <10ms overhead per order
- [ ] Self-tests pass for all RAD functions

### Phase 2 Metrics:
- [ ] Hybrid mode catches 100% of issues either system would catch
- [ ] Zero duplicate alerts
- [ ] Clear alert attribution (which system detected issue)
- [ ] Easy configuration (5 minutes or less)
- [ ] Works seamlessly with existing time-based monitoring

### Phase 3 Metrics:
- [ ] Early detection (alerts before 50% of orders fail)
- [ ] Diagnostic accuracy >80% (correct issue identification)
- [ ] Dashboard load time <500ms
- [ ] API response time <100ms
- [ ] User satisfaction with actionable alerts

---

## 🏗️ Technical Architecture

### Data Structure

```php
// wp_options: woom_order_history
[
    [
        'id' => 12345,
        'status' => 'success',  // or 'failed'
        'time' => 1697123456,
        'payment_method' => 'stripe',  // Phase 3
        'failure_reason' => null,      // Phase 3
    ],
    // ... up to N orders (configurable)
]
```

### Settings Schema

```php
// Added to SettingsDefaults.php
'rolling_enabled' => 'no',              // Phase 1
'rolling_window_size' => 10,            // Phase 1
'rolling_failure_threshold' => 70,      // Phase 1 (percentage)
'rolling_min_orders' => 3,              // Phase 1
'monitoring_mode' => 'hybrid',          // Phase 2
'rolling_trend_enabled' => 'no',        // Phase 3
'rolling_adaptive_threshold' => 'no',   // Phase 3
```

### Hook Integration

```php
// Phase 1: Basic tracking
add_action('woocommerce_order_status_changed',
    [$this, 'trackOrderInHistory'], 10, 4);

// Phase 3: Enhanced tracking
add_action('woocommerce_payment_complete',
    [$this, 'trackPaymentSuccess'], 10, 1);
add_action('woocommerce_order_status_failed',
    [$this, 'trackOrderFailure'], 10, 1);
```

---

## 🔄 Migration Strategy

### Non-Breaking Implementation:
1. **Additive Approach** - RAD added alongside existing monitoring
2. **Opt-in for Phase 1** - Disabled by default, enable via settings
3. **Gradual Rollout** - Test on one store before enabling on all
4. **Backward Compatible** - Existing time-based monitoring unchanged

### Risk Mitigation:
- **Feature Flag** - Can disable RAD without affecting time-based monitoring
- **Comprehensive Testing** - Self-tests for all RAD functions
- **Performance Monitoring** - Track overhead on order processing
- **Rollback Plan** - Can revert to time-based only if issues arise

---

## 📅 Timeline Estimate

- [ ] **Phase 1**: 1-2 weeks (Core RAD with immediate value)
- [ ] **Phase 2**: 1-2 weeks (Dual-mode monitoring)
- [ ] **Phase 3**: 2-3 weeks (Advanced analytics)
- [ ] **Total**: 4-7 weeks for complete RAD system

**Recommended Approach**: Complete Phase 1, deploy to production, gather feedback, then proceed with Phase 2.

---

## 🔗 Related Documents

- `PROJECT-FSM.md` - FSM architecture (deferred)
- `CHANGELOG.md` - Version history
- `src/Core/SettingsDefaults.php` - Centralized settings
- `src/Monitoring/OrderMonitor.php` - Main monitoring class

---

## 📝 Implementation Notes

### Why This Approach Works:

1. **Phase 1 = Immediate Value**
   - Solves Bloomzhemp's low-volume monitoring problem
   - Simple to implement and test
   - Can be deployed and validated quickly

2. **Phase 2 = Best of Both Worlds**
   - Keeps existing time-based monitoring (proven)
   - Adds RAD for edge cases
   - Comprehensive coverage

3. **Phase 3 = Intelligence Layer**
   - Builds on proven foundation
   - Adds proactive detection
   - Enterprise-ready features

### Key Design Decisions:

- **Array-based storage** - Simple, fast, no new database tables
- **Hook-based tracking** - Real-time, no cron overhead
- **Percentage-based thresholds** - Works for any order volume
- **Minimum order requirement** - Prevents false positives
- **Reuse existing throttling** - DRY principle, proven code

---

## 🚀 Next Steps

1. **Review this plan** - Confirm phases and approach
2. **Start Phase 1** - Begin with order history tracking
3. **Incremental development** - Small commits, frequent testing
4. **Deploy Phase 1** - Test on Bloomzhemp first
5. **Gather feedback** - Validate before Phase 2
6. **Iterate** - Adjust based on real-world usage

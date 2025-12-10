
# PROJECT.md: E-commerce Order Velocity Monitoring System

## 1. System Goal

To create a real-time order velocity monitoring system for an e-commerce platform (initial focus on WooCommerce, with portability to Shopify) that alerts the owner to potential cart checkout problems by tracking order velocity deviations using statistical control limits.

---

## 2. Core Algorithm and Statistics

The system employs **Anomaly Detection** based on **Statistical Process Control (SPC)**, specifically a Moving Average ($\bar{X}$) Control Chart, utilizing the $3\sigma$ rule to minimize false positives.

### A. Control Limit Formulas

The core statistical components are calculated as follows for each distinct time slot (e.g., Tuesday, 10 AM):

| Metric | Formula | Description |
| :--- | :--- | :--- |
| **CL (Center Line)** | $\mu_{\text{historic}}$ | The average hourly order volume over a stable historical period. |
| **UCL (Upper Control Limit)** | $\text{CL} + 3 \times \sigma_{\text{rolling}}$ | Three standard deviations **above** the mean. |
| **LCL (Lower Control Limit)** | $\text{CL} - 3 \times \sigma_{\text{rolling}}$ | Three standard deviations **below** the mean (must be $\ge 0$). |
| **$\sigma_{\text{rolling}}$ (Control Sigma)** | $\sigma_{\text{historic}} / \sqrt{N}$ | The standard deviation of the **moving average**, where $N$ is the size of your rolling window (e.g., 4 hours). |

### B. WooCommerce Cart Abandonment Note

WooCommerce **does not** include sophisticated, built-in cart abandonment analytics. A third-party plugin is required for dedicated tracking, calculation, and recovery features. This monitoring system focuses on *order volume* as a proxy for overall system health.

---

## 3. WordPress Plugin Architectural Outline

The system is structured using a hybrid approach, leveraging PHP for secure, heavy data processing and JavaScript/AJAX for real-time frontend visualization and alerting.

### A. Backend (PHP - Data & Calculation)

The PHP layer handles the heavy lifting of data retrieval, historical analysis, and calculation of the control limits.

| Component | Responsibility | Technical Details |
| :--- | :--- | :--- |
| **Data Aggregator** | Retrieve and organize historical WooCommerce order data. | Use `wc_get_orders()` with date/time filters. Aggregate counts into hourly buckets, grouped by time of day/day of week. |
| **Control Limit Calculator** | Calculate the baseline $\mu$, $\sigma$, UCL, and LCL for every specific time slot. | PHP implementation of the JavaScript functions provided previously (`calculateMean`, `calculateStandardDeviation`, etc.). **Store these limits** in the database (e.g., `wp_options` or a custom table) for quick retrieval. |
| **Real-Time Data Fetcher** | Get the current order count and calculate the current rolling average. | A lightweight function that fetches orders placed in the last $N$ hours. |
| **AJAX Endpoint** | Provide calculated data securely to the frontend. | WordPress AJAX action hooks (`wp_ajax_...`). This endpoint should return the current order velocity, the CL, the UCL, and the LCL. |

### B. Frontend (JavaScript/AJAX - Alerting & Display)

The JavaScript layer handles the continuous monitoring, visualization, and user alerts.

| Component | Responsibility | Technical Details |
| :--- | :--- | :--- |
| **Data Poller** | Periodically call the backend to check the order status. | Use `setInterval()` to make AJAX calls to the PHP endpoint every 5-15 minutes (adjust based on traffic/performance). |
| **Alert Logic** | Compare the current rolling average to the calculated limits. | This is where the core JavaScript logic (`checkAlert`) is used. |
| **User Interface** | Display a visual indicator (e.g., a dashboard widget or admin bar icon) showing the status. | If the current rolling average ($\bar{x}_{\text{current}}$) falls below LCL, trigger a visible, persistent alert in the WordPress Admin area. |
| **Dependencies** | Include the JavaScript file using `wp_enqueue_script()` only on the admin pages to optimize site performance. | Ensure all data passed to JS is secured/non-sensitive. |

### C. Example Logic Flow (Alerting)

 **Time:** It is currently Tuesday, 10:30 AM.
 **PHP Action:** The **Control Limit Calculator** fetches the pre-calculated limits for "Tuesday, 10 AM" from the database: $CL=30$, $UCL=45$, $LCL=15$.
 **JS AJAX Call:** The Data Poller requests the current rolling average from the PHP endpoint.
 **PHP Response:** The Real-Time Data Fetcher calculates the rolling average for the last 4 hours (7:30 AM - 10:30 AM), finds $\bar{x}_{\text{current}}=12$ orders/hr, and returns this value along with the limits.
 **JS Alert Logic:** `if (12 < 15)` is TRUE.
 **User Alert:** The JS code triggers a visible, persistent Admin Notice: ":rotating_light: ALERT: Low Velocity! Order rate (12/hr) is below LCL (15/hr). Check site status."

---

## 4. Portability to Shopify

The **logic is fully portable** because the core engine relies only on standard statistical mathematics.

**PHP $\to$ Ruby/Python:** The data retrieval and control limit calculation would be moved to a Shopify App's backend, likely written in Ruby (Rails) or Python.
**WooCommerce Orders $\to$ Shopify API:** Instead of `wc_get_orders()`, the system would use the **Shopify Admin API** (`/admin/api/2024-04/orders.json`) to fetch order data and calculate the historical and current rolling averages.
**JS/Frontend:** The JavaScript component remains largely the same, using AJAX to communicate with the Shopify App's API endpoint instead of the WordPress AJAX endpoint.

===

## GAP Analayis Augment

Great question! Let me analyze whether the **Statistical Process Control (SPC)** approach in PROJECT-RETHINK.md would actually make your WooCommerce order monitoring **more robust and reliable** compared to the current implementation.

---

## 🎯 **SPC vs Current Approach: Robustness Analysis**

### **Current Implementation Strengths:**

Your existing system is actually **quite robust** for its intended purpose:

````php path=src/Monitoring/OrderMonitor.php mode=EXCERPT
public function checkOrderThreshold(): array {
    $order_count = $this->query->getOrderCount($this->monitoring_period);
    $threshold_result = $this->threshold_checker->checkThreshold($order_count);
    // Simple, predictable, easy to understand
}
````

**✅ Current System Advantages:**
1. **Simple & Predictable** - You know exactly when alerts trigger
2. **Easy to Configure** - Set peak/off-peak thresholds based on your business
3. **Low Complexity** - Fewer moving parts = fewer failure points
4. **Immediate Feedback** - No "learning period" required
5. **Transparent** - Easy to debug and explain to stakeholders

---

### **SPC Approach: When Does It Help?**

SPC is **excellent** for certain scenarios, but may be **overkill** for others:

#### **✅ SPC Excels When:**

1. **High Variability in Normal Patterns**
   - Your order volume varies significantly by:
     - Day of week (Monday vs Saturday)
     - Time of day (10 AM vs 3 PM)
     - Seasonal patterns (holidays, sales events)
   - **Example:** Tuesday 10 AM normally gets 30 orders/hr, but Tuesday 3 PM gets 8 orders/hr
   - **Problem with current system:** You'd need to set different thresholds for every time slot manually
   - **SPC solution:** Automatically learns "Tuesday 10 AM = 30±5" and "Tuesday 3 PM = 8±2"

2. **Reducing False Positives**
   - Current system: If you set threshold too high → miss real problems
   - Current system: If you set threshold too low → alert fatigue from normal dips
   - **SPC solution:** 3σ rule statistically minimizes false alarms (99.7% confidence)

3. **Detecting Subtle Degradation**
   - **Example:** Your checkout slowly degrades from 30 orders/hr → 25 → 22 → 18
   - **Current system:** Only alerts when it drops below fixed threshold (e.g., 10)
   - **SPC solution:** Alerts when it drops below LCL (e.g., 25), catching problems earlier

4. **Hands-Off Operation**
   - **Current system:** You manually adjust thresholds as business grows
   - **SPC solution:** Automatically recalculates baselines from historical data

---

#### **❌ SPC May Be Overkill When:**

1. **Stable, Predictable Traffic**
   - If your order volume is relatively consistent throughout the day/week
   - **Example:** You get 8-12 orders every 15 minutes, all day, every day
   - **Current system works fine:** Set threshold to 5, done

2. **Binary Failure Modes**
   - If your checkout either works (normal volume) or completely breaks (zero orders)
   - **Current system works fine:** Simple threshold catches complete failures

3. **Small Sample Sizes**
   - If you only get 2-5 orders per 15 minutes
   - **SPC problem:** Statistical methods need larger samples to be reliable
   - **Current RAD (v1.6.0) is better:** Failure rate % works well with low volume

4. **Frequent Business Changes**
   - If you're constantly running promotions, changing products, or adjusting marketing
   - **SPC problem:** Historical baselines become outdated quickly
   - **Current system works better:** You manually adjust thresholds when you change strategy

---

### **🔍 Key Question: What's Your Traffic Pattern?**

Let me help you decide. **Which scenario describes your store better?**

#### **Scenario A: High Variability** (SPC would help)
```
Monday 9 AM:     5 orders/15min  (slow start to week)
Monday 12 PM:   15 orders/15min  (lunch rush)
Monday 6 PM:    25 orders/15min  (evening peak)
Friday 9 AM:    12 orders/15min  (weekend prep)
Friday 6 PM:    40 orders/15min  (weekend rush)
Saturday 2 PM:  50 orders/15min  (weekend peak)
```
**Problem:** You'd need 168 different thresholds (24 hours × 7 days) to avoid false alerts
**SPC Solution:** Automatically learns each time slot's pattern

---

#### **Scenario B: Stable Volume** (Current system is fine)
```
Every day, all hours: 8-12 orders/15min (±20% variation)
Occasional dips to 5-7 orders (normal fluctuation)
Real problems: Drops to 0-2 orders (checkout broken)
```
**Current System:** Set threshold to 5, works perfectly
**SPC:** Would work, but adds complexity without much benefit

---

### **💡 Hybrid Recommendation: Best of Both Worlds**

Instead of full SPC, consider **enhancing your current system** with selective statistical features:

#### **Option 1: Time-Slot Aware Thresholds** (Medium Complexity)
```php
// Instead of just peak/off-peak, have hourly thresholds
$thresholds = [
    'monday' => [
        '09:00' => 5,
        '12:00' => 15,
        '18:00' => 25,
        // ...
    ],
    'friday' => [
        '09:00' => 12,
        '18:00' => 40,
        // ...
    ]
];
```
**Benefit:** Captures time-based patterns without statistical complexity
**Effort:** 1-2 weeks to implement

---

#### **Option 2: Adaptive Thresholds** (Low Complexity)
```php
// Automatically adjust thresholds based on recent history
$last_7_days_avg = calculateAverageForTimeSlot('tuesday', '10:00', 7);
$dynamic_threshold = $last_7_days_avg * 0.7; // Alert if 30% below recent average
```
**Benefit:** Self-adjusting without manual updates
**Effort:** 1 week to implement

---

#### **Option 3: Enhanced RAD with Velocity** (Medium Complexity)
```php
// Combine current RAD (failure rate) with velocity monitoring
if ($failure_rate > 70%) {
    alert("High failure rate!");
}
if ($order_velocity < $expected_velocity * 0.5) {
    alert("Low order velocity!");
}
```
**Benefit:** Catches both quality issues (failures) and volume issues (velocity)
**Effort:** 1-2 weeks to implement

---

#### **Option 4: Full SPC Implementation** (High Complexity)
- Implement everything in PROJECT-RETHINK.md
- **Benefit:** Maximum sophistication, minimal false positives
- **Effort:** 6-9 weeks (as estimated earlier)

---

### **🎯 My Recommendation**

Based on your goal of **"more robust and reliable"** monitoring:

**Start with Option 2 (Adaptive Thresholds) + Option 3 (Enhanced RAD with Velocity)**

**Why?**
1. ✅ **Immediate Value** - Improves reliability in 2-3 weeks vs 6-9 weeks
2. ✅ **Lower Risk** - Builds on proven current system
3. ✅ **Easier to Debug** - Simpler logic when things go wrong
4. ✅ **Incremental** - Can add SPC later if needed
5. ✅ **Addresses Core Issues:**
   - Adaptive thresholds → Reduces manual configuration
   - Velocity monitoring → Catches volume drops
   - Current RAD → Already catches quality issues

**Then evaluate:** After 1-2 months, if you're still getting:
- Too many false positives → Consider adding 3σ statistical filtering
- Missing subtle degradation → Consider time-slot-specific baselines
- Manual threshold adjustments → Consider full SPC

---

### **❓ Questions to Help Decide:**

1. **How variable is your traffic?** (Scenario A vs B above)
2. **What's your current false positive rate?** (Getting alerts when nothing is wrong)
3. **What's your current false negative rate?** (Missing real problems)
4. **How often do you manually adjust thresholds?** (Weekly? Monthly? Never?)
5. **What's your typical order volume?** (Orders per 15 minutes during peak/off-peak)

**Your answers will help determine if SPC's complexity is justified or if simpler enhancements would be more robust.**

What are your thoughts? Would you like me to implement one of the hybrid options, or do you want to go straight to full SPC?

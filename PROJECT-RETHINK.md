
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

---

## Gap Analysis: SPC Vision vs Implementation

I'll analyze the PROJECT-RETHINK.md file against the current codebase implementation to provide you with a comprehensive gap and alignment analysis.

Now let me gather more information about the current implementation to complete the analysis:

Now let me check for any existing statistical calculations or historical data analysis:

Perfect! Now I have a comprehensive understanding of both the PROJECT-RETHINK.md vision and the current implementation. Let me create a detailed gap and alignment analysis.

## Gap and Alignment Analysis: PROJECT-RETHINK.md vs Current Implementation

Based on my analysis of PROJECT-RETHINK.md against the current codebase, here's a comprehensive gap and alignment report:

---

## 📊 **EXECUTIVE SUMMARY**

**Overall Alignment:** ~35% Complete  
**Status:** Current implementation uses a **simpler threshold-based approach** instead of the **Statistical Process Control (SPC)** methodology outlined in PROJECT-RETHINK.md

**Key Finding:** The plugin has evolved in a **different direction** than PROJECT-RETHINK.md envisioned, focusing on:
- ✅ Multi-block time-based thresholds (v1.7.0-1.7.1)
- ✅ Rolling Average Detection for failure rates (v1.6.0)
- ❌ **NOT** implementing SPC control charts with μ, σ, UCL, LCL

---

## 🎯 **DETAILED GAP ANALYSIS**

### **1. CORE ALGORITHM & STATISTICS**

| PROJECT-RETHINK.md Vision | Current Implementation | Status | Gap Severity |
|---------------------------|------------------------|--------|--------------|
| **Statistical Process Control (SPC)** with 3σ rule | Simple threshold comparison | ❌ **NOT IMPLEMENTED** | 🔴 **CRITICAL** |
| Moving Average (X̄) Control Chart | Fixed 15-minute window counts | ❌ **NOT IMPLEMENTED** | 🔴 **CRITICAL** |
| Calculate μ (mean) per time slot | Uses static thresholds per block | ❌ **NOT IMPLEMENTED** | 🔴 **CRITICAL** |
| Calculate σ (std deviation) | No statistical variance tracking | ❌ **NOT IMPLEMENTED** | 🔴 **CRITICAL** |
| UCL = CL + 3σ | Uses fixed threshold values | ❌ **NOT IMPLEMENTED** | 🔴 **CRITICAL** |
| LCL = CL - 3σ | Uses fixed threshold values | ❌ **NOT IMPLEMENTED** | 🔴 **CRITICAL** |
| Historical data aggregation by time slot | No historical analysis | ❌ **NOT IMPLEMENTED** | 🔴 **CRITICAL** |
| Rolling window (N hours) | Fixed 15-minute intervals | ⚠️ **PARTIAL** | 🟡 **MEDIUM** |

**Current Approach:**
```php
// What PROJECT-RETHINK.md wants:
$current_avg = calculateRollingAverage($last_4_hours);
$lcl = $historical_mean - (3 * $historical_stddev / sqrt($window_size));
if ($current_avg < $lcl) { alert(); }

// What we actually have:
$order_count = getOrderCount(15); // Last 15 minutes
$threshold = getThresholdForCurrentTime(); // Static value
if ($order_count < $threshold) { alert(); }
```

---

### **2. BACKEND ARCHITECTURE (PHP)**

| Component | PROJECT-RETHINK.md | Current Implementation | Status |
|-----------|-------------------|------------------------|--------|
| **Data Aggregator** | Aggregate into hourly buckets by day/time | ❌ No aggregation | 🔴 **MISSING** |
| **Control Limit Calculator** | Calculate μ, σ, UCL, LCL per time slot | ❌ No statistical calculations | 🔴 **MISSING** |
| **Real-Time Data Fetcher** | Calculate rolling average over N hours | ✅ Gets 15-min order count | 🟢 **PARTIAL** |
| **AJAX Endpoint** | Return velocity + CL + UCL + LCL | ⚠️ Returns order count + threshold | 🟡 **PARTIAL** |
| **Database Storage** | Store control limits in DB | ❌ Only stores static thresholds | 🔴 **MISSING** |

**What Exists:**
- ✅ `OrderMonitor.php` - Monitors orders every 15 minutes
- ✅ `ThresholdChecker.php` - Compares against static thresholds
- ✅ `OrderQuery.php` - Fetches order counts
- ❌ **NO** historical data aggregation
- ❌ **NO** statistical calculations
- ❌ **NO** control limit storage

---

### **3. FRONTEND (JavaScript/AJAX)**

| Component | PROJECT-RETHINK.md | Current Implementation | Status |
|-----------|-------------------|------------------------|--------|
| **Data Poller** | Poll every 5-15 minutes via AJAX | ❌ No frontend polling | 🔴 **MISSING** |
| **Alert Logic** | Compare rolling avg to LCL | ❌ Backend-only alerts | 🔴 **MISSING** |
| **User Interface** | Dashboard widget or admin bar icon | ❌ No real-time UI | 🔴 **MISSING** |
| **Visual Indicator** | Show current velocity vs. limits | ❌ No visualization | 🔴 **MISSING** |
| **Persistent Admin Notice** | Show alert in WP Admin | ⚠️ Email alerts only | 🟡 **PARTIAL** |

**Current Approach:**
- ✅ Backend cron job runs every 15 minutes
- ✅ Email alerts sent when threshold breached
- ❌ **NO** real-time frontend monitoring
- ❌ **NO** admin dashboard widgets
- ❌ **NO** AJAX polling

---

### **4. DATA STORAGE & HISTORICAL ANALYSIS**

| Requirement | PROJECT-RETHINK.md | Current Implementation | Status |
|-------------|-------------------|------------------------|--------|
| Historical order data | Aggregate by hour/day/time slot | ❌ No historical storage | 🔴 **MISSING** |
| Control limits storage | Store μ, σ, UCL, LCL per slot | ❌ Only static thresholds | 🔴 **MISSING** |
| Database table | Custom table or wp_options | ✅ Uses wp_options | 🟢 **ALIGNED** |
| Baseline calculation | Calculate from stable period | ❌ No baseline learning | 🔴 **MISSING** |
| Time slot grouping | Tuesday 10 AM, Wednesday 2 PM, etc. | ⚠️ 8 time blocks (not day-aware) | 🟡 **PARTIAL** |

**Current Storage:**
```php
// What we store:
'threshold_peak' => 10,
'threshold_offpeak' => 2,
'threshold_blocks' => [ /* 8 static blocks */ ]

// What PROJECT-RETHINK.md wants:
'control_limits' => [
    'tuesday_10am' => ['CL' => 30, 'UCL' => 45, 'LCL' => 15, 'sigma' => 5],
    'tuesday_11am' => ['CL' => 35, 'UCL' => 50, 'LCL' => 20, 'sigma' => 5],
    // ... for every hour of every day
]
```

---

### **5. EXAMPLE LOGIC FLOW**

**PROJECT-RETHINK.md Vision:**
```
1. Current time: Tuesday, 10:30 AM
2. PHP fetches pre-calculated limits for "Tuesday, 10 AM"
   → CL=30, UCL=45, LCL=15
3. JS polls via AJAX every 5-15 minutes
4. PHP calculates rolling average (last 4 hours)
   → 7:30 AM - 10:30 AM = 12 orders/hr
5. PHP returns: {current: 12, CL: 30, UCL: 45, LCL: 15}
6. JS checks: if (12 < 15) → ALERT
7. JS shows persistent admin notice
```

**Current Implementation:**
```
1. Cron runs every 15 minutes (backend only)
2. PHP gets order count for last 15 minutes
   → 3 orders
3. PHP determines current time block
   → "morning_steady" (08:00-10:59)
4. PHP gets threshold for block
   → threshold = 10
5. PHP checks: if (3 < 10) → ALERT
6. PHP sends email alert
7. No frontend interaction
```

**Gap:** ❌ No AJAX polling, ❌ No rolling averages, ❌ No statistical limits, ❌ No frontend UI

---

## ✅ **WHAT IS ALIGNED**

### **Implemented Features That Match Vision:**

1. **✅ WooCommerce Integration**
   - Uses `wc_get_orders()` as specified
   - Monitors order volume as proxy for system health
   - Supports HPOS (High-Performance Order Storage)

2. **✅ Time-Based Monitoring**
   - Multi-block threshold system (v1.7.0)
   - 8 time blocks covering 24 hours
   - Time-appropriate thresholds

3. **✅ Alert System**
   - Email notifications
   - Throttling (cooldown, daily limits)
   - Escalation (first, normal, escalated alerts)

4. **✅ Settings Storage**
   - Uses `wp_options` table
   - Centralized defaults (`SettingsDefaults.php`)
   - Validation and sanitization

5. **✅ Portability Foundation**
   - Clean separation of concerns
   - PSR-4 architecture (partial)
   - Could be adapted to Shopify

---

## 🔴 **CRITICAL GAPS**

### **Missing Core Components:**

1. **❌ Statistical Process Control (SPC)**
   - No mean (μ) calculation
   - No standard deviation (σ) calculation
   - No control limits (UCL/LCL)
   - No 3σ rule implementation

2. **❌ Historical Data Aggregation**
   - No hourly bucketing
   - No day-of-week grouping
   - No time-slot specific baselines
   - No historical analysis

3. **❌ Rolling Average Calculation**
   - No multi-hour rolling windows
   - Only fixed 15-minute snapshots
   - No velocity tracking

4. **❌ Frontend Real-Time Monitoring**
   - No AJAX polling
   - No dashboard widgets
   - No admin bar indicators
   - No visual alerts in WP Admin

5. **❌ Control Limit Storage**
   - No database schema for limits
   - No pre-calculated baselines
   - No per-time-slot configuration

---

## 🟡 **PARTIAL IMPLEMENTATIONS**

### **Features That Exist But Differ:**

1. **⚠️ Rolling Average Detection (RAD)**
   - **Implemented:** Tracks failure rate of last N orders
   - **Gap:** Not the same as velocity rolling average
   - **Status:** Different use case (failure detection vs. volume anomaly)

2. **⚠️ Multi-Block Thresholds**
   - **Implemented:** 8 static time blocks
   - **Gap:** Not day-of-week aware (no "Tuesday 10 AM" vs "Wednesday 10 AM")
   - **Status:** Simpler but less precise

3. **⚠️ Threshold Checking**
   - **Implemented:** Static threshold comparison
   - **Gap:** Not statistical control limits
   - **Status:** Works but lacks SPC sophistication

---

## 📈 **IMPLEMENTATION ROADMAP TO ALIGN**

### **Phase 1: Historical Data Foundation** (Estimated: 2-3 weeks)

**Tasks:**
1. Create custom database table for historical aggregation
2. Implement hourly order bucketing
3. Add day-of-week + hour grouping
4. Build data aggregation cron job

**Files to Create:**
- `src/Statistics/HistoricalAggregator.php`
- `src/Statistics/DataBucket.php`
- Database migration script

---

### **Phase 2: Statistical Calculations** (Estimated: 2-3 weeks)

**Tasks:**
1. Implement mean (μ) calculation per time slot
2. Implement standard deviation (σ) calculation
3. Calculate control limits (UCL/LCL) using 3σ rule
4. Store limits in database

**Files to Create:**
- `src/Statistics/ControlLimitCalculator.php`
- `src/Statistics/StatisticalFunctions.php`

**Example Implementation:**
```php
class ControlLimitCalculator {
    public function calculateLimits(array $historical_data, int $window_size): array {
        $mean = $this->calculateMean($historical_data);
        $stddev = $this->calculateStdDev($historical_data);
        $sigma_rolling = $stddev / sqrt($window_size);
        
        return [
            'CL' => $mean,
            'UCL' => $mean + (3 * $sigma_rolling),
            'LCL' => max(0, $mean - (3 * $sigma_rolling))
        ];
    }
}
```

---

### **Phase 3: Rolling Average Monitoring** (Estimated: 1-2 weeks)

**Tasks:**
1. Implement multi-hour rolling window
2. Calculate current rolling average
3. Compare against control limits
4. Update alert logic

**Files to Modify:**
- `src/Monitoring/OrderMonitor.php`
- `src/Monitoring/ThresholdChecker.php`

---

### **Phase 4: Frontend Real-Time UI** (Estimated: 2-3 weeks)

**Tasks:**
1. Create AJAX endpoint for velocity data
2. Build JavaScript polling system
3. Create dashboard widget
4. Add admin bar indicator
5. Implement persistent admin notices

**Files to Create:**
- `src/Admin/DashboardWidget.php`
- `assets/js/real-time-monitor.js`
- `assets/css/dashboard-widget.css`

---

### **Phase 5: Baseline Learning** (Estimated: 2-3 weeks)

**Tasks:**
1. Implement "Learn from History" feature
2. Auto-calculate baselines from last 14-30 days
3. Build confidence indicators
4. Add manual override capability

**Files to Create:**
- `src/Statistics/BaselineLearner.php`
- UI for baseline visualization

---

## 🎯 **RECOMMENDATIONS**

### **Option A: Full Alignment (10-12 weeks)**
Implement all missing SPC components to match PROJECT-RETHINK.md vision.

**Pros:**
- ✅ True anomaly detection with statistical rigor
- ✅ Minimizes false positives (3σ rule)
- ✅ Adaptive to traffic patterns
- ✅ Real-time frontend monitoring

**Cons:**
- ❌ Significant development effort
- ❌ Complex database schema
- ❌ Requires historical data collection period
- ❌ Higher maintenance complexity

---

### **Option B: Hybrid Approach (4-6 weeks)**
Keep current multi-block system but add statistical enhancements.

**Implement:**
1. ✅ Historical data aggregation (per block, not per day-hour)
2. ✅ Calculate μ and σ for each block
3. ✅ Use statistical limits instead of static thresholds
4. ❌ Skip frontend AJAX polling (keep cron-based)
5. ⚠️ Add dashboard widget (view-only, no real-time)

**Pros:**
- ✅ Adds statistical rigor to existing system
- ✅ Moderate development effort
- ✅ Backward compatible
- ✅ Improves accuracy

**Cons:**
- ⚠️ Not fully aligned with PROJECT-RETHINK.md
- ⚠️ No real-time frontend monitoring

---

### **Option C: Document Divergence (1 week)**
Update PROJECT-RETHINK.md to reflect current implementation direction.

**Tasks:**
1. Create PROJECT-CURRENT.md documenting actual approach
2. Archive PROJECT-RETHINK.md as "alternative vision"
3. Update roadmap to reflect multi-block + RAD strategy

**Pros:**
- ✅ Aligns documentation with reality
- ✅ Minimal effort
- ✅ Clarifies project direction

**Cons:**
- ❌ Abandons SPC vision
- ❌ Loses potential for advanced anomaly detection

---

## 📊 **ALIGNMENT SCORECARD**

| Category | Alignment % | Status |
|----------|-------------|--------|
| **Core Algorithm** | 10% | 🔴 Major gaps |
| **Backend Architecture** | 40% | 🟡 Partial |
| **Frontend/AJAX** | 0% | 🔴 Not implemented |
| **Data Storage** | 30% | 🟡 Different approach |
| **Alert System** | 70% | 🟢 Good alignment |
| **WooCommerce Integration** | 90% | 🟢 Excellent |
| **Portability** | 60% | 🟢 Good foundation |
| **Overall** | **35%** | 🟡 **Significant divergence** |

---

## 💡 **MY RECOMMENDATION**

**Choose Option B: Hybrid Approach**

**Rationale:**
1. Current multi-block system (v1.7.x) is working and provides value
2. Adding statistical calculations enhances accuracy without complete rewrite
3. Can be implemented incrementally
4. Maintains backward compatibility
5. Provides 80% of SPC benefits with 40% of the effort

**Next Steps:**
1. Implement historical data aggregation for each time block
2. Calculate μ and σ from last 30 days per block
3. Replace static thresholds with calculated LCL values
4. Add "Learn Baseline" button to admin UI
5. Keep cron-based monitoring (skip frontend AJAX for now)

This gives you **statistical rigor** without the complexity of full SPC implementation, and you can always add frontend real-time monitoring later if needed.

---

Would you like me to create a detailed implementation plan for Option B, or would you prefer to discuss a different approach?


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

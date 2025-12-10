# KISS WooCommerce Order Monitor - Code Audit Issues
**Date:** December 10, 2025
**Status:** Partially Fixed (2 of 4 issues resolved)

---

## ✅ ISSUE #4: Inconsistent text domains - **FIXED**
**Status:** ✅ **RESOLVED** on 2025-12-10

**Original Issue:**
Text domain typo in plugin header: `kiss-woocomerce-order-monitor` (missing 'm' in "woocommerce")

**Fix Applied:**
- Updated plugin header in `kiss-woo-order-monitoring-alerts.php` line 13
- Changed from: `Text Domain: kiss-woocomerce-order-monitor`
- Changed to: `Text Domain: woo-order-monitor`
- Now matches the text domain used throughout the codebase

**Impact:** Translation files will now load correctly, WordPress.org plugin directory will recognize translations properly.

---

## ✅ ISSUE #2: URL and date validation not enforced - **FIXED**
**Status:** ✅ **RESOLVED** on 2025-12-10

**Original Issue:**
Validation rules defined `url` and `date` types in `SettingsDefaults::getValidationRules()`, but the `Settings::validateSetting()` method did not implement validation for these types. This meant `webhook_url` and `daily_alert_date` were never validated/sanitized, creating potential security vulnerabilities.

**Fix Applied:**
Added validation cases to `src/Core/Settings.php`:
1. Added `case 'url':` with `isValidUrl()` helper method
   - Uses WordPress `esc_url_raw()` for sanitization
   - Uses PHP `filter_var()` with `FILTER_VALIDATE_URL`
   - Allows empty values (optional field)
   - Rejects malicious URLs (e.g., `javascript:alert(1)`)

2. Added `case 'date':` with `isValidDateFormat()` helper method
   - Validates Y-m-d format (e.g., 2025-12-10)
   - Uses `checkdate()` to verify real dates
   - Rejects invalid dates (e.g., 2025-02-30)
   - Allows empty values (set dynamically)

**Testing:**
Created `test-validation-fixes.php` to verify:
- Valid URLs accepted ✅
- Invalid URLs rejected ✅
- Malicious URLs rejected ✅
- Valid dates accepted ✅
- Invalid date formats rejected ✅
- Invalid dates rejected ✅

**Impact:** Security vulnerability closed, data integrity ensured.

---

## 🟡 ISSUE #1: Cron scheduling in multiple places - **PARTIALLY ADDRESSED**
**Status:** 🟡 **TODO COMMENTS ADDED** - Will be fully resolved after PSR-4 Phase 7

**Original Issue:**
`wp_schedule_event()` for `woom_check_orders` is called in 6 different locations:
1. `src/Core/Installer.php` (line 176) - PSR-4 activation
2. `src/Monitoring/CronScheduler.php` (line 103) - PSR-4 runtime
3. `src/Admin/SettingsPage.php` (line 612) - PSR-4 settings update
4. `kiss-woo-order-monitoring-alerts.php` (line 204) - Legacy runtime
5. `kiss-woo-order-monitoring-alerts.php` (line 232) - Legacy activation
6. `kiss-woo-order-monitoring-alerts.php` (line 2284) - Legacy self-test recovery

**Current Status:**
- ✅ Added TODO comments to all 3 PSR-4 locations documenting consolidation plan
- ⏳ Legacy locations (#4-6) will be removed during PSR-4 Phase 7 cleanup
- ⏳ PSR-4 locations (#1-3) need consolidation to only use `CronScheduler::schedule()`

**Remaining Work:**
1. Complete PSR-4 Phase 7 (Testing & Cleanup) to remove legacy code
2. Refactor `Installer` and `SettingsPage` to call `CronScheduler::schedule()` instead of direct `wp_schedule_event()`
3. Make `CronScheduler` the single source of truth for cron management

**Impact:** Currently causes potential race conditions and duplicate cron jobs due to hybrid PSR-4/legacy architecture.

---

## 🟡 ISSUE #3: Duplicated activation, deactivation, AJAX, and cron logic - **PENDING PSR-4 COMPLETION**
**Status:** 🟡 **WILL BE RESOLVED** by PSR-4 Phase 7

**Original Issue:**
Duplicate implementations due to hybrid PSR-4/legacy architecture:

**A. Activation/Deactivation Hooks (registered twice):**
- Legacy: `kiss-woo-order-monitoring-alerts.php` lines 135-136
- PSR-4: `src/Core/Plugin.php` lines 187-188

**B. AJAX Handlers (registered twice):**
- Legacy: `kiss-woo-order-monitoring-alerts.php` lines 127-129
- PSR-4: `src/Admin/AjaxHandler.php`

**C. Cron Logic (see Issue #1 above)**

**Resolution Plan:**
This will be **automatically resolved** when PSR-4 Phase 7 (Testing & Cleanup) is completed:
- Phase 7 includes "Remove old code from main file"
- All legacy hooks, AJAX handlers, and cron logic will be deleted
- Only PSR-4 implementations will remain
- Main file will be reduced from ~2,734 lines to ~100 lines

**Impact:** Currently causes duplicate hook registrations and potential conflicts.

---

## 📊 SUMMARY

| Issue | Status | Fixed? | Resolution |
|-------|--------|--------|------------|
| **#1: Cron in multiple places** | 🟡 Partial | 40% | TODO comments added, needs PSR-4 Phase 7 + consolidation |
| **#2: URL/date validation** | ✅ Fixed | 100% | Validation implemented in Settings.php |
| **#3: Duplicated logic** | 🟡 Pending | 0% | Will be resolved by PSR-4 Phase 7 |
| **#4: Text domain typo** | ✅ Fixed | 100% | Plugin header corrected |

**Overall Progress:** 2 of 4 issues fully resolved (50%)

---

## 🎯 NEXT STEPS

1. ✅ **COMPLETED:** Fix Issues #2 and #4 (validation and text domain)
2. ⏳ **IN PROGRESS:** Complete PSR-4 Phases 5-7 (Notifications, CLI, Integration, Testing & Cleanup)
3. ⏳ **PENDING:** Consolidate cron scheduling to use only `CronScheduler` class
4. ⏳ **PENDING:** Verify all issues resolved after PSR-4 completion

**Estimated Time to Full Resolution:** 7-10 hours (PSR-4 completion) + 30 minutes (cron consolidation)

---

## 📝 PSR-4 COMPLETION IMPACT ANALYSIS

**PSR-4 Completion would fix: 1.6 out of 4 issues (40%)**

**Breakdown:**
- ✅ Issue #3 - Fully resolved (100%)
- 🟡 Issue #1 - Mostly resolved (60%)
- ✅ Issue #2 - Already fixed (100%)
- ✅ Issue #4 - Already fixed (100%)

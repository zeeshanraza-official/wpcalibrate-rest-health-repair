# WPCalibrate REST Health & Repair

[![WordPress](https://img.shields.io/badge/WordPress-6.4%20to%207.1.3-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%20to%2011.2.1-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-indigo.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-49%2F49%20Passing-brightgreen.svg)]()

> **Enterprise-grade diagnostic engine, monitoring suite, and safe automated repair framework for WordPress and WooCommerce REST API, Store API, loopback routing, and security firewall layers.**

---

## Table of Contents

- [About](#about)
- [Key Features](#key-features)
- [Diagnostic Capabilities](#diagnostic-capabilities)
  - [1. Core WordPress REST Infrastructure](#1-core-wordpress-rest-infrastructure)
  - [2. HTTP, SSL & Loopback Connectivity](#2-http-ssl--loopback-connectivity)
  - [3. Permalinks & Web Server Rewrites](#3-permalinks--web-server-rewrites)
  - [4. Security, Firewall & Authentication Probes](#4-security-firewall--authentication-probes)
  - [5. WooCommerce & Store API Health](#5-woocommerce--store-api-health)
- [Safe Automatic Repairs Framework](#safe-automatic-repairs-framework)
  - [Verification & Rollback Architecture](#verification--rollback-architecture)
  - [Available Automatic Repairs](#available-automatic-repairs)
- [REST API Endpoints Reference](#rest-api-endpoints-reference)
- [WordPress Dashboard Automatic Updates](#wordpress-dashboard-automatic-updates)
- [Documentation & How to Use](#documentation--how-to-use)
  - [Installation](#1-installation)
  - [Running Diagnostics](#2-running-diagnostics)
  - [Executing Automated Repairs](#3-executing-automated-repairs)
  - [Exporting Support Reports](#4-exporting-support-reports)
- [Changelog](#changelog)
- [License & Support](#license--support)

---

## About

The WordPress REST API and WooCommerce Store API power Gutenberg block editors, modern cart and checkout blocks, headless frontends, mobile apps, third-party integrations, and external shipping/inventory webhooks. However, REST infrastructure is frequently broken by:
- Corrupted WordPress rewrite rule tables in `wp_options`.
- Sites configured with Plain permalinks (`?p=123`), rendering `/wp-json/` endpoints unreachable.
- Misconfigured Nginx reverse proxies, missing `try_files` directives, or corrupt Apache `.htaccess` files.
- Loopback connection timeouts and cURL SSL certificate verification errors.
- Aggressive security plugins filtering `rest_authentication_errors` and blocking guest shoppers from cart APIs.
- Cloudflare Bot Management or Web Application Firewalls (WAF) serving HTML CAPTCHA challenge pages instead of JSON payloads.

When these failures occur, WordPress simply displays opaque messages such as *"Publishing failed. The response is not a valid JSON response"*, *"Store API unavailable"*, or *"Your store is not driven by WordPress"*.

**WPCalibrate REST Health & Repair** pinpoints the exact failure layer, provides transparent technical evidence, and executes verified automatic repairs with pre-repair backups and rollback guarantees.

---

## Key Features

- **20+ Specialized Diagnostic Tests:** Probes application, web server, loopback transport, firewall, and WooCommerce Store API layers.
- **Root Cause & Symptom Classification:** Distinguishes genuine WordPress application errors from server-level blocks, Cloudflare challenges, ModSecurity 403s, and basic authentication prompts.
- **Safe Automatic Repairs with Verification:** Only marks a repair as **Fixed** after re-running the affected diagnostics and verifying that endpoints respond cleanly.
- **Zero-Risk Rollback System:** All database modifications (rewrite rules, permalinks, configuration options) save timestamped snapshots with instant rollback.
- **Plain Permalinks Self-Healing:** Seamlessly diagnoses Plain permalink setups and safely activates standard `/%postname%/` routing with complete reversibility.
- **Sanitized Support Reports:** Generates redacted markdown and JSON reports masking passwords, database credentials, nonces, and server paths for safe sharing with hosting support or developers.
- **Non-Destructive Operations:** Never disables user authentication, never lowers firewall rules, and never deactivates third-party themes or plugins.
- **High-Performance Order Storage (HPOS) & Block Ready:** Fully tested on WooCommerce 11.2.1, HPOS custom tables, and Cart/Checkout Blocks.

---

## Diagnostic Capabilities

### 1. Core WordPress REST Infrastructure
- **`wp_rest_registered`:** Verifies the REST API server class (`WP_REST_Server`) is loaded and registered.
- **`wp_rest_url`:** Evaluates REST URL formatting and scheme consistency (`rest_url()`).
- **`wp_rest_index`:** Fetches the root `/wp-json/` index and confirms JSON validity, schema version, and namespaces.
- **`wp_rest_core_endpoint`:** Tests standard core read access (`/wp-json/wp/v2/types/post`).
- **`wp_rest_json_sanity`:** Detects PHP fatal notices, warnings, or HTML markup prepended or appended to REST responses.
- **`wp_rest_app_passwords`:** Verifies Application Passwords support and SSL prerequisites.

### 2. HTTP, SSL & Loopback Connectivity
- **`loopback_request`:** Tests server loopback connectivity, detecting host resolution failures, connection timeouts, and loopback blocks.
- **`ssl_certificate`:** Validates TLS handshake status, expiration dates, and cURL transport error codes.
- **`url_consistency`:** Checks consistency between `siteurl`, `home_url()`, and incoming `Host` headers.
- **`redirect_chain`:** Analyzes redirect chains (HTTP -> HTTPS, canonical trailing slashes) to identify circular redirect loops.
- **`http_status_handling`:** Evaluates how external HTTP transport handles unexpected 4xx and 5xx status codes.

### 3. Permalinks & Web Server Rewrites
- **`permalink_structure`:** Evaluates current permalink structure and flags Plain format (`?p=123`).
- **`rewrite_rules_rest`:** Validates whether `^wp-json/` rewrite rules exist in the active WordPress routing table.
- **`rest_route_fallback`:** Confirms whether query-parameter fallback (`?rest_route=/`) functions when direct paths fail.
- **`webserver_rewrite`:** Inspects web server signature (Apache, Nginx, LiteSpeed, IIS) and checks `.htaccess` or `try_files` configuration.

### 4. Security, Firewall & Authentication Probes
- **`rest_auth_errors`:** Detects restrictive `rest_authentication_errors` filters blocking unauthenticated public REST calls.
- **`rest_access_restrictions`:** Identifies security plugins requiring login for all REST routes.
- **`security_challenge`:** Classifies Cloudflare Managed Challenges, Wordfence blocks, ModSecurity rules, and Sucuri WAF intercepts.
- **`basic_auth_protection`:** Identifies HTTP 401 Basic Auth headers blocking headless probes or webhook receivers.

### 5. WooCommerce & Store API Health
- **`wc_plugin_active`:** Validates WooCommerce active status and HPOS compatibility mode.
- **`wc_rest_namespaces`:** Verifies registration of WooCommerce namespaces (`wc/v3`, `wc/v4`, `wc/store/v1`).
- **`wc_store_api_public`:** Tests guest shopper connectivity to public Store API routes (`/wc/store/v1/products`).
- **`wc_store_api_blocks`:** Validates Cart & Checkout Blocks readiness and nonce exchange.
- **`wc_not_driven_diagnosis`:** Performs root-cause analysis on the common *"Your store is not driven by WordPress"* error.

---

## Safe Automatic Repairs Framework

### Verification & Rollback Architecture

Every repair in WPCalibrate adheres to strict safety boundaries:

```
[Diagnostic Identifies Failure]
       │
       ▼ (User Confirms Action)
[1. Pre-Repair State Snapshot & Database Backup]
       │
[2. Execute Repair Action (Controlled & Minimal)]
       │
[3. Diagnostic Cache Invalidation]
       │
[4. Mandatory Re-Testing & Verification of Affected Diagnostics]
       │
   ├──▶ All Tests Pass ───> [Mark Fixed & Log Audit History]
   │
   └──▶ Tests Fail ───────> [Report "Not Resolved" & Offer 1-Click Rollback]
```

### Available Automatic Repairs

| Repair ID | Title | Safety Risk | Description & Rollback |
| :--- | :--- | :--- | :--- |
| `flush_rewrite_rules` | **Flush WordPress Rewrite Rules** | **Low** | Regenerates the WordPress rewrite rules table in `wp_options`. Automatically upgrades Plain permalinks to `/%postname%/` if needed. Backs up prior rules with instant rollback. |
| `repair_htaccess_rules` | **Regenerate WordPress .htaccess Directives** | **Moderate** | Safely updates `.htaccess` with standard `# BEGIN WordPress` rules on Apache/LiteSpeed servers while preserving unrelated directives. Creates timestamped backup. Disabled automatically on Nginx. |
| `clear_diagnostic_cache` | **Clear Diagnostic Results Cache** | **Low** | Clears plugin transient caches and forces fresh real-time scans across all test categories. |
| `repair_scheduled_tasks` | **Repair Scheduled Tasks** | **Low** | Re-schedules orphaned or missing WP-Cron maintenance events. |

---

## REST API Endpoints Reference

All endpoints are registered under namespace `wpcalibrate-rest-health-repair/v1` and require `manage_options` capability plus WordPress REST nonces (`X-WP-Nonce`):

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/diagnostics` | Returns all diagnostic results, counts, and system status summary. |
| `POST` | `/diagnostics/run` | Executes a single diagnostic test by ID. |
| `POST` | `/diagnostics/run-all` | Clears cache and executes the complete diagnostic test suite. |
| `POST` | `/diagnostics/rerun-failed`| Re-evaluates only currently failing or warning diagnostics. |
| `POST` | `/repairs/execute` | Executes an automated repair, re-runs affected tests, and returns outcome. |
| `POST` | `/repairs/rollback` | Restores previous configuration state from rollback backup. |
| `GET` | `/reports/export` | Downloads sanitized support diagnostic report (JSON or Markdown format). |

---

## WordPress Dashboard Automatic Updates

This repository is built for native WordPress dashboard updates:

1. **Semantic GitHub Releases:** Tagged releases (`v1.0.1`, `v1.0.2`, etc.) contain pre-built installable ZIP assets.
2. **Dashboard Integration:** When paired with [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) or GitHub updater tools, WordPress queries this public GitHub repository, detects updates, and enables 1-click updates directly from **Dashboard > Plugins**.

---

## Documentation & How to Use

### 1. Installation
1. Download `wpcalibrate-rest-health-repair-1.0.1.zip` from the latest [GitHub Release](https://github.com/zeeshanraza-official/wpcalibrate-rest-health-repair/releases).
2. In your WordPress admin, go to **Plugins > Add New > Upload Plugin**.
3. Choose the ZIP file, click **Install Now**, and click **Activate**.

### 2. Running Diagnostics
1. Navigate to **WPCalibrate > REST Health & Repair** in the WordPress admin menu.
2. Click **Run Full Diagnostic** to trigger an end-to-end evaluation.
3. Review the summary cards:
   - **Healthy:** Green checkmark indicates working infrastructure.
   - **Warning:** Amber notice indicates non-critical or suboptimal settings.
   - **Failed:** Red alert indicates broken REST connectivity requiring intervention.
4. Click **View Technical Evidence** on any card to inspect response headers, status codes, and latency measurements.

### 3. Executing Automated Repairs
1. On any diagnostic card showing a failure, click **Auto Repair** if an automatic remedy is available.
2. Alternatively, switch to the **Repairs** tab to inspect all available repairs and their planned changes.
3. Click **Execute Repair**. The plugin will execute the fix, invalidate caches, and re-test affected diagnostics.
4. If you need to undo changes, click **Revert / Rollback** to restore previous settings.

### 4. Exporting Support Reports
1. Switch to the **Diagnostic Report** tab.
2. Click **Download Markdown (.md)** or **Download JSON (.json)**.
3. Share the generated report with your hosting provider, agency, or plugin developer. All sensitive data (passwords, tokens, nonces, paths) are automatically redacted.

---

## Changelog

### [1.0.1] - 2026-10-12
- **Fixed:** Constrained WordPress admin sidebar menu icon to standard 20x20px using an optimized SVG data URI and global `admin_head` CSS.
- **Fixed:** Dynamic admin icon color scheme compatibility (`#a7aaad` default, hover highlight, and pure white active state).
- **Fixed:** `FlushRewriteRulesRepair` is now universally applicable across all permalink environments, automatically upgrading Plain permalinks to `/%postname%/` on execution with full rollback support.
- **Fixed:** Enhanced UI for environment-restricted repairs (e.g. `.htaccess` repair on Nginx servers) with disabled buttons and clear explanatory notices.
- **Added:** Full automated unit test suite (49 passing tests) and clean packaging pipeline.

### [1.0.0] - 2026-10-12
- **Initial Release:** 20+ specialized diagnostic evaluators for WordPress core and WooCommerce REST APIs.
- Safe repair framework with pre-repair backups, post-repair re-verification, and rollback support.
- Sanitized support diagnostic report exporter (Markdown and JSON formats).
- Provider-independent licensing interface architecture.
- Declared full compatibility with WordPress 7.1.3, WooCommerce 11.2.1, HPOS, and Cart/Checkout Blocks.

---

## License & Support

- **License:** GNU General Public License v2.0 or later ([GPL-2.0-or-later](LICENSE))
- **Author:** WPCalibrate
- **Website:** [https://wpcalibrate.com](https://wpcalibrate.com)
- **Support:** [support@wpcalibrate.com](mailto:support@wpcalibrate.com)

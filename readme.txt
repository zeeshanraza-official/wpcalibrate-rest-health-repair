=== WPCalibrate REST Health & Repair ===
Contributors: wpcalibrate
Tags: rest api, woocommerce, health check, repair, permalinks, store api
Requires at least: 6.4
Tested up to: 7.1.3
Requires PHP: 8.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Diagnose and safely repair WordPress and WooCommerce REST API failures, Store API availability, rewrite routing, loopback connectivity, and firewall interference.

== Description ==

WPCalibrate REST Health & Repair is a dedicated diagnostic and safe repair plugin designed to identify why a WordPress or WooCommerce site reports REST-related failures such as:

* REST API disabled or unavailable.
* Loopback REST requests failed.
* "Your store is not driven by WordPress" error.
* Store API unavailable in WooCommerce Cart/Checkout Blocks.
* REST endpoints returning unexpected 401, 403, 404, 405, 429, or 500 responses.
* Web Application Firewall (WAF) or Cloudflare CAPTCHA/challenge pages blocking API probes.

= Product Principles =
* **Diagnose Before Repairing:** Report verifiable evidence, not guesses.
* **Distinguish Failure Layers:** Distinguishes WordPress application-level failures from server, DNS, SSL, CDN, or firewall issues.
* **Never Weaken Site Security:** The plugin will never disable authentication, lower firewall barriers, or disable SSL verification.
* **Non-Destructive Operations:** Never automatically deactivates plugins or switches themes.
* **Safe Automatic Repairs:** Only offers automatic repairs for controlled, reversible WordPress-level changes (such as flushing rewrite rules with backup rollback).
* **Mandatory Verification:** A repair is labeled "Fixed" ONLY after the affected diagnostic test re-runs and passes. If verification fails, it reports "Not Resolved" and offers rollback where supported.
* **No Arbitrary Percentage Scores:** Results are categorized into meaningful states: Healthy, Warning, Failed, Not Applicable, Skipped, and Unable to Test.

= WooCommerce Integration =
WooCommerce is completely optional. If WooCommerce is inactive, all core WordPress REST diagnostics continue working normally. When WooCommerce is active, dedicated tests evaluate HPOS status, REST namespaces, public Store API connectivity, Block dependencies, and the "Your store is not driven by WordPress" error.

== Installation ==

1. Upload the `wpcalibrate-rest-health-repair` folder to your `/wp-content/plugins/` directory, or install the ZIP file via Dashboard > Plugins > Add New > Upload Plugin.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to WPCalibrate > REST Health & Repair in the WordPress admin menu to run your initial diagnostic scan.

== Diagnostic Categories ==

1. **Core WordPress REST API:** Tests server classes, `rest_url()` generation, REST index availability, public core endpoints (`/wp/v2/types`), JSON output sanity (BOM and HTML detection), and Application Passwords readiness.
2. **HTTP & Network Transport:** Tests server loopback self-requests, strict SSL certificate verification, Site URL vs Home URL protocol/host consistency, HTTP redirect chains, and status code handling.
3. **Permalinks & URL Rewriting:** Inspects permalink structure, verifies core `^wp-json/` rewrite rules in the active routing table, checks `?rest_route=/` fallback behavior, and analyzes `.htaccess` (Apache/LiteSpeed) or `try_files` (Nginx).
4. **Security & Authentication Interference:** Safely inspects registered `rest_authentication_errors` filters and callback origins, scans for restrictive plugins, detects CDN/WAF challenge pages (Cloudflare, Wordfence, ModSecurity, Sucuri), and identifies HTTP Basic Auth (.htpasswd).
5. **WooCommerce REST & Store API:** Inspects WooCommerce active status, HPOS mode, registered `wc/v3`, `wc/v4`, `wc/store/v1` namespaces, public Store API products endpoint, Cart/Checkout Blocks dependencies, and provides root cause analysis for "Your store is not driven by WordPress".

== Automatic vs. Manual Repairs ==

* **Automatic Repairs:** Low or moderate risk actions controlled by WordPress APIs that include pre-repair backups and post-repair re-verification. Examples: Flushing WordPress rewrite rules, regenerating `.htaccess` WordPress blocks on writable Apache servers, clearing diagnostic caches, and repairing scheduled cron events.
* **Manual Only Guidance:** Issues residing in the web server, DNS, SSL certificate, hosting provider, or CDN cannot be safely edited from WordPress. The plugin provides step-by-step instructions (such as exact Cloudflare WAF skip rules or Nginx `try_files` configuration) rather than attempting unsafe server modifications.

== Privacy & Data Retention ==

* **Local Diagnostics Only:** This plugin does not transmit telemetry or diagnostic data to WPCalibrate or third-party servers.
* **Strict Secret Redaction:** All diagnostic reports and logs mask passwords, authorization headers, cookies, API tokens, license keys, and normalize absolute server filesystem paths.
* **Data Retention:** By default, plugin settings and repair audit history are preserved upon uninstall. Administrators can toggle "Delete all plugin data on uninstall" in Settings to execute a complete data purge upon deletion.

== Frequently Asked Questions ==

= Why does my site report "Your store is not driven by WordPress"? =
This message does not mean your site is not WordPress. It indicates that an external service or mobile app probed your REST root (`/wp-json/`) and encountered a block—such as an HTML 404 error, a Cloudflare Bot Challenge page, an HTTP Basic Auth prompt, or an SSL failure—preventing it from verifying the standard WordPress JSON index.

= Why does /wp-json/ return a 404 error while pages load fine? =
When permalinks are changed or WordPress is updated, the internal rewrite rule for `^wp-json/` can become de-synchronized, or the web server configuration may not forward `/wp-json/` requests to `index.php`. Use the automatic "Flush Rewrite Rules" repair action on the Repairs tab to regenerate your routing table.

= What causes REST 401 or 403 responses on public endpoints? =
A security plugin, membership plugin, or custom `rest_authentication_errors` filter may be configured to require user login for all REST requests. While this secures private endpoints, it blocks guest customers from using the WooCommerce Store API (Cart & Checkout). Check the Security tab in this plugin to inspect registered authentication callbacks.

= Does this plugin create WooCommerce API keys? =
No. The plugin tests public endpoints and infrastructure readiness without creating API credentials or requiring external secrets.

= Can this plugin edit Nginx configuration? =
No. Nginx configuration files live outside WordPress and cannot be edited via PHP. The plugin provides exact `try_files` directive instructions for your Nginx server block.

== Changelog ==

= 1.0.1 =
* Fixed: Constrained WordPress admin menu sidebar icon to standard 20x20px with SVG data URI and global admin CSS.
* Fixed: Menu icon color automatically matches WordPress admin color schemes (#a7aaad / currentColor) with hover and active states.
* Fixed: FlushRewriteRulesRepair is now applicable across all permalink environments, automatically upgrading Plain permalinks to /%postname%/ on execution with complete rollback support.
* Fixed: Enhanced UI for environment-restricted repairs (e.g. .htaccess on Nginx) with disabled buttons and clear explanatory notices.

= 1.0.0 =
* Initial release.
* Added 20+ specialized diagnostic evaluators for WordPress core and WooCommerce REST APIs.
* Added safe repair framework with pre-repair backups, post-repair re-verification, and rollback support.
* Added sanitized support diagnostic report exporter (Text and JSON formats).
* Added provider-independent licensing interface architecture.
* Declared full compatibility with WordPress 7.1.3, WooCommerce 11.2.1, HPOS, and Cart/Checkout Blocks.

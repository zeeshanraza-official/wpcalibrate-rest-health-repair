<?php
/**
 * Dedicated troubleshooting path for "Your store is not driven by WordPress" error.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics\WooCommerce;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticResult;
use WPCalibrate\RESTHealthRepair\Support\Environment;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * StoreNotDrivenDiagnostic class.
 */
final class StoreNotDrivenDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'wc_not_driven_diagnosis';
	}

	public function get_group(): string {
		return 'woocommerce';
	}

	public function get_title(): string {
		return __( '"Your Store Is Not Driven by WordPress" Root Cause Analysis', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Dedicated investigation engine for integrations or mobile apps reporting that your store is not powered by WordPress.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return true; // Applicable for any WP/WC site experiencing external verification issues.
	}

	public function get_repair_id(): ?string {
		return 'flush_rewrite_rules';
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_WARNING;
	}

	public function run(): DiagnosticResult {
		$start_time = microtime( true );

		$rest_url = function_exists( 'rest_url' ) ? rest_url() : home_url( '/wp-json/' );
		$settings = (array) get_option( 'wpcalibrate_rhr_settings', array() );
		$timeout  = max( 3, min( 30, (int) ( $settings['http_timeout'] ?? 10 ) ) );

		$response = wp_remote_get(
			$rest_url,
			array(
				'timeout'     => $timeout,
				'redirection' => 2,
				'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) WPCalibrate-ExternalProbe/1.0',
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);
		$duration = ( microtime( true ) - $start_time ) * 1000;

		$causes_found = array();
		$evidence     = array(
			'tested_url'  => $rest_url,
			'home_url'    => home_url(),
			'site_url'    => site_url(),
			'duration_ms' => round( $duration, 2 ),
		);

		// Check 1: Transport error.
		if ( is_wp_error( $response ) ) {
			$causes_found[] = sprintf(
				/* translators: %s: Error message */
				__( 'REST root is unreachable externally or locally: %s', 'wpcalibrate-rest-health-repair' ),
				$response->get_error_message()
			);
			$evidence['transport_error'] = $response->get_error_message();
		} else {
			$status_code     = wp_remote_retrieve_response_code( $response );
			$body            = (string) wp_remote_retrieve_body( $response );
			$body_lower      = strtolower( $body );
			$content_type    = (string) wp_remote_retrieve_header( $response, 'content-type' );
			$auth_header     = (string) wp_remote_retrieve_header( $response, 'www-authenticate' );

			$evidence['status_code']  = $status_code;
			$evidence['content_type'] = $content_type;

			// Check 2: 404 rewrite failure.
			if ( 404 === $status_code ) {
				$causes_found[] = __( '/wp-json/ returned 404. External services cannot find the WordPress REST root.', 'wpcalibrate-rest-health-repair' );
			}

			// Check 3: 401/403 security block or basic auth.
			if ( 401 === $status_code || 403 === $status_code ) {
				if ( ! empty( $auth_header ) ) {
					$causes_found[] = __( 'HTTP Basic Auth password protection is blocking external discovery requests.', 'wpcalibrate-rest-health-repair' );
				} else {
					$causes_found[] = __( 'Security plugin or WAF blocked the unauthenticated verification request.', 'wpcalibrate-rest-health-repair' );
				}
			}

			// Check 4: HTML instead of JSON (WAF or maintenance page).
			if ( str_contains( $body_lower, '<!doctype html>' ) || str_contains( $body_lower, '<html' ) ) {
				if ( str_contains( $body_lower, 'cloudflare' ) || str_contains( $body_lower, 'turnstile' ) || str_contains( $body_lower, 'just a moment...' ) ) {
					$causes_found[] = __( 'Cloudflare Bot Challenge / Turnstile page intercepted the discovery probe.', 'wpcalibrate-rest-health-repair' );
				} else {
					$causes_found[] = __( 'REST URL returned an HTML page instead of the expected WordPress REST index JSON.', 'wpcalibrate-rest-health-repair' );
				}
			} else {
				$json = json_decode( $body, true );
				if ( ! is_array( $json ) || ! isset( $json['namespaces'] ) ) {
					$causes_found[] = __( 'REST response was not valid JSON or lacked the standard WordPress "namespaces" array.', 'wpcalibrate-rest-health-repair' );
				}
			}
		}

		// Check 5: Site URL / Home URL mismatch.
		if ( home_url() !== site_url() ) {
			$home_p = wp_parse_url( home_url() );
			$site_p = wp_parse_url( site_url() );
			if ( ( $home_p['host'] ?? '' ) !== ( $site_p['host'] ?? '' ) || ( $home_p['scheme'] ?? '' ) !== ( $site_p['scheme'] ?? '' ) ) {
				$causes_found[] = __( 'Site URL and Home URL scheme or hostname mismatch.', 'wpcalibrate-rest-health-repair' );
			}
		}

		$evidence['causes_count'] = count( $causes_found );

		if ( ! empty( $causes_found ) ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				__( 'Identified specific barriers that cause external services to falsely report that your store is not driven by WordPress.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				$causes_found,
				__( 'External services (such as mobile apps, Jetpack, Facebook for WooCommerce, or API scanners) fail to verify WordPress because they cannot read the REST index.', 'wpcalibrate-rest-health-repair' ),
				__( 'Note: This message does NOT mean your store is not WordPress. It means external automated probes cannot reach /wp-json/ cleanly.', 'wpcalibrate-rest-health-repair' ),
				'flush_rewrite_rules',
				__( "To resolve this:\n1. If /wp-json/ is 404, use the Flush Rewrite Rules button below.\n2. If Cloudflare is active, add a WAF Rule to Skip security checks for /wp-json/*.\n3. If staging password protection is on, whitelist /wp-json/ in .htaccess.\n4. Verify Home URL and Site URL match in Settings > General.", 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				$duration
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			__( 'REST root responds cleanly with valid WordPress index JSON. External services should readily identify this site as WordPress-driven.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'Site is verified as WordPress-driven.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			$duration
		);
	}
}

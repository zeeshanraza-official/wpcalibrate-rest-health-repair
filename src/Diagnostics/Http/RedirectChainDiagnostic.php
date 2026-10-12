<?php
/**
 * Diagnostic for checking REST URL redirect behavior and unwanted redirect chains.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics\Http;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticResult;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RedirectChainDiagnostic class.
 */
final class RedirectChainDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'redirect_chain';
	}

	public function get_group(): string {
		return 'http';
	}

	public function get_title(): string {
		return __( 'REST Redirect Chains & Cross-Domain Redirects', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Checks if REST API endpoints trigger HTTP redirects (301/302/307/308) that disrupt POST/PUT requests or drop authorization headers.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return true;
	}

	public function get_repair_id(): ?string {
		return null;
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_WARNING;
	}

	public function run(): DiagnosticResult {
		$start_time = microtime( true );
		$target_url = function_exists( 'rest_url' ) ? rest_url() : home_url( '/wp-json/' );

		$settings = (array) get_option( 'wpcalibrate_rhr_settings', array() );
		$timeout  = max( 3, min( 30, (int) ( $settings['http_timeout'] ?? 10 ) ) );

		// Set redirection to 0 to inspect initial status directly.
		$response = wp_remote_get(
			$target_url,
			array(
				'timeout'     => $timeout,
				'redirection' => 0,
				'user-agent'  => 'WPCalibrate-Redirect-Diagnostic/1.0',
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);
		$duration = ( microtime( true ) - $start_time ) * 1000;

		if ( is_wp_error( $response ) ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_UNABLE_TO_TEST,
				__( 'Could not inspect redirects due to request failure.', 'wpcalibrate-rest-health-repair' ),
				array( 'error' => $response->get_error_message() ),
				array( __( 'Connection failure during redirect evaluation.', 'wpcalibrate-rest-health-repair' ) ),
				'',
				__( 'Verify loopback connectivity first.', 'wpcalibrate-rest-health-repair' ),
				null,
				null,
				DiagnosticResult::SEVERITY_INFO,
				time(),
				$duration
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$location    = wp_remote_retrieve_header( $response, 'location' );

		$evidence = array(
			'target_url'  => $target_url,
			'status_code' => $status_code,
			'location'    => Sanitizer::redact_secrets( (string) $location ),
			'duration_ms' => round( $duration, 2 ),
		);

		// If 3xx redirect detected:
		if ( in_array( $status_code, array( 301, 302, 303, 307, 308 ), true ) ) {
			$target_parsed = wp_parse_url( $target_url );
			$loc_parsed    = wp_parse_url( (string) $location );

			$is_cross_domain = ( $target_parsed['host'] ?? '' ) !== ( $loc_parsed['host'] ?? '' );
			$is_to_http      = 'http' === ( $loc_parsed['scheme'] ?? '' );
			$is_to_login     = str_contains( (string) $location, 'wp-login.php' );

			$evidence['is_cross_domain'] = $is_cross_domain;
			$evidence['is_to_login']     = $is_to_login;

			if ( $is_to_login ) {
				return new DiagnosticResult(
					$this->get_id(),
					DiagnosticResult::STATUS_FAILED,
					__( 'REST request was redirected to the WordPress login page (wp-login.php).', 'wpcalibrate-rest-health-repair' ),
					$evidence,
					array(
						__( 'A membership plugin, maintenance plugin, or security redirect rule forces all visitors to log in.', 'wpcalibrate-rest-health-repair' ),
					),
					__( 'REST consumers receive HTML login forms instead of API responses.', 'wpcalibrate-rest-health-repair' ),
					__( 'Configure your membership or security plugin to exclude /wp-json/ from login redirects.', 'wpcalibrate-rest-health-repair' ),
					null,
					__( 'In membership/security settings, add /wp-json/ or /wp-json/* to the URL exclusion or whitelist rules.', 'wpcalibrate-rest-health-repair' ),
					DiagnosticResult::SEVERITY_CRITICAL,
					time(),
					$duration
				);
			}

			if ( $is_cross_domain ) {
				return new DiagnosticResult(
					$this->get_id(),
					DiagnosticResult::STATUS_FAILED,
					sprintf(
						/* translators: %s: Destination host */
						__( 'Cross-domain redirect detected on REST root pointing to "%s".', 'wpcalibrate-rest-health-repair' ),
						$loc_parsed['host'] ?? 'another domain'
					),
					$evidence,
					array(
						__( 'Domain canonicalization redirect or external proxy misconfiguration.', 'wpcalibrate-rest-health-repair' ),
					),
					__( 'HTTP client libraries strip Authorization headers on cross-domain redirects for security reasons.', 'wpcalibrate-rest-health-repair' ),
					__( 'Update Home and Site URL settings to match the canonical domain.', 'wpcalibrate-rest-health-repair' ),
					null,
					null,
					DiagnosticResult::SEVERITY_CRITICAL,
					time(),
					$duration
				);
			}

			// Trailing slash redirect or HTTP to HTTPS redirect.
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_WARNING,
				sprintf(
					/* translators: 1: Status code, 2: Redirect location */
					__( 'REST root returned HTTP %1$d redirect to "%2$s".', 'wpcalibrate-rest-health-repair' ),
					$status_code,
					$location
				),
				$evidence,
				array(
					__( 'Trailing slash enforcement redirect or HTTP-to-HTTPS redirect rule.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'Can cause POST/PUT/DELETE requests to be converted to GET or drop custom headers.', 'wpcalibrate-rest-health-repair' ),
				__( 'Ensure REST clients and site settings use the exact destination URL including trailing slashes and HTTPS.', 'wpcalibrate-rest-health-repair' ),
				null,
				null,
				DiagnosticResult::SEVERITY_WARNING,
				time(),
				$duration
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			__( 'No unexpected HTTP redirects detected on the REST root URL.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'Requests reach endpoint directly.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			$duration
		);
	}
}

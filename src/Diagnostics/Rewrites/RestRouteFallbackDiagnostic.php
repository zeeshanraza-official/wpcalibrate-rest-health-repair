<?php
/**
 * Diagnostic for testing rest_route query parameter fallback behavior.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics\Rewrites;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticResult;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RestRouteFallbackDiagnostic class.
 */
final class RestRouteFallbackDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'rest_route_fallback';
	}

	public function get_group(): string {
		return 'rewrites';
	}

	public function get_title(): string {
		return __( 'REST Fallback Query Parameter (?rest_route=/)', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Queries ?rest_route=/ to distinguish webserver/rewrite routing failures from internal WordPress REST registration failures.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return true;
	}

	public function get_repair_id(): ?string {
		return 'flush_rewrite_rules';
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_WARNING;
	}

	public function run(): DiagnosticResult {
		$start_time   = microtime( true );
		$fallback_url = home_url( '/?rest_route=/' );

		$settings = (array) get_option( 'wpcalibrate_rhr_settings', array() );
		$timeout  = max( 3, min( 30, (int) ( $settings['http_timeout'] ?? 10 ) ) );

		$response = wp_remote_get(
			$fallback_url,
			array(
				'timeout'    => $timeout,
				'user-agent' => 'WPCalibrate-Fallback-Diagnostic/1.0',
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);
		$duration = ( microtime( true ) - $start_time ) * 1000;

		if ( is_wp_error( $response ) ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_UNABLE_TO_TEST,
				__( 'Could not test rest_route fallback due to request failure.', 'wpcalibrate-rest-health-repair' ),
				array( 'error' => $response->get_error_message() ),
				array(),
				'',
				'',
				null,
				null,
				DiagnosticResult::SEVERITY_INFO,
				time(),
				$duration
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = (string) wp_remote_retrieve_body( $response );
		$json_data   = json_decode( $body, true );

		$is_valid_json = is_array( $json_data ) && isset( $json_data['namespaces'] );

		$evidence = array(
			'fallback_url'  => $fallback_url,
			'status_code'   => $status_code,
			'is_valid_json' => $is_valid_json,
			'body_snippet'  => Sanitizer::sanitize_response_body_snippet( $body, 250 ),
			'duration_ms'   => round( $duration, 2 ),
		);

		if ( 200 === $status_code && $is_valid_json ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_HEALTHY,
				__( 'The REST API responds successfully via ?rest_route=/ query parameter fallback. Internal REST registration is functional.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(),
				__( 'Fallback REST routing is operational.', 'wpcalibrate-rest-health-repair' ),
				__( 'If /wp-json/ is failing while this passes, the problem is strictly URL rewriting/webserver routing.', 'wpcalibrate-rest-health-repair' ),
				'flush_rewrite_rules',
				null,
				DiagnosticResult::SEVERITY_INFO,
				time(),
				$duration
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_FAILED,
			sprintf(
				/* translators: %d: Status code */
				__( 'Fallback endpoint ?rest_route=/ returned HTTP %d or invalid JSON.', 'wpcalibrate-rest-health-repair' ),
				$status_code
			),
			$evidence,
			array(
				__( 'WordPress REST API is disabled or blocked before URL routing occurs.', 'wpcalibrate-rest-health-repair' ),
				__( 'Global authentication filter or security plugin blocking all REST access.', 'wpcalibrate-rest-health-repair' ),
			),
			__( 'REST API is failing internally inside WordPress rather than just at the webserver rewrite layer.', 'wpcalibrate-rest-health-repair' ),
			__( 'Investigate active plugins and rest_authentication_errors filters.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_CRITICAL,
			time(),
			$duration
		);
	}
}

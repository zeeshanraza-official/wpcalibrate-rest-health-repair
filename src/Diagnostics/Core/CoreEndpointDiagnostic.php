<?php
/**
 * Diagnostic for testing core public REST endpoint response behavior.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics\Core;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticResult;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CoreEndpointDiagnostic class.
 */
final class CoreEndpointDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'wp_rest_core_endpoint';
	}

	public function get_group(): string {
		return 'core';
	}

	public function get_title(): string {
		return __( 'Core Public REST Endpoint (/wp/v2/types)', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Queries the public core endpoint /wp-json/wp/v2/types to ensure deep nested REST routing and schema responses function properly.', 'wpcalibrate-rest-health-repair' );
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
		$start_time = microtime( true );
		$target_url = function_exists( 'rest_url' ) ? rest_url( 'wp/v2/types' ) : home_url( '/wp-json/wp/v2/types' );

		$settings = (array) get_option( 'wpcalibrate_rhr_settings', array() );
		$timeout  = max( 3, min( 30, (int) ( $settings['http_timeout'] ?? 10 ) ) );

		$response = wp_remote_get(
			$target_url,
			array(
				'timeout'    => $timeout,
				'user-agent' => 'WPCalibrate-REST-Diagnostic/' . ( defined( 'WPCALIBRATE_RHR_VERSION' ) ? WPCALIBRATE_RHR_VERSION : '1.0.0' ),
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);
		$duration = ( microtime( true ) - $start_time ) * 1000;

		if ( is_wp_error( $response ) ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to connect to core endpoint: %s', 'wpcalibrate-rest-health-repair' ),
					$response->get_error_message()
				),
				array(
					'url'        => $target_url,
					'error_code' => $response->get_error_code(),
				),
				array( __( 'Loopback network failure or request timeout.', 'wpcalibrate-rest-health-repair' ) ),
				__( 'Core REST endpoints cannot be reached.', 'wpcalibrate-rest-health-repair' ),
				__( 'Check loopback request diagnostic results.', 'wpcalibrate-rest-health-repair' ),
				null,
				null,
				DiagnosticResult::SEVERITY_WARNING,
				time(),
				$duration
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$json_data   = json_decode( $body, true );

		$evidence = array(
			'url'          => $target_url,
			'status_code'  => $status_code,
			'body_snippet' => Sanitizer::sanitize_response_body_snippet( $body, 250 ),
			'duration_ms'  => round( $duration, 2 ),
		);

		if ( 200 !== $status_code || ! is_array( $json_data ) || ! isset( $json_data['post'] ) ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				sprintf(
					/* translators: %d: Status code */
					__( 'Core endpoint /wp/v2/types returned HTTP %d or unexpected content.', 'wpcalibrate-rest-health-repair' ),
					$status_code
				),
				$evidence,
				array(
					__( 'Deep REST route rewrite failure (root works, but subpaths fail).', 'wpcalibrate-rest-health-repair' ),
					__( 'Authentication restriction blocking reading public post types.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'Gutenberg editor, widgets, and block themes will experience loading failures.', 'wpcalibrate-rest-health-repair' ),
				__( 'Flush rewrite rules and check for restrictive REST filters.', 'wpcalibrate-rest-health-repair' ),
				'flush_rewrite_rules',
				__( '1. Try the "Flush Rewrite Rules" repair. 2. Verify web server URL rewrite config supports sub-paths.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_WARNING,
				time(),
				$duration
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			__( 'Core endpoint /wp/v2/types responded with HTTP 200 and expected schema.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'Core endpoints are functional.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			$duration
		);
	}
}

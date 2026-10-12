<?php
/**
 * Diagnostic for checking REST HTTP error handling and status code response behavior.
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
 * HttpStatusHandlingDiagnostic class.
 */
final class HttpStatusHandlingDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'http_status_handling';
	}

	public function get_group(): string {
		return 'http';
	}

	public function get_title(): string {
		return __( 'REST Error Status Code & JSON Handling', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Tests how the server handles an invalid REST route to ensure WordPress REST error handlers return standard JSON errors rather than raw webserver HTML error pages.', 'wpcalibrate-rest-health-repair' );
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
		$target_url = function_exists( 'rest_url' )
			? rest_url( 'wp/v2/wpcalibrate-diagnostic-invalid-probe-' . wp_generate_password( 8, false ) )
			: home_url( '/wp-json/wp/v2/invalid-probe' );

		$settings = (array) get_option( 'wpcalibrate_rhr_settings', array() );
		$timeout  = max( 3, min( 30, (int) ( $settings['http_timeout'] ?? 10 ) ) );

		$response = wp_remote_get(
			$target_url,
			array(
				'timeout'    => $timeout,
				'user-agent' => 'WPCalibrate-Status-Diagnostic/1.0',
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);
		$duration = ( microtime( true ) - $start_time ) * 1000;

		if ( is_wp_error( $response ) ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_UNABLE_TO_TEST,
				__( 'Could not probe REST error handling due to connection failure.', 'wpcalibrate-rest-health-repair' ),
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

		$status_code  = wp_remote_retrieve_response_code( $response );
		$body         = (string) wp_remote_retrieve_body( $response );
		$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );

		$json_data  = json_decode( $body, true );
		$is_wp_json = is_array( $json_data ) && isset( $json_data['code'] ) && 'rest_no_route' === $json_data['code'];

		$evidence = array(
			'probe_url'    => $target_url,
			'status_code'  => $status_code,
			'content_type' => $content_type,
			'is_wp_json'   => $is_wp_json,
			'body_snippet' => Sanitizer::sanitize_response_body_snippet( $body, 250 ),
			'duration_ms'  => round( $duration, 2 ),
		);

		// If status is 404 and response has standard rest_no_route JSON, WordPress handled it perfectly.
		if ( 404 === $status_code && $is_wp_json ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_HEALTHY,
				__( 'WordPress REST API server properly captures non-existent routes and returns standard structured JSON error responses (rest_no_route).', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(),
				__( 'Standard error handling is active.', 'wpcalibrate-rest-health-repair' ),
				__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
				null,
				null,
				DiagnosticResult::SEVERITY_INFO,
				time(),
				$duration
			);
		}

		// If 404 but not WP REST JSON (raw webserver HTML 404).
		if ( 404 === $status_code && ! $is_wp_json ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_WARNING,
				__( 'Web server returned a generic 404 HTML response instead of passing the missing REST route to WordPress REST error handlers.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(
					__( 'Web server rewrite rule is catching invalid sub-paths before index.php is invoked.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'Clients calling missing endpoints will receive HTML 404 pages instead of structured JSON errors.', 'wpcalibrate-rest-health-repair' ),
				__( 'Ensure web server routing passes all /wp-json/* paths through WordPress index.php.', 'wpcalibrate-rest-health-repair' ),
				null,
				null,
				DiagnosticResult::SEVERITY_WARNING,
				time(),
				$duration
			);
		}

		// Other statuses (e.g. 403, 401, 500).
		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_WARNING,
			sprintf(
				/* translators: %d: Status code */
				__( 'Invalid route probe received unexpected HTTP %d response.', 'wpcalibrate-rest-health-repair' ),
				$status_code
			),
			$evidence,
			array(
				__( 'Global authentication filter or firewall rejected the request before route evaluation.', 'wpcalibrate-rest-health-repair' ),
			),
			__( 'Error states may be masked by global access restrictions.', 'wpcalibrate-rest-health-repair' ),
			__( 'Review global REST authentication restrictions.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_WARNING,
			time(),
			$duration
		);
	}
}

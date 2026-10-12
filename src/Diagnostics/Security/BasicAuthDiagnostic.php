<?php
/**
 * Diagnostic for detecting HTTP Basic Authentication blocking REST API endpoints.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics\Security;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticResult;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BasicAuthDiagnostic class.
 */
final class BasicAuthDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'basic_auth_protection';
	}

	public function get_group(): string {
		return 'security';
	}

	public function get_title(): string {
		return __( 'HTTP Basic Authentication & Password Protection', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Checks if server-level HTTP password protection (.htpasswd / Basic Auth) is blocking unauthenticated REST API requests.', 'wpcalibrate-rest-health-repair' );
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

		$response = wp_remote_get(
			$target_url,
			array(
				'timeout'    => $timeout,
				'user-agent' => 'WPCalibrate-BasicAuth-Diagnostic/1.0',
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);
		$duration = ( microtime( true ) - $start_time ) * 1000;

		if ( is_wp_error( $response ) ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_UNABLE_TO_TEST,
				__( 'Could not inspect Basic Auth due to request failure.', 'wpcalibrate-rest-health-repair' ),
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
		$auth_header = (string) wp_remote_retrieve_header( $response, 'www-authenticate' );

		$has_basic_auth = 401 === $status_code && ( str_contains( strtolower( $auth_header ), 'basic' ) || str_contains( strtolower( $auth_header ), 'digest' ) );

		$evidence = array(
			'status_code'         => $status_code,
			'has_auth_header'     => ! empty( $auth_header ),
			'auth_header_type'    => Sanitizer::redact_secrets( $auth_header ),
			'has_basic_challenge' => $has_basic_auth,
			'duration_ms'         => round( $duration, 2 ),
		);

		if ( $has_basic_auth ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_WARNING,
				__( 'HTTP Basic Authentication (.htpasswd) is enabled and requiring credentials for REST API calls.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(
					__( 'Staging site password protection configured via cPanel, .htaccess, or reverse proxy.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'External services, payment webhooks, WooCommerce apps, and loopback tasks fail with HTTP 401.', 'wpcalibrate-rest-health-repair' ),
				__( 'Whitelist /wp-json/ endpoints in your webserver configuration so API consumers are not blocked.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( "In .htaccess, allow /wp-json/ without password:\nSetEnvIf Request_URI \"^/wp-json/\" allow_rest\nOrder allow,deny\nAllow from env=allow_rest\nSatisfy any", 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_WARNING,
				time(),
				$duration
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			__( 'No server-level HTTP Basic Authentication is obstructing REST endpoints.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'Direct HTTP access permitted.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			$duration
		);
	}
}

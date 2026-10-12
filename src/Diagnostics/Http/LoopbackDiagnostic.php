<?php
/**
 * Diagnostic for testing local loopback request connectivity.
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
 * LoopbackDiagnostic class.
 */
final class LoopbackDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'loopback_request';
	}

	public function get_group(): string {
		return 'http';
	}

	public function get_title(): string {
		return __( 'Server Loopback & Self-Request Connectivity', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Verifies that the server can make HTTP requests to itself, which is required for WP-Cron, Site Health, and REST loopback calls.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return true;
	}

	public function get_repair_id(): ?string {
		return null;
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_CRITICAL;
	}

	public function run(): DiagnosticResult {
		$start_time = microtime( true );
		$target_url = admin_url( 'admin-ajax.php?action=health-check-loopback-test' );

		$settings = (array) get_option( 'wpcalibrate_rhr_settings', array() );
		$timeout  = max( 3, min( 30, (int) ( $settings['http_timeout'] ?? 10 ) ) );

		$response = wp_remote_get(
			$target_url,
			array(
				'timeout'    => $timeout,
				'user-agent' => 'WPCalibrate-Loopback-Diagnostic/1.0',
				'cookies'    => array(),
			)
		);
		$duration = ( microtime( true ) - $start_time ) * 1000;

		if ( is_wp_error( $response ) ) {
			$code    = $response->get_error_code();
			$message = $response->get_error_message();

			$evidence = array(
				'target_url'  => $target_url,
				'error_code'  => $code,
				'message'     => Sanitizer::redact_secrets( $message ),
				'duration_ms' => round( $duration, 2 ),
			);

			$likely = array(
				__( 'Web server firewall (ModSecurity, Cloudflare, AWS WAF) blocking outgoing connections from the server IP.', 'wpcalibrate-rest-health-repair' ),
				__( 'DNS loopback failure: Server cannot resolve its own domain name to 127.0.0.1 or public IP.', 'wpcalibrate-rest-health-repair' ),
				__( 'Hosting provider disabled or restricted outgoing loopback cURL connections.', 'wpcalibrate-rest-health-repair' ),
			);

			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				sprintf(
					/* translators: 1: Error code, 2: Error message */
					__( 'Loopback connection failed [%1$s]: %2$s', 'wpcalibrate-rest-health-repair' ),
					$code,
					$message
				),
				$evidence,
				$likely,
				__( 'Scheduled WP-Cron events, background updates, and REST API loopback verification fail.', 'wpcalibrate-rest-health-repair' ),
				__( 'Contact your hosting provider to verify loopback requests and local hosts file resolution.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( '1. Ask your web host to ensure the server can resolve its own domain locally in /etc/hosts. 2. Verify firewall allowlist for server outbound IP.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				$duration
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$evidence = array(
			'target_url'  => $target_url,
			'status_code' => $status,
			'duration_ms' => round( $duration, 2 ),
		);

		if ( 200 !== $status && 400 !== $status ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_WARNING,
				sprintf(
					/* translators: %d: Status code */
					__( 'Loopback request connected but received unexpected HTTP status %d.', 'wpcalibrate-rest-health-repair' ),
					$status
				),
				$evidence,
				array(
					__( 'HTTP Basic Authentication or password-protected directory blocking automated requests.', 'wpcalibrate-rest-health-repair' ),
					__( 'Firewall challenge page returned to loopback connection.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'Automated background tasks may encounter unexpected errors.', 'wpcalibrate-rest-health-repair' ),
				__( 'Check for server authentication (.htpasswd) or staging security locks.', 'wpcalibrate-rest-health-repair' ),
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
			__( 'Local loopback HTTP connectivity operates properly.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'Loopback functions normally.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			$duration
		);
	}
}

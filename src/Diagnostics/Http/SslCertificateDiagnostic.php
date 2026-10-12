<?php
/**
 * Diagnostic for checking SSL certificate verification and HTTPS health.
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
 * SslCertificateDiagnostic class.
 */
final class SslCertificateDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'ssl_certificate';
	}

	public function get_group(): string {
		return 'http';
	}

	public function get_title(): string {
		return __( 'SSL Certificate & HTTPS Verification', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Verifies SSL certificate trust and handshake integrity using WordPress HTTP API with strict SSL validation enabled.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return is_ssl() || str_starts_with( home_url(), 'https://' );
	}

	public function get_repair_id(): ?string {
		return null;
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_CRITICAL;
	}

	public function run(): DiagnosticResult {
		$start_time = microtime( true );
		$target_url = home_url( '/' );

		// Strict SSL validation test. We NEVER disable sslverify.
		$response = wp_remote_get(
			$target_url,
			array(
				'timeout'     => 10,
				'sslverify'   => true,
				'redirection' => 2,
				'user-agent'  => 'WPCalibrate-SSL-Diagnostic/1.0',
			)
		);
		$duration = ( microtime( true ) - $start_time ) * 1000;

		if ( is_wp_error( $response ) ) {
			$code    = $response->get_error_code();
			$message = $response->get_error_message();

			$is_ssl_error = str_contains( strtolower( $message ), 'certificate' )
				|| str_contains( strtolower( $message ), 'ssl' )
				|| str_contains( strtolower( $code ), 'ssl' )
				|| str_contains( strtolower( $message ), 'curl error 60' )
				|| str_contains( strtolower( $message ), 'curl error 51' )
				|| str_contains( strtolower( $message ), 'curl error 35' );

			$evidence = array(
				'target_url'   => $target_url,
				'error_code'   => $code,
				'message'      => Sanitizer::redact_secrets( $message ),
				'is_ssl_error' => $is_ssl_error,
				'duration_ms'  => round( $duration, 2 ),
			);

			if ( $is_ssl_error ) {
				return new DiagnosticResult(
					$this->get_id(),
					DiagnosticResult::STATUS_FAILED,
					sprintf(
						/* translators: %s: Error message */
						__( 'SSL Certificate verification failed: %s', 'wpcalibrate-rest-health-repair' ),
						$message
					),
					$evidence,
					array(
						__( 'Self-signed, invalid, or expired SSL certificate installed on server.', 'wpcalibrate-rest-health-repair' ),
						__( 'Certificate domain name does not match the WordPress Home URL hostname.', 'wpcalibrate-rest-health-repair' ),
						__( 'Outdated server CA bundle (curl.cainfo) failing to trust standard certificate authorities.', 'wpcalibrate-rest-health-repair' ),
					),
					__( 'External payment gateways, webhooks, and REST clients will reject connections to this site.', 'wpcalibrate-rest-health-repair' ),
					__( 'Renew or fix your SSL certificate, or update server CA root certificates. Do not disable SSL verification in code.', 'wpcalibrate-rest-health-repair' ),
					null,
					__( '1. Check certificate validity with an SSL checker tool (e.g. SSL Labs). 2. Contact host to update curl.cainfo bundle. 3. Never use add_filter("https_ssl_verify", "__return_false").', 'wpcalibrate-rest-health-repair' ),
					DiagnosticResult::SEVERITY_CRITICAL,
					time(),
					$duration
				);
			}

			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_WARNING,
				sprintf(
					/* translators: %s: Error message */
					__( 'HTTPS request encountered an issue: %s', 'wpcalibrate-rest-health-repair' ),
					$message
				),
				$evidence,
				array( __( 'Connection timeout or network failure before SSL handshake concluded.', 'wpcalibrate-rest-health-repair' ) ),
				__( 'HTTPS connectivity could not be verified completely.', 'wpcalibrate-rest-health-repair' ),
				__( 'Check network connectivity to site HTTPS URL.', 'wpcalibrate-rest-health-repair' ),
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
			__( 'SSL certificate is valid and trusted by the WordPress HTTP transport.', 'wpcalibrate-rest-health-repair' ),
			array(
				'target_url'  => $target_url,
				'status_code' => wp_remote_retrieve_response_code( $response ),
				'sslverify'   => true,
				'duration_ms' => round( $duration, 2 ),
			),
			array(),
			__( 'HTTPS connections operate securely.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			$duration
		);
	}
}

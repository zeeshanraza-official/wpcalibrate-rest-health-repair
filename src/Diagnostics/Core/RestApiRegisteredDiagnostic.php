<?php
/**
 * Diagnostic for checking core WordPress REST API registration and server initialization.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics\Core;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RestApiRegisteredDiagnostic class.
 */
final class RestApiRegisteredDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'wp_rest_registered';
	}

	public function get_group(): string {
		return 'core';
	}

	public function get_title(): string {
		return __( 'WordPress REST API Core Registration', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Verifies that WordPress core REST API server classes and infrastructure are present and initialized.', 'wpcalibrate-rest-health-repair' );
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

		$server_class_exists = class_exists( 'WP_REST_Server' );
		$rest_url_exists     = function_exists( 'rest_url' );
		$rest_server_func    = function_exists( 'rest_get_server' );

		$evidence = array(
			'WP_REST_Server_class' => $server_class_exists,
			'rest_url_function'    => $rest_url_exists,
			'rest_get_server'      => $rest_server_func,
		);

		if ( ! $server_class_exists || ! $rest_url_exists ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				__( 'Core WordPress REST API classes or functions are missing from the runtime.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(
					__( 'Corrupted WordPress core installation or missing files in /wp-includes/rest-api/.', 'wpcalibrate-rest-health-repair' ),
					__( 'Must-use plugin or custom bootstrap code unregistering or disabling core REST libraries.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'No REST endpoints or Gutenberg block editor features can operate.', 'wpcalibrate-rest-health-repair' ),
				__( 'Verify WordPress core file integrity via Dashboard > Updates > Re-install now, and check for interfering mu-plugins.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( '1. Go to WordPress Dashboard > Updates and click "Re-install version". 2. Check /wp-content/mu-plugins/ for code disabling REST classes.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				( microtime( true ) - $start_time ) * 1000
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			__( 'Core WordPress REST API server classes and helper functions are properly registered and available.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'Normal WordPress REST operation.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			( microtime( true ) - $start_time ) * 1000
		);
	}
}

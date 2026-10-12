<?php
/**
 * Diagnostic for checking Application Passwords availability for external REST authentication.
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
 * ApplicationPasswordsDiagnostic class.
 */
final class ApplicationPasswordsDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'wp_rest_app_passwords';
	}

	public function get_group(): string {
		return 'core';
	}

	public function get_title(): string {
		return __( 'Application Passwords Support', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Checks if WordPress Application Passwords feature is supported and enabled for secure external REST API authentication without creating passwords.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return true;
	}

	public function get_repair_id(): ?string {
		return null;
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_INFO;
	}

	public function run(): DiagnosticResult {
		$start_time = microtime( true );

		$func_exists = function_exists( 'wp_is_application_passwords_available' );
		$available   = $func_exists && wp_is_application_passwords_available();
		$is_ssl      = is_ssl();

		$current_user_id = get_current_user_id();
		$user_supported  = false;
		if ( $current_user_id && function_exists( 'wp_is_application_passwords_supported_for_user' ) ) {
			$user           = get_userdata( $current_user_id );
			$user_supported = $user ? wp_is_application_passwords_supported_for_user( $user ) : false;
		}

		$evidence = array(
			'feature_available' => $available,
			'is_ssl'            => $is_ssl,
			'user_supported'    => $user_supported,
		);

		if ( ! $available ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_WARNING,
				__( 'Application Passwords are unavailable on this WordPress site.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(
					__( 'Site is not running over HTTPS (required by default for Application Passwords).', 'wpcalibrate-rest-health-repair' ),
					__( 'Application Passwords feature was disabled via wp_is_application_passwords_available filter by a security plugin.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'Mobile apps, remote publishing tools, and third-party integrations requiring application passwords cannot authenticate.', 'wpcalibrate-rest-health-repair' ),
				__( 'Enable HTTPS or check security plugins for Application Passwords restrictions.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( '1. Ensure your site uses a valid SSL certificate and HTTPS scheme. 2. Verify security plugins have not disabled Application Passwords.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_WARNING,
				time(),
				( microtime( true ) - $start_time ) * 1000
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			__( 'Application Passwords feature is available and ready for secure REST authentication.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'External apps can authenticate using application passwords.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			( microtime( true ) - $start_time ) * 1000
		);
	}
}

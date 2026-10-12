<?php
/**
 * Diagnostic report sanitizer and secret redactor.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Reports;

use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ReportSanitizer class.
 */
final class ReportSanitizer {

	/**
	 * Sanitizes full diagnostic report data structure.
	 *
	 * @param array<string, mixed> $data Raw report data.
	 * @return array<string, mixed> Redacted data.
	 */
	public static function sanitize_report_data( array $data ): array {
		// Strict deep pass.
		$sanitized = (array) Sanitizer::sanitize_deep( $data );

		// Explicitly ensure no license key, database password, salts, or session tokens exist.
		unset(
			$sanitized['db_password'],
			$sanitized['auth_key'],
			$sanitized['secure_auth_key'],
			$sanitized['logged_in_key'],
			$sanitized['nonce_key'],
			$sanitized['auth_salt'],
			$sanitized['secure_auth_salt'],
			$sanitized['logged_in_salt'],
			$sanitized['nonce_salt'],
			$sanitized['license_key']
		);

		return $sanitized;
	}
}

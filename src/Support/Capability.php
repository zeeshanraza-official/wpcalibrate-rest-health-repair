<?php
/**
 * Capability and permission helper.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capability class.
 */
final class Capability {

	/**
	 * Default required capability.
	 *
	 * @var string
	 */
	public const REQUIRED_CAPABILITY = 'manage_options';

	/**
	 * Checks if current user has the required capability.
	 *
	 * @param string $capability Capability name.
	 * @return bool
	 */
	public static function current_user_can( string $capability = self::REQUIRED_CAPABILITY ): bool {
		return function_exists( 'current_user_can' ) && current_user_can( $capability );
	}

	/**
	 * Asserts current user capability and throws or errors if unauthorized.
	 *
	 * @param string $capability Capability name.
	 * @return void
	 */
	public static function check_or_die( string $capability = self::REQUIRED_CAPABILITY ): void {
		if ( ! self::current_user_can( $capability ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to access this page or perform this diagnostic action.', 'wpcalibrate-rest-health-repair' ),
				esc_html__( 'Unauthorized Access', 'wpcalibrate-rest-health-repair' ),
				array( 'response' => 403 )
			);
		}
	}
}

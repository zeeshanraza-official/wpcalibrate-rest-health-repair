<?php
/**
 * Settings registry and validator.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Admin;

use WPCalibrate\RESTHealthRepair\Support\Capability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class.
 */
final class Settings {

	public const OPTION_KEY = 'wpcalibrate_rhr_settings';

	/**
	 * Default settings.
	 *
	 * @var array<string, mixed>
	 */
	public const DEFAULTS = array(
		'http_timeout'        => 10,
		'logging_enabled'     => false,
		'retention_days'      => 7,
		'delete_on_uninstall' => false,
		'advanced_details'    => true,
	);

	/**
	 * Gets current settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		$saved = get_option( self::OPTION_KEY, array() );
		return array_merge( self::DEFAULTS, is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Gets a specific setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value if missing.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$all = self::get_all();
		return $all[ $key ] ?? $default;
	}

	/**
	 * Sanitizes and validates settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public static function sanitize_and_validate( array $input ): array {
		$clean = array();

		// Timeout: allowlisted range 3 to 30.
		$timeout = isset( $input['http_timeout'] ) ? absint( $input['http_timeout'] ) : 10;
		$clean['http_timeout'] = max( 3, min( 30, $timeout ) );

		// Logging: boolean.
		$clean['logging_enabled'] = ! empty( $input['logging_enabled'] );

		// Retention days: allowlisted choices 7, 14, 30.
		$retention = isset( $input['retention_days'] ) ? absint( $input['retention_days'] ) : 7;
		$clean['retention_days'] = in_array( $retention, array( 7, 14, 30 ), true ) ? $retention : 7;

		// Delete on uninstall: boolean.
		$clean['delete_on_uninstall'] = ! empty( $input['delete_on_uninstall'] );

		// Advanced details: boolean.
		$clean['advanced_details'] = ! empty( $input['advanced_details'] );

		return $clean;
	}

	/**
	 * Saves settings.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return bool
	 */
	public static function save( array $input ): bool {
		Capability::check_or_die();
		$validated = self::sanitize_and_validate( $input );
		return update_option( self::OPTION_KEY, $validated, false );
	}
}

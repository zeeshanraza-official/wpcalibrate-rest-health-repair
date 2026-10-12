<?php
/**
 * Unconfigured license provider default implementation.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Licensing;

use WPCalibrate\RESTHealthRepair\Support\Capability;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unconfigured license provider.
 */
final class UnconfiguredLicenseProvider implements LicenseProviderInterface {

	/**
	 * Option key for stored license data.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'wpcalibrate_rhr_license';

	/**
	 * Checks if configured.
	 *
	 * @return bool Always false until commercial provider details are supplied.
	 */
	public function is_configured(): bool {
		return false;
	}

	/**
	 * Gets license status.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return 'unconfigured';
	}

	/**
	 * Gets status message.
	 *
	 * @return string
	 */
	public function get_status_message(): string {
		return __( 'Licensing integration not configured. External licensing provider details have not yet been supplied.', 'wpcalibrate-rest-health-repair' );
	}

	/**
	 * Gets stored license key.
	 *
	 * @param bool $masked Whether to mask the key.
	 * @return string
	 */
	public function get_key( bool $masked = true ): string {
		$data = (array) get_option( self::OPTION_KEY, array() );
		$key  = (string) ( $data['key'] ?? '' );
		if ( '' === $key ) {
			return '';
		}
		return $masked ? Sanitizer::mask_license_key( $key ) : $key;
	}

	/**
	 * Sets license key.
	 *
	 * @param string $key License key.
	 * @return bool
	 */
	public function set_key( string $key ): bool {
		Capability::check_or_die();
		$clean_key = sanitize_text_field( trim( $key ) );
		$data      = (array) get_option( self::OPTION_KEY, array() );

		$data['key']        = $clean_key;
		$data['updated_at'] = time();

		return update_option( self::OPTION_KEY, $data, false );
	}

	/**
	 * Activates license.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function activate(): array {
		Capability::check_or_die();
		return array(
			'success' => false,
			'message' => __( 'Licensing integration not configured. No external activation request was performed.', 'wpcalibrate-rest-health-repair' ),
		);
	}

	/**
	 * Deactivates license.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function deactivate(): array {
		Capability::check_or_die();
		return array(
			'success' => false,
			'message' => __( 'Licensing integration not configured. No external deactivation request was performed.', 'wpcalibrate-rest-health-repair' ),
		);
	}

	/**
	 * Gets expiry date.
	 *
	 * @return string|null
	 */
	public function get_expiry(): ?string {
		return null;
	}

	/**
	 * Gets renewal URL.
	 *
	 * @return string|null
	 */
	public function get_renewal_url(): ?string {
		return 'https://marketplace.wpcalibrate.com/';
	}
}

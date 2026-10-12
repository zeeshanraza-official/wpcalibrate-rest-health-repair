<?php
/**
 * License provider interface.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface LicenseProviderInterface.
 */
interface LicenseProviderInterface {

	/**
	 * Checks if the provider is fully configured with an external API endpoint/credentials.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * Gets current license status code (e.g. 'unconfigured', 'inactive', 'active', 'expired', 'invalid').
	 *
	 * @return string
	 */
	public function get_status(): string;

	/**
	 * Gets human-readable license status message.
	 *
	 * @return string
	 */
	public function get_status_message(): string;

	/**
	 * Gets stored license key (masked for display if requested).
	 *
	 * @param bool $masked Whether to return masked key.
	 * @return string
	 */
	public function get_key( bool $masked = true ): string;

	/**
	 * Saves a license key.
	 *
	 * @param string $key License key.
	 * @return bool
	 */
	public function set_key( string $key ): bool;

	/**
	 * Activates license.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function activate(): array;

	/**
	 * Deactivates license.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function deactivate(): array;

	/**
	 * Gets license expiry date or null.
	 *
	 * @return string|null
	 */
	public function get_expiry(): ?string;

	/**
	 * Gets license renewal URL or null.
	 *
	 * @return string|null
	 */
	public function get_renewal_url(): ?string;
}

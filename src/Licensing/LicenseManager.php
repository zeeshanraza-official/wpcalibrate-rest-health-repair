<?php
/**
 * License manager.
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
 * License manager.
 */
final class LicenseManager {

	/**
	 * Provider instance.
	 *
	 * @var LicenseProviderInterface
	 */
	private LicenseProviderInterface $provider;

	/**
	 * Constructor.
	 *
	 * @param LicenseProviderInterface|null $provider Provider.
	 */
	public function __construct( ?LicenseProviderInterface $provider = null ) {
		$this->provider = $provider ?? new UnconfiguredLicenseProvider();
	}

	/**
	 * Gets the active provider.
	 *
	 * @return LicenseProviderInterface
	 */
	public function get_provider(): LicenseProviderInterface {
		return $this->provider;
	}

	/**
	 * Sets the active provider.
	 *
	 * @param LicenseProviderInterface $provider Provider.
	 * @return void
	 */
	public function set_provider( LicenseProviderInterface $provider ): void {
		$this->provider = $provider;
	}

	/**
	 * Gets license status.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return $this->provider->get_status();
	}

	/**
	 * Gets status message.
	 *
	 * @return string
	 */
	public function get_status_message(): string {
		return $this->provider->get_status_message();
	}

	/**
	 * Gets masked key.
	 *
	 * @return string
	 */
	public function get_masked_key(): string {
		return $this->provider->get_key( true );
	}

	/**
	 * Saves key.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public function save_key( string $key ): bool {
		return $this->provider->set_key( $key );
	}

	/**
	 * Activates.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function activate(): array {
		return $this->provider->activate();
	}

	/**
	 * Deactivates.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function deactivate(): array {
		return $this->provider->deactivate();
	}
}

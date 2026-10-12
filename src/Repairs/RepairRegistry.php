<?php
/**
 * Repair registry.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Repairs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RepairRegistry class.
 */
final class RepairRegistry {

	/**
	 * Registered repairs: id => RepairInterface.
	 *
	 * @var array<string, RepairInterface>
	 */
	private array $repairs = array();

	/**
	 * Registers a repair instance.
	 *
	 * @param RepairInterface $repair Repair instance.
	 * @return void
	 */
	public function register( RepairInterface $repair ): void {
		$this->repairs[ $repair->get_id() ] = $repair;
	}

	/**
	 * Gets a repair by ID.
	 *
	 * @param string $id Repair ID.
	 * @return RepairInterface|null
	 */
	public function get( string $id ): ?RepairInterface {
		return $this->repairs[ $id ] ?? null;
	}

	/**
	 * Checks if registered.
	 *
	 * @param string $id Repair ID.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->repairs[ $id ] );
	}

	/**
	 * Gets all repairs.
	 *
	 * @return array<string, RepairInterface>
	 */
	public function all(): array {
		return $this->repairs;
	}
}

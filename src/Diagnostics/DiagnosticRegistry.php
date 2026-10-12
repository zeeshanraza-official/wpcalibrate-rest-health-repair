<?php
/**
 * Diagnostic registry.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DiagnosticRegistry class.
 */
final class DiagnosticRegistry {

	/**
	 * Registered diagnostics map: id => DiagnosticInterface.
	 *
	 * @var array<string, DiagnosticInterface>
	 */
	private array $diagnostics = array();

	/**
	 * Registers a diagnostic instance.
	 *
	 * @param DiagnosticInterface $diagnostic Diagnostic.
	 * @return void
	 */
	public function register( DiagnosticInterface $diagnostic ): void {
		$this->diagnostics[ $diagnostic->get_id() ] = $diagnostic;
	}

	/**
	 * Gets a diagnostic by ID.
	 *
	 * @param string $id Diagnostic ID.
	 * @return DiagnosticInterface|null
	 */
	public function get( string $id ): ?DiagnosticInterface {
		return $this->diagnostics[ $id ] ?? null;
	}

	/**
	 * Checks if a diagnostic ID is registered.
	 *
	 * @param string $id Diagnostic ID.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->diagnostics[ $id ] );
	}

	/**
	 * Gets all registered diagnostics.
	 *
	 * @return array<string, DiagnosticInterface>
	 */
	public function all(): array {
		return $this->diagnostics;
	}

	/**
	 * Gets diagnostics filtered by group.
	 *
	 * @param string $group Group name.
	 * @return array<string, DiagnosticInterface>
	 */
	public function get_by_group( string $group ): array {
		$filtered = array();
		foreach ( $this->diagnostics as $id => $diagnostic ) {
			if ( $diagnostic->get_group() === $group ) {
				$filtered[ $id ] = $diagnostic;
			}
		}
		return $filtered;
	}

	/**
	 * Gets all registered diagnostic IDs.
	 *
	 * @return array<string>
	 */
	public function get_ids(): array {
		return array_keys( $this->diagnostics );
	}
}

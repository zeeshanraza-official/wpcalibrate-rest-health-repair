<?php
/**
 * Diagnostic interface.
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
 * Interface DiagnosticInterface.
 */
interface DiagnosticInterface {

	/**
	 * Unique diagnostic identifier (slug-format).
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Category / Group (core, http, rewrites, security, woocommerce).
	 *
	 * @return string
	 */
	public function get_group(): string;

	/**
	 * Human-readable title.
	 *
	 * @return string
	 */
	public function get_title(): string;

	/**
	 * Description of what this diagnostic tests.
	 *
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * Checks if this diagnostic applies to the current environment.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool;

	/**
	 * Runs the diagnostic and returns a DiagnosticResult.
	 *
	 * @return DiagnosticResult
	 */
	public function run(): DiagnosticResult;

	/**
	 * Associated automatic repair ID if one exists, or null.
	 *
	 * @return string|null
	 */
	public function get_repair_id(): ?string;

	/**
	 * Default severity level (critical, warning, info).
	 *
	 * @return string
	 */
	public function get_severity(): string;
}

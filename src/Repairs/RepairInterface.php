<?php
/**
 * Repair interface.
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
 * Interface RepairInterface.
 */
interface RepairInterface {

	public const RISK_LOW       = 'low';
	public const RISK_MODERATE  = 'moderate';
	public const RISK_MANUAL    = 'manual';

	/**
	 * Unique repair identifier.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Human-readable title.
	 *
	 * @return string
	 */
	public function get_title(): string;

	/**
	 * Detailed description.
	 *
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * Risk classification (low, moderate, manual).
	 *
	 * @return string
	 */
	public function get_risk_level(): string;

	/**
	 * Related diagnostic IDs this repair attempts to fix.
	 *
	 * @return array<string>
	 */
	public function get_related_diagnostic_ids(): array;

	/**
	 * Checks if the repair is applicable to the current site environment.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool;

	/**
	 * Returns human-readable explanation if the repair is not applicable to the current environment.
	 *
	 * @return string
	 */
	public function get_inapplicability_reason(): string;

	/**
	 * Explains what changes will be performed before execution.
	 *
	 * @return array<string>
	 */
	public function get_planned_changes(): array;

	/**
	 * Executes the repair operation.
	 *
	 * @return RepairResult
	 */
	public function execute(): RepairResult;

	/**
	 * Checks whether rollback is supported.
	 *
	 * @return bool
	 */
	public function can_rollback(): bool;

	/**
	 * Reverts changes made by this repair if a backup exists.
	 *
	 * @return RepairResult
	 */
	public function rollback(): RepairResult;
}

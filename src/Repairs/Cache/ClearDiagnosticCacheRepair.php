<?php
/**
 * Safe automatic repair for clearing diagnostic transients and cached results.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Repairs\Cache;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticRunner;
use WPCalibrate\RESTHealthRepair\Repairs\RepairInterface;
use WPCalibrate\RESTHealthRepair\Repairs\RepairResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ClearDiagnosticCacheRepair class.
 */
final class ClearDiagnosticCacheRepair implements RepairInterface {

	public function get_id(): string {
		return 'clear_diagnostic_cache';
	}

	public function get_title(): string {
		return __( 'Clear Diagnostic Results Cache', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Removes cached diagnostic results and temporary transients stored by this plugin, ensuring all future scans reflect fresh real-time status.', 'wpcalibrate-rest-health-repair' );
	}

	public function get_risk_level(): string {
		return self::RISK_LOW;
	}

	public function get_related_diagnostic_ids(): array {
		return array();
	}

	public function is_applicable(): bool {
		return true;
	}

	public function get_inapplicability_reason(): string {
		return '';
	}

	public function get_planned_changes(): array {
		return array(
			__( 'Delete transient wpcalibrate_rhr_diag_results.', 'wpcalibrate-rest-health-repair' ),
			__( 'Reset internal scan cache state.', 'wpcalibrate-rest-health-repair' ),
		);
	}

	public function execute(): RepairResult {
		delete_transient( DiagnosticRunner::CACHE_TRANSIENT_KEY );

		return new RepairResult(
			$this->get_id(),
			RepairResult::STATUS_FIXED,
			__( 'Cleared diagnostic cache and transients successfully.', 'wpcalibrate-rest-health-repair' ),
			$this->get_planned_changes(),
			array( __( 'Deleted transient wpcalibrate_rhr_diag_results.', 'wpcalibrate-rest-health-repair' ) ),
			true,
			false
		);
	}

	public function can_rollback(): bool {
		return false;
	}

	public function rollback(): RepairResult {
		return new RepairResult(
			$this->get_id(),
			RepairResult::STATUS_FAILED,
			__( 'Clearing cache cannot be rolled back.', 'wpcalibrate-rest-health-repair' )
		);
	}
}

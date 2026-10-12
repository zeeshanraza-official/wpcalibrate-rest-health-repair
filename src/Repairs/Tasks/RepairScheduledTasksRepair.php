<?php
/**
 * Safe automatic repair for checking and repairing plugin scheduled tasks.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Repairs\Tasks;

use WPCalibrate\RESTHealthRepair\Repairs\RepairInterface;
use WPCalibrate\RESTHealthRepair\Repairs\RepairResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RepairScheduledTasksRepair class.
 */
final class RepairScheduledTasksRepair implements RepairInterface {

	public const CRON_HOOK = 'wpcalibrate_rhr_daily_maintenance';

	public function get_id(): string {
		return 'repair_scheduled_tasks';
	}

	public function get_title(): string {
		return __( 'Repair Scheduled Tasks', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Checks and reschedules plugin maintenance cron events if they are missing or orphaned in WP-Cron.', 'wpcalibrate-rest-health-repair' );
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
			__( 'Clear any duplicated or stale plugin-owned cron events.', 'wpcalibrate-rest-health-repair' ),
			__( 'Ensure daily maintenance task is properly scheduled.', 'wpcalibrate-rest-health-repair' ),
		);
	}

	public function execute(): RepairResult {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );

		return new RepairResult(
			$this->get_id(),
			RepairResult::STATUS_FIXED,
			__( 'Successfully repaired and rescheduled plugin background tasks.', 'wpcalibrate-rest-health-repair' ),
			$this->get_planned_changes(),
			array(
				__( 'Cleared existing scheduled events for maintenance hook.', 'wpcalibrate-rest-health-repair' ),
				__( 'Rescheduled clean daily maintenance task.', 'wpcalibrate-rest-health-repair' ),
			),
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
			__( 'Rollback is not applicable for scheduled task repair.', 'wpcalibrate-rest-health-repair' )
		);
	}
}

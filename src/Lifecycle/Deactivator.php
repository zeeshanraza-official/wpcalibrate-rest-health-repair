<?php
/**
 * Plugin deactivation lifecycle handler.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Lifecycle;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticRunner;
use WPCalibrate\RESTHealthRepair\Repairs\RepairRunner;
use WPCalibrate\RESTHealthRepair\Repairs\Tasks\RepairScheduledTasksRepair;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivator class.
 */
final class Deactivator {

	/**
	 * Runs deactivation cleanup safely without erasing persistent user configuration.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Clear plugin-owned scheduled cron events.
		wp_clear_scheduled_hook( RepairScheduledTasksRepair::CRON_HOOK );

		// Clear temporary execution locks and transient caches.
		delete_transient( RepairRunner::LOCK_TRANSIENT_KEY );
		delete_transient( DiagnosticRunner::CACHE_TRANSIENT_KEY );
		delete_transient( 'wpcalibrate_rhr_just_activated' );
	}
}

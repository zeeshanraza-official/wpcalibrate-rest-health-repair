<?php
/**
 * Plugin activation lifecycle handler.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Lifecycle;

use WPCalibrate\RESTHealthRepair\Admin\Settings;
use WPCalibrate\RESTHealthRepair\Repairs\Tasks\RepairScheduledTasksRepair;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activator class.
 */
final class Activator {

	public const SCHEMA_VERSION_KEY = 'wpcalibrate_rhr_schema_version';
	public const CURRENT_SCHEMA_VER = '1.0.0';

	/**
	 * Runs activation logic safely.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Initialize default settings if not present.
		$existing_settings = get_option( Settings::OPTION_KEY, null );
		if ( null === $existing_settings ) {
			add_option( Settings::OPTION_KEY, Settings::DEFAULTS, '', false );
		}

		// Store schema version.
		update_option( self::SCHEMA_VERSION_KEY, self::CURRENT_SCHEMA_VER, false );

		// Schedule daily maintenance task if not already scheduled.
		if ( ! wp_next_scheduled( RepairScheduledTasksRepair::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', RepairScheduledTasksRepair::CRON_HOOK );
		}

		// Set activation transient to trigger first-run notice if desired.
		set_transient( 'wpcalibrate_rhr_just_activated', 1, 30 );
	}
}

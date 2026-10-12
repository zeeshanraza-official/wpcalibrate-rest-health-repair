<?php
/**
 * Plugin uninstall lifecycle handler.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Lifecycle;

use WPCalibrate\RESTHealthRepair\Admin\Settings;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticRunner;
use WPCalibrate\RESTHealthRepair\Licensing\UnconfiguredLicenseProvider;
use WPCalibrate\RESTHealthRepair\Logging\Logger;
use WPCalibrate\RESTHealthRepair\Repairs\RepairRunner;
use WPCalibrate\RESTHealthRepair\Repairs\Rewrites\FlushRewriteRulesRepair;
use WPCalibrate\RESTHealthRepair\Repairs\Rewrites\HtaccessRepair;
use WPCalibrate\RESTHealthRepair\Repairs\Tasks\RepairScheduledTasksRepair;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uninstaller class.
 */
final class Uninstaller {

	/**
	 * Executes uninstall cleanup.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		$settings            = (array) get_option( Settings::OPTION_KEY, array() );
		$delete_on_uninstall = ! empty( $settings['delete_on_uninstall'] );

		// Always unschedule our cron events.
		wp_clear_scheduled_hook( RepairScheduledTasksRepair::CRON_HOOK );

		// Always remove temporary locks and transients.
		delete_transient( RepairRunner::LOCK_TRANSIENT_KEY );
		delete_transient( DiagnosticRunner::CACHE_TRANSIENT_KEY );
		delete_transient( 'wpcalibrate_rhr_migration_lock' );
		delete_transient( 'wpcalibrate_rhr_just_activated' );

		if ( ! $delete_on_uninstall ) {
			// By default, preserve audit logs, settings, and rollback metadata.
			return;
		}

		// When explicit deletion is enabled, remove ONLY plugin-owned options:
		delete_option( Settings::OPTION_KEY );
		delete_option( Activator::SCHEMA_VERSION_KEY );
		delete_option( DiagnosticRunner::SUMMARY_OPTION_KEY );
		delete_option( Logger::OPTION_KEY );
		delete_option( RepairRunner::AUDIT_OPTION_KEY );
		delete_option( FlushRewriteRulesRepair::BACKUP_OPTION_KEY );
		delete_option( HtaccessRepair::BACKUP_OPTION_KEY );
		delete_option( UnconfiguredLicenseProvider::OPTION_KEY );

		// Clean plugin-owned user meta.
		delete_metadata( 'user', 0, 'wpcalibrate_rhr_notice_dismissed', '', true );
	}
}

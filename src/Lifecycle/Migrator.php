<?php
/**
 * Migration runner and schema upgrade manager.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Lifecycle;

use WPCalibrate\RESTHealthRepair\Logging\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrator class.
 */
final class Migrator {

	/**
	 * Checks if schema upgrade is needed and runs idempotent migrations.
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		$installed_ver = (string) get_option( Activator::SCHEMA_VERSION_KEY, '0.0.0' );

		if ( version_compare( $installed_ver, Activator::CURRENT_SCHEMA_VER, '>=' ) ) {
			return; // Up to date.
		}

		$lock_key = 'wpcalibrate_rhr_migration_lock';
		if ( get_transient( $lock_key ) ) {
			return; // Another process migrating.
		}
		set_transient( $lock_key, 1, 60 );

		try {
			if ( version_compare( $installed_ver, '1.0.0', '<' ) ) {
				// Initial schema setup.
				update_option( Activator::SCHEMA_VERSION_KEY, '1.0.0', false );
				Logger::info( 'Migrated WPCalibrate REST Health & Repair schema to 1.0.0.' );
			}
		} finally {
			delete_transient( $lock_key );
		}
	}
}

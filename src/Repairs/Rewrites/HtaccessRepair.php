<?php
/**
 * Safe automatic repair for regenerating standard WordPress .htaccess rewrite directives.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Repairs\Rewrites;

use WPCalibrate\RESTHealthRepair\Repairs\RepairInterface;
use WPCalibrate\RESTHealthRepair\Repairs\RepairResult;
use WPCalibrate\RESTHealthRepair\Support\Environment;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HtaccessRepair class.
 */
final class HtaccessRepair implements RepairInterface {

	public const BACKUP_OPTION_KEY = 'wpcalibrate_rhr_htaccess_backup';

	public function get_id(): string {
		return 'repair_htaccess_rules';
	}

	public function get_title(): string {
		return __( 'Regenerate WordPress .htaccess Directives', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Safely updates the .htaccess file with standard WordPress URL rewrite rules while preserving unrelated directives and creating a rollback backup.', 'wpcalibrate-rest-health-repair' );
	}

	public function get_risk_level(): string {
		return self::RISK_MODERATE;
	}

	public function get_related_diagnostic_ids(): array {
		return array(
			'webserver_rewrite',
			'wp_rest_index',
			'rest_route_fallback',
		);
	}

	public function is_applicable(): bool {
		$ht = Environment::get_htaccess_status();
		return Environment::is_apache_or_litespeed() && $ht['writable'];
	}

	public function get_inapplicability_reason(): string {
		if ( ! Environment::is_apache_or_litespeed() ) {
			return __( 'This repair applies only to Apache or LiteSpeed web servers. Your server is running Nginx, which handles URL rewriting in nginx.conf rather than .htaccess files.', 'wpcalibrate-rest-health-repair' );
		}
		$ht = Environment::get_htaccess_status();
		if ( ! $ht['writable'] ) {
			return __( 'The .htaccess file or root directory is not writable by the web server process.', 'wpcalibrate-rest-health-repair' );
		}
		return '';
	}

	public function get_planned_changes(): array {
		return array(
			__( 'Read current .htaccess content and save timestamped backup in database.', 'wpcalibrate-rest-health-repair' ),
			__( 'Invoke WordPress core save_mod_rewrite_rules() to safely insert or update the # BEGIN WordPress ... # END WordPress block.', 'wpcalibrate-rest-health-repair' ),
			__( 'Preserve all directives outside the WordPress block untouched.', 'wpcalibrate-rest-health-repair' ),
		);
	}

	public function execute(): RepairResult {
		$home_path     = function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH;
		$htaccess_file = $home_path . '.htaccess';

		if ( ! file_exists( $htaccess_file ) && ! is_writable( $home_path ) ) {
			return new RepairResult(
				$this->get_id(),
				RepairResult::STATUS_FAILED,
				__( 'Site directory is not writable. Cannot create or edit .htaccess.', 'wpcalibrate-rest-health-repair' )
			);
		}

		$original_content = file_exists( $htaccess_file ) ? (string) file_get_contents( $htaccess_file ) : '';

		// Back up current .htaccess before touching it.
		update_option(
			self::BACKUP_OPTION_KEY,
			array(
				'content'   => $original_content,
				'timestamp' => time(),
			),
			false
		);

		// Use core save_mod_rewrite_rules() function from wp-admin/includes/misc.php.
		if ( ! function_exists( 'save_mod_rewrite_rules' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$success = save_mod_rewrite_rules();

		if ( ! $success ) {
			return new RepairResult(
				$this->get_id(),
				RepairResult::STATUS_FAILED,
				__( 'WordPress save_mod_rewrite_rules() was unable to update .htaccess.', 'wpcalibrate-rest-health-repair' ),
				$this->get_planned_changes(),
				array(),
				false,
				true
			);
		}

		return new RepairResult(
			$this->get_id(),
			RepairResult::STATUS_FIXED,
			__( 'Successfully regenerated WordPress rewrite block in .htaccess.', 'wpcalibrate-rest-health-repair' ),
			$this->get_planned_changes(),
			array(
				__( 'Backed up original .htaccess to plugin option storage.', 'wpcalibrate-rest-health-repair' ),
				__( 'Executed save_mod_rewrite_rules() to write standard WordPress rules.', 'wpcalibrate-rest-health-repair' ),
			),
			false, // Verification done by RepairRunner.
			true,
			array( 'backup_created' => true )
		);
	}

	public function can_rollback(): bool {
		return false !== get_option( self::BACKUP_OPTION_KEY, false );
	}

	public function rollback(): RepairResult {
		$backup = get_option( self::BACKUP_OPTION_KEY, null );
		if ( ! is_array( $backup ) || ! isset( $backup['content'] ) ) {
			return new RepairResult(
				$this->get_id(),
				RepairResult::STATUS_FAILED,
				__( 'No .htaccess backup data found to restore.', 'wpcalibrate-rest-health-repair' )
			);
		}

		$home_path     = function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH;
		$htaccess_file = $home_path . '.htaccess';

		$written = @file_put_contents( $htaccess_file, (string) $backup['content'] );
		if ( false === $written ) {
			return new RepairResult(
				$this->get_id(),
				RepairResult::STATUS_FAILED,
				__( 'Failed to write restored backup content back into .htaccess file.', 'wpcalibrate-rest-health-repair' )
			);
		}

		delete_option( self::BACKUP_OPTION_KEY );

		return new RepairResult(
			$this->get_id(),
			RepairResult::STATUS_ROLLED_BACK,
			__( 'Restored original .htaccess file from pre-repair backup.', 'wpcalibrate-rest-health-repair' ),
			array( __( 'Revert .htaccess to pre-repair content.', 'wpcalibrate-rest-health-repair' ) ),
			array( __( 'Wrote original backup content to .htaccess.', 'wpcalibrate-rest-health-repair' ) ),
			true,
			false
		);
	}
}

<?php
/**
 * Safe automatic repair for flushing WordPress rewrite rules.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Repairs\Rewrites;

use WPCalibrate\RESTHealthRepair\Repairs\RepairInterface;
use WPCalibrate\RESTHealthRepair\Repairs\RepairResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FlushRewriteRulesRepair class.
 */
final class FlushRewriteRulesRepair implements RepairInterface {

	public const BACKUP_OPTION_KEY           = 'wpcalibrate_rhr_backup_rewrite_rules';
	public const BACKUP_PERMALINK_OPTION_KEY = 'wpcalibrate_rhr_backup_permalink_struct';

	public function get_id(): string {
		return 'flush_rewrite_rules';
	}

	public function get_title(): string {
		return __( 'Flush WordPress Rewrite Rules', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Regenerates the WordPress rewrite rules table to restore missing REST API (^wp-json/) and Store API endpoints.', 'wpcalibrate-rest-health-repair' );
	}

	public function get_risk_level(): string {
		return self::RISK_LOW;
	}

	public function get_related_diagnostic_ids(): array {
		return array(
			'wp_rest_index',
			'rewrite_rules_rest',
			'rest_route_fallback',
			'wp_rest_core_endpoint',
			'wc_store_api_public',
			'wc_not_driven_diagnosis',
		);
	}

	public function is_applicable(): bool {
		return true;
	}

	public function get_inapplicability_reason(): string {
		return '';
	}

	public function get_planned_changes(): array {
		$changes   = array();
		$structure = (string) get_option( 'permalink_structure', '' );

		if ( '' === $structure ) {
			$changes[] = __( 'Update permalink structure from Plain to standard "/%postname%/" to enable /wp-json/ REST and Store API URL routing.', 'wpcalibrate-rest-health-repair' );
		}

		$changes[] = __( 'Back up current rewrite_rules option to plugin rollback storage.', 'wpcalibrate-rest-health-repair' );
		$changes[] = __( 'Execute flush_rewrite_rules(false) to re-query all registered post types, taxonomies, and REST endpoints.', 'wpcalibrate-rest-health-repair' );
		$changes[] = __( 'Write regenerated rewrite rules to the database.', 'wpcalibrate-rest-health-repair' );

		return $changes;
	}

	public function execute(): RepairResult {
		// Step 1: Backup existing rules and permalink structure.
		$current_rules     = get_option( 'rewrite_rules', array() );
		$current_structure = (string) get_option( 'permalink_structure', '' );

		update_option( self::BACKUP_OPTION_KEY, $current_rules, false );
		update_option( self::BACKUP_PERMALINK_OPTION_KEY, $current_structure, false );

		$performed_changes = array(
			__( 'Saved backup of prior rewrite rules and permalink structure.', 'wpcalibrate-rest-health-repair' ),
		);

		// Step 2: If permalink structure is Plain, update it to standard /%postname%/ so /wp-json/ routes exist.
		if ( '' === $current_structure ) {
			global $wp_rewrite;
			update_option( 'permalink_structure', '/%postname%/' );
			if ( is_object( $wp_rewrite ) ) {
				$wp_rewrite->set_permalink_structure( '/%postname%/' );
			}
			$performed_changes[] = __( 'Configured permalink structure to standard "/%postname%/" to enable REST API URL routing.', 'wpcalibrate-rest-health-repair' );
		}

		// Step 3: Flush rewrite rules.
		flush_rewrite_rules( false );
		$performed_changes[] = __( 'Called flush_rewrite_rules(false).', 'wpcalibrate-rest-health-repair' );

		return new RepairResult(
			$this->get_id(),
			RepairResult::STATUS_FIXED,
			__( 'Flushed WordPress rewrite rules successfully.', 'wpcalibrate-rest-health-repair' ),
			$this->get_planned_changes(),
			$performed_changes,
			false, // Verification will be conducted by RepairRunner.
			true,
			array(
				'had_prior_rules' => ! empty( $current_rules ),
				'was_plain'       => '' === $current_structure,
			)
		);
	}

	public function can_rollback(): bool {
		return false !== get_option( self::BACKUP_OPTION_KEY, false )
			|| false !== get_option( self::BACKUP_PERMALINK_OPTION_KEY, false );
	}

	public function rollback(): RepairResult {
		$backup_rules     = get_option( self::BACKUP_OPTION_KEY, null );
		$backup_permalink = get_option( self::BACKUP_PERMALINK_OPTION_KEY, null );

		if ( null === $backup_rules && null === $backup_permalink ) {
			return new RepairResult(
				$this->get_id(),
				RepairResult::STATUS_FAILED,
				__( 'No previous rewrite rules or permalink backup found to restore.', 'wpcalibrate-rest-health-repair' )
			);
		}

		$revert_actions = array();

		// Restore permalink structure if backed up.
		if ( null !== $backup_permalink ) {
			global $wp_rewrite;
			update_option( 'permalink_structure', $backup_permalink );
			if ( is_object( $wp_rewrite ) ) {
				$wp_rewrite->set_permalink_structure( $backup_permalink );
			}
			delete_option( self::BACKUP_PERMALINK_OPTION_KEY );
			$revert_actions[] = __( 'Restored prior permalink structure from backup.', 'wpcalibrate-rest-health-repair' );
		}

		// Restore rewrite rules.
		if ( null !== $backup_rules ) {
			update_option( 'rewrite_rules', $backup_rules );
			delete_option( self::BACKUP_OPTION_KEY );
			$revert_actions[] = __( 'Restored prior rewrite rules from backup.', 'wpcalibrate-rest-health-repair' );
		}

		flush_rewrite_rules( false );

		return new RepairResult(
			$this->get_id(),
			RepairResult::STATUS_ROLLED_BACK,
			__( 'Restored prior rewrite rules and permalink settings from backup successfully.', 'wpcalibrate-rest-health-repair' ),
			array( __( 'Revert rewrite_rules and permalink_structure options to pre-repair state.', 'wpcalibrate-rest-health-repair' ) ),
			$revert_actions,
			true,
			false
		);
	}
}

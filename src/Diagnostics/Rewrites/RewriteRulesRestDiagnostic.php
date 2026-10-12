<?php
/**
 * Diagnostic for checking REST API rewrite rules registration in WordPress.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics\Rewrites;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RewriteRulesRestDiagnostic class.
 */
final class RewriteRulesRestDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'rewrite_rules_rest';
	}

	public function get_group(): string {
		return 'rewrites';
	}

	public function get_title(): string {
		return __( 'WordPress REST API Rewrite Rules', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Inspects the active WordPress rewrite rules table to verify that the core REST API rule (^wp-json/) is registered.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return '' !== (string) get_option( 'permalink_structure', '' );
	}

	public function get_repair_id(): ?string {
		return 'flush_rewrite_rules';
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_CRITICAL;
	}

	public function run(): DiagnosticResult {
		$start_time = microtime( true );

		global $wp_rewrite;
		$rules = array();

		if ( isset( $wp_rewrite ) && method_exists( $wp_rewrite, 'wp_rewrite_rules' ) ) {
			$rules = (array) $wp_rewrite->wp_rewrite_rules();
		}

		if ( empty( $rules ) ) {
			$rules = (array) get_option( 'rewrite_rules', array() );
		}

		$rest_rule_found = false;
		$rest_rule_key   = null;
		$rest_rule_val   = null;

		foreach ( $rules as $pattern => $query ) {
			if ( str_contains( (string) $pattern, 'wp-json' ) ) {
				$rest_rule_found = true;
				$rest_rule_key   = (string) $pattern;
				$rest_rule_val   = (string) $query;
				break;
			}
		}

		$evidence = array(
			'total_rules_count' => count( $rules ),
			'rest_rule_found'   => $rest_rule_found,
			'matched_pattern'   => $rest_rule_key,
			'matched_query'     => $rest_rule_val,
		);

		if ( ! $rest_rule_found ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				__( 'The core REST API rewrite rule (^wp-json/) is missing from WordPress rewrite rules.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(
					__( 'Rewrite rules have not been flushed following WordPress update or plugin activation.', 'wpcalibrate-rest-health-repair' ),
					__( 'Corrupted rewrite_rules option in the database.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'All requests to /wp-json/ endpoints return 404 Not Found error.', 'wpcalibrate-rest-health-repair' ),
				__( 'Flush WordPress rewrite rules to regenerate core route mappings.', 'wpcalibrate-rest-health-repair' ),
				'flush_rewrite_rules',
				__( 'Use the automatic "Flush Rewrite Rules" repair action to regenerate missing rewrite rules.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				( microtime( true ) - $start_time ) * 1000
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			__( 'Core REST API rewrite rule is correctly registered in the WordPress rewrite rules table.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'Routing table is valid.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			( microtime( true ) - $start_time ) * 1000
		);
	}
}

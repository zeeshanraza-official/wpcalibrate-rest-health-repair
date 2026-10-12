<?php
/**
 * Diagnostic for checking WordPress permalink structure.
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
 * PermalinkStructureDiagnostic class.
 */
final class PermalinkStructureDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'permalink_structure';
	}

	public function get_group(): string {
		return 'rewrites';
	}

	public function get_title(): string {
		return __( 'WordPress Permalink Structure', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Verifies that pretty permalinks are enabled to allow standard /wp-json/ REST URL routing.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return true;
	}

	public function get_repair_id(): ?string {
		return null;
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_WARNING;
	}

	public function run(): DiagnosticResult {
		$start_time = microtime( true );
		$structure  = (string) get_option( 'permalink_structure', '' );

		$evidence = array(
			'permalink_structure'    => '' === $structure ? 'Plain (empty)' : $structure,
			'has_pretty_permalinks' => '' !== $structure,
		);

		if ( '' === $structure ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_WARNING,
				__( 'Permalinks are set to Plain (?p=123). Standard /wp-json/ URLs are disabled.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(
					__( 'WordPress permalinks configured to default Plain format in Settings > Permalinks.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'Standard /wp-json/ URLs will return 404; REST API must be accessed via ?rest_route=/ query parameter fallback. WooCommerce Store API and Gutenberg block editor prefer pretty permalinks.', 'wpcalibrate-rest-health-repair' ),
				__( 'Select "Post name" or another pretty permalink structure in Settings > Permalinks.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( 'Go to Dashboard > Settings > Permalinks, select "Post name", and click "Save Changes".', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_WARNING,
				time(),
				( microtime( true ) - $start_time ) * 1000
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			sprintf(
				/* translators: %s: Permalink structure */
				__( 'Pretty permalinks are enabled (%s), supporting standard /wp-json/ REST routing.', 'wpcalibrate-rest-health-repair' ),
				$structure
			),
			$evidence,
			array(),
			__( 'Pretty permalinks operate normally.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			( microtime( true ) - $start_time ) * 1000
		);
	}
}

<?php
/**
 * Diagnostic runner and execution manager.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics;

use Throwable;
use WPCalibrate\RESTHealthRepair\Logging\Logger;
use WPCalibrate\RESTHealthRepair\Support\Capability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DiagnosticRunner class.
 */
final class DiagnosticRunner {

	public const CACHE_TRANSIENT_KEY = 'wpcalibrate_rhr_diag_results';
	public const SUMMARY_OPTION_KEY  = 'wpcalibrate_rhr_diag_summary';
	public const CACHE_TTL            = 300; // 5 minutes.

	private DiagnosticRegistry $registry;

	/**
	 * Constructor.
	 *
	 * @param DiagnosticRegistry $registry Diagnostic registry.
	 */
	public function __construct( DiagnosticRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Runs a single diagnostic by ID.
	 *
	 * @param string $id Diagnostic ID.
	 * @return DiagnosticResult
	 */
	public function run_one( string $id ): DiagnosticResult {
		Capability::check_or_die();

		$diagnostic = $this->registry->get( $id );
		if ( null === $diagnostic ) {
			return new DiagnosticResult(
				$id,
				DiagnosticResult::STATUS_UNABLE_TO_TEST,
				sprintf(
					/* translators: %s: Diagnostic ID */
					__( 'Diagnostic "%s" is not registered in the system.', 'wpcalibrate-rest-health-repair' ),
					$id
				),
				array(),
				array(),
				'',
				'',
				null,
				null,
				DiagnosticResult::SEVERITY_WARNING
			);
		}

		if ( ! $diagnostic->is_applicable() ) {
			return new DiagnosticResult(
				$id,
				DiagnosticResult::STATUS_NOT_APPLICABLE,
				__( 'This diagnostic is not applicable in the current environment.', 'wpcalibrate-rest-health-repair' ),
				array(),
				array(),
				'',
				'',
				null,
				null,
				DiagnosticResult::SEVERITY_INFO
			);
		}

		$start_time = microtime( true );
		try {
			$result = $diagnostic->run();
		} catch ( Throwable $e ) {
			$elapsed = ( microtime( true ) - $start_time ) * 1000;
			Logger::error(
				sprintf( 'Exception in diagnostic "%s": %s', $id, $e->getMessage() ),
				array( 'trace' => $e->getTraceAsString() )
			);
			$result = new DiagnosticResult(
				$id,
				DiagnosticResult::STATUS_FAILED,
				sprintf(
					/* translators: %s: Error message */
					__( 'Diagnostic encountered an unhandled error: %s', 'wpcalibrate-rest-health-repair' ),
					$e->getMessage()
				),
				array( 'exception' => get_class( $e ) ),
				array( __( 'Internal PHP runtime error or incompatible environment.', 'wpcalibrate-rest-health-repair' ) ),
				__( 'Diagnostic was unable to finish execution cleanly.', 'wpcalibrate-rest-health-repair' ),
				__( 'Review server error logs or contact support.', 'wpcalibrate-rest-health-repair' ),
				null,
				null,
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				$elapsed
			);
		}

		// Update cached entry.
		$cached = $this->get_cached_results();
		if ( is_array( $cached ) ) {
			$cached[ $id ] = $result->to_array();
			set_transient( self::CACHE_TRANSIENT_KEY, $cached, self::CACHE_TTL );
			$this->update_summary( $cached );
		}

		return $result;
	}

	/**
	 * Runs all registered diagnostics.
	 *
	 * @param bool $force_refresh Whether to bypass cache.
	 * @return array<string, array<string, mixed>> Results map.
	 */
	public function run_all( bool $force_refresh = true ): array {
		Capability::check_or_die();

		if ( ! $force_refresh ) {
			$cached = $this->get_cached_results();
			if ( ! empty( $cached ) ) {
				return $cached;
			}
		}

		$results = array();
		foreach ( $this->registry->all() as $id => $diagnostic ) {
			if ( ! $diagnostic->is_applicable() ) {
				$res = new DiagnosticResult(
					$id,
					DiagnosticResult::STATUS_NOT_APPLICABLE,
					__( 'Not applicable in this environment.', 'wpcalibrate-rest-health-repair' ),
					array(),
					array(),
					'',
					'',
					null,
					null,
					DiagnosticResult::SEVERITY_INFO
				);
				$results[ $id ] = $res->to_array();
				continue;
			}

			$start_time = microtime( true );
			try {
				$res = $diagnostic->run();
			} catch ( Throwable $e ) {
				$elapsed = ( microtime( true ) - $start_time ) * 1000;
				Logger::error( sprintf( 'Diagnostic exception %s: %s', $id, $e->getMessage() ) );
				$res = new DiagnosticResult(
					$id,
					DiagnosticResult::STATUS_FAILED,
					$e->getMessage(),
					array( 'exception' => get_class( $e ) ),
					array(),
					'',
					'',
					null,
					null,
					DiagnosticResult::SEVERITY_CRITICAL,
					time(),
					$elapsed
				);
			}

			$results[ $id ] = $res->to_array();
		}

		set_transient( self::CACHE_TRANSIENT_KEY, $results, self::CACHE_TTL );
		$this->update_summary( $results );

		return $results;
	}

	/**
	 * Re-runs only failed and warning diagnostics.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function rerun_failed_and_warnings(): array {
		Capability::check_or_die();
		$cached = $this->get_cached_results() ?? array();

		foreach ( $this->registry->all() as $id => $diagnostic ) {
			$current_status = $cached[ $id ]['status'] ?? null;
			if ( in_array( $current_status, array( DiagnosticResult::STATUS_FAILED, DiagnosticResult::STATUS_WARNING, null ), true ) ) {
				$res            = $this->run_one( $id );
				$cached[ $id ] = $res->to_array();
			}
		}

		set_transient( self::CACHE_TRANSIENT_KEY, $cached, self::CACHE_TTL );
		$this->update_summary( $cached );

		return $cached;
	}

	/**
	 * Gets cached diagnostic results or null.
	 *
	 * @return array<string, array<string, mixed>>|null
	 */
	public function get_cached_results(): ?array {
		$cached = get_transient( self::CACHE_TRANSIENT_KEY );
		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Invalidates diagnostic cache.
	 *
	 * @return void
	 */
	public function invalidate_cache(): void {
		delete_transient( self::CACHE_TRANSIENT_KEY );
	}

	/**
	 * Computes summary counts by status.
	 *
	 * @param array<string, array<string, mixed>> $results Results array.
	 * @return array{healthy: int, warning: int, failed: int, not_applicable: int, skipped: int, unable_to_test: int, total: int, last_scan: int}
	 */
	public function compute_summary( array $results ): array {
		$counts = array(
			'healthy'        => 0,
			'warning'        => 0,
			'failed'         => 0,
			'not_applicable' => 0,
			'skipped'        => 0,
			'unable_to_test' => 0,
			'total'          => count( $results ),
			'last_scan'      => time(),
		);

		foreach ( $results as $item ) {
			$st = $item['status'] ?? 'unknown';
			if ( isset( $counts[ $st ] ) ) {
				++$counts[ $st ];
			}
		}

		return $counts;
	}

	/**
	 * Updates and stores the diagnostic summary option.
	 *
	 * @param array<string, array<string, mixed>> $results Results array.
	 * @return void
	 */
	private function update_summary( array $results ): void {
		$summary = $this->compute_summary( $results );
		update_option( self::SUMMARY_OPTION_KEY, $summary, false );
	}

	/**
	 * Gets stored summary statistics.
	 *
	 * @return array{healthy: int, warning: int, failed: int, not_applicable: int, skipped: int, unable_to_test: int, total: int, last_scan: int}
	 */
	public function get_stored_summary(): array {
		$summary = get_option( self::SUMMARY_OPTION_KEY, null );
		if ( is_array( $summary ) && isset( $summary['total'] ) ) {
			return $summary;
		}

		return array(
			'healthy'        => 0,
			'warning'        => 0,
			'failed'         => 0,
			'not_applicable' => 0,
			'skipped'        => 0,
			'unable_to_test' => 0,
			'total'          => 0,
			'last_scan'      => 0,
		);
	}
}

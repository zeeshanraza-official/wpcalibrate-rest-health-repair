<?php
/**
 * Repair execution runner, locking, verification, and audit manager.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Repairs;

use Throwable;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticRunner;
use WPCalibrate\RESTHealthRepair\Logging\Logger;
use WPCalibrate\RESTHealthRepair\Support\Capability;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RepairRunner class.
 */
final class RepairRunner {

	public const LOCK_TRANSIENT_KEY  = 'wpcalibrate_rhr_repair_lock';
	public const AUDIT_OPTION_KEY    = 'wpcalibrate_rhr_repair_history';
	public const MAX_AUDIT_ENTRIES   = 20;

	private RepairRegistry $registry;
	private DiagnosticRunner $diagnostic_runner;

	/**
	 * Constructor.
	 *
	 * @param RepairRegistry   $registry Repair registry.
	 * @param DiagnosticRunner $diagnostic_runner Diagnostic runner.
	 */
	public function __construct( RepairRegistry $registry, DiagnosticRunner $diagnostic_runner ) {
		$this->registry          = $registry;
		$this->diagnostic_runner = $diagnostic_runner;
	}

	/**
	 * Executes a repair with locking, execution, post-repair diagnostic verification, and audit logging.
	 *
	 * @param string $repair_id Repair ID.
	 * @return RepairResult
	 */
	public function run( string $repair_id ): RepairResult {
		Capability::check_or_die();

		// Check for concurrent execution lock.
		if ( get_transient( self::LOCK_TRANSIENT_KEY ) ) {
			return new RepairResult(
				$repair_id,
				RepairResult::STATUS_FAILED,
				__( 'Another repair operation is currently running. Please wait a moment.', 'wpcalibrate-rest-health-repair' )
			);
		}

		$repair = $this->registry->get( $repair_id );
		if ( null === $repair ) {
			return new RepairResult(
				$repair_id,
				RepairResult::STATUS_FAILED,
				sprintf(
					/* translators: %s: Repair ID */
					__( 'Repair "%s" is not registered in the system.', 'wpcalibrate-rest-health-repair' ),
					$repair_id
				)
			);
		}

		if ( ! $repair->is_applicable() ) {
			$reason = method_exists( $repair, 'get_inapplicability_reason' )
				? $repair->get_inapplicability_reason()
				: '';
			if ( '' === $reason ) {
				$reason = __( 'This repair is not applicable to the current environment.', 'wpcalibrate-rest-health-repair' );
			}
			return new RepairResult(
				$repair_id,
				RepairResult::STATUS_FAILED,
				$reason
			);
		}

		// Acquire 60-second lock.
		set_transient( self::LOCK_TRANSIENT_KEY, $repair_id, 60 );

		try {
			// Step 1: Execute repair.
			$exec_result = $repair->execute();

			// Invalidate diagnostic cache immediately.
			$this->diagnostic_runner->invalidate_cache();

			// Step 2: Post-repair verification. Re-run affected diagnostics.
			$related_ids     = $repair->get_related_diagnostic_ids();
			$all_passed      = true;
			$verification_msg = array();

			foreach ( $related_ids as $diag_id ) {
				$check_result = $this->diagnostic_runner->run_one( $diag_id );
				if ( $check_result->is_failed() || $check_result->is_warning() ) {
					$all_passed        = false;
					$verification_msg[] = sprintf( '%s: %s', $diag_id, $check_result->get_message() );
				}
			}

			// Step 3: Determine final outcome.
			if ( $all_passed ) {
				$final_result = new RepairResult(
					$repair_id,
					RepairResult::STATUS_FIXED,
					sprintf(
						/* translators: %s: Repair title */
						__( 'Repair "%s" completed successfully and verified. All related diagnostic tests passed.', 'wpcalibrate-rest-health-repair' ),
						$repair->get_title()
					),
					$exec_result->get_planned_changes(),
					$exec_result->get_performed_changes(),
					true,
					$repair->can_rollback(),
					array( 'verified' => true )
				);
				Logger::info( sprintf( 'Repair "%s" executed and verified fixed.', $repair_id ) );
			} else {
				$final_result = new RepairResult(
					$repair_id,
					RepairResult::STATUS_NOT_RESOLVED,
					sprintf(
						/* translators: 1: Repair title, 2: Verification details */
						__( 'Repair "%1$s" executed, but diagnostic verification did not pass: %2$s', 'wpcalibrate-rest-health-repair' ),
						$repair->get_title(),
						implode( '; ', $verification_msg )
					),
					$exec_result->get_planned_changes(),
					$exec_result->get_performed_changes(),
					false,
					$repair->can_rollback(),
					array( 'verification_failures' => $verification_msg )
				);
				Logger::warning( sprintf( 'Repair "%s" executed but verification failed: %s', $repair_id, implode( '; ', $verification_msg ) ) );
			}
		} catch ( Throwable $e ) {
			Logger::error( sprintf( 'Exception during repair "%s": %s', $repair_id, $e->getMessage() ) );
			$final_result = new RepairResult(
				$repair_id,
				RepairResult::STATUS_FAILED,
				sprintf(
					/* translators: %s: Error message */
					__( 'Repair failed with exception: %s', 'wpcalibrate-rest-health-repair' ),
					$e->getMessage()
				),
				array(),
				array(),
				false,
				false
			);
		} finally {
			delete_transient( self::LOCK_TRANSIENT_KEY );
		}

		$this->record_audit_entry( $final_result );
		return $final_result;
	}

	/**
	 * Rolls back a repair if supported.
	 *
	 * @param string $repair_id Repair ID.
	 * @return RepairResult
	 */
	public function rollback( string $repair_id ): RepairResult {
		Capability::check_or_die();

		$repair = $this->registry->get( $repair_id );
		if ( null === $repair || ! $repair->can_rollback() ) {
			return new RepairResult(
				$repair_id,
				RepairResult::STATUS_FAILED,
				__( 'Rollback is not available for this repair.', 'wpcalibrate-rest-health-repair' )
			);
		}

		try {
			$result = $repair->rollback();
			$this->diagnostic_runner->invalidate_cache();
			$this->record_audit_entry( $result );
			Logger::info( sprintf( 'Rollback executed for repair "%s".', $repair_id ) );
			return $result;
		} catch ( Throwable $e ) {
			Logger::error( sprintf( 'Rollback error for "%s": %s', $repair_id, $e->getMessage() ) );
			return new RepairResult(
				$repair_id,
				RepairResult::STATUS_FAILED,
				sprintf(
					/* translators: %s: Error message */
					__( 'Rollback failed: %s', 'wpcalibrate-rest-health-repair' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Records an entry into the audit log history.
	 *
	 * @param RepairResult $result Result.
	 * @return void
	 */
	private function record_audit_entry( RepairResult $result ): void {
		$history = (array) get_option( self::AUDIT_OPTION_KEY, array() );
		$entry   = $result->to_array();

		array_unshift( $history, $entry );
		if ( count( $history ) > self::MAX_AUDIT_ENTRIES ) {
			$history = array_slice( $history, 0, self::MAX_AUDIT_ENTRIES );
		}

		update_option( self::AUDIT_OPTION_KEY, $history, false );
	}

	/**
	 * Gets stored repair audit history.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_audit_history(): array {
		$history = get_option( self::AUDIT_OPTION_KEY, array() );
		return is_array( $history ) ? $history : array();
	}
}

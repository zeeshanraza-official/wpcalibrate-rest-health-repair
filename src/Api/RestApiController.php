<?php
/**
 * REST API controller for WPCalibrate REST Health & Repair.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Api;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticRegistry;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticRunner;
use WPCalibrate\RESTHealthRepair\Repairs\RepairRegistry;
use WPCalibrate\RESTHealthRepair\Repairs\RepairRunner;
use WPCalibrate\RESTHealthRepair\Support\Capability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RestApiController class.
 */
final class RestApiController extends WP_REST_Controller {

	public const REST_NAMESPACE = 'wpcalibrate-rest-health-repair/v1';

	private DiagnosticRegistry $diagnostic_registry;
	private DiagnosticRunner $diagnostic_runner;
	private RepairRegistry $repair_registry;
	private RepairRunner $repair_runner;

	/**
	 * Constructor.
	 */
	public function __construct(
		DiagnosticRegistry $diagnostic_registry,
		DiagnosticRunner $diagnostic_runner,
		RepairRegistry $repair_registry,
		RepairRunner $repair_runner
	) {
		$this->diagnostic_registry = $diagnostic_registry;
		$this->diagnostic_runner   = $diagnostic_runner;
		$this->repair_registry     = $repair_registry;
		$this->repair_runner       = $repair_runner;
		$this->namespace           = self::REST_NAMESPACE;
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /status
		register_rest_route(
			$this->namespace,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		// POST /diagnostics/run
		register_rest_route(
			$this->namespace,
			'/diagnostics/run',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_diagnostics' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'diagnostic_id' => array(
						'description'       => 'Optional single diagnostic ID to execute.',
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		// POST /diagnostics/rerun-failed
		register_rest_route(
			$this->namespace,
			'/diagnostics/rerun-failed',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rerun_failed' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		// POST /repairs/execute
		register_rest_route(
			$this->namespace,
			'/repairs/execute',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'execute_repair' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'repair_id' => array(
						'description'       => 'Repair identifier to execute.',
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		// POST /repairs/rollback
		register_rest_route(
			$this->namespace,
			'/repairs/rollback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rollback_repair' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'repair_id' => array(
						'description'       => 'Repair identifier to rollback.',
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Permission check: strictly requires manage_options.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function permissions_check( WP_REST_Request $request ): bool|WP_Error {
		if ( ! Capability::current_user_can() ) {
			return new WP_Error(
				'wpcalibrate_rhr_forbidden',
				__( 'You do not have permission to access diagnostic data.', 'wpcalibrate-rest-health-repair' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * GET /status endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		$summary = $this->diagnostic_runner->get_stored_summary();
		$cached  = $this->diagnostic_runner->get_cached_results();

		return new WP_REST_Response(
			array(
				'summary'   => $summary,
				'has_cache' => ! empty( $cached ),
			),
			200
		);
	}

	/**
	 * POST /diagnostics/run endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function run_diagnostics( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$diag_id = $request->get_param( 'diagnostic_id' );

		if ( ! empty( $diag_id ) ) {
			if ( ! $this->diagnostic_registry->has( $diag_id ) ) {
				return new WP_Error(
					'wpcalibrate_rhr_invalid_diagnostic',
					__( 'Specified diagnostic ID does not exist.', 'wpcalibrate-rest-health-repair' ),
					array( 'status' => 400 )
				);
			}

			$result  = $this->diagnostic_runner->run_one( $diag_id );
			$summary = $this->diagnostic_runner->get_stored_summary();

			return new WP_REST_Response(
				array(
					'single'  => true,
					'result'  => $result->to_array(),
					'summary' => $summary,
				),
				200
			);
		}

		$results = $this->diagnostic_runner->run_all( true );
		$summary = $this->diagnostic_runner->get_stored_summary();

		return new WP_REST_Response(
			array(
				'single'  => false,
				'results' => $results,
				'summary' => $summary,
			),
			200
		);
	}

	/**
	 * POST /diagnostics/rerun-failed endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rerun_failed( WP_REST_Request $request ): WP_REST_Response {
		$results = $this->diagnostic_runner->rerun_failed_and_warnings();
		$summary = $this->diagnostic_runner->get_stored_summary();

		return new WP_REST_Response(
			array(
				'results' => $results,
				'summary' => $summary,
			),
			200
		);
	}

	/**
	 * POST /repairs/execute endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function execute_repair( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$repair_id = $request->get_param( 'repair_id' );

		if ( empty( $repair_id ) || ! $this->repair_registry->has( $repair_id ) ) {
			return new WP_Error(
				'wpcalibrate_rhr_invalid_repair',
				__( 'Specified repair action does not exist.', 'wpcalibrate-rest-health-repair' ),
				array( 'status' => 400 )
			);
		}

		$result = $this->repair_runner->run( $repair_id );

		return new WP_REST_Response(
			array(
				'repair'  => $result->to_array(),
				'summary' => $this->diagnostic_runner->get_stored_summary(),
			),
			200
		);
	}

	/**
	 * POST /repairs/rollback endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rollback_repair( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$repair_id = $request->get_param( 'repair_id' );

		if ( empty( $repair_id ) || ! $this->repair_registry->has( $repair_id ) ) {
			return new WP_Error(
				'wpcalibrate_rhr_invalid_repair',
				__( 'Specified repair action does not exist.', 'wpcalibrate-rest-health-repair' ),
				array( 'status' => 400 )
			);
		}

		$result = $this->repair_runner->rollback( $repair_id );

		return new WP_REST_Response(
			array(
				'rollback' => $result->to_array(),
				'summary'  => $this->diagnostic_runner->get_stored_summary(),
			),
			200
		);
	}
}

<?php
/**
 * Diagnostic for checking WooCommerce REST API namespaces registration.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics\WooCommerce;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticResult;
use WPCalibrate\RESTHealthRepair\Support\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerceNamespacesDiagnostic class.
 */
final class WooCommerceNamespacesDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'wc_rest_namespaces';
	}

	public function get_group(): string {
		return 'woocommerce';
	}

	public function get_title(): string {
		return __( 'WooCommerce REST & Store API Namespaces Registration', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Inspects registered REST namespaces to verify that WooCommerce API routes (wc/v3, wc/v4, wc/store/v1) are properly loaded.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return Environment::is_woocommerce_active();
	}

	public function get_repair_id(): ?string {
		return null;
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_WARNING;
	}

	public function run(): DiagnosticResult {
		$start_time = microtime( true );

		if ( ! Environment::is_woocommerce_active() ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_NOT_APPLICABLE,
				__( 'WooCommerce is not active.', 'wpcalibrate-rest-health-repair' ),
				array(),
				array(),
				'',
				'',
				null,
				null,
				DiagnosticResult::SEVERITY_INFO
			);
		}

		$namespaces = array();
		if ( function_exists( 'rest_get_server' ) ) {
			$server = rest_get_server();
			if ( $server && method_exists( $server, 'get_namespaces' ) ) {
				$namespaces = (array) $server->get_namespaces();
			}
		}

		$has_wc_v3    = in_array( 'wc/v3', $namespaces, true );
		$has_wc_v4    = in_array( 'wc/v4', $namespaces, true );
		$has_store_v1 = in_array( 'wc/store/v1', $namespaces, true ) || in_array( 'wc/store', $namespaces, true );

		$evidence = array(
			'has_wc_v3'         => $has_wc_v3,
			'has_wc_v4'         => $has_wc_v4,
			'has_store_api_v1'  => $has_store_v1,
			'total_namespaces'  => count( $namespaces ),
		);

		if ( ! $has_wc_v3 && ! $has_wc_v4 && ! $has_store_v1 ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				__( 'WooCommerce is active, but no WooCommerce REST namespaces (wc/v3, wc/v4, wc/store/v1) are registered in the REST server.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(
					__( 'WooCommerce REST API controllers failed to load during rest_api_init.', 'wpcalibrate-rest-health-repair' ),
					__( 'A plugin or code snippet deregistered WooCommerce REST routes.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'External WooCommerce integrations, Mobile apps, POS systems, and Store API cart features fail to connect.', 'wpcalibrate-rest-health-repair' ),
				__( 'Distinction: WordPress REST API is running, but WooCommerce REST registration specifically has failed.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( 'Check WooCommerce > Status > Logs for fatal errors during rest_api_init.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				( microtime( true ) - $start_time ) * 1000
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			__( 'WooCommerce REST namespaces are properly registered in the REST API server.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'WooCommerce API endpoints are reachable.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			( microtime( true ) - $start_time ) * 1000
		);
	}
}

<?php
/**
 * Diagnostic for testing public WooCommerce Store API endpoint connectivity.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics\WooCommerce;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticResult;
use WPCalibrate\RESTHealthRepair\Support\Environment;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * StoreApiPublicDiagnostic class.
 */
final class StoreApiPublicDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'wc_store_api_public';
	}

	public function get_group(): string {
		return 'woocommerce';
	}

	public function get_title(): string {
		return __( 'WooCommerce Store API Public Connectivity (/wc/store/v1/products)', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Tests public Store API endpoint without requiring credentials to verify that customer-facing Cart and Checkout APIs operate smoothly.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return Environment::is_woocommerce_active();
	}

	public function get_repair_id(): ?string {
		return 'flush_rewrite_rules';
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_CRITICAL;
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

		$target_url = function_exists( 'rest_url' )
			? rest_url( 'wc/store/v1/products?per_page=1' )
			: home_url( '/wp-json/wc/store/v1/products?per_page=1' );

		$settings = (array) get_option( 'wpcalibrate_rhr_settings', array() );
		$timeout  = max( 3, min( 30, (int) ( $settings['http_timeout'] ?? 10 ) ) );

		$response = wp_remote_get(
			$target_url,
			array(
				'timeout'    => $timeout,
				'user-agent' => 'WPCalibrate-StoreAPI-Diagnostic/1.0',
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);
		$duration = ( microtime( true ) - $start_time ) * 1000;

		if ( is_wp_error( $response ) ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				sprintf(
					/* translators: %s: Error message */
					__( 'Store API request failed with transport error: %s', 'wpcalibrate-rest-health-repair' ),
					$response->get_error_message()
				),
				array(
					'url'        => $target_url,
					'error_code' => $response->get_error_code(),
				),
				array( __( 'Server loopback connectivity error or connection timeout.', 'wpcalibrate-rest-health-repair' ) ),
				__( 'Cart and Checkout blocks will fail to load products or cart contents.', 'wpcalibrate-rest-health-repair' ),
				__( 'Check loopback request diagnostic results.', 'wpcalibrate-rest-health-repair' ),
				null,
				null,
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				$duration
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = (string) wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );
		$json_data   = json_decode( $body, true );

		$nonce_header = wp_remote_retrieve_header( $response, 'nonce' );

		$evidence = array(
			'url'          => $target_url,
			'status_code'  => $status_code,
			'has_nonce'    => ! empty( $nonce_header ),
			'is_json'      => is_array( $json_data ),
			'body_snippet' => Sanitizer::sanitize_response_body_snippet( $body, 250 ),
			'duration_ms'  => round( $duration, 2 ),
		);

		if ( 401 === $status_code || 403 === $status_code ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				sprintf(
					/* translators: %d: Status code */
					__( 'Store API returned HTTP %d. Public shoppers cannot access the Store API.', 'wpcalibrate-rest-health-repair' ),
					$status_code
				),
				$evidence,
				array(
					__( 'A security or membership plugin is blocking unauthenticated access to the Store API namespace.', 'wpcalibrate-rest-health-repair' ),
					__( 'rest_authentication_errors filter rejecting non-logged-in store visitors.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'Block-based Cart and Checkout cannot calculate shipping, totals, or complete orders for guest shoppers.', 'wpcalibrate-rest-health-repair' ),
				__( 'Whitelist "wc/store/v1" in your security plugin\'s REST restrictions.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( 'Configure security plugin to allow public access to /wp-json/wc/store/ routes.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				$duration
			);
		}

		if ( 404 === $status_code ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				__( 'Store API returned HTTP 404 Not Found.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(
					__( 'Rewrite rules have not mapped WooCommerce Store API routes.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'Cart and Checkout blocks report "Store API unavailable".', 'wpcalibrate-rest-health-repair' ),
				__( 'Flush WordPress rewrite rules. On sites with Plain permalinks, running Auto Repair will configure standard "/%postname%/" routing to restore Store API endpoints.', 'wpcalibrate-rest-health-repair' ),
				'flush_rewrite_rules',
				null,
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				$duration
			);
		}

		if ( 200 !== $status_code || ! is_array( $json_data ) ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				sprintf(
					/* translators: %d: Status code */
					__( 'Store API returned unexpected HTTP status %d or non-JSON content.', 'wpcalibrate-rest-health-repair' ),
					$status_code
				),
				$evidence,
				array( __( 'PHP fatal error or gateway timeout during Store API execution.', 'wpcalibrate-rest-health-repair' ) ),
				__( 'WooCommerce modern checkout experience is broken.', 'wpcalibrate-rest-health-repair' ),
				__( 'Check WooCommerce logs for errors occurring during Store API requests.', 'wpcalibrate-rest-health-repair' ),
				null,
				null,
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				$duration
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			__( 'WooCommerce Store API is publicly accessible and responding with valid product data.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'Store API functions normally for guest and logged-in customers.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			$duration
		);
	}
}

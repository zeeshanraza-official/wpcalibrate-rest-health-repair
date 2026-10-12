<?php
/**
 * Diagnostic for checking WooCommerce Cart & Checkout Blocks integration and Store API dependencies.
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
 * StoreApiBlocksDiagnostic class.
 */
final class StoreApiBlocksDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'wc_store_api_blocks';
	}

	public function get_group(): string {
		return 'woocommerce';
	}

	public function get_title(): string {
		return __( 'WooCommerce Cart & Checkout Blocks Store API Dependency', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Inspects whether the active store uses modern block-based Cart and Checkout templates and verifies Store API readiness.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return Environment::is_woocommerce_active();
	}

	public function get_repair_id(): ?string {
		return null;
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_INFO;
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

		$cart_page_id     = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'cart' ) : 0;
		$checkout_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'checkout' ) : 0;

		$cart_post     = $cart_page_id > 0 ? get_post( $cart_page_id ) : null;
		$checkout_post = $checkout_page_id > 0 ? get_post( $checkout_page_id ) : null;

		$cart_has_block     = $cart_post && function_exists( 'has_block' ) && has_block( 'woocommerce/cart', $cart_post );
		$checkout_has_block = $checkout_post && function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $checkout_post );

		$evidence = array(
			'cart_page_id'       => $cart_page_id,
			'checkout_page_id'   => $checkout_page_id,
			'cart_uses_blocks'   => $cart_has_block,
			'checkout_uses_blocks'=> $checkout_has_block,
		);

		if ( $cart_has_block || $checkout_has_block ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_HEALTHY,
				__( 'Cart or Checkout pages use modern block-based templates. Store API readiness is critical for checkout.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(),
				__( 'Store relies directly on Store API for shopper checkout operations.', 'wpcalibrate-rest-health-repair' ),
				__( 'Ensure the "WooCommerce Store API Public Connectivity" diagnostic passes.', 'wpcalibrate-rest-health-repair' ),
				null,
				null,
				DiagnosticResult::SEVERITY_INFO,
				time(),
				( microtime( true ) - $start_time ) * 1000
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			__( 'Cart and Checkout pages use traditional shortcodes [woocommerce_cart] / [woocommerce_checkout]. Store API is still recommended for blocks and mobile apps.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'Traditional shortcode checkout is less sensitive to Store API interruptions, but REST API is still required for admin and integrations.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			( microtime( true ) - $start_time ) * 1000
		);
	}
}

<?php
/**
 * Diagnostic for checking WooCommerce installation, version, and HPOS mode.
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
 * WooCommerceActiveDiagnostic class.
 */
final class WooCommerceActiveDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'wc_plugin_active';
	}

	public function get_group(): string {
		return 'woocommerce';
	}

	public function get_title(): string {
		return __( 'WooCommerce Installation & HPOS Status', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Verifies that WooCommerce is active, detects the installed release version, and inspects High-Performance Order Storage (HPOS) status.', 'wpcalibrate-rest-health-repair' );
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
				__( 'WooCommerce is not installed or active on this site.', 'wpcalibrate-rest-health-repair' ),
				array( 'active' => false ),
				array(),
				'',
				__( 'No action required if this is not a WooCommerce store.', 'wpcalibrate-rest-health-repair' ),
				null,
				null,
				DiagnosticResult::SEVERITY_INFO,
				time(),
				( microtime( true ) - $start_time ) * 1000
			);
		}

		$version     = Environment::get_woocommerce_version() ?? 'Unknown';
		$hpos_status = Environment::get_woocommerce_hpos_status();

		$evidence = array(
			'active'      => true,
			'version'     => $version,
			'hpos_status' => $hpos_status,
		);

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			sprintf(
				/* translators: 1: WooCommerce version, 2: HPOS status */
				__( 'WooCommerce %1$s is active (HPOS: %2$s).', 'wpcalibrate-rest-health-repair' ),
				$version,
				$hpos_status
			),
			$evidence,
			array(),
			__( 'WooCommerce core is active and operational.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			( microtime( true ) - $start_time ) * 1000
		);
	}
}

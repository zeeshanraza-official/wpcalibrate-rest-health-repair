<?php
/**
 * Diagnostic report download and export controller.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Reports;

use WPCalibrate\RESTHealthRepair\Support\Capability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ReportExporter class.
 */
final class ReportExporter {

	private ReportBuilder $builder;

	/**
	 * Constructor.
	 *
	 * @param ReportBuilder $builder Report builder.
	 */
	public function __construct( ReportBuilder $builder ) {
		$this->builder = $builder;
	}

	/**
	 * Registers admin-post export hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_post_wpcalibrate_rhr_export_report', array( $this, 'handle_export' ) );
	}

	/**
	 * Handles HTTP report download request.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		Capability::check_or_die();
		check_admin_referer( 'wpcalibrate_rhr_export_report_nonce' );

		$format   = isset( $_GET['format'] ) && 'json' === sanitize_key( $_GET['format'] ) ? 'json' : 'txt';
		$filename = sprintf( 'wpcalibrate-rest-diagnostic-report-%s.%s', gmdate( 'Y-m-d-His' ), $format );

		if ( 'json' === $format ) {
			$content      = $this->builder->build_json_report();
			$content_type = 'application/json; charset=utf-8';
		} else {
			$content      = $this->builder->build_text_report();
			$content_type = 'text/plain; charset=utf-8';
		}

		// Prevent caching.
		nocache_headers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $content ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $content;
		exit;
	}
}

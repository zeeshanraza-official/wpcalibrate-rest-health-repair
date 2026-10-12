<?php
/**
 * Diagnostic report builder.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Reports;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticRunner;
use WPCalibrate\RESTHealthRepair\Repairs\RepairRunner;
use WPCalibrate\RESTHealthRepair\Support\Capability;
use WPCalibrate\RESTHealthRepair\Support\Environment;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ReportBuilder class.
 */
final class ReportBuilder {

	private DiagnosticRunner $diagnostic_runner;
	private RepairRunner $repair_runner;

	/**
	 * Constructor.
	 *
	 * @param DiagnosticRunner $diagnostic_runner Diagnostic runner.
	 * @param RepairRunner     $repair_runner Repair runner.
	 */
	public function __construct( DiagnosticRunner $diagnostic_runner, RepairRunner $repair_runner ) {
		$this->diagnostic_runner = $diagnostic_runner;
		$this->repair_runner     = $repair_runner;
	}

	/**
	 * Builds full sanitized report array.
	 *
	 * @return array<string, mixed>
	 */
	public function build_report_data(): array {
		Capability::check_or_die();

		$cached_results = $this->diagnostic_runner->get_cached_results();
		if ( empty( $cached_results ) ) {
			$cached_results = $this->diagnostic_runner->run_all( false );
		}

		$summary = $this->diagnostic_runner->compute_summary( $cached_results );

		$home_p = wp_parse_url( Environment::get_home_url() );
		$site_p = wp_parse_url( Environment::get_site_url() );

		$raw_data = array(
			'generated_at'   => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
			'plugin'         => array(
				'name'    => 'WPCalibrate REST Health & Repair',
				'version' => defined( 'WPCALIBRATE_RHR_VERSION' ) ? WPCALIBRATE_RHR_VERSION : '1.0.0',
				'author'  => 'WPCalibrate',
				'website' => 'https://wpcalibrate.com',
			),
			'environment'    => array(
				'wordpress_version'   => Environment::get_wordpress_version(),
				'php_version'         => Environment::get_php_version(),
				'web_server'          => Environment::get_web_server(),
				'database_version'    => Environment::get_database_version(),
				'is_ssl'              => Environment::is_ssl(),
				'home_url_scheme'     => $home_p['scheme'] ?? 'unknown',
				'home_url_host'       => $home_p['host'] ?? 'unknown',
				'site_url_scheme'     => $site_p['scheme'] ?? 'unknown',
				'site_url_host'       => $site_p['host'] ?? 'unknown',
				'permalink_structure' => Environment::get_permalink_structure(),
				'is_multisite'        => Environment::is_multisite(),
			),
			'woocommerce'    => array(
				'is_active'   => Environment::is_woocommerce_active(),
				'version'     => Environment::get_woocommerce_version() ?? 'N/A',
				'hpos_status' => Environment::get_woocommerce_hpos_status(),
			),
			'diagnostics_summary' => $summary,
			'diagnostics'         => $cached_results,
			'repair_history'      => $this->repair_runner->get_audit_history(),
			'active_theme'        => Environment::get_active_theme_info(),
			'active_plugins'      => Environment::get_active_plugins_list(),
			'settings'            => $this->get_safe_settings(),
		);

		return ReportSanitizer::sanitize_report_data( $raw_data );
	}

	/**
	 * Generates human-readable Markdown/Text report.
	 *
	 * @return string
	 */
	public function build_text_report(): string {
		$data = $this->build_report_data();

		$out = array();
		$out[] = '=======================================================';
		$out[] = '  WPCalibrate REST Health & Repair - Diagnostic Report';
		$out[] = '=======================================================';
		$out[] = sprintf( 'Generated At: %s', $data['generated_at'] );
		$out[] = sprintf( 'Plugin Version: %s', $data['plugin']['version'] );
		$out[] = '';

		$out[] = '--- [1] Environment & Server ---';
		$out[] = sprintf( 'WordPress:           %s', $data['environment']['wordpress_version'] );
		$out[] = sprintf( 'PHP Version:         %s', $data['environment']['php_version'] );
		$out[] = sprintf( 'Web Server:          %s', $data['environment']['web_server'] );
		$out[] = sprintf( 'Database:            %s', $data['environment']['database_version'] );
		$out[] = sprintf( 'SSL Active:          %s', $data['environment']['is_ssl'] ? 'Yes' : 'No' );
		$out[] = sprintf( 'Home URL:            %s://%s', $data['environment']['home_url_scheme'], $data['environment']['home_url_host'] );
		$out[] = sprintf( 'Site URL:            %s://%s', $data['environment']['site_url_scheme'], $data['environment']['site_url_host'] );
		$out[] = sprintf( 'Permalink Structure: %s', $data['environment']['permalink_structure'] ?: 'Plain (Default)' );
		$out[] = sprintf( 'Multisite:           %s', $data['environment']['is_multisite'] ? 'Yes' : 'No' );
		$out[] = '';

		$out[] = '--- [2] WooCommerce ---';
		$out[] = sprintf( 'Active:      %s', $data['woocommerce']['is_active'] ? 'Yes' : 'No' );
		$out[] = sprintf( 'Version:     %s', $data['woocommerce']['version'] );
		$out[] = sprintf( 'HPOS Status: %s', $data['woocommerce']['hpos_status'] );
		$out[] = '';

		$out[] = '--- [3] Diagnostics Summary ---';
		$s     = $data['diagnostics_summary'];
		$out[] = sprintf( 'Healthy: %d | Warnings: %d | Failed: %d | Not Applicable: %d | Total: %d', $s['healthy'], $s['warning'], $s['failed'], $s['not_applicable'], $s['total'] );
		$out[] = '';

		$out[] = '--- [4] Detailed Diagnostic Results ---';
		foreach ( (array) $data['diagnostics'] as $id => $diag ) {
			$status_label = strtoupper( (string) ( $diag['status'] ?? 'UNKNOWN' ) );
			$out[] = sprintf( '[%s] %s', $status_label, $id );
			$out[] = sprintf( '  Message:  %s', $diag['message'] ?? '' );
			if ( ! empty( $diag['likely_causes'] ) ) {
				$out[] = '  Causes:';
				foreach ( (array) $diag['likely_causes'] as $cause ) {
					$out[] = sprintf( '    * %s', $cause );
				}
			}
			if ( ! empty( $diag['recommended_action'] ) ) {
				$out[] = sprintf( '  Action:   %s', $diag['recommended_action'] );
			}
			if ( ! empty( $diag['evidence'] ) ) {
				$out[] = sprintf( '  Evidence: %s', wp_json_encode( $diag['evidence'] ) );
			}
			$out[] = '';
		}

		$out[] = '--- [5] Active Theme ---';
		$theme = $data['active_theme'];
		$out[] = sprintf( '%s (Version %s)%s', $theme['name'], $theme['version'], $theme['is_child'] ? ' [Child Theme]' : '' );
		$out[] = '';

		$out[] = '--- [6] Active Plugins ---';
		foreach ( (array) $data['active_plugins'] as $name => $ver ) {
			$out[] = sprintf( '* %s (v%s)', $name, $ver );
		}
		$out[] = '';

		$out[] = '--- [7] Recent Repair History ---';
		if ( empty( $data['repair_history'] ) ) {
			$out[] = 'No repair actions recorded.';
		} else {
			foreach ( (array) $data['repair_history'] as $rep ) {
				$out[] = sprintf( '* [%s] %s: %s (Time: %s)', strtoupper( (string) ( $rep['status'] ?? '' ) ), $rep['repair_id'] ?? '', $rep['message'] ?? '', gmdate( 'Y-m-d H:i:s', (int) ( $rep['timestamp'] ?? time() ) ) );
			}
		}
		$out[] = '';
		$out[] = '=======================================================';
		$out[] = '  End of WPCalibrate Report (support@wpcalibrate.com)';
		$out[] = '=======================================================';

		return implode( "\n", $out );
	}

	/**
	 * Generates JSON string report.
	 *
	 * @return string
	 */
	public function build_json_report(): string {
		$data = $this->build_report_data();
		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Gets safe plugin configuration.
	 *
	 * @return array<string, mixed>
	 */
	private function get_safe_settings(): array {
		$settings = (array) get_option( 'wpcalibrate_rhr_settings', array() );
		// Redact any secrets if present.
		return array(
			'http_timeout'    => (int) ( $settings['http_timeout'] ?? 10 ),
			'logging_enabled' => ! empty( $settings['logging_enabled'] ),
			'retention_days'  => (int) ( $settings['retention_days'] ?? 7 ),
			'delete_on_uninstall' => ! empty( $settings['delete_on_uninstall'] ),
			'advanced_details'    => ! empty( $settings['advanced_details'] ),
		);
	}
}

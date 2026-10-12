<?php
/**
 * Diagnostic for identifying active security and firewall plugins that may manage REST access policies.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics\Security;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticResult;
use WPCalibrate\RESTHealthRepair\Support\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RestAccessRestrictionsDiagnostic class.
 */
final class RestAccessRestrictionsDiagnostic implements DiagnosticInterface {

	/**
	 * Known plugins that include REST restriction or firewall features.
	 *
	 * @var array<string, string>
	 */
	private const KNOWN_SECURITY_PLUGINS = array(
		'wordfence/wordfence.php'                          => 'Wordfence Security',
		'better-wp-security/better-wp-security.php'        => 'Solid Security (formerly iThemes)',
		'all-in-one-wp-security-and-firewall/all-in-one-wp-security-and-firewall.php' => 'All In One WP Security',
		'disable-json-api/disable-json-api.php'            => 'Disable WP REST API',
		'wp-rest-api-controller/wp-rest-api-controller.php'=> 'WP REST API Controller',
		'perfmatters/perfmatters.php'                      => 'Perfmatters (Disable REST API module)',
		'clearfy/clearfy.php'                              => 'Clearfy',
		'sucuri-scanner/sucuri.php'                        => 'Sucuri Security',
	);

	public function get_id(): string {
		return 'rest_access_restrictions';
	}

	public function get_group(): string {
		return 'security';
	}

	public function get_title(): string {
		return __( 'Active Security & REST Restriction Plugins Analysis', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Scans active plugins to identify security, optimization, or privacy tools that may be enforcing REST API access restrictions.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return true;
	}

	public function get_repair_id(): ?string {
		return null; // Safety rule: Never deactivate plugins automatically.
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_INFO;
	}

	public function run(): DiagnosticResult {
		$start_time     = microtime( true );
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$detected = array();
		foreach ( self::KNOWN_SECURITY_PLUGINS as $plugin_file => $plugin_name ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				$detected[ $plugin_name ] = $plugin_file;
			}
		}

		$evidence = array(
			'detected_security_plugins' => $detected,
			'count'                     => count( $detected ),
		);

		if ( ! empty( $detected ) ) {
			$names = implode( ', ', array_keys( $detected ) );
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_WARNING,
				sprintf(
					/* translators: %s: Comma separated plugin names */
					__( 'Identified %d active plugin(s) with REST restriction or firewall capabilities: %s', 'wpcalibrate-rest-health-repair' ),
					count( $detected ),
					$names
				),
				$evidence,
				array(
					__( 'One of these plugins may be configured to require authentication or block certain REST endpoints.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'Informational alert for troubleshooting conflict investigations.', 'wpcalibrate-rest-health-repair' ),
				__( 'If experiencing REST 401/403 errors, check the REST API settings inside these plugins.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( 'Conflict Testing Guide: 1. Clone to staging. 2. Temporarily test with the security plugin\'s "Disable REST" setting toggled off. 3. Ensure public routes (like WooCommerce Store API) are whitelisted.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_INFO,
				time(),
				( microtime( true ) - $start_time ) * 1000
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			__( 'No known generic REST-disabling plugins detected in active plugins.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'No suspected REST restriction plugins.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			( microtime( true ) - $start_time ) * 1000
		);
	}
}

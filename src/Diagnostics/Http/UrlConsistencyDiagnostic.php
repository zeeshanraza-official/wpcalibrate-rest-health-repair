<?php
/**
 * Diagnostic for checking Site URL vs Home URL consistency.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics\Http;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UrlConsistencyDiagnostic class.
 */
final class UrlConsistencyDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'url_consistency';
	}

	public function get_group(): string {
		return 'http';
	}

	public function get_title(): string {
		return __( 'Site URL vs Home URL Consistency', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Compares WordPress Address (site_url) and Site Address (home_url) to detect scheme, host, or protocol discrepancies that cause REST CORS or routing failures.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return true;
	}

	public function get_repair_id(): ?string {
		return null; // Changing URLs is NOT an automatic repair per safety rules.
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_WARNING;
	}

	public function run(): DiagnosticResult {
		$start_time = microtime( true );

		$home_url = home_url();
		$site_url = site_url();

		$home_parsed = wp_parse_url( $home_url );
		$site_parsed = wp_parse_url( $site_url );

		$home_scheme = $home_parsed['scheme'] ?? '';
		$site_scheme = $site_parsed['scheme'] ?? '';
		$home_host   = $home_parsed['host'] ?? '';
		$site_host   = $site_parsed['host'] ?? '';

		$evidence = array(
			'home_url'    => $home_url,
			'site_url'    => $site_url,
			'home_scheme' => $home_scheme,
			'site_scheme' => $site_scheme,
			'home_host'   => $home_host,
			'site_host'   => $site_host,
		);

		// Scheme mismatch (one http, other https).
		if ( $home_scheme !== $site_scheme ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				sprintf(
					/* translators: 1: Home URL scheme, 2: Site URL scheme */
					__( 'Protocol mismatch detected: Home URL uses %1$s but Site URL uses %2$s.', 'wpcalibrate-rest-health-repair' ),
					strtoupper( $home_scheme ),
					strtoupper( $site_scheme )
				),
				$evidence,
				array(
					__( 'Incomplete SSL migration where one URL was updated to https:// but the other remained http://.', 'wpcalibrate-rest-health-repair' ),
					__( 'Hardcoded constants in wp-config.php (WP_HOME or WP_SITEURL) with conflicting schemes.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'Cross-Origin (CORS) errors, mixed-content blocking in browsers, and broken REST authentication nonces.', 'wpcalibrate-rest-health-repair' ),
				__( 'Update both URLs to use https:// in Settings > General or in wp-config.php.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( '1. Go to Settings > General. 2. Verify WordPress Address (URL) and Site Address (URL) both start with https://. 3. If greyed out, update WP_HOME and WP_SITEURL in wp-config.php.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				( microtime( true ) - $start_time ) * 1000
			);
		}

		// Host mismatch (e.g. www vs non-www).
		if ( $home_host !== $site_host ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_WARNING,
				sprintf(
					/* translators: 1: Home host, 2: Site host */
					__( 'Hostname mismatch detected: Home host is "%1$s" but Site host is "%2$s".', 'wpcalibrate-rest-health-repair' ),
					$home_host,
					$site_host
				),
				$evidence,
				array(
					__( 'One URL uses "www." while the other uses the bare domain name.', 'wpcalibrate-rest-health-repair' ),
					__( 'Subdomain or multi-domain proxy configuration.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'Browsers treat requests between different hostnames as cross-origin, blocking REST cookie authentication.', 'wpcalibrate-rest-health-repair' ),
				__( 'Standardize both hostnames to use either www or non-www consistently.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( 'Ensure both addresses in Settings > General use the exact same domain name.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_WARNING,
				time(),
				( microtime( true ) - $start_time ) * 1000
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			__( 'Site URL and Home URL are fully consistent in protocol and hostname.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'URL routing and origin headers operate normally.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			( microtime( true ) - $start_time ) * 1000
		);
	}
}

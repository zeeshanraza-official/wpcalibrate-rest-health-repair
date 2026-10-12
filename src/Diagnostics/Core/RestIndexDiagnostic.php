<?php
/**
 * Diagnostic for checking REST root index availability, response code, and schema.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics\Core;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticResult;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RestIndexDiagnostic class.
 */
final class RestIndexDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'wp_rest_index';
	}

	public function get_group(): string {
		return 'core';
	}

	public function get_title(): string {
		return __( 'REST Root Index Accessibility', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Sends an HTTP request to the REST root endpoint to verify valid JSON index response and namespaces.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return true;
	}

	public function get_repair_id(): ?string {
		return 'flush_rewrite_rules';
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_CRITICAL;
	}

	public function run(): DiagnosticResult {
		$start_time = microtime( true );
		$rest_url   = function_exists( 'rest_url' ) ? rest_url() : home_url( '/wp-json/' );

		$settings = (array) get_option( 'wpcalibrate_rhr_settings', array() );
		$timeout  = max( 3, min( 30, (int) ( $settings['http_timeout'] ?? 10 ) ) );

		$args = array(
			'timeout'     => $timeout,
			'redirection' => 3,
			'user-agent'  => 'WPCalibrate-REST-Diagnostic/' . ( defined( 'WPCALIBRATE_RHR_VERSION' ) ? WPCALIBRATE_RHR_VERSION : '1.0.0' ),
			'headers'     => array(
				'Accept' => 'application/json',
			),
		);

		$response = wp_remote_get( $rest_url, $args );
		$duration = ( microtime( true ) - $start_time ) * 1000;

		if ( is_wp_error( $response ) ) {
			$error_code    = $response->get_error_code();
			$error_message = $response->get_error_message();

			$evidence = array(
				'url'           => $rest_url,
				'error_code'    => $error_code,
				'error_message' => Sanitizer::redact_secrets( $error_message ),
				'duration_ms'   => round( $duration, 2 ),
			);

			$likely = array(
				__( 'Local loopback HTTP requests are blocked by the webserver, firewall, or hosting environment.', 'wpcalibrate-rest-health-repair' ),
				__( 'DNS self-resolution failure: the server cannot resolve its own domain name.', 'wpcalibrate-rest-health-repair' ),
				__( 'SSL handshake or certificate validation failure on loopback request.', 'wpcalibrate-rest-health-repair' ),
			);

			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				sprintf(
					/* translators: 1: Error code, 2: Error message */
					__( 'Loopback request to REST root failed with transport error [%1$s]: %2$s', 'wpcalibrate-rest-health-repair' ),
					$error_code,
					$error_message
				),
				$evidence,
				$likely,
				__( 'Background processes, Site Health checks, and REST-dependent plugins cannot communicate with WordPress.', 'wpcalibrate-rest-health-repair' ),
				__( 'Check server loopback connectivity, DNS hosts file entries, and local SSL trust.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( '1. Ask hosting provider if 127.0.0.1 or site domain is resolvable locally. 2. Verify that cURL on the server has up-to-date CA certificates.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				$duration
			);
		}

		$status_code  = wp_remote_retrieve_response_code( $response );
		$headers      = wp_remote_retrieve_headers( $response );
		$body         = wp_remote_retrieve_body( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		$evidence = array(
			'url'          => $rest_url,
			'status_code'  => $status_code,
			'content_type' => $content_type,
			'headers'      => Sanitizer::sanitize_headers( $headers->getAll() ),
			'body_snippet' => Sanitizer::sanitize_response_body_snippet( $body, 300 ),
			'duration_ms'  => round( $duration, 2 ),
		);

		// Handle 404: common permalink/rewrite rule symptom.
		if ( 404 === $status_code ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				__( 'REST root returned HTTP 404 Not Found. Rewrite rules for /wp-json/ appear to be missing or misconfigured.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(
					__( 'WordPress rewrite rules are missing the REST API routing entry (^wp-json/).', 'wpcalibrate-rest-health-repair' ),
					__( 'Web server (.htaccess or Nginx configuration) is not passing /wp-json/ requests to index.php.', 'wpcalibrate-rest-health-repair' ),
					__( 'Permalinks are set to Plain without support for the default rewrite structure.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'The standard REST API endpoint is unreachable across the entire site.', 'wpcalibrate-rest-health-repair' ),
				__( 'Flush WordPress rewrite rules or verify webserver rewrite configuration.', 'wpcalibrate-rest-health-repair' ),
				'flush_rewrite_rules',
				__( '1. Use the automatic "Flush Rewrite Rules" repair button. 2. If using Nginx, ensure try_files $uri $uri/ /index.php?$args; is in server block.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				$duration
			);
		}

		// Handle 401 or 403: authentication restriction / firewall / security plugin.
		if ( 401 === $status_code || 403 === $status_code ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_WARNING,
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'REST root returned HTTP %d. Requests to public REST endpoints are restricted by authentication or security policy.', 'wpcalibrate-rest-health-repair' ),
					$status_code
				),
				$evidence,
				array(
					__( 'A security plugin, membership plugin, or custom rest_authentication_errors filter requires authentication for all REST requests.', 'wpcalibrate-rest-health-repair' ),
					__( 'A web application firewall (WAF), Cloudflare rule, or HTTP Basic Authentication is blocking loopback requests.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'External services, WooCommerce Store API, and unauthenticated public endpoints will fail.', 'wpcalibrate-rest-health-repair' ),
				__( 'Review active security plugins, authentication filters, and server access control rules.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( '1. Check Security and Membership plugins for "Disable REST API" settings. 2. Check server-level password protection (.htpasswd).', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_WARNING,
				time(),
				$duration
			);
		}

		// Check if response is 200.
		if ( 200 !== $status_code ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'REST root returned unexpected HTTP %d response.', 'wpcalibrate-rest-health-repair' ),
					$status_code
				),
				$evidence,
				array(
					__( 'Server misconfiguration, reverse proxy error, or application crash.', 'wpcalibrate-rest-health-repair' ),
					__( 'Rate limiting or temporary server overload.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'REST API is unavailable or returning error statuses.', 'wpcalibrate-rest-health-repair' ),
				__( 'Inspect web server and PHP error logs for the timestamp of the request.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( 'Check server error logs for fatal errors or upstream gateway timeouts.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				$duration
			);
		}

		// Check JSON decoding.
		$json_data = json_decode( $body, true );
		if ( ! is_array( $json_data ) ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				__( 'REST root returned HTTP 200 but the response body is not valid JSON (likely HTML or PHP error output).', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(
					__( 'A PHP notice, warning, or unexpected whitespace was output before the JSON response.', 'wpcalibrate-rest-health-repair' ),
					__( 'A theme template, maintenance page, or security challenge HTML was returned instead of REST JSON.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'REST consumers and block editors fail with JSON parse errors.', 'wpcalibrate-rest-health-repair' ),
				__( 'Inspect response body snippet in technical evidence for PHP errors or HTML tags.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( '1. Check wp-config.php for WP_DEBUG_DISPLAY and set to false. 2. Verify no PHP file has trailing whitespace after closing tags.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				$duration
			);
		}

		$namespaces = (array) ( $json_data['namespaces'] ?? array() );
		$has_wp_v2  = in_array( 'wp/v2', $namespaces, true );

		$evidence['namespaces_count'] = count( $namespaces );
		$evidence['has_wp_v2']        = $has_wp_v2;

		if ( ! $has_wp_v2 ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_WARNING,
				__( 'REST root JSON is valid, but the core "wp/v2" namespace was not found in the index.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(
					__( 'A custom plugin or filter deregistered the core WordPress REST routes.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'WordPress core REST endpoints (posts, pages, types) may be inaccessible.', 'wpcalibrate-rest-health-repair' ),
				__( 'Check active plugins or custom functions for rest_endpoints filters.', 'wpcalibrate-rest-health-repair' ),
				null,
				null,
				DiagnosticResult::SEVERITY_WARNING,
				time(),
				$duration
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			__( 'REST root index is accessible, returned HTTP 200 with valid JSON, and exposed core namespaces.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'Normal REST index operation.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			$duration
		);
	}
}

<?php
/**
 * Diagnostic for checking REST response JSON sanity and detecting unexpected HTML or PHP output corruption.
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
 * JsonSanityDiagnostic class.
 */
final class JsonSanityDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'wp_rest_json_sanity';
	}

	public function get_group(): string {
		return 'core';
	}

	public function get_title(): string {
		return __( 'REST JSON Format & Output Sanity', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Checks if the REST root response begins cleanly with valid JSON without PHP notices, BOM characters, or unexpected HTML documents.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return true;
	}

	public function get_repair_id(): ?string {
		return null;
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_WARNING;
	}

	public function run(): DiagnosticResult {
		$start_time = microtime( true );
		$target_url = function_exists( 'rest_url' ) ? rest_url() : home_url( '/wp-json/' );

		$settings = (array) get_option( 'wpcalibrate_rhr_settings', array() );
		$timeout  = max( 3, min( 30, (int) ( $settings['http_timeout'] ?? 10 ) ) );

		$response = wp_remote_get(
			$target_url,
			array(
				'timeout'    => $timeout,
				'user-agent' => 'WPCalibrate-REST-Diagnostic/' . ( defined( 'WPCALIBRATE_RHR_VERSION' ) ? WPCALIBRATE_RHR_VERSION : '1.0.0' ),
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);
		$duration = ( microtime( true ) - $start_time ) * 1000;

		if ( is_wp_error( $response ) ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_UNABLE_TO_TEST,
				__( 'Could not test JSON sanity due to connection transport failure.', 'wpcalibrate-rest-health-repair' ),
				array( 'error' => $response->get_error_message() ),
				array( __( 'Request failed before response body could be analyzed.', 'wpcalibrate-rest-health-repair' ) ),
				'',
				__( 'Resolve loopback connectivity first.', 'wpcalibrate-rest-health-repair' ),
				null,
				null,
				DiagnosticResult::SEVERITY_INFO,
				time(),
				$duration
			);
		}

		$body         = (string) wp_remote_retrieve_body( $response );
		$trimmed_body = ltrim( $body );

		$evidence = array(
			'first_50_chars' => Sanitizer::redact_secrets( substr( $body, 0, 50 ) ),
			'body_length'    => strlen( $body ),
			'has_bom'        => str_starts_with( $body, "\xEF\xBB\xBF" ),
			'starts_with_tag'=> str_starts_with( strtolower( $trimmed_body ), '<!doctype' ) || str_starts_with( strtolower( $trimmed_body ), '<html' ),
		);

		// Check BOM.
		if ( $evidence['has_bom'] ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				__( 'A UTF-8 Byte Order Mark (BOM) was detected at the beginning of the REST response.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(
					__( 'A PHP file (often wp-config.php or functions.php) was saved with UTF-8 BOM encoding instead of UTF-8 without BOM.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'Causes JSON parse errors in external API consumers, Gutenberg editor, and Stripe webhooks.', 'wpcalibrate-rest-health-repair' ),
				__( 'Resave wp-config.php and custom theme functions.php files using "UTF-8 without BOM" in your text editor.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( 'In VS Code, Notepad++, or your IDE, check the file encoding in the bottom status bar and select "UTF-8" (not UTF-8 with BOM).', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				$duration
			);
		}

		// Check HTML document start.
		if ( $evidence['starts_with_tag'] ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				__( 'The REST endpoint returned an HTML document instead of valid JSON.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(
					__( 'Web server returning a 404/500 custom error HTML page.', 'wpcalibrate-rest-health-repair' ),
					__( 'Security firewall or CDN displaying a CAPTCHA / bot challenge page.', 'wpcalibrate-rest-health-repair' ),
					__( 'A "Coming Soon" or Maintenance Mode plugin intercepting all site requests.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'Applications expecting JSON receive unparseable HTML documents.', 'wpcalibrate-rest-health-repair' ),
				__( 'Check for active maintenance mode plugins, firewall challenge rules, or server 404 templates.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( 'Inspect the response snippet to identify whether the HTML comes from Cloudflare, Wordfence, or a Maintenance plugin.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_CRITICAL,
				time(),
				$duration
			);
		}

		// Check JSON validity.
		json_decode( $body );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_FAILED,
				sprintf(
					/* translators: %s: JSON error message */
					__( 'Malformed JSON response: %s', 'wpcalibrate-rest-health-repair' ),
					json_last_error_msg()
				),
				$evidence,
				array(
					__( 'PHP notice, warning, or unbuffered output before headers were sent.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'REST consumers fail to decode payloads.', 'wpcalibrate-rest-health-repair' ),
				__( 'Check for PHP errors logged to debug.log or display_errors enabled.', 'wpcalibrate-rest-health-repair' ),
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
			__( 'REST response body is cleanly formatted JSON with no BOM markers or unexpected HTML wrapping.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'Normal JSON output.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			$duration
		);
	}
}

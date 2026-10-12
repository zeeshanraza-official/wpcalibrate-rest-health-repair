<?php
/**
 * Diagnostic for detecting rest_authentication_errors filter interference and callback sources.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics\Security;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticResult;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RestAuthErrorsDiagnostic class.
 */
final class RestAuthErrorsDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'rest_auth_errors';
	}

	public function get_group(): string {
		return 'security';
	}

	public function get_title(): string {
		return __( 'REST Authentication Hook Interference (rest_authentication_errors)', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Safely inspects active rest_authentication_errors filters to determine whether a plugin or custom code is blocking unauthenticated public REST requests.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return true;
	}

	public function get_repair_id(): ?string {
		return null; // Safety rule: Never remove third-party callbacks automatically.
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_WARNING;
	}

	public function run(): DiagnosticResult {
		$start_time = microtime( true );

		global $wp_filter;
		$callbacks_info = array();

		// Inspect registered callbacks on rest_authentication_errors without modifying them.
		if ( isset( $wp_filter['rest_authentication_errors'] ) ) {
			$hook = $wp_filter['rest_authentication_errors'];
			if ( isset( $hook->callbacks ) && is_array( $hook->callbacks ) ) {
				foreach ( $hook->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $cb_data ) {
						$function = $cb_data['function'] ?? null;
						$source   = $this->resolve_callback_source( $function );
						$callbacks_info[] = array(
							'priority' => $priority,
							'source'   => Sanitizer::normalize_file_paths( $source ),
						);
					}
				}
			}
		}

		// Simulate the filter as an unauthenticated request would experience it.
		// We pass null (standard initial value).
		$auth_result = apply_filters( 'rest_authentication_errors', null );

		$evidence = array(
			'has_registered_callbacks' => ! empty( $callbacks_info ),
			'callbacks'                => $callbacks_info,
			'simulated_result_is_error'=> is_wp_error( $auth_result ),
		);

		if ( is_wp_error( $auth_result ) ) {
			$error_code    = $auth_result->get_error_code();
			$error_message = $auth_result->get_error_message();

			$evidence['error_code']    = $error_code;
			$evidence['error_message'] = Sanitizer::redact_secrets( $error_message );

			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_WARNING,
				sprintf(
					/* translators: 1: Error code, 2: Error message */
					__( 'A plugin or custom code rejected public REST requests via rest_authentication_errors [%1$s]: %2$s', 'wpcalibrate-rest-health-repair' ),
					$error_code,
					$error_message
				),
				$evidence,
				array(
					__( 'A security or membership plugin configured to require login for all REST requests.', 'wpcalibrate-rest-health-repair' ),
					__( 'Custom code in functions.php or an mu-plugin enforcing site-wide REST lockdown.', 'wpcalibrate-rest-health-repair' ),
				),
				__( 'Public endpoints like WooCommerce Store API (Cart/Checkout) and external webhooks will be blocked unless authenticated.', 'wpcalibrate-rest-health-repair' ),
				__( 'Review the callback sources listed in technical evidence to locate the security rule or plugin.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( '1. Check the plugin or theme file indicated in the callback list. 2. Verify settings in your security/membership plugin regarding "Restrict REST API". 3. Whitelist public namespaces (e.g. wc/store/v1) if the lockdown is intentional.', 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_WARNING,
				time(),
				( microtime( true ) - $start_time ) * 1000
			);
		}

		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			__( 'No active rest_authentication_errors filter is blocking unauthenticated REST requests.', 'wpcalibrate-rest-health-repair' ),
			$evidence,
			array(),
			__( 'Public REST access is permitted.', 'wpcalibrate-rest-health-repair' ),
			__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			( microtime( true ) - $start_time ) * 1000
		);
	}

	/**
	 * Resolves a callable to its file/class source using Reflection.
	 *
	 * @param mixed $callback Callback callable.
	 * @return string Source description.
	 */
	private function resolve_callback_source( mixed $callback ): string {
		try {
			if ( is_string( $callback ) && function_exists( $callback ) ) {
				$rf = new ReflectionFunction( $callback );
				return (string) $rf->getFileName() . ':' . $rf->getStartLine();
			}

			if ( is_array( $callback ) && count( $callback ) === 2 ) {
				$class  = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
				$method = (string) $callback[1];
				if ( method_exists( $class, $method ) ) {
					$rm = new ReflectionMethod( $class, $method );
					return $class . '::' . $method . ' (' . (string) $rm->getFileName() . ':' . $rm->getStartLine() . ')';
				}
			}

			if ( $callback instanceof \Closure ) {
				$rf = new ReflectionFunction( $callback );
				return 'Closure at ' . (string) $rf->getFileName() . ':' . $rf->getStartLine();
			}
		} catch ( Throwable ) {
			// Ignore reflection errors.
		}

		return 'Unknown Callback';
	}
}

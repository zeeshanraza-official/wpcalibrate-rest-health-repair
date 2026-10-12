<?php
/**
 * Diagnostic logger.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Logging;

use WPCalibrate\RESTHealthRepair\Support\Capability;
use WPCalibrate\RESTHealthRepair\Support\Sanitizer;
use WPCalibrate\RESTHealthRepair\Support\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class.
 */
final class Logger {

	/**
	 * Log storage option key.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'wpcalibrate_rhr_logs';

	/**
	 * Maximum number of log entries to retain in DB.
	 *
	 * @var int
	 */
	public const MAX_ENTRIES = 100;

	/**
	 * Log levels.
	 */
	public const LEVEL_INFO     = 'info';
	public const LEVEL_WARNING  = 'warning';
	public const LEVEL_ERROR    = 'error';
	public const LEVEL_CRITICAL = 'critical';

	/**
	 * Checks if logging is enabled in settings.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$settings = (array) get_option( 'wpcalibrate_rhr_settings', array() );
		return ! empty( $settings['logging_enabled'] );
	}

	/**
	 * Logs a diagnostic message with safe sanitization.
	 *
	 * @param string               $level Log level (info, warning, error, critical).
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		if ( ! self::is_enabled() && self::LEVEL_CRITICAL !== $level ) {
			return;
		}

		$sanitized_message = Sanitizer::redact_secrets( $message );
		$sanitized_context = (array) Sanitizer::sanitize_deep( $context );

		$entry = array(
			'id'        => wp_generate_uuid4(),
			'timestamp' => time(),
			'level'     => $level,
			'message'   => $sanitized_message,
			'context'   => $sanitized_context,
		);

		$logs = self::get_raw_logs();
		array_unshift( $logs, $entry );

		// Prune to maximum entries.
		if ( count( $logs ) > self::MAX_ENTRIES ) {
			$logs = array_slice( $logs, 0, self::MAX_ENTRIES );
		}

		update_option( self::OPTION_KEY, $logs, false );

		// Optionally pass to WooCommerce logger if active.
		if ( Environment::is_woocommerce_active() && function_exists( 'wc_get_logger' ) ) {
			$wc_logger = wc_get_logger();
			$wc_source = array( 'source' => 'wpcalibrate-rest-health-repair' );
			switch ( $level ) {
				case self::LEVEL_CRITICAL:
					$wc_logger->critical( $sanitized_message, $wc_source );
					break;
				case self::LEVEL_ERROR:
					$wc_logger->error( $sanitized_message, $wc_source );
					break;
				case self::LEVEL_WARNING:
					$wc_logger->warning( $sanitized_message, $wc_source );
					break;
				default:
					$wc_logger->info( $sanitized_message, $wc_source );
					break;
			}
		}
	}

	/**
	 * Convenience helper: info.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Convenience helper: warning.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Convenience helper: error.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Convenience helper: critical.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public static function critical( string $message, array $context = array() ): void {
		self::log( self::LEVEL_CRITICAL, $message, $context );
	}

	/**
	 * Gets all stored logs.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_raw_logs(): array {
		$logs = get_option( self::OPTION_KEY, array() );
		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Clears all diagnostic logs. Requires manage_options.
	 *
	 * @return bool
	 */
	public static function clear(): bool {
		Capability::check_or_die();
		return delete_option( self::OPTION_KEY );
	}

	/**
	 * Prunes logs older than the retention days threshold.
	 *
	 * @param int $days Number of days.
	 * @return void
	 */
	public static function prune_by_retention( int $days = 7 ): void {
		if ( $days <= 0 ) {
			return;
		}
		$threshold = time() - ( $days * DAY_IN_SECONDS );
		$logs      = self::get_raw_logs();
		$filtered  = array_filter(
			$logs,
			static function ( array $entry ) use ( $threshold ): bool {
				return ( (int) ( $entry['timestamp'] ?? 0 ) ) >= $threshold;
			}
		);
		update_option( self::OPTION_KEY, array_values( $filtered ), false );
	}
}

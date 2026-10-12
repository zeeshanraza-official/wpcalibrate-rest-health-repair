<?php
/**
 * Sanitizer and secret redaction helper.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizer class.
 */
final class Sanitizer {

	/**
	 * Sensitive header names to redact.
	 *
	 * @var array<string>
	 */
	private const SENSITIVE_HEADERS = array(
		'authorization',
		'proxy-authorization',
		'cookie',
		'set-cookie',
		'x-api-key',
		'api-key',
		'x-auth-token',
		'x-wp-nonce',
		'cf-connecting-ip',
		'cf-ray',
	);

	/**
	 * Sensitive pattern regexes.
	 *
	 * @var array<string>
	 */
	private const SENSITIVE_PATTERNS = array(
		'/([?&](?:key|api_key|token|access_token|secret|password|pwd|auth)=)[^&]+/i' => '$1[REDACTED]',
		'/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/i'                                      => 'Bearer [REDACTED]',
		'/Basic\s+[A-Za-z0-9+\/]+=*/i'                                             => 'Basic [REDACTED]',
		'/(password|secret|auth_key|secure_auth_key|logged_in_key|nonce_key|auth_salt|secure_auth_salt|logged_in_salt|nonce_salt)\s*=\s*(?:[\'"][^\'"]+[\'"]|[^\s&;]+)/i' => '$1=[REDACTED]',
	);

	/**
	 * Redacts sensitive secrets from a string.
	 *
	 * @param string $content Input string.
	 * @return string Redacted string.
	 */
	public static function redact_secrets( string $content ): string {
		foreach ( self::SENSITIVE_PATTERNS as $pattern => $replacement ) {
			$content = (string) preg_replace( $pattern, $replacement, $content );
		}

		// Redact URLs containing inline credentials (user:password@host).
		$content = (string) preg_replace( '#([a-z]+://)([^:]+):([^@]+)@#i', '$1$2:***@', $content );

		return self::normalize_file_paths( $content );
	}

	/**
	 * Normalizes file paths to avoid leaking server filesystem structure.
	 *
	 * @param string $path Raw path or string containing paths.
	 * @return string Normalized path.
	 */
	public static function normalize_file_paths( string $path ): string {
		$normalized = wp_normalize_path( $path );

		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$content_dir = wp_normalize_path( WP_CONTENT_DIR );
			$normalized  = str_replace( $content_dir, '[WP_CONTENT_DIR]', $normalized );
		}

		if ( defined( 'ABSPATH' ) ) {
			$abspath    = wp_normalize_path( ABSPATH );
			$normalized = str_replace( $abspath, '[ABSPATH]/', $normalized );
		}

		return $normalized;
	}

	/**
	 * Sanitizes HTTP headers array, removing cookies and authorization secrets.
	 *
	 * @param array<string, mixed>|mixed $headers HTTP headers.
	 * @return array<string, string> Sanitized headers.
	 */
	public static function sanitize_headers( mixed $headers ): array {
		if ( ! is_array( $headers ) ) {
			return array();
		}

		$clean = array();
		foreach ( $headers as $key => $value ) {
			$key_lower = strtolower( (string) $key );
			if ( in_array( $key_lower, self::SENSITIVE_HEADERS, true ) ) {
				$clean[ (string) $key ] = '[REDACTED]';
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ (string) $key ] = self::redact_secrets( implode( ', ', array_map( 'strval', $value ) ) );
			} else {
				$clean[ (string) $key ] = self::redact_secrets( (string) $value );
			}
		}

		return $clean;
	}

	/**
	 * Truncates and sanitizes a response body to prevent storing massive HTML dumps.
	 *
	 * @param string $body Raw response body.
	 * @param int    $max_length Max characters to retain.
	 * @return string Truncated and sanitized body snippet.
	 */
	public static function sanitize_response_body_snippet( string $body, int $max_length = 500 ): string {
		$clean = self::redact_secrets( $body );
		if ( strlen( $clean ) > $max_length ) {
			return substr( $clean, 0, $max_length ) . ' ... [TRUNCATED (' . strlen( $clean ) . ' bytes total)]';
		}
		return $clean;
	}

	/**
	 * Masks a license key for display (e.g. "••••-••••-••••-1234").
	 *
	 * @param string $key Raw key.
	 * @return string Masked key.
	 */
	public static function mask_license_key( string $key ): string {
		$trimmed = trim( $key );
		if ( '' === $trimmed ) {
			return '';
		}
		$len = strlen( $trimmed );
		if ( $len <= 4 ) {
			return str_repeat( '•', $len );
		}
		$last4 = substr( $trimmed, -4 );
		return str_repeat( '•', max( 8, $len - 4 ) ) . '-' . $last4;
	}

	/**
	 * Recursively sanitizes nested array data for safe reporting or logging.
	 *
	 * @param mixed $data Arbitrary data.
	 * @return mixed Sanitized data.
	 */
	public static function sanitize_deep( mixed $data ): mixed {
		if ( is_array( $data ) ) {
			$sanitized = array();
			foreach ( $data as $k => $v ) {
				$k_str = (string) $k;
				if ( in_array( strtolower( $k_str ), array( 'password', 'pass', 'secret', 'token', 'key', 'auth', 'cookie' ), true ) ) {
					$sanitized[ $k ] = '[REDACTED]';
				} else {
					$sanitized[ $k ] = self::sanitize_deep( $v );
				}
			}
			return $sanitized;
		}

		if ( is_string( $data ) ) {
			return self::redact_secrets( $data );
		}

		return $data;
	}
}

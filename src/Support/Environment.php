<?php
/**
 * Environment detection and system telemetry inspection.
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
 * Environment helper class.
 */
final class Environment {

	/**
	 * Gets PHP version.
	 *
	 * @return string
	 */
	public static function get_php_version(): string {
		return PHP_VERSION;
	}

	/**
	 * Gets WordPress version.
	 *
	 * @return string
	 */
	public static function get_wordpress_version(): string {
		global $wp_version;
		return (string) ( $wp_version ?? 'Unknown' );
	}

	/**
	 * Gets web server software header or indicator.
	 *
	 * @return string
	 */
	public static function get_web_server(): string {
		$server = sanitize_text_field( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ) );
		return Sanitizer::redact_secrets( $server );
	}

	/**
	 * Gets database server version.
	 *
	 * @return string
	 */
	public static function get_database_version(): string {
		global $wpdb;
		if ( isset( $wpdb ) && method_exists( $wpdb, 'db_version' ) ) {
			return (string) $wpdb->db_version();
		}
		return 'Unknown';
	}

	/**
	 * Checks if WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
	}

	/**
	 * Gets WooCommerce version if active.
	 *
	 * @return string|null
	 */
	public static function get_woocommerce_version(): ?string {
		if ( ! self::is_woocommerce_active() ) {
			return null;
		}
		if ( defined( 'WC_VERSION' ) ) {
			return WC_VERSION;
		}
		if ( function_exists( 'WC' ) && isset( WC()->version ) ) {
			return (string) WC()->version;
		}
		return 'Unknown';
	}

	/**
	 * Checks if WooCommerce High-Performance Order Storage (HPOS) is enabled.
	 *
	 * @return string
	 */
	public static function get_woocommerce_hpos_status(): string {
		if ( ! self::is_woocommerce_active() ) {
			return 'N/A';
		}
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && method_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'Enabled' : 'Disabled (Post Meta)';
		}
		return 'Unknown';
	}

	/**
	 * Checks if site is using HTTPS.
	 *
	 * @return bool
	 */
	public static function is_ssl(): bool {
		return is_ssl();
	}

	/**
	 * Gets home URL scheme and host safely.
	 *
	 * @return string
	 */
	public static function get_home_url(): string {
		return home_url();
	}

	/**
	 * Gets site URL scheme and host safely.
	 *
	 * @return string
	 */
	public static function get_site_url(): string {
		return site_url();
	}

	/**
	 * Gets configured permalink structure.
	 *
	 * @return string
	 */
	public static function get_permalink_structure(): string {
		return (string) get_option( 'permalink_structure', '' );
	}

	/**
	 * Checks if pretty permalinks are enabled.
	 *
	 * @return bool
	 */
	public static function has_pretty_permalinks(): bool {
		return '' !== self::get_permalink_structure();
	}

	/**
	 * Gets REST root URL.
	 *
	 * @return string
	 */
	public static function get_rest_url(): string {
		return function_exists( 'rest_url' ) ? rest_url() : home_url( '/wp-json/' );
	}

	/**
	 * Checks if server is Apache or LiteSpeed.
	 *
	 * @return bool
	 */
	public static function is_apache_or_litespeed(): bool {
		global $is_apache;
		if ( ! empty( $is_apache ) ) {
			return true;
		}
		$software = strtolower( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' ) );
		return str_contains( $software, 'apache' ) || str_contains( $software, 'litespeed' );
	}

	/**
	 * Checks if server is Nginx.
	 *
	 * @return bool
	 */
	public static function is_nginx(): bool {
		global $is_nginx;
		if ( ! empty( $is_nginx ) ) {
			return true;
		}
		$software = strtolower( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' ) );
		return str_contains( $software, 'nginx' );
	}

	/**
	 * Checks .htaccess existence and writability.
	 *
	 * @return array{exists: bool, writable: bool, path: string}
	 */
	public static function get_htaccess_status(): array {
		if ( ! defined( 'ABSPATH' ) ) {
			return array(
				'exists'   => false,
				'writable' => false,
				'path'     => '',
			);
		}

		$home_path     = function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH;
		$htaccess_file = $home_path . '.htaccess';

		return array(
			'exists'   => file_exists( $htaccess_file ),
			'writable' => is_writable( $htaccess_file ),
			'path'     => Sanitizer::normalize_file_paths( $htaccess_file ),
		);
	}

	/**
	 * Gets active theme name and version.
	 *
	 * @return array{name: string, version: string, is_child: bool}
	 */
	public static function get_active_theme_info(): array {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return array(
				'name'     => 'Unknown',
				'version'  => 'Unknown',
				'is_child' => false,
			);
		}

		$theme = wp_get_theme();
		return array(
			'name'     => $theme->get( 'Name' ),
			'version'  => (string) $theme->get( 'Version' ),
			'is_child' => is_child_theme(),
		);
	}

	/**
	 * Gets active plugins list with versions (sanitized).
	 *
	 * @return array<string, string> Plugin name => Version
	 */
	public static function get_active_plugins_list(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			$plugin_admin_file = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( file_exists( $plugin_admin_file ) ) {
				require_once $plugin_admin_file;
			}
		}

		$all_plugins    = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$result         = array();

		foreach ( $active_plugins as $plugin_file ) {
			if ( isset( $all_plugins[ $plugin_file ] ) ) {
				$name            = $all_plugins[ $plugin_file ]['Name'] ?? $plugin_file;
				$version         = $all_plugins[ $plugin_file ]['Version'] ?? 'Unknown';
				$result[ $name ] = (string) $version;
			}
		}

		return $result;
	}

	/**
	 * Checks if multisite.
	 *
	 * @return bool
	 */
	public static function is_multisite(): bool {
		return is_multisite();
	}
}

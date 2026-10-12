<?php
/**
 * Admin asset enqueue manager.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Admin;

use WPCalibrate\RESTHealthRepair\Support\Capability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets class.
 */
final class Assets {

	/**
	 * Registers enqueue hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueues CSS and JS strictly on this plugin's admin screens.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! Capability::current_user_can() ) {
			return;
		}

		// Only load on WPCalibrate REST Health & Repair screen or fallback parent.
		$is_plugin_screen = str_contains( $hook_suffix, AdminMenu::PAGE_SLUG )
			|| ( str_contains( $hook_suffix, AdminMenu::PARENT_SLUG ) && isset( $_GET['page'] ) && AdminMenu::PAGE_SLUG === $_GET['page'] );

		if ( ! $is_plugin_screen ) {
			return;
		}

		$version = defined( 'WPCALIBRATE_RHR_VERSION' ) ? WPCALIBRATE_RHR_VERSION : '1.0.0';

		// Enqueue base CSS.
		wp_enqueue_style(
			'wpcalibrate-rhr-admin',
			WPCALIBRATE_RHR_URL . 'assets/css/admin.css',
			array(),
			$version
		);

		// Enqueue RTL CSS if site is in RTL language.
		if ( is_rtl() && file_exists( WPCALIBRATE_RHR_DIR . 'assets/css/admin-rtl.css' ) ) {
			wp_enqueue_style(
				'wpcalibrate-rhr-admin-rtl',
				WPCALIBRATE_RHR_URL . 'assets/css/admin-rtl.css',
				array( 'wpcalibrate-rhr-admin' ),
				$version
			);
		}

		// Enqueue JS.
		wp_enqueue_script(
			'wpcalibrate-rhr-admin',
			WPCALIBRATE_RHR_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$version,
			true
		);

		// Safe localized data. Never exposes secrets or license keys!
		wp_localize_script(
			'wpcalibrate-rhr-admin',
			'wpcalibrateRhrData',
			array(
				'restBase'      => esc_url_raw( rest_url( 'wpcalibrate-rest-health-repair/v1/' ) ),
				'restNonce'     => wp_create_nonce( 'wp_rest' ),
				'adminPostUrl'  => esc_url_raw( admin_url( 'admin-post.php' ) ),
				'exportNonce'   => wp_create_nonce( 'wpcalibrate_rhr_export_report_nonce' ),
				'strings'       => array(
					'runningScan'     => __( 'Running diagnostics...', 'wpcalibrate-rest-health-repair' ),
					'runningRepair'   => __( 'Executing repair...', 'wpcalibrate-rest-health-repair' ),
					'confirmRepair'   => __( 'Are you sure you want to run this repair action?', 'wpcalibrate-rest-health-repair' ),
					'confirmRollback' => __( 'Are you sure you want to revert this repair?', 'wpcalibrate-rest-health-repair' ),
					'fixed'           => __( 'Fixed', 'wpcalibrate-rest-health-repair' ),
					'notResolved'     => __( 'Not Resolved', 'wpcalibrate-rest-health-repair' ),
					'errorOccurred'   => __( 'An unexpected error occurred during request.', 'wpcalibrate-rest-health-repair' ),
				),
			)
		);
	}
}

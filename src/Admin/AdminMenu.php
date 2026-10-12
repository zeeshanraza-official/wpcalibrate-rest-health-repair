<?php
/**
 * Admin menu registration and collision-resistant routing.
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
 * AdminMenu class.
 */
final class AdminMenu {

	public const PARENT_SLUG = 'wpcalibrate';
	public const PAGE_SLUG   = 'wpcalibrate-rest-health-repair';

	private AdminPage $admin_page;

	/**
	 * Constructor.
	 *
	 * @param AdminPage $admin_page Admin page renderer.
	 */
	public function __construct( AdminPage $admin_page ) {
		$this->admin_page = $admin_page;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_head', array( $this, 'render_admin_menu_icon_css' ) );
	}

	/**
	 * Injects global admin CSS to enforce standard 20x20px icon size and dashboard colors.
	 *
	 * @return void
	 */
	public function render_admin_menu_icon_css(): void {
		?>
		<style id="wpcalibrate-rhr-menu-icon-css">
			#adminmenu #toplevel_page_wpcalibrate .wp-menu-image,
			#adminmenu .toplevel_page_wpcalibrate .wp-menu-image {
				display: flex;
				align-items: center;
				justify-content: center;
			}
			#adminmenu #toplevel_page_wpcalibrate .wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate .wp-menu-image img {
				width: 20px !important;
				height: 20px !important;
				max-width: 20px !important;
				max-height: 20px !important;
				padding: 0 !important;
				margin: 0 !important;
				box-sizing: content-box !important;
				opacity: 0.7 !important;
				filter: grayscale(100%) !important;
				transition: opacity 0.15s ease-in-out, filter 0.15s ease-in-out !important;
			}
			#adminmenu #toplevel_page_wpcalibrate:hover .wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate:hover .wp-menu-image img {
				opacity: 1 !important;
				filter: none !important;
			}
			#adminmenu #toplevel_page_wpcalibrate.wp-has-current-submenu .wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate.wp-has-current-submenu .wp-menu-image img,
			#adminmenu #toplevel_page_wpcalibrate.current .wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate.current .wp-menu-image img {
				opacity: 1 !important;
				filter: none !important;
			}
			#adminmenu #toplevel_page_wpcalibrate .wp-menu-image.svg,
			#adminmenu .toplevel_page_wpcalibrate .wp-menu-image.svg {
				background-size: 20px 20px !important;
				background-position: center center !important;
				background-repeat: no-repeat !important;
				opacity: 0.7 !important;
				transition: opacity 0.15s ease-in-out, filter 0.15s ease-in-out !important;
			}
			#adminmenu #toplevel_page_wpcalibrate:hover .wp-menu-image.svg,
			#adminmenu .toplevel_page_wpcalibrate:hover .wp-menu-image.svg {
				opacity: 1 !important;
				filter: brightness(1.2) !important;
			}
			#adminmenu #toplevel_page_wpcalibrate.wp-has-current-submenu .wp-menu-image.svg,
			#adminmenu .toplevel_page_wpcalibrate.wp-has-current-submenu .wp-menu-image.svg,
			#adminmenu #toplevel_page_wpcalibrate.current .wp-menu-image.svg,
			#adminmenu .toplevel_page_wpcalibrate.current .wp-menu-image.svg {
				opacity: 1 !important;
				filter: brightness(1.3) !important;
			}
		</style>
		<?php
	}

	/**
	 * Registers WPCalibrate parent menu if missing, and adds this plugin's submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		if ( ! Capability::current_user_can() ) {
			return;
		}

		global $admin_page_hooks;

		// Check if parent WPCalibrate menu is already registered by another WPCalibrate plugin.
		$parent_registered = isset( $admin_page_hooks[ self::PARENT_SLUG ] );

		if ( ! $parent_registered ) {
			$icon_url = $this->get_menu_icon();
			add_menu_page(
				'WPCalibrate',
				'WPCalibrate',
				Capability::REQUIRED_CAPABILITY,
				self::PARENT_SLUG,
				array( $this->admin_page, 'render' ),
				$icon_url,
				58
			);
		}

		// Add our dedicated, collision-resistant submenu.
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'REST Health & Repair', 'wpcalibrate-rest-health-repair' ),
			__( 'REST Health & Repair', 'wpcalibrate-rest-health-repair' ),
			Capability::REQUIRED_CAPABILITY,
			self::PAGE_SLUG,
			array( $this->admin_page, 'render' )
		);
	}

	/**
	 * Resolves menu icon URL.
	 *
	 * @return string
	 */
	private function get_menu_icon(): string {
		$svg_path = WPCALIBRATE_RHR_DIR . 'branding/icon.svg';
		if ( file_exists( $svg_path ) ) {
			$svg_content = file_get_contents( $svg_path );
			if ( false !== $svg_content && '' !== $svg_content ) {
				return 'data:image/svg+xml;base64,' . base64_encode( $svg_content );
			}
		}

		$png_20 = WPCALIBRATE_RHR_DIR . 'branding/icon-menu-20x20.png';
		if ( file_exists( $png_20 ) ) {
			return WPCALIBRATE_RHR_URL . 'branding/icon-menu-20x20.png';
		}

		return 'dashicons-rest-api';
	}
}

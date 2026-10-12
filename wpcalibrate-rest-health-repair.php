<?php
/**
 * Plugin Name:       WPCalibrate REST Health & Repair
 * Plugin URI:        https://wpcalibrate.com
 * Description:       Advanced diagnostic and safe repair suite for WordPress and WooCommerce REST API, Store API, loopback connectivity, rewrite routing, and security interference.
 * Version:           1.0.1
 * Requires at least: 6.4
 * Tested up to:      7.1.3
 * Requires PHP:      8.2
 * Author:            WPCalibrate
 * Author URI:        https://wpcalibrate.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcalibrate-rest-health-repair
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   11.2.1
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Version & path constants.
define( 'WPCALIBRATE_RHR_VERSION', '1.0.1' );
define( 'WPCALIBRATE_RHR_FILE', __FILE__ );
define( 'WPCALIBRATE_RHR_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCALIBRATE_RHR_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCALIBRATE_RHR_MIN_PHP', '8.2.0' );

// Strict PHP version check before bootstrapping.
if ( version_compare( PHP_VERSION, WPCALIBRATE_RHR_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					printf(
						/* translators: 1: Plugin name, 2: Required PHP version, 3: Current PHP version */
						esc_html__( '%1$s requires PHP version %2$s or higher. Your server is running PHP %3$s. Please upgrade your PHP runtime.', 'wpcalibrate-rest-health-repair' ),
						'<strong>WPCalibrate REST Health & Repair</strong>',
						esc_html( WPCALIBRATE_RHR_MIN_PHP ),
						esc_html( PHP_VERSION )
					);
					?>
				</p>
			</div>
			<?php
		}
	);
	return;
}

// Load PSR-4 compatible autoloader.
require_once WPCALIBRATE_RHR_DIR . 'src/Autoloader.php';
\WPCalibrate\RESTHealthRepair\Autoloader::register( WPCALIBRATE_RHR_DIR . 'src' );

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, array( '\WPCalibrate\RESTHealthRepair\Lifecycle\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\WPCalibrate\RESTHealthRepair\Lifecycle\Deactivator', 'deactivate' ) );

// Bootstrap plugin instance.
add_action(
	'plugins_loaded',
	static function (): void {
		\WPCalibrate\RESTHealthRepair\Plugin::get_instance();
	}
);

<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load autoloader.
require_once __DIR__ . '/src/Autoloader.php';
\WPCalibrate\RESTHealthRepair\Autoloader::register( __DIR__ . '/src' );

// Run uninstall cleanup.
\WPCalibrate\RESTHealthRepair\Lifecycle\Uninstaller::uninstall();

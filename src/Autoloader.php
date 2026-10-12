<?php
/**
 * PSR-4 compatible autoloader for WPCalibrate REST Health & Repair.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoloader class.
 */
final class Autoloader {

	/**
	 * Namespace prefix.
	 *
	 * @var string
	 */
	private const NAMESPACE_PREFIX = 'WPCalibrate\\RESTHealthRepair\\';

	/**
	 * Base directory for namespace prefix.
	 *
	 * @var string
	 */
	private string $base_dir;

	/**
	 * Constructor.
	 *
	 * @param string $base_dir Absolute path to the src directory.
	 */
	public function __construct( string $base_dir ) {
		$this->base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Registers the autoloader.
	 *
	 * @param string $base_dir Base directory.
	 * @return self
	 */
	public static function register( string $base_dir ): self {
		$instance = new self( $base_dir );
		spl_autoload_register( array( $instance, 'autoload' ) );
		return $instance;
	}

	/**
	 * Autoload callback.
	 *
	 * @param string $class Class name.
	 * @return void
	 */
	public function autoload( string $class ): void {
		if ( ! str_starts_with( $class, self::NAMESPACE_PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( self::NAMESPACE_PREFIX ) );
		$file           = $this->base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}

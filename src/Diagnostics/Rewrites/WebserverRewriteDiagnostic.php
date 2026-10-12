<?php
/**
 * Diagnostic for checking Web Server rewrite configuration (.htaccess / Nginx).
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Diagnostics\Rewrites;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticResult;
use WPCalibrate\RESTHealthRepair\Support\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WebserverRewriteDiagnostic class.
 */
final class WebserverRewriteDiagnostic implements DiagnosticInterface {

	public function get_id(): string {
		return 'webserver_rewrite';
	}

	public function get_group(): string {
		return 'rewrites';
	}

	public function get_title(): string {
		return __( 'Web Server Rewrite Configuration (.htaccess / Nginx)', 'wpcalibrate-rest-health-repair' );
	}

	public function get_description(): string {
		return __( 'Evaluates the web server software and configuration files (.htaccess on Apache/LiteSpeed or try_files on Nginx) responsible for forwarding /wp-json/ to WordPress.', 'wpcalibrate-rest-health-repair' );
	}

	public function is_applicable(): bool {
		return true;
	}

	public function get_repair_id(): ?string {
		$ht = Environment::get_htaccess_status();
		if ( Environment::is_apache_or_litespeed() && $ht['writable'] ) {
			return 'repair_htaccess_rules';
		}
		return null;
	}

	public function get_severity(): string {
		return DiagnosticResult::SEVERITY_WARNING;
	}

	public function run(): DiagnosticResult {
		$start_time = microtime( true );

		$web_server = Environment::get_web_server();
		$is_apache  = Environment::is_apache_or_litespeed();
		$is_nginx   = Environment::is_nginx();
		$ht_status  = Environment::get_htaccess_status();

		$evidence = array(
			'web_server'        => $web_server,
			'is_apache_or_lite' => $is_apache,
			'is_nginx'          => $is_nginx,
			'htaccess_exists'   => $ht_status['exists'],
			'htaccess_writable' => $ht_status['writable'],
			'htaccess_path'     => $ht_status['path'],
		);

		// Nginx evaluation:
		if ( $is_nginx ) {
			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_HEALTHY,
				__( 'Nginx web server detected. WordPress cannot edit Nginx configuration files directly.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(),
				__( 'Nginx routes URLs using server block configuration directives.', 'wpcalibrate-rest-health-repair' ),
				__( 'If /wp-json/ returns 404 on Nginx, ensure try_files $uri $uri/ /index.php?$args; is configured in your server block.', 'wpcalibrate-rest-health-repair' ),
				null,
				__( "In your Nginx site configuration (e.g. /etc/nginx/sites-available/your-site.conf):\nlocation / {\n    try_files \$uri \$uri/ /index.php?\$args;\n}\nThen run: sudo nginx -t && sudo systemctl reload nginx", 'wpcalibrate-rest-health-repair' ),
				DiagnosticResult::SEVERITY_INFO,
				time(),
				( microtime( true ) - $start_time ) * 1000
			);
		}

		// Apache / LiteSpeed evaluation:
		if ( $is_apache ) {
			if ( ! $ht_status['exists'] ) {
				return new DiagnosticResult(
					$this->get_id(),
					DiagnosticResult::STATUS_WARNING,
					__( '.htaccess file is missing from the WordPress root directory.', 'wpcalibrate-rest-health-repair' ),
					$evidence,
					array(
						__( 'File was deleted or WordPress lacks write permission to create .htaccess.', 'wpcalibrate-rest-health-repair' ),
					),
					__( 'Pretty permalinks and /wp-json/ routing cannot function on Apache without an .htaccess file.', 'wpcalibrate-rest-health-repair' ),
					__( 'Generate a standard WordPress .htaccess file.', 'wpcalibrate-rest-health-repair' ),
					$ht_status['writable'] ? 'repair_htaccess_rules' : null,
					__( "Create an .htaccess file in your site root with:\n# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress", 'wpcalibrate-rest-health-repair' ),
					DiagnosticResult::SEVERITY_WARNING,
					time(),
					( microtime( true ) - $start_time ) * 1000
				);
			}

			// Check content of .htaccess.
			$home_path     = function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH;
			$htaccess_file = $home_path . '.htaccess';
			$content       = (string) @file_get_contents( $htaccess_file );

			$has_wp_block = str_contains( $content, '# BEGIN WordPress' ) && str_contains( $content, '# END WordPress' );
			$has_rewrite  = str_contains( $content, 'RewriteEngine On' );
			$evidence['has_wp_block'] = $has_wp_block;
			$evidence['has_rewrite']  = $has_rewrite;

			if ( ! $has_wp_block || ! $has_rewrite ) {
				return new DiagnosticResult(
					$this->get_id(),
					DiagnosticResult::STATUS_WARNING,
					__( '.htaccess exists but appears to be missing standard WordPress rewrite rules.', 'wpcalibrate-rest-health-repair' ),
					$evidence,
					array(
						__( 'Custom or incomplete .htaccess file without standard WordPress mod_rewrite block.', 'wpcalibrate-rest-health-repair' ),
					),
					__( 'Requests to /wp-json/ may not be forwarded to index.php.', 'wpcalibrate-rest-health-repair' ),
					__( 'Regenerate standard WordPress rewrite block in .htaccess.', 'wpcalibrate-rest-health-repair' ),
					$ht_status['writable'] ? 'repair_htaccess_rules' : null,
					null,
					DiagnosticResult::SEVERITY_WARNING,
					time(),
					( microtime( true ) - $start_time ) * 1000
				);
			}

			return new DiagnosticResult(
				$this->get_id(),
				DiagnosticResult::STATUS_HEALTHY,
				__( '.htaccess file exists and contains standard WordPress mod_rewrite rules.', 'wpcalibrate-rest-health-repair' ),
				$evidence,
				array(),
				__( 'Web server rewrite rules are properly configured.', 'wpcalibrate-rest-health-repair' ),
				__( 'No action required.', 'wpcalibrate-rest-health-repair' ),
				null,
				null,
				DiagnosticResult::SEVERITY_INFO,
				time(),
				( microtime( true ) - $start_time ) * 1000
			);
		}

		// Fallback for other web servers (IIS, Caddy, etc.):
		return new DiagnosticResult(
			$this->get_id(),
			DiagnosticResult::STATUS_HEALTHY,
			sprintf(
				/* translators: %s: Web server software */
				__( 'Web server detected: %s. Ensure URL rewriting forwards /wp-json/ to index.php.', 'wpcalibrate-rest-health-repair' ),
				$web_server
			),
			$evidence,
			array(),
			__( 'Non-Apache/Nginx web server.', 'wpcalibrate-rest-health-repair' ),
			__( 'Verify URL rewrite configuration in your server documentation.', 'wpcalibrate-rest-health-repair' ),
			null,
			null,
			DiagnosticResult::SEVERITY_INFO,
			time(),
			( microtime( true ) - $start_time ) * 1000
		);
	}
}

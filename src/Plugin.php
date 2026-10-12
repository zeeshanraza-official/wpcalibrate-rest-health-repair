<?php
/**
 * Main plugin orchestrator and service coordinator.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Plugin;

namespace WPCalibrate\RESTHealthRepair;

use WPCalibrate\RESTHealthRepair\Admin\AdminMenu;
use WPCalibrate\RESTHealthRepair\Admin\AdminPage;
use WPCalibrate\RESTHealthRepair\Admin\Assets;
use WPCalibrate\RESTHealthRepair\Admin\Notices;
use WPCalibrate\RESTHealthRepair\Admin\Settings;
use WPCalibrate\RESTHealthRepair\Api\RestApiController;
use WPCalibrate\RESTHealthRepair\Diagnostics\Core\ApplicationPasswordsDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Core\CoreEndpointDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Core\JsonSanityDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Core\RestApiRegisteredDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Core\RestIndexDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Core\RestUrlDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticRegistry;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticRunner;
use WPCalibrate\RESTHealthRepair\Diagnostics\Http\HttpStatusHandlingDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Http\LoopbackDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Http\RedirectChainDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Http\SslCertificateDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Http\UrlConsistencyDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Rewrites\PermalinkStructureDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Rewrites\RestRouteFallbackDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Rewrites\RewriteRulesRestDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Rewrites\WebserverRewriteDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Security\BasicAuthDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Security\RestAccessRestrictionsDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Security\RestAuthErrorsDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\Security\SecurityChallengeDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\WooCommerce\StoreApiBlocksDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\WooCommerce\StoreApiPublicDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\WooCommerce\StoreNotDrivenDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\WooCommerce\WooCommerceActiveDiagnostic;
use WPCalibrate\RESTHealthRepair\Diagnostics\WooCommerce\WooCommerceNamespacesDiagnostic;
use WPCalibrate\RESTHealthRepair\Licensing\LicenseManager;
use WPCalibrate\RESTHealthRepair\Lifecycle\Migrator;
use WPCalibrate\RESTHealthRepair\Logging\Logger;
use WPCalibrate\RESTHealthRepair\Repairs\Cache\ClearDiagnosticCacheRepair;
use WPCalibrate\RESTHealthRepair\Repairs\RepairRegistry;
use WPCalibrate\RESTHealthRepair\Repairs\RepairRunner;
use WPCalibrate\RESTHealthRepair\Repairs\Rewrites\FlushRewriteRulesRepair;
use WPCalibrate\RESTHealthRepair\Repairs\Rewrites\HtaccessRepair;
use WPCalibrate\RESTHealthRepair\Repairs\Tasks\RepairScheduledTasksRepair;
use WPCalibrate\RESTHealthRepair\Reports\ReportBuilder;
use WPCalibrate\RESTHealthRepair\Reports\ReportExporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin class.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private DiagnosticRegistry $diagnostic_registry;
	private DiagnosticRunner $diagnostic_runner;
	private RepairRegistry $repair_registry;
	private RepairRunner $repair_runner;
	private ReportBuilder $report_builder;
	private ReportExporter $report_exporter;
	private LicenseManager $license_manager;
	private AdminMenu $admin_menu;
	private AdminPage $admin_page;
	private Assets $assets;
	private Notices $notices;
	private RestApiController $rest_controller;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->initialize_services();
		$this->register_hooks();
	}

	/**
	 * Gets singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initializes all plugin services, registries, and controllers.
	 *
	 * @return void
	 */
	private function initialize_services(): void {
		// Registries.
		$this->diagnostic_registry = new DiagnosticRegistry();
		$this->repair_registry     = new RepairRegistry();

		// Register core diagnostics.
		$this->diagnostic_registry->register( new RestApiRegisteredDiagnostic() );
		$this->diagnostic_registry->register( new RestUrlDiagnostic() );
		$this->diagnostic_registry->register( new RestIndexDiagnostic() );
		$this->diagnostic_registry->register( new CoreEndpointDiagnostic() );
		$this->diagnostic_registry->register( new JsonSanityDiagnostic() );
		$this->diagnostic_registry->register( new ApplicationPasswordsDiagnostic() );

		// Register HTTP / Network diagnostics.
		$this->diagnostic_registry->register( new LoopbackDiagnostic() );
		$this->diagnostic_registry->register( new SslCertificateDiagnostic() );
		$this->diagnostic_registry->register( new UrlConsistencyDiagnostic() );
		$this->diagnostic_registry->register( new RedirectChainDiagnostic() );
		$this->diagnostic_registry->register( new HttpStatusHandlingDiagnostic() );

		// Register Rewrite diagnostics.
		$this->diagnostic_registry->register( new PermalinkStructureDiagnostic() );
		$this->diagnostic_registry->register( new RewriteRulesRestDiagnostic() );
		$this->diagnostic_registry->register( new RestRouteFallbackDiagnostic() );
		$this->diagnostic_registry->register( new WebserverRewriteDiagnostic() );

		// Register Security / Interference diagnostics.
		$this->diagnostic_registry->register( new RestAuthErrorsDiagnostic() );
		$this->diagnostic_registry->register( new RestAccessRestrictionsDiagnostic() );
		$this->diagnostic_registry->register( new SecurityChallengeDiagnostic() );
		$this->diagnostic_registry->register( new BasicAuthDiagnostic() );

		// Register WooCommerce diagnostics (applicability checked within each class).
		$this->diagnostic_registry->register( new WooCommerceActiveDiagnostic() );
		$this->diagnostic_registry->register( new WooCommerceNamespacesDiagnostic() );
		$this->diagnostic_registry->register( new StoreApiPublicDiagnostic() );
		$this->diagnostic_registry->register( new StoreApiBlocksDiagnostic() );
		$this->diagnostic_registry->register( new StoreNotDrivenDiagnostic() );

		// Register Safe Repairs.
		$this->repair_registry->register( new FlushRewriteRulesRepair() );
		$this->repair_registry->register( new HtaccessRepair() );
		$this->repair_registry->register( new ClearDiagnosticCacheRepair() );
		$this->repair_registry->register( new RepairScheduledTasksRepair() );

		// Runners.
		$this->diagnostic_runner = new DiagnosticRunner( $this->diagnostic_registry );
		$this->repair_runner     = new RepairRunner( $this->repair_registry, $this->diagnostic_runner );

		// Reports & Licensing.
		$this->report_builder  = new ReportBuilder( $this->diagnostic_runner, $this->repair_runner );
		$this->report_exporter = new ReportExporter( $this->report_builder );
		$this->license_manager = new LicenseManager();

		// Admin & REST controllers.
		$this->admin_page      = new AdminPage(
			$this->diagnostic_registry,
			$this->diagnostic_runner,
			$this->repair_registry,
			$this->repair_runner,
			$this->report_builder,
			$this->license_manager
		);
		$this->admin_menu      = new AdminMenu( $this->admin_page );
		$this->assets          = new Assets();
		$this->notices         = new Notices();
		$this->rest_controller = new RestApiController(
			$this->diagnostic_registry,
			$this->diagnostic_runner,
			$this->repair_registry,
			$this->repair_runner
		);
	}

	/**
	 * Registers WordPress action and filter hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Translation & Migration.
		add_action( 'init', array( $this, 'on_init' ) );

		// WooCommerce Compatibility Declarations (HPOS & Blocks).
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );

		// Admin controllers.
		if ( is_admin() ) {
			$this->admin_menu->register_hooks();
			$this->admin_page->register_hooks();
			$this->assets->register_hooks();
			$this->notices->register_hooks();
			$this->report_exporter->register_hooks();
		}

		// Daily background maintenance.
		add_action( RepairScheduledTasksRepair::CRON_HOOK, array( $this, 'run_daily_maintenance' ) );
	}

	/**
	 * WordPress init hook handler.
	 *
	 * @return void
	 */
	public function on_init(): void {
		load_plugin_textdomain(
			'wpcalibrate-rest-health-repair',
			false,
			dirname( plugin_basename( WPCALIBRATE_RHR_FILE ) ) . '/languages'
		);

		// Schema upgrade check.
		Migrator::maybe_migrate();
	}

	/**
	 * Declares High-Performance Order Storage (HPOS) and Blocks compatibility.
	 *
	 * @return void
	 */
	public function declare_woocommerce_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			// HPOS Compatibility: We don't query order tables directly; standard WP APIs used.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				WPCALIBRATE_RHR_FILE,
				true
			);
			// Cart & Checkout Blocks Compatibility.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				WPCALIBRATE_RHR_FILE,
				true
			);
		}
	}

	/**
	 * Executes daily maintenance tasks (prunes expired logs).
	 *
	 * @return void
	 */
	public function run_daily_maintenance(): void {
		$settings       = Settings::get_all();
		$retention_days = (int) ( $settings['retention_days'] ?? 7 );
		Logger::prune_by_retention( $retention_days );
	}

	// Service Getters for testing and external access.
	public function get_diagnostic_registry(): DiagnosticRegistry {
		return $this->diagnostic_registry;
	}

	public function get_diagnostic_runner(): DiagnosticRunner {
		return $this->diagnostic_runner;
	}

	public function get_repair_registry(): RepairRegistry {
		return $this->repair_registry;
	}

	public function get_repair_runner(): RepairRunner {
		return $this->repair_runner;
	}

	public function get_license_manager(): LicenseManager {
		return $this->license_manager;
	}
}

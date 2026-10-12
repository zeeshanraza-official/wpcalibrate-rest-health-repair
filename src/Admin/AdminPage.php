<?php
/**
 * Admin page controller and view renderer.
 *
 * @package WPCalibrate\RESTHealthRepair
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace WPCalibrate\RESTHealthRepair\Admin;

use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticRegistry;
use WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticRunner;
use WPCalibrate\RESTHealthRepair\Licensing\LicenseManager;
use WPCalibrate\RESTHealthRepair\Logging\Logger;
use WPCalibrate\RESTHealthRepair\Repairs\RepairRegistry;
use WPCalibrate\RESTHealthRepair\Repairs\RepairRunner;
use WPCalibrate\RESTHealthRepair\Reports\ReportBuilder;
use WPCalibrate\RESTHealthRepair\Support\Capability;
use WPCalibrate\RESTHealthRepair\Support\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AdminPage class.
 */
final class AdminPage {

	private DiagnosticRegistry $diagnostic_registry;
	private DiagnosticRunner $diagnostic_runner;
	private RepairRegistry $repair_registry;
	private RepairRunner $repair_runner;
	private ReportBuilder $report_builder;
	private LicenseManager $license_manager;

	/**
	 * Constructor.
	 */
	public function __construct(
		DiagnosticRegistry $diagnostic_registry,
		DiagnosticRunner $diagnostic_runner,
		RepairRegistry $repair_registry,
		RepairRunner $repair_runner,
		ReportBuilder $report_builder,
		LicenseManager $license_manager
	) {
		$this->diagnostic_registry = $diagnostic_registry;
		$this->diagnostic_runner   = $diagnostic_runner;
		$this->repair_registry     = $repair_registry;
		$this->repair_runner       = $repair_runner;
		$this->report_builder      = $report_builder;
		$this->license_manager     = $license_manager;
	}

	/**
	 * Registers admin form submit hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_post_wpcalibrate_rhr_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_wpcalibrate_rhr_clear_logs', array( $this, 'handle_clear_logs' ) );
		add_action( 'admin_post_wpcalibrate_rhr_save_license', array( $this, 'handle_save_license' ) );
	}

	/**
	 * Handles settings form submission.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		Capability::check_or_die();
		check_admin_referer( 'wpcalibrate_rhr_settings_nonce' );

		$settings = array(
			'http_timeout'        => isset( $_POST['http_timeout'] ) ? absint( $_POST['http_timeout'] ) : 10,
			'logging_enabled'     => ! empty( $_POST['logging_enabled'] ),
			'retention_days'      => isset( $_POST['retention_days'] ) ? absint( $_POST['retention_days'] ) : 7,
			'delete_on_uninstall' => ! empty( $_POST['delete_on_uninstall'] ),
			'advanced_details'    => ! empty( $_POST['advanced_details'] ),
		);

		Settings::save( $settings );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => AdminMenu::PAGE_SLUG,
					'tab'     => 'settings',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handles clear logs submission.
	 *
	 * @return void
	 */
	public function handle_clear_logs(): void {
		Capability::check_or_die();
		check_admin_referer( 'wpcalibrate_rhr_clear_logs_nonce' );

		Logger::clear();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => AdminMenu::PAGE_SLUG,
					'tab'          => 'settings',
					'logs_cleared' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handles save license key submission.
	 *
	 * @return void
	 */
	public function handle_save_license(): void {
		Capability::check_or_die();
		check_admin_referer( 'wpcalibrate_rhr_license_nonce' );

		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		$this->license_manager->save_key( $key );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => AdminMenu::PAGE_SLUG,
					'tab'             => 'license',
					'license_updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Renders the main admin page with tabs.
	 *
	 * @return void
	 */
	public function render(): void {
		Capability::check_or_die();

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
		$valid_tabs  = array( 'overview', 'tests', 'woocommerce', 'repairs', 'report', 'settings', 'license', 'support' );
		if ( ! in_array( $current_tab, $valid_tabs, true ) ) {
			$current_tab = 'overview';
		}

		$summary = $this->diagnostic_runner->get_stored_summary();
		$results = $this->diagnostic_runner->get_cached_results() ?? array();

		// If no cached results yet, run non-blocking initial scan once.
		if ( empty( $results ) && 'overview' === $current_tab ) {
			$results = $this->diagnostic_runner->run_all( false );
			$summary = $this->diagnostic_runner->get_stored_summary();
		}
		?>
		<div class="wrap wpcalibrate-rhr-wrap" id="wpcalibrate-rhr-dashboard">
			<header class="wpcalibrate-rhr-header">
				<div class="wpcalibrate-rhr-branding">
					<img src="<?php echo esc_url( WPCALIBRATE_RHR_URL . 'branding/icon-dark.png' ); ?>" alt="WPCalibrate Logo" class="wpcalibrate-rhr-logo" width="32" height="32" />
					<div class="wpcalibrate-rhr-title-group">
						<h1><?php esc_html_e( 'REST Health & Repair', 'wpcalibrate-rest-health-repair' ); ?></h1>
						<span class="wpcalibrate-rhr-version-tag">v<?php echo esc_html( defined( 'WPCALIBRATE_RHR_VERSION' ) ? WPCALIBRATE_RHR_VERSION : '1.0.0' ); ?></span>
					</div>
				</div>
				<div class="wpcalibrate-rhr-header-actions">
					<button type="button" class="button button-primary" id="wpcalibrate-rhr-run-full-scan">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Run Full Diagnostic', 'wpcalibrate-rest-health-repair' ); ?>
					</button>
				</div>
			</header>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings updated successfully.', 'wpcalibrate-rest-health-repair' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['logs_cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Diagnostic logs cleared successfully.', 'wpcalibrate-rest-health-repair' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['license_updated'] ) ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'License key saved. External licensing integration is currently unconfigured.', 'wpcalibrate-rest-health-repair' ); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper wpcalibrate-rhr-nav-tabs" aria-label="<?php esc_attr_e( 'Navigation Tabs', 'wpcalibrate-rest-health-repair' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=overview' ) ); ?>" class="nav-tab <?php echo 'overview' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Overview', 'wpcalibrate-rest-health-repair' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=tests' ) ); ?>" class="nav-tab <?php echo 'tests' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-rest-api"></span> <?php esc_html_e( 'REST Tests', 'wpcalibrate-rest-health-repair' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=woocommerce' ) ); ?>" class="nav-tab <?php echo 'woocommerce' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'WooCommerce', 'wpcalibrate-rest-health-repair' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=repairs' ) ); ?>" class="nav-tab <?php echo 'repairs' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Repairs', 'wpcalibrate-rest-health-repair' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=report' ) ); ?>" class="nav-tab <?php echo 'report' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-media-document"></span> <?php esc_html_e( 'Diagnostic Report', 'wpcalibrate-rest-health-repair' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=settings' ) ); ?>" class="nav-tab <?php echo 'settings' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Settings', 'wpcalibrate-rest-health-repair' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=license' ) ); ?>" class="nav-tab <?php echo 'license' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-privacy"></span> <?php esc_html_e( 'License', 'wpcalibrate-rest-health-repair' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=support' ) ); ?>" class="nav-tab <?php echo 'support' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-sos"></span> <?php esc_html_e( 'Support', 'wpcalibrate-rest-health-repair' ); ?>
				</a>
			</nav>

			<main class="wpcalibrate-rhr-tab-content" id="wpcalibrate-rhr-main-content">
				<?php
				switch ( $current_tab ) {
					case 'tests':
						$this->render_tab_tests( $results );
						break;
					case 'woocommerce':
						$this->render_tab_woocommerce( $results );
						break;
					case 'repairs':
						$this->render_tab_repairs();
						break;
					case 'report':
						$this->render_tab_report();
						break;
					case 'settings':
						$this->render_tab_settings();
						break;
					case 'license':
						$this->render_tab_license();
						break;
					case 'support':
						$this->render_tab_support();
						break;
					default:
						$this->render_tab_overview( $summary, $results );
						break;
				}
				?>
			</main>
		</div>
		<?php
	}

	/**
	 * Renders Overview Tab.
	 *
	 * @param array $summary Summary counts.
	 * @param array $results Diagnostic results.
	 * @return void
	 */
	private function render_tab_overview( array $summary, array $results ): void {
		$last_scan = ! empty( $summary['last_scan'] ) ? wp_date( 'M j, Y g:i a', $summary['last_scan'] ) : __( 'Never', 'wpcalibrate-rest-health-repair' );
		?>
		<div class="wpcalibrate-rhr-overview-grid">
			<div class="wpcalibrate-rhr-card wpcalibrate-rhr-summary-card">
				<h2><?php esc_html_e( 'Diagnostic Status Summary', 'wpcalibrate-rest-health-repair' ); ?></h2>
				<p class="description">
					<?php
					printf(
						/* translators: %s: Last scan formatted date */
						esc_html__( 'Last scan executed: %s (Counts reflect empirical test results, not arbitrary percentage scores)', 'wpcalibrate-rest-health-repair' ),
						esc_html( $last_scan )
					);
					?>
				</p>
				<div class="wpcalibrate-rhr-badges-row">
					<div class="wpcalibrate-rhr-metric-badge badge-healthy">
						<span class="metric-count"><?php echo esc_html( (string) $summary['healthy'] ); ?></span>
						<span class="metric-label"><?php esc_html_e( 'Healthy', 'wpcalibrate-rest-health-repair' ); ?></span>
					</div>
					<div class="wpcalibrate-rhr-metric-badge badge-warning">
						<span class="metric-count"><?php echo esc_html( (string) $summary['warning'] ); ?></span>
						<span class="metric-label"><?php esc_html_e( 'Warnings', 'wpcalibrate-rest-health-repair' ); ?></span>
					</div>
					<div class="wpcalibrate-rhr-metric-badge badge-failed">
						<span class="metric-count"><?php echo esc_html( (string) $summary['failed'] ); ?></span>
						<span class="metric-label"><?php esc_html_e( 'Failed', 'wpcalibrate-rest-health-repair' ); ?></span>
					</div>
					<div class="wpcalibrate-rhr-metric-badge badge-na">
						<span class="metric-count"><?php echo esc_html( (string) $summary['not_applicable'] ); ?></span>
						<span class="metric-label"><?php esc_html_e( 'Not Applicable', 'wpcalibrate-rest-health-repair' ); ?></span>
					</div>
				</div>

				<div class="wpcalibrate-rhr-action-bar">
					<button type="button" class="button button-primary" id="wpcalibrate-rhr-scan-again">
						<?php esc_html_e( 'Run Full Diagnostic Now', 'wpcalibrate-rest-health-repair' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="wpcalibrate-rhr-rerun-failed">
						<?php esc_html_e( 'Re-run Failed & Warning Tests', 'wpcalibrate-rest-health-repair' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=report' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'View Sanitized Report', 'wpcalibrate-rest-health-repair' ); ?>
					</a>
				</div>
			</div>

			<div class="wpcalibrate-rhr-card wpcalibrate-rhr-env-card">
				<h2><?php esc_html_e( 'Key REST Infrastructure Signals', 'wpcalibrate-rest-health-repair' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'WordPress Core Version', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td><?php echo esc_html( Environment::get_wordpress_version() ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'PHP Version', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td><?php echo esc_html( Environment::get_php_version() ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Web Server', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td><?php echo esc_html( Environment::get_web_server() ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Permalink Structure', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td>
								<?php
								$structure = Environment::get_permalink_structure();
								if ( '' === $structure ) {
									echo '<span class="status-pill pill-warning">' . esc_html__( 'Plain (?p=123)', 'wpcalibrate-rest-health-repair' ) . '</span>';
								} else {
									echo '<span class="status-pill pill-healthy">' . esc_html( $structure ) . '</span>';
								}
								?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'HTTPS / SSL Status', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td>
								<?php echo Environment::is_ssl() ? '<span class="status-pill pill-healthy">' . esc_html__( 'HTTPS Active', 'wpcalibrate-rest-health-repair' ) . '</span>' : '<span class="status-pill pill-warning">' . esc_html__( 'HTTP (No SSL)', 'wpcalibrate-rest-health-repair' ) . '</span>'; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'WooCommerce Store API', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td>
								<?php
								if ( Environment::is_woocommerce_active() ) {
									echo '<span class="status-pill pill-healthy">' . sprintf( esc_html__( 'Active (v%s)', 'wpcalibrate-rest-health-repair' ), esc_html( (string) Environment::get_woocommerce_version() ) ) . '</span>';
								} else {
									echo '<span class="status-pill pill-na">' . esc_html__( 'WooCommerce Inactive (Optional)', 'wpcalibrate-rest-health-repair' ) . '</span>';
								}
								?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<?php if ( ! Environment::is_woocommerce_active() ) : ?>
			<div class="notice notice-info inline" style="margin-top: 20px;">
				<p>
					<strong><?php esc_html_e( 'WooCommerce is not active:', 'wpcalibrate-rest-health-repair' ); ?></strong>
					<?php esc_html_e( 'Core WordPress REST diagnostics are operating at full capability. WooCommerce-specific checks are automatically skipped.', 'wpcalibrate-rest-health-repair' ); ?>
				</p>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders REST Tests Tab.
	 *
	 * @param array $results Diagnostic results map.
	 * @return void
	 */
	private function render_tab_tests( array $results ): void {
		$diagnostics = $this->diagnostic_registry->all();
		?>
		<div class="wpcalibrate-rhr-tests-view">
			<div class="wpcalibrate-rhr-toolbar">
				<p class="description">
					<?php esc_html_e( 'Independent diagnostic evaluators inspecting REST endpoint availability, loopback connectivity, authentication filters, and URL rewrites.', 'wpcalibrate-rest-health-repair' ); ?>
				</p>
			</div>

			<div class="wpcalibrate-rhr-results-accordion">
				<?php
				foreach ( $diagnostics as $id => $diag ) {
					if ( 'woocommerce' === $diag->get_group() ) {
						continue; // Rendered in dedicated WooCommerce tab.
					}

					$item = $results[ $id ] ?? array(
						'status'  => 'skipped',
						'message' => __( 'Not yet tested.', 'wpcalibrate-rest-health-repair' ),
					);

					$this->render_diagnostic_card( $id, $diag, $item );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders WooCommerce Tab.
	 *
	 * @param array $results Diagnostic results.
	 * @return void
	 */
	private function render_tab_woocommerce( array $results ): void {
		?>
		<div class="wpcalibrate-rhr-wc-view">
			<?php if ( ! Environment::is_woocommerce_active() ) : ?>
				<div class="wpcalibrate-rhr-card">
					<h2><?php esc_html_e( 'WooCommerce Integration', 'wpcalibrate-rest-health-repair' ); ?></h2>
					<p><?php esc_html_e( 'WooCommerce is not installed or active on this WordPress site. Core WordPress REST diagnostics remain fully active.', 'wpcalibrate-rest-health-repair' ); ?></p>
					<p class="description"><?php esc_html_e( 'When WooCommerce is activated, this section unlocks Store API connectivity checks, HPOS readiness, and "Your store is not driven by WordPress" investigations.', 'wpcalibrate-rest-health-repair' ); ?></p>
				</div>
			<?php else : ?>
				<div class="wpcalibrate-rhr-card" style="margin-bottom: 20px;">
					<h2><?php esc_html_e( 'WooCommerce REST & Store API Health', 'wpcalibrate-rest-health-repair' ); ?></h2>
					<p class="description">
						<?php
						printf(
							/* translators: 1: Version, 2: HPOS */
							esc_html__( 'Installed: WooCommerce %1$s | HPOS: %2$s', 'wpcalibrate-rest-health-repair' ),
							esc_html( (string) Environment::get_woocommerce_version() ),
							esc_html( Environment::get_woocommerce_hpos_status() )
						);
						?>
					</p>
				</div>

				<div class="wpcalibrate-rhr-results-accordion">
					<?php
					$wc_diagnostics = $this->diagnostic_registry->get_by_group( 'woocommerce' );
					foreach ( $wc_diagnostics as $id => $diag ) {
						$item = $results[ $id ] ?? array(
							'status'  => 'skipped',
							'message' => __( 'Not yet tested.', 'wpcalibrate-rest-health-repair' ),
						);
						$this->render_diagnostic_card( $id, $diag, $item );
					}
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders a diagnostic result card.
	 *
	 * @param string                                                 $id Diagnostic ID.
	 * @param \WPCalibrate\RESTHealthRepair\Diagnostics\DiagnosticInterface $diag Diagnostic instance.
	 * @param array<string, mixed>                                   $item Result data.
	 * @return void
	 */
	private function render_diagnostic_card( string $id, object $diag, array $item ): void {
		$status = $item['status'] ?? 'skipped';
		$status_class = match ( $status ) {
			'healthy'        => 'status-healthy',
			'warning'        => 'status-warning',
			'failed'         => 'status-failed',
			'not_applicable' => 'status-na',
			default          => 'status-skipped',
		};

		$status_icon = match ( $status ) {
			'healthy'        => 'dashicons-yes-alt',
			'warning'        => 'dashicons-warning',
			'failed'         => 'dashicons-dismiss',
			'not_applicable' => 'dashicons-minus',
			default          => 'dashicons-clock',
		};

		$repair_id = $diag->get_repair_id();
		?>
		<div class="wpcalibrate-rhr-test-card <?php echo esc_attr( $status_class ); ?>" id="diag-card-<?php echo esc_attr( $id ); ?>">
			<div class="wpcalibrate-rhr-test-header">
				<div class="test-title-col">
					<span class="dashicons <?php echo esc_attr( $status_icon ); ?> status-indicator-icon"></span>
					<strong><?php echo esc_html( $diag->get_title() ); ?></strong>
					<span class="status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( strtoupper( $status ) ); ?></span>
				</div>
				<div class="test-action-col">
					<button type="button" class="button button-small wpcalibrate-rhr-single-test-btn" data-diag-id="<?php echo esc_attr( $id ); ?>">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Test', 'wpcalibrate-rest-health-repair' ); ?>
					</button>
				</div>
			</div>

			<div class="wpcalibrate-rhr-test-body">
				<p class="diag-message"><?php echo esc_html( $item['message'] ?? $diag->get_description() ); ?></p>

				<?php if ( ! empty( $item['likely_causes'] ) ) : ?>
					<div class="diag-causes-box">
						<strong><?php esc_html_e( 'Likely Causes:', 'wpcalibrate-rest-health-repair' ); ?></strong>
						<ul>
							<?php foreach ( (array) $item['likely_causes'] as $cause ) : ?>
								<li><?php echo esc_html( $cause ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $item['impact'] ) ) : ?>
					<p class="diag-impact"><strong><?php esc_html_e( 'Impact:', 'wpcalibrate-rest-health-repair' ); ?></strong> <?php echo esc_html( $item['impact'] ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $item['recommended_action'] ) ) : ?>
					<p class="diag-rec-action"><strong><?php esc_html_e( 'Recommended Action:', 'wpcalibrate-rest-health-repair' ); ?></strong> <?php echo esc_html( $item['recommended_action'] ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $item['manual_instructions'] ) ) : ?>
					<div class="diag-manual-guide">
						<strong><?php esc_html_e( 'Manual Step-by-Step Fix:', 'wpcalibrate-rest-health-repair' ); ?></strong>
						<pre><?php echo esc_html( $item['manual_instructions'] ); ?></pre>
					</div>
				<?php endif; ?>

				<?php if ( $repair_id && 'healthy' !== $status ) : ?>
					<div class="diag-repair-row">
						<button type="button" class="button button-primary wpcalibrate-rhr-auto-repair-btn" data-repair-id="<?php echo esc_attr( $repair_id ); ?>">
							<span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Auto Repair', 'wpcalibrate-rest-health-repair' ); ?>
						</button>
						<span class="description"><?php esc_html_e( 'Safe WordPress-level repair with verification and rollback.', 'wpcalibrate-rest-health-repair' ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $item['evidence'] ) ) : ?>
					<details class="diag-evidence-details">
						<summary><?php esc_html_e( 'View Technical Evidence', 'wpcalibrate-rest-health-repair' ); ?></summary>
						<pre><?php echo esc_html( (string) wp_json_encode( $item['evidence'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
					</details>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders Repairs Tab.
	 *
	 * @return void
	 */
	private function render_tab_repairs(): void {
		$repairs = $this->repair_registry->all();
		$history = $this->repair_runner->get_audit_history();
		?>
		<div class="wpcalibrate-rhr-repairs-view">
			<div class="wpcalibrate-rhr-card">
				<h2><?php esc_html_e( 'Available Safe Automatic Repairs', 'wpcalibrate-rest-health-repair' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Every automatic repair has an explicit safety classification, explains planned changes, includes rollback capability where practical, and is marked Fixed ONLY after re-testing passes.', 'wpcalibrate-rest-health-repair' ); ?>
				</p>

				<div class="wpcalibrate-rhr-repairs-list">
					<?php foreach ( $repairs as $id => $repair ) :
						$is_applicable        = $repair->is_applicable();
						$inapplicable_reason  = method_exists( $repair, 'get_inapplicability_reason' )
							? $repair->get_inapplicability_reason()
							: '';
					?>
						<div class="wpcalibrate-rhr-repair-card<?php echo $is_applicable ? '' : ' repair-inapplicable'; ?>" id="repair-box-<?php echo esc_attr( $id ); ?>">
							<div class="repair-header">
								<div class="repair-title-group">
									<h3><?php echo esc_html( $repair->get_title() ); ?></h3>
									<span class="risk-badge risk-<?php echo esc_attr( $repair->get_risk_level() ); ?>">
										<?php
										/* translators: %s: Risk level */
										printf( esc_html__( 'Risk: %s', 'wpcalibrate-rest-health-repair' ), esc_html( ucfirst( $repair->get_risk_level() ) ) );
										?>
									</span>
									<?php if ( ! $is_applicable ) : ?>
										<span class="status-badge" style="background:#fff3cd; color:#856404; font-weight:600; font-size:11px; padding:3px 8px; border-radius:4px; margin-left:8px;">
											<?php esc_html_e( 'Environment Restricted', 'wpcalibrate-rest-health-repair' ); ?>
										</span>
									<?php endif; ?>
								</div>
								<div class="repair-action-col">
									<?php if ( $is_applicable ) : ?>
										<button type="button" class="button button-primary wpcalibrate-rhr-execute-repair-btn" data-repair-id="<?php echo esc_attr( $id ); ?>">
											<?php esc_html_e( 'Execute Repair', 'wpcalibrate-rest-health-repair' ); ?>
										</button>
									<?php else : ?>
										<button type="button" class="button button-secondary wpcalibrate-rhr-execute-repair-btn" disabled="disabled" title="<?php echo esc_attr( $inapplicable_reason ); ?>">
											<?php esc_html_e( 'Not Applicable', 'wpcalibrate-rest-health-repair' ); ?>
										</button>
									<?php endif; ?>
									<?php if ( $repair->can_rollback() ) : ?>
										<button type="button" class="button button-secondary wpcalibrate-rhr-rollback-btn" data-repair-id="<?php echo esc_attr( $id ); ?>">
											<?php esc_html_e( 'Revert / Rollback', 'wpcalibrate-rest-health-repair' ); ?>
										</button>
									<?php endif; ?>
								</div>
							</div>
							<p><?php echo esc_html( $repair->get_description() ); ?></p>

							<?php if ( ! $is_applicable && ! empty( $inapplicable_reason ) ) : ?>
								<div style="margin: 8px 0 12px; padding: 8px 12px; background: #fff8e5; border-left: 4px solid #dba617; border-radius: 2px;">
									<p style="margin: 0; font-size: 13px; color: #614700;">
										<strong><?php esc_html_e( 'Environment Notice:', 'wpcalibrate-rest-health-repair' ); ?></strong>
										<?php echo esc_html( $inapplicable_reason ); ?>
									</p>
								</div>
							<?php endif; ?>

							<details class="repair-plan-details">
								<summary><?php esc_html_e( 'Planned Changes & Safeguards', 'wpcalibrate-rest-health-repair' ); ?></summary>
								<ul>
									<?php foreach ( $repair->get_planned_changes() as $change ) : ?>
										<li><?php echo esc_html( $change ); ?></li>
									<?php endforeach; ?>
								</ul>
							</details>
							<div class="repair-result-msg" style="display:none; margin-top:10px;"></div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="wpcalibrate-rhr-card" style="margin-top: 24px;">
				<h2><?php esc_html_e( 'Repair Audit History', 'wpcalibrate-rest-health-repair' ); ?></h2>
				<?php if ( empty( $history ) ) : ?>
					<p><?php esc_html_e( 'No repair actions have been performed yet.', 'wpcalibrate-rest-health-repair' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Timestamp', 'wpcalibrate-rest-health-repair' ); ?></th>
								<th><?php esc_html_e( 'Repair', 'wpcalibrate-rest-health-repair' ); ?></th>
								<th><?php esc_html_e( 'Result', 'wpcalibrate-rest-health-repair' ); ?></th>
								<th><?php esc_html_e( 'Details', 'wpcalibrate-rest-health-repair' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $history as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( gmdate( 'Y-m-d H:i:s', (int) ( $entry['timestamp'] ?? time() ) ) ); ?> UTC</td>
									<td><strong><?php echo esc_html( (string) ( $entry['repair_id'] ?? '' ) ); ?></strong></td>
									<td>
										<span class="status-badge status-<?php echo esc_attr( (string) ( $entry['status'] ?? 'unknown' ) ); ?>">
											<?php echo esc_html( strtoupper( (string) ( $entry['status'] ?? '' ) ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( (string) ( $entry['message'] ?? '' ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders Diagnostic Report Tab.
	 *
	 * @return void
	 */
	private function render_tab_report(): void {
		$text_preview = $this->report_builder->build_text_report();
		?>
		<div class="wpcalibrate-rhr-report-view">
			<div class="wpcalibrate-rhr-card">
				<h2><?php esc_html_e( 'Sanitized Support Diagnostic Report', 'wpcalibrate-rest-health-repair' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Generate a sanitized technical export suitable for sharing with WPCalibrate support or your hosting provider. All passwords, secrets, nonces, cookies, authorization headers, and absolute server paths are strictly redacted.', 'wpcalibrate-rest-health-repair' ); ?>
				</p>

				<div class="wpcalibrate-rhr-export-buttons" style="margin: 15px 0;">
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpcalibrate_rhr_export_report&format=txt' ), 'wpcalibrate_rhr_export_report_nonce' ) ); ?>" class="button button-primary">
						<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Download Text Report (.txt)', 'wpcalibrate-rest-health-repair' ); ?>
					</a>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpcalibrate_rhr_export_report&format=json' ), 'wpcalibrate_rhr_export_report_nonce' ) ); ?>" class="button button-secondary">
						<span class="dashicons dashicons-media-code"></span> <?php esc_html_e( 'Download JSON Report (.json)', 'wpcalibrate-rest-health-repair' ); ?>
					</a>
				</div>

				<h3><?php esc_html_e( 'Report Preview', 'wpcalibrate-rest-health-repair' ); ?></h3>
				<textarea class="large-text code" rows="18" readonly style="font-family: monospace; font-size: 12px;"><?php echo esc_textarea( $text_preview ); ?></textarea>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders Settings Tab.
	 *
	 * @return void
	 */
	private function render_tab_settings(): void {
		$settings = Settings::get_all();
		$logs     = Logger::get_raw_logs();
		?>
		<div class="wpcalibrate-rhr-settings-view">
			<div class="wpcalibrate-rhr-card">
				<h2><?php esc_html_e( 'Diagnostic & Performance Settings', 'wpcalibrate-rest-health-repair' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wpcalibrate_rhr_settings_nonce' ); ?>
					<input type="hidden" name="action" value="wpcalibrate_rhr_save_settings" />

					<table class="form-table">
						<tr>
							<th scope="row"><label for="http_timeout"><?php esc_html_e( 'HTTP Diagnostic Timeout', 'wpcalibrate-rest-health-repair' ); ?></label></th>
							<td>
								<input type="number" id="http_timeout" name="http_timeout" min="3" max="30" value="<?php echo esc_attr( (string) $settings['http_timeout'] ); ?>" class="small-text" />
								<span><?php esc_html_e( 'seconds (Allowlisted range: 3 - 30 seconds, default: 10)', 'wpcalibrate-rest-health-repair' ); ?></span>
								<p class="description"><?php esc_html_e( 'Maximum time allowed for loopback self-requests and REST root queries before timing out.', 'wpcalibrate-rest-health-repair' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Diagnostic Logging', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="logging_enabled" value="1" <?php checked( ! empty( $settings['logging_enabled'] ) ); ?> />
									<?php esc_html_e( 'Enable bounded diagnostic logging', 'wpcalibrate-rest-health-repair' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Stores diagnostic execution errors and repair audits (secrets are masked, maximum 100 entries, no customer data).', 'wpcalibrate-rest-health-repair' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="retention_days"><?php esc_html_e( 'Log Retention Period', 'wpcalibrate-rest-health-repair' ); ?></label></th>
							<td>
								<select id="retention_days" name="retention_days">
									<option value="7" <?php selected( 7, (int) $settings['retention_days'] ); ?>><?php esc_html_e( '7 days', 'wpcalibrate-rest-health-repair' ); ?></option>
									<option value="14" <?php selected( 14, (int) $settings['retention_days'] ); ?>><?php esc_html_e( '14 days', 'wpcalibrate-rest-health-repair' ); ?></option>
									<option value="30" <?php selected( 30, (int) $settings['retention_days'] ); ?>><?php esc_html_e( '30 days', 'wpcalibrate-rest-health-repair' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Advanced Details', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="advanced_details" value="1" <?php checked( ! empty( $settings['advanced_details'] ) ); ?> />
									<?php esc_html_e( 'Show technical evidence accordions by default', 'wpcalibrate-rest-health-repair' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Data Retention on Uninstall', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?> />
									<strong style="color: #d63638;"><?php esc_html_e( 'Delete all plugin data on uninstall', 'wpcalibrate-rest-health-repair' ); ?></strong>
								</label>
								<p class="description"><?php esc_html_e( 'By default, uninstall preserves audit logs and repair rollback metadata. Check this box if you want all plugin options, transients, and logs erased upon deletion.', 'wpcalibrate-rest-health-repair' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Settings', 'wpcalibrate-rest-health-repair' ) ); ?>
				</form>
			</div>

			<div class="wpcalibrate-rhr-card" style="margin-top: 24px;">
				<div style="display: flex; justify-content: space-between; align-items: center;">
					<h2><?php esc_html_e( 'Diagnostic Log Viewer', 'wpcalibrate-rest-health-repair' ); ?></h2>
					<?php if ( ! empty( $logs ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'wpcalibrate_rhr_clear_logs_nonce' ); ?>
							<input type="hidden" name="action" value="wpcalibrate_rhr_clear_logs" />
							<button type="submit" class="button button-secondary"><?php esc_html_e( 'Clear Logs', 'wpcalibrate-rest-health-repair' ); ?></button>
						</form>
					<?php endif; ?>
				</div>

				<?php if ( empty( $logs ) ) : ?>
					<p><?php esc_html_e( 'No diagnostic log entries recorded.', 'wpcalibrate-rest-health-repair' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Time', 'wpcalibrate-rest-health-repair' ); ?></th>
								<th><?php esc_html_e( 'Level', 'wpcalibrate-rest-health-repair' ); ?></th>
								<th><?php esc_html_e( 'Message', 'wpcalibrate-rest-health-repair' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $logs, 0, 50 ) as $log ) : ?>
								<tr>
									<td><?php echo esc_html( gmdate( 'Y-m-d H:i:s', (int) ( $log['timestamp'] ?? time() ) ) ); ?></td>
									<td>
										<span class="status-badge level-<?php echo esc_attr( (string) ( $log['level'] ?? 'info' ) ); ?>">
											<?php echo esc_html( strtoupper( (string) ( $log['level'] ?? 'INFO' ) ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( (string) ( $log['message'] ?? '' ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders License Tab.
	 *
	 * @return void
	 */
	private function render_tab_license(): void {
		$masked_key = $this->license_manager->get_masked_key();
		$status     = $this->license_manager->get_status();
		$message    = $this->license_manager->get_status_message();
		?>
		<div class="wpcalibrate-rhr-license-view">
			<div class="wpcalibrate-rhr-card">
				<h2><?php esc_html_e( 'WPCalibrate Plugin License Management', 'wpcalibrate-rest-health-repair' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Configure commercial licensing credentials for automatic updates and support.', 'wpcalibrate-rest-health-repair' ); ?>
				</p>

				<div class="notice notice-info inline" style="margin: 15px 0;">
					<p>
						<strong><?php esc_html_e( 'Development Status:', 'wpcalibrate-rest-health-repair' ); ?></strong>
						<?php echo esc_html( $message ); ?>
					</p>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wpcalibrate_rhr_license_nonce' ); ?>
					<input type="hidden" name="action" value="wpcalibrate_rhr_save_license" />

					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'License Status', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td>
								<span class="status-badge status-warning"><?php echo esc_html( strtoupper( $status ) ); ?></span>
								<p class="description"><?php esc_html_e( 'No external server requests are made until a live licensing provider is configured.', 'wpcalibrate-rest-health-repair' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="license_key"><?php esc_html_e( 'License Key', 'wpcalibrate-rest-health-repair' ); ?></label></th>
							<td>
								<input type="text" id="license_key" name="license_key" value="<?php echo esc_attr( $masked_key ); ?>" class="regular-text" placeholder="XXXX-XXXX-XXXX-XXXX" />
								<p class="description"><?php esc_html_e( 'Keys are securely stored and masked to protect credentials.', 'wpcalibrate-rest-health-repair' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save License Key', 'wpcalibrate-rest-health-repair' ) ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders Support Tab.
	 *
	 * @return void
	 */
	private function render_tab_support(): void {
		?>
		<div class="wpcalibrate-rhr-support-view">
			<div class="wpcalibrate-rhr-card">
				<h2><?php esc_html_e( 'WPCalibrate Support & Inquiries', 'wpcalibrate-rest-health-repair' ); ?></h2>
				<p><?php esc_html_e( 'Need help diagnosing complex REST failures, reverse proxy issues, or WooCommerce Store API blocking? Reach out to our engineering team.', 'wpcalibrate-rest-health-repair' ); ?></p>

				<table class="widefat striped" style="margin: 20px 0;">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Vendor', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td><strong>WPCalibrate</strong></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Official Website', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td><a href="https://wpcalibrate.com" target="_blank" rel="noopener noreferrer">https://wpcalibrate.com</a></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Marketplace', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td><a href="https://marketplace.wpcalibrate.com/" target="_blank" rel="noopener noreferrer">https://marketplace.wpcalibrate.com/</a></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Technical Support Email', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td><a href="mailto:support@wpcalibrate.com">support@wpcalibrate.com</a></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Phone / WhatsApp Direct', 'wpcalibrate-rest-health-repair' ); ?></th>
							<td><a href="https://wa.me/447474795976" target="_blank" rel="noopener noreferrer">+447474795976</a></td>
						</tr>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'Quick Troubleshooting FAQ', 'wpcalibrate-rest-health-repair' ); ?></h3>
				<div class="wpcalibrate-rhr-faq-accordion">
					<details>
						<summary><strong><?php esc_html_e( 'Why does my site report "Your store is not driven by WordPress"?', 'wpcalibrate-rest-health-repair' ); ?></strong></summary>
						<p><?php esc_html_e( 'This occurs when an external service probes /wp-json/ and receives an HTML page, Cloudflare challenge, 404 rewrite failure, or 401 authentication prompt instead of standard WordPress JSON. It does not mean your site is not WordPress; it means the external probe was blocked from reading the REST root.', 'wpcalibrate-rest-health-repair' ); ?></p>
					</details>
					<details>
						<summary><strong><?php esc_html_e( 'Why does /wp-json/ return 404 while the rest of the site works?', 'wpcalibrate-rest-health-repair' ); ?></strong></summary>
						<p><?php esc_html_e( 'WordPress rewrite rules are missing the ^wp-json/ routing directive, or your web server (.htaccess on Apache or try_files on Nginx) is not passing /wp-json/ to index.php. Use the "Flush Rewrite Rules" repair button on the Repairs tab.', 'wpcalibrate-rest-health-repair' ); ?></p>
					</details>
					<details>
						<summary><strong><?php esc_html_e( 'Why does Store API report HTTP 401 or 403?', 'wpcalibrate-rest-health-repair' ); ?></strong></summary>
						<p><?php esc_html_e( 'A security plugin, membership plugin, or rest_authentication_errors filter is locking down all REST endpoints to logged-in users only. Guest customers need public read access to /wp-json/wc/store/ to calculate shipping and view cart contents.', 'wpcalibrate-rest-health-repair' ); ?></p>
					</details>
				</div>
			</div>
		</div>
		<?php
	}
}

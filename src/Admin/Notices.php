<?php
/**
 * Admin notices controller.
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
 * Notices class.
 */
final class Notices {

	public const NOTICE_DISMISSED_KEY = 'wpcalibrate_rhr_notice_dismissed';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_notices', array( $this, 'render_first_run_notice' ) );
		add_action( 'wp_ajax_wpcalibrate_rhr_dismiss_notice', array( $this, 'handle_dismiss_notice' ) );
	}

	/**
	 * Renders first-run onboarding notice if not dismissed.
	 *
	 * @return void
	 */
	public function render_first_run_notice(): void {
		if ( ! Capability::current_user_can() ) {
			return;
		}

		$current_screen = get_current_screen();
		if ( $current_screen && str_contains( $current_screen->id, AdminMenu::PAGE_SLUG ) ) {
			return; // Don't show inside the plugin page itself.
		}

		$dismissed = get_user_meta( get_current_user_id(), self::NOTICE_DISMISSED_KEY, true );
		if ( $dismissed ) {
			return;
		}

		$url = admin_url( 'admin.php?page=' . AdminMenu::PAGE_SLUG );
		?>
		<div class="notice notice-info is-dismissible wpcalibrate-rhr-first-run-notice" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpcalibrate_rhr_dismiss_notice' ) ); ?>">
			<p>
				<strong><?php esc_html_e( 'WPCalibrate REST Health & Repair:', 'wpcalibrate-rest-health-repair' ); ?></strong>
				<?php esc_html_e( 'Your WordPress REST API diagnostic suite is ready.', 'wpcalibrate-rest-health-repair' ); ?>
				<a href="<?php echo esc_url( $url ); ?>" class="button button-secondary button-small" style="margin-left: 8px;">
					<?php esc_html_e( 'Run REST Diagnostics', 'wpcalibrate-rest-health-repair' ); ?>
				</a>
			</p>
		</div>
		<script>
		(function($){
			$(document).on('click', '.wpcalibrate-rhr-first-run-notice .notice-dismiss', function(){
				var nonce = $('.wpcalibrate-rhr-first-run-notice').data('nonce');
				$.post(ajaxurl, {
					action: 'wpcalibrate_rhr_dismiss_notice',
					nonce: nonce
				});
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Handles dismiss notice AJAX.
	 *
	 * @return void
	 */
	public function handle_dismiss_notice(): void {
		Capability::check_or_die();
		check_ajax_referer( 'wpcalibrate_rhr_dismiss_notice', 'nonce' );

		update_user_meta( get_current_user_id(), self::NOTICE_DISMISSED_KEY, 1 );
		wp_send_json_success();
	}
}

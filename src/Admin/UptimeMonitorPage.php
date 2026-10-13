<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\Uptime\UptimeMonitor;

/**
 * Admin screen for the Domain & Uptime Monitor. The page is a thin server-rendered
 * shell; the monitor list, add/edit form and "check now" actions are driven by
 * assets/js/uptime-monitor.js talking to the /seoistic/v1/uptime REST routes.
 */
final class UptimeMonitorPage {

	public const PAGE_SLUG = 'seoistic-uptime';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	private function capability(): string {
		return (string) apply_filters( 'seoistic/uptime_capability', 'manage_options' );
	}

	public function add_submenu(): void {
		add_submenu_page(
			'seoistic',
			__( 'Uptime Monitor', 'seoistic' ),
			__( 'Uptime Monitor', 'seoistic' ),
			$this->capability(),
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'seoistic-admin', SEOISTIC_URL . 'assets/css/admin.css', array(), SEOISTIC_VERSION );
		wp_enqueue_script(
			'seoistic-uptime',
			SEOISTIC_URL . 'assets/js/uptime-monitor.js',
			array( 'wp-api-fetch', 'wp-i18n' ),
			SEOISTIC_VERSION,
			true
		);
		wp_set_script_translations( 'seoistic-uptime', 'seoistic' );
	}

	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'Unauthorized', 'seoistic' ) );
		}
		$retention = UptimeMonitor::settings()['retention_days'];
		?>
		<div class="wrap seoistic-page seoistic-uptime">
			<h1><?php esc_html_e( 'Domain & Uptime Monitor', 'seoistic' ); ?></h1>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: retention in days. */
						__( 'SEOISTIC checks each monitored URL on its own interval and emails you when it goes down or recovers. Check history is kept for %d days.', 'seoistic' ),
						$retention
					)
				);
				?>
			</p>

			<div class="seoistic-uptime-toolbar" style="margin:16px 0;">
				<button type="button" class="button button-primary" id="seoistic-uptime-add">
					<?php esc_html_e( 'Add monitor', 'seoistic' ); ?>
				</button>
			</div>

			<form id="seoistic-uptime-form" class="seoistic-card" hidden aria-label="<?php esc_attr_e( 'Monitor details', 'seoistic' ); ?>" style="padding:16px;margin-bottom:16px;">
				<input type="hidden" name="id" value="" />
				<p>
					<label for="seoistic-uptime-label"><strong><?php esc_html_e( 'Label', 'seoistic' ); ?></strong></label><br />
					<input type="text" id="seoistic-uptime-label" name="label" class="regular-text" />
				</p>
				<p>
					<label for="seoistic-uptime-url"><strong><?php esc_html_e( 'URL to monitor', 'seoistic' ); ?></strong></label><br />
					<input type="url" id="seoistic-uptime-url" name="url" class="regular-text" placeholder="https://example.com" required />
				</p>
				<p>
					<label for="seoistic-uptime-interval"><?php esc_html_e( 'Check every (minutes)', 'seoistic' ); ?></label>
					<input type="number" id="seoistic-uptime-interval" name="interval_minutes" value="5" min="5" max="1440" step="1" />
					&nbsp;
					<label for="seoistic-uptime-timeout"><?php esc_html_e( 'Timeout (seconds)', 'seoistic' ); ?></label>
					<input type="number" id="seoistic-uptime-timeout" name="timeout_seconds" value="10" min="1" max="30" step="1" />
				</p>
				<p>
					<label for="seoistic-uptime-method"><?php esc_html_e( 'Method', 'seoistic' ); ?></label>
					<select id="seoistic-uptime-method" name="method">
						<option value="GET">GET</option>
						<option value="HEAD">HEAD</option>
					</select>
					&nbsp;
					<label for="seoistic-uptime-expected"><?php esc_html_e( 'Expected HTTP code (0 = any 2xx/3xx)', 'seoistic' ); ?></label>
					<input type="number" id="seoistic-uptime-expected" name="expected_code" value="0" min="0" max="599" step="1" />
				</p>
				<p>
					<label for="seoistic-uptime-keyword"><?php esc_html_e( 'Body must contain (optional)', 'seoistic' ); ?></label><br />
					<input type="text" id="seoistic-uptime-keyword" name="keyword" class="regular-text" />
				</p>
				<p>
					<label for="seoistic-uptime-email"><?php esc_html_e( 'Alert email (blank = site admin)', 'seoistic' ); ?></label><br />
					<input type="email" id="seoistic-uptime-email" name="notify_email" class="regular-text" />
				</p>
				<p>
					<label><input type="checkbox" id="seoistic-uptime-enabled" name="enabled" checked /> <?php esc_html_e( 'Enabled', 'seoistic' ); ?></label>
				</p>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save monitor', 'seoistic' ); ?></button>
					<button type="button" class="button" id="seoistic-uptime-cancel"><?php esc_html_e( 'Cancel', 'seoistic' ); ?></button>
				</p>
			</form>

			<div id="seoistic-uptime-message" role="status" aria-live="polite"></div>

			<table class="wp-list-table widefat fixed striped" id="seoistic-uptime-table">
				<caption class="screen-reader-text"><?php esc_html_e( 'Monitored domains', 'seoistic' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Status', 'seoistic' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Monitor', 'seoistic' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Uptime (30d)', 'seoistic' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Response', 'seoistic' ); ?></th>
						<th scope="col"><?php esc_html_e( 'SSL expires', 'seoistic' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last checked', 'seoistic' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'seoistic' ); ?></th>
					</tr>
				</thead>
				<tbody id="seoistic-uptime-rows">
					<tr><td colspan="7"><?php esc_html_e( 'Loading…', 'seoistic' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}

<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\Core\ScheduledAudit;

/**
 * SEOISTIC → Settings → Automation. Renders as a tab inside Admin::settings().
 * Off by default — enabling it schedules ScheduledAudit's WP-Cron event.
 */
final class AutomationSettingsPage {

	public function register(): void {
		add_action( 'admin_post_seoistic_save_automation_settings', array( $this, 'save' ) );
	}

	public function render_fields(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = ScheduledAudit::settings();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="seoistic_save_automation_settings">
			<?php wp_nonce_field( 'seoistic_automation_settings' ); ?>
			<div class="seoistic-table-wrap">
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Scheduled Site Audits', 'seoistic' ); ?></th>
					<td>
						<label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Automatically re-score every published page on a schedule', 'seoistic' ); ?></label>
						<p class="description"><?php esc_html_e( 'Runs unattended via WP-Cron — no need to click "Run Site Audit" yourself. You\'ll get an email digest only when pages newly drop below your threshold, not every run.', 'seoistic' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="seoistic_automation_frequency"><?php esc_html_e( 'Frequency', 'seoistic' ); ?></label></th>
					<td>
						<select id="seoistic_automation_frequency" name="frequency">
							<option value="daily" <?php selected( $s['frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'seoistic' ); ?></option>
							<option value="weekly" <?php selected( $s['frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'seoistic' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="seoistic_automation_threshold"><?php esc_html_e( 'Score threshold', 'seoistic' ); ?></label></th>
					<td>
						<input type="number" id="seoistic_automation_threshold" name="score_threshold" min="0" max="100" step="5" value="<?php echo esc_attr( (string) $s['score_threshold'] ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'Pages that drop below this score (out of 100) trigger the digest email.', 'seoistic' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="seoistic_automation_email"><?php esc_html_e( 'Notify email', 'seoistic' ); ?></label></th>
					<td>
						<input type="email" id="seoistic_automation_email" name="notify_email" value="<?php echo esc_attr( $s['notify_email'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Leave blank to use the site admin email.', 'seoistic' ); ?></p>
					</td>
				</tr>
			</table>
			</div>
			<?php submit_button( __( 'Save Automation settings', 'seoistic' ) ); ?>
		</form>
		<?php
	}

	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_automation_settings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}

		ScheduledAudit::save(
			isset( $_POST['enabled'] ),
			sanitize_key( wp_unslash( $_POST['frequency'] ?? 'weekly' ) ),
			(int) ( $_POST['score_threshold'] ?? 70 ),
			sanitize_email( wp_unslash( $_POST['notify_email'] ?? '' ) )
		);

		wp_safe_redirect( add_query_arg( array( 'updated' => '1', 'tab' => 'automation' ), admin_url( 'admin.php?page=seoistic-settings' ) ) );
		exit;
	}
}

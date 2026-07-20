<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\License;

use Wpistic\Seoistic\Admin\View;
use Wpistic\Seoistic\Core\Links;
use Wpistic\Seoistic\Module\Entitlement;

/**
 * License screen + the entitlement bridge. When a Licenseistic license is valid,
 * premium addons unlock through the `seoistic/entitlement` filter. A daily cron
 * re-validates. This is the ONLY place premium licensing lives.
 *
 * The inactive form has exactly two controls (license key, Activate) — no
 * server/product-ID fields; those are documented deployment constants now
 * (see LicenseClient). The server is always the source of truth: the UI only
 * ever reflects what LicenseClient::is_valid() / status() already decided.
 */
final class License {

	private LicenseClient $client;

	public function __construct() {
		$this->client = new LicenseClient();
	}

	public function register(): void {
		// Entitlement is computed in Module\Entitlement from the cached license; this
		// module keeps that cache fresh and provides the activation UI.
		add_action( 'seoistic_license_cron', array( $this, 'cron' ) );
		add_action( 'admin_menu', array( $this, 'menu' ), 22 );
		add_action( 'admin_post_seoistic_license', array( $this, 'handle' ) );

		if ( ! wp_next_scheduled( 'seoistic_license_cron' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'seoistic_license_cron' );
		}
	}

	public function cron(): void {
		$this->client->validate();
	}

	public function menu(): void {
		add_submenu_page( 'seoistic', __( 'License', 'seoistic' ), __( 'License', 'seoistic' ), 'manage_options', 'seoistic-license', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$valid = $this->client->is_valid();

		View::header( 'seoistic-license', __( 'SEOISTIC License', 'seoistic' ), __( 'Your license unlocks the premium addons — activation and validation happen through the WPistic licensing service.', 'seoistic' ) );

		$this->render_notice();

		if ( $valid ) {
			$this->render_active();
		} else {
			$this->render_inactive();
		}

		View::footer();
	}

	/**
	 * A one-time, short-lived notice from the last activate/deactivate action —
	 * stored server-side (never in the URL) so we never reflect arbitrary text
	 * back into the page from a query string.
	 */
	private function render_notice(): void {
		$notice_key = $this->notice_transient_key();
		$notice     = get_transient( $notice_key );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( $notice_key );

		$code = (string) ( $notice['code'] ?? '' );
		$tone = in_array( $code, array( 'activated', 'deactivated' ), true ) ? 'success' : ( 'rate_limited' === $code ? 'info' : 'error' );
		$text = $this->status_message( $code );
		if ( '' !== $text && '' !== (string) ( $notice['detail'] ?? '' ) && 'failed' === $code ) {
			/* translators: %s: the license server's own short error message. */
			$text .= ' ' . sprintf( __( '(%s)', 'seoistic' ), $notice['detail'] );
		}
		if ( '' === $text ) {
			return;
		}

		echo '<div class="seoistic-license-notice is-' . esc_attr( $tone ) . '" role="status">' . esc_html( $text ) . '</div>';
	}

	private function status_message( string $code ): string {
		return match ( $code ) {
			'activated'    => __( 'License activated — premium features are now unlocked.', 'seoistic' ),
			'deactivated'  => __( 'License deactivated.', 'seoistic' ),
			'rate_limited' => __( 'Too many attempts. Please wait a few minutes and try again.', 'seoistic' ),
			'network'      => __( 'Could not reach the license server. Check your site’s outbound connections and try again shortly.', 'seoistic' ),
			'bad_response' => __( 'The license server returned an unexpected response. Please try again shortly.', 'seoistic' ),
			'empty_key'    => __( 'Enter a license key first.', 'seoistic' ),
			'failed'       => __( 'That license key could not be activated. Double-check the key and try again.', 'seoistic' ),
			default        => '',
		};
	}

	/* -------------------------------------------------------------- */
	/* Rendering                                                        */
	/* -------------------------------------------------------------- */

	private function render_inactive(): void {
		?>
		<div class="seoistic-license-card">
			<p><?php esc_html_e( 'Enter your license key to unlock the premium addons.', 'seoistic' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="seoistic_license">
				<input type="hidden" name="do" value="activate">
				<?php wp_nonce_field( 'seoistic_license' ); ?>
				<div class="seoistic-license-field">
					<label for="seoistic_license_key"><?php esc_html_e( 'License key', 'seoistic' ); ?></label>
					<input type="text" id="seoistic_license_key" name="license_key" class="regular-text" value="" autocomplete="off" required>
				</div>
				<div class="seoistic-license-actions">
					<button type="submit" class="seoistic-btn seoistic-btn-primary"><?php esc_html_e( 'Activate License', 'seoistic' ); ?></button>
				</div>
			</form>
			<a class="seoistic-license-account-link" href="<?php echo esc_url( Links::account_url() ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Get or manage your license', 'seoistic' ); ?> <span class="dashicons dashicons-external" aria-hidden="true" style="font-size:13px;width:13px;height:13px;vertical-align:-1px;"></span>
			</a>
		</div>
		<?php
	}

	private function render_active(): void {
		$expires = $this->client->expires();
		$meta    = $this->client->meta();
		$sites   = $meta['sites_used'] ?? $meta['site_count'] ?? null;
		$allowed = $meta['sites_allowed'] ?? null;
		?>
		<div class="seoistic-license-card">
			<div class="seoistic-license-status-row">
				<?php echo View::badge( __( 'Active', 'seoistic' ), 'good' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="seoistic-license-key-display"><?php echo esc_html( $this->client->masked_key() ); ?></span>
			</div>

			<div class="seoistic-license-meta">
				<div class="seoistic-license-meta-row">
					<span class="seoistic-license-meta-label"><?php esc_html_e( 'Plan', 'seoistic' ); ?></span>
					<span class="seoistic-license-meta-value"><?php echo esc_html( ucfirst( Entitlement::plan() ) ); ?></span>
				</div>
				<?php if ( '' !== $expires ) : ?>
					<div class="seoistic-license-meta-row">
						<span class="seoistic-license-meta-label"><?php esc_html_e( 'Renews / expires', 'seoistic' ); ?></span>
						<span class="seoistic-license-meta-value"><?php echo esc_html( mysql2date( get_option( 'date_format', 'Y-m-d' ), $expires ) ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( is_numeric( $sites ) ) : ?>
					<div class="seoistic-license-meta-row">
						<span class="seoistic-license-meta-label"><?php esc_html_e( 'Sites', 'seoistic' ); ?></span>
						<span class="seoistic-license-meta-value"><?php echo esc_html( is_numeric( $allowed ) ? sprintf( '%d / %d', (int) $sites, (int) $allowed ) : (string) (int) $sites ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( '' !== $this->client->last_validated() ) : ?>
					<div class="seoistic-license-meta-row">
						<span class="seoistic-license-meta-label"><?php esc_html_e( 'Last validated', 'seoistic' ); ?></span>
						<span class="seoistic-license-meta-value"><?php echo esc_html( $this->client->last_validated() ); ?></span>
					</div>
				<?php endif; ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="seoistic_license">
				<input type="hidden" name="do" value="deactivate">
				<?php wp_nonce_field( 'seoistic_license' ); ?>
				<div class="seoistic-license-actions">
					<button type="submit" class="seoistic-btn seoistic-btn-ghost" data-seoistic-confirm="<?php esc_attr_e( 'Deactivate this license? Premium addons will turn off on this site until you activate again.', 'seoistic' ); ?>"><?php esc_html_e( 'Deactivate License', 'seoistic' ); ?></button>
				</div>
			</form>
			<a class="seoistic-license-account-link" href="<?php echo esc_url( Links::account_url() ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Manage account', 'seoistic' ); ?> <span class="dashicons dashicons-external" aria-hidden="true" style="font-size:13px;width:13px;height:13px;vertical-align:-1px;"></span>
			</a>
		</div>
		<?php
	}

	/* -------------------------------------------------------------- */
	/* Handling                                                         */
	/* -------------------------------------------------------------- */

	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_license' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
		$detail = '';

		if ( 'activate' === $action ) {
			$key = $this->normalize_key( sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) ) );

			if ( '' === $key ) {
				$code = 'empty_key';
			} elseif ( $this->client->is_rate_limited() ) {
				$code = 'rate_limited';
			} else {
				$this->client->record_attempt();
				$result = $this->client->activate( $key );
				$code   = ! empty( $result['success'] ) ? 'activated' : ( '' !== ( $result['code'] ?? '' ) ? (string) $result['code'] : 'failed' );
				$detail = (string) ( $result['message'] ?? '' );
			}
		} elseif ( 'deactivate' === $action ) {
			$this->client->deactivate();
			$code = 'deactivated';
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=seoistic-license' ) );
			exit;
		}

		set_transient(
			$this->notice_transient_key(),
			array( 'code' => $code, 'detail' => mb_substr( $detail, 0, 200 ) ),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-license&seoistic_license=1' ) );
		exit;
	}

	private function notice_transient_key(): string {
		return 'seoistic_license_notice_' . get_current_user_id();
	}

	/**
	 * Trims whitespace and strips accidental internal whitespace/line breaks
	 * from a pasted key — never alters case, since key formats may be
	 * case-sensitive.
	 */
	private function normalize_key( string $key ): string {
		return trim( (string) preg_replace( '/\s+/', '', $key ) );
	}
}

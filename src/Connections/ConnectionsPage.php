<?php
/**
 * SEOISTIC → Connections. One screen, one license, one Google connect:
 * GA4 + Search Console + PageSpeed. States: (1) not licensed → prompt,
 * (2) licensed, not connected → OAuth client + Connect Google,
 * (3) connected → pick property/site, see GA4 + GSC snapshot, run PSI.
 *
 * @package Seoistic
 */

declare(strict_types=1);

namespace Wpistic\Seoistic\Connections;

use Wpistic\Seoistic\Admin\View;
use Wpistic\Seoistic\License\LicenseClient;
use Wpistic\Seoistic\Module\Entitlement as ModuleEntitlement;

final class ConnectionsPage {

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 26 );
		add_action( 'admin_post_seoistic_connections_save_client', array( $this, 'save_client' ) );
		add_action( 'admin_post_seoistic_connections_callback', array( $this, 'oauth_callback' ) );
		add_action( 'admin_post_seoistic_connections_save_selection', array( $this, 'save_selection' ) );
		add_action( 'admin_post_seoistic_connections_disconnect', array( $this, 'disconnect' ) );
		add_action( 'wp_ajax_seoistic_connections_psi', array( $this, 'ajax_psi' ) );
		add_action( 'wp_ajax_seoistic_connections_refresh', array( $this, 'ajax_refresh' ) );
	}

	public function menu(): void {
		add_submenu_page( 'seoistic', __( 'Connections', 'seoistic' ), __( 'Connections', 'seoistic' ), 'manage_options', 'seoistic-connections', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		View::header( 'seoistic-connections', __( 'Connections', 'seoistic' ), __( 'GA4, Search Console and PageSpeed — one license key, one Google connect, everything inside SEOISTIC.', 'seoistic' ) );

		if ( ! ModuleEntitlement::can( 'connections', 'premium' ) ) {
			$this->render_license_prompt();
			View::footer();
			return;
		}

		if ( ! ConnectionsSettings::is_connected() ) {
			$this->render_setup();
			View::footer();
			return;
		}

		$this->render_dashboard();
		View::footer();
	}

	private function render_license_prompt(): void {
		$license = new LicenseClient();
		?>
		<div class="seoistic-card">
			<h2><?php esc_html_e( 'Unlock Connections', 'seoistic' ); ?></h2>
			<p><?php esc_html_e( 'Connections is part of the Pro plan: activate your WPistic license key to connect GA4, Search Console and PageSpeed in one place.', 'seoistic' ); ?></p>
			<p><code><?php echo esc_html( $license->masked_key() ); ?></code> — <?php echo esc_html( $license->is_valid() ? __( 'license active, but a higher plan is required for this module.', 'seoistic' ) : __( 'no active license.', 'seoistic' ) ); ?></p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=seoistic-license' ) ); ?>"><?php esc_html_e( 'Activate license', 'seoistic' ); ?></a>
		</div>
		<?php
	}

	private function render_setup(): void {
		$client_id = ConnectionsSettings::client_id();
		?>
		<div class="seoistic-card">
			<h2><?php esc_html_e( 'Connect Google (GA4 + Search Console)', 'seoistic' ); ?></h2>
			<p><?php esc_html_e( 'One OAuth client, one click. PageSpeed needs no Google setup at all.', 'seoistic' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="seoistic_connections_save_client" />
				<?php wp_nonce_field( 'seoistic_connections_save_client' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="seoistic-conn-client-id"><?php esc_html_e( 'Google OAuth Client ID', 'seoistic' ); ?></label></th>
						<td><input id="seoistic-conn-client-id" name="client_id" type="text" class="regular-text" value="<?php echo esc_attr( $client_id ); ?>" placeholder="<?php esc_attr_e( 'xxxx.apps.googleusercontent.com', 'seoistic' ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="seoistic-conn-client-secret"><?php esc_html_e( 'Client Secret', 'seoistic' ); ?></label></th>
						<td>
							<input id="seoistic-conn-client-secret" name="client_secret" type="password" class="regular-text" autocomplete="new-password" placeholder="<?php echo ConnectionsSettings::client_secret() ? esc_attr__( 'saved — leave blank to keep', 'seoistic' ) : ''; ?>" />
							<p class="description"><?php esc_html_e( 'Reuse: an OAuth client configured for the Search Console module works here as-is — just add this redirect URI in Google Cloud Console:', 'seoistic' ); ?> <code><?php echo esc_html( ConnectionsClient::redirect_uri() ); ?></code></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save client', 'seoistic' ) ); ?>
			</form>
			<?php if ( '' !== $client_id && '' !== ConnectionsSettings::client_secret() ) : ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( ( new ConnectionsClient() )->connect_url() ); ?>"><?php esc_html_e( 'Connect Google →', 'seoistic' ); ?></a>
					<span class="description"><?php esc_html_e( 'Read-only scopes: Analytics + Search Console. Nothing is ever written to your Google account.', 'seoistic' ); ?></span>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_dashboard(): void {
		$client  = new ConnectionsClient();
		$service = new Ga4Service();
		$all     = ConnectionsSettings::all();
		$license = new LicenseClient();

		$property_id = ConnectionsSettings::property_id();
		$gsc_site    = ConnectionsSettings::gsc_site();
		?>
		<div class="seoistic-card">
			<h2>
				<?php esc_html_e( 'Google connected', 'seoistic' ); ?>
				<span class="seoistic-badge seoistic-badge--ok"><?php esc_html_e( 'License', 'seoistic' ); ?>: <?php echo esc_html( $license->masked_key() ); ?></span>
			</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
				<input type="hidden" name="action" value="seoistic_connections_disconnect" />
				<?php wp_nonce_field( 'seoistic_connections_disconnect' ); ?>
				<button class="button" type="submit"><?php esc_html_e( 'Disconnect', 'seoistic' ); ?></button>
			</form>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="seoistic_connections_save_selection" />
			<?php wp_nonce_field( 'seoistic_connections_save_selection' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="seoistic-conn-property"><?php esc_html_e( 'GA4 property', 'seoistic' ); ?></label></th>
					<td>
						<select id="seoistic-conn-property" name="property_id">
							<option value=""><?php esc_html_e( '— select —', 'seoistic' ); ?></option>
							<?php
							$properties = $client->list_ga4_properties();
							if ( is_wp_error( $properties ) ) {
								echo '<option value="' . esc_attr( $property_id ) . '">' . esc_html( $property_id . ' (' . $properties->get_error_message() . ')' ) . '</option>';
							} else {
								foreach ( $properties as $p ) {
									echo '<option value="' . esc_attr( $p['id'] ) . '"' . selected( $property_id, $p['id'], false ) . '>' . esc_html( $p['name'] . ' (' . $p['id'] . ')' ) . '</option>';
								}
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="seoistic-conn-gsc"><?php esc_html_e( 'Search Console site', 'seoistic' ); ?></label></th>
					<td>
						<select id="seoistic-conn-gsc" name="gsc_site">
							<option value=""><?php esc_html_e( '— select —', 'seoistic' ); ?></option>
							<?php
							$sites = $client->list_gsc_sites();
							if ( is_wp_error( $sites ) ) {
								echo '<option value="' . esc_attr( $gsc_site ) . '">' . esc_html( $gsc_site . ' (' . $sites->get_error_message() . ')' ) . '</option>';
							} else {
								foreach ( $sites as $s ) {
									echo '<option value="' . esc_attr( $s['siteUrl'] ) . '"' . selected( $gsc_site, $s['siteUrl'], false ) . '>' . esc_html( $s['siteUrl'] ) . '</option>';
								}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save selection', 'seoistic' ) ); ?>
		</form>
		<?php

		if ( '' !== $property_id ) {
			$overview = $service->overview( $property_id );
			$pages    = $service->top_pages( $property_id );
			echo '<div class="seoistic-card"><h2>' . esc_html__( 'GA4 — last 28 days', 'seoistic' ) . '</h2>';
			if ( is_wp_error( $overview ) ) {
				echo '<p>' . esc_html( $overview->get_error_message() ) . '</p>';
			} else {
				printf(
					'<p><strong>%s</strong> %s · <strong>%s</strong> %s · <strong>%s</strong> %s</p>',
					esc_html( number_format_i18n( $overview['sessions'] ) ),
					esc_html__( 'sessions', 'seoistic' ),
					esc_html( number_format_i18n( $overview['users'] ) ),
					esc_html__( 'users', 'seoistic' ),
					esc_html( number_format_i18n( $overview['views'] ) ),
					esc_html__( 'page views', 'seoistic' )
				);
				if ( is_array( $pages ) && array() !== $pages ) {
					echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Top pages', 'seoistic' ) . '</th><th>' . esc_html__( 'Views', 'seoistic' ) . '</th></tr></thead><tbody>';
					foreach ( array_slice( $pages, 0, 10 ) as $p ) {
						echo '<tr><td><code>' . esc_html( $p['path'] ) . '</code></td><td>' . esc_html( number_format_i18n( $p['views'] ) ) . '</td></tr>';
					}
					echo '</tbody></table>';
				}
			}
			echo '</div>';
		}

		if ( '' !== $gsc_site ) {
			$search = $service->search_snapshot( $gsc_site );
			echo '<div class="seoistic-card"><h2>' . esc_html__( 'Search Console — last 28 days', 'seoistic' ) . '</h2>';
			if ( is_wp_error( $search ) ) {
				echo '<p>' . esc_html( $search->get_error_message() ) . '</p>';
			} else {
				printf(
					'<p><strong>%s</strong> %s · <strong>%s</strong> %s</p>',
					esc_html( number_format_i18n( $search['clicks'] ) ),
					esc_html__( 'clicks', 'seoistic' ),
					esc_html( number_format_i18n( $search['impressions'] ) ),
					esc_html__( 'impressions', 'seoistic' )
				);
				if ( array() !== $search['top_queries'] ) {
					echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Query', 'seoistic' ) . '</th><th>' . esc_html__( 'Clicks', 'seoistic' ) . '</th><th>' . esc_html__( 'Position', 'seoistic' ) . '</th></tr></thead><tbody>';
					foreach ( array_slice( $search['top_queries'], 0, 10 ) as $q ) {
						echo '<tr><td>' . esc_html( $q['query'] ) . '</td><td>' . esc_html( number_format_i18n( $q['clicks'] ) ) . '</td><td>' . esc_html( (string) $q['position'] ) . '</td></tr>';
					}
					echo '</tbody></table>';
				}
			}
			echo '</div>';
		}

		// PageSpeed — runs keyless through the platform proxy; optional own key for heavy use.
		?>
		<div class="seoistic-card">
			<h2><?php esc_html_e( 'PageSpeed Insights', 'seoistic' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="seoistic_connections_save_selection" />
				<?php wp_nonce_field( 'seoistic_connections_save_selection' ); ?>
				<input type="hidden" name="property_id" value="<?php echo esc_attr( $property_id ); ?>" />
				<input type="hidden" name="gsc_site" value="<?php echo esc_attr( $gsc_site ); ?>" />
				<p>
					<label for="seoistic-conn-psi-key"><?php esc_html_e( 'Optional: your own Google PageSpeed API key (higher quota)', 'seoistic' ); ?></label>
					<input id="seoistic-conn-psi-key" name="psi_api_key" type="password" class="regular-text" autocomplete="new-password" placeholder="<?php echo '' !== ConnectionsSettings::psi_api_key() ? esc_attr__( 'saved — leave blank to keep', 'seoistic' ) : esc_attr__( 'AIza…', 'seoistic' ); ?>" />
					<button class="button" type="submit"><?php esc_html_e( 'Save key', 'seoistic' ); ?></button>
				</p>
			</form>
			<p>
				<button class="button button-primary" id="seoistic-conn-psi-run" data-home="<?php echo esc_attr( home_url( '/' ) ); ?>"><?php esc_html_e( 'Run PageSpeed test on homepage', 'seoistic' ); ?></button>
				<span id="seoistic-conn-psi-out"></span>
			</p>
			<script>
			( function() {
				var btn = document.getElementById( 'seoistic-conn-psi-run' ), out = document.getElementById( 'seoistic-conn-psi-out' );
				if ( ! btn ) { return; }
				btn.addEventListener( 'click', function() {
					out.textContent = '…';
					var body = new URLSearchParams( { action: 'seoistic_connections_psi', nonce: '<?php echo esc_js( wp_create_nonce( 'seoistic_connections_psi' ) ); ?>', url: btn.getAttribute( 'data-home' ) } );
					fetch( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() } )
						.then( function( r ) { return r.json(); } )
						.then( function( j ) {
							out.textContent = j && j.success ? ' ✓ ' + j.data.message : ' ✗ ' + ( ( j && j.data ) || 'failed' );
						} )
						.catch( function() { out.textContent = ' ✗ request failed'; } );
				} );
			} )();
			</script>
		</div>
		<?php
	}

	public function save_client(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'seoistic' ) );
		}
		check_admin_referer( 'seoistic_connections_save_client' );
		ConnectionsSettings::save_client(
			isset( $_POST['client_id'] ) ? (string) wp_unslash( $_POST['client_id'] ) : '',
			isset( $_POST['client_secret'] ) ? (string) wp_unslash( $_POST['client_secret'] ) : ''
		);
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-connections&saved=1' ) );
		exit;
	}

	public function oauth_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'seoistic' ) );
		}
		$code  = isset( $_GET['code'] ) ? (string) wp_unslash( $_GET['code'] ) : '';
		$state = isset( $_GET['state'] ) ? (string) wp_unslash( $_GET['state'] ) : '';
		if ( '' === $code || ! ConnectionsSettings::valid_state( $state ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=seoistic-connections&error=state' ) );
			exit;
		}
		$result = ( new ConnectionsClient() )->exchange_code( $code );
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-connections' . ( $result['success'] ? '&connected=1' : '&error=' . rawurlencode( (string) ( $result['error'] ?? 'unknown' ) ) ) ) );
		exit;
	}

	public function save_selection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'seoistic' ) );
		}
		check_admin_referer( 'seoistic_connections_save_selection' );
		$patch = array();
		if ( isset( $_POST['property_id'] ) ) {
			$patch['property_id'] = preg_replace( '/[^0-9]/', '', (string) wp_unslash( $_POST['property_id'] ) );
		}
		if ( isset( $_POST['gsc_site'] ) ) {
			$patch['gsc_site'] = (string) wp_unslash( $_POST['gsc_site'] );
		}
		if ( isset( $_POST['psi_api_key'] ) ) {
			$patch['psi_api_key'] = (string) wp_unslash( $_POST['psi_api_key'] );
		}
		ConnectionsSettings::save( $patch );
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-connections&saved=1' ) );
		exit;
	}

	public function disconnect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'seoistic' ) );
		}
		check_admin_referer( 'seoistic_connections_disconnect' );
		ConnectionsSettings::clear_tokens();
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-connections&disconnected=1' ) );
		exit;
	}

	public function ajax_psi(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Not allowed.', 'seoistic' ), 403 );
		}
		check_ajax_referer( 'seoistic_connections_psi', 'nonce' );
		$url = isset( $_POST['url'] ) ? esc_url_raw( (string) wp_unslash( $_POST['url'] ) ) : home_url( '/' );
		$result = ( new \Wpistic\Seoistic\Core\Performance\PerformanceService() )->run( $url, 'mobile', false );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		$msg = sprintf(
			/* translators: 1: performance score, 2: LCP ms, 3: CLS. */
			__( 'Score %1$s · LCP %2$s ms · CLS %3$s', 'seoistic' ),
			(string) ( $result['score'] ?? '—' ),
			(string) ( $result['lcp'] ?? '—' ),
			(string) ( $result['cls'] ?? '—' )
		);
		wp_send_json_success( array( 'message' => $msg ) );
	}

	public function ajax_refresh(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Not allowed.', 'seoistic' ), 403 );
		}
		check_ajax_referer( 'seoistic_connections_refresh', 'nonce' );
		delete_transient( 'seoistic_connections_access_token' );
		wp_send_json_success( array( 'message' => __( 'Connection refreshed.', 'seoistic' ) ) );
	}
}

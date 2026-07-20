<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\Gsc\GscClient;
use Wpistic\Seoistic\Gsc\GscSettings;

/**
 * SEOISTIC → Search Console. Three states rendered from one page: (1) enter
 * your own Google Cloud OAuth Client ID/Secret, (2) connect (OAuth redirect)
 * and pick which verified property to use, (3) the dashboard — top queries,
 * top pages (Search Analytics, read-only), and a per-URL indexing/coverage
 * lookup (URL Inspection). Only reachable at all once entitled — ModuleRegistry
 * gates GscModule::register() (which wires this page) behind the Business plan.
 */
final class GscPage {

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 27 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_seoistic_save_gsc_client', array( $this, 'save_client' ) );
		add_action( 'admin_post_seoistic_gsc_oauth_callback', array( $this, 'oauth_callback' ) );
		add_action( 'admin_post_seoistic_gsc_select_site', array( $this, 'select_site' ) );
		add_action( 'admin_post_seoistic_gsc_disconnect', array( $this, 'disconnect' ) );
		add_action( 'wp_ajax_seoistic_gsc_inspect_url', array( $this, 'ajax_inspect_url' ) );
	}

	public function menu(): void {
		add_submenu_page( 'seoistic', __( 'Search Console', 'seoistic' ), __( 'Search Console', 'seoistic' ), 'manage_options', 'seoistic-gsc', array( $this, 'render' ) );
	}

	public function assets( string $hook ): void {
		if ( false === strpos( (string) $hook, 'seoistic-gsc' ) ) {
			return;
		}
		wp_enqueue_script( 'seoistic-gsc', SEOISTIC_URL . 'assets/js/gsc.js', array( 'seoistic-admin' ), SEOISTIC_VERSION, true );
		wp_localize_script(
			'seoistic-gsc',
			'SeoisticGsc',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'seoistic_gsc_inspect' ),
			)
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		View::header( 'seoistic-gsc', __( 'Search Console', 'seoistic' ), __( 'Real indexing/coverage status and query/click data from Google Search Console — read-only, refreshed on demand.', 'seoistic' ) );

		if ( ! empty( $_GET['seoistic_gsc_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="seoistic-tool-result is-error" style="display:block;">' . esc_html(
				sprintf(
					/* translators: %s: the OAuth error code Google returned. */
					__( 'Google Search Console authorization was not completed (%s). You can try connecting again.', 'seoistic' ),
					sanitize_key( wp_unslash( $_GET['seoistic_gsc_error'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				)
			) . '</div>';
		}

		if ( ! GscSettings::has_client() ) {
			$this->render_client_setup();
		} elseif ( '' === GscSettings::refresh_token() ) {
			$this->render_connect();
		} elseif ( '' === GscSettings::site_url() ) {
			$this->render_site_picker();
		} else {
			$this->render_dashboard();
		}

		View::footer();
	}

	private function render_client_setup(): void {
		echo '<div class="seoistic-table-wrap" style="padding:18px 20px;">';
		echo '<h2>' . esc_html__( 'Step 1 — Connect your Google Cloud OAuth app', 'seoistic' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'SEOISTIC has no central relay server for this, so you connect using your own Google Cloud project — the same way you already do for the Google Indexing API in Indexistic.', 'seoistic' ) . '</p>';
		echo '<ol style="margin:0 0 16px 20px;">';
		echo '<li>' . esc_html__( 'Go to the Google Cloud Console → APIs & Services → Credentials.', 'seoistic' ) . '</li>';
		echo '<li>' . esc_html__( 'Create an OAuth 2.0 Client ID of type "Web application".', 'seoistic' ) . '</li>';
		echo '<li>' . esc_html(
			sprintf(
				/* translators: %s: the redirect URI to paste into Google Cloud Console. */
				__( 'Add this exact Authorized redirect URI: %s', 'seoistic' ),
				GscClient::redirect_uri()
			)
		) . ' <code>' . esc_html( GscClient::redirect_uri() ) . '</code></li>';
		echo '<li>' . esc_html__( 'Enable the "Google Search Console API" for the project.', 'seoistic' ) . '</li>';
		echo '<li>' . esc_html__( 'Paste the Client ID and Client Secret below.', 'seoistic' ) . '</li>';
		echo '</ol>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="seoistic_save_gsc_client">';
		wp_nonce_field( 'seoistic_gsc_client' );
		echo '<table class="form-table">';
		echo '<tr><th><label for="seoistic_gsc_client_id">' . esc_html__( 'Client ID', 'seoistic' ) . '</label></th><td><input type="text" id="seoistic_gsc_client_id" name="client_id" class="regular-text" value="' . esc_attr( GscSettings::client_id() ) . '"></td></tr>';
		echo '<tr><th><label for="seoistic_gsc_client_secret">' . esc_html__( 'Client Secret', 'seoistic' ) . '</label></th><td><input type="password" id="seoistic_gsc_client_secret" name="client_secret" class="regular-text" autocomplete="off" placeholder="' . ( GscSettings::masked_client_secret() ? esc_attr( GscSettings::masked_client_secret() ) : '' ) . '"></td></tr>';
		echo '</table>';
		submit_button( __( 'Save & continue', 'seoistic' ) );
		echo '</form></div>';
	}

	private function render_connect(): void {
		echo '<div class="seoistic-table-wrap" style="padding:18px 20px;">';
		echo '<h2>' . esc_html__( 'Step 2 — Connect', 'seoistic' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'You\'ll be sent to Google to authorize read-only access to your Search Console data, then back here to pick a property.', 'seoistic' ) . '</p>';
		echo '<a class="seoistic-btn seoistic-btn-primary" href="' . esc_url( ( new GscClient() )->authorize_url() ) . '"><span class="dashicons dashicons-admin-links"></span> ' . esc_html__( 'Connect Google Search Console', 'seoistic' ) . '</a>';
		echo '</div>';
	}

	private function render_site_picker(): void {
		$result = ( new GscClient() )->list_sites();
		echo '<div class="seoistic-table-wrap" style="padding:18px 20px;">';
		echo '<h2>' . esc_html__( 'Step 3 — Choose a property', 'seoistic' ) . '</h2>';

		if ( ! $result['success'] ) {
			echo '<div class="seoistic-tool-result is-error" style="display:block;">' . esc_html( $result['error'] ?? __( 'Could not list Search Console properties.', 'seoistic' ) ) . '</div>';
			echo '</div>';
			return;
		}
		if ( array() === $result['data'] ) {
			echo '<p class="description">' . esc_html__( 'No verified properties found on this Google account. Verify a property in Search Console first, then reload this page.', 'seoistic' ) . '</p></div>';
			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="seoistic_gsc_select_site">';
		wp_nonce_field( 'seoistic_gsc_select_site' );
		echo '<select name="site_url">';
		foreach ( $result['data'] as $site ) {
			echo '<option value="' . esc_attr( (string) $site['siteUrl'] ) . '">' . esc_html( (string) $site['siteUrl'] ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Use this property', 'seoistic' ), 'primary', 'submit', false );
		echo '</form></div>';
	}

	private function render_dashboard(): void {
		$client = new GscClient();

		echo '<div class="seoistic-cards">';
		View::card( 'yes-alt', __( 'Connected', 'seoistic' ), GscSettings::site_url(), 'good' );
		echo '</div>';

		echo '<div class="seoistic-table-wrap" style="padding:18px 20px;">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:10px;">';
		echo '<input type="hidden" name="action" value="seoistic_gsc_disconnect">';
		wp_nonce_field( 'seoistic_gsc_disconnect' );
		echo '<button type="submit" class="seoistic-btn" data-seoistic-confirm="' . esc_attr__( 'Disconnect Search Console? You can reconnect any time.', 'seoistic' ) . '">' . esc_html__( 'Disconnect', 'seoistic' ) . '</button>';
		echo '</form>';

		echo '<h2>' . esc_html__( 'Check indexing status', 'seoistic' ) . '</h2>';
		echo '<input type="url" id="seoistic-gsc-inspect-url" class="regular-text" placeholder="' . esc_attr( home_url( '/' ) ) . '">';
		echo ' <button type="button" class="seoistic-btn seoistic-btn-primary" id="seoistic-gsc-inspect-btn">' . esc_html__( 'Check', 'seoistic' ) . '</button>';
		echo '<div class="seoistic-tool-result" id="seoistic-gsc-inspect-result"></div>';
		echo '</div>';

		$this->render_analytics_table( $client, __( 'Top queries (last 28 days)', 'seoistic' ), array( 'query' ), __( 'Query', 'seoistic' ) );
		$this->render_analytics_table( $client, __( 'Top pages (last 28 days)', 'seoistic' ), array( 'page' ), __( 'Page', 'seoistic' ) );
	}

	/**
	 * @param list<string> $dimensions
	 */
	private function render_analytics_table( GscClient $client, string $title, array $dimensions, string $key_label ): void {
		$result = $client->search_analytics( array( 'dimensions' => $dimensions, 'row_limit' => 20 ) );

		echo '<div class="seoistic-section-title">' . esc_html( $title ) . '</div>';
		echo '<div class="seoistic-table-wrap">';
		if ( ! $result['success'] ) {
			echo '<p class="description" style="padding:14px 18px;">' . esc_html( $result['error'] ?? __( 'Could not load Search Analytics data.', 'seoistic' ) ) . '</p></div>';
			return;
		}
		if ( array() === $result['data'] ) {
			echo '<p class="description" style="padding:14px 18px;">' . esc_html__( 'No data for this period yet.', 'seoistic' ) . '</p></div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html( $key_label ) . '</th><th>' . esc_html__( 'Clicks', 'seoistic' ) . '</th><th>' . esc_html__( 'Impressions', 'seoistic' ) . '</th><th>' . esc_html__( 'CTR', 'seoistic' ) . '</th><th>' . esc_html__( 'Avg. position', 'seoistic' ) . '</th></tr></thead><tbody>';
		foreach ( $result['data'] as $row ) {
			$label = implode( ' / ', (array) ( $row['keys'] ?? array() ) );
			echo '<tr><td>' . esc_html( $label ) . '</td>';
			echo '<td>' . esc_html( (string) ( $row['clicks'] ?? 0 ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $row['impressions'] ?? 0 ) ) . '</td>';
			echo '<td>' . esc_html( round( (float) ( $row['ctr'] ?? 0 ) * 100, 1 ) . '%' ) . '</td>';
			echo '<td>' . esc_html( (string) round( (float) ( $row['position'] ?? 0 ), 1 ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public function save_client(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_gsc_client' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		GscSettings::save_client(
			sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['client_secret'] ?? '' ) )
		);
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-gsc' ) );
		exit;
	}

	public function oauth_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		if ( '' === $state || ! GscSettings::consume_oauth_state( $state ) ) {
			wp_die( esc_html__( 'This authorization link is invalid or has expired — please try connecting again.', 'seoistic' ) );
		}

		if ( ! empty( $_GET['error'] ) ) {
			wp_safe_redirect( add_query_arg( 'seoistic_gsc_error', sanitize_key( wp_unslash( $_GET['error'] ) ), admin_url( 'admin.php?page=seoistic-gsc' ) ) );
			exit;
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( '' === $code ) {
			wp_die( esc_html__( 'Google did not return an authorization code.', 'seoistic' ) );
		}

		( new GscClient() )->exchange_code( $code );
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-gsc' ) );
		exit;
	}

	public function select_site(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_gsc_select_site' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		GscSettings::save_site_url( wp_unslash( $_POST['site_url'] ?? '' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-gsc' ) );
		exit;
	}

	public function disconnect(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_gsc_disconnect' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		GscSettings::disconnect();
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-gsc' ) );
		exit;
	}

	public function ajax_inspect_url(): void {
		check_ajax_referer( 'seoistic_gsc_inspect', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'seoistic' ) ), 403 );
		}

		$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
		if ( '' === $url ) {
			wp_send_json_error( array( 'message' => __( 'No URL provided.', 'seoistic' ) ) );
		}

		$result = ( new GscClient() )->inspect_url( $url );
		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['error'] ?? __( 'Inspection failed.', 'seoistic' ) ) );
		}
		wp_send_json_success( array( 'data' => $result['data'] ?? array() ) );
	}
}

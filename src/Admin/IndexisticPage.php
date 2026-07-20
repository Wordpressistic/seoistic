<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Admin;

use Wpistic\Seoistic\Addon\IndexisticModule;
use Wpistic\Seoistic\Indexistic\GoogleIndexingClient;
use Wpistic\Seoistic\Indexistic\IndexingLog;
use Wpistic\Seoistic\Indexistic\IndexisticSettings;
use Wpistic\Seoistic\Indexistic\IndexNowClient;

/**
 * SEOISTIC → Indexistic. The fast/instant-indexing dashboard: quota/status
 * cards, a bulk submission console, settings (Google service-account key,
 * IndexNow key, post-type toggles, auto-submit), and the submission history.
 * Branded "Indexistic" — the sub-brand this addon is named for.
 */
final class IndexisticPage {

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 26 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_seoistic_save_indexistic_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_seoistic_indexistic_clear_history', array( $this, 'clear_history' ) );
		add_action( 'admin_post_seoistic_indexistic_regenerate_key', array( $this, 'regenerate_key' ) );
		add_action( 'wp_ajax_seoistic_indexistic_console_submit', array( $this, 'ajax_console_submit' ) );
		add_action( 'wp_ajax_seoistic_indexistic_check_status', array( $this, 'ajax_check_status' ) );
	}

	public function menu(): void {
		add_submenu_page( 'seoistic', __( 'Indexistic', 'seoistic' ), __( 'Indexistic', 'seoistic' ), 'manage_options', 'seoistic-indexistic', array( $this, 'render' ) );
	}

	public function assets( string $hook ): void {
		if ( false === strpos( (string) $hook, 'seoistic-indexistic' ) ) {
			return;
		}
		wp_enqueue_script( 'seoistic-indexistic', SEOISTIC_URL . 'assets/js/indexistic.js', array( 'seoistic-admin' ), SEOISTIC_VERSION, true );
		wp_localize_script(
			'seoistic-indexistic',
			'SeoisticIndexistic',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'consoleNonce' => wp_create_nonce( 'seoistic_indexistic_console' ),
			)
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'console'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		View::header( 'seoistic-indexistic', __( 'Indexistic', 'seoistic' ), __( 'Fast, free-tier search-engine indexing: the Google Indexing API and the IndexNow protocol (Bing, Yandex), with auto-submit and a bulk console.', 'seoistic' ) );

		$google = new GoogleIndexingClient();
		$this->render_stat_cards( $google );

		echo '<div class="seoistic-preview-toggle" style="margin:18px 0;">';
		foreach ( array( 'console' => __( 'Console', 'seoistic' ), 'settings' => __( 'Settings', 'seoistic' ), 'history' => __( 'History', 'seoistic' ) ) as $key => $label ) {
			$class = $key === $tab ? ' seoistic-btn-primary' : '';
			echo '<a class="seoistic-btn' . esc_attr( $class ) . '" href="' . esc_url( admin_url( 'admin.php?page=seoistic-indexistic&tab=' . $key ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</div>';

		if ( 'settings' === $tab ) {
			$this->render_settings();
		} elseif ( 'history' === $tab ) {
			$this->render_history();
		} else {
			$this->render_console( $google );
		}

		View::footer();
	}

	private function render_stat_cards( GoogleIndexingClient $google ): void {
		echo '<div class="seoistic-cards">';
		View::card( 'cloud-upload', (string) IndexingLog::count_today( 'google' ), __( 'Google submissions today', 'seoistic' ), $google->is_configured() ? 'good' : 'neutral' );
		View::card( 'randomize', (string) IndexingLog::count_today( 'indexnow' ), __( 'IndexNow submissions today', 'seoistic' ) );
		View::card( 'admin-network', $google->is_configured() ? __( 'Connected', 'seoistic' ) : __( 'Not configured', 'seoistic' ), __( 'Google Indexing API', 'seoistic' ), $google->is_configured() ? 'good' : 'bad' );
		View::card( 'shield', __( 'Ready', 'seoistic' ), __( 'IndexNow (no setup needed)', 'seoistic' ), 'good' );
		echo '</div>';
	}

	private function render_console( GoogleIndexingClient $google ): void {
		echo '<div class="seoistic-table-wrap" style="padding:18px 20px;">';
		echo '<div class="seoistic-field">';
		echo '<label class="seoistic-field-label" for="seoistic-indexistic-urls">' . esc_html__( 'URLs (one per line)', 'seoistic' ) . '</label>';
		echo '<textarea id="seoistic-indexistic-urls" rows="6" placeholder="' . esc_attr( home_url( '/' ) ) . '"></textarea>';
		echo '</div>';

		echo '<div class="seoistic-field-head" style="margin-bottom:14px;">';
		echo '<label><input type="radio" name="seoistic_indexistic_engine" value="indexnow" checked> ' . esc_html__( 'IndexNow (Bing, Yandex — free, no setup)', 'seoistic' ) . '</label>';
		echo '<label style="margin-left:16px;' . ( $google->is_configured() ? '' : 'opacity:.5;' ) . '"><input type="radio" name="seoistic_indexistic_engine" value="google" ' . disabled( $google->is_configured(), false, false ) . '> ' . esc_html__( 'Google Indexing API', 'seoistic' ) . '</label>';
		echo '</div>';

		echo '<div class="seoistic-tool-progress" id="seoistic-indexistic-progress"><div class="seoistic-tool-progress-bar"></div></div>';
		echo '<button type="button" class="seoistic-btn seoistic-btn-primary" id="seoistic-indexistic-submit"><span class="dashicons dashicons-upload"></span> ' . esc_html__( 'Submit URLs', 'seoistic' ) . '</button>';
		echo '<button type="button" class="seoistic-btn" id="seoistic-indexistic-check-status" style="margin-left:8px;" ' . disabled( $google->is_configured(), false, false ) . '><span class="dashicons dashicons-search"></span> ' . esc_html__( 'Check Google status (first URL)', 'seoistic' ) . '</button>';
		echo '<div class="seoistic-tool-result" id="seoistic-indexistic-result"></div>';
		echo '</div>';
	}

	private function render_settings(): void {
		$settings   = IndexisticSettings::all();
		$indexnow   = new IndexNowClient();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		echo '<div class="seoistic-table-wrap" style="padding:18px 20px;">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		echo '<input type="hidden" name="action" value="seoistic_save_indexistic_settings">';
		wp_nonce_field( 'seoistic_indexistic_settings' );

		echo '<h2>' . esc_html__( 'Google Indexing API', 'seoistic' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Create a Google Cloud service account with the Indexing API enabled, then paste or upload its JSON key here.', 'seoistic' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Note: Google officially supports this API only for pages with JobPosting or BroadcastEvent (livestream) markup. It works for other page types in practice, but Google may ignore or rate-limit unrelated submissions — IndexNow below has no such restriction.', 'seoistic' ) . '</p>';
		echo '<div class="seoistic-field">';
		echo '<textarea name="google_key_json" rows="6" placeholder="' . esc_attr__( 'Paste the service-account JSON here', 'seoistic' ) . '">' . ( IndexisticSettings::has_google_key() ? esc_textarea( IndexisticSettings::masked_google_key() ) : '' ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Or upload the JSON file:', 'seoistic' ) . ' <input type="file" name="google_key_file" accept="application/json"></p>';
		if ( IndexisticSettings::has_google_key() ) {
			echo '<label><input type="checkbox" name="clear_google_key" value="1"> ' . esc_html__( 'Remove saved key', 'seoistic' ) . '</label>';
		}
		echo '</div>';
		echo '<div class="seoistic-field"><label class="seoistic-field-label">' . esc_html__( 'Auto-submit these post types to Google', 'seoistic' ) . '</label>';
		foreach ( $post_types as $post_type ) {
			echo '<label style="display:block;font-size:12.5px;margin-bottom:4px;"><input type="checkbox" name="google_post_types[]" value="' . esc_attr( $post_type->name ) . '" ' . checked( in_array( $post_type->name, $settings['google_post_types'], true ), true, false ) . '> ' . esc_html( $post_type->labels->name ) . '</label>';
		}
		echo '</div>';

		echo '<h2>' . esc_html__( 'IndexNow', 'seoistic' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Free, no setup required — the key below is generated automatically and served at the URL shown.', 'seoistic' ) . '</p>';
		echo '<div class="seoistic-field"><label class="seoistic-field-label">' . esc_html__( 'Key location', 'seoistic' ) . '</label>';
		echo '<code>' . esc_html( $indexnow->key_location() ) . '</code> ';
		echo '<a class="seoistic-btn seoistic-btn-sm" href="' . esc_url( $indexnow->key_location() ) . '" target="_blank">' . esc_html__( 'Check key', 'seoistic' ) . '</a>';
		echo '</div>';
		echo '<div class="seoistic-field"><label class="seoistic-field-label">' . esc_html__( 'Auto-submit these post types to IndexNow', 'seoistic' ) . '</label>';
		foreach ( $post_types as $post_type ) {
			echo '<label style="display:block;font-size:12.5px;margin-bottom:4px;"><input type="checkbox" name="indexnow_post_types[]" value="' . esc_attr( $post_type->name ) . '" ' . checked( in_array( $post_type->name, $settings['indexnow_post_types'], true ), true, false ) . '> ' . esc_html( $post_type->labels->name ) . '</label>';
		}
		echo '</div>';

		echo '<div class="seoistic-field"><label><input type="checkbox" name="auto_submit" value="1" ' . checked( $settings['auto_submit'], true, false ) . '> ' . esc_html__( 'Automatically submit new/updated posts on publish (subject to the post types selected above)', 'seoistic' ) . '</label></div>';

		submit_button( __( 'Save Indexistic settings', 'seoistic' ) );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:10px;">';
		echo '<input type="hidden" name="action" value="seoistic_indexistic_regenerate_key">';
		wp_nonce_field( 'seoistic_indexistic_regenerate_key' );
		echo '<button type="submit" class="seoistic-btn" data-seoistic-confirm="' . esc_attr__( 'Replace the IndexNow key? Any pending crawls using the old key may be rejected.', 'seoistic' ) . '"><span class="dashicons dashicons-update"></span> ' . esc_html__( 'Regenerate IndexNow key', 'seoistic' ) . '</button>';
		echo '</form>';
		echo '</div>';
	}

	private function render_history(): void {
		$rows = IndexingLog::recent( 100 );

		echo '<div class="seoistic-table-wrap">';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__( 'Time', 'seoistic' ) . '</th><th>' . esc_html__( 'URL', 'seoistic' ) . '</th><th>' . esc_html__( 'Engine', 'seoistic' ) . '</th><th>' . esc_html__( 'Action', 'seoistic' ) . '</th><th>' . esc_html__( 'Status', 'seoistic' ) . '</th><th>' . esc_html__( 'Type', 'seoistic' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $row['created_at'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $row['url'] ) . '</code></td>';
			echo '<td>' . esc_html( ucfirst( (string) $row['engine'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row['action'] ) . '</td>';
			echo '<td>' . View::badge( ucfirst( (string) $row['status'] ), 'success' === $row['status'] ? 'good' : 'bad' ) . '</td>';
			echo '<td>' . ( ! empty( $row['is_manual'] ) ? esc_html__( 'Manual', 'seoistic' ) : esc_html__( 'Automatic', 'seoistic' ) ) . '</td>';
			echo '</tr>';
		}
		if ( array() === $rows ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No submissions yet.', 'seoistic' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="seoistic_indexistic_clear_history">';
		wp_nonce_field( 'seoistic_indexistic_clear_history' );
		echo '<button type="submit" class="seoistic-btn" data-seoistic-confirm="' . esc_attr__( 'Clear all indexing history? This cannot be undone.', 'seoistic' ) . '">' . esc_html__( 'Clear history', 'seoistic' ) . '</button>';
		echo '</form>';
	}

	public function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_indexistic_settings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}

		$google_post_types   = isset( $_POST['google_post_types'] ) && is_array( $_POST['google_post_types'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['google_post_types'] ) ) : array();
		$indexnow_post_types = isset( $_POST['indexnow_post_types'] ) && is_array( $_POST['indexnow_post_types'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['indexnow_post_types'] ) ) : array();
		IndexisticSettings::save( $google_post_types, $indexnow_post_types, isset( $_POST['auto_submit'] ) );

		if ( ! empty( $_POST['clear_google_key'] ) ) {
			IndexisticSettings::clear_google_key();
		} else {
			$json = sanitize_textarea_field( wp_unslash( $_POST['google_key_json'] ?? '' ) );
			if ( isset( $_FILES['google_key_file'] ) && ! empty( $_FILES['google_key_file']['tmp_name'] ) && is_uploaded_file( $_FILES['google_key_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidationSanitization
				$uploaded = file_get_contents( $_FILES['google_key_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( is_string( $uploaded ) && '' !== $uploaded ) {
					$json = $uploaded;
				}
			}
			if ( '' !== $json && false === strpos( $json, '•' ) ) {
				$decoded = json_decode( $json, true );
				if ( is_array( $decoded ) && ! empty( $decoded['client_email'] ) && ! empty( $decoded['private_key'] ) ) {
					IndexisticSettings::set_google_key( $json );
				}
			}
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'seoistic-indexistic', 'tab' => 'settings', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function clear_history(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_indexistic_clear_history' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		IndexingLog::clear();
		wp_safe_redirect( add_query_arg( array( 'page' => 'seoistic-indexistic', 'tab' => 'history' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function regenerate_key(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_indexistic_regenerate_key' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		IndexisticSettings::regenerate_indexnow_key();
		wp_safe_redirect( add_query_arg( array( 'page' => 'seoistic-indexistic', 'tab' => 'settings', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Batched console submission — processes one engine's worth of URLs per
	 * request so a large paste never times out a single call.
	 */
	public function ajax_console_submit(): void {
		check_ajax_referer( 'seoistic_indexistic_console', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'seoistic' ) ), 403 );
		}

		$engine = sanitize_key( wp_unslash( $_POST['engine'] ?? '' ) );
		$urls   = isset( $_POST['urls'] ) && is_array( $_POST['urls'] ) ? array_map( 'esc_url_raw', wp_unslash( $_POST['urls'] ) ) : array();
		$urls   = array_values( array_filter( $urls ) );

		if ( array() === $urls ) {
			wp_send_json_error( array( 'message' => __( 'No valid URLs found.', 'seoistic' ) ) );
		}

		$module = new IndexisticModule();
		if ( 'google' === $engine ) {
			$failures = 0;
			foreach ( $urls as $url ) {
				$result = $module->submit_google( $url, 'URL_UPDATED', true );
				if ( ! $result['success'] ) {
					++$failures;
				}
			}
			wp_send_json_success(
				array(
					/* translators: 1: number submitted, 2: number failed. */
					'message' => sprintf( __( 'Submitted %1$d URLs to Google (%2$d failed).', 'seoistic' ), count( $urls ), $failures ),
				)
			);
		}

		$result = $module->submit_indexnow( $urls, true );
		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['error'] ?? __( 'IndexNow submission failed.', 'seoistic' ) ) );
		}
		wp_send_json_success(
			array(
				/* translators: %d: number of URLs submitted. */
				'message' => sprintf( __( 'Submitted %d URLs to IndexNow.', 'seoistic' ), count( $urls ) ),
			)
		);
	}

	public function ajax_check_status(): void {
		check_ajax_referer( 'seoistic_indexistic_console', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'seoistic' ) ), 403 );
		}

		$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
		if ( '' === $url ) {
			wp_send_json_error( array( 'message' => __( 'No URL provided.', 'seoistic' ) ) );
		}

		$result = ( new IndexisticModule() )->get_google_status( $url );
		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['error'] ?? __( 'Status check failed.', 'seoistic' ) ) );
		}
		wp_send_json_success( array( 'data' => $result['data'] ?? array() ) );
	}
}

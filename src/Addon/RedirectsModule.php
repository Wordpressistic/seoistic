<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\SeoCore\Redirect\Redirect;
use Wpistic\Seoistic\Admin\View;
use Wpistic\Seoistic\Core\DashboardMetrics;
use Wpistic\Seoistic\Module\AbstractModule;

/**
 * Redirect manager + 404 monitor. 301/302/307/410 with regex, .htaccess-compatible,
 * stored in a custom table. Free — RankMath gates this behind Pro.
 */
final class RedirectsModule extends AbstractModule {

	public function id(): string {
		return 'redirects';
	}

	public function name(): string {
		return __( 'Redirects & 404 Monitor', 'seoistic' );
	}

	public function description(): string {
		return __( '301/302/410 redirects with regex and a 404 monitor — free (RankMath gates this).', 'seoistic' );
	}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
		add_action( 'template_redirect', array( $this, 'log_404' ), 999 );
		add_action( 'admin_menu', array( $this, 'menu' ), 30 );
		add_action( 'admin_post_seoistic_save_redirect', array( $this, 'save' ) );
		add_action( 'admin_post_seoistic_delete_redirect', array( $this, 'delete' ) );
		add_action( 'admin_post_seoistic_toggle_redirect', array( $this, 'toggle' ) );
		add_action( 'admin_post_seoistic_import_redirects', array( $this, 'import' ) );
		add_action( 'admin_post_seoistic_export_htaccess', array( $this, 'export_htaccess' ) );
		add_action( 'admin_post_seoistic_export_redirects_csv', array( $this, 'export_csv' ) );
	}

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoistic_redirects';
	}

	private function log_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoistic_log_404';
	}

	private function current_path(): string {
		return '/' . trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
	}

	public function maybe_redirect(): void {
		global $wpdb;
		$path = $this->current_path();
		$rows = $wpdb->get_results( "SELECT * FROM {$this->table()} WHERE enabled = 1", ARRAY_A ); // phpcs:ignore WordPress.DB

		foreach ( (array) $rows as $row ) {
			$is_regex = ! empty( $row['is_regex'] );
			$source   = '/' . trim( (string) $row['source'], '/' );
			$matched  = $is_regex ? (bool) @preg_match( '#' . $row['source'] . '#', $path ) : ( $source === $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

			if ( ! $matched ) {
				continue;
			}

			$wpdb->query( $wpdb->prepare( "UPDATE {$this->table()} SET hits = hits + 1, last_hit = %s WHERE id = %d", current_time( 'mysql', true ), $row['id'] ) ); // phpcs:ignore WordPress.DB

			$code = (int) $row['code'];
			if ( 410 === $code ) {
				status_header( 410 );
				nocache_headers();
				exit;
			}

			$target = $is_regex ? preg_replace( '#' . $row['source'] . '#', (string) $row['target'], $path ) : (string) $row['target'];
			if ( ! preg_match( '#^https?://#i', (string) $target ) ) {
				$target = home_url( $target );
			}
			wp_redirect( $target, $code ); // phpcs:ignore WordPress.Security.SafeRedirect
			exit;
		}
	}

	public function log_404(): void {
		if ( ! is_404() ) {
			return;
		}
		global $wpdb;
		$url = mb_substr( $this->current_path(), 0, 255 );
		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$now = current_time( 'mysql', true );
		$wpdb->query( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"INSERT INTO {$this->log_table()} (url, referer, hits, last_seen) VALUES (%s, %s, 1, %s)
				 ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = %s, referer = %s",
				$url,
				$ref,
				$now,
				$now,
				$ref
			)
		);
	}

	public function menu(): void {
		add_submenu_page( 'seoistic', __( 'Redirects', 'seoistic' ), __( 'Redirects', 'seoistic' ), 'manage_options', 'seoistic-redirects', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$this->table()} ORDER BY id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB
		$logs = $wpdb->get_results( "SELECT * FROM {$this->log_table()} ORDER BY last_seen DESC LIMIT 50", ARRAY_A ); // phpcs:ignore WordPress.DB
		$convert_from = isset( $_GET['convert'] ) ? sanitize_text_field( wp_unslash( $_GET['convert'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		View::header( 'seoistic-redirects', __( 'Redirects & 404 Monitor', 'seoistic' ), __( '301/302/307/410 redirects with regex support, plus a 404 monitor to catch broken links before visitors do.', 'seoistic' ) );
		?>
		<div class="seoistic-table-wrap">
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Source', 'seoistic' ); ?></th><th><?php esc_html_e( 'Target', 'seoistic' ); ?></th><th><?php esc_html_e( 'Code', 'seoistic' ); ?></th><th><?php esc_html_e( 'Hits', 'seoistic' ); ?></th><th><?php esc_html_e( 'Last hit', 'seoistic' ); ?></th><th><?php esc_html_e( 'Status', 'seoistic' ); ?></th><th></th></tr></thead>
			<tbody>
			<?php foreach ( (array) $rows as $row ) : ?>
				<tr>
					<td><code><?php echo esc_html( $row['source'] ); ?></code><?php echo ! empty( $row['is_regex'] ) ? ' ' . View::badge( __( 'Regex', 'seoistic' ), 'neutral' ) : ''; ?></td>
					<td><?php echo esc_html( $row['target'] ); ?></td>
					<td><?php echo (int) $row['code']; ?></td>
					<td><?php echo (int) $row['hits']; ?></td>
					<td><?php echo esc_html( ( $row['last_hit'] ?? '' ) ?: '—' ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="seoistic_toggle_redirect">
							<input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
							<?php wp_nonce_field( 'seoistic_redirect' ); ?>
							<label class="seoistic-switch"><input type="checkbox" name="enabled" value="1" onchange="this.form.submit()" <?php checked( (int) $row['enabled'], 1 ); ?>><span class="seoistic-switch-track"></span></label>
						</form>
					</td>
					<td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="seoistic_delete_redirect"><input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>"><?php wp_nonce_field( 'seoistic_redirect' ); ?><button class="seoistic-btn seoistic-btn-sm" data-seoistic-confirm="<?php esc_attr_e( 'Delete this redirect?', 'seoistic' ); ?>"><?php esc_html_e( 'Delete', 'seoistic' ); ?></button></form></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( array() === (array) $rows ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No redirects yet.', 'seoistic' ); ?></td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		</div>

		<div class="seoistic-section-title" id="seoistic-add-redirect"><?php esc_html_e( 'Add redirect', 'seoistic' ); ?></div>
		<div class="seoistic-table-wrap" style="padding:16px 20px;">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="seoistic_save_redirect">
			<?php wp_nonce_field( 'seoistic_redirect' ); ?>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Source path', 'seoistic' ); ?></th><td><input type="text" name="source" class="regular-text" placeholder="/old-page" value="<?php echo esc_attr( $convert_from ); ?>" required></td></tr>
				<tr><th><?php esc_html_e( 'Target', 'seoistic' ); ?></th><td><input type="text" name="target" class="regular-text" placeholder="/new-page or https://…"></td></tr>
				<tr><th><?php esc_html_e( 'Code', 'seoistic' ); ?></th><td><select name="code"><option>301</option><option>302</option><option>307</option><option>410</option></select></td></tr>
				<tr><th><?php esc_html_e( 'Regex', 'seoistic' ); ?></th><td><label><input type="checkbox" name="is_regex" value="1"> <?php esc_html_e( 'Source is a regular expression', 'seoistic' ); ?></label></td></tr>
			</table>
			<?php submit_button( __( 'Add redirect', 'seoistic' ) ); ?>
		</form>
		</div>

		<div class="seoistic-section-title"><?php esc_html_e( 'Import / export', 'seoistic' ); ?></div>
		<div class="seoistic-table-wrap" style="padding:16px 20px;">
		<p class="description"><?php esc_html_e( 'CSV columns: old_url, bucket, dest_domain, new_url, priority, status_code.', 'seoistic' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="display:inline-block;margin-right:12px;">
			<input type="hidden" name="action" value="seoistic_import_redirects">
			<?php wp_nonce_field( 'seoistic_redirect' ); ?>
			<input type="file" name="csv" accept=".csv" required>
			<button class="seoistic-btn"><?php esc_html_e( 'Import CSV', 'seoistic' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
			<input type="hidden" name="action" value="seoistic_export_redirects_csv">
			<?php wp_nonce_field( 'seoistic_redirect' ); ?>
			<button class="seoistic-btn"><?php esc_html_e( 'Export CSV', 'seoistic' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
			<input type="hidden" name="action" value="seoistic_export_htaccess">
			<?php wp_nonce_field( 'seoistic_redirect' ); ?>
			<button class="seoistic-btn"><?php esc_html_e( 'Export .htaccess', 'seoistic' ); ?></button>
		</form>
		</div>

		<div class="seoistic-section-title"><?php esc_html_e( '404 Monitor', 'seoistic' ); ?></div>
		<div class="seoistic-table-wrap">
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'URL', 'seoistic' ); ?></th><th><?php esc_html_e( 'Hits', 'seoistic' ); ?></th><th><?php esc_html_e( 'Last seen', 'seoistic' ); ?></th><th></th></tr></thead>
			<tbody>
			<?php foreach ( (array) $logs as $log ) : ?>
				<tr>
					<td><code><?php echo esc_html( $log['url'] ); ?></code></td>
					<td><?php echo (int) $log['hits']; ?></td>
					<td><?php echo esc_html( $log['last_seen'] ); ?></td>
					<td><a class="seoistic-btn seoistic-btn-sm" href="<?php echo esc_url( add_query_arg( 'convert', rawurlencode( $log['url'] ), admin_url( 'admin.php?page=seoistic-redirects' ) ) . '#seoistic-add-redirect' ); ?>"><?php esc_html_e( 'Convert to redirect', 'seoistic' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( array() === (array) $logs ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No 404s logged yet.', 'seoistic' ); ?></td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		</div>
		<?php
		View::footer();
	}

	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_redirect' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'source'     => sanitize_text_field( wp_unslash( $_POST['source'] ?? '' ) ),
				'target'     => sanitize_text_field( wp_unslash( $_POST['target'] ?? '' ) ),
				'code'       => (int) ( $_POST['code'] ?? 301 ),
				'is_regex'   => isset( $_POST['is_regex'] ) ? 1 : 0,
				'enabled'    => 1,
				'created_at' => current_time( 'mysql', true ),
			)
		);
		DashboardMetrics::flush();
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-redirects' ) );
		exit;
	}

	public function delete(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_redirect' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		global $wpdb;
		$wpdb->delete( $this->table(), array( 'id' => absint( $_POST['id'] ?? 0 ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		DashboardMetrics::flush();
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-redirects' ) );
		exit;
	}

	public function toggle(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_redirect' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array( 'enabled' => isset( $_POST['enabled'] ) ? 1 : 0 ),
			array( 'id' => absint( $_POST['id'] ?? 0 ) )
		);
		wp_safe_redirect( admin_url( 'admin.php?page=seoistic-redirects' ) );
		exit;
	}

	/**
	 * Import a redirect-map CSV (the REDIRECT-MAP-MASTER format). Cross-domain targets
	 * (e.g. Bucket B → orchao.com) become absolute URLs.
	 */
	public function import(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_redirect' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}

		$count = 0;
		if ( ! empty( $_FILES['csv']['tmp_name'] ) && is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security
			$handle = fopen( $_FILES['csv']['tmp_name'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( $handle ) {
				global $wpdb;
				$header = fgetcsv( $handle );
				$index  = array_flip( array_map( static fn ( $c ) => sanitize_key( (string) $c ), (array) $header ) );

				while ( ( $row = fgetcsv( $handle ) ) !== false ) {
					$value = static function ( $key ) use ( $row, $index ) {
						return isset( $index[ $key ], $row[ $index[ $key ] ] ) ? trim( (string) $row[ $index[ $key ] ] ) : '';
					};

					$source = $value( 'old_url' ) ?: $value( 'source' );
					if ( '' === $source ) {
						continue;
					}
					$dest_domain = $value( 'dest_domain' );
					$new_url     = $value( 'new_url' ) ?: $value( 'target' );
					$code        = (int) ( $value( 'status_code' ) ?: ( $value( 'code' ) ?: 301 ) );
					$target      = $new_url;
					if ( '' !== $dest_domain && ! preg_match( '#^https?://#i', $new_url ) ) {
						$target = rtrim( $dest_domain, '/' ) . '/' . ltrim( $new_url, '/' );
					}

					$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$this->table(),
						array(
							'source'     => $source,
							'target'     => $target,
							'code'       => $code > 0 ? $code : 301,
							'is_regex'   => 0,
							'enabled'    => 1,
							'created_at' => current_time( 'mysql', true ),
						)
					);
					++$count;
				}
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}

		DashboardMetrics::flush();
		wp_safe_redirect( add_query_arg( 'imported', $count, admin_url( 'admin.php?page=seoistic-redirects' ) ) );
		exit;
	}

	/**
	 * Stream the redirects as an Apache .htaccess file (uses the seo-core Redirect VO).
	 */
	public function export_htaccess(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_redirect' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		global $wpdb;
		$rows  = $wpdb->get_results( "SELECT * FROM {$this->table()} WHERE enabled = 1", ARRAY_A ); // phpcs:ignore WordPress.DB
		$lines = array(
			'# SEOISTIC redirects — generated ' . gmdate( 'Y-m-d H:i' ) . ' UTC',
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
		);
		foreach ( (array) $rows as $row ) {
			$redirect = new Redirect( '/' . ltrim( (string) $row['source'], '/' ), (string) $row['target'], (int) $row['code'], ! empty( $row['is_regex'] ) );
			$lines[]  = $redirect->toHtaccess();
		}
		$lines[] = '</IfModule>';

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=seoistic-redirects.htaccess' );
		echo implode( "\n", $lines ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Export all redirects as a CSV compatible with the import() format above.
	 */
	public function export_csv(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'seoistic_redirect' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$this->table()} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=seoistic-redirects.csv' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, array( 'source', 'target', 'code', 'is_regex', 'enabled', 'hits', 'last_hit' ) );
		foreach ( (array) $rows as $row ) {
			fputcsv( $out, array( $row['source'], $row['target'], $row['code'], $row['is_regex'], $row['enabled'], $row['hits'], $row['last_hit'] ?? '' ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}

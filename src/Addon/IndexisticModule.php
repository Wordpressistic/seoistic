<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Admin\IndexisticPage;
use Wpistic\Seoistic\Core\Sitemaps;
use Wpistic\Seoistic\Indexistic\GoogleIndexingClient;
use Wpistic\Seoistic\Indexistic\IndexingLog;
use Wpistic\Seoistic\Indexistic\IndexisticSettings;
use Wpistic\Seoistic\Indexistic\IndexNowClient;
use Wpistic\Seoistic\Module\AbstractModule;
use WP_Post;

/**
 * Indexistic — fast/instant search-engine indexing. Google's Indexing API
 * (service-account JWT, no vendor library) and the free IndexNow protocol
 * (Bing, Yandex, Seznam, Naver), with auto-submit on publish/update, a manual
 * bulk console, post-list row/bulk actions, and a submission history log.
 * Free — this is the addon the whole "Indexistic" sub-brand is named for.
 */
final class IndexisticModule extends AbstractModule {

	private const THROTTLE_SECONDS   = 5;
	private const PING_TRANSIENT     = 'seoistic_indexistic_last_ping';
	private const PING_THROTTLE_SECS = 300;

	public function id(): string {
		return 'indexistic';
	}

	public function name(): string {
		return __( 'Indexistic — Fast Indexing', 'seoistic' );
	}

	public function description(): string {
		return __( 'Get new and updated pages indexed fast with the Google Indexing API and the free IndexNow protocol (Bing, Yandex) — auto-submit, bulk console, and history.', 'seoistic' );
	}

	public function register(): void {
		( new IndexNowClient() )->register();
		( new IndexisticPage() )->register();

		add_action( 'save_post', array( $this, 'maybe_auto_submit' ), 20, 2 );
		add_action( 'wp_trash_post', array( $this, 'maybe_submit_delete' ) );
		add_action( 'admin_post_seoistic_indexistic_row_submit', array( $this, 'handle_row_action_submit' ) );

		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'row_actions' ), 10, 2 );

		foreach ( get_post_types( array( 'public' => true ) ) as $post_type ) {
			add_filter( "bulk_actions-edit-{$post_type}", array( $this, 'register_bulk_actions' ) );
			add_filter( "handle_bulk_actions-edit-{$post_type}", array( $this, 'handle_bulk_actions' ), 10, 3 );
		}
	}

	public function maybe_auto_submit( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status || ! IndexisticSettings::auto_submit_enabled() ) {
			return;
		}

		$url = get_permalink( $post );
		if ( ! $url ) {
			return;
		}

		if ( in_array( $post->post_type, IndexisticSettings::google_post_types(), true ) ) {
			$this->submit_google( $url, 'URL_UPDATED', false );
		}
		if ( in_array( $post->post_type, IndexisticSettings::indexnow_post_types(), true ) ) {
			$this->submit_indexnow( array( $url ), false );
		}
	}

	public function maybe_submit_delete( int $post_id ): void {
		if ( ! IndexisticSettings::auto_submit_enabled() ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, IndexisticSettings::google_post_types(), true ) ) {
			return;
		}
		$url = get_permalink( $post_id );
		if ( $url ) {
			$this->submit_google( $url, 'URL_DELETED', false );
		}
	}

	/**
	 * @return array{success:bool, data?:array<string,mixed>, error?:string, code?:int}
	 */
	public function submit_google( string $url, string $type = 'URL_UPDATED', bool $is_manual = true ): array {
		if ( ! $is_manual && $this->throttled( $url, 'google' ) ) {
			return array( 'success' => false, 'error' => __( 'Skipped — submitted too recently.', 'seoistic' ) );
		}
		$result = ( new GoogleIndexingClient() )->submit( $url, $type );
		IndexingLog::record( $url, 'google', strtolower( $type ), $result['success'] ? 'success' : 'error', $result['error'] ?? '', $is_manual );
		if ( $result['success'] ) {
			$this->maybe_ping_sitemap();
		}
		return $result;
	}

	/**
	 * @return array{success:bool, data?:array<string,mixed>, error?:string, code?:int}
	 */
	public function get_google_status( string $url ): array {
		return ( new GoogleIndexingClient() )->get_status( $url );
	}

	/**
	 * @param list<string> $urls
	 * @return array{success:bool, code?:int, error?:string}
	 */
	public function submit_indexnow( array $urls, bool $is_manual = true ): array {
		$urls = array_values( array_unique( array_filter( $urls ) ) );
		if ( array() === $urls ) {
			return array( 'success' => false, 'error' => __( 'No URLs to submit.', 'seoistic' ) );
		}
		if ( ! $is_manual && $this->throttled( $urls[0], 'indexnow' ) ) {
			return array( 'success' => false, 'error' => __( 'Skipped — submitted too recently.', 'seoistic' ) );
		}

		$result = ( new IndexNowClient() )->submit( $urls );
		foreach ( $urls as $url ) {
			IndexingLog::record( $url, 'indexnow', 'submit', $result['success'] ? 'success' : 'error', $result['error'] ?? '', $is_manual );
		}
		if ( $result['success'] ) {
			$this->maybe_ping_sitemap();
		}
		return $result;
	}

	private function throttled( string $url, string $engine ): bool {
		$last = IndexingLog::last_submission_for( $url, $engine );
		if ( ! $last ) {
			return false;
		}
		return ( time() - strtotime( $last['created_at'] . ' UTC' ) ) < self::THROTTLE_SECONDS;
	}

	/**
	 * Best-effort sitemap ping whenever a submission succeeds — throttled to once
	 * per 5 minutes regardless of how many URLs are submitted in that window, so a
	 * bulk console submission or a burst of auto-submits doesn't hammer Bing's ping
	 * endpoint with a request per URL.
	 */
	private function maybe_ping_sitemap(): void {
		if ( get_transient( self::PING_TRANSIENT ) ) {
			return;
		}
		set_transient( self::PING_TRANSIENT, 1, self::PING_THROTTLE_SECS );
		Sitemaps::ping();
	}

	/**
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public function row_actions( $actions, WP_Post $post ) {
		if ( ! current_user_can( 'manage_options' ) || 'publish' !== $post->post_status ) {
			return $actions;
		}
		$nonce = wp_create_nonce( 'seoistic_indexistic_row_action' );

		if ( IndexisticSettings::has_google_key() && in_array( $post->post_type, IndexisticSettings::google_post_types(), true ) ) {
			$url = add_query_arg(
				array( 'action' => 'seoistic_indexistic_row_submit', 'engine' => 'google', 'post_id' => $post->ID, '_wpnonce' => $nonce ),
				admin_url( 'admin-post.php' )
			);
			$actions['seoistic_google_submit'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Submit to Google', 'seoistic' ) . '</a>';
		}
		if ( in_array( $post->post_type, IndexisticSettings::indexnow_post_types(), true ) ) {
			$url = add_query_arg(
				array( 'action' => 'seoistic_indexistic_row_submit', 'engine' => 'indexnow', 'post_id' => $post->ID, '_wpnonce' => $nonce ),
				admin_url( 'admin-post.php' )
			);
			$actions['seoistic_indexnow_submit'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Submit to IndexNow', 'seoistic' ) . '</a>';
		}
		return $actions;
	}

	public function handle_row_action_submit(): void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'seoistic_indexistic_row_action' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'seoistic' ) );
		}

		$post_id = absint( $_GET['post_id'] ?? 0 );
		$engine  = sanitize_key( wp_unslash( $_GET['engine'] ?? '' ) );
		$post    = get_post( $post_id );

		if ( $post && 'publish' === $post->post_status ) {
			$url = get_permalink( $post );
			if ( 'google' === $engine && $url ) {
				$this->submit_google( $url, 'URL_UPDATED', true );
			} elseif ( 'indexnow' === $engine && $url ) {
				$this->submit_indexnow( array( $url ), true );
			}
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'edit.php' ) );
		exit;
	}

	/**
	 * @param array<string, string> $bulk_actions
	 * @return array<string, string>
	 */
	public function register_bulk_actions( $bulk_actions ) {
		$bulk_actions['seoistic_google_submit']   = __( 'Indexistic: Submit to Google', 'seoistic' );
		$bulk_actions['seoistic_indexnow_submit'] = __( 'Indexistic: Submit to IndexNow', 'seoistic' );
		return $bulk_actions;
	}

	/**
	 * @param list<int> $post_ids
	 */
	public function handle_bulk_actions( string $redirect_to, string $doaction, array $post_ids ): string {
		if ( ! in_array( $doaction, array( 'seoistic_google_submit', 'seoistic_indexnow_submit' ), true ) ) {
			return $redirect_to;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return $redirect_to;
		}

		$engine = 'seoistic_google_submit' === $doaction ? 'google' : 'indexnow';
		$urls   = array();
		foreach ( $post_ids as $post_id ) {
			if ( 'publish' === get_post_status( $post_id ) ) {
				$permalink = get_permalink( $post_id );
				if ( $permalink ) {
					$urls[] = $permalink;
				}
			}
		}

		if ( 'google' === $engine ) {
			foreach ( $urls as $url ) {
				$this->submit_google( $url, 'URL_UPDATED', true );
			}
		} else {
			$this->submit_indexnow( $urls, true );
		}

		return add_query_arg( 'seoistic_indexistic_submitted', count( $urls ), $redirect_to );
	}
}

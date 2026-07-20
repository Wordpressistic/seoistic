<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * XML sitemaps. Builds on WP core's sitemaps (reliable, already indexed by every
 * major engine) rather than a custom generator, and adds: noindex exclusion,
 * excluded post types, excluded post IDs. WordPress core sitemaps deliberately do
 * not support per-URL priority/changefreq — we don't fake support for that.
 * News/Video sitemaps are the SitemapExtras addon roadmap item.
 */
final class Sitemaps {

	private const OPTION = 'seoistic_sitemap_settings';

	public function register(): void {
		if ( ! get_option( 'seoistic_sitemaps', 1 ) ) {
			add_filter( 'wp_sitemaps_enabled', '__return_false' );
			return;
		}
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'exclude_posts' ), 10, 2 );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'exclude_post_types' ) );
	}

	/**
	 * @return array{excluded_post_types: list<string>, excluded_ids: list<int>}
	 */
	public static function settings(): array {
		$defaults = array( 'excluded_post_types' => array(), 'excluded_ids' => array() );
		$saved    = get_option( self::OPTION, array() );
		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	/**
	 * @param list<string> $excluded_types
	 * @param list<int>    $excluded_ids
	 */
	public static function save_settings( array $excluded_types, array $excluded_ids ): void {
		update_option(
			self::OPTION,
			array(
				'excluded_post_types' => array_values( array_unique( array_map( 'sanitize_key', $excluded_types ) ) ),
				'excluded_ids'        => array_values( array_unique( array_map( 'absint', $excluded_ids ) ) ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public function exclude_posts( $args, $post_type ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'OR',
			array( 'key' => '_seoistic_robots', 'value' => 'noindex', 'compare' => '!=' ),
			array( 'key' => '_seoistic_robots', 'compare' => 'NOT EXISTS' ),
		);

		$excluded_ids = self::settings()['excluded_ids'];
		if ( array() !== $excluded_ids ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), $excluded_ids );
		}

		return $args;
	}

	/**
	 * @param array<string, \WP_Post_Type> $post_types
	 * @return array<string, \WP_Post_Type>
	 */
	public function exclude_post_types( $post_types ) {
		foreach ( self::settings()['excluded_post_types'] as $post_type ) {
			unset( $post_types[ $post_type ] );
		}
		return $post_types;
	}

	/**
	 * Ping IndexNow-network engines (Bing, Yandex, etc.) that the sitemap changed.
	 * Google retired its public sitemap-ping endpoint in 2023, so Bing's ping
	 * endpoint — which still fans out across the IndexNow network — is the only
	 * one left. Best-effort and non-blocking; failures are reported, never fatal.
	 *
	 * @return array{success:bool, status?:int, sitemap:string, error?:string}
	 */
	public static function ping(): array {
		$sitemap  = home_url( '/wp-sitemap.xml' );
		$response = wp_remote_get(
			'https://www.bing.com/ping?sitemap=' . rawurlencode( $sitemap ),
			array( 'timeout' => 15 )
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'sitemap' => $sitemap, 'error' => $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		return array( 'success' => $code < 400, 'status' => $code, 'sitemap' => $sitemap );
	}
}

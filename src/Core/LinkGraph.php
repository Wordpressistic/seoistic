<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * A one-shot, admin-triggered scan of every published page's content for
 * internal links, used to flag orphan pages (published, but nothing else on
 * the site links to them). This is a real link graph over post_content —
 * unlike the AI internal-link report (Admin\AiToolsPage), which is AI-
 * generated suggestions per low-scoring post, not a count of actual links.
 *
 * A single regex pass over content is cheap even at a few thousand posts, so
 * this runs as one admin-triggered scan rather than a batched/paginated
 * action — the result is cached so the dashboard doesn't re-scan on every load.
 */
final class LinkGraph {

	private const TRANSIENT = 'seoistic_orphan_pages';
	private const TTL       = DAY_IN_SECONDS;

	/**
	 * @return array<int, array{id:int, title:string, edit_url:string}>|null Null if never scanned.
	 */
	public static function cached_orphans(): ?array {
		$cached = get_transient( self::TRANSIENT );
		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * @return array<int, array{id:int, title:string, edit_url:string}>
	 */
	public static function scan(): array {
		global $wpdb;

		$public_types = get_post_types( array( 'public' => true ) );
		$placeholders = implode( ',', array_fill( 0, count( $public_types ), '%s' ) );
		$limit        = (int) apply_filters( 'seoistic/orphan_scan_limit', 5000 );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_content FROM {$wpdb->posts}
				 WHERE post_status = 'publish' AND post_type IN ({$placeholders})
				 ORDER BY ID ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $public_types, array( $limit ) )
			),
			ARRAY_A
		);

		$host      = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$linked    = array();
		$published = array();

		foreach ( (array) $rows as $row ) {
			$published[ (int) $row['ID'] ] = (string) $row['post_title'];
			foreach ( self::internal_link_targets( (string) $row['post_content'], $host ) as $target_id ) {
				$linked[ $target_id ] = true;
			}
		}

		$exclude = array_filter( array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) ) );

		$orphans = array();
		foreach ( $published as $id => $title ) {
			if ( in_array( $id, $exclude, true ) || isset( $linked[ $id ] ) ) {
				continue;
			}
			$orphans[] = array( 'id' => $id, 'title' => $title, 'edit_url' => (string) get_edit_post_link( $id, 'raw' ) );
		}

		set_transient( self::TRANSIENT, $orphans, self::TTL );
		return $orphans;
	}

	/**
	 * @return list<int> Post IDs this content links to internally.
	 */
	private static function internal_link_targets( string $content, string $host ): array {
		preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\']/i', $content, $matches );
		$targets = array();

		foreach ( $matches[1] as $href ) {
			$href = trim( html_entity_decode( $href ) );
			if ( '' === $href || '#' === $href[0] || 0 === stripos( $href, 'mailto:' ) || 0 === stripos( $href, 'tel:' ) || 0 === stripos( $href, 'javascript:' ) ) {
				continue;
			}
			if ( 0 === strpos( $href, '/' ) ) {
				$href = home_url( $href );
			}
			$link_host = wp_parse_url( $href, PHP_URL_HOST );
			if ( '' !== (string) $link_host && $link_host !== $host ) {
				continue; // External link.
			}
			$target_id = url_to_postid( $href );
			if ( $target_id > 0 ) {
				$targets[] = $target_id;
			}
		}

		return $targets;
	}
}

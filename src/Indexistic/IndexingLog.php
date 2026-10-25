<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Indexistic;

/**
 * Reads/writes the seoistic_indexing_log table — every Google/IndexNow
 * submission attempt, manual or automatic, with its result. Reads are cached
 * (generation-invalidated on every write); all SQL uses %i table placeholders.
 */
final class IndexingLog {

	private const CACHE_GROUP = 'seoistic_indexing_log';
	private const CACHE_TTL   = 120;

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoistic_indexing_log';
	}

	private static function cache_generation(): int {
		$gen = wp_cache_get( 'generation', self::CACHE_GROUP );
		return is_int( $gen ) && $gen > 0 ? $gen : (int) ( time() / 60 );
	}

	public static function record( string $url, string $engine, string $action, string $status, string $message = '', bool $is_manual = true ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
			self::table(),
			array(
				'url'               => esc_url_raw( $url ),
				'engine'            => sanitize_key( $engine ),
				'action'            => sanitize_key( $action ),
				'status'            => sanitize_key( $status ),
				'response_message'  => sanitize_textarea_field( $message ),
				'is_manual'         => $is_manual ? 1 : 0,
				'created_at'        => current_time( 'mysql', true ),
			)
		);
		wp_cache_delete( 'generation', self::CACHE_GROUP );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function recent( int $limit = 50, int $offset = 0 ): array {
		$cache_key = 'recent_' . self::cache_generation() . '_' . $limit . '_' . $offset;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', self::table(), $limit, $offset ),
			ARRAY_A
		);
		wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, self::CACHE_TTL );
		return $rows;
	}

	public static function total(): int {
		$cache_key = 'total_' . self::cache_generation();
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_int( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		wp_cache_set( $cache_key, $total, self::CACHE_GROUP, self::CACHE_TTL );
		return $total;
	}

	public static function count_today( string $engine = '' ): int {
		$cache_key = 'today_' . self::cache_generation() . '_' . md5( $engine . '|' . gmdate( 'Y-m-d' ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_int( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$since = gmdate( 'Y-m-d 00:00:00' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		$count = '' !== $engine
			? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'success' AND engine = %s AND created_at >= %s", self::table(), $engine, $since ) )
			: (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'success' AND created_at >= %s", self::table(), $since ) );
		// phpcs:enable
		wp_cache_set( $cache_key, $count, self::CACHE_GROUP, self::CACHE_TTL );
		return $count;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function last_submission_for( string $url, string $engine ): ?array {
		$cache_key = 'last_' . self::cache_generation() . '_' . md5( $url . '|' . $engine );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
			$wpdb->prepare( 'SELECT * FROM %i WHERE url = %s AND engine = %s ORDER BY id DESC LIMIT 1', self::table(), $url, $engine ),
			ARRAY_A
		);
		$result = is_array( $row ) ? $row : array();
		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );
		return is_array( $row ) ? $row : null;
	}

	public static function clear(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', self::table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- TRUNCATE has no WordPress API and cannot be cached.
		wp_cache_delete( 'generation', self::CACHE_GROUP );
	}
}

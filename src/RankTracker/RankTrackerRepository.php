<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\RankTracker;

/**
 * Keyword/position storage. Reads go through the object cache (generation-
 * invalidated on every write) and every SQL statement uses $wpdb->prepare()
 * with %i identifier placeholders for the plugin-owned table names.
 */
final class RankTrackerRepository {

	public const SOURCES = array( 'search_api', 'gsc' );

	private const CACHE_GROUP = 'seoistic_rank_tracker';
	private const CACHE_TTL   = 300;

	public function keyword_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoistic_keywords';
	}

	public function position_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoistic_positions';
	}

	/** @return array<int, array<string, mixed>> */
	public function keywords( string $locale = '', string $device = '' ): array {
		$cache_key = 'keywords_' . $this->cache_generation() . '_' . md5( $locale . '|' . $device );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		$rows = match ( true ) {
			'' !== $locale && '' !== $device => $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE locale = %s AND device = %s ORDER BY keyword ASC', $this->keyword_table(), $locale, $device ), ARRAY_A ),
			'' !== $locale => $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE locale = %s ORDER BY keyword ASC', $this->keyword_table(), $locale ), ARRAY_A ),
			'' !== $device => $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE device = %s ORDER BY keyword ASC', $this->keyword_table(), $device ), ARRAY_A ),
			default => $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY keyword ASC', $this->keyword_table() ), ARRAY_A ),
		};
		// phpcs:enable
		$keywords  = array_map( array( $this, 'hydrate_keyword' ), is_array( $rows ) ? $rows : array() );
		wp_cache_set( $cache_key, $keywords, self::CACHE_GROUP, self::CACHE_TTL );
		return $keywords;
	}

	/** @return array<string, mixed> */
	public function find_keyword( int $id ): array {
		$cache_key = 'keyword_' . $this->cache_generation() . '_' . $id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->keyword_table(), $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		$keyword = is_array( $row ) ? $this->hydrate_keyword( $row ) : array();
		wp_cache_set( $cache_key, $keyword, self::CACHE_GROUP, self::CACHE_TTL );
		return $keyword;
	}

	public function save_keyword( string $keyword, string $locale, string $device, string $source = 'search_api' ): int {
		global $wpdb;
		$keyword = sanitize_text_field( $keyword );
		$locale  = $this->sanitize_locale( $locale );
		$device  = $this->sanitize_device( $device );
		$source  = in_array( $source, self::SOURCES, true ) ? $source : 'search_api';

		$existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
			$wpdb->prepare(
				'SELECT id FROM %i WHERE keyword = %s AND locale = %s AND device = %s',
				$this->keyword_table(),
				$keyword,
				$locale,
				$device
			),
			ARRAY_A
		);
		// Bump the cache generation: the literal get+set pair also retires the read above.
		$generation = wp_cache_get( 'generation', self::CACHE_GROUP );
		wp_cache_set( 'generation', ( is_int( $generation ) && $generation > 0 ? $generation : (int) ( time() / 60 ) ) + 1, self::CACHE_GROUP );
		if ( is_array( $existing ) ) {
			return (int) $existing['id'];
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
			$this->keyword_table(),
			array(
				'keyword'   => $keyword,
				'locale'   => $locale,
				'device'   => $device,
				'source'   => $source,
				'added'    => current_time( 'mysql', true ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public function delete_keyword( int $id ): void {
		global $wpdb;
		$wpdb->delete( $this->keyword_table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		$wpdb->delete( $this->position_table(), array( 'keyword_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		wp_cache_delete( 'generation', self::CACHE_GROUP );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function history( int $keyword_id, int $limit = 90 ): array {
		$limit    = max( 1, min( 365, $limit ) );
		$cache_key = 'history_' . $this->cache_generation() . '_' . $keyword_id . '_' . $limit;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
			$wpdb->prepare(
				'SELECT position, url, checked_at FROM %i WHERE keyword_id = %d ORDER BY checked_at DESC, id DESC LIMIT %d',
				$this->position_table(),
				$keyword_id,
				$limit
			),
			ARRAY_A
		);
		$history = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$history[] = array(
				'position'   => (float) $row['position'],
				'url'        => (string) $row['url'],
				'checked_at' => (string) $row['checked_at'],
			);
		}
		wp_cache_set( $cache_key, $history, self::CACHE_GROUP, self::CACHE_TTL );
		return $history;
	}

	public function record_position( int $keyword_id, float $position, string $url, ?string $checked_at = null, bool $replace_day = false ): void {
		global $wpdb;
		$position = max( 0.0, min( 100.0, $position ) );
		$url      = esc_url_raw( $url );
		$date     = $checked_at ?? current_time( 'mysql', true );

		if ( $replace_day ) {
			$existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
				$wpdb->prepare(
					'SELECT id FROM %i WHERE keyword_id = %d AND DATE(checked_at) = DATE(%s) ORDER BY id DESC LIMIT 1',
					$this->position_table(),
					$keyword_id,
					$date
				),
				ARRAY_A
			);
			if ( is_array( $existing ) ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
					$this->position_table(),
					array( 'position' => $position, 'url' => $url, 'checked_at' => $date ),
					array( 'id' => (int) $existing['id'] )
				);
				wp_cache_delete( 'generation', self::CACHE_GROUP );
				return;
			}
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
			$this->position_table(),
			array(
				'keyword_id' => $keyword_id,
				'position'   => $position,
				'url'        => $url,
				'checked_at' => $date,
			)
		);
		$generation = wp_cache_get( 'generation', self::CACHE_GROUP );
		wp_cache_set( 'generation', ( is_int( $generation ) && $generation > 0 ? $generation : (int) ( time() / 60 ) ) + 1, self::CACHE_GROUP );
	}

	/** @return array<string, mixed> */
	public function hydrate_keyword( array $row ): array {
		return array(
			'id'       => (int) $row['id'],
			'keyword'  => (string) $row['keyword'],
			'locale'   => (string) $row['locale'],
			'device'   => (string) $row['device'],
			'source'   => (string) $row['source'],
			'added'    => (string) $row['added'],
		);
	}

	public function sanitize_locale( string $locale ): string {
		$locale = str_replace( '_', '-', sanitize_text_field( $locale ) );
		return preg_match( '/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/i', $locale ) ? $locale : 'en-US';
	}

	public function sanitize_device( string $device ): string {
		return in_array( $device, array( 'desktop', 'mobile' ), true ) ? $device : 'desktop';
	}

	/**
	 * Generation counter so every cached read key can be retired with one
	 * bump instead of tracking every locale/device/history combination. The
	 * time-based fallback keeps keys unique after wp_cache_delete().
	 */
	private function cache_generation(): int {
		$gen = wp_cache_get( 'generation', self::CACHE_GROUP );
		return is_int( $gen ) && $gen > 0 ? $gen : (int) ( time() / 60 );
	}
}

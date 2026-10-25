<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Uptime;

/**
 * Data-access layer for the domain / uptime monitor. All SQL goes through
 * $wpdb->prepare() with %i identifier placeholders for the plugin-owned table
 * names; reads are cached (generation-invalidated on every write). Keeps the
 * rest of the addon free of raw SQL.
 */
final class UptimeStore {

	private const CACHE_GROUP = 'seoistic_uptime';
	private const CACHE_TTL   = 120;

	private function monitors_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoistic_uptime_monitors';
	}

	private function checks_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoistic_uptime_checks';
	}

	private function cache_generation(): int {
		$gen = wp_cache_get( 'generation', self::CACHE_GROUP );
		return is_int( $gen ) && $gen > 0 ? $gen : (int) ( time() / 60 );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function all_monitors(): array {
		$cached = wp_cache_get( 'monitors_' . $this->cache_generation(), self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY label ASC', $this->monitors_table() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		$monitors = is_array( $rows ) ? $rows : array();
		wp_cache_set( 'monitors_' . $this->cache_generation(), $monitors, self::CACHE_GROUP, self::CACHE_TTL );
		return $monitors;
	}

	/**
	 * Cheap existence check used by the scheduler (avoids SELECT * on hot paths).
	 */
	public function count_monitors(): int {
		$cached = wp_cache_get( 'count_' . $this->cache_generation(), self::CACHE_GROUP );
		if ( is_int( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->monitors_table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		wp_cache_set( 'count_' . $this->cache_generation(), $count, self::CACHE_GROUP, self::CACHE_TTL );
		return $count;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_monitor( int $id ): ?array {
		$cached = wp_cache_get( 'monitor_' . $this->cache_generation() . '_' . $id, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->monitors_table(), $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		$monitor = is_array( $row ) ? $row : array();
		wp_cache_set( 'monitor_' . $this->cache_generation() . '_' . $id, $monitor, self::CACHE_GROUP, self::CACHE_TTL );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Monitors whose per-monitor interval has elapsed and are due for a check.
	 * Never cached: the scheduler depends on fresh due-state.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function due_monitors( int $limit, string $now_gmt ): array {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- plugin-owned custom table (no WordPress API); must not be cached: the scheduler depends on fresh due-state.
			$wpdb->prepare(
				'SELECT * FROM %i
				 WHERE enabled = 1
				   AND ( last_checked IS NULL OR last_checked <= ( %s - INTERVAL interval_minutes MINUTE ) )
				 ORDER BY last_checked IS NOT NULL, last_checked ASC
				 LIMIT %d',
				$this->monitors_table(),
				$now_gmt,
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string, mixed> $data
	 * @return int Inserted monitor id, or 0 on failure.
	 */
	public function insert_monitor( array $data ): int {
		global $wpdb;
		$data['created_at'] = gmdate( 'Y-m-d H:i:s' );
		$ok = $wpdb->insert( $this->monitors_table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		wp_cache_delete( 'generation', self::CACHE_GROUP );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function update_monitor( int $id, array $data ): bool {
		global $wpdb;
		$ok = false !== $wpdb->update( $this->monitors_table(), $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		wp_cache_delete( 'generation', self::CACHE_GROUP );
		return $ok;
	}

	public function delete_monitor( int $id ): bool {
		global $wpdb;
		$wpdb->delete( $this->checks_table(), array( 'monitor_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		$ok = false !== $wpdb->delete( $this->monitors_table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		wp_cache_delete( 'generation', self::CACHE_GROUP );
		return $ok;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert_check( array $data ): void {
		global $wpdb;
		$data['checked_at'] = gmdate( 'Y-m-d H:i:s' );
		$wpdb->insert( $this->checks_table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		$generation = wp_cache_get( 'generation', self::CACHE_GROUP );
		wp_cache_set( 'generation', ( is_int( $generation ) && $generation > 0 ? $generation : (int) ( time() / 60 ) ) + 1, self::CACHE_GROUP );
	}

	/**
	 * Recent checks for a monitor, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function recent_checks( int $monitor_id, int $limit = 20 ): array {
		$cache_key = 'checks_' . $this->cache_generation() . '_' . $monitor_id . '_' . $limit;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
			$wpdb->prepare(
				'SELECT * FROM %i WHERE monitor_id = %d ORDER BY checked_at DESC LIMIT %d',
				$this->checks_table(),
				$monitor_id,
				$limit
			),
			ARRAY_A
		);
		$checks = is_array( $rows ) ? $rows : array();
		wp_cache_set( $cache_key, $checks, self::CACHE_GROUP, self::CACHE_TTL );
		return $checks;
	}

	/**
	 * Uptime percentage over the trailing window (days), 0–100. Returns null when
	 * there is no check data yet so the UI can say "no data" rather than "0%".
	 */
	public function uptime_percent( int $monitor_id, int $days = 30 ): ?float {
		$cache_key = 'uptime_' . $this->cache_generation() . '_' . $monitor_id . '_' . $days;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_float( $cached ) || is_int( $cached ) ) {
			return (float) $cached;
		}
		if ( is_array( $cached ) ) {
			return null; // Cached "no data yet" marker.
		}

		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, SUM( status = 'up' ) AS ups
				 FROM %i WHERE monitor_id = %d AND checked_at >= %s",
				$this->checks_table(),
				$monitor_id,
				$since
			),
			ARRAY_A
		);
		$total = (int) ( $row['total'] ?? 0 );
		if ( 0 === $total ) {
			wp_cache_set( $cache_key, array(), self::CACHE_GROUP, self::CACHE_TTL );
			return null;
		}
		$percent = round( ( (int) $row['ups'] / $total ) * 100, 2 );
		wp_cache_set( $cache_key, $percent, self::CACHE_GROUP, self::CACHE_TTL );
		return $percent;
	}

	/**
	 * Delete check rows older than the retention window. Returns rows removed.
	 */
	public function prune_checks( int $retention_days ): int {
		global $wpdb;
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - $retention_days * DAY_IN_SECONDS );
		$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE checked_at < %s', $this->checks_table(), $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table; no WordPress API exists for it.
		wp_cache_delete( 'generation', self::CACHE_GROUP );
		return (int) $deleted;
	}
}

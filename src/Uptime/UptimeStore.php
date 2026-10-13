<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Uptime;

/**
 * Data-access layer for the domain / uptime monitor. All dynamic SQL goes through
 * $wpdb->prepare(); table names are built from the trusted $wpdb->prefix and never
 * interpolate user input. Keeps the rest of the addon free of raw SQL.
 */
final class UptimeStore {

	private function monitors_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoistic_uptime_monitors';
	}

	private function checks_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoistic_uptime_checks';
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function all_monitors(): array {
		global $wpdb;
		$table = $this->monitors_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is trusted, no user input.
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY label ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Cheap existence check used by the scheduler (avoids SELECT * on hot paths).
	 */
	public function count_monitors(): int {
		global $wpdb;
		$table = $this->monitors_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name trusted, no user input.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_monitor( int $id ): ?array {
		global $wpdb;
		$table = $this->monitors_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name trusted; value prepared.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Monitors whose per-monitor interval has elapsed and are due for a check.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function due_monitors( int $limit, string $now_gmt ): array {
		global $wpdb;
		$table = $this->monitors_table();
		$sql   = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			"SELECT * FROM {$table}
			 WHERE enabled = 1
			   AND ( last_checked IS NULL OR last_checked <= ( %s - INTERVAL interval_minutes MINUTE ) )
			 ORDER BY last_checked IS NOT NULL, last_checked ASC
			 LIMIT %d",
			$now_gmt,
			$limit
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is a prepared string.
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string, mixed> $data
	 * @return int Inserted monitor id, or 0 on failure.
	 */
	public function insert_monitor( array $data ): int {
		global $wpdb;
		$data['created_at'] = gmdate( 'Y-m-d H:i:s' );
		$ok = $wpdb->insert( $this->monitors_table(), $data );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function update_monitor( int $id, array $data ): bool {
		global $wpdb;
		return false !== $wpdb->update( $this->monitors_table(), $data, array( 'id' => $id ) );
	}

	public function delete_monitor( int $id ): bool {
		global $wpdb;
		$wpdb->delete( $this->checks_table(), array( 'monitor_id' => $id ) );
		return false !== $wpdb->delete( $this->monitors_table(), array( 'id' => $id ) );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert_check( array $data ): void {
		global $wpdb;
		$data['checked_at'] = gmdate( 'Y-m-d H:i:s' );
		$wpdb->insert( $this->checks_table(), $data );
	}

	/**
	 * Recent checks for a monitor, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function recent_checks( int $monitor_id, int $limit = 20 ): array {
		global $wpdb;
		$table = $this->checks_table();
		$sql   = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			"SELECT * FROM {$table} WHERE monitor_id = %d ORDER BY checked_at DESC LIMIT %d",
			$monitor_id,
			$limit
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql prepared.
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Uptime percentage over the trailing window (days), 0–100. Returns null when
	 * there is no check data yet so the UI can say "no data" rather than "0%".
	 */
	public function uptime_percent( int $monitor_id, int $days = 30 ): ?float {
		global $wpdb;
		$table = $this->checks_table();
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$sql   = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			"SELECT COUNT(*) AS total, SUM( status = 'up' ) AS ups
			 FROM {$table} WHERE monitor_id = %d AND checked_at >= %s",
			$monitor_id,
			$since
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql prepared.
		$row   = $wpdb->get_row( $sql, ARRAY_A );
		$total = (int) ( $row['total'] ?? 0 );
		if ( 0 === $total ) {
			return null;
		}
		return round( ( (int) $row['ups'] / $total ) * 100, 2 );
	}

	/**
	 * Delete check rows older than the retention window. Returns rows removed.
	 */
	public function prune_checks( int $retention_days ): int {
		global $wpdb;
		$table  = $this->checks_table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $retention_days * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted; value prepared.
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE checked_at < %s", $cutoff ) );
		return (int) $deleted;
	}
}

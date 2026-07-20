<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Indexistic;

/**
 * Reads/writes the seoistic_indexing_log table — every Google/IndexNow
 * submission attempt, manual or automatic, with its result.
 */
final class IndexingLog {

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seoistic_indexing_log';
	}

	public static function record( string $url, string $engine, string $action, string $status, string $message = '', bool $is_manual = true ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
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
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function recent( int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d OFFSET %d', $limit, $offset ),
			ARRAY_A
		);
	}

	public static function total(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ); // phpcs:ignore WordPress.DB
	}

	public static function count_today( string $engine = '' ): int {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d 00:00:00' );
		if ( '' !== $engine ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'success' AND engine = %s AND created_at >= %s", $engine, $since ) ); // phpcs:ignore WordPress.DB
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'success' AND created_at >= %s", $since ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function last_submission_for( string $url, string $engine ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE url = %s AND engine = %s ORDER BY id DESC LIMIT 1', $url, $engine ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	public static function clear(): void {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::table() ); // phpcs:ignore WordPress.DB
	}
}
